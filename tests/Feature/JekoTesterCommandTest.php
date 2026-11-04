<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JekoTesterCommandTest extends TestCase
{
    private function configurerJeko(?string $webhookSecret = 'secret_test'): void
    {
        config([
            'services.jeko.api_key' => 'cle_test',
            'services.jeko.api_key_id' => 'cle_id_test',
            'services.jeko.store_id' => 'magasin_test',
            'services.jeko.webhook_secret' => $webhookSecret,
            'services.jeko.base_url' => 'https://api.jeko.africa',
        ]);
    }

    public function test_echoue_proprement_si_les_identifiants_sont_vides(): void
    {
        config(['services.jeko.api_key' => null, 'services.jeko.api_key_id' => null, 'services.jeko.store_id' => null]);

        $this->artisan('jeko:tester')
            ->assertFailed()
            ->expectsOutputToContain('rien à tester');
    }

    public function test_reussit_et_confirme_le_webhook_si_tout_est_configure(): void
    {
        $this->configurerJeko();
        Http::fake([
            'api.jeko.africa/partner_api/stores/*/balance' => Http::response(['balance' => 50000], 200),
        ]);

        $this->artisan('jeko:tester')
            ->assertSuccessful()
            ->expectsOutputToContain('Connexion à Jèko réussie')
            ->expectsOutputToContain('configuration Jèko est complète');
    }

    public function test_echoue_proprement_si_jeko_refuse_les_identifiants(): void
    {
        $this->configurerJeko();
        Http::fake([
            'api.jeko.africa/partner_api/stores/*/balance' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->artisan('jeko:tester')
            ->assertFailed()
            ->expectsOutputToContain('Échec de connexion à Jèko');
    }

    public function test_avertit_si_le_secret_de_webhook_manque(): void
    {
        $this->configurerJeko(webhookSecret: null);
        Http::fake([
            'api.jeko.africa/partner_api/stores/*/balance' => Http::response(['balance' => 50000], 200),
        ]);

        $this->artisan('jeko:tester')
            ->assertFailed()
            ->expectsOutputToContain('Connexion à Jèko réussie')
            ->expectsOutputToContain('JEKO_WEBHOOK_SECRET est vide');
    }
}
