<?php

namespace Tests\Feature;

use App\Services\MobileMoney\MobileMoneyGatewayInterface;
use App\Services\Stripe\StripeGatewayInterface;
use Tests\TestCase;

/**
 * Garde-fou permanent : vérifie que StripeGatewayInterface et
 * MobileMoneyGatewayInterface restent bloqués par défaut pendant les tests
 * (voir AppServiceProvider), même si quelqu'un modifie un jour cette
 * condition par erreur. Sans ce test, une régression ici ne serait
 * découverte qu'en constatant un vrai appel à Stripe/Jèko en production au
 * moment où la suite de tests tourne sur le serveur réel (voir
 * deploy/forge-deploy.sh).
 */
class GatewaysBloquesEnTestTest extends TestCase
{
    public function test_lenvironnement_de_test_est_bien_force(): void
    {
        $this->assertSame('testing', app()->environment());
    }

    public function test_stripe_reel_est_bloque_par_defaut_pendant_les_tests(): void
    {
        $gateway = app(StripeGatewayInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Aucun appel réel à Stripe/');

        $gateway->createPaymentIntent(1000);
    }

    public function test_jeko_reel_est_bloque_par_defaut_pendant_les_tests(): void
    {
        $gateway = app(MobileMoneyGatewayInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Aucun appel réel à Jèko/');

        $gateway->verifyWebhookSignature('{}', null);
    }
}
