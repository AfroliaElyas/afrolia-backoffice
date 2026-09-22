<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CLE = 'rayon_recherche_defaut_km';

    public function up(): void
    {
        DB::table('parametres')->updateOrInsert(
            ['cle' => self::CLE],
            ['valeur' => '8', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('parametres')->where('cle', self::CLE)->delete();
    }
};
