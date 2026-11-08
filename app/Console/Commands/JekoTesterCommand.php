<?php

namespace App\Console\Commands;

use App\Services\MobileMoney\JekoGateway;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Vérifie que JEKO_API_KEY / JEKO_API_KEY_ID / JEKO_STORE_ID fonctionnent
 * réellement (vrai appel à l'API Jèko, lecture du solde du magasin —
 * aucun argent déplacé), et que JEKO_WEBHOOK_SECRET est renseigné.
 * Exécutable depuis l'onglet "Commands" de Forge, sans terminal. Même
 * esprit que backblaze:diagnostiquer et notifications:tester : jamais de
 * secret affiché (demandé le 04/11/2026).
 *
 * Étendu le 08/11/2026 (refus persistant "Invalid API key ID" malgré une
 * vérification et un redéploiement confirmés par Ruth) avec exactement la
 * même protection déjà posée pour backblaze:diagnostiquer : la
 * configuration chargée peut différer du fichier .env réel quand elle est
 * en cache (Laravel saute alors EXPRÈS la relecture de .env — vérifié dans
 * LoadEnvironmentVariables::bootstrap()). On compare donc, dans ce même
 * processus et sans jamais afficher les secrets, la configuration chargée
 * au fichier .env réel lu avec Dotenv::parse() (aucun effet de bord), et on
 * détecte aussi un espace ou caractère invisible parasite en début/fin —
 * la copie manuelle d'un identifiant depuis le Dashboard Jèko vers Forge
 * (notamment sur iPad, où un collage a déjà corrompu un script ce même
 * jour) est une source d'erreur plausible qu'aucune vérification visuelle
 * ne peut écarter avec certitude.
 */
class JekoTesterCommand extends Command
{
    protected $signature = 'jeko:tester';

    protected $description = "Vérifie que les identifiants Jèko sont valides en lisant le solde du magasin, sans déplacer d'argent ni afficher de secret";

    /**
     * Nom affiché (celui de la variable .env) => clé de config correspondante.
     */
    private const CLES_CONFIG = [
        'JEKO_API_KEY' => 'services.jeko.api_key',
        'JEKO_API_KEY_ID' => 'services.jeko.api_key_id',
        'JEKO_STORE_ID' => 'services.jeko.store_id',
        'JEKO_WEBHOOK_SECRET' => 'services.jeko.webhook_secret',
    ];

    public function handle(): int
    {
        $this->info('=== 0. Configuration chargée vs fichier .env réel sur le disque ===');
        $valeursFichierEnv = $this->lireValeursFichierEnv();
        $decalageTrouve = false;

        foreach (self::CLES_CONFIG as $nom => $cleConfig) {
            $valeurFichier = $valeursFichierEnv[$nom] ?? '';
            $valeurConfig = config($cleConfig);

            if ($valeurFichier === '') {
                $etat = 'absent du fichier .env';
            } elseif ($valeurFichier === $valeurConfig) {
                $etat = '✅ identique';
            } else {
                $etat = '⚠️ DIFFÉRENT';
                $decalageTrouve = true;
            }

            $this->line("  {$nom} : {$etat}");
        }

        if ($this->getLaravel()->configurationIsCached()) {
            $this->newLine();
            $this->warn('  ⚠️ La configuration était EN CACHE (bootstrap/cache/config.php existe) : .env n\'a pas');
            $this->warn('  été relu par ce processus à son démarrage (comportement normal de Laravel quand la');
            $this->warn('  configuration est en cache).');
        }

        if ($decalageTrouve) {
            $this->newLine();
            $this->warn('  → Le fichier .env réel fait foi : les valeurs marquées DIFFÉRENT ci-dessus sont');
            $this->warn('    utilisées pour la suite de CE diagnostic, à la place de la configuration chargée.');
            foreach (self::CLES_CONFIG as $nom => $cleConfig) {
                $valeurFichier = $valeursFichierEnv[$nom] ?? '';
                if ($valeurFichier !== '' && $valeurFichier !== config($cleConfig)) {
                    config([$cleConfig => $valeurFichier]);
                }
            }
        }

        $this->newLine();
        $this->info('=== 1. Présence et forme des variables (sans afficher les secrets) ===');
        foreach (self::CLES_CONFIG as $nom => $cleConfig) {
            $brute = config($cleConfig);

            if ($brute === null || $brute === '') {
                $this->line("  {$nom} : absente ou vide.");
                continue;
            }

            $longueur = strlen($brute);
            $espacesParasites = trim($brute) !== $brute;
            $caractereInvisible = (bool) preg_match('/[^\x20-\x7E]/', $brute);

            if ($nom === 'JEKO_API_KEY_ID' && $longueur > 10) {
                $affichage = substr($brute, 0, 4).str_repeat('•', max($longueur - 8, 1)).substr($brute, -4)." ({$longueur} caractères)";
            } elseif ($nom === 'JEKO_API_KEY' || $nom === 'JEKO_WEBHOOK_SECRET') {
                $affichage = "{$longueur} caractères";
            } else {
                $affichage = "\"{$brute}\" ({$longueur} caractères)";
            }

            $alerte = '';
            if ($espacesParasites) {
                $alerte .= '  ⚠️ ESPACE(S) PARASITE(S) EN DÉBUT/FIN DÉTECTÉ(S)';
            }
            if ($caractereInvisible) {
                $alerte .= '  ⚠️ CARACTÈRE NON IMPRIMABLE DÉTECTÉ (copie/collage probablement corrompu)';
            }

            $this->line("  {$nom} : {$affichage}{$alerte}");
        }

        $this->newLine();
        $jeko = config('services.jeko');

        if (empty($jeko['api_key']) || empty($jeko['api_key_id']) || empty($jeko['store_id'])) {
            $this->error('❌ JEKO_API_KEY, JEKO_API_KEY_ID ou JEKO_STORE_ID est vide dans la configuration du serveur : rien à tester.');

            return self::FAILURE;
        }

        $gateway = new JekoGateway(
            apiKey: $jeko['api_key'],
            apiKeyId: $jeko['api_key_id'],
            storeId: $jeko['store_id'],
            webhookSecret: $jeko['webhook_secret'],
            baseUrl: $jeko['base_url'] ?: 'https://api.jeko.africa',
        );

        $this->info('=== 2. Appel réel à l\'API Jèko (lecture du solde, aucun argent déplacé) ===');

        try {
            $solde = $gateway->verifierSolde();
        } catch (\Throwable $e) {
            $this->error("❌ Échec de connexion à Jèko avec ces identifiants : {$e->getMessage()}");
            $this->afficherEnTetesReponse($jeko);

            return self::FAILURE;
        }

        $this->info('✅ Connexion à Jèko réussie : API_KEY, API_KEY_ID et STORE_ID sont corrects.');
        $this->info('Réponse du solde du magasin (aucune information sensible) : '.json_encode($solde));

        if (empty($jeko['webhook_secret'])) {
            $this->warn(
                "⚠️  JEKO_WEBHOOK_SECRET est vide : les paiements pourront être initiés, mais leur confirmation "
                .'automatique ne fonctionnera jamais tant que le webhook Jèko ne sera pas configuré avec ce secret.'
            );

            return self::FAILURE;
        }

        $this->info('✅ JEKO_WEBHOOK_SECRET est renseigné. La configuration Jèko est complète.');

        return self::SUCCESS;
    }

    /**
     * Refait le même appel que verifierSolde(), mais en gardant la réponse
     * complète pour en afficher les en-têtes (jamais le corps ni les
     * secrets envoyés) : demandé le 08/11/2026 pour distinguer un vrai
     * refus de Jèko (en-têtes de Jèko lui-même) d'une interception en
     * route (proxy, pare-feu applicatif) qui renverrait des en-têtes
     * différents de ceux habituels de l'API.
     */
    private function afficherEnTetesReponse(array $jeko): void
    {
        $baseUrl = $jeko['base_url'] ?: 'https://api.jeko.africa';

        try {
            $reponse = Http::withHeaders([
                'X-API-KEY' => $jeko['api_key'],
                'X-API-KEY-ID' => $jeko['api_key_id'],
            ])->get("{$baseUrl}/partner_api/stores/{$jeko['store_id']}/balance");
        } catch (\Throwable $e) {
            $this->warn("  Impossible de rejouer l'appel pour inspecter les en-têtes : {$e->getMessage()}");

            return;
        }

        $this->newLine();
        $this->line('  En-têtes envoyés (noms seulement, jamais les valeurs) : X-API-KEY, X-API-KEY-ID');
        $this->line("  Code HTTP reçu : {$reponse->status()}");
        $this->line('  En-têtes de la réponse Jèko :');
        foreach ($reponse->headers() as $nomEntete => $valeurs) {
            $this->line("    {$nomEntete} : ".implode(', ', (array) $valeurs));
        }
    }

    private function lireValeursFichierEnv(): array
    {
        $cheminEnv = $this->getLaravel()->environmentFilePath();

        if (! is_readable($cheminEnv)) {
            return [];
        }

        $valeursFichier = Dotenv::parse((string) file_get_contents($cheminEnv));
        $resultats = [];

        foreach (self::CLES_CONFIG as $nom => $cleConfig) {
            if (array_key_exists($nom, $valeursFichier)) {
                $valeur = trim((string) $valeursFichier[$nom]);
                if ($valeur !== '') {
                    $resultats[$nom] = $valeur;
                }
            }
        }

        return $resultats;
    }
}
