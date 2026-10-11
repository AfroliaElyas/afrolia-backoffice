<?php

namespace Tests\Feature;

use App\Services\Sms\TwilioSmsGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Vérifie envoyer() contre la forme exacte de l'API REST Twilio
 * (Accounts/{Sid}/Messages.json) — aucun appel réseau réel, Http::fake()
 * simule les réponses de l'API.
 */
class TwilioSmsGatewayTest extends TestCase
{
    private function gateway(): TwilioSmsGateway
    {
        return new TwilioSmsGateway(
            accountSid: 'AC_test_sid',
            authToken: 'token_test',
            from: 'AFROLIA',
        );
    }

    public function test_envoyer_poste_la_requete_documentee_et_renvoie_vrai(): void
    {
        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC_test_sid/Messages.json' => Http::response([
                'sid' => 'SM123',
                'status' => 'queued',
            ], 201),
        ]);

        $resultat = $this->gateway()->envoyer('+2250700000000', 'Votre code Afrolia : 123456');

        $this->assertTrue($resultat);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_test_sid/Messages.json'
                && $request['To'] === '+2250700000000'
                && $request['From'] === 'AFROLIA'
                && $request['Body'] === 'Votre code Afrolia : 123456';
        });
    }

    public function test_envoyer_renvoie_faux_si_twilio_refuse_la_requete(): void
    {
        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC_test_sid/Messages.json' => Http::response([
                'code' => 21211,
                'message' => 'The \'To\' number is not a valid phone number.',
            ], 400),
        ]);

        $resultat = $this->gateway()->envoyer('invalide', 'Votre code Afrolia : 123456');

        $this->assertFalse($resultat);
    }
}
