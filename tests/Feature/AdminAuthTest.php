<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visiteur_non_connecte_est_redirige_vers_la_connexion(): void
    {
        $this->get('/litiges')->assertRedirect('/');
        $this->get('/users')->assertRedirect('/');
    }

    public function test_un_visiteur_non_connecte_ne_peut_pas_resoudre_un_litige(): void
    {
        $this->post('/litiges/1/resoudre', ['resolution' => 'Remboursement effectué'])
            ->assertRedirect('/');
    }

    public function test_un_visiteur_non_connecte_ne_peut_pas_suspendre_un_compte(): void
    {
        $this->post('/users/1/suspend')->assertRedirect('/');
    }

    public function test_un_admin_connecte_accede_au_back_office(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/litiges')->assertOk();
        $this->actingAs($admin)->get('/users')->assertOk();
    }
}
