#!/usr/bin/env bash
#
# Aiguilleur Afrolia — installé sur le serveur Forge à
# /home/forge/diagnostic-afrolia.sh (même emplacement qu'avant : aucune
# modification de ~/.ssh/authorized_keys n'est nécessaire pour cette mise
# à jour, voir deploy/README-diagnostic-ssh.md), déclenché uniquement via
# une clé SSH dédiée, restreinte par une "forced command".
#
# Avant le 07/11/2026, ce fichier ne faisait QUE le diagnostic ci-dessous
# et ignorait volontairement $SSH_ORIGINAL_COMMAND (son contenu était
# alors identique à l'intérieur du bloc "diagnostic*" ci-dessous). Depuis,
# une "forced command" fournit malgré tout $SSH_ORIGINAL_COMMAND (la
# commande réellement demandée par le client SSH) : ce fichier s'en sert
# désormais pour choisir entre un nombre FIXE d'actions, chacune
# allowlistée explicitement — jamais une commande arbitraire, jamais un
# secret affiché. Toute commande non reconnue est rejetée.
#
# Actions disponibles :
#   diagnostic            — comportement inchangé depuis toujours (lecture seule)
#   deploy                — déclenche le vrai script de déploiement Forge
#                            (deploy/forge-deploy.sh, Zero-Downtime natif),
#                            via DEPLOY_TRIGGER_URL ci-dessous
#   demo-captures          — php artisan demo:creer-compte-captures
#   jeko-test               — php artisan jeko:tester
#   notifications-test <id> — php artisan notifications:tester <id>
#                             (id validé : téléphone ou email uniquement)

set -euo pipefail

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

# À remplir manuellement après installation, UNIQUEMENT si l'action
# "deploy" doit être utilisable depuis cette clé : l'URL de déclenchement
# de déploiement de Forge (site Forge > en-tête de la page > icône de
# déclenchement / "Deployment Trigger URL"). Elle déclenche le VRAI
# script de déploiement déjà préparé et revu (deploy/forge-deploy.sh)
# dans son contexte Zero-Downtime natif ($CREATE_RELEASE,
# $ACTIVATE_RELEASE...) — jamais une version reconstruite à la main ici,
# qui perdrait ces garanties. Cette URL reste uniquement dans ce fichier,
# sur le serveur : jamais partagée avec Claude, jamais commise sur
# GitHub. Si "deploy" n'est pas utilisée depuis cette clé (déploiement
# fait autrement, ex. bouton Forge ou Quick Deploy), laisse la ligne
# telle quelle : l'action refusera proprement plutôt que d'échouer
# silencieusement.
DEPLOY_TRIGGER_URL="REMPLACE_PAR_TON_URL_DE_DECLENCHEMENT_FORGE"

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

  deploy*)
    if [ "$DEPLOY_TRIGGER_URL" = "REMPLACE_PAR_TON_URL_DE_DECLENCHEMENT_FORGE" ]; then
      echo "ERREUR : DEPLOY_TRIGGER_URL n'a pas été configurée dans ce script." >&2
      exit 1
    fi
    echo "Déploiement déclenché via le mécanisme officiel Forge (asynchrone) :"
    echo "exécute deploy/forge-deploy.sh dans son vrai contexte Zero-Downtime."
    echo "Relance l'action 'diagnostic' dans 1-2 minutes pour confirmer le nouveau commit."
    curl -fsS -X POST "$DEPLOY_TRIGGER_URL" -o /dev/null
    ;;

  demo-captures*)
    (cd "$SITE_DIR" && php artisan demo:creer-compte-captures)
    ;;

  jeko-test*)
    (cd "$SITE_DIR" && php artisan jeko:tester)
    ;;

  notifications-test\ *)
    identifiant="${action#notifications-test }"
    # Téléphone (chiffres, espace, +) ou email simple uniquement — rien
    # d'autre n'est jamais transmis à la commande artisan.
    if [[ "$identifiant" =~ ^[+0-9[:space:]]{6,20}$ ]] || [[ "$identifiant" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]]; then
      (cd "$SITE_DIR" && php artisan notifications:tester "$identifiant")
    else
      echo "ERREUR : identifiant invalide (ni téléphone ni email reconnu)." >&2
      exit 1
    fi
    ;;

  *)
    echo "ERREUR : action inconnue ou non autorisée." >&2
    exit 1
    ;;
esac
