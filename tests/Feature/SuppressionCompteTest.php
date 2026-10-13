<?php

namespace Tests\Feature;

use App\Models\Gains;
use App\Models\Reservations;
use App\Models\UsersApp;
use App\Services\SuppressionCompteBloqueeException;
use App\Services\SuppressionCompteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Avant cette correction, supprimerCompte() appelait UsersApp::delete(),
 * dont les clés étrangères en cascade effaçaient immédiatement tout
 * l'historique financier (réservations, paiements, gains, commandes,
 * versements, avis) de la personne — alors que les livres comptables et
 * pièces justificatives doivent être conservés dix ans (article 24 de
 * l'AUDCIF).
 */
class SuppressionCompteTest extends TestCase
{
    use RefreshDatabase;

    private function creerUtilisateur(array $overrides = []): UsersApp
    {
        return UsersApp::create(array_merge([
            'name' => 'Aïcha', 'last_name' => 'Koné', 'phone' => '07' . random_int(10000000, 99999999),
            'password' => Hash::make('motdepasse'), 'role' => 'user',
        ], $overrides));
    }

    private function creerReservation(UsersApp $client, UsersApp $coiffeuse, string $statut, string $statutRemboursement = 'aucun'): Reservations
    {
        $specialite = DB::table('specialites')->insertGetId(['libelle' => 'Tresses', 'created_at' => now(), 'updated_at' => now()]);
        $service = DB::table('services')->insertGetId([
            'prix' => 10000, 'minute' => 60, 'id_utilisateur' => $coiffeuse->id_user_app, 'id_speciale' => $specialite,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Reservations::create([
            'numero_reservation' => 'RES-' . uniqid(),
            'id_client' => $client->id_user_app,
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_service' => $service,
            'date_reservation' => now()->toDateString(),
            'heure_reservation' => '10:00',
            'statut' => $statut,
            'statut_paiement' => 'paye',
            'statut_remboursement' => $statutRemboursement,
            'prix_service' => 10000,
            'montant_commission' => 800,
            'montant_total' => 10000,
            'methode_paiement' => 'mobile_money',
        ]);
    }

    public function test_un_compte_sans_historique_est_supprime_normalement(): void
    {
        $client = $this->creerUtilisateur();

        (new SuppressionCompteService())->supprimer($client);

        $client->refresh();
        $this->assertSame('Compte supprimé', $client->name);
        $this->assertSame('Supprime', $client->statut);
        $this->assertNull($client->email);
        $this->assertDatabaseCount('users_app', 1);
    }

    public function test_la_suppression_est_refusee_si_une_reservation_est_en_cours(): void
    {
        $client = $this->creerUtilisateur();
        $coiffeuse = $this->creerUtilisateur(['role' => 'hair']);
        $this->creerReservation($client, $coiffeuse, 'confirmee');

        $this->expectException(SuppressionCompteBloqueeException::class);

        (new SuppressionCompteService())->supprimer($client);
    }

    public function test_la_suppression_est_refusee_si_un_remboursement_est_en_attente(): void
    {
        $client = $this->creerUtilisateur();
        $coiffeuse = $this->creerUtilisateur(['role' => 'hair']);
        $this->creerReservation($client, $coiffeuse, 'annulee', 'en_attente');

        $this->expectException(SuppressionCompteBloqueeException::class);

        (new SuppressionCompteService())->supprimer($client);
    }

    public function test_la_suppression_est_refusee_si_des_gains_ne_sont_pas_encore_verses(): void
    {
        $coiffeuse = $this->creerUtilisateur(['role' => 'hair']);
        $client = $this->creerUtilisateur();
        $reservation = $this->creerReservation($client, $coiffeuse, 'terminee');
        Gains::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_reservation' => $reservation->id_reservation,
            'montant_brut' => 10000, 'montant_commission' => 800, 'montant_net' => 9200,
            'statut' => 'disponible',
        ]);

        $this->expectException(SuppressionCompteBloqueeException::class);

        (new SuppressionCompteService())->supprimer($coiffeuse);
    }

    public function test_un_compte_avec_des_gains_deja_payes_peut_etre_supprime(): void
    {
        $coiffeuse = $this->creerUtilisateur(['role' => 'hair']);
        $client = $this->creerUtilisateur();
        $reservation = $this->creerReservation($client, $coiffeuse, 'terminee');
        Gains::create([
            'id_coiffeur' => $coiffeuse->id_user_app,
            'id_reservation' => $reservation->id_reservation,
            'montant_brut' => 10000, 'montant_commission' => 800, 'montant_net' => 9200,
            'statut' => 'paye',
        ]);

        (new SuppressionCompteService())->supprimer($coiffeuse);

        $this->assertSame('Supprime', $coiffeuse->fresh()->statut);
    }

    public function test_la_reservation_terminee_garde_l_identite_au_moment_de_la_suppression(): void
    {
        $client = $this->creerUtilisateur(['name' => 'Aïcha', 'last_name' => 'Koné']);
        $coiffeuse = $this->creerUtilisateur(['role' => 'hair', 'name' => 'Fatou', 'last_name' => 'Diabaté']);
        $reservation = $this->creerReservation($client, $coiffeuse, 'terminee');

        (new SuppressionCompteService())->supprimer($client);

        $reservation->refresh();
        $this->assertSame('Aïcha Koné', $reservation->client_nom_facturation);
        $this->assertNull($reservation->coiffeur_nom_facturation);

        $client->refresh();
        $this->assertSame('Compte supprimé', $client->name);
    }

    public function test_le_numero_de_telephone_original_redevient_disponible_pour_une_nouvelle_inscription(): void
    {
        $client = $this->creerUtilisateur(['phone' => '0712345678']);

        (new SuppressionCompteService())->supprimer($client);

        $this->assertDatabaseMissing('users_app', ['phone' => '0712345678']);
        $nouveauCompte = $this->creerUtilisateur(['phone' => '0712345678']);
        $this->assertNotNull($nouveauCompte);
    }

    public function test_un_compte_supprime_ne_peut_plus_se_connecter(): void
    {
        $client = $this->creerUtilisateur(['phone' => '0712345679']);
        $telephoneOriginal = $client->phone;

        (new SuppressionCompteService())->supprimer($client);

        $response = $this->postJson('/api/login', ['login' => $telephoneOriginal, 'password' => 'motdepasse']);
        $response->assertStatus(401);
    }

    public function test_les_jetons_actifs_sont_revoques_a_la_suppression(): void
    {
        $client = $this->creerUtilisateur();
        $client->createToken('mobile');
        $this->assertSame(1, $client->tokens()->count());

        (new SuppressionCompteService())->supprimer($client);

        $this->assertSame(0, $client->tokens()->count());
    }

    public function test_api_supprimer_compte_renvoie_une_erreur_claire_si_bloque(): void
    {
        $client = $this->creerUtilisateur();
        $coiffeuse = $this->creerUtilisateur(['role' => 'hair']);
        $this->creerReservation($client, $coiffeuse, 'en_attente');

        Sanctum::actingAs($client);
        $response = $this->deleteJson("/api/delete/{$client->id_user_app}");

        $response->assertStatus(409);
        $this->assertSame('Active', $client->fresh()->statut);
    }

    public function test_api_supprimer_compte_fonctionne_normalement_sans_blocage(): void
    {
        $client = $this->creerUtilisateur();

        Sanctum::actingAs($client);
        $response = $this->deleteJson("/api/delete/{$client->id_user_app}");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertSame('Supprime', $client->fresh()->statut);
    }
}
