<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Stripe\Webhook;
use Tests\TestCase;

class TesterWebhookStripeTest extends TestCase
{
    private const SECRET = 'whsec_test_diagnostic';

    public function test_envoie_une_requete_avec_une_signature_valide_pour_le_secret_configure(): void
    {
        config(['services.stripe.webhook_secret' => self::SECRET]);
        config(['app.url' => 'https://afrolia-app.test']);

        $requeteCapturee = null;
        Http::fake(function ($request) use (&$requeteCapturee) {
            $requeteCapturee = $request;

            return Http::response(['status' => 'ok'], 200);
        });

        $this->artisan('stripe:tester-webhook')->assertExitCode(0);

        $this->assertNotNull($requeteCapturee);
        $this->assertSame('https://afrolia-app.test/api/stripe/webhook', $requeteCapturee->url());

        // La signature envoyée doit être exactement celle que
        // \Stripe\Webhook::constructEvent (utilisée côté serveur réel)
        // accepterait pour ce secret — preuve que le test reproduit
        // fidèlement ce que Stripe enverrait, pas une approximation.
        $signature = $requeteCapturee->header('Stripe-Signature')[0];
        $event = Webhook::constructEvent($requeteCapturee->body(), $signature, self::SECRET);
        $this->assertSame('payment_intent.succeeded', $event->type);
    }

    public function test_respecte_l_option_url(): void
    {
        config(['services.stripe.webhook_secret' => self::SECRET]);

        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        $this->artisan('stripe:tester-webhook', ['--url' => 'https://autre-domaine.test/api/stripe/webhook'])
            ->assertExitCode(0);

        Http::assertSent(fn ($request) => $request->url() === 'https://autre-domaine.test/api/stripe/webhook');
    }

    public function test_echoue_proprement_si_le_secret_est_vide(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        Http::fake();

        $this->artisan('stripe:tester-webhook')->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_remonte_un_echec_si_le_serveur_refuse_la_requete(): void
    {
        config(['services.stripe.webhook_secret' => self::SECRET]);

        Http::fake(['*' => Http::response(['error' => 'Signature invalide'], 400)]);

        $this->artisan('stripe:tester-webhook')->assertExitCode(1);
    }
}
