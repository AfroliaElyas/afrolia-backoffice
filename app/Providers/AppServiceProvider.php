<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\Ia\AnthropicClaudeClient;
use App\Services\Ia\ClaudeClientInterface;
use App\Services\MobileMoney\BlockedMobileMoneyGateway;
use App\Services\MobileMoney\GenericMobileMoneyGateway;
use App\Services\MobileMoney\JekoGateway;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use App\Services\Stripe\BlockedStripeGateway;
use App\Services\Stripe\RealStripeGateway;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // En environnement de test (app()->environment('testing'), forcé par
        // phpunit.xml même si le vrai .env du serveur dit autre chose — voir
        // ce fichier), AUCUN appel réel à Stripe ou Jèko n'est permis : on
        // lie des implémentations qui lèvent une exception dès qu'on les
        // appelle. Un test qui a besoin d'un comportement précis remplace ce
        // faux par le sien ($this->app->instance(...)), comme le font déjà
        // tous les tests existants qui touchent aux paiements. Sans ce
        // garde-fou, exécuter la suite de tests directement sur le serveur
        // de production (filet de sécurité de deploy/forge-deploy.sh)
        // risquerait d'appeler les vraies clés Stripe/Jèko si un test
        // oubliait de les remplacer (demandé le 24/10/2026).
        if ($this->app->environment('testing')) {
            $this->app->bind(MobileMoneyGatewayInterface::class, BlockedMobileMoneyGateway::class);
            $this->app->bind(StripeGatewayInterface::class, BlockedStripeGateway::class);
        } else {
            // Jèko n'est branché comme connecteur actif que si ses identifiants
            // sont réellement configurés (clé API, clé API ID, magasin) : tant
            // qu'ils manquent, on retombe sur GenericMobileMoneyGateway, qui
            // refuse honnêtement (503) plutôt que de simuler un paiement.
            $this->app->bind(MobileMoneyGatewayInterface::class, function () {
                $jeko = config('services.jeko');

                if (!empty($jeko['api_key']) && !empty($jeko['api_key_id']) && !empty($jeko['store_id'])) {
                    return new JekoGateway(
                        apiKey: $jeko['api_key'],
                        apiKeyId: $jeko['api_key_id'],
                        storeId: $jeko['store_id'],
                        webhookSecret: $jeko['webhook_secret'],
                        baseUrl: $jeko['base_url'] ?: 'https://api.jeko.africa',
                        successUrl: $jeko['success_url'] ?: 'afrolia://paiement/succes',
                        errorUrl: $jeko['error_url'] ?: 'afrolia://paiement/echec',
                    );
                }

                return new GenericMobileMoneyGateway();
            });

            $this->app->bind(StripeGatewayInterface::class, fn () => new RealStripeGateway(
                secretKey: (string) config('services.stripe.secret'),
            ));
        }

        $this->app->singleton(AnthropicClient::class, fn () => new AnthropicClient(
            apiKey: config('services.anthropic.api_key'),
        ));
        $this->app->bind(ClaudeClientInterface::class, AnthropicClaudeClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Envoi des e-mails via l'API HTTPS de Brevo (port 443) plutôt que
        // son SMTP (port 587) : DigitalOcean bloque les ports SMTP
        // sortants par défaut sur les droplets, ce qui fait échouer tout
        // envoi SMTP avec un "Connection timed out".
        Mail::extend('brevo', fn () => new BrevoApiTransport((string) config('services.brevo.key')));
    }
}
