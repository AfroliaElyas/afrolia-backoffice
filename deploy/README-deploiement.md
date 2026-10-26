# Déploiement sécurisé Afrolia — guide

Ce dossier prépare un déploiement automatique plus sûr, sans rien avoir
activé ni modifié sur ton serveur Forge. Rien ici n'est en ligne tant
que tu n'as pas fait les étapes "À faire" ci-dessous toi-même.

## Comment ça s'articule

1. **GitHub** bloque la fusion dans `main` tant que les tests n'ont pas
   réussi (à activer, voir plus bas). C'est la vérification
   principale : du code qui casse quelque chose n'atteint jamais `main`.
2. **Forge** déploie automatiquement dès qu'il voit une mise à jour de
   `main` (une fois Quick Deploy activé par toi). Le script
   `forge-deploy.sh` rejoue les tests une seconde fois par sécurité,
   sauvegarde la base avant tout changement, et annule proprement le
   déploiement s'il détecte un problème.

## Ce qui a été préparé (pas encore actif)

- `deploy/forge-deploy.sh` — script à coller dans Forge.
- `deploy/rollback.sh` — restauration manuelle en cas de problème que
  le déploiement n'a pas pu résoudre tout seul.
- `.github/workflows/tests.yml` côté app Flutter (dépôt `Afrolia`) —
  lance les tests automatiques sur chaque pull request.
- Ce dépôt a déjà `.github/workflows/tests.yml` qui tourne sur chaque
  pull request et chaque push sur `main` (existait avant, rien changé).

## Ce que je n'ai pas touché

- Ton serveur Forge (aucun accès depuis cette session).
- Quick Deploy (pas activé).
- Tes clés Stripe et Jèko.
- Aucune publication Google Play / App Store.

## À faire, dans l'ordre (toi, depuis ton iPhone, en 10 minutes)

### 1. Vérifier le type de base de données réel
`forge-deploy.sh` détecte automatiquement MySQL, PostgreSQL ou SQLite à
partir de ton fichier `.env` sur le serveur. Les sites Forge utilisent
MySQL par défaut — c'est très probablement ton cas, mais si tu as un
doute, regarde dans Forge → ton site → Environnement, la ligne
`DB_CONNECTION`.

### 2. Coller le script de déploiement dans Forge
Forge → ton site → onglet **Apps** → zone "Deploy Script" → remplace le
contenu par celui de `deploy/forge-deploy.sh` (une fois cette pull
request fusionnée, pour l'avoir sous les yeux). Enregistre.
**Ne coche pas encore "Quick Deploy"** à cette étape.

### 3. Activer la protection de la branche `main` sur GitHub
Sur chaque dépôt (`Afrolia` et `afrolia-backoffice`), depuis un
navigateur (Safari sur iPhone fonctionne, bascule en "Affichage
Bureau" si besoin) :
- Dépôt → **Settings** → **Branches** → **Add branch ruleset** (ou
  "Add rule" selon la version)
- Nom de la branche protégée : `main`
- Coche **Require status checks to pass before merging**
- Dans la liste qui apparaît, sélectionne la vérification **Tests**
  (nom du workflow `tests.yml`)
- Coche aussi **Require a pull request before merging** si ce n'est
  pas déjà le cas, pour ne jamais pouvoir pousser directement sur
  `main` sans passer par une pull request que tu approuves
- Enregistre

Sur un compte GitHub gratuit, ces options sont disponibles aussi bien
pour un dépôt public que privé — ça ne devrait pas être limité par ton
abonnement. Si une case est grisée avec un message d'upgrade, c'est le
signe d'une limite de ton plan ; dis-le-moi et je chercherai une
solution de contournement.

### 4. Une fois les deux premières pull requests fusionnées et testées
Seulement quand tu es à l'aise avec le fonctionnement (après avoir vu
au moins un déploiement manuel réussir avec ce script), active
**Quick Deploy** dans Forge → ton site → Apps. À partir de là, chaque
fusion dans `main` déploiera automatiquement.

## En cas de problème pendant un déploiement

Forge (modèle Zero-Downtime) garde l'ancienne version du site en ligne
tant que le nouveau déploiement n'a pas entièrement réussi : un
problème pendant la récupération du code ou l'installation des
dépendances n'interrompt donc jamais le site, Forge ne bascule
simplement pas vers la nouvelle version.

Le seul cas qui demande une action manuelle, c'est une migration qui
échoue après avoir déjà modifié la base : `deploy/rollback.sh` restaure
alors la base depuis la sauvegarde ET réactive la version précédente
(il n'y a plus de commit Git à restaurer — Forge garde déjà les
anciennes versions sur le disque). Depuis l'onglet "Commands" de Forge
(ou en SSH) :

```
bash deploy/rollback.sh <fichier-sauvegarde.sql.gz> <racine-du-site>
```

`<racine-du-site>` est le dossier qui contient `releases/` et
`current` (ex. `/home/forge/afrolia-app.com`) — visible dans
Forge → ton site → en-tête de la page. Le script détecte lui-même
quelle version réactiver ; le fichier de sauvegarde exact à utiliser
est affiché par le script de déploiement au moment de l'échec.

Les sauvegardes sont gardées dans `~/backups/afrolia-db/` sur le
serveur (les 10 dernières), en dehors du dossier du site.
