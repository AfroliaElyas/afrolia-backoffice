<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GainsSecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_coiffeuse_ne_peut_plus_fabriquer_ses_propres_gains(): void
    {
        $coiffeuse = UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000020', 'password' => 'x', 'role' => 'hair',
        ]);
        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/gains-coiffeuses/', [
            'id_reservation' => 1,
            'montant_brut' => 999999,
            'montant_commission' => 0,
            'montant_net' => 999999,
            'statut' => 'paye',
        ])->assertStatus(404);

        $this->putJson('/api/gains-coiffeuses/1', ['montant_net' => 999999])->assertStatus(404);
        $this->deleteJson('/api/gains-coiffeuses/1')->assertStatus(404);
    }
}
