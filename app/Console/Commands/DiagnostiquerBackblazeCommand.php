<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Diagnostique une erreur « Signature validation failed » (ou toute autre
 * erreur de connexion) sur le disque Backblaze B2, sans jamais afficher les
 * secrets eux-mêmes — seulement leur longueur et des indices (espaces
 * parasites, région/endpoint incohérents) qui trahissent une faute de
 * transcription lors de la saisie dans Forge (demandé le 30/10/2026).
 *
 * La section 3 écrit un unique petit fichier de diagnostic (préfixé
 * "__diagnostic_afrolia_", horodaté pour être unique), le liste, puis le
 * supprime aussitôt — exactement les trois opérations que sauvegarde:externe
 * utilise réellement (écrire, lister pour la purge, supprimer les anciennes
 * sauvegardes). Jamais de lecture du contenu d'un fichier existant, jamais de
 * donnée réelle touchée. Un test en lecture seule (Storage::exists) avait été
 * tenté d'abord, mais échouait par 403 sur une clé pourtant valide dont les
 * capacités Backblaze ne couvrent que l'écriture — exactement ce que la
 * sauvegarde utilise, donc sans rapport avec un vrai problème (confirmé en
 * production le 01/11/2026).
 */
class DiagnostiquerBackblazeCommand extends Command
{
    protected $signature = 'backblaze:diagnostiquer';

    protected $description = "Diagnostique la connexion Backblaze B2 sans afficher les secrets (longueur, espaces parasites, cohérence région/endpoint, puis test de connexion réel)";

    /**
     * Nom affiché (celui de la variable .env, pour que le message parle à
     * Ruth) => clé de config correspondante dans config/filesystems.php.
     * Lire via config() plutôt que env() directement : identique en
     * production (la config n'évalue chaque env() qu'une fois au
     * chargement), mais permet de simuler des valeurs dans les tests.
     */
    private const CLES_CONFIG = [
        'BACKBLAZE_KEY_ID' => 'filesystems.disks.backblaze.key',
        'BACKBLAZE_APPLICATION_KEY' => 'filesystems.disks.backblaze.secret',
        'BACKBLAZE_REGION' => 'filesystems.disks.backblaze.region',
        'BACKBLAZE_BUCKET' => 'filesystems.disks.backblaze.bucket',
        'BACKBLAZE_ENDPOINT' => 'filesystems.disks.backblaze.endpoint',
    ];

    public function handle(): int
    {
        $this->info('=== 1. Présence et forme des variables d\'environnement ===');

        $valeurs = [];
        $problemeDetecte = false;

        foreach (self::CLES_CONFIG as $nom => $cleConfig) {
            $brute = config($cleConfig);
            $valeurs[$nom] = $brute;

            if ($brute === null || $brute === '') {
                $this->error("  {$nom} : ABSENTE ou vide.");
                $problemeDetecte = true;

                continue;
            }

            $longueur = strlen($brute);
            $sansEspaces = trim($brute);
            $espacesParasites = $sansEspaces !== $brute;

            // Les deux secrets (clé et application key) ne sont jamais
            // affichés, même partiellement : seule leur longueur compte ici.
            $estSecret = in_array($nom, ['BACKBLAZE_KEY_ID', 'BACKBLAZE_APPLICATION_KEY'], true);
            $affichage = $estSecret ? "{$longueur} caractères" : "\"{$brute}\" ({$longueur} caractères)";

            $this->line("  {$nom} : {$affichage}" . ($espacesParasites ? '  ⚠️ ESPACE(S) PARASITE(S) EN DÉBUT/FIN DÉTECTÉ(S)' : ''));

            if ($espacesParasites) {
                $problemeDetecte = true;
            }
        }

        $this->newLine();
        $this->info('=== 2. Cohérence région / endpoint ===');

        $region = trim((string) ($valeurs['BACKBLAZE_REGION'] ?? ''));
        $endpoint = trim((string) ($valeurs['BACKBLAZE_ENDPOINT'] ?? ''));

        if ($region !== '' && $endpoint !== '') {
            $anomalies = $this->verifierRegionEtEndpoint($region, $endpoint);
            foreach ($anomalies as $anomalie) {
                $this->error("  {$anomalie}");
                $problemeDetecte = true;
            }

            if ($anomalies === []) {
                $this->line("  OK : région ({$region}) et endpoint ({$endpoint}) cohérents.");
            }
        } else {
            $this->line('  (ignoré : région ou endpoint absent, déjà signalé ci-dessus)');
        }

        // Longueurs habituelles des clés Backblaze : simples indices (jamais
        // bloquants), utiles pour repérer une clé tronquée au copier-coller
        // ou le nom de la clé saisi à la place de son identifiant.
        $longueurKeyId = strlen(trim((string) ($valeurs['BACKBLAZE_KEY_ID'] ?? '')));
        $longueurAppKey = strlen(trim((string) ($valeurs['BACKBLAZE_APPLICATION_KEY'] ?? '')));
        if ($longueurKeyId > 0 && $longueurKeyId !== 25) {
            $this->warn("  Indice : BACKBLAZE_KEY_ID fait {$longueurKeyId} caractères ; l'identifiant d'une clé d'application Backblaze en fait généralement 25 (12 pour la clé principale « master », déconseillée pour l'API S3). Vérifie que c'est bien le « keyID » et non le « keyName ».");
        }
        if ($longueurAppKey > 0 && $longueurAppKey !== 31) {
            $this->warn("  Indice : BACKBLAZE_APPLICATION_KEY fait {$longueurAppKey} caractères ; une clé d'application Backblaze en fait généralement 31. Une clé tronquée au copier-coller donne exactement « Signature validation failed ».");
        }

        // Une horloge serveur décalée de plus de quelques minutes fait aussi
        // rejeter les signatures : l'heure est affichée pour comparaison.
        $this->line('  Heure du serveur (UTC) : '.gmdate('Y-m-d H:i:s').' — doit correspondre à l\'heure réelle à quelques secondes près.');

        $this->newLine();
        $this->info('=== 3. Test des capacités réellement utilisées par la sauvegarde ===');
        $this->line('  (écrire, lister, supprimer — exactement ce que fait sauvegarde:externe ;');
        $this->line('  jamais de lecture du contenu d\'un fichier existant.)');

        $nomFichier = '__diagnostic_afrolia_' . now()->timestamp . '__';

        try {
            $disque = Storage::disk('backblaze');

            $disque->put($nomFichier, 'diagnostic Afrolia — fichier temporaire, supprimé immédiatement');
            $this->info('  ✅ Écriture (writeFiles) : réussie.');

            $disque->files();
            $this->info('  ✅ Listage (listFiles) : réussi.');

            $disque->delete($nomFichier);
            $this->info('  ✅ Suppression du fichier de test (deleteFiles) : réussie.');

            $this->newLine();
            $this->info('  ✅ Connexion réussie : la sauvegarde externe peut fonctionner normalement.');

            if ($problemeDetecte) {
                $this->warn('  (Les anomalies listées plus haut n\'empêchent donc pas la connexion ici — à surveiller quand même.)');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            // Nettoyage best-effort : si l'échec a lieu après l'écriture
            // (ex. lors du listage ou de la suppression), on essaie quand
            // même de retirer le fichier de test pour ne rien laisser
            // traîner dans le bucket.
            try {
                Storage::disk('backblaze')->delete($nomFichier);
            } catch (\Throwable) {
                // Ignoré : si la suppression échoue aussi, c'est déjà
                // signalé par l'erreur principale ci-dessous.
            }

            $this->error('  ❌ ÉCHEC : ' . get_class($e));
            $this->error('  Message complet renvoyé par Backblaze : ' . $e->getMessage());

            // League\Flysystem enveloppe toujours l'exception réelle du SDK
            // AWS (ex. Aws\S3\Exception\S3Exception) dans sa propre exception
            // générique ("Unable to check existence for: ..."). Le vrai code
            // d'erreur ("SignatureDoesNotMatch", "InvalidAccessKeyId"...) ne
            // vit que dans cette exception d'origine, récupérable via la
            // chaîne getPrevious() — jamais affiché avant ce correctif
            // (demandé après un premier diagnostic resté trop vague le
            // 01/11/2026).
            $cause = $e->getPrevious();
            while ($cause !== null) {
                $this->newLine();
                $this->error('  Cause réelle : ' . get_class($cause));
                $this->error('  Message : ' . $cause->getMessage());

                if (method_exists($cause, 'getAwsErrorCode')) {
                    $this->error('  Code d\'erreur AWS/Backblaze : ' . ($cause->getAwsErrorCode() ?? '(absent)'));
                }
                if (method_exists($cause, 'getStatusCode')) {
                    $this->error('  Code HTTP renvoyé par Backblaze : ' . $cause->getStatusCode());
                }

                $cause = $cause->getPrevious();
            }

            $this->newLine();
            $this->line('  Ce code précis (pas juste "Signature validation failed") indique généralement :');
            $this->line('  - "SignatureDoesNotMatch" → une faute dans BACKBLAZE_APPLICATION_KEY (le plus souvent une faute de transcription).');
            $this->line('  - "InvalidAccessKeyId" → une faute dans BACKBLAZE_KEY_ID, ou une clé supprimée/régénérée côté Backblaze.');
            $this->line('  - "NoSuchBucket" → BACKBLAZE_BUCKET ne correspond pas exactement au nom du bucket.');
            $this->line('  - "AccessDenied" → la clé d\'application existe mais n\'a pas la permission sur ce bucket précis.');
            $this->line('  - 403 sans code précis → vérifier les "Capabilities" de la clé d\'application dans Backblaze (doit inclure au moins listFiles, writeFiles et deleteFiles pour que la sauvegarde fonctionne).');

            return self::FAILURE;
        }
    }

    /**
     * Vérifie que la région et l'endpoint ont exactement la forme attendue
     * par Backblaze. L'ancienne vérification (« l'endpoint contient la
     * région ») laissait passer l'erreur la plus courante : une région
     * copiée avec son préfixe, ex. « s3.us-west-004 », qui est bien
     * contenue dans l'endpoint mais fausse la signature, car la région
     * fait partie de ce qui est signé.
     *
     * @return list<string> Les anomalies trouvées (vide si tout est cohérent).
     */
    private function verifierRegionEtEndpoint(string $region, string $endpoint): array
    {
        $anomalies = [];

        if (! preg_match('/^[a-z]{2}-[a-z]+-\d{3}$/', $region)) {
            $anomalies[] = "INCOHÉRENT : BACKBLAZE_REGION vaut « {$region} », alors qu'une région Backblaze a la forme « us-west-004 » ou « eu-central-003 » — seule, sans « s3. » devant ni « .backblazeb2.com » derrière. Une région mal formée fausse la signature (« Signature validation failed »).";
        }

        if (! preg_match('#^https?://#i', $endpoint)) {
            $anomalies[] = "INCOHÉRENT : BACKBLAZE_ENDPOINT doit commencer par « https:// » (valeur actuelle : « {$endpoint} »).";

            return $anomalies;
        }

        $hote = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $chemin = (string) parse_url($endpoint, PHP_URL_PATH);

        if (str_ends_with($hote, 'backblazeb2.com')) {
            $hoteAttendu = 's3.'.$region.'.backblazeb2.com';
            if ($hote !== $hoteAttendu) {
                $anomalies[] = "INCOHÉRENT : l'endpoint pointe vers « {$hote} », mais pour la région « {$region} » il devrait être exactement « https://{$hoteAttendu} » (sans le nom du bucket devant).";
            }
            if ($chemin !== '' && $chemin !== '/') {
                $anomalies[] = "INCOHÉRENT : l'endpoint ne doit contenir aucun chemin après le domaine (trouvé : « {$chemin} ») — le bucket se règle uniquement dans BACKBLAZE_BUCKET.";
            }
        } elseif (! str_contains($endpoint, $region)) {
            // Endpoint non Backblaze (tests, autre fournisseur compatible S3) :
            // on garde au moins la vérification d'origine.
            $anomalies[] = "INCOHÉRENT : l'endpoint ({$endpoint}) ne contient pas la région déclarée ({$region}).";
        }

        return $anomalies;
    }
}
