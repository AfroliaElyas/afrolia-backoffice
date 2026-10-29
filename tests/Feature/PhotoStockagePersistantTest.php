<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Avant cette correction, les photos (profil, galerie, produits) étaient
 * enregistrées dans public/salon/..., à l'intérieur du dossier de version
 * Forge — jamais partagé entre deux déploiements zero-downtime, donc
 * perdues silencieusement au déploiement suivant (confirmé le 29/10/2026).
 * Ce test vérifie que les nouvelles photos vont bien dans storage/app/public,
 * le seul espace réellement partagé entre les versions par Forge.
 */
class PhotoStockagePersistantTest extends TestCase
{
    use RefreshDatabase;

    private function creerCoiffeuse(): UsersApp
    {
        return UsersApp::create([
            'name' => 'Aïcha', 'last_name' => 'Koné', 'phone' => '07' . random_int(10000000, 99999999),
            'password' => 'x', 'role' => 'hair',
        ]);
    }

    public function test_la_photo_dun_nouveau_produit_est_stockee_dans_storage_partage(): void
    {
        Storage::fake('public');
        $coiffeuse = $this->creerCoiffeuse();

        Sanctum::actingAs($coiffeuse);
        $response = $this->postJson('/api/produits', [
            'nom' => 'Shampooing',
            'prix' => 5000,
            'quantite_stock' => 10,
            'photo' => UploadedFile::fake()->image('produit.jpg'),
        ]);

        $response->assertStatus(201);
        $fichiers = Storage::disk('public')->allFiles('salon/produits');
        $this->assertNotEmpty($fichiers, 'La photo du produit doit être écrite dans storage/app/public/salon/produits.');
        $this->assertStringContainsString('/storage/salon/produits/', $response->json('data.photo'));
    }

    public function test_une_photo_de_galerie_est_stockee_dans_storage_partage(): void
    {
        Storage::fake('public');
        $coiffeuse = $this->creerCoiffeuse();

        Sanctum::actingAs($coiffeuse);
        $response = $this->postJson('/api/hair/gallery', [
            'images' => [
                ['image' => UploadedFile::fake()->image('photo.jpg')],
            ],
        ]);

        $response->assertStatus(200);
        $fichiers = Storage::disk('public')->allFiles('salon/gallery');
        $this->assertNotEmpty($fichiers, 'La photo de galerie doit être écrite dans storage/app/public/salon/gallery.');
        $this->assertStringContainsString('/storage/salon/gallery/', $response->json('data.0.image'));
    }
}
