<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminComptesCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lister_comptes_affiche_les_comptes_existants(): void
    {
        User::create(['name' => 'Ruth', 'email' => 'ruth@example.com', 'password' => 'x']);

        $this->artisan('admin:lister-comptes')
            ->assertExitCode(0)
            ->expectsOutputToContain('ruth@example.com');
    }

    public function test_lister_comptes_sans_compte_previent_sans_planter(): void
    {
        $this->artisan('admin:lister-comptes')
            ->assertExitCode(0)
            ->expectsOutputToContain('Aucun compte admin trouvé');
    }

    public function test_reinitialiser_mot_de_passe_change_bien_le_mot_de_passe(): void
    {
        $utilisateur = User::create(['name' => 'Ruth', 'email' => 'ruth@example.com', 'password' => Hash::make('ancien')]);

        $this->artisan('admin:reinitialiser-mot-de-passe', ['email' => 'ruth@example.com'])
            ->assertExitCode(0);

        $utilisateur->refresh();
        $this->assertFalse(Hash::check('ancien', $utilisateur->password));
    }

    public function test_reinitialiser_mot_de_passe_echoue_proprement_si_email_inconnu(): void
    {
        $this->artisan('admin:reinitialiser-mot-de-passe', ['email' => 'inconnu@example.com'])
            ->assertExitCode(1)
            ->expectsOutputToContain('Aucun compte admin trouvé');
    }
}
