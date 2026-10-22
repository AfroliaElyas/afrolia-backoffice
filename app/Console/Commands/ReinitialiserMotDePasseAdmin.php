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
 * production. Le nouveau mot de passe est généré aléatoirement et affiché
 * une seule fois dans la sortie de la commande : à changer après connexion.
 */
class ReinitialiserMotDePasseAdmin extends Command
{
    protected $signature = 'admin:reinitialiser-mot-de-passe {email}';

    protected $description = "Génère un nouveau mot de passe aléatoire pour un compte admin existant";

    public function handle(): int
    {
        $email = $this->argument('email');
        $utilisateur = User::where('email', $email)->first();

        if (!$utilisateur) {
            $this->error("Aucun compte admin trouvé avec l'adresse {$email}.");
            $this->line("Lance 'php artisan admin:lister-comptes' pour voir les adresses existantes.");
            return self::FAILURE;
        }

        $nouveauMotDePasse = Str::password(16);
        $utilisateur->update(['password' => Hash::make($nouveauMotDePasse)]);

        $this->info("Mot de passe réinitialisé pour {$utilisateur->email}.");
        $this->warn("Nouveau mot de passe (à noter maintenant, il ne sera plus jamais affiché) :");
        $this->line($nouveauMotDePasse);

        return self::SUCCESS;
    }
}
