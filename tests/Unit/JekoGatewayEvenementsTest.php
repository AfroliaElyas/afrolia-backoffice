<?php

namespace Tests\Unit;

use App\Services\MobileMoney\JekoGateway;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie la lecture du corps TRANSACTION_COMPLETED tel que documenté par
 * Jèko (page "Événements"), avec l'exemple JSON fourni tel quel.
 */
class JekoGatewayEvenementsTest extends TestCase
{
    public function test_un_corps_transaction_completee_reussie_est_correctement_interprete(): void
    {
        $gateway = new JekoGateway('peu importe ici');

        // Exemple exact de la documentation Jèko ("Événements").
        $corps = json_decode(<<<'JSON'
        {
          "id": "txn_1234567890",
          "amount": { "amount": 10000, "currency": "XOF" },
          "fees": { "amount": 100, "currency": "XOF" },
          "status": "success",
          "counterpartLabel": "John Doe",
          "counterpartIdentifier": "+2250701234567",
          "paymentMethod": "wave",
          "transactionType": "payment",
          "businessName": "Ma Boutique",
          "storeId": "01a0b1c2-d3e4-7f89-a0b1-c2d3e4f5a6b7",
          "storeReference": "STORE-001",
          "storeName": "Magasin Principal",
          "description": "Payment for order #12345",
          "executedAt": "2024-01-15 14:30:25",
          "transactionDetails": {
            "id": "d22c81f3-ee04-4ec5-8bd2-cd8af5dabcfc",
            "reference": "PAY-2024-001",
            "paymentLinkId": "abc123def456"
          }
        }
        JSON, true);

        $resultat = $gateway->interpreterTransactionCompletee($corps);

        $this->assertSame('txn_1234567890', $resultat['id_transaction_jeko']);
        $this->assertSame('PAY-2024-001', $resultat['reference']);
        $this->assertSame('success', $resultat['statut']);
        $this->assertSame(10000, $resultat['montant']);
        $this->assertSame('XOF', $resultat['devise']);
        $this->assertSame(100, $resultat['frais']);
        $this->assertSame('wave', $resultat['methode_paiement']);
        $this->assertSame('payment', $resultat['type']);
        $this->assertSame('01a0b1c2-d3e4-7f89-a0b1-c2d3e4f5a6b7', $resultat['store_id']);
    }

    public function test_un_corps_incomplet_ne_declenche_aucune_erreur(): void
    {
        $gateway = new JekoGateway('peu importe ici');

        $resultat = $gateway->interpreterTransactionCompletee([]);

        $this->assertSame('', $resultat['id_transaction_jeko']);
        $this->assertNull($resultat['reference']);
        $this->assertSame('', $resultat['statut']);
        $this->assertSame(0, $resultat['montant']);
    }

    public function test_les_constantes_d_evenements_correspondent_a_la_documentation(): void
    {
        $this->assertSame('TRANSACTION_COMPLETED', JekoGateway::EVENT_TRANSACTION_COMPLETED);
        $this->assertSame('SERVICE_PROVIDER_LINK_REQUEST', JekoGateway::EVENT_SERVICE_PROVIDER_LINK_REQUEST);
        $this->assertSame('ESCROW_HELD', JekoGateway::EVENT_ESCROW_HELD);
        $this->assertSame('ESCROW_RELEASED', JekoGateway::EVENT_ESCROW_RELEASED);
        $this->assertSame('ESCROW_REFUNDED', JekoGateway::EVENT_ESCROW_REFUNDED);
        $this->assertSame('COMPLIANCE_VERIFICATION_COMPLETED', JekoGateway::EVENT_COMPLIANCE_VERIFICATION_COMPLETED);
    }
}
