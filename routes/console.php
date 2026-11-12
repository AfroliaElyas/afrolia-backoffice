<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('commandes:annuler-expirees')->everyFiveMinutes();

// Rappel de rendez-vous (notification push), demandé le 03/11/2026 :
// fenêtre d'envoi d'1h, vérifiée toutes les 5 minutes — largement
// suffisant pour ne manquer aucune réservation (rappel_envoye_at
// empêche tout doublon, voir EnvoyerRappelsReservationCommand).
Schedule::command('rappels:envoyer')
    ->everyFiveMinutes()
    ->appendOutputTo(storage_path('logs/scheduler-rappels.log'));
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

// Sauvegarde externe (Backblaze B2) de la base de données, indépendante des
// déploiements : les données changent tous les jours même sans déploiement.
// Mêmes raisons qu'au-dessus pour appendOutputTo() — et ici d'autant plus
// utile qu'un échec silencieux de cette tâche précise (identifiants B2
// expirés, quota dépassé...) ne casserait rien de visible sur le site.
Schedule::command('sauvegarde:externe')
    ->daily()
    ->appendOutputTo(storage_path('logs/scheduler-sauvegarde-externe.log'));

// Sauvegarde externe chiffrée vers GitHub (sauvegarde:github), totalement
// indépendante de Backblaze — voir SauvegardeGithubCommand. Décalée à
// 00h20 (au lieu de minuit) pour ne pas faire tourner deux mysqldump en
// même temps que sauvegarde:externe ci-dessus.
Schedule::command('sauvegarde:github')
    ->dailyAt('00:20')
    ->appendOutputTo(storage_path('logs/scheduler-sauvegarde-github.log'));
