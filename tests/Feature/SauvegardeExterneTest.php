<?php

namespace Tests\Feature;

use App\Console\Commands\SauvegardeExterneCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La partie base de données (mysqldump réel + envoi vers Backblaze B2) a
 * été testée manuellement dans un environnement isolé — vrai MariaDB
 * jetable et vrai serveur S3 compatible local (moto) — car ni l'un ni
 * l'autre n'existe dans l'environnement de test CI (sqlite en mémoire,
 * aucun accès réseau externe). La partie sauvegarde des photos (ajoutée
 * le 29/10/2026) ne dépend elle que du système de fichiers local et de
 * Storage::fake, donc testable directement ici.
 */
class SauvegardeExterneTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuse_proprement_si_la_connexion_nest_pas_mysql(): void
    {
        $this->artisan('sauvegarde:externe')
            ->assertFailed();
    }

    private function appellerArchiverEtEnvoyerDossier(string $dossier, string $prefixe): bool
    {
        $commande = new SauvegardeExterneCommand();
        $commande->setLaravel($this->app);
        $commande->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput()
        ));
        $methode = new \ReflectionMethod($commande, 'archiverEtEnvoyerDossier');
        $methode->setAccessible(true);

        return $methode->invoke($commande, $dossier, $prefixe);
    }

    public function test_archive_et_envoie_un_dossier_de_photos_non_vide(): void
    {
        Storage::fake('backblaze');

        $dossier = sys_get_temp_dir().'/test-photos-'.uniqid();
        mkdir($dossier);
        file_put_contents($dossier.'/photo1.jpg', 'contenu-test');

        $reussite = $this->appellerArchiverEtEnvoyerDossier($dossier, 'test-photos-');

        $this->assertTrue($reussite);
        $fichiers = Storage::disk('backblaze')->files();
        $this->assertCount(1, $fichiers);
        $this->assertStringStartsWith('test-photos-', $fichiers[0]);
        $this->assertStringEndsWith('.tar.gz', $fichiers[0]);

        array_map('unlink', glob($dossier.'/*'));
        rmdir($dossier);
    }

    public function test_ne_fait_rien_si_le_dossier_de_photos_est_absent_ou_vide(): void
    {
        Storage::fake('backblaze');

        $reussite = $this->appellerArchiverEtEnvoyerDossier(sys_get_temp_dir().'/dossier-inexistant-'.uniqid(), 'test-photos-');

        $this->assertTrue($reussite);
        $this->assertCount(0, Storage::disk('backblaze')->files());
    }

    public function test_purge_les_anciennes_sauvegardes_photos_au_dela_de_la_conservation(): void
    {
        Storage::fake('backblaze');
        $disque = Storage::disk('backblaze');

        // 14 sauvegardes déjà présentes avec une date manifestement plus
        // ancienne que "maintenant" (évite toute ambiguïté de tri avec le
        // nouvel envoi, dont le nom utilise la date réelle du jour) : avec
        // la conservation à 14, la plus ancienne de ce lot doit disparaître
        // une fois le 15ᵉ fichier (le nouvel envoi) arrivé.
        for ($i = 1; $i <= 14; $i++) {
            $disque->put(sprintf('test-photos-19990101_%06d.tar.gz', $i), 'x');
        }

        $dossier = sys_get_temp_dir().'/test-photos-'.uniqid();
        mkdir($dossier);
        file_put_contents($dossier.'/photo1.jpg', 'contenu-test');

        $this->appellerArchiverEtEnvoyerDossier($dossier, 'test-photos-');

        $this->assertCount(14, $disque->files());
        $this->assertFalse($disque->exists('test-photos-19990101_000001.tar.gz'));
        $this->assertTrue($disque->exists('test-photos-19990101_000002.tar.gz'));
        $this->assertTrue($disque->exists('test-photos-19990101_000014.tar.gz'));

        array_map('unlink', glob($dossier.'/*'));
        rmdir($dossier);
    }

    public function test_la_purge_des_photos_ne_compte_pas_les_archives_heritage(): void
    {
        Storage::fake('backblaze');
        $disque = Storage::disk('backblaze');

        // 14 archives « heritage » : elles partagent le début du nom
        // (« test-photos-heritage-… ») mais forment une série distincte,
        // qui ne doit jamais être comptée ni purgée avec les photos.
        for ($i = 1; $i <= 14; $i++) {
            $disque->put(sprintf('test-photos-heritage-19990101_%06d.tar.gz', $i), 'x');
        }
        $disque->put('test-photos-19990101_000001.tar.gz', 'x');

        $dossier = sys_get_temp_dir().'/test-photos-'.uniqid();
        mkdir($dossier);
        file_put_contents($dossier.'/photo1.jpg', 'contenu-test');

        $this->appellerArchiverEtEnvoyerDossier($dossier, 'test-photos-');

        // 14 heritage + l'ancienne + la nouvelle : rien n'a été supprimé.
        $this->assertCount(16, $disque->files());
        $this->assertTrue($disque->exists('test-photos-19990101_000001.tar.gz'));
        $this->assertTrue($disque->exists('test-photos-heritage-19990101_000001.tar.gz'));

        array_map('unlink', glob($dossier.'/*'));
        rmdir($dossier);
    }

    public function test_ne_laisse_aucun_fichier_temporaire_apres_l_archive_des_photos(): void
    {
        Storage::fake('backblaze');

        $avant = glob(sys_get_temp_dir().'/sauvegarde-afrolia-photos-*') ?: [];

        $dossier = sys_get_temp_dir().'/test-photos-'.uniqid();
        mkdir($dossier);
        file_put_contents($dossier.'/photo1.jpg', 'contenu-test');

        $this->appellerArchiverEtEnvoyerDossier($dossier, 'test-photos-');

        $apres = glob(sys_get_temp_dir().'/sauvegarde-afrolia-photos-*') ?: [];
        $this->assertSame([], array_values(array_diff($apres, $avant)));

        array_map('unlink', glob($dossier.'/*'));
        rmdir($dossier);
    }
}
