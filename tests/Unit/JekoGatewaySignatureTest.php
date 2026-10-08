<?php

namespace Tests\Unit;

use App\Services\MobileMoney\JekoGateway;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie l'algorithme de signature documenté par Jèko ("Introduction aux
 * Webhooks") : HMAC-SHA256 du corps brut, en hexadécimal, comparé à
 * l'en-tête Jeko-Signature.
 */
class JekoGatewaySignatureTest extends TestCase
{
    private const SECRET = 'secret_webhook_test';

    private function signer(string $payload): string
    {
        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public function test_une_signature_correcte_est_acceptee(): void
    {
        $gateway = new JekoGateway(self::SECRET);
        $payload = '{"type":"payment","reference":"abc123"}';

        $this->assertTrue($gateway->verifyWebhookSignature($payload, $this->signer($payload)));
    }

    public function test_une_signature_calculee_sur_un_corps_modifie_est_refusee(): void
    {
        $gateway = new JekoGateway(self::SECRET);
        $payload = '{"type":"payment","reference":"abc123"}';
        $signatureDuCorpsOriginal = $this->signer($payload);

        $corpsModifie = '{"type":"payment","reference":"abc124"}';

        $this->assertFalse($gateway->verifyWebhookSignature($corpsModifie, $signatureDuCorpsOriginal));
    }

    public function test_une_signature_falsifiee_est_refusee(): void
    {
        $gateway = new JekoGateway(self::SECRET);
        $payload = '{"type":"payment","reference":"abc123"}';

        $this->assertFalse($gateway->verifyWebhookSignature($payload, 'signature_falsifiee'));
    }

    public function test_une_signature_absente_est_refusee(): void
    {
        $gateway = new JekoGateway(self::SECRET);

        $this->assertFalse($gateway->verifyWebhookSignature('{"type":"payment"}', null));
    }

    public function test_sans_secret_configure_tout_est_refuse(): void
    {
        $gateway = new JekoGateway(null);
        $payload = '{"type":"payment"}';

        $this->assertFalse($gateway->verifyWebhookSignature($payload, hash_hmac('sha256', $payload, 'peu importe')));
    }

    public function test_initiate_n_est_pas_encore_implementable(): void
    {
        $gateway = new JekoGateway(self::SECRET);

        $this->expectException(\RuntimeException::class);

        $gateway->initiate(new \App\Models\Paiements(), 'orange', '0700000000');
    }
}
