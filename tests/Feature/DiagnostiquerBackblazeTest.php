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

    public function test_detecte_un_ecart_et_utilise_la_valeur_du_fichier_pour_la_suite_du_diagnostic(): void
    {
        // Simule le scénario qui a motivé ce correctif : la configuration
        // chargée (ex. depuis un cache périmé) diffère de ce qui est
        // réellement écrit dans .env aujourd'hui.
        //
        // Une version intermédiaire relançait la commande dans un nouveau
        // processus pour repartir sur une configuration fraîche — écartée
        // avant déploiement : un nouveau processus démarré juste après
        // peut lui aussi retomber sur un cache déjà périmé ou tout juste
        // régénéré, sans qu'aucune garantie n'existe entre les deux
        // instants. La version actuelle évite complètement le problème en
        // ne redémarrant jamais : elle utilise directement, dans LE MÊME
        // processus, la valeur lue dans le fichier .env réel — dont la
        // source est identifiée sans ambiguïté — à la place de la
        // configuration chargée. Vérifié ici via l'empreinte SHA-256
        // affichée en section 1 : elle doit correspondre à la valeur du
        // FICHIER, jamais à celle (périmée) de la configuration chargée.
        $this->avecFichierEnvDeTest("BACKBLAZE_KEY_ID=valeur-reellement-dans-env\n", function () {
            config(array_merge(self::CONFIG_VALIDE, [
                'filesystems.disks.backblaze.key' => 'valeur-chargee-en-config',
            ]));

            $this->artisan('backblaze:diagnostiquer')
                ->expectsOutputToContain('BACKBLAZE_KEY_ID : ⚠️ DIFFÉRENT')
                ->expectsOutputToContain('empreinte SHA-256 : '.hash('sha256', 'valeur-reellement-dans-env'))
                ->doesntExpectOutputToContain(hash('sha256', 'valeur-chargee-en-config'))
                ->doesntExpectOutputToContain('valeur-reellement-dans-env')
                ->doesntExpectOutputToContain('valeur-chargee-en-config');
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

    public function test_ignore_la_verification_tls_pendant_les_tests_automatiques(): void
    {
        // Ajouté le 08/11/2026 : la vérification TLS a besoin d'une vraie
        // connexion réseau, jamais souhaitable dans la suite de tests
        // automatiques (lenteur, instabilité). Elle doit rester muette ici
        // et s'exécuter seulement en conditions réelles.
        config(self::CONFIG_VALIDE);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('=== 6. Authenticité du certificat TLS présenté par Backblaze (depuis ce serveur) ===')
            ->expectsOutputToContain('ignoré pendant les tests automatiques');
    }

    public function test_signale_un_hote_injoignable_lors_de_l_inspection_tls(): void
    {
        // Teste directement la méthode (hors du garde-fou "tests
        // automatiques" ci-dessus) contre un port local fermé : échec de
        // connexion immédiat et prévisible, sans dépendre d'un vrai
        // service ni risquer d'attendre le délai d'expiration (10s) d'une
        // adresse injoignable mais routée.
        $commande = new \App\Console\Commands\DiagnostiquerBackblazeCommand();
        $methode = new \ReflectionMethod($commande, 'inspecterCertificatTls');

        $resultat = $methode->invoke($commande, '127.0.0.1');

        $this->assertFalse($resultat['ok']);
        $this->assertNotEmpty($resultat['details']);
    }

    /**
     * Nettoie le journal de test (jamais présent avant ces tests, dans un
     * dépôt fraîchement cloné) sans jamais toucher à un vrai journal qui
     * existerait déjà localement.
     */
    private function avecJournalDeTest(string $contenu, \Closure $test): void
    {
        $chemin = storage_path('logs/scheduler-sauvegarde-externe.log');
        $existaitDeja = is_file($chemin);
        $sauvegarde = $existaitDeja ? file_get_contents($chemin) : null;

        file_put_contents($chemin, $contenu);

        try {
            $test();
        } finally {
            if ($existaitDeja) {
                file_put_contents($chemin, $sauvegarde);
            } else {
                @unlink($chemin);
            }
        }
    }

    public function test_rapporte_lorsque_aucun_journal_de_sauvegarde_nest_trouve(): void
    {
        config(self::CONFIG_VALIDE);
        $chemin = storage_path('logs/scheduler-sauvegarde-externe.log');
        $existaitDeja = is_file($chemin);
        if ($existaitDeja) {
            $this->markTestSkipped('Un vrai journal existe déjà à cet emplacement local : test sauté pour ne pas le perturber.');
        }

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('=== 7. État de la dernière sauvegarde externe (base + photos) ===')
            ->expectsOutputToContain('Aucun journal trouvé')
            ->expectsOutputToContain('Impossible de confirmer la date de la dernière sauvegarde réussie');
    }

    public function test_rapporte_la_derniere_sauvegarde_reussie_depuis_le_journal(): void
    {
        config(self::CONFIG_VALIDE);

        $this->avecJournalDeTest(
            "Sauvegarde externe envoyée : afrolia-db-20261105_030001.sql.gz (1234 octets).\n"
            ."Sauvegarde externe envoyée : afrolia-db-20261108_030002.sql.gz (1250 octets).\n"
            ."Sauvegarde photos envoyée : afrolia-photos-20261108_030015.tar.gz (999 octets).\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('Base de données : ✅ dernier envoi réussi confirmé le 08/11/2026 03:00:02')
                    ->expectsOutputToContain('Photos          : ✅ dernier envoi réussi confirmé le 08/11/2026 03:00:15');
            }
        );
    }

    public function test_ne_confond_pas_les_archives_heritage_avec_la_derniere_sauvegarde_photos(): void
    {
        // Même piège que pour la purge (voir SauvegardeExterneCommand) :
        // une archive "afrolia-photos-heritage-*" ne doit jamais être
        // retenue comme preuve d'une sauvegarde photos réussie.
        config(self::CONFIG_VALIDE);

        $this->avecJournalDeTest(
            "Sauvegarde photos envoyée : afrolia-photos-heritage-20261109_030020.tar.gz (500 octets).\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('Photos          : ⚠️ aucun envoi réussi trouvé');
            }
        );
    }

    public function test_rapporte_la_derniere_erreur_du_journal(): void
    {
        config(self::CONFIG_VALIDE);

        $this->avecJournalDeTest(
            "ÉCHEC : mysqldump a échoué — identifiants refusés.\n"
            ."AVERTISSEMENT : purge des anciennes sauvegardes impossible — délai dépassé.\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain("délai dépassé");
            }
        );
    }

    public function test_liste_les_sauvegardes_locales_recentes(): void
    {
        config(self::CONFIG_VALIDE);

        $dossier = sys_get_temp_dir().'/test-backups-afrolia-'.uniqid();
        mkdir($dossier);
        file_put_contents($dossier.'/afrolia-db-20261108.sql.gz', str_repeat('x', 2048));
        touch($dossier.'/afrolia-db-20261108.sql.gz', strtotime('2026-11-08 03:00:00'));
        file_put_contents($dossier.'/afrolia-db-20261109.sql.gz', str_repeat('x', 2048));
        touch($dossier.'/afrolia-db-20261109.sql.gz', strtotime('2026-11-09 03:00:00'));
        config(['services.sauvegarde.dossier_local' => $dossier]);

        try {
            $this->artisan('backblaze:diagnostiquer')
                ->expectsOutputToContain('2 fichier(s), la plus récente en premier')
                ->expectsOutputToContain('afrolia-db-20261109.sql.gz');
        } finally {
            array_map('unlink', glob($dossier.'/*'));
            rmdir($dossier);
        }
    }

    public function test_rapporte_lorsque_aucune_sauvegarde_locale_nest_trouvee(): void
    {
        config(self::CONFIG_VALIDE);
        config(['services.sauvegarde.dossier_local' => sys_get_temp_dir().'/dossier-inexistant-'.uniqid()]);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('Aucune sauvegarde locale trouvée');
    }

    public function test_signale_que_l_alerte_par_email_n_est_pas_configuree(): void
    {
        config(self::CONFIG_VALIDE);
        config(['services.alertes.email' => null]);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('NON configurée — aucune alerte');
    }

    public function test_signale_que_l_alerte_par_email_est_configuree(): void
    {
        config(self::CONFIG_VALIDE);
        config(['services.alertes.email' => 'admin-test@afrolia-app.com']);

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('✅ configurée, active');
    }

    public function test_signale_les_trois_reglages_sauvegarde_github_absents(): void
    {
        config(self::CONFIG_VALIDE);
        config([
            'services.sauvegarde_github.cle' => null,
            'services.sauvegarde_github.token' => null,
            'services.sauvegarde_github.depot' => null,
        ]);

        $this->avecFichierEnvDeTest("APP_NAME=Afrolia\n", function () {
            $this->artisan('backblaze:diagnostiquer')
                ->expectsOutputToContain('SAUVEGARDE_GITHUB_CLE : ❌ absent du fichier .env ET de la configuration chargée.')
                ->expectsOutputToContain('SAUVEGARDE_GITHUB_TOKEN : ❌ absent du fichier .env ET de la configuration chargée.')
                ->expectsOutputToContain('SAUVEGARDE_GITHUB_DEPOT : ❌ absent du fichier .env ET de la configuration chargée.');
        });
    }

    public function test_signale_un_reglage_present_dans_env_mais_absent_de_la_configuration_chargee(): void
    {
        // Reproduit exactement le scénario qui a fait échouer
        // sauvegarde:github --verifier le 12/11/2026 chez Ruth : les trois
        // lignes bien présentes dans .env après déploiement, mais la
        // configuration encore en cache ne les avait pas prises en compte.
        config(self::CONFIG_VALIDE);
        config([
            'services.sauvegarde_github.cle' => null,
            'services.sauvegarde_github.token' => 'jeton-deja-charge',
            'services.sauvegarde_github.depot' => 'AfroliaElyas/afrolia-backoffice',
        ]);

        $this->avecFichierEnvDeTest("SAUVEGARDE_GITHUB_CLE=cle-reellement-dans-env\n", function () {
            $this->artisan('backblaze:diagnostiquer')
                ->expectsOutputToContain('SAUVEGARDE_GITHUB_CLE : ⚠️ présent dans le fichier .env, mais ABSENT de la configuration chargée')
                ->doesntExpectOutputToContain('cle-reellement-dans-env');
        });
    }

    public function test_signale_que_les_trois_reglages_sauvegarde_github_sont_corrects(): void
    {
        config(self::CONFIG_VALIDE);
        config([
            'services.sauvegarde_github.cle' => 'cle-de-test',
            'services.sauvegarde_github.token' => 'jeton-de-test',
            'services.sauvegarde_github.depot' => 'AfroliaElyas/afrolia-backoffice',
        ]);

        $this->avecFichierEnvDeTest(
            "SAUVEGARDE_GITHUB_CLE=cle-de-test\nSAUVEGARDE_GITHUB_TOKEN=jeton-de-test\nSAUVEGARDE_GITHUB_DEPOT=AfroliaElyas/afrolia-backoffice\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('SAUVEGARDE_GITHUB_CLE : ✅ présent dans le fichier .env et chargé par la configuration.')
                    ->expectsOutputToContain('SAUVEGARDE_GITHUB_TOKEN : ✅ présent dans le fichier .env et chargé par la configuration.')
                    ->expectsOutputToContain('SAUVEGARDE_GITHUB_DEPOT : ✅ présent dans le fichier .env et chargé par la configuration.');
            }
        );
    }

    public function test_signale_quand_le_fichier_env_differe_de_la_configuration_chargee_github(): void
    {
        // Ajouté le 13/11/2026 : après un « Save » (sans « Deploy ») dans
        // Forge, le fichier .env est déjà à jour mais la configuration en
        // cache ne l'est pas encore. Ce test vérifie que ce diagnostic
        // détecte et signale l'écart entre les deux, sans jamais afficher
        // les valeurs elles-mêmes.
        config(self::CONFIG_VALIDE);
        config([
            'services.sauvegarde_github.cle' => 'cle-de-test',
            'services.sauvegarde_github.token' => 'ancien-jeton-encore-en-cache',
            'services.sauvegarde_github.depot' => 'AfroliaElyas/afrolia-backoffice',
        ]);

        $this->avecFichierEnvDeTest(
            "SAUVEGARDE_GITHUB_CLE=cle-de-test\nSAUVEGARDE_GITHUB_TOKEN=nouveau-jeton-colle-apres-save\nSAUVEGARDE_GITHUB_DEPOT=AfroliaElyas/afrolia-backoffice\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('⚠️ La valeur du fichier .env diffère de celle actuellement chargée — un déploiement est nécessaire pour que le changement prenne effet.')
                    ->doesntExpectOutputToContain('ancien-jeton-encore-en-cache')
                    ->doesntExpectOutputToContain('nouveau-jeton-colle-apres-save');
            }
        );
    }

    public function test_detecte_un_espace_parasite_dans_le_jeton_github_sans_lafficher(): void
    {
        // Ajouté le 13/11/2026 : la première exécution réelle de
        // sauvegarde:github --verifier (via le cron) a échoué avec "HTTP
        // 401" lors de la création de la release GitHub — même famille
        // d'erreur ("identifiants refusés") que deux incidents Backblaze
        // déjà causés par un espace resté collé lors du copier-coller dans
        // Forge. Ce test vérifie que le même espace, sur le jeton GitHub,
        // est bien détecté, sans jamais afficher le jeton lui-même.
        //
        // avecFichierEnvDeTest est nécessaire ici (comme pour
        // test_signale_que_les_trois_reglages_sauvegarde_github_sont_corrects
        // ci-dessus) : sans fichier .env correspondant, la commande
        // rapporte "chargé par la configuration, mais absent du fichier
        // .env" plutôt que "✅ présent" — repéré par la CI de cette PR.
        config(self::CONFIG_VALIDE);
        config([
            'services.sauvegarde_github.cle' => 'cle-de-test',
            'services.sauvegarde_github.token' => 'jeton-secret-ne-doit-jamais-apparaitre ',
            'services.sauvegarde_github.depot' => 'AfroliaElyas/afrolia-backoffice',
        ]);

        $this->avecFichierEnvDeTest(
            "SAUVEGARDE_GITHUB_CLE=cle-de-test\nSAUVEGARDE_GITHUB_TOKEN=jeton-secret-ne-doit-jamais-apparaitre \nSAUVEGARDE_GITHUB_DEPOT=AfroliaElyas/afrolia-backoffice\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('SAUVEGARDE_GITHUB_TOKEN : ✅ présent dans le fichier .env et chargé par la configuration.')
                    ->expectsOutputToContain('ESPACE(S) PARASITE(S) EN DÉBUT/FIN DÉTECTÉ(S)')
                    ->expectsOutputToContain('empreinte SHA-256 : '.hash('sha256', 'jeton-secret-ne-doit-jamais-apparaitre '))
                    ->doesntExpectOutputToContain('jeton-secret-ne-doit-jamais-apparaitre ');
            }
        );
    }

    public function test_ne_signale_aucun_espace_parasite_sur_les_reglages_github_propres(): void
    {
        config(self::CONFIG_VALIDE);
        config([
            'services.sauvegarde_github.cle' => 'cle-de-test',
            'services.sauvegarde_github.token' => 'jeton-de-test',
            'services.sauvegarde_github.depot' => 'AfroliaElyas/afrolia-backoffice',
        ]);

        $this->avecFichierEnvDeTest(
            "SAUVEGARDE_GITHUB_CLE=cle-de-test\nSAUVEGARDE_GITHUB_TOKEN=jeton-de-test\nSAUVEGARDE_GITHUB_DEPOT=AfroliaElyas/afrolia-backoffice\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('SAUVEGARDE_GITHUB_CLE : ✅')
                    ->doesntExpectOutputToContain('ESPACE(S) PARASITE(S) EN DÉBUT/FIN DÉTECTÉ(S)');
            }
        );
    }

    public function test_naffiche_aucune_empreinte_pour_le_depot_github_car_pas_un_secret(): void
    {
        // SAUVEGARDE_GITHUB_DEPOT (propriétaire/nom de dépôt) n'a pas la
        // même sensibilité que la clé ou le jeton : pas d'empreinte à
        // calculer pour lui, seulement la détection d'espace parasite.
        config(self::CONFIG_VALIDE);
        config([
            'services.sauvegarde_github.cle' => 'cle-de-test',
            'services.sauvegarde_github.token' => 'jeton-de-test',
            'services.sauvegarde_github.depot' => 'AfroliaElyas/afrolia-backoffice',
        ]);

        $this->artisan('backblaze:diagnostiquer')
            ->doesntExpectOutputToContain('empreinte SHA-256 : '.hash('sha256', 'AfroliaElyas/afrolia-backoffice'));
    }

    /**
     * Même principe que avecJournalDeTest ci-dessus (ne touche jamais à un
     * vrai journal applicatif qui existerait déjà localement), appliqué à
     * storage/logs/laravel.log pour les tests de la section 9.
     */
    private function avecJournalApplicatifDeTest(string $contenu, \Closure $test): void
    {
        $chemin = storage_path('logs/laravel.log');
        $existaitDeja = is_file($chemin);
        $sauvegarde = $existaitDeja ? file_get_contents($chemin) : null;

        file_put_contents($chemin, $contenu);

        try {
            $test();
        } finally {
            if ($existaitDeja) {
                file_put_contents($chemin, $sauvegarde);
            } else {
                @unlink($chemin);
            }
        }
    }

    public function test_rapporte_aucune_erreur_quand_le_journal_applicatif_nen_contient_pas(): void
    {
        config(self::CONFIG_VALIDE);

        $this->avecJournalApplicatifDeTest(
            "[2026-11-12 00:20:01] production.INFO: Sauvegarde GitHub démarrée.\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('=== 9. Dernière erreur dans le journal applicatif (storage/logs/laravel.log) ===')
                    ->expectsOutputToContain('Aucune entrée ERROR/CRITICAL/EMERGENCY/ALERT trouvée');
            }
        );
    }

    public function test_rapporte_la_derniere_erreur_du_journal_applicatif_sans_la_trace_complete(): void
    {
        config(self::CONFIG_VALIDE);

        $this->avecJournalApplicatifDeTest(
            "[2026-11-12 00:20:01] production.INFO: Sauvegarde GitHub démarrée.\n"
            ."[2026-11-12 00:20:05] production.ERROR: Exception fictive de test : délai dépassé.\n"
            ."#0 /chemin/fictif/de/test.php(42): uneFonctionFictive()\n"
            ."#1 {main}\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('Dernière erreur consignée le 2026-11-12 00:20:05 UTC (niveau ERROR)')
                    ->expectsOutputToContain('Exception fictive de test : délai dépassé.')
                    ->doesntExpectOutputToContain('uneFonctionFictive');
            }
        );
    }

    /**
     * Même principe que avecJournalDeTest ci-dessus, appliqué à
     * storage/logs/scheduler-sauvegarde-github.log pour les tests de la
     * section 10.
     */
    private function avecJournalGithubDeTest(string $contenu, \Closure $test): void
    {
        $chemin = storage_path('logs/scheduler-sauvegarde-github.log');
        $existaitDeja = is_file($chemin);
        $sauvegarde = $existaitDeja ? file_get_contents($chemin) : null;

        file_put_contents($chemin, $contenu);

        try {
            $test();
        } finally {
            if ($existaitDeja) {
                file_put_contents($chemin, $sauvegarde);
            } else {
                @unlink($chemin);
            }
        }
    }

    public function test_rapporte_lorsque_aucun_journal_de_sauvegarde_github_nest_trouve(): void
    {
        config(self::CONFIG_VALIDE);
        $chemin = storage_path('logs/scheduler-sauvegarde-github.log');
        if (is_file($chemin)) {
            $this->markTestSkipped('Un vrai journal existe déjà à cet emplacement local : test sauté pour ne pas le perturber.');
        }

        $this->artisan('backblaze:diagnostiquer')
            ->expectsOutputToContain('=== 10. État de la dernière sauvegarde GitHub (+ vérification de restauration) ===')
            ->expectsOutputToContain('Aucun journal trouvé à storage/logs/scheduler-sauvegarde-github.log');
    }

    public function test_rapporte_un_envoi_et_une_verification_reussis_depuis_le_journal_github(): void
    {
        config(self::CONFIG_VALIDE);

        $this->avecJournalGithubDeTest(
            "Sauvegarde GitHub envoyée : release 'sauvegarde-20261112_002003' sur AfroliaElyas/afrolia-backoffice.\n"
            ."Base restaurée dans un environnement isolé : 12 table(s), 340 ligne(s) au total.\n"
            ."✅ Toutes les tables de production sont présentes dans la sauvegarde restaurée.\n"
            ."Photos restaurées dans un environnement isolé : 5 fichier(s), 12345 octet(s) au total.\n"
            ."✅ Nombre de fichiers et taille totale identiques à la production.\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('Dernier envoi réussi : ✅ confirmé (release « sauvegarde-20261112_002003 »).')
                    ->expectsOutputToContain('Vérification de restauration (--verifier), base   : ✅ dernière vérification trouvée : réussie.')
                    ->expectsOutputToContain('Vérification de restauration (--verifier), photos : ✅ dernière vérification trouvée : réussie.');
            }
        );
    }

    public function test_signale_une_verification_github_absente_ou_en_echec(): void
    {
        config(self::CONFIG_VALIDE);

        $this->avecJournalGithubDeTest(
            "ÉCHEC : mysqldump a échoué — erreur fictive de test.\n",
            function () {
                $this->artisan('backblaze:diagnostiquer')
                    ->expectsOutputToContain('Dernier envoi réussi : ⚠️ aucun envoi réussi trouvé')
                    ->expectsOutputToContain('Vérification de restauration (--verifier), base   : ⚠️ aucune vérification réussie trouvée')
                    ->expectsOutputToContain('Vérification de restauration (--verifier), photos : ⚠️ aucune vérification réussie trouvée')
                    ->expectsOutputToContain('ÉCHEC : mysqldump a échoué — erreur fictive de test.');
            }
        );
    }
}
