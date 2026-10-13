<?php

namespace Tests\Feature;

use App\Models\InscriptionLancement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InscriptionLancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_adresse_e_mail_valide_est_enregistree(): void
    {
        $reponse = $this->postJson('lancement/inscription', ['email' => 'cliente@example.com']);

        $reponse->assertStatus(200);
        $reponse->assertJson(['success' => true]);
        $this->assertDatabaseHas('inscriptions_lancement', ['email' => 'cliente@example.com']);
    }

    public function test_une_adresse_e_mail_invalide_est_refusee(): void
    {
        $reponse = $this->postJson('lancement/inscription', ['email' => 'pas-une-adresse']);

        $reponse->assertStatus(422);
        $this->assertDatabaseMissing('inscriptions_lancement', ['email' => 'pas-une-adresse']);
    }

    public function test_une_adresse_deja_inscrite_reste_un_succes_sans_doublon(): void
    {
        InscriptionLancement::create(['email' => 'deja@example.com']);

        $reponse = $this->postJson('lancement/inscription', ['email' => 'deja@example.com']);

        $reponse->assertStatus(200);
        $reponse->assertJson(['success' => true]);
        $this->assertSame(1, InscriptionLancement::where('email', 'deja@example.com')->count());
    }
}
