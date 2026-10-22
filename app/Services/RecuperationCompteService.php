<?php

namespace App\Services;

use App\Mail\LienReinitialisationMotDePasse;
use App\Mail\LienVerificationEmail;
use App\Models\ActionToken;
use App\Models\RecoveryCode;
use App\Models\UsersApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Vérification d'e-mail, réinitialisation de mot de passe par lien, et
 * codes de secours — regroupés ici pour que l'API et les pages web
 * publiques (clic sur le lien reçu par e-mail) appliquent exactement les
 * mêmes règles de preuve et la même durée de validité.
 */
class RecuperationCompteService
{
    private const DUREE_VALIDITE_VERIFICATION_HEURES = 24;
    private const DUREE_VALIDITE_RESET_MINUTES = 60;
    private const NOMBRE_CODES_SECOURS = 8;

    /**
     * @throws EnvoiEmailIndisponibleException si aucun fournisseur d'e-mail
     *         réel n'est configuré (mailer "log").
     */
    public function demanderVerificationEmail(UsersApp $utilisateur): void
    {
        $this->verifierFournisseurEmailDisponible();

        $tokenBrut = Str::random(64);

        ActionToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'type' => 'verification_email',
            'token_hash' => hash('sha256', $tokenBrut),
            'expire_le' => now()->addHours(self::DUREE_VALIDITE_VERIFICATION_HEURES),
        ]);

        Mail::to($utilisateur->email)->send(new LienVerificationEmail($tokenBrut));
    }

    /**
     * Vérifie qu'un jeton de vérification d'e-mail est valable, sans le
     * consommer. Sert à afficher la page de confirmation (avec son bouton)
     * sans invalider le lien si un scanner anti-hameçonnage de la
     * messagerie (Outlook/Hotmail notamment) l'ouvre automatiquement avant
     * la personne elle-même.
     */
    public function jetonVerificationValide(string $tokenBrut): bool
    {
        return $this->trouverJetonValide($tokenBrut, 'verification_email') !== null;
    }

    /**
     * Consomme un jeton de vérification d'e-mail. Retourne true si
     * l'e-mail a bien été marqué vérifié.
     */
    public function verifierEmailAvecJeton(string $tokenBrut): bool
    {
        $jeton = $this->trouverJetonValide($tokenBrut, 'verification_email');

        if (!$jeton) {
            return false;
        }

        $jeton->utilisateur->update(['email_verified_at' => now()]);
        $jeton->update(['utilise_le' => now()]);

        return true;
    }

    /**
     * @throws EnvoiEmailIndisponibleException si aucun fournisseur d'e-mail
     *         réel n'est configuré.
     */
    public function demanderReinitialisationParEmail(UsersApp $utilisateur): void
    {
        $this->verifierFournisseurEmailDisponible();

        $tokenBrut = Str::random(64);

        ActionToken::create([
            'id_user_app' => $utilisateur->id_user_app,
            'type' => 'reinitialisation_mot_de_passe',
            'token_hash' => hash('sha256', $tokenBrut),
            'expire_le' => now()->addMinutes(self::DUREE_VALIDITE_RESET_MINUTES),
        ]);

        Mail::to($utilisateur->email)->send(new LienReinitialisationMotDePasse($tokenBrut));
    }

    public function jetonReinitialisationValide(string $tokenBrut): bool
    {
        return $this->trouverJetonValide($tokenBrut, 'reinitialisation_mot_de_passe') !== null;
    }

    /**
     * Consomme le jeton et applique le nouveau mot de passe. Révoque aussi
     * toutes les connexions actives : un lien de réinitialisation reçu par
     * une personne malveillante ne doit pas laisser une session active
     * ouverte à la propriétaire légitime du compte... et inversement, une
     * fois le mot de passe changé, les anciennes sessions ne doivent plus
     * être valables.
     */
    public function reinitialiserAvecJeton(string $tokenBrut, string $nouveauMotDePasse): bool
    {
        $jeton = $this->trouverJetonValide($tokenBrut, 'reinitialisation_mot_de_passe');

        if (!$jeton) {
            return false;
        }

        $utilisateur = $jeton->utilisateur;
        $utilisateur->update(['password' => Hash::make($nouveauMotDePasse)]);
        $utilisateur->tokens()->delete();
        $jeton->update(['utilise_le' => now()]);

        return true;
    }

    /**
     * Génère de nouveaux codes de secours pour une personne déjà
     * connectée. Les anciens codes non utilisés sont supprimés : seul le
     * dernier lot généré reste valable, pour éviter d'accumuler des codes
     * oubliés que la personne ne sait plus distinguer.
     *
     * @return string[] les codes en clair, à montrer une seule fois — ils
     *         ne sont jamais récupérables ensuite (seul leur hash est
     *         conservé).
     */
    public function genererCodesSecours(UsersApp $utilisateur): array
    {
        RecoveryCode::where('id_user_app', $utilisateur->id_user_app)
            ->whereNull('utilise_le')
            ->delete();

        $codes = [];

        for ($i = 0; $i < self::NOMBRE_CODES_SECOURS; $i++) {
            $code = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            $codes[] = $code;

            RecoveryCode::create([
                'id_user_app' => $utilisateur->id_user_app,
                'code_hash' => Hash::make($code),
            ]);
        }

        return $codes;
    }

    public function nombreCodesSecoursRestants(UsersApp $utilisateur): int
    {
        return RecoveryCode::where('id_user_app', $utilisateur->id_user_app)
            ->whereNull('utilise_le')
            ->count();
    }

    /**
     * Réinitialise le mot de passe par téléphone + code de secours. Si
     * aucun code valide ne correspond, ne réinitialise rien : l'absence de
     * preuve n'est jamais remplacée par un jugement humain.
     */
    public function reinitialiserAvecCodeSecours(
        string $phone,
        string $code,
        string $nouveauMotDePasse,
        Request $request,
    ): bool {
        $utilisateur = UsersApp::where('phone', $phone)->first();

        if (!$utilisateur) {
            return false;
        }

        $codeCorrespondant = RecoveryCode::where('id_user_app', $utilisateur->id_user_app)
            ->whereNull('utilise_le')
            ->get()
            ->first(fn (RecoveryCode $candidat) => Hash::check($code, $candidat->code_hash));

        if (!$codeCorrespondant) {
            return false;
        }

        $codeCorrespondant->update([
            'utilise_le' => now(),
            'utilise_ip' => $request->ip(),
            'utilise_user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $utilisateur->update(['password' => Hash::make($nouveauMotDePasse)]);
        $utilisateur->tokens()->delete();

        return true;
    }

    private function trouverJetonValide(string $tokenBrut, string $type): ?ActionToken
    {
        return ActionToken::where('token_hash', hash('sha256', $tokenBrut))
            ->where('type', $type)
            ->whereNull('utilise_le')
            ->where('expire_le', '>', now())
            ->first();
    }

    /**
     * Tant qu'aucun fournisseur d'e-mail réel n'est branché (mailer "log"
     * par défaut, ou Brevo/Resend sélectionné sans sa clé API), un e-mail
     * "envoyé" n'atteindrait jamais personne : même principe que le 503 du
     * SMS tant que Twilio n'est pas prêt.
     */
    private function verifierFournisseurEmailDisponible(): void
    {
        $mailer = config('mail.default');

        if (
            $mailer === 'log'
            || ($mailer === 'brevo' && empty(config('services.brevo.key')))
            || ($mailer === 'resend' && empty(config('services.resend.key')))
        ) {
            throw new EnvoiEmailIndisponibleException();
        }
    }
}
