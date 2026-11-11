<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Sauvegarde la base de données ET les photos, chiffrées, vers les
 * "Releases" d'un dépôt GitHub privé — une seconde copie hors serveur,
 * totalement indépendante de Backblaze (voir SauvegardeExterneCommand) :
 * aucune des deux ne dépend du bon fonctionnement de l'autre.
 *
 * N'est PAS planifiée automatiquement (voir routes/console.php) : tant que
 * cette ligne n'y est pas ajoutée, cette commande ne s'exécute jamais
 * seule — l'ajouter est une décision à part, prise seulement après
 * validation explicite de Ruth.
 *
 * Ne modifie rien à la sauvegarde Backblaze existante
 * (SauvegardeExterneCommand) : fichier entièrement séparé.
 */
class SauvegardeGithubCommand extends Command
{
    protected $signature = 'sauvegarde:github';

    protected $description = "Sauvegarde la base de données et les photos, chiffrées, vers les Releases d'un dépôt GitHub privé (indépendant de Backblaze)";

    // Contrairement à Backblaze (où la facturation dépend du volume
    // stocké), les pièces jointes de Release GitHub n'ont pas de coût lié
    // à leur nombre ou à leur durée de conservation : rien n'empêche de
    // garder plus de marge de sécurité que les 14 jours de Backblaze.
    // 90 jours de sauvegardes quotidiennes représentent encore quelques
    // centaines de Mo au grand maximum, loin des limites GitHub.
    private const NOMBRE_SAUVEGARDES_CONSERVEES = 90;

    public function handle(): int
    {
        $connexion = config('database.default');
        if ($connexion !== 'mysql') {
            $this->error("Connexion '{$connexion}' non prise en charge (MySQL uniquement). Sauvegarde GitHub non effectuée.");

            return self::FAILURE;
        }

        $cle = config('services.sauvegarde_github.cle');
        $token = config('services.sauvegarde_github.token');
        $depot = config('services.sauvegarde_github.depot');

        if (empty($cle) || empty($token) || empty($depot)) {
            $this->error('ÉCHEC : SAUVEGARDE_GITHUB_CLE, SAUVEGARDE_GITHUB_TOKEN et SAUVEGARDE_GITHUB_DEPOT doivent tous les trois être renseignés. Sauvegarde GitHub non effectuée.');

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
        $fichiers = array_filter([$cheminDumpChiffre, $cheminPhotosChiffre ?: null]);

        $reussite = $this->publierRelease($depot, $token, $nomTag, $fichiers);

        foreach ($fichiers as $fichier) {
            @unlink($fichier);
        }

        if (! $reussite) {
            return self::FAILURE;
        }

        $this->info("Sauvegarde GitHub envoyée : release '{$nomTag}' sur {$depot}.");

        $this->purgerAnciennesReleases($depot, $token);

        return self::SUCCESS;
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

    private function publierRelease(string $depot, string $token, string $tag, array $fichiers): bool
    {
        $entetes = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/vnd.github+json',
        ];

        try {
            $reponse = Http::withHeaders($entetes)
                ->post("https://api.github.com/repos/{$depot}/releases", [
                    'tag_name' => $tag,
                    'name' => "Sauvegarde chiffrée {$tag}",
                    'body' => 'Sauvegarde automatique chiffrée (base + photos). Clé de déchiffrement non stockée ici.',
                    'prerelease' => true,
                ]);
        } catch (\Throwable $e) {
            $this->error('ÉCHEC : impossible de créer la release GitHub — '.$e->getMessage());

            return false;
        }

        if (! $reponse->successful()) {
            $this->error('ÉCHEC : création de la release GitHub refusée — HTTP '.$reponse->status().' — '.$reponse->body());

            return false;
        }

        $idRelease = $reponse->json('id');
        $urlUpload = str_replace('{?name,label}', '', (string) $reponse->json('upload_url'));

        foreach ($fichiers as $fichier) {
            $nomFichier = basename($fichier);
            try {
                $reponseUpload = Http::withHeaders($entetes + ['Content-Type' => 'application/octet-stream'])
                    ->withBody(file_get_contents($fichier), 'application/octet-stream')
                    ->post("{$urlUpload}?name={$nomFichier}");
            } catch (\Throwable $e) {
                $this->error("ÉCHEC : envoi de {$nomFichier} impossible — ".$e->getMessage());

                return false;
            }

            if (! $reponseUpload->successful()) {
                $this->error("ÉCHEC : envoi de {$nomFichier} refusé — HTTP ".$reponseUpload->status());

                return false;
            }
        }

        return true;
    }

    private function purgerAnciennesReleases(string $depot, string $token): void
    {
        $entetes = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/vnd.github+json',
        ];

        try {
            $reponse = Http::withHeaders($entetes)->get("https://api.github.com/repos/{$depot}/releases", ['per_page' => 100]);
            if (! $reponse->successful()) {
                $this->warn('AVERTISSEMENT : impossible de lister les releases pour la purge — la sauvegarde du jour est bien envoyée.');

                return;
            }

            $releases = collect($reponse->json())
                ->filter(fn ($r) => str_starts_with((string) $r['tag_name'], 'sauvegarde-'))
                ->sortBy('tag_name')
                ->values();

            $aSupprimer = $releases->count() - self::NOMBRE_SAUVEGARDES_CONSERVEES;
            if ($aSupprimer <= 0) {
                return;
            }

            foreach ($releases->take($aSupprimer) as $release) {
                Http::withHeaders($entetes)->delete("https://api.github.com/repos/{$depot}/releases/{$release['id']}");
            }

            $this->info("{$aSupprimer} ancienne(s) release(s) de sauvegarde supprimée(s) (conservation : ".self::NOMBRE_SAUVEGARDES_CONSERVEES.').');
        } catch (\Throwable $e) {
            $this->warn('AVERTISSEMENT : purge des anciennes releases impossible — la sauvegarde du jour est bien envoyée. Détail : '.$e->getMessage());
        }
    }
}
