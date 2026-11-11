<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenererCleSauvegardeGithubCommandTest extends TestCase
{
    public function test_affiche_une_cle_base64_de_32_octets(): void
    {
        Artisan::call('sauvegarde:generer-cle-github');
        $texte = Artisan::output();

        preg_match('/^[A-Za-z0-9+\/]{43}=$/m', $texte, $correspondances);
        $this->assertNotEmpty($correspondances, "La sortie doit contenir une clé base64 de 32 octets (44 caractères) : {$texte}");

        $cle = $correspondances[0];
        $this->assertSame(32, strlen(base64_decode($cle)));
    }

    public function test_deux_executions_produisent_des_cles_differentes(): void
    {
        Artisan::call('sauvegarde:generer-cle-github');
        $premiere = Artisan::output();

        Artisan::call('sauvegarde:generer-cle-github');
        $seconde = Artisan::output();

        preg_match('/^[A-Za-z0-9+\/]{43}=$/m', $premiere, $m1);
        preg_match('/^[A-Za-z0-9+\/]{43}=$/m', $seconde, $m2);

        $this->assertNotEmpty($m1);
        $this->assertNotEmpty($m2);
        $this->assertNotSame($m1[0], $m2[0], 'Deux exécutions ne doivent jamais produire la même clé.');
    }

    public function test_n_ecrit_aucun_fichier_et_n_avertit_de_ne_rien_coller_ailleurs(): void
    {
        Artisan::call('sauvegarde:generer-cle-github');
        $texte = Artisan::output();

        $this->assertStringContainsString('jamais affichée après cette commande', $texte);
        $this->assertStringContainsString('Ne la colle nulle part ailleurs', $texte);
    }
}
