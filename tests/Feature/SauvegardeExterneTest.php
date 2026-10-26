<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La commande elle-même (mysqldump réel + envoi vers Backblaze B2) a été
 * testée manuellement dans un environnement isolé — vrai MariaDB jetable
 * et vrai serveur S3 compatible local (moto) — car ni l'un ni l'autre
 * n'existe dans l'environnement de test CI (sqlite en mémoire, aucun
 * accès réseau externe). Ce test couvre ce qui PEUT tourner ici : le
 * refus propre quand la connexion n'est pas MySQL, exactement le cas de
 * figure de cet environnement de test.
 */
class SauvegardeExterneTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuse_proprement_si_la_connexion_nest_pas_mysql(): void
    {
        $this->artisan('sauvegarde:externe')
            ->assertFailed();
    }
}
