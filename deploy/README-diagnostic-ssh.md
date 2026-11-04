# Diagnostic SSH restreint pour Afrolia

Clé SSH dédiée, limitée par une "forced command" dans `authorized_keys` :
quelle que soit la commande demandée, le serveur exécute toujours et
uniquement `diagnostic-afrolia.sh` (lecture seule, aucun paramètre accepté).

## Installation (une seule fois, depuis l'onglet Commands de Forge)

Remplace `COLLE_TA_CLE_PUBLIQUE_ICI` par le contenu de ta clé publique
(le fichier qui se termine par `.pub`), puis colle le tout tel quel :

```bash
cat > /home/forge/diagnostic-afrolia.sh << 'SCRIPT_EOF'
#!/usr/bin/env bash
set -e
SITE_DIR="/home/forge/afrolia-app.com/current"
cd "$SITE_DIR"
echo "=== Commit déployé ==="
git log -1 --format='%H (%ad) — %s' --date=iso
echo
echo "=== Persistance du dossier storage ==="
echo -n "storage -> "
readlink -f storage
echo
echo "=== Diagnostic Backblaze ==="
php artisan backblaze:diagnostiquer
SCRIPT_EOF
chmod 700 /home/forge/diagnostic-afrolia.sh
echo 'command="bash /home/forge/diagnostic-afrolia.sh",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty COLLE_TA_CLE_PUBLIQUE_ICI' >> ~/.ssh/authorized_keys
echo "Installation terminée."
```

## Pourquoi c'est sûr

- `command="..."` force le serveur à toujours exécuter ce script précis,
  quoi que le client SSH demande — impossible de faire exécuter autre
  chose avec cette clé, même en cas de fuite du secret GitHub.
- `no-pty` empêche toute session interactive (pas de terminal, pas
  d'échappement possible vers un shell).
- `no-port-forwarding` / `no-agent-forwarding` / `no-X11-forwarding`
  ferment les usages détournés classiques d'une connexion SSH.
- Le script lui-même ne contient que des lectures (`git log`, `readlink`,
  une commande Artisan déjà conçue pour ne jamais afficher de secret) :
  aucune écriture, aucune suppression, aucun `sudo`.
- `>> ~/.ssh/authorized_keys` ajoute une ligne, n'écrase jamais les clés
  existantes.
