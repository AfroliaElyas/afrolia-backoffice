<?php

namespace Tests\Feature;

use App\Models\Paiements;
use App\Models\Reservations;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JekoPaiementTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mobile_money.driver' => 'jeko',
            'services.jeko.api_key' => 'cle',
            'services.jeko.api_key_id' => 'cle-id',
            'services.jeko.store_id' => '11111111-1111-1111-1111-111111111111',
            'services.jeko.webhook_secret' => self::SECRET,
            'services.jeko.return_url_base' => 'https://api.exemple.test',
        ]);
    }

    private function creerReservation(): array
    {
        $coiffeuse = UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000001',
            'password' => 'x', 'role' => 'hair', 'formule_abonnement' => 'gratuit',
        ]);
        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000002',
            'password' => 'x', 'role' => 'user',
        ]);
        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60, 'commission' => 500,
            'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs($client);
        $id = $this->postJson('/api/reservations', [
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_reservation' => '10:00',
            'prix_service' => 10000,
            'montant_commission' => 500,
            'montant_total' => 10500,
            'methode_paiement' => 'mobile_money',
        ])->json('data.id_reservation');

        return [$client, $id];
    }

    private function webhook(array $corps, ?string $signature = null, string $event = 'TRANSACTION_COMPLETED')
    {
        $brut = json_encode($corps);
        $signature ??= hash_hmac('sha256', $brut, self::SECRET);

        return $this->call('POST', '/api/jeko/webhook', [], [], [], [
            'HTTP_JEKO_SIGNATURE' => $signature,
            'HTTP_JEKO_EVENT' => $event,
            'CONTENT_TYPE' => 'application/json',
        ], $brut);
    }

    public function test_le_paiement_jeko_renvoie_lurl_de_paiement(): void
    {
        Http::fake([
            'api.jeko.africa/*' => Http::response([
                'id' => 'pr-123',
                'status' => 'pending',
                'redirectUrl' => 'https://pay.jeko.africa/pay_request/pr/pr-123',
            ], 201),
        ]);
        [, $idReservation] = $this->creerReservation();

        $this->postJson('/api/paiements', [
            'montant' => 1,
            'id_reservation' => $idReservation,
            'methode' => 'mobile_money',
            'operateur' => 'wave',
        ])
            ->assertStatus(200)
            ->assertJsonPath('redirect_url', 'https://pay.jeko.africa/pay_request/pr/pr-123');

        $this->assertDatabaseHas('paiements', [
            'id_reservation' => $idReservation,
            'provider_transaction_id' => 'pr-123',
            'status' => 'pending',
        ]);

        Http::assertSent(fn ($request) => $request->hasHeader('X-API-KEY', 'cle')
            && $request->hasHeader('X-API-KEY-ID', 'cle-id')
            && $request['amountCents'] === 1050000
            && $request['currency'] === 'XOF'
            && $request['paymentDetails']['data']['paymentMethod'] === 'wave');
    }

    public function test_si_jeko_est_indisponible_le_paiement_echoue_proprement(): void
    {
        Http::fake(['api.jeko.africa/*' => Http::response(['error' => 'x'], 500)]);
        [, $idReservation] = $this->creerReservation();

        $this->postJson('/api/paiements', [
            'montant' => 1, 'id_reservation' => $idReservation,
            'methode' => 'mobile_money', 'operateur' => 'orange',
        ])->assertStatus(502);

        $this->assertDatabaseHas('paiements', ['id_reservation' => $idReservation, 'status' => 'failed']);
    }

    public function test_le_webhook_jeko_confirme_le_paiement_une_seule_fois(): void
    {
        Http::fake(['api.jeko.africa/*' => Http::response([
            'id' => 'pr-123', 'status' => 'pending', 'redirectUrl' => 'https://pay.jeko.africa/x',
        ], 201)]);
        [, $idReservation] = $this->creerReservation();
        $this->postJson('/api/paiements', [
            'id_reservation' => $idReservation, 'methode' => 'mobile_money', 'operateur' => 'mtn', 'montant' => 1,
        ])->assertStatus(200);

        $corps = ['id' => 'pr-123', 'status' => 'success', 'transactionType' => 'payment', 'amount' => 10500];

        $this->webhook($corps)->assertStatus(200);
        $this->webhook($corps)->assertStatus(200); // livraison en double

        $this->assertSame('paye', Reservations::find($idReservation)->statut_paiement);
        $this->assertSame(1, DB::table('gains')->where('id_reservation', $idReservation)->count());
    }

    public function test_le_webhook_jeko_refuse_une_mauvaise_signature(): void
    {
        $paiement = Paiements::create([
            'amount' => 5000, 'currency' => 'XOF', 'payment_method' => 'mobile_money',
            'provider_transaction_id' => 'pr-9', 'status' => 'pending',
        ]);

        $this->webhook(
            ['id' => 'pr-9', 'status' => 'success', 'transactionType' => 'payment'],
            str_repeat('0', 64)
        )->assertStatus(401);

        $this->assertSame('pending', $paiement->fresh()->status);
    }

    public function test_le_webhook_jeko_ignore_les_transferts(): void
    {
        $paiement = Paiements::create([
            'amount' => 5000, 'currency' => 'XOF', 'payment_method' => 'mobile_money',
            'provider_transaction_id' => 'pr-8', 'status' => 'pending',
        ]);

        $this->webhook(['id' => 'pr-8', 'status' => 'success', 'transactionType' => 'transfer'])
            ->assertStatus(200);

        $this->assertSame('pending', $paiement->fresh()->status);
    }
}
