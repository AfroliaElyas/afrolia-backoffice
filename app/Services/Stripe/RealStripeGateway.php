<?php

namespace App\Services\Stripe;

use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class RealStripeGateway implements StripeGatewayInterface
{
    public function __construct(private readonly string $secretKey)
    {
    }

    public function createPaymentIntent(float $montant): array
    {
        Stripe::setApiKey($this->secretKey);

        // XOF est une devise "zero-decimal" pour Stripe : le montant doit
        // être transmis tel quel, sans le multiplier par 100 (contrairement
        // à EUR/USD). https://docs.stripe.com/currencies#zero-decimal
        $intent = PaymentIntent::create([
            'amount' => $montant,
            'currency' => 'xof',
            'payment_method_types' => ['card'],
        ]);

        return $this->versTableau($intent);
    }

    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        Stripe::setApiKey($this->secretKey);

        return $this->versTableau(PaymentIntent::retrieve($paymentIntentId));
    }

    public function refund(string $paymentIntentId, float $montant): array
    {
        Stripe::setApiKey($this->secretKey);

        // Même règle que pour la création : XOF est une devise
        // zéro-décimale, le montant est transmis tel quel.
        $refund = Refund::create([
            'payment_intent' => $paymentIntentId,
            'amount' => $montant,
        ]);

        return ['id' => $refund->id, 'status' => $refund->status];
    }

    private function versTableau(PaymentIntent $intent): array
    {
        return [
            'id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'status' => $intent->status,
        ];
    }
}
