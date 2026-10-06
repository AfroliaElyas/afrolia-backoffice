<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\Ia\AnthropicClaudeClient;
use App\Services\Ia\ClaudeClientInterface;
use App\Services\MobileMoney\GenericMobileMoneyGateway;
use App\Services\MobileMoney\JekoMobileMoneyGateway;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MobileMoneyGatewayInterface::class, fn () => match (config('services.mobile_money.driver')) {
            'jeko' => new JekoMobileMoneyGateway(),
            default => new GenericMobileMoneyGateway(),
        });

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
        //
    }
}
