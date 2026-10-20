<?php

namespace Tests\Feature;

use App\Models\Services;
use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Une catégorie (specialites) reste générique (ex. "Box braids") : c'est le
 * service lui-même qui porte le nom précis de la prestation (ex. "Box
 * braids courtes"), pour qu'une coiffeuse puisse proposer plusieurs
 * prestations dans une même catégorie avec des prix et durées différents.
 */
class ServicesPrestationsTest extends TestCase
{
    use RefreshDatabase;

    private function creerCoiffeuse(string $phone): UsersApp
    {
        return UsersApp::create([
            'name' => 'Coiffeuse',
            'last_name' => 'Test',
            'phone' => $phone,
            'password' => 'x',
            'role' => 'hair',
        ]);
    }

    private function creerSpecialite(string $libelle = 'Box braids'): int
    {
        return DB::table('specialites')->insertGetId([
            'libelle' => $libelle, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_cree_une_prestation_avec_nom_longueur_et_meches_comprises(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000050');
        $specialite = $this->creerSpecialite();

        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/hair/services', [
            'nom' => 'Box braids courtes',
            'prix' => 15000,
            'minute' => 120,
            'description' => 'Box braids épaules, mèches synthétiques incluses',
            'longueur' => 'Épaules',
            'meches_comprises' => true,
            'id_speciale' => $specialite,
        ])->assertStatus(201);

        $this->assertDatabaseHas('services', [
            'nom' => 'Box braids courtes',
            'longueur' => 'Épaules',
            'meches_comprises' => 1,
            'id_utilisateur' => $coiffeuse->id_user_app,
        ]);
    }

    public function test_longueur_et_meches_comprises_restent_facultatifs(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000051');
        $specialite = $this->creerSpecialite('Coupe & coiffure');

        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/hair/services', [
            'nom' => 'Coupe simple',
            'prix' => 3000,
            'minute' => 30,
            'description' => 'Coupe et mise en forme',
            'id_speciale' => $specialite,
        ])->assertStatus(201);

        $this->assertDatabaseHas('services', [
            'nom' => 'Coupe simple',
            'longueur' => null,
            'meches_comprises' => null,
        ]);
    }

    public function test_refuse_une_prestation_sans_nom(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000052');
        $specialite = $this->creerSpecialite();

        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/hair/services', [
            'prix' => 15000,
            'minute' => 120,
            'description' => 'Box braids épaules',
            'id_speciale' => $specialite,
        ])->assertStatus(422);

        $this->assertSame(0, Services::count());
    }

    public function test_permet_deux_prestations_dans_la_meme_categorie_avec_prix_et_durees_differents(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000053');
        $specialite = $this->creerSpecialite();

        Sanctum::actingAs($coiffeuse);

        $this->postJson('/api/hair/services', [
            'services' => [
                [
                    'nom' => 'Box braids courtes',
                    'prix' => 15000,
                    'minute' => 120,
                    'description' => 'Épaules',
                    'longueur' => 'Épaules',
                    'meches_comprises' => true,
                    'id_speciale' => $specialite,
                ],
                [
                    'nom' => 'Box braids longues',
                    'prix' => 25000,
                    'minute' => 240,
                    'description' => 'Jusqu\'aux hanches',
                    'longueur' => 'Hanches',
                    'meches_comprises' => false,
                    'id_speciale' => $specialite,
                ],
            ],
        ])->assertStatus(201);

        $this->assertSame(2, Services::where('id_speciale', $specialite)->count());
        $this->assertDatabaseHas('services', ['nom' => 'Box braids courtes', 'prix' => 15000, 'minute' => 120]);
        $this->assertDatabaseHas('services', ['nom' => 'Box braids longues', 'prix' => 25000, 'minute' => 240]);
    }

    public function test_le_prix_nest_pas_impose_de_maniere_identique_entre_coiffeuses(): void
    {
        $coiffeuseA = $this->creerCoiffeuse('0700000054');
        $coiffeuseB = $this->creerCoiffeuse('0700000055');
        $specialite = $this->creerSpecialite();

        Sanctum::actingAs($coiffeuseA);
        $this->postJson('/api/hair/services', [
            'nom' => 'Box braids courtes',
            'prix' => 15000,
            'minute' => 120,
            'description' => 'Épaules',
            'id_speciale' => $specialite,
        ])->assertStatus(201);

        Sanctum::actingAs($coiffeuseB);
        $this->postJson('/api/hair/services', [
            'nom' => 'Box braids courtes',
            'prix' => 20000,
            'minute' => 90,
            'description' => 'Épaules',
            'id_speciale' => $specialite,
        ])->assertStatus(201);

        $this->assertDatabaseHas('services', ['id_utilisateur' => $coiffeuseA->id_user_app, 'prix' => 15000]);
        $this->assertDatabaseHas('services', ['id_utilisateur' => $coiffeuseB->id_user_app, 'prix' => 20000]);
    }

    public function test_met_a_jour_le_nom_dune_prestation_existante(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000056');
        $specialite = $this->creerSpecialite();
        $service = Services::create([
            'nom' => 'Box braids',
            'prix' => 15000,
            'minute' => 120,
            'description' => 'Épaules',
            'id_utilisateur' => $coiffeuse->id_user_app,
            'id_speciale' => $specialite,
        ]);

        Sanctum::actingAs($coiffeuse);

        $this->putJson("/api/services/{$service->id_service}", [
            'nom' => 'Box braids courtes',
            'longueur' => 'Épaules',
            'meches_comprises' => true,
        ])->assertStatus(200);

        $service->refresh();
        $this->assertSame('Box braids courtes', $service->nom);
        $this->assertSame('Épaules', $service->longueur);
        $this->assertTrue($service->meches_comprises);
    }

    public function test_un_service_deja_enregistre_sans_les_nouveaux_champs_reste_valide(): void
    {
        $coiffeuse = $this->creerCoiffeuse('0700000057');
        $specialite = $this->creerSpecialite();

        // Simule un service créé avant l'ajout de nom/longueur/meches_comprises.
        $service = Services::create([
            'prix' => 5000,
            'minute' => 30,
            'description' => 'Tresses simples',
            'id_utilisateur' => $coiffeuse->id_user_app,
            'id_speciale' => $specialite,
        ]);

        $this->assertDatabaseHas('services', ['id_service' => $service->id_service, 'nom' => null]);
    }
}
