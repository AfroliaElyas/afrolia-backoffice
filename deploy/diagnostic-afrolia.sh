#!/usr/bin/env bash
#
# Aiguilleur Afrolia — installé sur le serveur Forge à
# /home/forge/diagnostic-afrolia.sh (même emplacement qu'avant : aucune
# modification de ~/.ssh/authorized_keys n'est nécessaire pour cette mise
# à jour, voir deploy/README-diagnostic-ssh.md), déclenché uniquement via
# une clé SSH dédiée, restreinte par une "forced command".
#
# Avant le 08/11/2026, ce fichier ne faisait QUE le diagnostic ci-dessous
# et ignorait volontairement $SSH_ORIGINAL_COMMAND. Depuis, une "forced
# command" fournit malgré tout $SSH_ORIGINAL_COMMAND (la commande
# réellement demandée par le client SSH) : ce fichier s'en sert désormais
# pour choisir entre un nombre FIXE d'actions, chacune allowlistée
# explicitement — jamais une commande arbitraire, jamais un secret
# affiché. Toute commande non reconnue est rejetée.
#
# Portée volontairement limitée aux diagnostics en lecture seule (demandé
# le 08/11/2026) : ni déploiement, ni création de compte, ni envoi de
# notification. jeko-test ne fait que lire le solde du magasin (aucun
# argent déplacé). recuperer-sauvegarde-github (ajoutée le 13/11/2026) ne
# fait que LIRE des fichiers déjà produits par le cron Laravel existant
# (storage/app/sauvegardes-github-pretes/) et les retransmettre encodés en
# base64 : aucune écriture, aucune commande artisan déclenchée par cette
# action précise — toujours strictement en lecture, exactement comme
# "diagnostic" et "jeko-test", conformément à la restriction du 08/11/2026.
#
# Actions disponibles :
#   diagnostic — comportement inchangé depuis toujours (lecture seule), plus
#                depuis le 08/11/2026 une vérification du mécanisme de cache
#                de configuration (voir plus bas) : sur ce serveur, chaque
#                diagnostic Backblaze/Jèko a systématiquement trouvé la
#                configuration "EN CACHE", y compris juste après un
#                déploiement terminé avec succès contenant une valeur
#                corrigée (JEKO_API_KEY_ID, observé ce jour). Cette nouvelle
#                section détermine si bootstrap/cache est un lien symbolique
#                partagé entre déploiements (auquel cas un cache généré une
#                fois persiste tant que rien ne le régénère explicitement),
#                sans jamais modifier quoi que ce soit — lecture seule,
#                aucun secret affiché.
#   jeko-test  — php artisan jeko:tester (lecture seule, aucun paiement)
#   recuperer-sauvegarde-github — lit storage/app/sauvegardes-github-pretes/
#                (manifest.json + fichiers chiffrés déjà produits par le
#                cron sauvegarde:github existant) et les imprime encodés en
#                base64, pour que le workflow GitHub Actions
#                sauvegarde-github.yml les récupère et les publie dans les
#                Releases du dépôt — avec le jeton automatique GITHUB_TOKEN,
#                jamais un jeton personnel. Ne modifie, n'exécute et ne
#                supprime jamais rien.

set -e

echo "=== Recherche du dossier du site ==="
# Recherche insensible à la casse (confirmé le 01/11/2026 : le dossier réel
# s'appelle "Afrolia-app.com", avec un A majuscule, pas "afrolia-app.com").
SITE_ROOT=$(find /home/forge -mindepth 1 -maxdepth 1 -type d -iname 'afrolia-app.com' | head -n1)

if [ -z "$SITE_ROOT" ]; then
    echo "ÉCHEC : dossier du site introuvable (recherche insensible à la casse)." >&2
    echo "Contenu de /home/forge :" >&2
    ls -la /home/forge >&2
    exit 1
elif [ -d "$SITE_ROOT/current" ]; then
    SITE_DIR="$SITE_ROOT/current"
elif [ -f "$SITE_ROOT/artisan" ]; then
    SITE_DIR="$SITE_ROOT"
else
    echo "ÉCHEC : dossier trouvé ($SITE_ROOT) mais ni /current ni artisan à l'intérieur." >&2
    echo "Contenu de $SITE_ROOT :" >&2
    ls -la "$SITE_ROOT" >&2
    exit 1
fi
echo "Dossier trouvé : $SITE_DIR"

action="${SSH_ORIGINAL_COMMAND:-}"

case "$action" in
  diagnostic*)
    echo
    echo "=== Commit déployé ==="
    git -C "$SITE_DIR" log -1 --format='%H (%ad) — %s' --date=iso

    echo
    echo "=== Persistance du dossier storage (doit pointer hors de ce dossier de version) ==="
    echo -n "storage -> "
    readlink -f "$SITE_DIR/storage"

    echo
    echo "=== Mécanisme du cache de configuration (bootstrap/cache) ==="
    # La date du commit (ci-dessus) est celle où le code a été écrit/poussé,
    # pas celle où Forge l'a réellement déployé sur CE serveur. Forge bascule
    # le lien symbolique "current" vers la nouvelle version à la toute fin
    # de chaque déploiement : la date de modification du lien LUI-MÊME (pas
    # de sa cible — "stat" sans -L renvoie les métadonnées du lien) est donc
    # la meilleure approximation disponible ici de l'heure réelle du dernier
    # déploiement (remarque de Ruth du 08/11/2026 : la date du commit seule
    # ne suffit pas à le prouver).
    if [ -L "$SITE_ROOT/current" ]; then
        echo "Heure réelle du dernier déploiement (bascule du lien 'current') : $(stat -c '%y' "$SITE_ROOT/current")"
    else
        echo "'$SITE_ROOT/current' n'est pas un lien symbolique : heure réelle du déploiement non disponible par ce moyen."
    fi
    if [ -L "$SITE_DIR/bootstrap/cache" ]; then
        echo "bootstrap/cache est un LIEN SYMBOLIQUE -> $(readlink -f "$SITE_DIR/bootstrap/cache")"
        echo "  → ce dossier est donc PARTAGÉ entre déploiements : un cache généré une fois"
        echo "    y survit tant que rien ne le régénère explicitement après chaque déploiement."
    else
        echo "bootstrap/cache est un dossier normal de cette version (pas de lien symbolique)."
    fi
    if [ -L "$SITE_DIR/bootstrap/cache/config.php" ]; then
        echo "bootstrap/cache/config.php est lui-même un LIEN SYMBOLIQUE -> $(readlink -f "$SITE_DIR/bootstrap/cache/config.php")"
    elif [ -f "$SITE_DIR/bootstrap/cache/config.php" ]; then
        echo "bootstrap/cache/config.php existe, dernière modification : $(date -r "$SITE_DIR/bootstrap/cache/config.php" -Iseconds)"
        echo "  (à comparer à l'heure réelle du déploiement ci-dessus, PAS à la date du commit :"
        echo "   si cette modification est antérieure à la bascule de 'current', le déploiement"
        echo "   n'a pas régénéré ce fichier de cache.)"
    else
        echo "bootstrap/cache/config.php n'existe pas à cet instant (pas de cache actif)."
    fi

    echo
    echo "=== État du site (HTTP, depuis le serveur lui-même) ==="
    HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "https://afrolia-app.com" 2>/dev/null || echo "000")
    echo "Code HTTP reçu pour https://afrolia-app.com : $HTTP_CODE"

    echo
    echo "=== Diagnostic Backblaze (ne révèle jamais les secrets) ==="
    (cd "$SITE_DIR" && php artisan backblaze:diagnostiquer)
    ;;

  jeko-test*)
    (cd "$SITE_DIR" && php artisan jeko:tester)
    ;;

  recuperer-sauvegarde-github*)
    DOSSIER_PRET="$SITE_DIR/storage/app/sauvegardes-github-pretes"
    MANIFESTE="$DOSSIER_PRET/manifest.json"

    if [ ! -f "$MANIFESTE" ]; then
        echo "ERREUR : aucun manifeste trouvé dans $DOSSIER_PRET (sauvegarde:github n'a peut-être jamais tourné avec succès)." >&2
        exit 1
    fi

    # Lecture seule : cat + base64, jamais d'écriture, jamais de commande
    # arbitraire. Format en une ligne par élément (voir
    # .github/workflows/sauvegarde-github.yml pour l'analyse côté GitHub
    # Actions) : "MANIFEST <json compact sur une ligne>" puis
    # "FICHIER <nom> <contenu en base64 sur une ligne>" pour chaque fichier
    # listé dans le manifeste.
    echo "MANIFEST $(tr -d '\n' < "$MANIFESTE")"

    for nom in $(php -r '$m = json_decode(file_get_contents($argv[1]), true); foreach ($m["fichiers"] as $f) { echo $f["nom"] . "\n"; }' "$MANIFESTE"); do
        chemin="$DOSSIER_PRET/$nom"
        if [ ! -f "$chemin" ]; then
            echo "ERREUR : fichier listé dans le manifeste introuvable : $nom" >&2
            exit 1
        fi
        echo "FICHIER $nom $(base64 -w0 "$chemin")"
    done
    ;;

  *)
    echo "ERREUR : action inconnue ou non autorisée." >&2
    exit 1
    ;;
esac
