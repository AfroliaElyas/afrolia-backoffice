<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Sauvegarde la base de données et l'envoie sur un stockage externe
 * (Backblaze B2), en dehors du serveur Forge — pour protéger les
 * données d'Afrolia même en cas de perte totale du serveur. Vient en
 * complément de la sauvegarde locale prise à chaque déploiement
 * (deploy/forge-deploy.sh), pas à sa place : celle-ci tourne chaque
 * jour, indépendamment des déploiements.
 */
class SauvegardeExterneCommand extends Command
{
    protected $signature = 'sauvegarde:externe';

    protected $description = "Sauvegarde la base de données et l'envoie sur Backblaze B2, hors du serveur Forge";

    private const NOMBRE_SAUVEGARDES_CONSERVEES = 14;

    public function handle(): int
    {
        $connexion = config('database.default');
        if ($connexion !== 'mysql') {
            $this->error("Connexion '{$connexion}' non prise en charge par cette commande (MySQL uniquement). Sauvegarde externe non effectuée.");

            return self::FAILURE;
        }

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

            return self::FAILURE;
        }

        $tailleSql = @filesize($cheminSql) ?: 0;
        if ($tailleSql < 1024) {
            @unlink($cheminSql);
            $this->error("ÉCHEC : la sauvegarde ne fait que {$tailleSql} octets, ce n'est pas crédible.");

            return self::FAILURE;
        }

        if (! str_contains((string) file_get_contents($cheminSql, false, null, 0, 65536), 'CREATE TABLE')) {
            @unlink($cheminSql);
            $this->error('ÉCHEC : le contenu de la sauvegarde ne ressemble pas à un dump MySQL valide (aucun « CREATE TABLE » trouvé).');

            return self::FAILURE;
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

            return self::FAILURE;
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

            return self::FAILURE;
        }

        @unlink($cheminGz);

        $this->info("Sauvegarde externe envoyée : {$nomDistant} ({$tailleGz} octets).");

        $this->purgerAnciennesSauvegardes();

        return self::SUCCESS;
    }

    private function purgerAnciennesSauvegardes(): void
    {
        $disque = Storage::disk('backblaze');

        $fichiers = collect($disque->files())
            ->filter(fn (string $f) => str_starts_with($f, 'afrolia-db-') && str_ends_with($f, '.sql.gz'))
            ->sort()
            ->values();

        $aSupprimer = $fichiers->count() - self::NOMBRE_SAUVEGARDES_CONSERVEES;

        if ($aSupprimer > 0) {
            $disque->delete($fichiers->take($aSupprimer)->all());
            $this->info("{$aSupprimer} ancienne(s) sauvegarde(s) externe(s) supprimée(s) (conservation : ".self::NOMBRE_SAUVEGARDES_CONSERVEES.').');
        }
    }
}
