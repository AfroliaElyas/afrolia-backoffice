<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilSecuriteTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(string $phone, string $role = 'hair'): UsersApp
    {
        return UsersApp::create([
            'name' => 'Test', 'last_name' => 'Test', 'phone' => $phone, 'password' => 'x', 'role' => $role,
        ]);
    }

    // updateinfobasic laissait passer n'importe quelle chaîne comme e-mail,
    // sans vérifier l'unicité ni réinitialiser email_verified_at : une
    // coiffeuse avec un e-mail déjà vérifié pouvait ensuite le remplacer par
    // l'adresse de quelqu'un d'autre et la voir rester marquée "vérifiée",
    // ce que demanderReinitialisationParEmail() utilise comme preuve de
    // propriété de l'adresse.
    public function test_changer_son_email_reinitialise_la_verification(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000080');
        $coiffeuse->forceFill(['email' => 'ancienne@example.com', 'email_verified_at' => now()])->save();

        Sanctum::actingAs($coiffeuse);

        $this->postJson("/api/updateinfobasic/{$coiffeuse->id_user_app}", [
            'email' => 'nouvelle@example.com',
        ])->assertStatus(200);

        $coiffeuse->refresh();
        $this->assertSame('nouvelle@example.com', $coiffeuse->email);
        $this->assertNull($coiffeuse->email_verified_at);
    }

    public function test_garder_le_meme_email_ne_reinitialise_pas_la_verification(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000081');
        $coiffeuse->forceFill(['email' => 'moi@example.com', 'email_verified_at' => now()])->save();

        Sanctum::actingAs($coiffeuse);

        $this->postJson("/api/updateinfobasic/{$coiffeuse->id_user_app}", [
            'email' => 'moi@example.com',
            'nom' => 'Nouveau nom',
        ])->assertStatus(200);

        $coiffeuse->refresh();
        $this->assertNotNull($coiffeuse->email_verified_at);
    }

    public function test_impossible_de_prendre_l_email_deja_verifie_de_quelqu_un_d_autre(): void
    {
        $this->creerUtilisateur('0700000082')->forceFill([
            'email' => 'victime@example.com', 'email_verified_at' => now(),
        ])->save();
        $attaquant = $this->creerUtilisateur('0700000083');

        Sanctum::actingAs($attaquant);

        $this->postJson("/api/updateinfobasic/{$attaquant->id_user_app}", [
            'email' => 'victime@example.com',
        ])->assertStatus(422);
    }

    public function test_un_email_mal_forme_est_rejete(): void
    {
        $coiffeuse = $this->creerUtilisateur('0700000084');
        Sanctum::actingAs($coiffeuse);

        $this->postJson("/api/updateinfobasic/{$coiffeuse->id_user_app}", [
            'email' => 'pas-un-email',
        ])->assertStatus(422);
    }

    public function test_la_photo_de_profil_a_une_taille_maximale(): void
    {
        Storage::fake('public');
        $utilisateur = $this->creerUtilisateur('0700000085', 'user');
        Sanctum::actingAs($utilisateur);

        $photoTropLourde = UploadedFile::fake()->image('photo.jpg')->size(6000); // 6MB > 5MB

        $this->postJson("/api/update/{$utilisateur->id_user_app}", [
            'photo' => $photoTropLourde,
        ])->assertStatus(422);
    }
}
