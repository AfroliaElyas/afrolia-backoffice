#!/usr/bin/env bash
#
# Script de déploiement à coller dans Forge : Site > Apps > Deploy Script.
# NE PAS exécuter ce fichier directement en dehors de Forge : il dépend
# des variables d'environnement que Forge fournit lui-même au moment du
# déploiement ($FORGE_PHP, $FORGE_COMPOSER, $FORGE_SITE_BRANCH,
# $FORGE_PHP_FPM) et s'exécute déjà depuis le dossier du site.
#
# Logique, dans l'ordre :
#   1. Sauvegarde la base de données et VÉRIFIE que la sauvegarde est
#      exploitable avant de toucher à quoi que ce soit d'autre.
#   2. Récupère le nouveau code.
#   3. Rejoue les tests automatiques comme filet de sécurité final (la
#      vérification principale a déjà eu lieu dans GitHub Actions avant
#      que cette branche ait pu être fusionnée dans main — voir
#      deploy/README-deploiement.md).
#   4. Si les tests échouent ICI : restaure automatiquement le code
#      précédent (git reset) avant même de toucher aux migrations, donc
#      sans risque pour les données. Le site continue de tourner sur
#      l'ancienne version.
#   5. Si c'est la migration qui échoue (plus rare, après des tests
#      pourtant réussis) : le script s'arrête et affiche exactement quoi
#      exécuter dans deploy/rollback.sh — une restauration automatique
#      de la base à ce stade serait plus risquée qu'utile.
#   6. Sinon, redémarre les services normalement.

set -e
set -o pipefail
# pipefail est indispensable ici : sans lui, "mysqldump ... | gzip > fichier"
# renvoie le code de sortie de gzip (qui réussit même si mysqldump a échoué
# avant lui, ex. identifiants refusés), pas celui de mysqldump — un échec de
# sauvegarde passerait alors inaperçu (relevé en revue le 24/10/2026).

BACKUP_DIR="$HOME/backups/afrolia-db"
mkdir -p "$BACKUP_DIR"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/afrolia-db-$TIMESTAMP.sql.gz"
PREVIOUS_SHA=$(git rev-parse HEAD)

get_env() {
    grep -E "^$1=" .env | head -n1 | cut -d '=' -f2- | tr -d '"'
}

echo "==> [1/6] Sauvegarde de la base de données avant toute modification"
DB_CONN=$(get_env DB_CONNECTION)
DB_HOST_V=$(get_env DB_HOST)
DB_PORT_V=$(get_env DB_PORT)
DB_DATABASE_V=$(get_env DB_DATABASE)
DB_USERNAME_V=$(get_env DB_USERNAME)
DB_PASSWORD_V=$(get_env DB_PASSWORD)

case "$DB_CONN" in
    mysql)
        MYSQL_PWD="$DB_PASSWORD_V" mysqldump --single-transaction \
            -h "${DB_HOST_V:-127.0.0.1}" -P "${DB_PORT_V:-3306}" -u "$DB_USERNAME_V" \
            "$DB_DATABASE_V" | gzip > "$BACKUP_FILE"
        ;;
    pgsql)
        PGPASSWORD="$DB_PASSWORD_V" pg_dump \
            -h "${DB_HOST_V:-127.0.0.1}" -p "${DB_PORT_V:-5432}" -U "$DB_USERNAME_V" \
            "$DB_DATABASE_V" | gzip > "$BACKUP_FILE"
        ;;
    sqlite)
        gzip -c "$DB_DATABASE_V" > "$BACKUP_FILE"
        ;;
    *)
        echo "Type de base de données non reconnu ($DB_CONN) : sauvegarde impossible, déploiement annulé par sécurité."
        exit 1
        ;;
esac

# Vérification : une sauvegarde de quelques octets est un fichier vide ou
# une erreur silencieuse (mauvais mot de passe, base inaccessible), pas
# une vraie sauvegarde exploitable.
BACKUP_SIZE=$(stat -c%s "$BACKUP_FILE" 2>/dev/null || stat -f%z "$BACKUP_FILE" 2>/dev/null || echo 0)
if [ "$BACKUP_SIZE" -lt 1024 ]; then
    echo "ÉCHEC : la sauvegarde fait seulement $BACKUP_SIZE octets, ce n'est pas crédible. Déploiement annulé, rien n'a été modifié."
    rm -f "$BACKUP_FILE"
    exit 1
fi
echo "Sauvegarde vérifiée : $BACKUP_FILE ($BACKUP_SIZE octets)"

# Garde les 10 dernières sauvegardes seulement, pour ne pas remplir le disque.
ls -1t "$BACKUP_DIR"/afrolia-db-*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm --

echo "==> [2/6] Récupération du code ($FORGE_SITE_BRANCH)"
git pull origin "$FORGE_SITE_BRANCH"

# Installation AVEC les dépendances de dev (donc PHPUnit/Pest) : artisan
# test en a besoin pour tourner. --no-dev est appliqué seulement après coup,
# une fois les tests passés, pour la version qui tourne réellement en
# production. Sans ça, cette étape échouait toujours : "artisan test"
# dépend d'un paquet retiré par --no-dev juste avant (relevé en revue le
# 24/10/2026).
echo "==> [3/6] Installation des dépendances (avec les outils de test)"
$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader

echo "==> [4/6] Vérification finale (filet de sécurité)"
if ! $FORGE_PHP artisan test; then
    echo "ÉCHEC DES TESTS APRÈS MISE À JOUR DU CODE — restauration automatique de la version précédente."
    git reset --hard "$PREVIOUS_SHA"
    $FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    echo "Code restauré à $PREVIOUS_SHA. Aucune migration n'a été jouée : la base de données n'a pas besoin d'être restaurée pour cet échec."
    echo "Sauvegarde conservée au cas où : $BACKUP_FILE"
    exit 1
fi

# Les tests ont tourné avec les outils de dev présents ; on repasse sur
# l'installation de production (sans ces outils) avant de toucher à la base.
echo "==> [4b/6] Réinstallation sans les outils de développement (production)"
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> [5/6] Mise à jour de la base de données"
if ! $FORGE_PHP artisan migrate --force; then
    echo "ÉCHEC DE LA MIGRATION — le code est resté sur la nouvelle version mais la base peut être incohérente."
    echo "Exécute deploy/rollback.sh avec :"
    echo "  Sauvegarde : $BACKUP_FILE"
    echo "  Commit précédent : $PREVIOUS_SHA"
    exit 1
fi

echo "==> [6/6] Redémarrage des services"
( flock -w 10 9 || exit 1
    sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock

$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan queue:restart

echo "Déploiement réussi. Sauvegarde conservée : $BACKUP_FILE"
