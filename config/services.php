<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    // Envoi des e-mails (vérification d'adresse, lien de réinitialisation
    // de mot de passe) via l'API HTTPS de Brevo. Voir config/mail.php et
    // AppServiceProvider::boot().
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

    // Dossier des sauvegardes locales prises à chaque déploiement (voir
    // deploy/forge-deploy.sh, BACKUP_DIR). Configurable pour les tests
    // automatiques ; en production, HOME vaut toujours /home/forge.
    'sauvegarde' => [
        'dossier_local' => env('SAUVEGARDE_LOCALE_DOSSIER', (getenv('HOME') ?: '/home/forge').'/backups/afrolia-db'),
    ],

    // Adresse prévenue par e-mail quand sauvegarde:externe échoue (base ou
    // photos) : sans ça, un échec de cette tâche quotidienne ne casse rien
    // de visible sur le site et peut passer inaperçu indéfiniment (ajouté
    // le 10/11/2026, suite au constat que la sauvegarde externe dépend de
    // la même connexion Backblaze restée bloquée tout ce temps). Optionnel
    // : si vide, aucune alerte n'est envoyée (comportement actuel inchangé).
    'alertes' => [
        'email' => env('ADMIN_ALERT_EMAIL'),
    ],

    // Sauvegarde externe chiffrée vers les Releases d'un dépôt GitHub
    // privé (sauvegarde:github), totalement indépendante de Backblaze
    // (voir SauvegardeExterneCommand) : ni l'une ni l'autre ne dépend du
    // bon fonctionnement de la seconde.
    'sauvegarde_github' => [
        // Clé de chiffrement (AES-256) : à garder uniquement ici et dans
        // le gestionnaire de mots de passe de Ruth — jamais sur GitHub.
        // Seule variable encore utilisée par sauvegarde:github depuis le
        // 13/11/2026 (voir ci-dessous).
        'cle' => env('SAUVEGARDE_GITHUB_CLE'),

        // SAUVEGARDE_GITHUB_TOKEN et SAUVEGARDE_GITHUB_DEPOT ne sont PLUS
        // utilisées depuis le 13/11/2026 : sauvegarde:github ne parle plus
        // du tout à l'API GitHub (abandonné après un échec persistant
        // "Bad credentials" qui obligeait à chaque fois un nouveau
        // copier-coller suivi d'un déploiement complet juste pour
        // vérifier). La publication vers GitHub est désormais faite par le
        // workflow .github/workflows/sauvegarde-github.yml, avec le jeton
        // automatique GITHUB_TOKEN — plus aucun jeton personnel à gérer.
        // Ces deux lignes de .env peuvent être retirées quand Ruth le
        // souhaite ; les laisser ne gêne rien.
        'token' => env('SAUVEGARDE_GITHUB_TOKEN'),
        'depot' => env('SAUVEGARDE_GITHUB_DEPOT'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'mobile_money' => [
        'webhook_secret' => env('MOBILE_MONEY_WEBHOOK_SECRET'),
    ],

    // Agrégateur Mobile Money retenu (Jèko). Voir App\Services\MobileMoney\JekoGateway
    // pour l'implémentation complète (création de paiement, vérification de
    // statut, vérification de signature webhook).
    'jeko' => [
        'api_key' => env('JEKO_API_KEY'),
        'api_key_id' => env('JEKO_API_KEY_ID'),
        'store_id' => env('JEKO_STORE_ID'),
        'webhook_secret' => env('JEKO_WEBHOOK_SECRET'),
        'base_url' => env('JEKO_BASE_URL', 'https://api.jeko.africa'),
        // Liens profonds (deep links) de l'application mobile vers lesquels
        // Jèko redirige après paiement : pas besoin d'hébergement web public
        // pour ceux-ci (seul le webhook a besoin d'une URL HTTPS publique).
        'success_url' => env('JEKO_SUCCESS_URL', 'afrolia://paiement/succes'),
        'error_url' => env('JEKO_ERROR_URL', 'afrolia://paiement/echec'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    // Notifications push (Firebase Cloud Messaging), demandées le
    // 03/11/2026. Voir App\Services\NotificationService : tant que
    // credentials_json n'est pas renseigné (projet Firebase à créer), les
    // notifications sont ignorées silencieusement plutôt que de faire
    // échouer l'action qui les déclenche.
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        // Contenu intégral (JSON) du fichier de clé de compte de service
        // Firebase (Paramètres du projet > Comptes de service > Générer
        // une nouvelle clé privée), collé tel quel comme valeur de
        // variable d'environnement — jamais un chemin de fichier, pour
        // rester cohérent avec le reste de la configuration (Backblaze,
        // Jèko...).
        'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),
    ],

];
