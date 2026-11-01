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
# Recherche insensible à la casse (confirmé le 01/11/2026 : le dossier réel
# s'appelle "Afrolia-app.com", avec un A majuscule, pas "afrolia-app.com").
SITE_ROOT=$(find /home/forge -mindepth 1 -maxdepth 1 -type d -iname 'afrolia-app.com' | head -n1)

if [ -z "$SITE_ROOT" ]; then
    echo "ÉCHEC : dossier du site introuvable (recherche insensible à la casse)."
    echo "Contenu de /home/forge :"
    ls -la /home/forge
    exit 1
elif [ -d "$SITE_ROOT/current" ]; then
    SITE_DIR="$SITE_ROOT/current"
elif [ -f "$SITE_ROOT/artisan" ]; then
    SITE_DIR="$SITE_ROOT"
else
    echo "ÉCHEC : dossier trouvé ($SITE_ROOT) mais ni /current ni artisan à l'intérieur."
    echo "Contenu de $SITE_ROOT :"
    ls -la "$SITE_ROOT"
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
