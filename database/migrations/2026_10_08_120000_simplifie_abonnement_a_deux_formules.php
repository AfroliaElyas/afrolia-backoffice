<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Passage de 3 formules (gratuit/standard/premium) à 2 (gratuit/premium) :
 * la formule "Standard" à 4 500 FCFA / 3 % devient la nouvelle "Premium",
 * l'ancienne Premium à 8 000 FCFA / 0 % disparaît. Décision prise suite à
 * l'arrivée d'un concurrent gratuit sur le marché ivoirien.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'prix_abonnement_premium'],
            ['valeur' => '4500', 'updated_at' => now()]
        );
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_premium'],
            ['valeur' => '3', 'updated_at' => now()]
        );
        DB::table('parametres')->whereIn('cle', ['prix_abonnement_standard', 'commission_standard'])->delete();

        // Les coiffeuses déjà sur l'ancienne formule Standard basculent sur
        // la nouvelle Premium (même prix, même commission). Celles qui
        // étaient sur l'ancienne Premium (8000/0%) y restent aussi, au
        // nouveau tarif (4500/3%) : pas d'utilisatrice réelle à ce jour,
        // donc aucune conséquence pratique.
        DB::table('users_app')->where('formule_abonnement', 'standard')->update(['formule_abonnement' => 'premium']);
        DB::table('abonnement_paiements')->where('formule', 'standard')->update(['formule' => 'premium']);
    }

    public function down(): void
    {
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'prix_abonnement_premium'],
            ['valeur' => '8000', 'updated_at' => now()]
        );
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_premium'],
            ['valeur' => '0', 'updated_at' => now()]
        );
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'prix_abonnement_standard'],
            ['valeur' => '4500', 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_standard'],
            ['valeur' => '3', 'created_at' => now(), 'updated_at' => now()]
        );

        // Impossible de distinguer, parmi les coiffeuses en "premium", qui
        // était auparavant "standard" : ce sens de la migration ne restaure
        // pas cette répartition (sans conséquence tant qu'aucune utilisatrice
        // réelle n'existe).
    }
};
