<?php

namespace App\Providers;

use App\Services\MobileMoney\GenericMobileMoneyGateway;
use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MobileMoneyGatewayInterface::class, GenericMobileMoneyGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
