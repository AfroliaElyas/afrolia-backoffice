<?php

namespace Tests\Feature;

use Tests\TestCase;

class DiagnostiquerBackblazeTest extends TestCase
{
    // L'endpoint pointe vers un port local fermé (jamais le vrai
    // Backblaze) : la commande teste toujours une vraie connexion en
    // section 3, donc même les tests qui ne portent que sur les sections 1
    // et 2 doivent éviter tout appel réseau réel vers backblazeb2.com.
    private const CONFIG_VALIDE = [
        'filesystems.disks.backblaze.key' => 'cle-de-test',
        'filesystems.disks.backblaze.secret' => 'secret-de-test',
        'filesystems.disks.backblaze.region' => 'us-west-004',
        'filesystems.disks.backblaze.bucket' => 'afrolia-test',
        'filesystems.disks.backblaze.endpoint' => 'http://127.0.0.1:1',
    ];

    public function test_signale_chaque_variable_manquante(): void
    {
        config([
            'filesystems.disks.backblaze.key' => null,
            'filesystems.disks.backblaze.secret' => '',
            'filesystems.disks.backblaze.region' => null,
            'filesystems.disks.backblaze.bucket' => null,
            'filesystems.disks.backblaze.endpoint' => null,
        ]);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('BACKBLAZE_KEY_ID : ABSENTE ou vide.')
            ->expectsOutputToContain('BACKBLAZE_APPLICATION_KEY : ABSENTE ou vide.')
            ->assertExitCode(1);
    }

    public function test_detecte_un_espace_parasite_en_fin_de_valeur(): void
    {
        config(array_merge(self::CONFIG_VALIDE, [
            // Faute de transcription typique : un espace resté collé en
            // copiant-collant la clé depuis Backblaze vers Forge.
            'filesystems.disks.backblaze.key' => 'cle-de-test ',
        ]));

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('ESPACE(S) PARASITE(S)')
            ->assertExitCode(1);
    }

    public function test_ne_signale_aucun_espace_parasite_quand_tout_est_propre(): void
    {
        config(self::CONFIG_VALIDE);

        $this->artisan('backblaze:diagnostiquer')
            ->doesntExpectOutputToContain('ESPACE(S) PARASITE(S)');
    }

    public function test_detecte_une_region_absente_de_l_endpoint(): void
    {
        config(array_merge(self::CONFIG_VALIDE, [
            'filesystems.disks.backblaze.region' => 'eu-central-003',
            // Endpoint resté sur l'ancienne région après un changement de
            // bucket, par exemple (jamais un vrai domaine Backblaze ici).
            'filesystems.disks.backblaze.endpoint' => 'http://127.0.0.1:1',
        ]));

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('INCOHÉRENT')
            ->assertExitCode(1);
    }

    public function test_ne_affiche_jamais_les_secrets_en_clair(): void
    {
        config(array_merge(self::CONFIG_VALIDE, [
            'filesystems.disks.backblaze.key' => 'cle-secrete-ne-doit-jamais-apparaitre',
            'filesystems.disks.backblaze.secret' => 'application-key-ne-doit-jamais-apparaitre',
        ]));

        $this->artisan('backblaze:diagnostiquer')
            ->doesntExpectOutputToContain('cle-secrete-ne-doit-jamais-apparaitre')
            ->doesntExpectOutputToContain('application-key-ne-doit-jamais-apparaitre');
    }

    public function test_detecte_une_region_copiee_avec_son_prefixe_s3(): void
    {
        // Erreur la plus courante : la région recopiée depuis l'endpoint
        // avec son « s3. ». L'ancienne vérification la laissait passer
        // (l'endpoint la « contient » bien), alors qu'elle fausse la
        // signature.
        $this->assertNotEmpty($this->anomalies('s3.us-west-004', 'https://s3.us-west-004.backblazeb2.com'));
    }

    public function test_detecte_un_endpoint_backblaze_qui_ne_correspond_pas_a_la_region(): void
    {
        $this->assertNotEmpty($this->anomalies('us-west-004', 'https://s3.eu-central-003.backblazeb2.com'));
        $this->assertNotEmpty($this->anomalies('us-west-004', 'https://afrolia.s3.us-west-004.backblazeb2.com'));
        $this->assertNotEmpty($this->anomalies('us-west-004', 'https://s3.us-west-004.backblazeb2.com/afrolia'));
        $this->assertNotEmpty($this->anomalies('us-west-004', 's3.us-west-004.backblazeb2.com'));
    }

    public function test_accepte_une_configuration_backblaze_correcte(): void
    {
        $this->assertSame([], $this->anomalies('us-west-004', 'https://s3.us-west-004.backblazeb2.com'));
        $this->assertSame([], $this->anomalies('eu-central-003', 'https://s3.eu-central-003.backblazeb2.com/'));
    }

    /**
     * Appelle directement la vérification de forme, sans la section 3 de la
     * commande : aucun appel réseau vers backblazeb2.com dans les tests.
     *
     * @return list<string>
     */
    private function anomalies(string $region, string $endpoint): array
    {
        $methode = new \ReflectionMethod(\App\Console\Commands\DiagnostiquerBackblazeCommand::class, 'verifierRegionEtEndpoint');

        return $methode->invoke(new \App\Console\Commands\DiagnostiquerBackblazeCommand(), $region, $endpoint);
    }

    public function test_echoue_proprement_si_la_connexion_echoue(): void
    {
        // CONFIG_VALIDE pointe déjà vers un port local fermé (jamais le
        // vrai Backblaze) : la commande doit afficher le message d'erreur
        // complet plutôt que de planter.
        config(self::CONFIG_VALIDE);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('ÉCHEC')
            ->assertExitCode(1);
    }

    public function test_compare_le_resultat_du_sdk_brut_et_de_laravel(): void
    {
        // Ajouté le 06/11/2026 : après plusieurs correctifs de config sans
        // effet observé, ce test direct au SDK AWS (sans Flysystem) permet
        // de savoir si le blocage vient de notre code ou de Backblaze.
        config(self::CONFIG_VALIDE);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('=== 4. Test direct avec le SDK AWS, sans Flysystem ===')
            ->expectsOutputToContain('=== Comparaison ===')
            ->assertExitCode(1);
    }

    public function test_affiche_la_cause_reelle_derriere_l_exception_flysystem(): void
    {
        // League\Flysystem enveloppe toujours l'erreur du SDK AWS dans sa
        // propre exception générique ("Unable to check existence for..."),
        // qui ne contient jamais le vrai code renvoyé par Backblaze
        // (SignatureDoesNotMatch, InvalidAccessKeyId...) — seule la cause
        // d'origine, récupérable via getPrevious(), le contient. Confirmé
        // trop tard sur un vrai diagnostic en production le 01/11/2026 :
        // le premier message affiché restait "Unable to check existence
        // for: ..." sans jamais révéler le code AWS réel.
        config(self::CONFIG_VALIDE);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('Cause réelle')
            ->assertExitCode(1);
    }

    public function test_teste_l_api_native_b2_et_rapporte_le_succes(): void
    {
        // Ajouté le 08/11/2026 : après confirmation que l'applicationKey
        // enregistrée dans Forge correspond exactement à la copie d'origine,
        // les étapes 3 et 4 (toutes deux via la compatibilité S3) ne peuvent
        // plus distinguer "identifiants invalides" de "passerelle S3
        // Backblaze défaillante pour ce compte". Ce test appelle l'API
        // native B2 (b2_authorize_account), indépendante de toute
        // compatibilité S3, avec les mêmes identifiants.
        \Illuminate\Support\Facades\Http::fake([
            'api.backblazeb2.com/*' => \Illuminate\Support\Facades\Http::response([
                'accountId' => 'compte-de-test',
                'allowed' => [
                    'capabilities' => ['listFiles', 'writeFiles', 'deleteFiles'],
                    'bucketName' => 'afrolia-test',
                ],
            ], 200),
        ]);
        config(self::CONFIG_VALIDE);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('=== 5. Test avec l\'API native Backblaze B2 (sans aucune compatibilité S3) ===')
            ->expectsOutputToContain('Authentification native B2')
            ->expectsOutputToContain('compte-de-test')
            ->expectsOutputToContain('listFiles, writeFiles, deleteFiles')
            ->expectsOutputToContain('spécifique à la')
            ->assertExitCode(1); // Les étapes 3/4 (S3, port fermé) échouent toujours.
    }

    public function test_affiche_l_empreinte_sha256_du_keyid_et_de_l_application_key(): void
    {
        // Ajouté le 08/11/2026 : permet une comparaison fiable (une page
        // locale calculant la même empreinte dans un navigateur, par
        // exemple) entre la valeur stockée et une copie d'origine, sans
        // jamais faire transiter ni afficher le secret lui-même, et sans le
        // risque d'erreur d'une comparaison visuelle (0/O, 1/l/I).
        config(self::CONFIG_VALIDE);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('empreinte SHA-256 : ' . hash('sha256', 'cle-de-test'))
            ->expectsOutputToContain('empreinte SHA-256 : ' . hash('sha256', 'secret-de-test'))
            // "secret-de-test" (l'application key) n'est jamais affiché en
            // clair, quelle que soit sa longueur (couvert séparément par
            // test_ne_affiche_jamais_les_secrets_en_clair pour le keyID,
            // dont le masquage dépend lui de la longueur).
            ->doesntExpectOutputToContain('secret-de-test');
    }

    /**
     * Pointe temporairement l'application vers un fichier .env de test
     * (dans un dossier à part, jamais le vrai .env du projet), le temps
     * d'un test, puis nettoie derrière lui.
     */
    private function avecFichierEnvDeTest(string $contenu, \Closure $test): void
    {
        $dossier = sys_get_temp_dir().'/afrolia-env-test-'.uniqid();
        mkdir($dossier);
        $fichier = '.env.test';
        file_put_contents($dossier.'/'.$fichier, $contenu);

        $this->app->useEnvironmentPath($dossier);
        $this->app->loadEnvironmentFrom($fichier);

        try {
            $test();
        } finally {
            @unlink($dossier.'/'.$fichier);
            @rmdir($dossier);
        }
    }

    public function test_rapporte_une_valeur_absente_du_fichier_env_reel(): void
    {
        // Ajouté le 08/11/2026, en remplacement d'une précédente version
        // basée sur getenv() : fausse, car Laravel saute EXPRÈS le
        // chargement de .env quand la configuration est en cache (vérifié
        // dans le code du framework), rendant getenv() tout aussi peu
        // fiable dans ce cas précis. Seule une lecture directe du fichier
        // .env réel, avec le même analyseur que Laravel (Dotenv::parse()),
        // est fiable indépendamment du cache.
        $this->avecFichierEnvDeTest("APP_NAME=Afrolia\n", function () {
            config(self::CONFIG_VALIDE);

            $this->artisan('backblaze:diagnostiquer')
                ->expectsOutputToContain('BACKBLAZE_KEY_ID : absent du fichier .env')
                ->expectsOutputToContain('BACKBLAZE_APPLICATION_KEY : absent du fichier .env');
        });
    }

    public function test_rapporte_une_valeur_identique_entre_config_et_fichier_env_reel(): void
    {
        $this->avecFichierEnvDeTest("BACKBLAZE_KEY_ID=cle-de-test\n", function () {
            config(self::CONFIG_VALIDE); // 'cle-de-test', cohérent avec le fichier ci-dessus.

            $this->artisan('backblaze:diagnostiquer')
                ->expectsOutputToContain('BACKBLAZE_KEY_ID : ✅ identique');
        });
    }

    public function test_detecte_un_ecart_entre_la_config_chargee_et_le_fichier_env_reel(): void
    {
        // Simule le scénario qui a motivé ce correctif : la configuration
        // chargée (ex. depuis un cache périmé) diffère de ce qui est
        // réellement écrit dans .env aujourd'hui. --dans-un-nouveau-
        // processus bypasse volontairement le redémarrage automatique
        // (qui lancerait un vrai sous-processus, hors de portée d'un test
        // unitaire rapide) pour isoler la détection elle-même ; le
        // redémarrage réel est vérifié en conditions réelles (PR #131,
        // relancée en production le 08/11/2026).
        $this->avecFichierEnvDeTest("BACKBLAZE_KEY_ID=valeur-reellement-dans-env\n", function () {
            config(array_merge(self::CONFIG_VALIDE, [
                'filesystems.disks.backblaze.key' => 'valeur-chargee-en-config',
            ]));

            $this->artisan('backblaze:diagnostiquer', ['--dans-un-nouveau-processus' => true])
                ->expectsOutputToContain('BACKBLAZE_KEY_ID : ⚠️ DIFFÉRENT')
                ->doesntExpectOutputToContain('valeur-reellement-dans-env')
                ->doesntExpectOutputToContain('valeur-chargee-en-config');
        });
    }

    public function test_ne_redemarre_pas_en_boucle_quand_deja_dans_un_nouveau_processus(): void
    {
        // L'option --dans-un-nouveau-processus doit empêcher tout
        // redémarrage, même face à un écart — sans quoi une configuration
        // réellement incohérente provoquerait une boucle infinie.
        $this->avecFichierEnvDeTest("BACKBLAZE_KEY_ID=valeur-reellement-dans-env\n", function () {
            config(array_merge(self::CONFIG_VALIDE, [
                'filesystems.disks.backblaze.key' => 'valeur-chargee-en-config',
            ]));

            $this->artisan('backblaze:diagnostiquer', ['--dans-un-nouveau-processus' => true])
                ->doesntExpectOutputToContain('Redémarrage dans un nouveau processus');
        });
    }

    public function test_teste_l_api_native_b2_et_rapporte_l_echec_sans_afficher_les_secrets(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.backblazeb2.com/*' => \Illuminate\Support\Facades\Http::response([
                'status' => 401,
                'code' => 'unauthorized',
                'message' => 'The applicationKeyId and applicationKey did not match.',
            ], 401),
        ]);
        config(array_merge(self::CONFIG_VALIDE, [
            'filesystems.disks.backblaze.key' => 'cle-secrete-ne-doit-jamais-apparaitre',
            'filesystems.disks.backblaze.secret' => 'application-key-ne-doit-jamais-apparaitre',
        ]));

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('ÉCHEC (API native B2)')
            ->expectsOutputToContain('unauthorized')
            ->expectsOutputToContain('did not match')
            ->expectsOutputToContain('TROIS mécanismes')
            ->doesntExpectOutputToContain('cle-secrete-ne-doit-jamais-apparaitre')
            ->doesntExpectOutputToContain('application-key-ne-doit-jamais-apparaitre')
            ->assertExitCode(1);
    }
}
