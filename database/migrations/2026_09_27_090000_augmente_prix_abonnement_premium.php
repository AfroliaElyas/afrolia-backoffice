<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ANCIEN_PRIX = '5500';
    private const NOUVEAU_PRIX = '8000';

    public function up(): void
    {
        DB::table('parametres')->updateOrInsert(
            ['cle' => 'prix_abonnement_premium'],
            ['valeur' => self::NOUVEAU_PRIX, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('parametres')->where('cle', 'prix_abonnement_premium')
            ->update(['valeur' => self::ANCIEN_PRIX, 'updated_at' => now()]);
    }
};
