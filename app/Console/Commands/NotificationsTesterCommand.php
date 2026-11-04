<?php

namespace App\Console\Commands;

use App\Models\UsersApp;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Envoie une vraie notification push de test à un utilisateur précis, pour
 * vérifier FIREBASE_PROJECT_ID / FIREBASE_CREDENTIALS_JSON après un dépôt
 * manuel dans l'Environnement Forge (pas d'accès SSH/serveur disponible pour
 * vérifier autrement — demandé le 04/11/2026). Même esprit que
 * stripe:tester-webhook : un vrai appel réseau à travers tout le circuit
 * réel, lancé depuis l'onglet "Commands" de Forge, sans jamais afficher de
 * secret.
 */
class NotificationsTesterCommand extends Command
{
    protected $signature = 'notifications:tester {identifiant : Email ou numéro de téléphone du compte à notifier}';

    protected $description = "Envoie une vraie notification push de test à un utilisateur, pour vérifier la configuration Firebase sans l'afficher";

    public function handle(NotificationService $notifications): int
    {
        $identifiant = $this->argument('identifiant');

        $utilisateur = UsersApp::where('email', $identifiant)
            ->orWhere('phone', $identifiant)
            ->first();

        if (!$utilisateur) {
            $this->error("Aucun compte trouvé avec l'email ou le téléphone « {$identifiant} ».");

            return self::FAILURE;
        }

        $this->info("Compte trouvé : {$utilisateur->name} {$utilisateur->last_name} (id {$utilisateur->id_user_app}).");

        $resultat = $notifications->diagnostiquerPourUtilisateur($utilisateur);

        if (!$resultat['configure']) {
            $this->error('❌ FIREBASE_PROJECT_ID ou FIREBASE_CREDENTIALS_JSON est vide dans la configuration du serveur : rien à tester.');

            return self::FAILURE;
        }

        if ($resultat['nombre_appareils'] === 0) {
            $this->warn(
                "⚠️  FIREBASE_PROJECT_ID / FIREBASE_CREDENTIALS_JSON sont bien renseignés, mais ce compte n'a "
                . "aucun appareil enregistré. Connecte-toi avec l'app sur ce compte (ça enregistre l'appareil "
                . 'automatiquement), puis relance cette commande.'
            );

            return self::FAILURE;
        }

        $this->info("{$resultat['nombre_appareils']} appareil(s) enregistré(s) pour ce compte.");

        if ($resultat['jeton_obtenu'] === false) {
            $this->error(
                "❌ Impossible d'obtenir un jeton d'accès Google avec FIREBASE_CREDENTIALS_JSON : la clé est "
                . 'probablement mal copiée (vérifie les guillemets autour de la valeur dans Forge) ou invalide. '
                . 'Voir storage/logs/laravel.log pour le détail exact (jamais la clé elle-même).'
            );

            return self::FAILURE;
        }

        $echec = false;
        foreach ($resultat['resultats'] as $envoi) {
            if ($envoi['envoye']) {
                $this->info("✅ Envoyée à un appareil {$envoi['plateforme']}.");
            } else {
                $echec = true;
                $this->error("❌ Échec sur un appareil {$envoi['plateforme']} : {$envoi['erreur']}");
            }
        }

        if ($echec) {
            return self::FAILURE;
        }

        $this->info('Si le téléphone de ce compte a l\'app ouverte (ou en arrière-plan) et les notifications autorisées, la notification "Test Afrolia" doit apparaître maintenant.');

        return self::SUCCESS;
    }
}
