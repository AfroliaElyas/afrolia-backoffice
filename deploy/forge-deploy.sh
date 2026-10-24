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
#   2. Récupère le nouveau code et installe les dépendances. Les tests
#      automatiques ne sont PAS rejoués ici (demandé le 24/10/2026) : la
#      seule vérification a lieu dans GitHub Actions, avant que la
#      branche ait pu être fusionnée dans main — voir
#      deploy/README-deploiement.md pour la règle de protection de
#      branche qui rend ça obligatoire. Si la récupération du code ou
#      l'installation échoue, restaure automatiquement le code précédent
#      (git reset), sans aucun risque pour les données.
#   3. Une fois le code en place : met le site en maintenance (page
#      d'attente propre) pour la partie réellement risquée — la
#      migration et le redémarrage des services — et le remet en ligne
#      automatiquement à la sortie du script, SAUF en cas d'échec de
#      migration, où rester en maintenance est plus sûr qu'exposer un
#      site dont le code et la base ne correspondent plus.
#   4. Si c'est la migration qui échoue : le script s'arrête, laisse le
#      site en maintenance, et affiche exactement quoi exécuter dans
#      deploy/rollback.sh — une restauration automatique de la base à ce
#      stade serait plus risquée qu'utile.
#   5. Sinon, redémarre les services et remet le site en ligne.

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

echo "==> [1/5] Sauvegarde de la base de données avant toute modification"
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

# Restaure le code précédent et quitte : utilisée pour toute panne AVANT
# la migration (récupération du code, installation, tests), où revenir à
# l'ancien code suffit à tout remettre en état sans toucher à la base.
restaurer_code_precedent_et_quitter() {
    local raison="$1"
    echo "$raison — restauration automatique de la version précédente."
    git reset --hard "$PREVIOUS_SHA"
    if ! $FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader; then
        echo "ÉCHEC CRITIQUE : impossible de réinstaller les dépendances de la version précédente."
        echo "Le site reste volontairement en maintenance. Intervention manuelle nécessaire."
        trap - EXIT
        exit 1
    fi
    echo "Code restauré à $PREVIOUS_SHA. Aucune migration n'a été jouée : la base de données n'a pas besoin d'être restaurée pour cet échec."
    echo "Sauvegarde conservée au cas où : $BACKUP_FILE"
    exit 1
}

echo "==> [2/5] Récupération du code ($FORGE_SITE_BRANCH)"
git pull origin "$FORGE_SITE_BRANCH" || restaurer_code_precedent_et_quitter "ÉCHEC DE LA RÉCUPÉRATION DU CODE"

# Pas de --no-dev à retirer après coup : les tests ne tournent plus ici
# (voir l'en-tête du fichier), donc l'installation de production se fait
# directement en une seule fois.
echo "==> [3/5] Installation des dépendances"
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    || restaurer_code_precedent_et_quitter "ÉCHEC DE L'INSTALLATION DES DÉPENDANCES"

# À partir d'ici : le code vient de GitHub Actions, déjà vérifié (branche
# protégée, voir deploy/README-deploiement.md) ; il ne reste que la
# migration et le redémarrage, la partie réellement risquée pour les
# visiteurs (schéma de base et code temporairement désynchronisés). Remis
# en ligne automatiquement à la sortie du script grâce au trap — sauf en
# cas d'échec de migration, qui le retire volontairement pour laisser le
# site en maintenance (voir plus bas).
$FORGE_PHP artisan down --retry=30 2>/dev/null || true
trap '$FORGE_PHP artisan up 2>/dev/null || true' EXIT

echo "==> [4/5] Mise en maintenance effectuée, mise à jour de la base de données"
if ! $FORGE_PHP artisan migrate --force; then
    echo "ÉCHEC DE LA MIGRATION — le code est resté sur la nouvelle version mais la base peut être incohérente."
    echo "Le site reste volontairement EN MAINTENANCE (pas de remise en ligne automatique ici) pour ne jamais exposer cet état intermédiaire."
    echo "Exécute deploy/rollback.sh avec :"
    echo "  Sauvegarde : $BACKUP_FILE"
    echo "  Commit précédent : $PREVIOUS_SHA"
    trap - EXIT
    exit 1
fi

echo "==> [5/5] Redémarrage des services"
( flock -w 10 9 || exit 1
    sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock

$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan queue:restart

echo "Déploiement réussi. Sauvegarde conservée : $BACKUP_FILE"
# Le trap posé plus haut remet le site en ligne ici (sortie normale du script).
