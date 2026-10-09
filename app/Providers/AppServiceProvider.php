<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\Ia\AnthropicClaudeClient;
use App\Services\Ia\ClaudeClientInterface;
use App\Services\MobileMoney\GenericMobileMoneyGateway;
use App\Services\MobileMoney\JekoGateway;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use App\Services\Stripe\RealStripeGateway;
use App\Services\Stripe\StripeGatewayInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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

        $this->app->singleton(AnthropicClient::class, fn () => new AnthropicClient(
            apiKey: config('services.anthropic.api_key'),
        ));
        $this->app->bind(ClaudeClientInterface::class, AnthropicClaudeClient::class);

        $this->app->bind(StripeGatewayInterface::class, fn () => new RealStripeGateway(
            secretKey: (string) config('services.stripe.secret'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
