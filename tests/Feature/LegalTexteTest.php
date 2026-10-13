<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Politicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalTexteTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_politique_de_confidentialite_est_refusee_si_rien_n_est_configure(): void
    {
        $this->getJson('/api/politique')->assertStatus(401);
    }

    public function test_les_conditions_sont_refusees_si_rien_n_est_configure(): void
    {
        $this->getJson('/api/conditions')->assertStatus(401);
    }

    public function test_la_politique_de_confidentialite_convertit_le_html_en_texte_lisible(): void
    {
        Politicy::create([
            'politicy' => '<h2>Section 1</h2><p>Premier paragraphe.</p><ul><li>Point A</li><li>Point B</li></ul>',
        ]);

        $reponse = $this->getJson('/api/politique');

        $reponse->assertStatus(200);
        $texte = $reponse->json('data');

        $this->assertStringNotContainsString('<', $texte);
        $this->assertStringContainsString('Section 1', $texte);
        $this->assertStringContainsString('Premier paragraphe.', $texte);
        $this->assertStringContainsString('- Point A', $texte);
        $this->assertStringContainsString('- Point B', $texte);
    }

    public function test_les_conditions_renvoient_la_derniere_version_enregistree(): void
    {
        Condition::create(['condition' => '<p>Ancienne version</p>'])
            ->forceFill(['created_at' => now()->subMinute()])
            ->save();
        Condition::create(['condition' => '<p>Nouvelle version</p>']);

        $reponse = $this->getJson('/api/conditions');

        $reponse->assertStatus(200);
        $this->assertStringContainsString('Nouvelle version', $reponse->json('data'));
        $this->assertStringNotContainsString('Ancienne version', $reponse->json('data'));
    }
}
