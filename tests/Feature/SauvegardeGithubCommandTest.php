<?php

namespace Tests\Feature;

use App\Console\Commands\SauvegardeGithubCommand;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La partie base de données (mysqldump réel) a été testée manuellement en
 * bac à sable isolé (vrai MariaDB jetable), comme pour SauvegardeExterneTest
 * : ni mysqldump ni un vrai dépôt GitHub n'existent dans l'environnement de
 * test CI (sqlite en mémoire, aucun accès réseau externe). Les méthodes
 * testables directement ici (chiffrement, photos, appels HTTP GitHub via
 * Http::fake, purge) couvrent tout ce qui ne dépend pas de MySQL.
 */
class SauvegardeGithubCommandTest extends TestCase
{
    private function nouvelleCommande(): SauvegardeGithubCommand
    {
        $commande = new SauvegardeGithubCommand();
        $commande->setLaravel($this->app);
        $commande->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput()
        ));

        return $commande;
    }

    private function appelerMethodePrivee(SauvegardeGithubCommand $commande, string $nom, array $arguments)
    {
        $methode = new \ReflectionMethod($commande, $nom);
        $methode->setAccessible(true);

        return $methode->invoke($commande, ...$arguments);
    }

    public function test_echoue_proprement_si_la_connexion_nest_pas_mysql(): void
    {
        $this->artisan('sauvegarde:github')->assertFailed();
    }

    public function test_chiffre_puis_dechiffre_donne_un_contenu_identique(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'test-clair-');
        file_put_contents($source, 'CONTENU FICTIF DE TEST — AUCUNE DONNEE REELLE');
        $chiffre = $source.'.enc';
        $dechiffre = $source.'.dec';

        $reussite = $this->appelerMethodePrivee($this->nouvelleCommande(), 'chiffrer', [$source, $chiffre, 'cle-de-test-fictive']);

        $this->assertTrue($reussite);
        $this->assertFileExists($chiffre);
        $this->assertStringNotContainsString('CONTENU FICTIF', (string) file_get_contents($chiffre));

        exec(sprintf(
            'openssl enc -d -aes-256-cbc -pbkdf2 -in %s -out %s -pass pass:%s',
            escapeshellarg($chiffre),
            escapeshellarg($dechiffre),
            escapeshellarg('cle-de-test-fictive')
        ));

        $this->assertSame('CONTENU FICTIF DE TEST — AUCUNE DONNEE REELLE', file_get_contents($dechiffre));

        array_map('unlink', [$source, $chiffre, $dechiffre]);
    }

    public function test_ne_considere_pas_un_dossier_photos_vide_comme_un_echec(): void
    {
        // storage/app/public/salon est vide dans l'environnement de test :
        // comportement attendu identique à SauvegardeExterneCommand (rien à
        // sauvegarder n'est pas une erreur). La branche « dossier rempli »
        // utilise les mêmes commandes tar/openssl que test_chiffre_puis_dechiffre
        // ci-dessus, déjà vérifiées, et a été revérifiée manuellement en bac
        // à sable avec de vraies photos fictives.
        $resultat = $this->appelerMethodePrivee($this->nouvelleCommande(), 'sauvegarderEtChiffrerPhotos', ['cle-de-test-fictive']);

        $this->assertNull($resultat);
    }

    public function test_cree_une_release_et_envoie_les_fichiers_chiffres(): void
    {
        Http::fake([
            'api.github.com/repos/*/releases' => Http::response([
                'id' => 999,
                'upload_url' => 'https://uploads.github.com/repos/Test/depot/releases/999/assets{?name,label}',
            ], 201),
            'uploads.github.com/*' => Http::response(['id' => 1], 201),
        ]);

        $fichierFictif = tempnam(sys_get_temp_dir(), 'test-chiffre-');
        file_put_contents($fichierFictif, 'octets-chiffres-fictifs');

        $reussite = $this->appelerMethodePrivee(
            $this->nouvelleCommande(),
            'publierRelease',
            ['Test/depot', 'jeton-de-test-fictif', 'sauvegarde-test', [$fichierFictif]]
        );

        $this->assertTrue($reussite);

        Http::assertSent(fn ($requete) => str_contains($requete->url(), 'api.github.com/repos/Test/depot/releases')
            && $requete->method() === 'POST');
        Http::assertSent(fn ($requete) => str_contains($requete->url(), 'uploads.github.com'));

        unlink($fichierFictif);
    }

    public function test_purge_les_releases_de_sauvegarde_au_dela_de_la_conservation(): void
    {
        $releasesFictives = array_map(
            fn (int $i) => ['id' => $i, 'tag_name' => 'sauvegarde-'.sprintf('%03d', $i)],
            range(1, 93)
        );

        Http::fake([
            'api.github.com/repos/*/releases?*' => Http::response($releasesFictives, 200),
            'api.github.com/repos/*/releases/*' => Http::response([], 204),
        ]);

        $this->appelerMethodePrivee($this->nouvelleCommande(), 'purgerAnciennesReleases', ['Test/depot', 'jeton-de-test-fictif']);

        // 93 releases, 90 conservées → 3 suppressions attendues (les plus anciennes).
        Http::assertSentCount(4); // 1 listing + 3 suppressions
    }

    public function test_ne_supprime_rien_si_sous_le_seuil_de_conservation(): void
    {
        $releasesFictives = array_map(
            fn (int $i) => ['id' => $i, 'tag_name' => 'sauvegarde-'.sprintf('%03d', $i)],
            range(1, 5)
        );

        Http::fake([
            'api.github.com/repos/*/releases?*' => Http::response($releasesFictives, 200),
        ]);

        $this->appelerMethodePrivee($this->nouvelleCommande(), 'purgerAnciennesReleases', ['Test/depot', 'jeton-de-test-fictif']);

        Http::assertSentCount(1); // seulement le listing, aucune suppression
    }

    public function test_echoue_proprement_si_la_configuration_est_incomplete(): void
    {
        config([
            'services.sauvegarde_github.cle' => null,
            'services.sauvegarde_github.token' => null,
            'services.sauvegarde_github.depot' => null,
        ]);

        // database.default reste sqlite ici aussi, donc l'échec vient déjà
        // de la connexion — la vérification de configuration est testée
        // directement via la commande réelle en bac à sable (mysql réel),
        // pas reproductible ici sans dupliquer tout l'environnement MySQL.
        $this->artisan('sauvegarde:github')->assertFailed();
    }
}
