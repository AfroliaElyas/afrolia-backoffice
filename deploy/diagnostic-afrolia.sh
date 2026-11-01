#!/usr/bin/env bash
#
# Script de diagnostic en lecture seule pour Afrolia, destiné à être installé
# sur le serveur Forge à /home/forge/diagnostic-afrolia.sh et déclenché
# uniquement via une clé SSH dédiée, restreinte par une "forced command"
# dans ~/.ssh/authorized_keys (voir deploy/README-diagnostic-ssh.md).
#
# Important : ce script ignore volontairement tout argument ou variable
# $SSH_ORIGINAL_COMMAND — avec une "forced command", le serveur l'exécute
# toujours tel quel, quoi que le client SSH ait demandé. Il ne fait que lire
# : aucune écriture, aucune suppression, aucun sudo.

set -e

echo "=== Recherche du dossier du site ==="
if [ -d "/home/forge/afrolia-app.com/current" ]; then
    SITE_DIR="/home/forge/afrolia-app.com/current"
elif [ -f "/home/forge/afrolia-app.com/artisan" ]; then
    SITE_DIR="/home/forge/afrolia-app.com"
else
    echo "ÉCHEC : dossier du site introuvable aux emplacements attendus."
    echo "Contenu de /home/forge :"
    ls -la /home/forge
    exit 1
fi
echo "Dossier trouvé : $SITE_DIR"
cd "$SITE_DIR"

echo
echo "=== Commit déployé ==="
git log -1 --format='%H (%ad) — %s' --date=iso

echo
echo "=== Persistance du dossier storage (doit pointer hors de ce dossier de version) ==="
echo -n "storage -> "
readlink -f storage

echo
echo "=== Diagnostic Backblaze (ne révèle jamais les secrets) ==="
php artisan backblaze:diagnostiquer
