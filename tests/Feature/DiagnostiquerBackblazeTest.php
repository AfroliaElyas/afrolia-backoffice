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
}
