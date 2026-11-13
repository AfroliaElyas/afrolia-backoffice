<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Chiffre la base de données ET les photos, puis les prépare dans un
 * dossier stable (storage/app/sauvegardes-github-pretes/) pour
 * récupération par le workflow GitHub Actions sauvegarde-github.yml, qui
 * les publie dans les Releases d'un dépôt GitHub privé — une seconde
 * copie hors serveur, totalement indépendante de Backblaze (voir
 * SauvegardeExterneCommand) : aucune des deux ne dépend du bon
 * fonctionnement de l'autre.
 *
 * Avant le 13/11/2026, cette commande appelait elle-même l'API GitHub
 * avec un jeton d'accès personnel (SAUVEGARDE_GITHUB_TOKEN) — abandonné
 * après un échec persistant "Bad credentials" (HTTP 401) nécessitant à
 * chaque fois que Ruth recrée/recolle un jeton puis redéploie, sans
 * jamais pouvoir vérifier la valeur avant un déploiement complet. Cette
 * commande ne parle plus du tout à GitHub : elle produit seulement les
 * fichiers chiffrés localement. C'est le workflow GitHub Actions qui va
 * les chercher (lecture seule, via la clé SSH déjà existante et déjà
 * restreinte, voir deploy/diagnostic-afrolia.sh) et les publie avec le
 * jeton automatique GITHUB_TOKEN fourni par GitHub Actions — jamais un
 * jeton personnel à créer ou coller.
 *
 * Ne modifie rien à la sauvegarde Backblaze existante
 * (SauvegardeExterneCommand) : fichier entièrement séparé.
 */
class SauvegardeGithubCommand extends Command
{
    protected $signature = 'sauvegarde:github {--verifier : Déchiffre et restaure immédiatement les fichiers venant d\'être produits, dans un environnement isolé (base temporaire supprimée ensuite), sans jamais toucher à la production ni à GitHub}';

    protected $description = "Chiffre la base de données et les photos, et les prépare pour récupération par le workflow GitHub Actions sauvegarde-github.yml (aucun appel réseau à GitHub depuis ce processus)";

    // Nombre de jeux de sauvegarde (base + photos chiffrées) gardés
    // localement sur le serveur, en plus de celui du jour : une marge de
    // sécurité si le workflow GitHub Actions qui vient les récupérer a
    // manqué une exécution, sans faire grossir indéfiniment le disque. La
    // conservation longue durée (90 jours) vit désormais dans les
    // Releases GitHub elles-mêmes, gérées par ce workflow — voir
    // .github/workflows/sauvegarde-github.yml.
    private const NOMBRE_JEUX_LOCAUX_CONSERVES = 2;

    public function handle(): int
    {
        $connexion = config('database.default');
        if ($connexion !== 'mysql') {
            $this->error("Connexion '{$connexion}' non prise en charge (MySQL uniquement). Sauvegarde GitHub non effectuée.");

            return self::FAILURE;
        }

        $cle = config('services.sauvegarde_github.cle');

        if (empty($cle)) {
            $this->error('ÉCHEC : SAUVEGARDE_GITHUB_CLE doit être renseignée. Sauvegarde GitHub non effectuée.');

            return self::FAILURE;
        }

        $cheminDumpChiffre = $this->sauvegarderEtChiffrerBase($cle);
        if ($cheminDumpChiffre === null) {
            return self::FAILURE;
        }

        $cheminPhotosChiffre = $this->sauvegarderEtChiffrerPhotos($cle);
        // Pas de photos à sauvegarder (dossier vide) n'est pas un échec —
        // voir sauvegarderEtChiffrerPhotos().
        if ($cheminPhotosChiffre === false) {
            @unlink($cheminDumpChiffre);

            return self::FAILURE;
        }

        $nomTag = 'sauvegarde-'.now()->format('Ymd_His');

        if ($this->option('verifier')) {
            $this->verifierLocalement($cheminDumpChiffre, $cheminPhotosChiffre ?: null, $cle);
        }

        $dossierPret = $this->deplacerVersDossierPret($nomTag, $cheminDumpChiffre, $cheminPhotosChiffre ?: null);
        if ($dossierPret === null) {
            return self::FAILURE;
        }

        $this->info("Sauvegarde chiffrée prête pour récupération : '{$nomTag}' (storage/app/sauvegardes-github-pretes/).");

        $this->purgerAnciensJeuxLocaux();

        return self::SUCCESS;
    }

    /**
     * Déchiffre et restaure IMMÉDIATEMENT les fichiers qui viennent d'être
     * produits (ceux de CETTE exécution, pas ceux déjà publiés sur
     * GitHub), dans un environnement isolé — jamais la base ou le dossier
     * de production. Remplace l'ancienne vérification (qui téléchargeait
     * depuis GitHub avec un jeton) : plus simple, plus rapide, et ne
     * dépend plus du tout d'une connexion réseau à GitHub ni d'aucun
     * jeton. Tout est supprimé à la fin, succès ou échec (bloc finally).
     * N'affiche jamais le contenu réel (ni lignes de table, ni noms de
     * fichiers), seulement des compteurs.
     */
    private function verifierLocalement(string $cheminDumpChiffre, ?string $cheminPhotosChiffre, string $cle): void
    {
        $this->info('=== Vérification locale (environnement isolé, sans réseau ni GitHub) ===');

        $dossierTemp = sys_get_temp_dir().'/verif-sauvegarde-github-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4));
        @mkdir($dossierTemp, 0700, true);
        $nomBaseTemp = 'verif_sauvegarde_github_'.now()->format('YmdHis');

        try {
            $cheminDumpClairGz = $dossierTemp.'/dump.gz';
            if ($this->dechiffrer($cheminDumpChiffre, $cheminDumpClairGz, $cle)) {
                $this->verifierBaseRestauree($cheminDumpClairGz, $nomBaseTemp);
            } else {
                $this->warn('AVERTISSEMENT : déchiffrement de vérification impossible pour le dump.');
            }

            if ($cheminPhotosChiffre !== null) {
                $cheminPhotosClair = $dossierTemp.'/photos.tar.gz';
                if ($this->dechiffrer($cheminPhotosChiffre, $cheminPhotosClair, $cle)) {
                    $this->verifierPhotosRestaurees($cheminPhotosClair);
                } else {
                    $this->warn('AVERTISSEMENT : déchiffrement de vérification impossible pour les photos.');
                }
            }
        } finally {
            $this->supprimerRecursif($dossierTemp);
            $this->supprimerBaseTemp($nomBaseTemp);
        }
    }

    private function dechiffrer(string $cheminSource, string $cheminDestination, string $cle): bool
    {
        $processus = new Process([
            'openssl', 'enc', '-d', '-aes-256-cbc', '-pbkdf2',
            '-in', $cheminSource,
            '-out', $cheminDestination,
            '-pass', 'env:SAUVEGARDE_GITHUB_CLE_OPENSSL',
        ], null, ['SAUVEGARDE_GITHUB_CLE_OPENSSL' => $cle]);
        $processus->setTimeout(300);
        $processus->run();

        return $processus->isSuccessful();
    }

    private function verifierBaseRestauree(string $cheminGz, string $nomBaseTemp): void
    {
        $cheminSql = preg_replace('/\.gz$/', '', $cheminGz);
        $gunzip = Process::fromShellCommandline(sprintf('gunzip -f %s', escapeshellarg($cheminGz)));
        $gunzip->run();

        if (! $gunzip->isSuccessful() || ! is_file($cheminSql)) {
            $this->warn('AVERTISSEMENT : décompression du dump restauré impossible.');

            return;
        }

        $config = config('database.connections.mysql');
        $env = ['MYSQL_PWD' => $config['password']];

        $creer = Process::fromShellCommandline(sprintf(
            'mysql -h %s -P %s -u %s -e %s',
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            escapeshellarg("CREATE DATABASE IF NOT EXISTS `{$nomBaseTemp}`;")
        ), null, $env);
        $creer->run();

        if (! $creer->isSuccessful()) {
            $this->warn('AVERTISSEMENT : création de la base de vérification impossible — '.trim($creer->getErrorOutput()));

            return;
        }

        $importer = Process::fromShellCommandline(sprintf(
            'mysql -h %s -P %s -u %s %s < %s',
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($nomBaseTemp),
            escapeshellarg($cheminSql)
        ), null, $env);
        $importer->setTimeout(600);
        $importer->run();

        if (! $importer->isSuccessful()) {
            $this->error("ÉCHEC : la restauration de la base dans l'environnement isolé a échoué — ".trim($importer->getErrorOutput()));

            return;
        }

        $tablesRestaurees = $this->listerTables($nomBaseTemp, $config);
        $tablesProduction = $this->listerTables((string) $config['database'], $config);
        $manquantes = array_diff($tablesProduction, $tablesRestaurees);

        $totalLignes = 0;
        foreach ($tablesRestaurees as $table) {
            $totalLignes += $this->compterLignes($nomBaseTemp, $table, $config);
        }

        $this->info('Base restaurée dans un environnement isolé : '.count($tablesRestaurees)." table(s), {$totalLignes} ligne(s) au total.");

        if (empty($manquantes)) {
            $this->info('✅ Toutes les tables de production sont présentes dans la sauvegarde restaurée.');
        } else {
            $this->error('❌ ÉCHEC DE VÉRIFICATION : '.count($manquantes)." table(s) de production absente(s) de la sauvegarde restaurée.");
        }
    }

    private function verifierPhotosRestaurees(string $cheminTarGz): void
    {
        $dossierExtrait = $cheminTarGz.'-extrait';
        @mkdir($dossierExtrait, 0700, true);

        $tar = Process::fromShellCommandline(sprintf(
            'tar -xzf %s -C %s',
            escapeshellarg($cheminTarGz),
            escapeshellarg($dossierExtrait)
        ));
        $tar->run();

        if (! $tar->isSuccessful()) {
            $this->error("ÉCHEC : extraction des photos restaurées impossible — ".trim($tar->getErrorOutput()));

            return;
        }

        $dossierProduction = storage_path('app/public/salon');
        $fichiersRestaures = glob($dossierExtrait.'/*') ?: [];
        $fichiersProduction = is_dir($dossierProduction) ? (glob($dossierProduction.'/*') ?: []) : [];

        $tailleRestauree = array_sum(array_map('filesize', $fichiersRestaures));
        $tailleProduction = array_sum(array_map('filesize', $fichiersProduction));

        $this->info('Photos restaurées dans un environnement isolé : '.count($fichiersRestaures)." fichier(s), {$tailleRestauree} octet(s) au total.");

        if (count($fichiersRestaures) === count($fichiersProduction) && $tailleRestauree === $tailleProduction) {
            $this->info('✅ Nombre de fichiers et taille totale identiques à la production.');
        } else {
            $this->warn('⚠️ Différence détectée (production : '.count($fichiersProduction).' fichier(s)/'.$tailleProduction." octets ; restauré : ".count($fichiersRestaures).'/'.$tailleRestauree." octets) — peut être normal si des photos ont changé entre la sauvegarde et maintenant.");
        }
    }

    private function listerTables(string $base, array $config): array
    {
        $process = Process::fromShellCommandline(sprintf(
            'mysql -h %s -P %s -u %s -N -e %s %s',
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            escapeshellarg('SHOW TABLES;'),
            escapeshellarg($base)
        ), null, ['MYSQL_PWD' => $config['password']]);
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", trim($process->getOutput())))));
    }

    private function compterLignes(string $base, string $table, array $config): int
    {
        $process = Process::fromShellCommandline(sprintf(
            'mysql -h %s -P %s -u %s -N -e %s %s',
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            escapeshellarg("SELECT COUNT(*) FROM `{$table}`;"),
            escapeshellarg($base)
        ), null, ['MYSQL_PWD' => $config['password']]);
        $process->run();

        return $process->isSuccessful() ? (int) trim($process->getOutput()) : 0;
    }

    private function supprimerBaseTemp(string $nomBaseTemp): void
    {
        $config = config('database.connections.mysql');
        $process = Process::fromShellCommandline(sprintf(
            'mysql -h %s -P %s -u %s -e %s',
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            escapeshellarg("DROP DATABASE IF EXISTS `{$nomBaseTemp}`;")
        ), null, ['MYSQL_PWD' => $config['password']]);
        $process->run();
    }

    private function supprimerRecursif(string $dossier): void
    {
        if (! is_dir($dossier)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dossier);
    }

    private function sauvegarderEtChiffrerBase(string $cle): ?string
    {
        $config = config('database.connections.mysql');
        $cheminSql = tempnam(sys_get_temp_dir(), 'sauvegarde-github-');
        $cheminGz = $cheminSql.'.gz';
        $cheminChiffre = $cheminGz.'.enc';

        $commandeDump = sprintf(
            'mysqldump --single-transaction -h %s -P %s -u %s %s > %s',
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($config['database']),
            escapeshellarg($cheminSql)
        );

        $dump = Process::fromShellCommandline($commandeDump, null, ['MYSQL_PWD' => $config['password']]);
        $dump->setTimeout(600);
        $dump->run();

        if (! $dump->isSuccessful()) {
            @unlink($cheminSql);
            $this->error('ÉCHEC : mysqldump a échoué — '.trim($dump->getErrorOutput()));

            return null;
        }

        $tailleSql = @filesize($cheminSql) ?: 0;
        if ($tailleSql < 1024 || ! str_contains((string) file_get_contents($cheminSql, false, null, 0, 65536), 'CREATE TABLE')) {
            @unlink($cheminSql);
            $this->error('ÉCHEC : la sauvegarde ne ressemble pas à un dump MySQL valide.');

            return null;
        }

        $gzip = Process::fromShellCommandline(sprintf('gzip -c %s > %s', escapeshellarg($cheminSql), escapeshellarg($cheminGz)));
        $gzip->setTimeout(300);
        $gzip->run();
        @unlink($cheminSql);

        if (! $gzip->isSuccessful()) {
            @unlink($cheminGz);
            $this->error('ÉCHEC : compression impossible — '.trim($gzip->getErrorOutput()));

            return null;
        }

        if (! $this->chiffrer($cheminGz, $cheminChiffre, $cle)) {
            @unlink($cheminGz);

            return null;
        }
        @unlink($cheminGz);

        return $cheminChiffre;
    }

    /**
     * Retourne le chemin du fichier chiffré, ou false en cas d'échec réel.
     * Un dossier photos vide ou absent n'est pas un échec (comportement
     * identique à SauvegardeExterneCommand::archiverEtEnvoyerDossier) :
     * dans ce cas, retourne null (rien à envoyer, pas une erreur).
     */
    private function sauvegarderEtChiffrerPhotos(string $cle): string|false|null
    {
        $dossier = storage_path('app/public/salon');
        if (! is_dir($dossier) || count(glob($dossier.'/*') ?: []) === 0) {
            return null;
        }

        $cheminTemporaire = tempnam(sys_get_temp_dir(), 'sauvegarde-github-photos-');
        @unlink($cheminTemporaire);
        $cheminArchive = $cheminTemporaire.'.tar.gz';
        $cheminChiffre = $cheminArchive.'.enc';

        $tar = Process::fromShellCommandline(sprintf(
            'tar -czf %s -C %s .',
            escapeshellarg($cheminArchive),
            escapeshellarg($dossier)
        ));
        $tar->setTimeout(600);
        $tar->run();

        if (! $tar->isSuccessful()) {
            @unlink($cheminArchive);
            $this->error('ÉCHEC : compression des photos impossible — '.trim($tar->getErrorOutput()));

            return false;
        }

        if (! $this->chiffrer($cheminArchive, $cheminChiffre, $cle)) {
            @unlink($cheminArchive);

            return false;
        }
        @unlink($cheminArchive);

        return $cheminChiffre;
    }

    private function chiffrer(string $cheminSource, string $cheminDestination, string $cle): bool
    {
        $processus = new Process([
            'openssl', 'enc', '-aes-256-cbc', '-pbkdf2', '-salt',
            '-in', $cheminSource,
            '-out', $cheminDestination,
            '-pass', 'env:SAUVEGARDE_GITHUB_CLE_OPENSSL',
        ], null, ['SAUVEGARDE_GITHUB_CLE_OPENSSL' => $cle]);
        $processus->setTimeout(300);
        $processus->run();

        if (! $processus->isSuccessful()) {
            $this->error('ÉCHEC : chiffrement impossible — '.trim($processus->getErrorOutput()));

            return false;
        }

        return true;
    }

    /**
     * Déplace les fichiers chiffrés venant d'être produits vers le dossier
     * stable storage/app/sauvegardes-github-pretes/ (persistant entre
     * déploiements, comme tout storage/), et écrit un manifest.json
     * décrivant ce jeu précis (tag, horodatage, nom/empreinte SHA-256/
     * taille de chaque fichier). C'est ce manifeste, lu et retransmis tel
     * quel (jamais exécuté) par la nouvelle action SSH en lecture seule
     * "recuperer-sauvegarde-github" (voir deploy/diagnostic-afrolia.sh),
     * qui permet au workflow GitHub Actions sauvegarde-github.yml de
     * vérifier que rien n'a été corrompu pendant le transfert avant de
     * publier.
     *
     * rename() peut échouer si storage/app et le dossier temporaire
     * système (sys_get_temp_dir()) ne sont pas sur le même système de
     * fichiers : on retombe alors sur copy()+unlink().
     */
    private function deplacerVersDossierPret(string $tag, string $cheminDumpChiffre, ?string $cheminPhotosChiffre): ?string
    {
        $dossier = storage_path('app/sauvegardes-github-pretes');
        if (! is_dir($dossier) && ! @mkdir($dossier, 0700, true) && ! is_dir($dossier)) {
            $this->error("ÉCHEC : impossible de créer le dossier de préparation {$dossier}.");
            @unlink($cheminDumpChiffre);
            if ($cheminPhotosChiffre !== null) {
                @unlink($cheminPhotosChiffre);
            }

            return null;
        }

        $nomDump = "afrolia-db-{$tag}.sql.gz.enc";
        $nomPhotos = $cheminPhotosChiffre !== null ? "afrolia-photos-{$tag}.tar.gz.enc" : null;

        if (! $this->deplacerFichier($cheminDumpChiffre, $dossier.'/'.$nomDump)) {
            $this->error('ÉCHEC : impossible de déplacer le dump chiffré vers le dossier de préparation.');
            if ($cheminPhotosChiffre !== null) {
                @unlink($cheminPhotosChiffre);
            }

            return null;
        }

        if ($nomPhotos !== null && ! $this->deplacerFichier($cheminPhotosChiffre, $dossier.'/'.$nomPhotos)) {
            $this->error('ÉCHEC : impossible de déplacer les photos chiffrées vers le dossier de préparation.');
            @unlink($dossier.'/'.$nomDump);

            return null;
        }

        $fichiers = [['nom' => $nomDump, 'chemin' => $dossier.'/'.$nomDump]];
        if ($nomPhotos !== null) {
            $fichiers[] = ['nom' => $nomPhotos, 'chemin' => $dossier.'/'.$nomPhotos];
        }

        $manifest = [
            'tag' => $tag,
            'cree_le_utc' => now('UTC')->toIso8601String(),
            'fichiers' => array_map(fn (array $f) => [
                'nom' => $f['nom'],
                'sha256' => hash_file('sha256', $f['chemin']),
                'octets' => filesize($f['chemin']),
            ], $fichiers),
        ];

        file_put_contents($dossier.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $dossier;
    }

    private function deplacerFichier(string $source, string $destination): bool
    {
        if (@rename($source, $destination)) {
            return true;
        }

        if (@copy($source, $destination)) {
            @unlink($source);

            return true;
        }

        return false;
    }

    /**
     * Garde seulement les NOMBRE_JEUX_LOCAUX_CONSERVES jeux les plus
     * récents dans le dossier de préparation (regroupés par tag, extrait
     * du nom de fichier) — une marge de sécurité locale si le workflow
     * GitHub Actions a manqué une exécution, sans faire grossir
     * indéfiniment le disque. Le tag horodaté (sauvegarde-AAAAMMJJ_HHMMSS)
     * se trie correctement par ordre alphabétique = ordre chronologique.
     */
    private function purgerAnciensJeuxLocaux(): void
    {
        $dossier = storage_path('app/sauvegardes-github-pretes');
        $fichiers = glob($dossier.'/afrolia-*.enc') ?: [];

        $parTag = [];
        foreach ($fichiers as $fichier) {
            if (preg_match('/^afrolia-(?:db|photos)-(sauvegarde-\d{8}_\d{6})\./', basename($fichier), $m)) {
                $parTag[$m[1]][] = $fichier;
            }
        }

        $tags = array_keys($parTag);
        rsort($tags);

        $aSupprimer = array_slice($tags, self::NOMBRE_JEUX_LOCAUX_CONSERVES);
        foreach ($aSupprimer as $tag) {
            foreach ($parTag[$tag] as $fichier) {
                @unlink($fichier);
            }
        }
    }
}
