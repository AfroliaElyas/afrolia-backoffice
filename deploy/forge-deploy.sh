#!/usr/bin/env bash
#
# Script de déploiement à coller dans Forge : Site > Apps > Deploy Script.
# NE PAS exécuter ce fichier directement en dehors de Forge : il dépend
# des variables d'environnement que Forge fournit lui-même au moment du
# déploiement, et des macros natives Zero-Downtime de Forge
# ($CREATE_RELEASE, $ACTIVATE_RELEASE, $RESTART_QUEUES) — conservées
# telles quelles, jamais remplacées par une version maison.
#
# Afrolia utilise le déploiement "Zero-Downtime" natif de Forge, pas le
# modèle "en place" (git pull direct dans le dossier du site) qu'une
# précédente version de ce fichier supposait à tort. Avec Zero-Downtime,
# chaque déploiement crée un nouveau dossier de version
# ($FORGE_RELEASE_DIRECTORY) ; l'ancienne version reste celle réellement
# servie aux visiteurs tant que $ACTIVATE_RELEASE() n'a pas été appelé
# avec succès — donc tant que ce script n'est pas arrivé jusque-là, un
# échec n'interrompt jamais le site, quoi qu'il arrive.
#
# Logique, dans l'ordre :
#   1. Sauvegarde la base de données et VÉRIFIE qu'elle est exploitable
#      (taille ET contenu) avant de toucher à quoi que ce soit d'autre.
#   2. Installe les dépendances et construit les assets dans la nouvelle
#      version. Les tests automatiques ne sont PAS rejoués ici (demandé
#      le 24/10/2026) : la seule vérification a lieu dans GitHub
#      Actions, avant que la branche ait pu être fusionnée dans main —
#      voir deploy/README-deploiement.md pour la règle de protection de
#      branche qui rend ça obligatoire.
#   3. Migre la base. Si ça échoue : le script s'arrête ici — Forge
#      n'active jamais une version dont le déploiement n'a pas
#      entièrement réussi, donc le code ne change pas pour les
#      visiteurs — mais la base a pu être modifiée par les migrations
#      qui ont réussi avant l'échec. Le script affiche alors exactement
#      quoi exécuter dans deploy/rollback.sh pour la restaurer.
#   4. Sinon, active la nouvelle version et redémarre les files
#      d'attente (macros natives Forge).

set -e
set -o pipefail
# pipefail est indispensable ici : sans lui, "mysqldump ... | gzip > fichier"
# renvoie le code de sortie de gzip (qui réussit même si mysqldump a échoué
# avant lui, ex. identifiants refusés), pas celui de mysqldump — un échec de
# sauvegarde passerait alors inaperçu.

$CREATE_RELEASE()

cd "$FORGE_RELEASE_DIRECTORY" || { echo "ÉCHEC : impossible d'accéder au dossier de la nouvelle version (\$FORGE_RELEASE_DIRECTORY = $FORGE_RELEASE_DIRECTORY). Déploiement annulé par sécurité, rien n'a été modifié."; exit 1; }

echo "==> [1/4] Sauvegarde de la base de données avant toute modification"

# .env est partagé entre les versions par Forge (déploiement
# zero-downtime), donc disponible ici malgré le nouveau dossier de
# version.
#
# Spécifique à MySQL (moteur confirmé pour Afrolia en production —
# DB_CONNECTION dans l'Environnement Forge). Si ça change un jour, cette
# section devra être adaptée au nouveau moteur, pas élargie "au cas où"
# à plusieurs moteurs à la fois.
if [ ! -r .env ]; then
    echo "ÉCHEC : fichier .env introuvable ou illisible dans $(pwd). Déploiement annulé par sécurité, rien n'a été modifié."
    exit 1
fi

# Lit une variable de .env sans jamais arrêter le script si elle est absente
# (le "|| true" porte uniquement sur cette ligne) et ne retire que les
# guillemets qui encadrent toute la valeur — jamais un guillemet qui ferait
# partie du mot de passe lui-même.
get_env() {
    local raw
    raw=$(grep -E "^$1=" .env | head -n1 | cut -d '=' -f2-) || true
    if [[ "$raw" == \"*\" && ${#raw} -ge 2 ]]; then
        raw=${raw:1:-1}
    fi
    printf '%s' "$raw"
}

DB_CONN=$(get_env DB_CONNECTION)
DB_HOST_V=$(get_env DB_HOST)
DB_PORT_V=$(get_env DB_PORT)
DB_DATABASE_V=$(get_env DB_DATABASE)
DB_USERNAME_V=$(get_env DB_USERNAME)
DB_PASSWORD_V=$(get_env DB_PASSWORD)

if [ "$DB_CONN" != "mysql" ]; then
    echo "ÉCHEC : DB_CONNECTION=$DB_CONN dans .env, mais ce script est écrit pour MySQL uniquement. Déploiement annulé par sécurité — vérifie l'Environnement Forge avant de relancer."
    exit 1
fi

# DB_HOST et DB_PORT ont des valeurs par défaut sûres plus bas (127.0.0.1,
# 3306) : ils peuvent légitimement être absents. Le reste est obligatoire.
for paire in "DB_DATABASE_V:DB_DATABASE" "DB_USERNAME_V:DB_USERNAME" "DB_PASSWORD_V:DB_PASSWORD"; do
    variable_interne="${paire%%:*}"
    nom_dans_env="${paire##*:}"
    if [ -z "${!variable_interne}" ]; then
        echo "ÉCHEC : la variable obligatoire $nom_dans_env est absente ou vide dans .env. Déploiement annulé par sécurité, rien n'a été modifié."
        exit 1
    fi
done

BACKUP_DIR="$HOME/backups/afrolia-db"
mkdir -p "$BACKUP_DIR"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/afrolia-db-$TIMESTAMP.sql.gz"

# Si mysqldump échoue (mauvais identifiants, base inaccessible...), pipefail
# fait échouer tout le pipeline et le script s'arrête ici (set -e). Le trap
# retire le fichier partiel/vide que gzip a quand même pu créer, pour que
# "rien n'a été modifié" reste vrai même dans ce cas ; il se désarme lui-même
# juste après une sauvegarde réussie.
trap 'rm -f "$BACKUP_FILE"' EXIT
MYSQL_PWD="$DB_PASSWORD_V" mysqldump --single-transaction \
    -h "${DB_HOST_V:-127.0.0.1}" -P "${DB_PORT_V:-3306}" -u "$DB_USERNAME_V" \
    "$DB_DATABASE_V" | gzip > "$BACKUP_FILE"
trap - EXIT

BACKUP_SIZE=$(stat -c%s "$BACKUP_FILE" 2>/dev/null || stat -f%z "$BACKUP_FILE" 2>/dev/null || echo 0)
if [ "$BACKUP_SIZE" -lt 1024 ]; then
    echo "ÉCHEC : la sauvegarde fait seulement $BACKUP_SIZE octets, ce n'est pas crédible. Déploiement annulé, rien n'a été modifié."
    rm -f "$BACKUP_FILE"
    exit 1
fi

# Vérifie aussi le CONTENU, pas seulement la taille : une taille plausible
# peut encore être un fichier corrompu ou une page d'erreur compressée. Un
# dump mysqldump texte contient toujours au moins un "CREATE TABLE".
# Décompression vers un fichier temporaire puis grep sur ce fichier — jamais
# "zcat ... | grep -q", qui peut tuer zcat par SIGPIPE dès que grep trouve sa
# première correspondance et ferme son entrée ; avec pipefail, ce SIGPIPE
# ferait échouer le pipeline (donc tout le script) même si le contenu de la
# sauvegarde est parfaitement valide.
BACKUP_CHECK_TMP=$(mktemp)
gzip -dc "$BACKUP_FILE" > "$BACKUP_CHECK_TMP"
if ! grep -q "CREATE TABLE" "$BACKUP_CHECK_TMP"; then
    echo "ÉCHEC : le contenu de la sauvegarde ne ressemble pas à un dump MySQL valide (aucun « CREATE TABLE » trouvé). Déploiement annulé, rien n'a été modifié."
    rm -f "$BACKUP_FILE" "$BACKUP_CHECK_TMP"
    exit 1
fi
rm -f "$BACKUP_CHECK_TMP"
echo "Sauvegarde vérifiée (taille et contenu) : $BACKUP_FILE ($BACKUP_SIZE octets)"

# Garde les 10 dernières sauvegardes seulement, pour ne pas remplir le disque.
ls -1t "$BACKUP_DIR"/afrolia-db-*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm --

echo "==> [2/4] Installation des dépendances et construction des assets"
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci || npm install
npm run build

$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link

# Migration ponctuelle des photos envoyées avant le correctif de
# persistance du stockage (29/10/2026) : elles vivent encore dans
# public/salon de la version actuellement active ($FORGE_SITE_PATH),
# jamais partagé entre versions — contrairement à storage/, partagé par
# Forge, où les nouvelles photos vont désormais. "cp -rn" ne touche
# jamais l'ancien emplacement (aucune suppression) et n'écrase jamais un
# fichier déjà migré : sans danger de relancer ce déploiement, et sans
# danger si la migration a déjà eu lieu lors d'un déploiement précédent.
if [ -d "$FORGE_SITE_PATH/public/salon" ]; then
    mkdir -p "$FORGE_RELEASE_DIRECTORY/storage/app/public/salon"
    if ! cp -rn "$FORGE_SITE_PATH/public/salon/." "$FORGE_RELEASE_DIRECTORY/storage/app/public/salon/" 2>/dev/null; then
        echo "AVERTISSEMENT : migration des anciennes photos incomplète — rien n'est perdu (l'ancien dossier n'est jamais touché), à réessayer au prochain déploiement."
    fi
fi

echo "==> [3/4] Mise à jour de la base de données"
# Forge n'appelle $ACTIVATE_RELEASE() que si tout ce qui précède a réussi :
# tant que cette ligne n'est pas atteinte, l'ancienne version reste celle
# réellement servie aux visiteurs, quoi qu'il arrive. Seule la BASE peut
# avoir été modifiée par les migrations qui ont réussi avant un échec —
# c'est elle qu'il faut alors restaurer.
if ! $FORGE_PHP artisan migrate --force; then
    echo "ÉCHEC DE LA MIGRATION — la nouvelle version n'est pas activée (Forge n'active jamais un déploiement qui n'a pas entièrement réussi), mais la base a pu être modifiée par les migrations qui ont réussi avant l'échec."
    echo "Si la base doit être restaurée à son état d'avant ce déploiement, exécute, depuis l'onglet Commands de Forge (ou en SSH) :"
    echo "  bash \"$FORGE_RELEASE_DIRECTORY/deploy/rollback.sh\" \"$BACKUP_FILE\" \"$FORGE_SITE_PATH\""
    exit 1
fi

echo "==> [4/4] Activation de la nouvelle version et redémarrage des files d'attente"
$ACTIVATE_RELEASE()

$RESTART_QUEUES()

echo "Déploiement réussi. Sauvegarde conservée : $BACKUP_FILE"
