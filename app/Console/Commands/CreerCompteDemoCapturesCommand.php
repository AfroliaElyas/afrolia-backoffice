<?php

namespace App\Console\Commands;

use App\Models\ClientFavorite;
use App\Models\Commandes;
use App\Models\CommandeLignes;
use App\Models\Gains;
use App\Models\Produits;
use App\Models\Reservations;
use App\Models\Reviews;
use App\Models\Services;
use App\Models\Specialites;
use App\Models\UsersApp;
use App\Models\UsersAppSpecialites;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crée (ou retrouve si déjà exécutée) un compte client + un compte
 * coiffeuse de démonstration, avec des services, une réservation terminée,
 * un avis et des produits boutique, pour que les captures d'écran
 * automatisées (Google Play / App Store) montrent de vrais écrans peuplés
 * au lieu d'un compte vide. Exécutable depuis l'onglet "Commands" de
 * Forge, sans terminal (demandé le 04/11/2026).
 *
 * Identifiants volontairement en clair dans ce fichier : ce sont des
 * comptes bac à sable publics, sans donnée personnelle réelle ni argent
 * réel, créés uniquement pour alimenter des captures d'écran de store. Ils
 * n'ont aucune valeur de sécurité à protéger, contrairement aux comptes
 * réels (voir le reste du code, où un mot de passe n'est jamais journalisé
 * ni codé en dur).
 */
class CreerCompteDemoCapturesCommand extends Command
{
    protected $signature = 'demo:creer-compte-captures';

    protected $description = "Crée ou retrouve un compte client et un compte coiffeuse de démonstration (avec services, réservation, avis, produits) pour générer des captures d'écran store automatisées";

    public const TELEPHONE_COIFFEUSE = '+225 0100000001';

    public const TELEPHONE_CLIENT = '+225 0100000002';

    public const MOT_DE_PASSE = 'DemoAfrolia2026Captures!';

    public function handle(): int
    {
        $specialite = Specialites::firstOrCreate(['libelle' => 'Coiffure afro']);

        $coiffeuse = $this->creerOuRetrouverUtilisateur(
            telephone: self::TELEPHONE_COIFFEUSE,
            email: 'demo.coiffeuse.captures@afrolia-app.com',
            nom: 'Awa',
            prenom: 'Koné',
            role: 'hair',
            extra: [
                'presentation' => "Spécialiste en tresses et soins capillaires naturels, à votre écoute depuis plus de 8 ans. Compte de démonstration pour les captures d'écran.",
                'commune' => 'Cocody, Abidjan',
                'experience' => 8,
                'formule_abonnement' => 'premium',
                'prochain_prelevement_le' => now()->addMonth(),
                'renouvellement_automatique' => false,
            ],
        );

        if (!UsersAppSpecialites::where('id_utilisateur', $coiffeuse->id_user_app)->where('id_speciale', $specialite->id_specialite)->exists()) {
            UsersAppSpecialites::create([
                'id_utilisateur' => $coiffeuse->id_user_app,
                'id_speciale' => $specialite->id_specialite,
            ]);
        }

        $client = $this->creerOuRetrouverUtilisateur(
            telephone: self::TELEPHONE_CLIENT,
            email: 'demo.client.captures@afrolia-app.com',
            nom: 'Fatou',
            prenom: 'Diabaté',
            role: 'user',
            extra: [
                'commune' => 'Marcory, Abidjan',
            ],
        );

        // Sans ça, l'écran "favoris" du client reste vide sur la capture —
        // précisément ce que ce compte peuplé est censé éviter.
        ClientFavorite::firstOrCreate([
            'client_id' => $client->id_user_app,
            'stylist_id' => $coiffeuse->id_user_app,
        ]);

        $services = [
            ['nom' => 'Tresses box braids', 'prix' => 15000, 'minute' => 180, 'description' => 'Box braids moyennes, avec mèches de qualité comprises.', 'longueur' => 'Épaules', 'meches_comprises' => true],
            ['nom' => 'Soin profond hydratant', 'prix' => 8000, 'minute' => 60, 'description' => 'Soin en profondeur pour cheveux secs ou abîmés, à base de produits naturels.', 'longueur' => null, 'meches_comprises' => false],
            ['nom' => 'Coiffure afro événementielle', 'prix' => 12000, 'minute' => 90, 'description' => 'Mise en forme élégante pour mariages, cérémonies et occasions spéciales.', 'longueur' => null, 'meches_comprises' => false],
        ];

        $servicesCrees = [];
        foreach ($services as $donnees) {
            $servicesCrees[] = Services::firstOrCreate(
                ['id_utilisateur' => $coiffeuse->id_user_app, 'nom' => $donnees['nom']],
                $donnees + ['id_speciale' => $specialite->id_specialite],
            );
        }

        $reservation = Reservations::where('numero_reservation', 'like', 'DEMO-CAPTURES-%')->first();
        if (!$reservation) {
            $service = $servicesCrees[0];
            $reservation = Reservations::create([
                'numero_reservation' => 'DEMO-CAPTURES-' . strtoupper(Str::random(6)),
                'id_client' => $client->id_user_app,
                'id_coiffeur' => $coiffeuse->id_user_app,
                'client_nom_facturation' => $client->name . ' ' . $client->last_name,
                'coiffeur_nom_facturation' => $coiffeuse->name . ' ' . $coiffeuse->last_name,
                'id_service' => $service->id_service,
                'date_reservation' => now()->subDays(5)->toDateString(),
                'heure_reservation' => '14:00',
                'duree_minutes' => $service->minute,
                'statut' => 'terminee',
                'prix_service' => $service->prix,
                'montant_commission' => round($service->prix * 0.15, 2),
                'montant_total' => $service->prix,
                'statut_paiement' => 'paye',
                'methode_paiement' => 'mobile_money',
                'confirme_le' => now()->subDays(5),
                'termine_le' => now()->subDays(5)->addHours(3),
            ]);
        }

        // Sans ce gain, l'écran "revenus" de la coiffeuse affiche 0 FCFA
        // partout malgré une réservation payée : ApiGainsCoiffeuseController
        // exclut explicitement le statut "en_attente" (celui que mettrait un
        // vrai paiement) de tous les totaux affichés. "disponible" est le
        // statut d'un gain réellement acquis et non encore versé — c'est
        // aussi ce que lit le solde de l'écran "aperçu"
        // (ApiDashboardCoiffeuseController::where('statut', 'disponible')).
        if (!Gains::where('id_reservation', $reservation->id_reservation)->exists()) {
            Gains::create([
                'id_coiffeur' => $coiffeuse->id_user_app,
                'id_reservation' => $reservation->id_reservation,
                'montant_brut' => $reservation->montant_total,
                'montant_commission' => $reservation->montant_commission,
                'montant_net' => $reservation->prix_service - $reservation->montant_commission,
                'statut' => 'disponible',
            ]);
        }

        $avisExiste = Reviews::where('id_reservation', $reservation->id_reservation)->exists();
        if (!$avisExiste) {
            Reviews::create([
                'id_reservation' => $reservation->id_reservation,
                'id_client' => $client->id_user_app,
                'id_stylist' => $coiffeuse->id_user_app,
                'rating' => 5,
                'comment' => 'Un vrai travail de professionnelle, je recommande vivement !',
                'is_anonymous' => false,
                'is_verified' => true,
                'status' => 'approved',
            ]);
        }

        $produits = [
            ['nom' => 'Huile de ricin capillaire', 'description' => 'Fortifie et nourrit les cheveux en profondeur, 100ml.', 'prix' => 4500, 'quantite_stock' => 25],
            ['nom' => 'Crème coiffante boucles définies', 'description' => 'Définit et hydrate les boucles sans effet gras.', 'prix' => 6000, 'quantite_stock' => 18],
            ['nom' => 'Mèches synthétiques premium', 'description' => "Pack de mèches pour box braids, longue tenue.", 'prix' => 9000, 'quantite_stock' => 12],
        ];

        $produitsCrees = [];
        foreach ($produits as $donnees) {
            $produitsCrees[] = Produits::firstOrCreate(
                ['id_coiffeur' => $coiffeuse->id_user_app, 'nom' => $donnees['nom']],
                $donnees + ['statut' => 'actif'],
            );
        }

        $commande = Commandes::where('numero_commande', 'like', 'DEMO-CAPTURES-%')->first();
        if (!$commande) {
            $produit = $produitsCrees[0];
            $commande = Commandes::create([
                'numero_commande' => 'DEMO-CAPTURES-' . strtoupper(Str::random(6)),
                'id_client' => $client->id_user_app,
                'id_coiffeur' => $coiffeuse->id_user_app,
                'montant_produits' => $produit->prix,
                'montant_commission' => round($produit->prix * 0.15, 2),
                'montant_total' => $produit->prix,
                'methode_paiement' => 'mobile_money',
                'statut_paiement' => 'paye',
                'statut_commande' => 'livree',
                'livree_le' => now()->subDays(3),
            ]);

            CommandeLignes::create([
                'id_commande' => $commande->id_commande,
                'id_produit' => $produit->id_produit,
                'nom_produit' => $produit->nom,
                'prix_unitaire' => $produit->prix,
                'quantite' => 1,
                'sous_total' => $produit->prix,
            ]);
        }

        // Même raison que pour le gain de la réservation ci-dessus : sans
        // lui, la commande livrée n'apparaît dans aucun total de l'écran
        // "revenus".
        if (!Gains::where('id_commande', $commande->id_commande)->exists()) {
            Gains::create([
                'id_coiffeur' => $coiffeuse->id_user_app,
                'id_commande' => $commande->id_commande,
                'montant_brut' => $commande->montant_total,
                'montant_commission' => $commande->montant_commission,
                'montant_net' => $commande->montant_produits - $commande->montant_commission,
                'statut' => 'disponible',
            ]);
        }

        $this->info('✅ Compte de démonstration prêt pour les captures d\'écran.');
        $this->line('');
        $this->line("Coiffeuse : {$coiffeuse->phone} / mot de passe : " . self::MOT_DE_PASSE . " (id {$coiffeuse->id_user_app})");
        $this->line("Client    : {$client->phone} / mot de passe : " . self::MOT_DE_PASSE . " (id {$client->id_user_app})");
        $this->line('Services en place : ' . count($servicesCrees));
        $this->line('Produits boutique en place : ' . count($produitsCrees));
        $this->line('Réservation terminée : ' . $reservation->numero_reservation);
        $this->line('Commande livrée : ' . $commande->numero_commande);
        $this->line('Avis client : ' . ($avisExiste ? 'déjà présent' : 'créé'));
        $this->line('Favori client : coiffeuse ajoutée');
        $this->line('Gains disponibles : ' . Gains::where('id_coiffeur', $coiffeuse->id_user_app)->where('statut', 'disponible')->count());

        return self::SUCCESS;
    }

    private function creerOuRetrouverUtilisateur(string $telephone, string $email, string $nom, string $prenom, string $role, array $extra = []): UsersApp
    {
        $utilisateur = UsersApp::where('phone', $telephone)->first();

        if ($utilisateur) {
            return $utilisateur;
        }

        return UsersApp::create(array_merge([
            'phone' => $telephone,
            'email' => $email,
            'name' => $nom,
            'last_name' => $prenom,
            'password' => Hash::make(self::MOT_DE_PASSE),
            'role' => $role,
            'statut' => 'Active',
            'email_verified_at' => now(),
        ], $extra));
    }
}
