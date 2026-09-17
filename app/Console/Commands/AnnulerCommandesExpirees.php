<?php

namespace App\Console\Commands;

use App\Models\Commandes;
use App\Models\Produits;
use Illuminate\Console\Command;

class AnnulerCommandesExpirees extends Command
{
    protected $signature = 'commandes:annuler-expirees {--minutes=30}';

    protected $description = "Annule les commandes boutique jamais payées et restitue le stock réservé";

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        $commandes = Commandes::with('lignes')
            ->where('statut_commande', 'en_attente')
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->get();

        foreach ($commandes as $commande) {
            foreach ($commande->lignes as $ligne) {
                Produits::where('id_produit', $ligne->id_produit)
                    ->increment('quantite_stock', $ligne->quantite);
            }

            $commande->update([
                'statut_commande' => 'annulee',
                'raison_annulation' => "Paiement non reçu sous {$minutes} minutes",
                'annulee_le' => now(),
            ]);
        }

        $this->info("{$commandes->count()} commande(s) expirée(s) annulée(s), stock restitué.");

        return self::SUCCESS;
    }
}
