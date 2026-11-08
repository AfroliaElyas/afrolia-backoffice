<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Diagnostique une erreur « Signature validation failed » (ou toute autre
 * erreur de connexion) sur le disque Backblaze B2, sans jamais afficher les
 * secrets eux-mêmes — seulement leur longueur et des indices (espaces
 * parasites, région/endpoint incohérents) qui trahissent une faute de
 * transcription lors de la saisie dans Forge (demandé le 30/10/2026).
 *
 * La section 3 écrit un unique petit fichier de diagnostic (préfixé
 * "__diagnostic_afrolia_", horodaté pour être unique), le liste, puis le
 * supprime aussitôt — exactement les trois opérations que sauvegarde:externe
 * utilise réellement (écrire, lister pour la purge, supprimer les anciennes
 * sauvegardes). Jamais de lecture du contenu d'un fichier existant, jamais de
 * donnée réelle touchée. Un test en lecture seule (Storage::exists) avait été
 * tenté d'abord, mais échouait par 403 sur une clé pourtant valide dont les
 * capacités Backblaze ne couvrent que l'écriture — exactement ce que la
 * sauvegarde utilise, donc sans rapport avec un vrai problème (confirmé en
 * production le 01/11/2026).
 */
class DiagnostiquerBackblazeCommand extends Command
{
    protected $signature = 'backblaze:diagnostiquer';

    protected $description = "Diagnostique la connexion Backblaze B2 sans afficher les secrets (longueur, espaces parasites, cohérence région/endpoint, puis test de connexion réel)";

    /**
     * Nom affiché (celui de la variable .env, pour que le message parle à
     * Ruth) => clé de config correspondante dans config/filesystems.php.
     * Lire via config() plutôt que env() directement : identique en
     * production (la config n'évalue chaque env() qu'une fois au
     * chargement), mais permet de simuler des valeurs dans les tests.
     */
    private const CLES_CONFIG = [
        'BACKBLAZE_KEY_ID' => 'filesystems.disks.backblaze.key',
        'BACKBLAZE_APPLICATION_KEY' => 'filesystems.disks.backblaze.secret',
        'BACKBLAZE_REGION' => 'filesystems.disks.backblaze.region',
        'BACKBLAZE_BUCKET' => 'filesystems.disks.backblaze.bucket',
        'BACKBLAZE_ENDPOINT' => 'filesystems.disks.backblaze.endpoint',
    ];

    public function handle(): int
    {
        // Ajouté le 06/11/2026 après deux régénérations de clé Backblaze
        // sans effet sur l'erreur (toujours "AccessDenied" exact, jamais
        // "SignatureDoesNotMatch" ni "InvalidAccessKeyId") : si la config
        // était mise en cache (`php artisan config:cache`) à un moment où
        // .env contenait d'anciennes valeurs, le serveur continue de servir
        // ces anciennes valeurs indéfiniment, même après avoir changé .env
        // ou régénéré une clé — jusqu'au prochain `config:cache` explicite.
        // On rend ce cache visible, puis on le vide avant le test pour que
        // celui-ci porte à coup sûr sur les valeurs actuelles de .env.
        $etaitEnCache = $this->getLaravel()->configurationIsCached();
        $this->info('=== 0. État du cache de configuration ===');
        if ($etaitEnCache) {
            $this->warn('  ⚠️ La configuration était EN CACHE (bootstrap/cache/config.php existe).');
            $this->warn('  Si ce cache a été généré avant un changement de clé Backblaze ou de .env,');
            $this->warn('  le serveur utilisait les ANCIENNES valeurs jusqu\'à présent, quoi que .env contienne.');
            Artisan::call('config:clear');
            $this->info('  → Cache vidé pour ce test : les valeurs ci-dessous viennent directement de .env.');
        } else {
            $this->line('  OK : aucune configuration en cache, .env est lu directement (rien à vider).');
        }

        $this->newLine();
        $this->info('=== 1. Présence et forme des variables d\'environnement ===');

        $valeurs = [];
        $problemeDetecte = false;

        foreach (self::CLES_CONFIG as $nom => $cleConfig) {
            $brute = config($cleConfig);

            // `config:clear` (section 0) supprime le fichier de cache, mais
            // ne force pas CE processus — déjà démarré, configuration déjà
            // chargée en mémoire depuis ce cache — à relire .env : il ne
            // profite qu'au PROCHAIN lancement. La variable d'environnement
            // brute, elle, est toujours relue par Laravel à chaque
            // démarrage, cache ou non (bootstrapper distinct de celui qui
            // charge la config) : si elle diffère de la config mise en
            // cache, c'est la preuve que ce test portait jusqu'ici sur un
            // instantané périmé plutôt que sur .env réellement actuel.
            // `getenv()` renvoie `false` dans les tests (qui simulent via
            // `config()`, jamais via de vraies variables d'environnement) :
            // cette vérification ne s'y déclenche donc jamais (trouvé le
            // 08/11/2026).
            $brutEnv = getenv($nom);
            if ($brutEnv !== false) {
                $brutEnv = trim($brutEnv);
                if ($brutEnv !== '' && $brutEnv !== $brute) {
                    $this->warn("  ⚠️ {$nom} : la configuration mise en cache diffère de la variable d'environnement réelle de ce processus.");
                    $this->warn('     → La variable d\'environnement (toujours à jour, cache ou non) est utilisée pour la suite de ce diagnostic.');
                    $brute = $brutEnv;
                    $problemeDetecte = true;
                }
            }

            $valeurs[$nom] = $brute;

            if ($brute === null || $brute === '') {
                $this->error("  {$nom} : ABSENTE ou vide.");
                $problemeDetecte = true;

                continue;
            }

            $longueur = strlen($brute);
            $sansEspaces = trim($brute);
            $espacesParasites = $sansEspaces !== $brute;

            // L'application key (le vrai secret) n'est jamais affichée, même
            // partiellement : seule sa longueur compte. Le keyID, lui, n'a
            // pas la même sensibilité (Backblaze l'affiche en clair dans son
            // propre tableau de bord) : un aperçu masqué (6 premiers + 4
            // derniers caractères) permet à Ruth de confirmer, juste en
            // comparant visuellement avec la page Backblaze, que c'est bien
            // LE keyID de la clé qu'elle vient de créer, et pas un ancien,
            // sans jamais coller la valeur complète nulle part (ajouté le
            // 06/11/2026 après deux régénérations de clé sans effet observé).
            if ($nom === 'BACKBLAZE_KEY_ID' && $longueur > 12) {
                $affichage = substr($brute, 0, 6) . str_repeat('•', max($longueur - 10, 1)) . substr($brute, -4) . " ({$longueur} caractères)";
            } elseif ($nom === 'BACKBLAZE_APPLICATION_KEY') {
                $affichage = "{$longueur} caractères";
            } else {
                $affichage = "\"{$brute}\" ({$longueur} caractères)";
            }

            $this->line("  {$nom} : {$affichage}" . ($espacesParasites ? '  ⚠️ ESPACE(S) PARASITE(S) EN DÉBUT/FIN DÉTECTÉ(S)' : ''));

            // Empreinte SHA-256 de la valeur ACTUELLEMENT stockée, affichée
            // pour les deux identifiants (demandé le 08/11/2026) : une
            // empreinte ne permet pas de retrouver le secret d'origine, mais
            // permet de vérifier, en dehors de cette commande (ex. page
            // locale calculant la même empreinte dans le navigateur), que la
            // valeur stockée ici correspond, OCTET POUR OCTET, à une copie
            // d'origine — sans jamais faire transiter ni afficher le secret
            // lui-même, et sans qu'aucune comparaison visuelle (ambiguë sur
            // des caractères comme 0/O ou 1/l/I) ne puisse se tromper.
            if ($nom === 'BACKBLAZE_KEY_ID' || $nom === 'BACKBLAZE_APPLICATION_KEY') {
                $this->line("    empreinte SHA-256 : " . hash('sha256', $brute));
            }

            if ($espacesParasites) {
                $problemeDetecte = true;
            }
        }

        $this->newLine();
        $this->info('=== 2. Cohérence région / endpoint ===');

        $region = trim((string) ($valeurs['BACKBLAZE_REGION'] ?? ''));
        $endpoint = trim((string) ($valeurs['BACKBLAZE_ENDPOINT'] ?? ''));

        if ($region !== '' && $endpoint !== '') {
            $anomalies = $this->verifierRegionEtEndpoint($region, $endpoint);
            foreach ($anomalies as $anomalie) {
                $this->error("  {$anomalie}");
                $problemeDetecte = true;
            }

            if ($anomalies === []) {
                $this->line("  OK : région ({$region}) et endpoint ({$endpoint}) cohérents.");
            }
        } else {
            $this->line('  (ignoré : région ou endpoint absent, déjà signalé ci-dessus)');
        }

        // Longueurs habituelles des clés Backblaze : simples indices (jamais
        // bloquants), utiles pour repérer une clé tronquée au copier-coller
        // ou le nom de la clé saisi à la place de son identifiant.
        $longueurKeyId = strlen(trim((string) ($valeurs['BACKBLAZE_KEY_ID'] ?? '')));
        $longueurAppKey = strlen(trim((string) ($valeurs['BACKBLAZE_APPLICATION_KEY'] ?? '')));
        if ($longueurKeyId > 0 && $longueurKeyId !== 25) {
            $this->warn("  Indice : BACKBLAZE_KEY_ID fait {$longueurKeyId} caractères ; l'identifiant d'une clé d'application Backblaze en fait généralement 25 (12 pour la clé principale « master », déconseillée pour l'API S3). Vérifie que c'est bien le « keyID » et non le « keyName ».");
        }
        if ($longueurAppKey > 0 && $longueurAppKey !== 31) {
            $this->warn("  Indice : BACKBLAZE_APPLICATION_KEY fait {$longueurAppKey} caractères ; une clé d'application Backblaze en fait généralement 31. Une clé tronquée au copier-coller donne exactement « Signature validation failed ».");
        }

        // Une horloge serveur décalée de plus de quelques minutes fait aussi
        // rejeter les signatures : l'heure est affichée pour comparaison.
        $this->line('  Heure du serveur (UTC) : '.gmdate('Y-m-d H:i:s').' — doit correspondre à l\'heure réelle à quelques secondes près.');

        $this->newLine();
        $this->info('=== 3. Test des capacités réellement utilisées par la sauvegarde ===');
        $this->line('  (écrire, lister, supprimer — exactement ce que fait sauvegarde:externe ;');
        $this->line('  jamais de lecture du contenu d\'un fichier existant.)');

        $nomFichier = '__diagnostic_afrolia_' . now()->timestamp . '__';
        $etape3Reussie = false;

        try {
            $disque = Storage::disk('backblaze');

            $disque->put($nomFichier, 'diagnostic Afrolia — fichier temporaire, supprimé immédiatement');
            $this->info('  ✅ Écriture (writeFiles) : réussie.');

            $disque->files();
            $this->info('  ✅ Listage (listFiles) : réussi.');

            $disque->delete($nomFichier);
            $this->info('  ✅ Suppression du fichier de test (deleteFiles) : réussie.');

            $this->newLine();
            $this->info('  ✅ Connexion réussie : la sauvegarde externe peut fonctionner normalement.');

            if ($problemeDetecte) {
                $this->warn('  (Les anomalies listées plus haut n\'empêchent donc pas la connexion ici — à surveiller quand même.)');
            }

            $etape3Reussie = true;
        } catch (\Throwable $e) {
            // Nettoyage best-effort : si l'échec a lieu après l'écriture
            // (ex. lors du listage ou de la suppression), on essaie quand
            // même de retirer le fichier de test pour ne rien laisser
            // traîner dans le bucket.
            try {
                Storage::disk('backblaze')->delete($nomFichier);
            } catch (\Throwable) {
                // Ignoré : si la suppression échoue aussi, c'est déjà
                // signalé par l'erreur principale ci-dessous.
            }

            $this->error('  ❌ ÉCHEC : ' . get_class($e));
            $this->error('  Message complet renvoyé par Backblaze : ' . $e->getMessage());

            // League\Flysystem enveloppe toujours l'exception réelle du SDK
            // AWS (ex. Aws\S3\Exception\S3Exception) dans sa propre exception
            // générique ("Unable to check existence for: ..."). Le vrai code
            // d'erreur ("SignatureDoesNotMatch", "InvalidAccessKeyId"...) ne
            // vit que dans cette exception d'origine, récupérable via la
            // chaîne getPrevious() — jamais affiché avant ce correctif
            // (demandé après un premier diagnostic resté trop vague le
            // 01/11/2026).
            $cause = $e->getPrevious();
            while ($cause !== null) {
                $this->newLine();
                $this->error('  Cause réelle : ' . get_class($cause));
                $this->error('  Message : ' . $cause->getMessage());

                if (method_exists($cause, 'getAwsErrorCode')) {
                    $this->error('  Code d\'erreur AWS/Backblaze : ' . ($cause->getAwsErrorCode() ?? '(absent)'));
                }
                if (method_exists($cause, 'getStatusCode')) {
                    $this->error('  Code HTTP renvoyé par Backblaze : ' . $cause->getStatusCode());
                }

                // getMessage() tronque le corps de la réponse (résumé Guzzle
                // limité à ~120 caractères) : le XML d'erreur complet peut
                // contenir un détail supplémentaire après "Signature
                // validation failed" (ex. un RequestId à donner au support
                // Backblaze, ou une précision sur la cause) — jamais visible
                // jusqu'ici (ajouté le 06/11/2026).
                if (method_exists($cause, 'getResponse') && $cause->getResponse() !== null) {
                    $corpsComplet = (string) $cause->getResponse()->getBody();
                    if ($corpsComplet !== '') {
                        $this->newLine();
                        $this->line('  Corps complet de la réponse HTTP (non tronqué) :');
                        $this->line('  ' . $corpsComplet);
                    }
                }

                $cause = $cause->getPrevious();
            }

            $this->newLine();
            $this->line('  Ce code précis (pas juste "Signature validation failed") indique généralement :');
            $this->line('  - "SignatureDoesNotMatch" → une faute dans BACKBLAZE_APPLICATION_KEY (le plus souvent une faute de transcription).');
            $this->line('  - "InvalidAccessKeyId" → une faute dans BACKBLAZE_KEY_ID, ou une clé supprimée/régénérée côté Backblaze.');
            $this->line('  - "NoSuchBucket" → BACKBLAZE_BUCKET ne correspond pas exactement au nom du bucket.');
            $this->line('  - "AccessDenied" → la clé d\'application existe mais n\'a pas la permission sur ce bucket précis.');
            $this->line('  - 403 sans code précis → vérifier les "Capabilities" de la clé d\'application dans Backblaze (doit inclure au moins listFiles, writeFiles et deleteFiles pour que la sauvegarde fonctionne).');
        }

        // Demandé le 06/11/2026 après plusieurs correctifs de configuration
        // (cache, signature_version) sans effet observé sur l'étape 3 : ce
        // test appelle le SDK AWS directement, sans passer par Flysystem ni
        // la couche "disk" de Laravel, avec exactement les mêmes valeurs.
        // But : savoir si le blocage vient de la façon dont Laravel/
        // Flysystem construisent la requête (bug de notre côté, corrigible)
        // ou s'il est déjà présent avec le SDK AWS nu (donc du côté du
        // compte/de la clé Backblaze elle-même, pas de notre code).
        $this->newLine();
        $this->info('=== 4. Test direct avec le SDK AWS, sans Flysystem ===');
        $this->line('  (mêmes identifiants, même région, même endpoint — pour isoler si le blocage');
        $this->line('  vient de la façon dont Laravel construit la requête, ou de Backblaze lui-même.)');

        $etape4Reussie = $this->testerAvecSdkBrut($valeurs);

        // Demandé le 08/11/2026 : après confirmation que l'applicationKey
        // enregistrée dans Forge correspond caractère pour caractère à la
        // copie d'origine (comparaison faite par Ruth elle-même), les
        // étapes 3 et 4 ci-dessus ne peuvent plus distinguer "mauvais
        // identifiants" de "API S3 de Backblaze indisponible/mal provisionnée
        // pour ce compte" — les deux passent par la même couche de
        // compatibilité S3 (signature SigV4, endpoint s3.<region>...). Ce
        // test appelle à la place l'API NATIVE de Backblaze B2
        // (b2_authorize_account, api.backblazeb2.com) avec la même paire
        // keyID/applicationKey : c'est le mécanisme d'authentification
        // principal de Backblaze, indépendant de toute compatibilité S3. S'il
        // réussit alors que 3 et 4 échouent, cela prouve que les identifiants
        // sont valides et que le problème est spécifique à la passerelle S3 de
        // Backblaze pour ce compte — pas aux identifiants eux-mêmes.
        $this->newLine();
        $this->info('=== 5. Test avec l\'API native Backblaze B2 (sans aucune compatibilité S3) ===');
        $this->line('  (mêmes identifiants, mécanisme d\'authentification Backblaze natif —');
        $this->line('  b2_authorize_account — pour savoir si le blocage est spécifique à la passerelle S3.)');

        $etape5Reussie = $this->testerApiNativeB2($valeurs);

        $this->newLine();
        $this->info('=== Comparaison ===');
        $this->line('  Étape 3 (Laravel/Flysystem, API S3) : ' . ($etape3Reussie ? '✅ réussie' : '❌ échec'));
        $this->line('  Étape 4 (SDK AWS direct, API S3)    : ' . ($etape4Reussie ? '✅ réussie' : '❌ échec'));
        $this->line('  Étape 5 (API native B2, sans S3)    : ' . ($etape5Reussie ? '✅ réussie' : '❌ échec'));

        if (!$etape3Reussie && $etape4Reussie) {
            $this->warn('  → VÉRIFIÉ : le SDK nu fonctionne mais pas Laravel/Flysystem : le blocage vient de la façon dont');
            $this->warn('    la configuration est transmise au SDK par le disque "backblaze" (corrigible dans le code).');
        } elseif (!$etape3Reussie && !$etape4Reussie && $etape5Reussie) {
            $this->warn('  → VÉRIFIÉ : les identifiants sont valides (l\'API native B2 les accepte), mais les DEUX');
            $this->warn('    tests S3 échouent avec les mêmes identifiants. Le blocage est donc spécifique à la');
            $this->warn('    passerelle de compatibilité S3 de Backblaze pour ce compte — pas aux identifiants,');
            $this->warn('    pas à notre code. Hypothèse restante (non vérifiée ici) : API S3 non ou mal');
            $this->warn('    provisionnée pour ce compte, ou incident côté Backblaze sur cette passerelle.');
        } elseif (!$etape3Reussie && !$etape4Reussie && !$etape5Reussie) {
            $this->warn('  → VÉRIFIÉ : échec identique sur les TROIS mécanismes (Flysystem, SDK S3 nu, ET API native');
            $this->warn('    Backblaze sans aucune compatibilité S3). Le blocage ne vient ni de notre code ni de la');
            $this->warn('    passerelle S3 : il est au niveau le plus fondamental, les identifiants eux-mêmes ou le');
            $this->warn('    compte Backblaze. Voir le message et le code exacts de l\'étape 5 ci-dessus.');
        } elseif ($etape3Reussie && $etape4Reussie) {
            $this->info('  → Tout fonctionne normalement.');
        }

        return ($etape3Reussie && $etape4Reussie) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Teste la paire keyID/applicationKey directement contre l'API native de
     * Backblaze B2 (b2_authorize_account), sans passer par la couche de
     * compatibilité S3 ni par le SDK AWS. N'affiche jamais les secrets —
     * seulement le code HTTP et le message d'erreur renvoyés par Backblaze.
     *
     * @param array<string, mixed> $valeurs Les 5 valeurs BACKBLAZE_* déjà lues en section 1.
     */
    private function testerApiNativeB2(array $valeurs): bool
    {
        $cle = $valeurs['BACKBLAZE_KEY_ID'] ?? null;
        $secret = $valeurs['BACKBLAZE_APPLICATION_KEY'] ?? null;

        if (!$cle || !$secret) {
            $this->line('  (ignoré : keyID ou applicationKey déjà signalé absent en section 1.)');

            return false;
        }

        try {
            $reponse = Http::withBasicAuth($cle, $secret)
                ->timeout(15)
                ->get('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
        } catch (\Throwable $e) {
            $this->error('  ❌ ÉCHEC (API native B2, erreur réseau avant même la réponse de Backblaze) : ' . get_class($e));
            $this->error('  Message : ' . $e->getMessage());

            return false;
        }

        if ($reponse->successful()) {
            $corps = $reponse->json();
            $this->info('  ✅ Authentification native B2 (b2_authorize_account) : réussie.');
            $this->line('  accountId renvoyé par Backblaze : ' . ($corps['accountId'] ?? '(absent)'));

            $capacites = $corps['allowed']['capabilities'] ?? null;
            if (is_array($capacites)) {
                $this->line('  Capacités confirmées par Backblaze pour cette clé : ' . implode(', ', $capacites));
            }

            $bucketRestreint = $corps['allowed']['bucketName'] ?? null;
            if ($bucketRestreint !== null) {
                $this->line('  Bucket auquel cette clé est restreinte (selon Backblaze lui-même) : ' . $bucketRestreint);
            }

            return true;
        }

        $this->error('  ❌ ÉCHEC (API native B2) : HTTP ' . $reponse->status());
        $corps = $reponse->json();
        if (is_array($corps)) {
            $this->error('  code renvoyé par Backblaze : ' . ($corps['code'] ?? '(absent)'));
            $this->error('  message renvoyé par Backblaze : ' . ($corps['message'] ?? '(absent)'));
        } else {
            $this->error('  Corps de la réponse (non JSON) : ' . $reponse->body());
        }

        return false;
    }

    /**
     * Reproduit exactement le test de l'étape 3 (écrire, lister, supprimer)
     * mais avec un client Aws\S3\S3Client construit à la main, sans passer
     * par Storage::disk() ni par l'adaptateur Flysystem — pour vérifier que
     * la configuration Laravel transmet bien les mêmes valeurs au SDK.
     *
     * @param array<string, mixed> $valeurs Les 5 valeurs BACKBLAZE_* déjà lues en section 1.
     */
    private function testerAvecSdkBrut(array $valeurs): bool
    {
        $cle = $valeurs['BACKBLAZE_KEY_ID'] ?? null;
        $secret = $valeurs['BACKBLAZE_APPLICATION_KEY'] ?? null;
        $region = $valeurs['BACKBLAZE_REGION'] ?? null;
        $bucket = $valeurs['BACKBLAZE_BUCKET'] ?? null;
        $endpoint = $valeurs['BACKBLAZE_ENDPOINT'] ?? null;

        if (!$cle || !$secret || !$region || !$bucket || !$endpoint) {
            $this->line('  (ignoré : au moins une valeur manque déjà, signalé en section 1.)');

            return false;
        }

        $nomFichier = '__diagnostic_afrolia_sdk_brut_' . now()->timestamp . '__';

        try {
            $client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'endpoint' => $endpoint,
                'use_path_style_endpoint' => true,
                'signature_version' => 'v4',
                'credentials' => [
                    'key' => $cle,
                    'secret' => $secret,
                ],
            ]);

            $client->putObject([
                'Bucket' => $bucket,
                'Key' => $nomFichier,
                'Body' => 'diagnostic Afrolia (SDK brut) — fichier temporaire, supprimé immédiatement',
            ]);
            $this->info('  ✅ Écriture directe (SDK AWS) : réussie.');

            $client->listObjectsV2(['Bucket' => $bucket, 'MaxKeys' => 1]);
            $this->info('  ✅ Listage direct (SDK AWS) : réussi.');

            $client->deleteObject(['Bucket' => $bucket, 'Key' => $nomFichier]);
            $this->info('  ✅ Suppression directe (SDK AWS) : réussie.');

            return true;
        } catch (\Throwable $e) {
            try {
                $client->deleteObject(['Bucket' => $bucket, 'Key' => $nomFichier]);
            } catch (\Throwable) {
                // Ignoré, comme pour l'étape 3.
            }

            $this->error('  ❌ ÉCHEC (SDK brut) : ' . get_class($e));
            $this->error('  Message : ' . $e->getMessage());

            if (method_exists($e, 'getAwsErrorCode')) {
                $this->error('  Code d\'erreur AWS/Backblaze : ' . ($e->getAwsErrorCode() ?? '(absent)'));
            }

            return false;
        }
    }

    /**
     * Vérifie que la région et l'endpoint ont exactement la forme attendue
     * par Backblaze. L'ancienne vérification (« l'endpoint contient la
     * région ») laissait passer l'erreur la plus courante : une région
     * copiée avec son préfixe, ex. « s3.us-west-004 », qui est bien
     * contenue dans l'endpoint mais fausse la signature, car la région
     * fait partie de ce qui est signé.
     *
     * @return list<string> Les anomalies trouvées (vide si tout est cohérent).
     */
    private function verifierRegionEtEndpoint(string $region, string $endpoint): array
    {
        $anomalies = [];

        if (! preg_match('/^[a-z]{2}-[a-z]+-\d{3}$/', $region)) {
            $anomalies[] = "INCOHÉRENT : BACKBLAZE_REGION vaut « {$region} », alors qu'une région Backblaze a la forme « us-west-004 » ou « eu-central-003 » — seule, sans « s3. » devant ni « .backblazeb2.com » derrière. Une région mal formée fausse la signature (« Signature validation failed »).";
        }

        if (! preg_match('#^https?://#i', $endpoint)) {
            $anomalies[] = "INCOHÉRENT : BACKBLAZE_ENDPOINT doit commencer par « https:// » (valeur actuelle : « {$endpoint} »).";

            return $anomalies;
        }

        $hote = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $chemin = (string) parse_url($endpoint, PHP_URL_PATH);

        if (str_ends_with($hote, 'backblazeb2.com')) {
            $hoteAttendu = 's3.'.$region.'.backblazeb2.com';
            if ($hote !== $hoteAttendu) {
                $anomalies[] = "INCOHÉRENT : l'endpoint pointe vers « {$hote} », mais pour la région « {$region} » il devrait être exactement « https://{$hoteAttendu} » (sans le nom du bucket devant).";
            }
            if ($chemin !== '' && $chemin !== '/') {
                $anomalies[] = "INCOHÉRENT : l'endpoint ne doit contenir aucun chemin après le domaine (trouvé : « {$chemin} ») — le bucket se règle uniquement dans BACKBLAZE_BUCKET.";
            }
        } elseif (! str_contains($endpoint, $region)) {
            // Endpoint non Backblaze (tests, autre fournisseur compatible S3) :
            // on garde au moins la vérification d'origine.
            $anomalies[] = "INCOHÉRENT : l'endpoint ({$endpoint}) ne contient pas la région déclarée ({$region}).";
        }

        return $anomalies;
    }
}
