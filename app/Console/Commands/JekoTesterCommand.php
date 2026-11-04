<?php

namespace App\Console\Commands;

use App\Services\MobileMoney\JekoGateway;
use Illuminate\Console\Command;

/**
 * Vérifie que JEKO_API_KEY / JEKO_API_KEY_ID / JEKO_STORE_ID fonctionnent
 * réellement (vrai appel à l'API Jèko, lecture du solde du magasin —
 * aucun argent déplacé), et que JEKO_WEBHOOK_SECRET est renseigné.
 * Exécutable depuis l'onglet "Commands" de Forge, sans terminal. Même
 * esprit que backblaze:diagnostiquer et notifications:tester : jamais de
 * secret affiché (demandé le 04/11/2026).
 */
class JekoTesterCommand extends Command
{
    protected $signature = 'jeko:tester';

    protected $description = "Vérifie que les identifiants Jèko sont valides en lisant le solde du magasin, sans déplacer d'argent ni afficher de secret";

    public function handle(): int
    {
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

        try {
            $solde = $gateway->verifierSolde();
        } catch (\Throwable $e) {
            $this->error("❌ Échec de connexion à Jèko avec ces identifiants : {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('✅ Connexion à Jèko réussie : API_KEY, API_KEY_ID et STORE_ID sont corrects.');
        $this->info('Réponse du solde du magasin (aucune information sensible) : ' . json_encode($solde));

        if (empty($jeko['webhook_secret'])) {
            $this->warn(
                "⚠️  JEKO_WEBHOOK_SECRET est vide : les paiements pourront être initiés, mais leur confirmation "
                . 'automatique ne fonctionnera jamais tant que le webhook Jèko ne sera pas configuré avec ce secret.'
            );

            return self::FAILURE;
        }

        $this->info('✅ JEKO_WEBHOOK_SECRET est renseigné. La configuration Jèko est complète.');

        return self::SUCCESS;
    }
}
