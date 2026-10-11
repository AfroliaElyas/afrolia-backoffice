<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nouveau modèle de tarification : la cliente paie le prix affiché, sans
 * supplément ; la retenue (commission Afrolia + frais de paiement, non
 * distingués l'un de l'autre dans l'affichage) est déduite des gains de la
 * coiffeuse. 8 % en formule Gratuite, 6 % en Premium.
 */
return new class extends Migration
{
    public function up(): void
    {
        // La boutique (produits) n'est pas concernée par ce changement :
        // on fige son taux actuel sous de nouvelles clés dédiées avant de
        // réutiliser commission_gratuit/commission_premium pour les
        // prestations.
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_boutique_gratuit'],
            ['valeur' => '5', 'updated_at' => now()]
        );
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_boutique_premium'],
            ['valeur' => '3', 'updated_at' => now()]
        );

        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_gratuit'],
            ['valeur' => '8', 'updated_at' => now()]
        );
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_premium'],
            ['valeur' => '6', 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_gratuit'],
            ['valeur' => '5', 'updated_at' => now()]
        );
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_premium'],
            ['valeur' => '3', 'updated_at' => now()]
        );

        DB::table('parametres')->whereIn('cle', ['commission_boutique_gratuit', 'commission_boutique_premium'])->delete();
    }
};
