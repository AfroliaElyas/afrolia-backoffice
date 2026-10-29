<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Sauvegarde la base de données ET les photos (profil, galerie, produits),
 * puis les envoie sur un stockage externe (Backblaze B2), en dehors du
 * serveur Forge — pour protéger les données d'Afrolia même en cas de perte
 * totale du serveur. Vient en complément de la sauvegarde locale prise à
 * chaque déploiement (deploy/forge-deploy.sh), pas à sa place : celle-ci
 * tourne chaque jour, indépendamment des déploiements.
 */
class SauvegardeExterneCommand extends Command
{
    protected $signature = 'sauvegarde:externe';

    protected $description = "Sauvegarde la base de données et les photos, et les envoie sur Backblaze B2, hors du serveur Forge";

    private const NOMBRE_SAUVEGARDES_CONSERVEES = 14;

    public function handle(): int
    {
        $connexion = config('database.default');
        if ($connexion !== 'mysql') {
            $this->error("Connexion '{$connexion}' non prise en charge par cette commande (MySQL uniquement). Sauvegarde externe non effectuée.");

            return self::FAILURE;
        }

        $resultatBase = $this->sauvegarderBase();
        $resultatPhotos = $this->sauvegarderPhotos();

        // Les deux sauvegardes sont indépendantes : l'échec de l'une ne doit
        // jamais empêcher l'autre de s'exécuter — perdre la sauvegarde des
        // photos parce que la base a échoué (ou inversement) serait pire que
        // de n'en perdre qu'une seule.
        return ($resultatBase && $resultatPhotos) ? self::SUCCESS : self::FAILURE;
    }

    private function sauvegarderBase(): bool
    {
        $config = config('database.connections.mysql');

        // tempnam() crée réellement le fichier à ce chemin exact : il ne faut
        // pas lui ajouter de suffixe (ça créerait un chemin différent),
        // sinon le fichier vide d'origine n'est jamais nettoyé.
        $cheminSql = tempnam(sys_get_temp_dir(), 'sauvegarde-afrolia-');
        $cheminGz = $cheminSql.'.gz';

        // Deux commandes shell distinctes, chacune avec une seule redirection
        // (">"), jamais un pipe entre deux processus externes : le code de
        // sortie de "mysqldump ... > fichier" est directement celui de
        // mysqldump, sans l'ambiguïté d'un pipefail à gérer (/bin/sh n'est
        // pas forcément bash sur le serveur).
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

            return false;
        }

        $tailleSql = @filesize($cheminSql) ?: 0;
        if ($tailleSql < 1024) {
            @unlink($cheminSql);
            $this->error("ÉCHEC : la sauvegarde ne fait que {$tailleSql} octets, ce n'est pas crédible.");

            return false;
        }

        if (! str_contains((string) file_get_contents($cheminSql, false, null, 0, 65536), 'CREATE TABLE')) {
            @unlink($cheminSql);
            $this->error('ÉCHEC : le contenu de la sauvegarde ne ressemble pas à un dump MySQL valide (aucun « CREATE TABLE » trouvé).');

            return false;
        }

        $gzip = Process::fromShellCommandline(sprintf(
            'gzip -c %s > %s',
            escapeshellarg($cheminSql),
            escapeshellarg($cheminGz)
        ));
        $gzip->setTimeout(300);
        $gzip->run();
        @unlink($cheminSql);

        if (! $gzip->isSuccessful()) {
            @unlink($cheminGz);
            $this->error('ÉCHEC : compression de la sauvegarde impossible — '.trim($gzip->getErrorOutput()));

            return false;
        }

        $tailleGz = @filesize($cheminGz) ?: 0;
        $nomDistant = 'afrolia-db-'.now()->format('Ymd_His').'.sql.gz';

        try {
            $flux = fopen($cheminGz, 'r');
            Storage::disk('backblaze')->put($nomDistant, $flux);
            if (is_resource($flux)) {
                fclose($flux);
            }
        } catch (\Throwable $e) {
            @unlink($cheminGz);
            $this->error('ÉCHEC : envoi vers le stockage externe impossible — '.$e->getMessage());

            return false;
        }

        @unlink($cheminGz);

        $this->info("Sauvegarde externe envoyée : {$nomDistant} ({$tailleGz} octets).");

        $this->purgerAnciennesSauvegardes('afrolia-db-', '.sql.gz');

        return true;
    }

    /**
     * Sauvegarde les photos (profil, galerie, produits) : l'emplacement
     * persistant (storage/app/public/salon, voir le correctif du
     * 29/10/2026) et l'ancien emplacement non partagé entre déploiements
     * (public/salon) si des photos y restent encore — tant que la
     * migration vers le stockage persistant n'a pas eu lieu, c'est là que
     * vivent les photos déjà en ligne.
     */
    private function sauvegarderPhotos(): bool
    {
        $reussite = true;

        $reussite = $this->archiverEtEnvoyerDossier(
            storage_path('app/public/salon'),
            'afrolia-photos-'
        ) && $reussite;

        $reussite = $this->archiverEtEnvoyerDossier(
            public_path('salon'),
            'afrolia-photos-heritage-'
        ) && $reussite;

        return $reussite;
    }

    private function archiverEtEnvoyerDossier(string $dossier, string $prefixeNomDistant): bool
    {
        if (! is_dir($dossier) || count(glob($dossier.'/*') ?: []) === 0) {
            // Dossier absent ou vide : rien à sauvegarder, pas une erreur
            // (cas normal avant toute photo envoyée, ou après migration
            // complète pour l'emplacement hérité).
            return true;
        }

        $cheminArchive = tempnam(sys_get_temp_dir(), 'sauvegarde-afrolia-photos-').'.tar.gz';

        $tar = Process::fromShellCommandline(sprintf(
            'tar -czf %s -C %s .',
            escapeshellarg($cheminArchive),
            escapeshellarg($dossier)
        ));
        $tar->setTimeout(600);
        $tar->run();

        if (! $tar->isSuccessful()) {
            @unlink($cheminArchive);
            $this->error("ÉCHEC : impossible de compresser {$dossier} — ".trim($tar->getErrorOutput()));

            return false;
        }

        $taille = @filesize($cheminArchive) ?: 0;
        if ($taille < 1) {
            @unlink($cheminArchive);
            $this->error("ÉCHEC : l'archive de {$dossier} est vide.");

            return false;
        }

        $nomDistant = $prefixeNomDistant.now()->format('Ymd_His').'.tar.gz';

        try {
            $flux = fopen($cheminArchive, 'r');
            Storage::disk('backblaze')->put($nomDistant, $flux);
            if (is_resource($flux)) {
                fclose($flux);
            }
        } catch (\Throwable $e) {
            @unlink($cheminArchive);
            $this->error("ÉCHEC : envoi de l'archive {$nomDistant} impossible — ".$e->getMessage());

            return false;
        }

        @unlink($cheminArchive);

        $this->info("Sauvegarde photos envoyée : {$nomDistant} ({$taille} octets).");

        $this->purgerAnciennesSauvegardes($prefixeNomDistant, '.tar.gz');

        return true;
    }

    private function purgerAnciennesSauvegardes(string $prefixe, string $suffixe): void
    {
        $disque = Storage::disk('backblaze');

        $fichiers = collect($disque->files())
            ->filter(fn (string $f) => str_starts_with($f, $prefixe) && str_ends_with($f, $suffixe))
            ->sort()
            ->values();

        $aSupprimer = $fichiers->count() - self::NOMBRE_SAUVEGARDES_CONSERVEES;

        if ($aSupprimer > 0) {
            $disque->delete($fichiers->take($aSupprimer)->all());
            $this->info("{$aSupprimer} ancienne(s) sauvegarde(s) '{$prefixe}*{$suffixe}' supprimée(s) (conservation : ".self::NOMBRE_SAUVEGARDES_CONSERVEES.').');
        }
    }
}
