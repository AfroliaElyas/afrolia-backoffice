<?php

namespace Tests\Feature;

use App\Models\Langues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialiserLanguesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_les_langues_de_base(): void
    {
        $this->artisan('langues:initialiser')
            ->assertExitCode(0);

        $this->assertDatabaseHas('langues', ['libelle' => 'Français']);
        $this->assertDatabaseHas('langues', ['libelle' => 'Dioula']);
        $this->assertGreaterThan(1, Langues::count());
    }

    public function test_relancer_la_commande_ne_cree_pas_de_doublon(): void
    {
        $this->artisan('langues:initialiser')->assertExitCode(0);
        $nombreApresPremierAppel = Langues::count();

        $this->artisan('langues:initialiser')->assertExitCode(0);

        $this->assertSame($nombreApresPremierAppel, Langues::count());
    }

    public function test_ne_touche_pas_une_langue_deja_existante(): void
    {
        $langue = Langues::create(['libelle' => 'Français']);

        $this->artisan('langues:initialiser')->assertExitCode(0);

        $this->assertSame(1, Langues::where('libelle', 'Français')->count());
        $this->assertSame($langue->id_langue, Langues::where('libelle', 'Français')->first()->id_langue);
    }
}
