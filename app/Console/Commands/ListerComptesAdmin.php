<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Le compte admin (back-office, table "users") est entièrement séparé des
 * comptes client/coiffeuse de l'application mobile (table "users_app") : il
 * n'existe aucune page "mot de passe oublié" pour ce compte-là. Cette
 * commande affiche les adresses existantes pour retrouver la bonne, sans
 * passer par Tinker en production.
 */
class ListerComptesAdmin extends Command
{
    protected $signature = 'admin:lister-comptes';

    protected $description = "Liste les comptes admin du back-office (table users), pour retrouver la bonne adresse email";

    public function handle(): int
    {
        $comptes = User::orderBy('id')->get(['id', 'name', 'email']);

        if ($comptes->isEmpty()) {
            $this->warn('Aucun compte admin trouvé dans la table users.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nom', 'Email'],
            $comptes->map(fn (User $u) => [$u->id, $u->name, $u->email])->all(),
        );

        return self::SUCCESS;
    }
}
