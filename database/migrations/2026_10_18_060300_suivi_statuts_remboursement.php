<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * statut_remboursement existait déjà (migration de création de
 * reservations) mais n'était jamais lu ni écrit par aucun contrôleur :
 * une réservation payée puis annulée n'apparaissait dans "remboursements
 * à traiter" que via un filtre implicite (statut=annulee ET
 * statut_paiement=paye), sans distinguer à traiter / en cours / remboursé
 * / rejeté (demandé le 18/10/2026).
 *
 * Remplace l'enum par une simple chaîne (sans doctrine/dbal disponible
 * pour ->change(), et pour éviter l'écart de comportement MySQL/SQLite
 * sur un ALTER d'enum) : aucune perte de données, la colonne ne contenait
 * jusqu'ici que sa valeur par défaut "aucun" sur toutes les lignes
 * (jamais écrite ailleurs dans le code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('statut_remboursement');
        });

        Schema::table('reservations', function (Blueprint $table) {
            // aucun | en_attente | en_cours | traite | rejete
            $table->string('statut_remboursement', 20)->default('aucun')->after('raison_annulation');
            // Historique horodaté de chaque transition (demande, tentative,
            // échec, succès, rejet...), en JSON : {"au": "...", "action": "...", "detail": "..."}[].
            // Toujours en complément de statut_remboursement, jamais à sa
            // place : évite de devoir reconstituer l'état actuel en relisant
            // tout l'historique.
            $table->text('remboursement_historique')->nullable()->after('raison_rejet_remboursement');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('remboursement_historique');
            $table->dropColumn('statut_remboursement');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('statut_remboursement', ['aucun', 'en_attente', 'traite', 'rejete'])
                ->default('aucun')
                ->after('raison_annulation');
        });
    }
};
