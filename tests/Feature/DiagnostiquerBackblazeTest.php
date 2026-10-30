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
