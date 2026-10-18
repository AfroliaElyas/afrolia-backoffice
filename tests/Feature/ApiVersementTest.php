<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiVersementTest extends TestCase
{
    use RefreshDatabase;

    private static int $compteur = 0;

    private function creerCoiffeuse(): UsersApp
    {
        self::$compteur++;

        return UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '071111' . str_pad((string) self::$compteur, 4, '0', STR_PAD_LEFT),
            'password' => 'x', 'role' => 'hair',
        ]);
    }

    public function test_une_coiffeuse_peut_enregistrer_son_numero_de_versement(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        Sanctum::actingAs($coiffeuse);

        $response = $this->postJson("/api/versement/{$coiffeuse->id_user_app}/infos", [
            'payout_telephone' => '0712345678',
            'payout_operateur' => 'orange',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.payout_telephone', '0712345678')
            ->assertJsonPath('data.payout_operateur', 'orange');

        $this->assertSame('0712345678', $coiffeuse->fresh()->payout_telephone);
        $this->assertSame('orange', $coiffeuse->fresh()->payout_operateur);
    }

    public function test_une_coiffeuse_peut_consulter_son_numero_de_versement(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $coiffeuse->update(['payout_telephone' => '0799999999', 'payout_operateur' => 'wave']);
        Sanctum::actingAs($coiffeuse);

        $response = $this->getJson("/api/versement/{$coiffeuse->id_user_app}/infos");

        $response->assertStatus(200)
            ->assertJsonPath('data.payout_telephone', '0799999999')
            ->assertJsonPath('data.payout_operateur', 'wave');
    }

    public function test_une_coiffeuse_ne_peut_pas_modifier_les_infos_de_versement_dune_autre(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        $autre = $this->creerCoiffeuse();
        Sanctum::actingAs($coiffeuse);

        $response = $this->postJson("/api/versement/{$autre->id_user_app}/infos", [
            'payout_telephone' => '0712345678',
            'payout_operateur' => 'orange',
        ]);

        $response->assertStatus(403);
        $this->assertNull($autre->fresh()->payout_telephone);
    }

    public function test_un_operateur_invalide_est_rejete(): void
    {
        $coiffeuse = $this->creerCoiffeuse();
        Sanctum::actingAs($coiffeuse);

        $response = $this->postJson("/api/versement/{$coiffeuse->id_user_app}/infos", [
            'payout_telephone' => '0712345678',
            'payout_operateur' => 'paypal',
        ]);

        $response->assertStatus(422);
    }
}
