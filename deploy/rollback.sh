#!/usr/bin/env bash
#
# Restauration manuelle après un déploiement raté dont forge-deploy.sh
# n'a pas pu se remettre tout seul (typiquement : une migration a
# échoué après avoir déjà modifié la base — voir l'étape 5 de
# forge-deploy.sh). N'efface jamais rien avant d'avoir confirmé que la
# sauvegarde donnée existe et semble valide.
#
# Utilisation (depuis l'onglet "Commands" de Forge, ou en SSH, dans le
# dossier du site) :
#   bash deploy/rollback.sh /home/forge/backups/afrolia-db/afrolia-db-XXXXXXXX_XXXXXX.sql.gz <sha-git-precedent>
#
# Les deux valeurs exactes sont affichées par forge-deploy.sh au moment
# de l'échec.

set -e
set -o pipefail
# Même raison que dans forge-deploy.sh : "gunzip -c fichier | mysql ..."
# doit remonter l'échec de n'importe quel maillon du pipeline, pas
# seulement du dernier.

BACKUP_FILE="$1"
PREVIOUS_SHA="$2"

if [ -z "$BACKUP_FILE" ] || [ -z "$PREVIOUS_SHA" ]; then
    echo "Usage : rollback.sh <fichier-sauvegarde.sql.gz> <sha-git-precedent>"
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "ÉCHEC : le fichier de sauvegarde '$BACKUP_FILE' n'existe pas. Rien n'a été touché."
    exit 1
fi

get_env() {
    grep -E "^$1=" .env | head -n1 | cut -d '=' -f2- | tr -d '"'
}

DB_CONN=$(get_env DB_CONNECTION)
DB_HOST_V=$(get_env DB_HOST)
DB_PORT_V=$(get_env DB_PORT)
DB_DATABASE_V=$(get_env DB_DATABASE)
DB_USERNAME_V=$(get_env DB_USERNAME)
DB_PASSWORD_V=$(get_env DB_PASSWORD)

echo "==> [1/3] Restauration du code vers $PREVIOUS_SHA"
git reset --hard "$PREVIOUS_SHA"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> [2/3] Restauration de la base de données depuis $BACKUP_FILE"
case "$DB_CONN" in
    mysql)
        gunzip -c "$BACKUP_FILE" | MYSQL_PWD="$DB_PASSWORD_V" mysql \
            -h "${DB_HOST_V:-127.0.0.1}" -P "${DB_PORT_V:-3306}" -u "$DB_USERNAME_V" \
            "$DB_DATABASE_V"
        ;;
    pgsql)
        gunzip -c "$BACKUP_FILE" | PGPASSWORD="$DB_PASSWORD_V" psql \
            -h "${DB_HOST_V:-127.0.0.1}" -p "${DB_PORT_V:-5432}" -U "$DB_USERNAME_V" \
            "$DB_DATABASE_V"
        ;;
    sqlite)
        gunzip -c "$BACKUP_FILE" > "$DB_DATABASE_V"
        ;;
    *)
        echo "ÉCHEC : type de base de données non reconnu ($DB_CONN), restauration de la base impossible automatiquement."
        exit 1
        ;;
esac

echo "==> [3/3] Redémarrage et remise en ligne"
php artisan config:cache
php artisan route:cache
php artisan queue:restart
# forge-deploy.sh laisse volontairement le site en maintenance quand la
# migration échoue (voir son étape 6) : c'est ici, une fois le code et la
# base effectivement cohérents, qu'on le remet en ligne.
php artisan up

echo "Restauration terminée : code à $PREVIOUS_SHA, base restaurée depuis $BACKUP_FILE, site de nouveau en ligne."
