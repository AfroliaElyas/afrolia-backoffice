<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreerAccesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_un_nouveau_compte_si_lemail_nexiste_pas(): void
    {
        $this->artisan('admin:creer-acces', ['email' => 'nouvelle-admin@afrolia-app.com'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'nouvelle-admin@afrolia-app.com']);
    }

    public function test_reinitialise_le_mot_de_passe_dun_compte_existant_sans_en_creer_un_autre(): void
    {
        $utilisateur = User::create([
            'name' => 'Admin',
            'email' => 'admin-existant@afrolia-app.com',
            'password' => Hash::make('ancien-mot-de-passe'),
        ]);
        $ancienHash = $utilisateur->password;

        $this->artisan('admin:creer-acces', ['email' => 'admin-existant@afrolia-app.com'])
            ->assertExitCode(0);

        $this->assertSame(1, User::where('email', 'admin-existant@afrolia-app.com')->count());
        $this->assertNotSame($ancienHash, $utilisateur->fresh()->password);
    }

    public function test_deux_appels_generent_des_mots_de_passe_differents(): void
    {
        $this->artisan('admin:creer-acces', ['email' => 'connexion-test@afrolia-app.com'])
            ->assertExitCode(0);
        $utilisateur = User::where('email', 'connexion-test@afrolia-app.com')->firstOrFail();
        $premierHash = $utilisateur->password;

        $this->artisan('admin:creer-acces', ['email' => 'connexion-test@afrolia-app.com'])
            ->assertExitCode(0);

        $this->assertNotSame($premierHash, $utilisateur->fresh()->password);
    }

    public function test_refuse_une_adresse_email_invalide(): void
    {
        $this->artisan('admin:creer-acces', ['email' => 'pas-un-email'])
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }
}
