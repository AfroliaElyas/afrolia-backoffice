<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('commandes:annuler-expirees')->everyFiveMinutes();
Schedule::command('abonnements:prelever')->daily();
// ->appendOutputTo() : sans ça, rien ne prouve après coup que cette tâche
// s'exécute réellement sur le serveur (le cron Forge peut très bien ne
// jamais être actif pour schedule:run, ou l'être sans que cette commande
// précise tourne) — la présence du code ne suffit pas à le vérifier
// (demandé le 18/10/2026). Chaque exécution (même "0 dossier débloqué")
// ajoute une ligne horodatée à ce fichier, consultable depuis Forge.
Schedule::command('remboursements:debloquer-en-cours')
    ->everyFiveMinutes()
    ->appendOutputTo(storage_path('logs/scheduler-remboursements.log'));
