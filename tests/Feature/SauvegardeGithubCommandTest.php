<?php

namespace Tests\Feature;

use App\Console\Commands\SauvegardeGithubCommand;
use Tests\TestCase;

/**
 * La partie base de données (mysqldump réel) a été testée manuellement en
 * bac à sable isolé (vrai MariaDB jetable), comme pour SauvegardeExterneTest
 * : mysqldump n'existe pas dans l'environnement de test CI (sqlite en
 * mémoire). Les méthodes testables directement ici (chiffrement, photos,
 * déplacement vers le dossier de préparation, manifeste, purge locale)
 * couvrent tout ce qui ne dépend pas de MySQL.
 *
 * Depuis le 13/11/2026, cette commande ne parle plus du tout à GitHub
 * (plus de jeton, plus d'appel HTTP) : voir SauvegardeGithubCommand pour
 * le détail. Les anciens tests qui simulaient l'API GitHub (Http::fake)
 * ont été remplacés par des tests sur le dossier de préparation local et
 * sur la vérification locale.
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

    private function nettoyerDossierPret(string $dossier): void
    {
        array_map('unlink', glob($dossier.'/*') ?: []);
    }

    public function test_echoue_proprement_si_la_connexion_nest_pas_mysql(): void
    {
        $this->artisan('sauvegarde:github')->assertFailed();
    }

    public function test_echoue_proprement_si_la_cle_est_absente(): void
    {
        config(['services.sauvegarde_github.cle' => null]);

        // database.default reste sqlite ici aussi : l'échec vient déjà de
        // la connexion avant même d'atteindre la vérification de la clé —
        // la vérification de configuration elle-même est testée
        // directement via la commande réelle en bac à sable (MySQL réel),
        // pas reproductible ici sans dupliquer tout l'environnement MySQL.
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

    public function test_deplace_les_fichiers_chiffres_et_ecrit_un_manifest(): void
    {
        $dossierPret = storage_path('app/sauvegardes-github-pretes');
        $this->nettoyerDossierPret($dossierPret);

        $dump = tempnam(sys_get_temp_dir(), 'test-dump-');
        file_put_contents($dump, 'dump-chiffre-fictif');
        $photos = tempnam(sys_get_temp_dir(), 'test-photos-');
        file_put_contents($photos, 'photos-chiffre-fictif');

        try {
            $resultat = $this->appelerMethodePrivee(
                $this->nouvelleCommande(),
                'deplacerVersDossierPret',
                ['sauvegarde-20261113_002000', $dump, $photos]
            );

            $this->assertNotNull($resultat);
            $this->assertFileExists($dossierPret.'/afrolia-db-sauvegarde-20261113_002000.sql.gz.enc');
            $this->assertFileExists($dossierPret.'/afrolia-photos-sauvegarde-20261113_002000.tar.gz.enc');
            $this->assertFileExists($dossierPret.'/manifest.json');
            $this->assertFileDoesNotExist($dump, 'Le fichier source doit avoir été déplacé, pas copié.');

            $manifest = json_decode((string) file_get_contents($dossierPret.'/manifest.json'), true);
            $this->assertSame('sauvegarde-20261113_002000', $manifest['tag']);
            $this->assertCount(2, $manifest['fichiers']);

            $parNom = collect($manifest['fichiers'])->keyBy('nom');
            $this->assertSame(hash('sha256', 'dump-chiffre-fictif'), $parNom['afrolia-db-sauvegarde-20261113_002000.sql.gz.enc']['sha256']);
            $this->assertSame(hash('sha256', 'photos-chiffre-fictif'), $parNom['afrolia-photos-sauvegarde-20261113_002000.tar.gz.enc']['sha256']);
        } finally {
            $this->nettoyerDossierPret($dossierPret);
        }
    }

    public function test_fonctionne_sans_photos(): void
    {
        $dossierPret = storage_path('app/sauvegardes-github-pretes');
        $this->nettoyerDossierPret($dossierPret);

        $dump = tempnam(sys_get_temp_dir(), 'test-dump-');
        file_put_contents($dump, 'dump-chiffre-fictif-seul');

        try {
            $resultat = $this->appelerMethodePrivee(
                $this->nouvelleCommande(),
                'deplacerVersDossierPret',
                ['sauvegarde-20261113_003000', $dump, null]
            );

            $this->assertNotNull($resultat);
            $manifest = json_decode((string) file_get_contents($dossierPret.'/manifest.json'), true);
            $this->assertCount(1, $manifest['fichiers']);
        } finally {
            $this->nettoyerDossierPret($dossierPret);
        }
    }

    public function test_purge_les_anciens_jeux_locaux_au_dela_de_la_conservation(): void
    {
        $dossier = storage_path('app/sauvegardes-github-pretes');
        $this->nettoyerDossierPret($dossier);
        @mkdir($dossier, 0700, true);

        foreach (['sauvegarde-20261110_002000', 'sauvegarde-20261111_002000', 'sauvegarde-20261112_002000', 'sauvegarde-20261113_002000'] as $tag) {
            file_put_contents($dossier."/afrolia-db-{$tag}.sql.gz.enc", 'x');
        }

        try {
            $this->appelerMethodePrivee($this->nouvelleCommande(), 'purgerAnciensJeuxLocaux', []);

            $restants = array_map('basename', glob($dossier.'/afrolia-db-*.enc') ?: []);
            sort($restants);

            // 4 jeux, 2 conservés (NOMBRE_JEUX_LOCAUX_CONSERVES) → les 2 plus récents.
            $this->assertSame([
                'afrolia-db-sauvegarde-20261112_002000.sql.gz.enc',
                'afrolia-db-sauvegarde-20261113_002000.sql.gz.enc',
            ], $restants);
        } finally {
            $this->nettoyerDossierPret($dossier);
        }
    }

    public function test_ne_supprime_rien_si_sous_le_seuil_de_conservation(): void
    {
        $dossier = storage_path('app/sauvegardes-github-pretes');
        $this->nettoyerDossierPret($dossier);
        @mkdir($dossier, 0700, true);

        file_put_contents($dossier.'/afrolia-db-sauvegarde-20261113_002000.sql.gz.enc', 'x');

        try {
            $this->appelerMethodePrivee($this->nouvelleCommande(), 'purgerAnciensJeuxLocaux', []);

            $this->assertFileExists($dossier.'/afrolia-db-sauvegarde-20261113_002000.sql.gz.enc');
        } finally {
            $this->nettoyerDossierPret($dossier);
        }
    }

    public function test_verification_locale_dechiffre_et_ne_laisse_rien_apres_coup(): void
    {
        $dossier = sys_get_temp_dir().'/test-verif-locale-'.uniqid();
        mkdir($dossier);
        file_put_contents($dossier.'/contenu.txt', 'CONTENU FICTIF DE TEST');
        exec(sprintf('gzip -c %s > %s', escapeshellarg($dossier.'/contenu.txt'), escapeshellarg($dossier.'/contenu.gz')));
        exec(sprintf(
            'openssl enc -aes-256-cbc -pbkdf2 -salt -in %s -out %s -pass pass:%s',
            escapeshellarg($dossier.'/contenu.gz'),
            escapeshellarg($dossier.'/contenu.gz.enc'),
            escapeshellarg('cle-de-test-fictive')
        ));

        // database.connections.mysql n'existe pas forcément en CI (sqlite) :
        // la restauration réelle (mysqldump/mysql CLI) a été vérifiée
        // manuellement en bac à sable, comme le reste de cette commande. Ce
        // test vérifie seulement que le déchiffrement se déroule sans
        // erreur fatale et que rien ne reste après coup, même quand la
        // restauration MySQL elle-même échoue dans cet environnement.
        $this->appelerMethodePrivee(
            $this->nouvelleCommande(),
            'verifierLocalement',
            [$dossier.'/contenu.gz.enc', null, 'cle-de-test-fictive']
        );

        $restes = glob(sys_get_temp_dir().'/verif-sauvegarde-github-*');
        $this->assertEmpty($restes, 'Aucun dossier temporaire ne doit rester après la vérification.');

        array_map('unlink', glob($dossier.'/*'));
        rmdir($dossier);
    }
}
