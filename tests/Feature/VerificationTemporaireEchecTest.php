<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Fichier temporaire : sert uniquement à vérifier que la protection de
 * branche GitHub bloque bien la fusion quand un test échoue. Sera
 * supprimé avec la branche une fois la vérification faite.
 */
class VerificationTemporaireEchecTest extends TestCase
{
    public function test_echoue_volontairement(): void
    {
        $this->assertTrue(false, 'Échec volontaire pour vérifier la protection de branche.');
    }
}
