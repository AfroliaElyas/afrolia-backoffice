# Diagnostic + déploiement + commandes SSH restreints pour Afrolia

Une seule clé SSH dédiée, limitée par une "forced command" dans
`authorized_keys` : quelle que soit la commande demandée, le serveur
exécute toujours et uniquement `/home/forge/diagnostic-afrolia.sh`.

Depuis le 07/11/2026, ce script fait plus qu'un diagnostic : il lit la
commande réellement demandée (`$SSH_ORIGINAL_COMMAND`, fournie par SSH
malgré la "forced command") et choisit parmi un nombre **fixe** d'actions
allowlistées. Aucune commande arbitraire n'est jamais possible, aucun
secret n'est jamais affiché — voir "Pourquoi c'est sûr" plus bas.

## Actions disponibles

| Commande envoyée              | Effet                                                          |
|--------------------------------|-----------------------------------------------------------------|
| `diagnostic`                   | Lecture seule, inchangé depuis toujours (commit, storage, HTTP, Backblaze) |
| `deploy`                       | Déclenche le vrai script Forge (`deploy/forge-deploy.sh`, Zero-Downtime) — nécessite d'avoir rempli `DEPLOY_TRIGGER_URL` dans le script, voir plus bas |
| `demo-captures`                 | `php artisan demo:creer-compte-captures`                         |
| `jeko-test`                      | `php artisan jeko:tester`                                        |
| `notifications-test <identifiant>` | `php artisan notifications:tester <identifiant>` — `<identifiant>` doit ressembler à un téléphone ou un email, sinon rejeté |

Toute autre commande est rejetée avec un message d'erreur, sans rien exécuter.

## Mise à jour (une seule manipulation, depuis l'onglet Commands de Forge)

Ceci **remplace le contenu** du script déjà installé, au même
emplacement : `~/.ssh/authorized_keys` n'a besoin d'aucune modification
(la ligne `command="bash /home/forge/diagnostic-afrolia.sh"` qui s'y
trouve déjà reste valable telle quelle). La commande ci-dessous
sauvegarde d'abord l'ancien script sous un nom horodaté avant de le
remplacer — rien n'est perdu, voir "Revenir en arrière" plus bas.

Colle ceci tel quel, en une fois :

```bash
cp /home/forge/diagnostic-afrolia.sh /home/forge/diagnostic-afrolia.sh.avant-aiguilleur-$(date +%Y%m%d%H%M%S) 2>/dev/null
cat > /home/forge/diagnostic-afrolia.sh << 'SCRIPT_EOF'
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
SCRIPT_EOF
chmod 700 /home/forge/diagnostic-afrolia.sh
ls -la /home/forge/diagnostic-afrolia.sh*
echo "Installation terminée."
```

La dernière ligne (`ls -la ...`) affiche le fichier de sauvegarde créé —
note son nom exact si tu penses pouvoir vouloir revenir en arrière un jour.

### Activer l'action "deploy" (facultatif)

Si tu veux que cette clé puisse aussi déclencher un déploiement, une
seule chose à faire **en plus**, directement dans Forge (jamais ici, ni
sur GitHub) :

1. Forge → ton site → en-tête de la page → cherche l'icône de
   déclenchement de déploiement ("Deployment Trigger URL" / webhook) et
   copie l'URL.
2. Depuis l'onglet Commands de Forge, colle :
   ```bash
   sed -i 's#REMPLACE_PAR_TON_URL_DE_DECLENCHEMENT_FORGE#COLLE_TON_URL_ICI#' /home/forge/diagnostic-afrolia.sh
   ```
   en remplaçant `COLLE_TON_URL_ICI` par l'URL copiée.

Si tu ne fais pas cette étape, l'action "deploy" refuse proprement
(message d'erreur clair) au lieu d'échouer silencieusement — tout le
reste (diagnostic, demo-captures, jeko-test, notifications-test)
fonctionne sans cette étape.

Important : cette URL déclenche `deploy/forge-deploy.sh` — qui doit
d'abord avoir été collé dans Forge → ton site → Apps → "Deploy Script"
(voir `deploy/README-deploiement.md`). Si ce n'est pas encore fait,
l'action "deploy" déclenchera le script de déploiement par défaut de
Forge à la place (sans la sauvegarde ni le filet de sécurité).

## Revenir en arrière

Si le nouveau script pose problème, restaure l'ancien depuis l'onglet
Commands de Forge (remplace `HORODATAGE` par celui affiché à
l'installation) :

```bash
cp /home/forge/diagnostic-afrolia.sh.avant-aiguilleur-HORODATAGE /home/forge/diagnostic-afrolia.sh
chmod 700 /home/forge/diagnostic-afrolia.sh
echo "Restauration terminée."
```

Le contenu exact d'avant cette mise à jour reste aussi dans l'historique
Git de ce dépôt (ce fichier `deploy/diagnostic-afrolia.sh`, avant ce
commit), en plus de la sauvegarde sur le serveur.

## Pourquoi c'est sûr

- `command="..."` force le serveur à toujours exécuter ce script précis,
  quoi que le client SSH demande — impossible de faire exécuter autre
  chose avec cette clé, même en cas de fuite du secret GitHub.
- `no-pty` empêche toute session interactive (pas de terminal, pas
  d'échappement possible vers un shell).
- `no-port-forwarding` / `no-agent-forwarding` / `no-X11-forwarding`
  ferment les usages détournés classiques d'une connexion SSH.
- Le script lui-même choisit parmi une liste FIXE et courte d'actions
  (`case "$action" in ...`) : `$SSH_ORIGINAL_COMMAND` n'est jamais passé
  à `eval` ni interprété comme du code, seulement comparé à des motifs
  fixes ; le seul paramètre accepté (`notifications-test <identifiant>`)
  est validé par une expression régulière stricte (téléphone ou email
  uniquement) avant d'être transmis, toujours entre guillemets, à la
  commande Artisan correspondante.
- Aucune commande n'affiche jamais de secret (clé SSH, identifiants
  Backblaze/DB/Firebase) : `backblaze:diagnostiquer` et
  `notifications:tester` sont conçues pour ne jamais les révéler en
  clair, et l'URL de déclenchement de déploiement (si configurée) n'est
  ni affichée ni journalisée.
