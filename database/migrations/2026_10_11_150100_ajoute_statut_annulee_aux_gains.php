<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un gain lié à une réservation annulée par la cliente n'est ni "en
 * attente", ni "disponible", ni "payé" : il ne sera jamais versé. Ce
 * nouveau statut le distingue clairement dans les rapports, plutôt que de
 * le confondre avec un gain normal à montant_net = 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (tests) stocke déjà "statut" en varchar simple, sans
        // contrainte d'énumération : seul MySQL (production) a besoin de
        // cette modification.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE gains MODIFY statut ENUM('en_attente', 'disponible', 'paye', 'annulee') DEFAULT 'en_attente'");
        }
    }

    public function down(): void
    {
        DB::table('gains')->where('statut', 'annulee')->update(['statut' => 'en_attente']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE gains MODIFY statut ENUM('en_attente', 'disponible', 'paye') DEFAULT 'en_attente'");
        }
    }
};
