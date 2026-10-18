<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
 *
 * Reprend aussi les dossiers déjà annulés AVANT cette migration (up() ci-
 * dessous) : sans ce rattrapage, ils resteraient à "aucun" pour toujours
 * et disparaîtraient silencieusement de la liste "à traiter", alors qu'ils
 * répondaient déjà à l'ancien filtre implicite (demandé le 18/10/2026).
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
            // Référence stable envoyée à Jèko pour un transfert de
            // remboursement, nécessaire pour rattacher le webhook de
            // confirmation/échec du transfert à ce dossier précis (voir
            // ApiPaiementsController::mobileMoneyWebhook).
            $table->string('remboursement_reference', 50)->nullable()->unique()->after('remboursement_historique');
        });

        // Rattrapage : une réservation déjà annulée + déjà payée avant
        // cette migration répondait à l'ancien filtre implicite
        // (statut=annulee ET statut_paiement=paye) et doit donc apparaître
        // comme "à traiter", pas comme "aucun remboursement dû". Celles
        // déjà remboursées (statut_paiement=rembourse) passent directement
        // à "traite" : elles n'ont plus rien à faire, mais doivent rester
        // visibles et correctement classées, pas invisibles.
        DB::table('reservations')
            ->where('statut', 'annulee')
            ->where('statut_paiement', 'paye')
            ->update([
                'statut_remboursement' => 'en_attente',
                'remboursement_historique' => json_encode([[
                    'au' => now()->toIso8601String(),
                    'action' => 'demande_creee',
                    'detail' => 'Dossier déjà annulé et payé avant la mise en place de ce suivi (rattrapage de migration)',
                ]]),
            ]);

        DB::table('reservations')
            ->where('statut', 'annulee')
            ->where('statut_paiement', 'rembourse')
            ->update([
                'statut_remboursement' => 'traite',
                'remboursement_historique' => json_encode([[
                    'au' => now()->toIso8601String(),
                    'action' => 'rembourse',
                    'detail' => 'Dossier déjà remboursé avant la mise en place de ce suivi (rattrapage de migration)',
                ]]),
            ]);
    }

    public function down(): void
    {
        // Chaque colonne supprimée dans son propre Schema::table() : sur
        // SQLite (tests), qui reconstruit la table entière à chaque
        // suppression de colonne, regrouper celle qui porte un index
        // unique (remboursement_reference) avec les autres dans un même
        // appel fait échouer la reconstruction ("error in index ... after
        // drop column"). Fonctionne aussi tel quel sur MySQL (production).
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['remboursement_reference']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('remboursement_reference');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('remboursement_historique');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('statut_remboursement');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('statut_remboursement', ['aucun', 'en_attente', 'traite', 'rejete'])
                ->default('aucun')
                ->after('raison_annulation');
        });
    }
};
