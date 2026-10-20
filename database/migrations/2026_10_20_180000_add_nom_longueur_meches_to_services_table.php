<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permet à une coiffeuse de proposer plusieurs prestations précises dans une
 * même catégorie (ex. spécialité "Box braids" -> services "Box braids
 * courtes" et "Box braids longues", prix et durées différents) :
 * - nom : nom précis de la prestation, distinct du libellé de catégorie
 *   (specialites.libelle) qui reste générique.
 * - longueur : texte libre (ex. "Épaules", "Jusqu'aux hanches"), pertinent
 *   seulement pour les prestations à mèches.
 * - meches_comprises : true si le prix inclut les mèches, false si la
 *   cliente doit les apporter, null si la distinction ne s'applique pas à
 *   cette prestation (ex. coupe, soin).
 * Toutes nullable pour ne pas invalider les services déjà enregistrés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('nom')->nullable()->after('id_speciale');
            $table->string('longueur')->nullable()->after('description');
            $table->boolean('meches_comprises')->nullable()->after('longueur');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['nom', 'longueur', 'meches_comprises']);
        });
    }
};
