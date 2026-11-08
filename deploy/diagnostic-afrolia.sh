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
# notification — seulement de quoi vérifier Backblaze et Jèko sans
# manipulation de Ruth. jeko-test ne fait que lire le solde du magasin
# (aucun argent déplacé, voir app/Console/Commands/JekoTesterCommand.php).
#
# Actions disponibles :
#   diagnostic — comportement inchangé depuis toujours (lecture seule)
#   jeko-test  — php artisan jeko:tester (lecture seule, aucun paiement)

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

  *)
    echo "ERREUR : action inconnue ou non autorisée." >&2
    exit 1
    ;;
esac
