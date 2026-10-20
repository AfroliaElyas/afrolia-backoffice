<?php

namespace App\Console\Commands;

use App\Models\Langues;
use Illuminate\Console\Command;

/**
 * Même bug que specialites (voir InitialiserSpecialites), constaté sur la
 * table langues le 20/10/2026 : "Langues parlées" affichait "Pas de Langue
 * disponible" dans "Infos professionnelles", table jamais peuplée en
 * production.
 *
 * Idempotente (firstOrCreate par libellé) : peut être relancée sans risque,
 * elle ne crée jamais de doublon et n'efface rien.
 */
class InitialiserLangues extends Command
{
    protected $signature = 'langues:initialiser';

    protected $description = 'Crée les langues de base (Français, Anglais, Dioula, ...) si elles n\'existent pas déjà.';

    private const LIBELLES = [
        'Français',
        'Anglais',
        'Dioula',
        'Baoulé',
        'Bété',
        'Sénoufo',
        'Agni',
        'Attié',
    ];

    public function handle(): int
    {
        $crees = 0;

        foreach (self::LIBELLES as $libelle) {
            $langue = Langues::firstOrCreate(['libelle' => $libelle]);

            if ($langue->wasRecentlyCreated) {
                $crees++;
            }
        }

        $this->info("{$crees} langue(s) créée(s), " . (count(self::LIBELLES) - $crees) . ' déjà présente(s).');

        return self::SUCCESS;
    }
}
