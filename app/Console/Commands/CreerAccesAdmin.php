<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

/**
 * Crée ou réinitialise un accès au back-office (table users, distincte de
 * users_app qui est celle de l'app mobile) directement depuis Forge, sans
 * jamais passer par Tinker en production ni saisir de mot de passe en
 * clair dans une commande — un mot de passe aléatoire est généré et
 * affiché une seule fois dans la sortie de la commande (signalé le
 * 18/10/2026 : aucun compte admin connu pour se connecter à /admin).
 */
class CreerAccesAdmin extends Command
{
    protected $signature = 'admin:creer-acces {email} {--nom=Admin}';

    protected $description = "Crée un compte back-office pour cet email, ou réinitialise son mot de passe s'il existe déjà, avec un mot de passe aléatoire affiché une seule fois.";

    public function handle(): int
    {
        $email = $this->argument('email');

        $validateur = Validator::make(['email' => $email], [
            'email' => 'required|email',
        ]);

        if ($validateur->fails()) {
            $this->error("Adresse e-mail invalide : {$email}");

            return self::FAILURE;
        }

        $motDePasse = Str::random(16);

        $utilisateur = User::where('email', $email)->first();

        if ($utilisateur) {
            $utilisateur->update(['password' => Hash::make($motDePasse)]);
            $this->info("Mot de passe réinitialisé pour le compte existant : {$email}");
        } else {
            User::create([
                'name' => $this->option('nom'),
                'email' => $email,
                'password' => Hash::make($motDePasse),
            ]);
            $this->info("Compte back-office créé : {$email}");
        }

        $this->line('');
        $this->line("Mot de passe (à noter maintenant, il ne sera plus jamais affiché) : {$motDePasse}");
        $this->line('Connectez-vous sur /admin avec cet e-mail et ce mot de passe, puis changez-le si l\'application le permet.');

        return self::SUCCESS;
    }
}
