<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JekoTesterCommandTest extends TestCase
{
    private function configurerJeko(?string $webhookSecret = 'secret_test'): void
    {
        config([
            'services.jeko.api_key' => 'cle_test',
            'services.jeko.api_key_id' => 'cle_id_test',
            'services.jeko.store_id' => 'magasin_test',
            'services.jeko.webhook_secret' => $webhookSecret,
            'services.jeko.base_url' => 'https://api.jeko.africa',
        ]);
    }

    public function test_echoue_proprement_si_les_identifiants_sont_vides(): void
    {
        config(['services.jeko.api_key' => null, 'services.jeko.api_key_id' => null, 'services.jeko.store_id' => null]);

        $this->artisan('jeko:tester')
            ->assertFailed()
            ->expectsOutputToContain('rien à tester');
    }

    public function test_reussit_et_confirme_le_webhook_si_tout_est_configure(): void
    {
        $this->configurerJeko();
        Http::fake([
            'api.jeko.africa/partner_api/stores/*/balance' => Http::response(['balance' => 50000], 200),
        ]);

        $this->artisan('jeko:tester')
            ->assertSuccessful()
            ->expectsOutputToContain('Connexion à Jèko réussie')
            ->expectsOutputToContain('configuration Jèko est complète');
    }

    public function test_echoue_proprement_si_jeko_refuse_les_identifiants(): void
    {
        $this->configurerJeko();
        Http::fake([
            'api.jeko.africa/partner_api/stores/*/balance' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->artisan('jeko:tester')
            ->assertFailed()
            ->expectsOutputToContain('Échec de connexion à Jèko');
    }

    public function test_avertit_si_le_secret_de_webhook_manque(): void
    {
        $this->configurerJeko(webhookSecret: null);
        Http::fake([
            'api.jeko.africa/partner_api/stores/*/balance' => Http::response(['balance' => 50000], 200),
        ]);

        $this->artisan('jeko:tester')
            ->assertFailed()
            ->expectsOutputToContain('Connexion à Jèko réussie')
            ->expectsOutputToContain('JEKO_WEBHOOK_SECRET est vide');
    }

    /**
     * Pointe temporairement l'application vers un fichier .env de test
     * (jamais le vrai .env du projet), le temps d'un test. Même helper que
     * DiagnostiquerBackblazeTest, pour la même raison : Dotenv::parse() ne
     * touche ni getenv() ni $_ENV, donc aucun effet de bord entre les tests.
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
        $this->avecFichierEnvDeTest("APP_NAME=Afrolia\n", function () {
            $this->configurerJeko();
            Http::fake(['api.jeko.africa/partner_api/stores/*/balance' => Http::response(['balance' => 0], 200)]);

            $this->artisan('jeko:tester')
                ->expectsOutputToContain('JEKO_API_KEY_ID : absent du fichier .env')
                ->expectsOutputToContain('JEKO_STORE_ID : absent du fichier .env');
        });
    }

    public function test_rapporte_une_valeur_identique_entre_config_et_fichier_env_reel(): void
    {
        $this->avecFichierEnvDeTest("JEKO_API_KEY_ID=cle_id_test\n", function () {
            $this->configurerJeko(); // 'cle_id_test', cohérent avec le fichier ci-dessus.
            Http::fake(['api.jeko.africa/partner_api/stores/*/balance' => Http::response(['balance' => 0], 200)]);

            $this->artisan('jeko:tester')
                ->expectsOutputToContain('JEKO_API_KEY_ID : ✅ identique');
        });
    }

    public function test_detecte_un_ecart_et_utilise_la_valeur_du_fichier_pour_authentifier(): void
    {
        // Scénario exact du refus persistant signalé le 08/11/2026 : Ruth a
        // vérifié et resauvegardé JEKO_API_KEY_ID dans Forge, mais la
        // configuration chargée par ce processus (potentiellement en cache,
        // voir le constat déjà fait pour Backblaze) garde encore l'ancienne
        // valeur. Le diagnostic doit utiliser celle du fichier .env réel
        // pour le VRAI appel à Jèko, pas celle restée en configuration.
        $this->avecFichierEnvDeTest("JEKO_API_KEY_ID=identifiant_reellement_dans_env\n", function () {
            $this->configurerJeko(); // config() garde 'cle_id_test', volontairement différent.
            Http::fake([
                'api.jeko.africa/partner_api/stores/*/balance' => function ($request) {
                    return $request->header('X-API-KEY-ID') === ['identifiant_reellement_dans_env']
                        ? Http::response(['balance' => 50000], 200)
                        : Http::response(['id' => 'unauthorized', 'extras' => 'Invalid API key ID'], 401);
                },
            ]);

            $this->artisan('jeko:tester')
                ->expectsOutputToContain('JEKO_API_KEY_ID : ⚠️ DIFFÉRENT')
                ->expectsOutputToContain('Connexion à Jèko réussie')
                ->assertSuccessful();
        });
    }

    public function test_signale_un_caractere_non_imprimable_sans_jamais_l_afficher(): void
    {
        // Un espace ASCII parasite en bord de valeur est déjà neutralisé
        // silencieusement par phpdotenv lui-même (vérifié : KEY=abc123<espace>
        // devient "abc123"), donc indétectable à ce stade et sans
        // conséquence. Un caractère invisible NON-ASCII (espace
        // insécable U+00A0, par exemple issu d'une correction automatique
        // au collage sur iPad — déjà vu ce jour avec le script SSH corrompu)
        // n'est en revanche jamais normalisé par le parseur : il doit être
        // signalé, sans jamais être affiché en clair.
        $this->configurerJeko();
        config(['services.jeko.api_key_id' => "cle_id\u{00A0}test"]);
        Http::fake(['api.jeko.africa/partner_api/stores/*/balance' => Http::response(['id' => 'unauthorized'], 401)]);

        // Une seule assertion de substring par ligne de sortie attendue : le
        // mock de test de Laravel attribue chaque ligne écrite à la PREMIÈRE
        // expectation qui la reconnaît, donc une vérification "JEKO_API_KEY_ID"
        // en plus aurait capté cette même ligne avant celle-ci et aurait fait
        // échouer la suivante à tort, même si le texte y est bien présent.
        $this->artisan('jeko:tester')
            ->expectsOutputToContain('CARACTÈRE NON IMPRIMABLE')
            ->doesntExpectOutputToContain("cle_id\u{00A0}test")
            ->doesntExpectOutputToContain('cle_id_test'); // la partie avant le caractère invisible non plus.
    }

    public function test_affiche_les_en_tetes_de_reponse_sans_jamais_afficher_les_secrets(): void
    {
        $this->configurerJeko();
        Http::fake([
            'api.jeko.africa/partner_api/stores/*/balance' => Http::response(
                ['id' => 'unauthorized', 'message' => 'Unauthorized', 'extras' => 'Invalid API key ID'],
                401,
                ['X-Request-Id' => 'req_test_123']
            ),
        ]);

        $this->artisan('jeko:tester')
            ->assertFailed()
            ->expectsOutputToContain('En-têtes envoyés (noms seulement')
            ->expectsOutputToContain('Code HTTP reçu : 401')
            ->expectsOutputToContain('X-Request-Id : req_test_123')
            ->doesntExpectOutputToContain('cle_test')
            ->doesntExpectOutputToContain('cle_id_test');
    }
}
