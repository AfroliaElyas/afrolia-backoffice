<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La boutique utilisait des paramètres de commission séparés
 * (commission_boutique_gratuit/premium, 5 %/3 %), jamais réellement
 * validés comme décision délibérée — simplement l'ancien taux resté en
 * place quand les prestations sont passées à 8 %/6 % (migration
 * 2026_10_11_150000_retenue_totale_8_et_6_pourcent). Confirmé le
 * 18/10/2026 : les deux doivent utiliser le même taux (8 % Gratuite /
 * 6 % Premium). AbonnementService::tauxCommissionBoutique() délègue
 * maintenant directement à tauxCommission() ; ces paramètres ne sont plus
 * lus nulle part et sont supprimés pour ne pas laisser une valeur
 * obsolète (5 %/3 %) traîner en base, qui pourrait induire en erreur une
 * relecture future.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('parametres')->whereIn('cle', ['commission_boutique_gratuit', 'commission_boutique_premium'])->delete();
    }

    public function down(): void
    {
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_boutique_gratuit'],
            ['valeur' => '5', 'updated_at' => now()]
        );
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_boutique_premium'],
            ['valeur' => '3', 'updated_at' => now()]
        );
    }
};
