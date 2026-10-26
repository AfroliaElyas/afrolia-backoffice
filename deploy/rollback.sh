#!/usr/bin/env bash
#
# Restauration manuelle après un déploiement Zero-Downtime (Forge) raté
# dont le filet de sécurité intégré n'a pas pu se rattraper tout seul —
# typiquement une migration qui a échoué après avoir déjà modifié la
# base (voir l'étape correspondante du script de déploiement).
#
# Contrairement à l'ancienne version de ce fichier (modèle "en place",
# avec git reset), il n'y a rien à restaurer côté code avec git : Forge
# garde déjà les anciennes versions sur le disque (dossier "releases"),
# il suffit de rebasculer le lien symbolique "current" vers l'une
# d'elles — c'est exactement ce que fait $ACTIVATE_RELEASE() pendant un
# déploiement normal, reproduit ici à la main.
#
# Cas le plus courant (migration qui échoue dans forge-deploy.sh) :
# $ACTIVATE_RELEASE() n'a alors jamais été appelé, "current" pointe
# donc déjà sur la bonne version — ce script le détecte tout seul et ne
# touche alors qu'à la base, sans rebasculer aucun lien.
#
# Utilisation (depuis l'onglet "Commands" de Forge, ou en SSH) :
#   bash deploy/rollback.sh <fichier-sauvegarde.sql.gz> <racine-du-site> [dossier-de-la-version-precedente]
#
#   <racine-du-site>                    ex: /home/forge/afrolia-app.com
#                                        (le dossier qui contient releases/ et current)
#   [dossier-de-la-version-precedente]  optionnel — si omis, le script
#                                        prend automatiquement la version
#                                        juste avant celle actuellement active.
#
# Le fichier de sauvegarde exact à utiliser est affiché par le script de
# déploiement au moment de l'échec.

set -e
set -o pipefail

BACKUP_FILE="$1"
SITE_ROOT="$2"
PREVIOUS_RELEASE_DIR="$3"

if [ -z "$BACKUP_FILE" ] || [ -z "$SITE_ROOT" ]; then
    echo "Usage : rollback.sh <fichier-sauvegarde.sql.gz> <racine-du-site> [dossier-de-la-version-precedente]"
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "ÉCHEC : le fichier de sauvegarde '$BACKUP_FILE' n'existe pas. Rien n'a été touché."
    exit 1
fi

CURRENT_LINK="$SITE_ROOT/current"
RELEASES_DIR="$SITE_ROOT/releases"

if [ ! -d "$RELEASES_DIR" ]; then
    echo "ÉCHEC : '$RELEASES_DIR' introuvable — vérifie la racine du site passée en argument. Rien n'a été touché."
    exit 1
fi

REACTIVER=1

if [ -z "$PREVIOUS_RELEASE_DIR" ]; then
    echo "==> Aucune version précédente précisée : détection automatique dans $RELEASES_DIR"
    ACTIVE_RELEASE=$(readlink -f "$CURRENT_LINK" 2>/dev/null) || true
    if [ -z "$ACTIVE_RELEASE" ]; then
        echo "ÉCHEC : impossible de lire le lien '$CURRENT_LINK' pour savoir quelle version est active. Précise toi-même le dossier de la version précédente en 3e argument. Rien n'a été touché."
        exit 1
    fi

    # Triés par nom : les dossiers de version Forge sont des horodatages,
    # donc l'ordre alphabétique correspond aussi à l'ordre chronologique.
    mapfile -t RELEASES < <(find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort)

    INDEX_ACTIF=-1
    for i in "${!RELEASES[@]}"; do
        if [ "$RELEASES_DIR/${RELEASES[$i]}" = "$ACTIVE_RELEASE" ]; then
            INDEX_ACTIF=$i
            break
        fi
    done

    if [ "$INDEX_ACTIF" -eq -1 ]; then
        echo "ÉCHEC : la version active ('$ACTIVE_RELEASE') est introuvable dans '$RELEASES_DIR'. Précise toi-même le dossier de la version précédente en 3e argument. Rien n'a été touché."
        exit 1
    fi

    DERNIER_INDEX=$(( ${#RELEASES[@]} - 1 ))

    if [ "$INDEX_ACTIF" -lt "$DERNIER_INDEX" ]; then
        # La version active n'est PAS la plus récente sur le disque : un
        # déploiement plus récent existe mais n'a jamais été activé
        # (cas normal d'une migration qui échoue avant $ACTIVATE_RELEASE()).
        # La bonne version est déjà en ligne, rien à réactiver — seule la
        # base a besoin d'être restaurée.
        echo "    Version active déjà correcte (un déploiement plus récent existe mais n'a jamais été activé) : rien à réactiver, seule la base sera restaurée."
        REACTIVER=0
        PREVIOUS_RELEASE_DIR="$ACTIVE_RELEASE"
    else
        if [ "$INDEX_ACTIF" -le 0 ]; then
            echo "ÉCHEC : impossible de déterminer automatiquement une version précédente (c'est déjà la plus ancienne conservée dans '$RELEASES_DIR'). Précise toi-même le dossier en 3e argument. Rien n'a été touché."
            exit 1
        fi
        PREVIOUS_RELEASE_DIR="$RELEASES_DIR/${RELEASES[$((INDEX_ACTIF - 1))]}"
        echo "    Version précédente détectée : $PREVIOUS_RELEASE_DIR"
    fi
fi

if [ ! -d "$PREVIOUS_RELEASE_DIR" ] || [ ! -f "$PREVIOUS_RELEASE_DIR/artisan" ]; then
    echo "ÉCHEC : '$PREVIOUS_RELEASE_DIR' n'existe pas ou ne ressemble pas à une version Laravel valide (pas de fichier 'artisan'). Rien n'a été touché."
    exit 1
fi

if [ ! -r "$PREVIOUS_RELEASE_DIR/.env" ]; then
    echo "ÉCHEC : '$PREVIOUS_RELEASE_DIR/.env' introuvable ou illisible. Rien n'a été touché."
    exit 1
fi

# Lit une variable de .env sans jamais arrêter le script si elle est
# absente (le "|| true" porte uniquement sur cette ligne) et ne retire
# que les guillemets qui encadrent toute la valeur — jamais un guillemet
# qui ferait partie du mot de passe lui-même.
get_env() {
    local raw
    raw=$(grep -E "^$1=" "$PREVIOUS_RELEASE_DIR/.env" | head -n1 | cut -d '=' -f2-) || true
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
    echo "ÉCHEC : DB_CONNECTION=$DB_CONN dans .env, mais ce script ne sait restaurer que MySQL. Rien n'a été touché — restauration manuelle nécessaire."
    exit 1
fi

for paire in "DB_DATABASE_V:DB_DATABASE" "DB_USERNAME_V:DB_USERNAME" "DB_PASSWORD_V:DB_PASSWORD"; do
    variable_interne="${paire%%:*}"
    nom_dans_env="${paire##*:}"
    if [ -z "${!variable_interne}" ]; then
        echo "ÉCHEC : la variable obligatoire $nom_dans_env est absente ou vide dans .env. Rien n'a été touché."
        exit 1
    fi
done

echo "==> [1/4] Mise en maintenance (le temps de restaurer la base et de changer de version)"
"$CURRENT_LINK/artisan" down --retry=30 2>/dev/null || true

echo "==> [2/4] Restauration de la base de données depuis $BACKUP_FILE"
if ! gunzip -c "$BACKUP_FILE" | MYSQL_PWD="$DB_PASSWORD_V" mysql \
    -h "${DB_HOST_V:-127.0.0.1}" -P "${DB_PORT_V:-3306}" -u "$DB_USERNAME_V" \
    "$DB_DATABASE_V"; then
    echo "ÉCHEC CRITIQUE : la restauration de la base a échoué en cours de route — elle peut être dans un état intermédiaire incohérent. Le site reste volontairement en maintenance. Intervention manuelle nécessaire avant de le remettre en ligne."
    exit 1
fi

if [ "$REACTIVER" -eq 1 ]; then
    echo "==> [3/4] Réactivation de la version précédente ($PREVIOUS_RELEASE_DIR)"
    ln -nfs "$PREVIOUS_RELEASE_DIR" "$CURRENT_LINK"
else
    echo "==> [3/4] Version déjà correcte — aucun changement de version nécessaire"
fi

echo "==> [4/4] Redémarrage des services et remise en ligne"
# Le nom exact du service PHP-FPM dépend de la version de PHP du site
# (ex. php8.3-fpm) ; Forge ne fournit ce nom que pendant un déploiement
# normal ($FORGE_PHP_FPM), pas ici. On essaie les noms courants : sans
# ce rechargement, PHP peut continuer à servir l'ancien code en mémoire
# (cache de chemins réels) même après le changement du lien symbolique.
FPM_RECHARGE=0
for fpm in php8.6-fpm php8.5-fpm php8.4-fpm php8.3-fpm php8.2-fpm php8.1-fpm; do
    if sudo -S service "$fpm" status >/dev/null 2>&1; then
        sudo -S service "$fpm" reload
        echo "    $fpm rechargé."
        FPM_RECHARGE=1
        break
    fi
done
if [ "$FPM_RECHARGE" -eq 0 ]; then
    echo "    AVERTISSEMENT : aucun service PHP-FPM reconnu n'a pu être rechargé automatiquement. Si le site affiche encore l'ancien comportement après cette restauration, recharge-le manuellement (ex. sudo service phpX.Y-fpm reload, en remplaçant X.Y par la version PHP du site)."
fi

"$CURRENT_LINK/artisan" config:cache
"$CURRENT_LINK/artisan" route:cache
"$CURRENT_LINK/artisan" queue:restart
"$CURRENT_LINK/artisan" up

if [ "$REACTIVER" -eq 1 ]; then
    echo "Restauration terminée : version active = $PREVIOUS_RELEASE_DIR, base restaurée depuis $BACKUP_FILE, site de nouveau en ligne."
else
    echo "Restauration terminée : version active inchangée ($PREVIOUS_RELEASE_DIR), base restaurée depuis $BACKUP_FILE, site de nouveau en ligne."
fi
