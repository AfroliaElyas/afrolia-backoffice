<?php

namespace Tests\Feature;

use App\Models\UsersApp;
use App\Services\Ia\ClaudeClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(): UsersApp
    {
        return UsersApp::create([
            'name' => 'Client',
            'last_name' => 'Test',
            'phone' => '0700000003',
            'password' => 'x',
            'role' => 'user',
        ]);
    }

    private function fausseReponseClaude(string $reponse): void
    {
        $faux = new class($reponse) implements ClaudeClientInterface {
            public function __construct(private readonly string $reponse)
            {
            }

            public function repondre(string $promptSysteme, string $question): string
            {
                return $this->reponse;
            }
        };

        $this->app->instance(ClaudeClientInterface::class, $faux);
    }

    public function test_une_route_non_authentifiee_est_refusee(): void
    {
        $this->postJson('/api/assistant', ['question' => 'Comment réserver ?'])
            ->assertStatus(401);
    }

    public function test_une_question_vide_est_rejetee(): void
    {
        $utilisateur = $this->creerUtilisateur();
        Sanctum::actingAs($utilisateur);

        $this->postJson('/api/assistant', ['question' => ''])
            ->assertStatus(422);
    }

    public function test_lassistant_renvoie_la_reponse_du_modele(): void
    {
        $this->fausseReponseClaude('Pour réserver, ouvrez le profil d\'une coiffeuse et choisissez un créneau.');

        $utilisateur = $this->creerUtilisateur();
        Sanctum::actingAs($utilisateur);

        $this->postJson('/api/assistant', ['question' => 'Comment réserver un rendez-vous ?'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reponse', 'Pour réserver, ouvrez le profil d\'une coiffeuse et choisissez un créneau.');
    }

    public function test_une_panne_du_modele_renvoie_une_erreur_propre_sans_exposer_le_detail(): void
    {
        $enPanne = new class implements ClaudeClientInterface {
            public function repondre(string $promptSysteme, string $question): string
            {
                throw new \RuntimeException('clé API invalide');
            }
        };
        $this->app->instance(ClaudeClientInterface::class, $enPanne);

        $utilisateur = $this->creerUtilisateur();
        Sanctum::actingAs($utilisateur);

        $response = $this->postJson('/api/assistant', ['question' => 'Comment réserver ?']);

        $response->assertStatus(503)
            ->assertJsonPath('success', false);
        $this->assertStringNotContainsString('clé API invalide', $response->getContent());
    }
}
