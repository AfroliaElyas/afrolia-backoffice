<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Le compte admin (back-office) n'a pas de "mot de passe oublié" en
 * self-service (contrairement aux comptes client/coiffeuse de l'app) ; cette
 * commande donne un moyen sûr de reprendre la main sans passer par Tinker en
 * production. Un mot de passe choisi peut être fourni directement (demandé
 * le 23/10/2026 : recopier un mot de passe généré à la main est source
 * d'erreurs de frappe) ; sans argument, un mot de passe aléatoire est
 * généré et affiché une seule fois dans la sortie de la commande.
 */
class ReinitialiserMotDePasseAdmin extends Command
{
    protected $signature = 'admin:reinitialiser-mot-de-passe {email} {motdepasse?}';

    protected $description = "Définit (ou génère) un nouveau mot de passe pour un compte admin existant";

    public function handle(): int
    {
        $email = $this->argument('email');
        $utilisateur = User::where('email', $email)->first();

        if (!$utilisateur) {
            $this->error("Aucun compte admin trouvé avec l'adresse {$email}.");
            $this->line("Lance 'php artisan admin:lister-comptes' pour voir les adresses existantes.");
            return self::FAILURE;
        }

        $motDePasseChoisi = $this->argument('motdepasse');

        if ($motDePasseChoisi !== null && mb_strlen($motDePasseChoisi) < 8) {
            $this->error('Le mot de passe doit contenir au moins 8 caractères.');
            return self::FAILURE;
        }

        $nouveauMotDePasse = $motDePasseChoisi ?? Str::password(16);
        $utilisateur->update(['password' => Hash::make($nouveauMotDePasse)]);

        if ($motDePasseChoisi !== null) {
            $this->info("Mot de passe mis à jour pour {$utilisateur->email}.");
        } else {
            $this->info("Mot de passe réinitialisé pour {$utilisateur->email}.");
            $this->warn("Nouveau mot de passe (à noter maintenant, il ne sera plus jamais affiché) :");
            $this->line($nouveauMotDePasse);
        }

        return self::SUCCESS;
    }
}
