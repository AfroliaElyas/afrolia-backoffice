<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\Ia\AnthropicClaudeClient;
use App\Services\Ia\ClaudeClientInterface;
use App\Services\MobileMoney\GenericMobileMoneyGateway;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use App\Services\Sms\GenericSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
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
        $this->app->bind(MobileMoneyGatewayInterface::class, GenericMobileMoneyGateway::class);
        $this->app->bind(SmsGatewayInterface::class, GenericSmsGateway::class);

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
