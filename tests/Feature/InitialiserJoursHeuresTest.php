<?php

namespace Tests\Feature;

use App\Models\Heures;
use App\Models\Jours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialiserJoursHeuresTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_les_jours_et_heures_de_base(): void
    {
        $this->artisan('jours-heures:initialiser')
            ->assertExitCode(0);

        $this->assertDatabaseHas('jours', ['libelle' => 'Lundi']);
        $this->assertDatabaseHas('jours', ['libelle' => 'Dimanche']);
        $this->assertDatabaseHas('heures', ['libelle' => '08:00']);
        $this->assertSame(7, Jours::count());
        $this->assertGreaterThan(1, Heures::count());
    }

    public function test_relancer_la_commande_ne_cree_pas_de_doublon(): void
    {
        $this->artisan('jours-heures:initialiser')->assertExitCode(0);
        $nombreJours = Jours::count();
        $nombreHeures = Heures::count();

        $this->artisan('jours-heures:initialiser')->assertExitCode(0);

        $this->assertSame($nombreJours, Jours::count());
        $this->assertSame($nombreHeures, Heures::count());
    }

    public function test_ne_touche_pas_un_jour_deja_existant(): void
    {
        $jour = Jours::create(['libelle' => 'Lundi']);

        $this->artisan('jours-heures:initialiser')->assertExitCode(0);

        $this->assertSame(1, Jours::where('libelle', 'Lundi')->count());
        $this->assertSame($jour->id_jour, Jours::where('libelle', 'Lundi')->first()->id_jour);
    }

    public function test_permet_ensuite_denregistrer_une_disponibilite(): void
    {
        $this->artisan('jours-heures:initialiser')->assertExitCode(0);

        $coiffeuse = \App\Models\UsersApp::create([
            'name' => 'Coiffeuse', 'last_name' => 'Test', 'phone' => '0700000060',
            'password' => 'x', 'role' => 'hair',
        ]);
        \Laravel\Sanctum\Sanctum::actingAs($coiffeuse);

        $jour = Jours::where('libelle', 'Lundi')->first();
        $heure = Heures::where('libelle', '09:00')->first();

        $response = $this->postJson('/api/disponibilites', [
            'disponibilites' => [
                ['id_day' => $jour->id_jour, 'id_time' => $heure->id_heure],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('disponibilites', [
            'id_utilisateur' => $coiffeuse->id_user_app,
            'id_day' => $jour->id_jour,
            'id_time' => $heure->id_heure,
        ]);
    }
}
