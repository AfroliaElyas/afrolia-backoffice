<?php

namespace App\Console\Commands;

use App\Models\Heures;
use App\Models\Jours;
use Illuminate\Console\Command;

/**
 * Même bug que specialites/langues (voir InitialiserSpecialites), constaté
 * le 20/10/2026 sur jours et heures : "Mes disponibilités" n'affichait
 * aucune grille de créneaux à cocher (jours vide), et poster des
 * disponibilités avec une liste vide échouait ("Les disponibilités sont
 * requises."). Sans disponibilité enregistrable, aucune coiffeuse ne
 * pouvait jamais apparaître avec un créneau réservable côté cliente.
 *
 * Idempotente (firstOrCreate par libellé) : peut être relancée sans risque,
 * elle ne crée jamais de doublon et n'efface rien.
 */
class InitialiserJoursHeures extends Command
{
    protected $signature = 'jours-heures:initialiser';

    protected $description = 'Crée les jours de la semaine et les créneaux horaires de base si absents.';

    private const JOURS = [
        'Lundi',
        'Mardi',
        'Mercredi',
        'Jeudi',
        'Vendredi',
        'Samedi',
        'Dimanche',
    ];

    // Créneaux horaires d'une journée type de salon de coiffure à Abidjan
    // (8h-19h), par pas d'une heure.
    private const HEURES = [
        '08:00', '09:00', '10:00', '11:00', '12:00', '13:00',
        '14:00', '15:00', '16:00', '17:00', '18:00', '19:00',
    ];

    public function handle(): int
    {
        $joursCrees = 0;
        foreach (self::JOURS as $libelle) {
            $jour = Jours::firstOrCreate(['libelle' => $libelle]);
            if ($jour->wasRecentlyCreated) {
                $joursCrees++;
            }
        }

        $heuresCrees = 0;
        foreach (self::HEURES as $libelle) {
            $heure = Heures::firstOrCreate(['libelle' => $libelle]);
            if ($heure->wasRecentlyCreated) {
                $heuresCrees++;
            }
        }

        $this->info("{$joursCrees} jour(s) créé(s), " . (count(self::JOURS) - $joursCrees) . ' déjà présent(s).');
        $this->info("{$heuresCrees} heure(s) créée(s), " . (count(self::HEURES) - $heuresCrees) . ' déjà présente(s).');

        return self::SUCCESS;
    }
}
