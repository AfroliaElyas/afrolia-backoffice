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
 * N'écrit ni ne modifie aucune donnée : la seule opération réseau est une
 * vérification d'existence d'un objet qui n'existe pas (Storage::exists),
 * l'équivalent en lecture d'un simple "ping" du bucket.
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
            if (str_contains($endpoint, $region)) {
                $this->line("  OK : l'endpoint ({$endpoint}) contient bien la région ({$region}).");
            } else {
                $this->error("  INCOHÉRENT : l'endpoint ({$endpoint}) ne contient pas la région déclarée ({$region}).");
                $this->line('  → Backblaze B2 affiche la région exacte à côté du nom du bucket dans son tableau de bord ("Application Keys" / détails du bucket) : copie-la exactement telle qu\'elle apparaît dans l\'endpoint.');
                $problemeDetecte = true;
            }
        } else {
            $this->line('  (ignoré : région ou endpoint absent, déjà signalé ci-dessus)');
        }

        $this->newLine();
        $this->info('=== 3. Test de connexion réel (lecture seule, aucune donnée modifiée) ===');

        try {
            Storage::disk('backblaze')->exists('__diagnostic_afrolia_' . now()->timestamp . '__');
            $this->info('  ✅ Connexion réussie : les identifiants, la région, l\'endpoint et le bucket sont corrects.');

            if ($problemeDetecte) {
                $this->warn('  (Les anomalies listées plus haut n\'empêchent donc pas la connexion ici — à surveiller quand même.)');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('  ❌ ÉCHEC : ' . get_class($e));
            $this->error('  Message complet renvoyé par Backblaze : ' . $e->getMessage());
            $this->newLine();
            $this->line('  Ce message précis (pas juste "Signature validation failed") indique généralement :');
            $this->line('  - "SignatureDoesNotMatch" → une faute dans BACKBLAZE_APPLICATION_KEY (le plus souvent une faute de transcription).');
            $this->line('  - "InvalidAccessKeyId" → une faute dans BACKBLAZE_KEY_ID, ou une clé supprimée/régénérée côté Backblaze.');
            $this->line('  - "NoSuchBucket" → BACKBLAZE_BUCKET ne correspond pas exactement au nom du bucket.');
            $this->line('  - "AccessDenied" → la clé d\'application existe mais n\'a pas la permission sur ce bucket précis.');

            return self::FAILURE;
        }
    }
}
