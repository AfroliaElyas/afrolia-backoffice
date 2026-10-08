<?php

namespace Tests\Feature;

use App\Models\Services;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServicesSecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_coiffeuse_ne_peut_plus_reassigner_son_service_a_une_autre(): void
    {
        $coiffeuseA = UsersApp::create(['name' => 'A', 'last_name' => 'Test', 'phone' => '0700000040', 'password' => 'x', 'role' => 'hair']);
        $coiffeuseB = UsersApp::create(['name' => 'B', 'last_name' => 'Test', 'phone' => '0700000041', 'password' => 'x', 'role' => 'hair']);

        $specialite = DB::table('specialites')->insertGetId([
            'libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = Services::create([
            'prix' => 5000, 'minute' => 30, 'description' => 'Tresses simples',
            'id_utilisateur' => $coiffeuseA->id_user_app, 'id_speciale' => $specialite,
        ]);

        Sanctum::actingAs($coiffeuseA);

        $this->putJson("/api/services/{$service->id_service}", [
            'prix' => 6000,
            'id_utilisateur' => $coiffeuseB->id_user_app,
        ])->assertStatus(200);

        $service->refresh();
        $this->assertEquals(6000, $service->prix);
        $this->assertSame($coiffeuseA->id_user_app, $service->id_utilisateur);
    }
}
