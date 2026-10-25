<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Fichier temporaire : vérifie que la protection de branche bloque bien
 * la fusion quand un test échoue. Supprimé avec la branche juste après.
 */
class VerificationTemporaireEchec3Test extends TestCase
{
    public function test_echoue_volontairement(): void
    {
        $this->assertTrue(false, 'Échec volontaire pour vérifier la protection de branche.');
    }
}
