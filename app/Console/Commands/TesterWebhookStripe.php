<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Vérifie que STRIPE_WEBHOOK_SECRET est correctement configuré côté
 * serveur, sans jamais afficher sa valeur ni en ressaisir une copie. Le
 * dashboard Stripe mobile ("Workbench > Webhooks") ne propose qu'un envoi
 * d'événement de test via la CLI (destinée à un écouteur local sur
 * ordinateur), inutilisable sur téléphone pour un webhook de production —
 * ce test reproduit la même vérification (requête HTTPS réelle, signature
 * calculée exactement comme Stripe le fait) entièrement depuis le serveur
 * (demandé le 18/10/2026).
 *
 * L'identifiant de PaymentIntent utilisé n'existe pas en base
 * (payment_intent_id introuvable) : ApiPaiementsController::stripeWebhook
 * répond alors simplement {"status":"ok"} sans aucun effet de bord
 * (aucune réservation ni commande n'est modifiée).
 */
class TesterWebhookStripe extends Command
{
    protected $signature = 'stripe:tester-webhook {--url= : URL complète à tester, par défaut APP_URL + /api/stripe/webhook}';

    protected $description = "Envoie un faux événement Stripe signé au webhook configuré, pour vérifier STRIPE_WEBHOOK_SECRET sans l'afficher";

    public function handle(): int
    {
        $secret = config('services.stripe.webhook_secret');

        if (empty($secret)) {
            $this->error('STRIPE_WEBHOOK_SECRET est vide dans la configuration du serveur : rien à tester.');

            return self::FAILURE;
        }

        $url = $this->option('url') ?: rtrim(config('app.url'), '/') . '/api/stripe/webhook';

        $payload = json_encode([
            'id' => 'evt_test_diagnostic_' . now()->timestamp,
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_diagnostic_inexistant',
                ],
            ],
        ]);

        // Même algorithme que Stripe (doc "Vérifier les signatures des
        // webhooks") : horodatage + "." + corps brut, HMAC-SHA256 avec le
        // secret de signature.
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        $this->info("Envoi d'un événement de test vers : {$url}");

        $reponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Stripe-Signature' => "t={$timestamp},v1={$signature}",
        ])->withBody($payload, 'application/json')->post($url);

        $this->info("Code de réponse HTTP : {$reponse->status()}");
        $this->info('Corps de la réponse : ' . $reponse->body());

        if ($reponse->status() === 200) {
            $this->info('✅ Signature acceptée : STRIPE_WEBHOOK_SECRET est correctement configuré.');

            return self::SUCCESS;
        }

        $this->error('❌ Le serveur a refusé ou mal traité la requête (voir le code et le corps ci-dessus).');

        return self::FAILURE;
    }
}
