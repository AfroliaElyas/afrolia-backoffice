<?php

namespace App\Console\Commands;

use App\Models\Specialites;
use Illuminate\Console\Command;

/**
 * Remplit la table specialites avec le catalogue de base des prestations
 * capillaires proposées sur Afrolia : aucune migration ni seeder ne l'a
 * jamais fait (constaté le 20/10/2026), si bien que la table est restée
 * vide en production. Résultat pour une coiffeuse : le sélecteur de
 * spécialité de "Infos professionnelles" et de "Ajout de Services & Tarifs"
 * n'avait rien à proposer, impossible donc de créer un service.
 *
 * Idempotente (updateOrCreate par libellé) : peut être relancée sans risque,
 * elle ne crée jamais de doublon et n'efface rien.
 */
class InitialiserSpecialites extends Command
{
    protected $signature = 'specialites:initialiser';

    protected $description = 'Crée les spécialités de base (Tresses, Locks, Coloration, ...) si elles n\'existent pas déjà.';

    private const LIBELLES = [
        'Tresses africaines',
        'Nattes / Vanilles',
        'Locks / Dreadlocks',
        'Tissage & extensions',
        'Coupe & coiffure',
        'Défrisage',
        'Coloration',
        'Soins capillaires',
        'Pose de perruque',
        'Lissage brésilien',
        'Coiffure de mariée',
        'Crochet braids',
    ];

    public function handle(): int
    {
        $crees = 0;

        foreach (self::LIBELLES as $libelle) {
            $specialite = Specialites::firstOrCreate(['libelle' => $libelle]);

            if ($specialite->wasRecentlyCreated) {
                $crees++;
            }
        }

        $this->info("{$crees} spécialité(s) créée(s), " . (count(self::LIBELLES) - $crees) . ' déjà présente(s).');

        return self::SUCCESS;
    }
}
