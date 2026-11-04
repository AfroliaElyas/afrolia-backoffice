<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le suivi de remboursement aux commandes boutique, sur le même
 * principe que reservations.statut_remboursement (voir
 * 2026_10_18_060300_suivi_statuts_remboursement.php) : demandé le
 * 04/11/2026, suite à l'audit financier qui avait relevé qu'aucun moyen
 * n'existait de rembourser une commande boutique déjà payée.
 *
 * statut_paiement est un enum ('en_attente', 'paye', 'echoue') : remplacé
 * par une simple chaîne pour pouvoir y ajouter 'rembourse' sans ->change()
 * (doctrine/dbal non disponible, même raison que pour reservations). Les
 * valeurs existantes sont sauvegardées puis restaurées après coup, aucune
 * perte de données.
 */
return new class extends Migration
{
    public function up(): void
    {
        $valeursExistantes = DB::table('commandes')->pluck('statut_paiement', 'id_commande');

        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('statut_paiement');
        });

        Schema::table('commandes', function (Blueprint $table) {
            // en_attente | paye | echoue | rembourse
            $table->string('statut_paiement', 20)->default('en_attente')->after('methode_paiement');
        });

        foreach ($valeursExistantes as $id => $valeur) {
            DB::table('commandes')->where('id_commande', $id)->update(['statut_paiement' => $valeur]);
        }

        Schema::table('commandes', function (Blueprint $table) {
            // aucun | en_attente | en_cours | traite (pas de workflow de
            // litige/rejet pour les commandes, contrairement aux
            // réservations : non demandé).
            $table->string('statut_remboursement', 20)->default('aucun')->after('raison_annulation');
            $table->text('remboursement_historique')->nullable()->after('statut_remboursement');
            $table->string('remboursement_reference', 50)->nullable()->unique()->after('remboursement_historique');
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropUnique(['remboursement_reference']);
        });

        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('remboursement_reference');
        });

        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('remboursement_historique');
        });

        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('statut_remboursement');
        });

        $valeursExistantes = DB::table('commandes')->pluck('statut_paiement', 'id_commande');

        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('statut_paiement');
        });

        Schema::table('commandes', function (Blueprint $table) {
            $table->enum('statut_paiement', ['en_attente', 'paye', 'echoue'])->default('en_attente')->after('methode_paiement');
        });

        foreach ($valeursExistantes as $id => $valeur) {
            if (in_array($valeur, ['en_attente', 'paye', 'echoue'], true)) {
                DB::table('commandes')->where('id_commande', $id)->update(['statut_paiement' => $valeur]);
            }
        }
    }
};
