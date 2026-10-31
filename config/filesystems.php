<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Sauvegarde externe de la base de données (hors du serveur Forge),
        // sur Backblaze B2 — compatible S3, utilisé via le même pilote "s3".
        // Variables dédiées (BACKBLAZE_*) plutôt que AWS_* : ce n'est pas
        // Amazon, et ça évite toute confusion avec un futur usage réel d'AWS.
        //
        // trim() : un espace ou un retour à la ligne resté collé en
        // copiant-collant une valeur depuis Backblaze vers Forge suffit à
        // fausser la signature (« Signature validation failed »). Aucune
        // valeur Backblaze légitime ne commence ni ne finit par un espace.
        // `?: null` garde "absente" distinct de "vide" pour le diagnostic.
        'backblaze' => [
            'driver' => 's3',
            'key' => trim((string) env('BACKBLAZE_KEY_ID')) ?: null,
            'secret' => trim((string) env('BACKBLAZE_APPLICATION_KEY')) ?: null,
            'region' => trim((string) env('BACKBLAZE_REGION')) ?: null,
            'bucket' => trim((string) env('BACKBLAZE_BUCKET')) ?: null,
            'endpoint' => trim((string) env('BACKBLAZE_ENDPOINT')) ?: null,
            'use_path_style_endpoint' => false,
            // Les SDK AWS récents (aws-sdk-php ≥ 3.337) ajoutent par défaut
            // des sommes de contrôle CRC aux envois et lectures. Backblaze
            // B2 n'est pas Amazon S3 et a eu des incompatibilités avec ces
            // en-têtes : ne les envoyer que lorsque l'opération l'exige,
            // comme avant ce changement de comportement du SDK.
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            'throw' => true,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
