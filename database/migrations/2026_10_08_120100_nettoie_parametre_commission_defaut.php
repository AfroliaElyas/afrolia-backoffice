<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "commission_defaut" (15%) n'était lu par aucune logique de calcul réelle :
 * seulement affiché/modifiable dans le back-office admin, sans effet sur les
 * montants facturés (qui passent uniquement par AbonnementService).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('parametres')->where('cle', 'commission_defaut')->delete();
    }

    public function down(): void
    {
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'commission_defaut'],
            ['valeur' => '15', 'description' => 'Commission par défaut en % appliquée aux nouveaux services', 'created_at' => now(), 'updated_at' => now()]
        );
    }
};
