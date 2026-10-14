<?php
namespace Tests\Feature;
use Tests\TestCase;

class LangSwitchTest extends TestCase
{
    public function test_defaut_francais(): void
    {
        $r = $this->get('/');
        $r->assertStatus(200);
        $r->assertSee('Trouvez et réservez votre coiffeuse, simplement', false);
    }

    public function test_bascule_anglais(): void
    {
        $r = $this->get('/?lang=en');
        $r->assertStatus(200);
        $r->assertSee('Find and book your hairdresser, simply', false);
    }

    public function test_session_memorise_la_langue(): void
    {
        $this->get('/?lang=en');
        $r = $this->get('/');
        $r->assertSee('Find and book your hairdresser, simply', false);
    }

    public function test_langue_invalide_ignoree(): void
    {
        $r = $this->get('/?lang=xx');
        $r->assertStatus(200);
        $r->assertSee('Trouvez et réservez votre coiffeuse, simplement', false);
    }
}
