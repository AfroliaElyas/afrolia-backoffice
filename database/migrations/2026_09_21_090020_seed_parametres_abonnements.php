<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PARAMETRES_PAR_DEFAUT = [
        'prix_abonnement_standard' => '4500',
        'prix_abonnement_premium' => '5500',
        'commission_gratuit' => '5',
        'commission_standard' => '3',
        'commission_premium' => '0',
    ];

    public function up(): void
    {
        foreach (self::PARAMETRES_PAR_DEFAUT as $cle => $valeur) {
            DB::table('parametres')->updateOrInsert(
                ['cle' => $cle],
                ['valeur' => $valeur, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('parametres')->whereIn('cle', array_keys(self::PARAMETRES_PAR_DEFAUT))->delete();
    }
};
