<?php

namespace Tests\Feature;

use App\Models\Paiements;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaiementsSecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_ne_peut_plus_trafiquer_le_statut_de_son_paiement(): void
    {
        $client = UsersApp::create([
            'name' => 'Client', 'last_name' => 'Test', 'phone' => '0700000021', 'password' => 'x', 'role' => 'user',
        ]);
        $paiement = Paiements::create([
            'amount' => 5000,
            'currency' => 'XOF',
            'payment_method' => 'mobile_money',
            'status' => 'pending',
        ]);
        Sanctum::actingAs($client);

        $this->putJson("/api/paiements/{$paiement->id_paiement}", ['status' => 'succeeded'])
            ->assertStatus(404);
        $this->deleteJson("/api/paiements/{$paiement->id_paiement}")
            ->assertStatus(404);

        $this->assertSame('pending', $paiement->fresh()->status);
    }
}
