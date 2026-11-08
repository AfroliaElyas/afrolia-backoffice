# Diagnostic + Jèko SSH restreint pour Afrolia

Une seule clé SSH dédiée, limitée par une "forced command" dans
`authorized_keys` : quelle que soit la commande demandée, le serveur
exécute toujours et uniquement `/home/forge/diagnostic-afrolia.sh`.

Depuis le 08/11/2026, ce script choisit parmi un nombre **fixe**
d'actions allowlistées, en lisant `$SSH_ORIGINAL_COMMAND` (fourni par
SSH malgré la "forced command"). Portée volontairement limitée aux
diagnostics en lecture seule : ni déploiement, ni création de compte,
ni envoi de notification, aucun paiement réel possible.

## Actions disponibles

| Commande envoyée | Effet |
|---|---|
| `diagnostic` | Lecture seule (commit, storage, mécanisme du cache de configuration, HTTP, Backblaze) |
| `jeko-test` | `php artisan jeko:tester` — lit le solde du magasin Jèko, ne déplace aucun argent |

Toute autre commande est rejetée avec un message d'erreur, sans rien exécuter.

## Mise à jour (une seule manipulation, depuis l'onglet Commands de Forge)

Ceci **remplace le contenu** du script déjà installé, au même
emplacement : `~/.ssh/authorized_keys` n'a besoin d'aucune
modification (la ligne `command="bash /home/forge/diagnostic-afrolia.sh"`
qui s'y trouve déjà reste valable telle quelle). La commande ci-dessous
sauvegarde d'abord l'ancien script sous un nom horodaté avant de le
remplacer — rien n'est perdu.

Colle ceci tel quel, en une fois :

```bash
cp /home/forge/diagnostic-afrolia.sh /home/forge/diagnostic-afrolia.sh.avant-jeko-$(date +%Y%m%d%H%M%S) 2>/dev/null
cat > /home/forge/diagnostic-afrolia.sh << 'SCRIPT_EOF'
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
        echo "  (à comparer à la date du commit déployé ci-dessus : si cette modification est"
        echo "   antérieure au déploiement, celui-ci n'a pas régénéré ce fichier de cache.)"
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

## Revenir en arrière

Depuis l'onglet Commands de Forge (remplace `HORODATAGE` par celui
affiché à l'installation) :

```bash
cp /home/forge/diagnostic-afrolia.sh.avant-jeko-HORODATAGE /home/forge/diagnostic-afrolia.sh
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
- Le script choisit parmi une liste FIXE et courte d'actions
  (`case "$action" in ...`) : `$SSH_ORIGINAL_COMMAND` n'est jamais passé
  à `eval` ni interprété comme du code, seulement comparé à des motifs
  fixes — tout ce qui suit un point-virgule ou toute autre tentative
  d'injection est ignoré, jamais exécuté.
- Aucune commande n'affiche jamais de secret (clé SSH, identifiants
  Backblaze/Jèko) : `backblaze:diagnostiquer` et `jeko:tester` sont
  conçues pour ne jamais les révéler en clair.
- `jeko-test` ne fait que lire le solde du magasin (aucun paiement,
  aucun transfert) : voir `app/Console/Commands/JekoTesterCommand.php`.
