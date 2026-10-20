<?php

namespace Tests\Feature;

use App\Models\Specialites;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialiserSpecialitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_les_specialites_de_base(): void
    {
        $this->artisan('specialites:initialiser')
            ->assertExitCode(0);

        $this->assertDatabaseHas('specialites', ['libelle' => 'Tresses africaines']);
        $this->assertDatabaseHas('specialites', ['libelle' => 'Locks / Dreadlocks']);
        $this->assertGreaterThan(1, Specialites::count());
    }

    public function test_relancer_la_commande_ne_cree_pas_de_doublon(): void
    {
        $this->artisan('specialites:initialiser')->assertExitCode(0);
        $nombreApresPremierAppel = Specialites::count();

        $this->artisan('specialites:initialiser')->assertExitCode(0);

        $this->assertSame($nombreApresPremierAppel, Specialites::count());
    }

    public function test_ne_touche_pas_une_specialite_deja_existante(): void
    {
        $specialite = Specialites::create(['libelle' => 'Tresses africaines']);

        $this->artisan('specialites:initialiser')->assertExitCode(0);

        $this->assertSame(1, Specialites::where('libelle', 'Tresses africaines')->count());
        $this->assertSame($specialite->id_specialite, Specialites::where('libelle', 'Tresses africaines')->first()->id_specialite);
    }
}
