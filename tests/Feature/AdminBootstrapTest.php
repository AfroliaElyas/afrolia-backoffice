<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/creer-compte-admin/YvvKORAIKWjEJr5X9qs1F1MHp1YzIynf';

    public function test_le_formulaire_s_affiche_quand_aucun_compte_n_existe(): void
    {
        $this->get(self::URL)->assertStatus(200)->assertSee('Créer le compte administrateur');
    }

    public function test_creer_le_compte_avec_un_mot_de_passe_valide(): void
    {
        $reponse = $this->post(self::URL, [
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
        ]);

        $reponse->assertStatus(200)->assertSee('Compte créé');

        $utilisateur = User::where('email', 'afroliaofficiel@gmail.com')->first();
        $this->assertNotNull($utilisateur);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('motdepasse123', $utilisateur->password));
    }

    public function test_mots_de_passe_qui_ne_correspondent_pas_sont_refuses(): void
    {
        $this->post(self::URL, [
            'password' => 'motdepasse123',
            'password_confirmation' => 'autrechose',
        ])->assertSessionHasErrors();

        $this->assertNull(User::where('email', 'afroliaofficiel@gmail.com')->first());
    }

    public function test_ne_touche_a_rien_si_un_compte_existe_deja(): void
    {
        User::create([
            'name' => 'Existant',
            'email' => 'afroliaofficiel@gmail.com',
            'password' => \Illuminate\Support\Facades\Hash::make('ancien-mot-de-passe'),
        ]);

        $this->get(self::URL)->assertSee('existe déjà');

        $this->post(self::URL, [
            'password' => 'nouveaumotdepasse',
            'password_confirmation' => 'nouveaumotdepasse',
        ])->assertSessionHasErrors();

        $utilisateur = User::where('email', 'afroliaofficiel@gmail.com')->first();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('ancien-mot-de-passe', $utilisateur->password));
    }
}
