<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Génère une clé de chiffrement pour la future sauvegarde externe vers
 * GitHub, et l'affiche UNE SEULE FOIS à l'écran — jamais écrite sur disque,
 * jamais journalisée, jamais renvoyée à un outil automatisé. Pensée pour
 * être exécutée depuis l'onglet "Commands" de Forge (comme
 * demo:creer-compte-captures) : la sortie n'apparaît que dans la session
 * de Ruth, pas dans un journal GitHub Actions ni dans aucune conversation.
 *
 * Cette commande ne change rien à la sauvegarde existante : elle ne fait
 * que produire une clé, à copier soi-même dans un gestionnaire de mots de
 * passe personnel avant de fermer l'onglet.
 */
class GenererCleSauvegardeGithubCommand extends Command
{
    protected $signature = 'sauvegarde:generer-cle-github';

    protected $description = "Génère une clé de chiffrement à usage unique pour la future sauvegarde externe vers GitHub, affichée une seule fois (jamais journalisée)";

    public function handle(): int
    {
        $cle = base64_encode(random_bytes(32));

        $this->warn('⚠️  Cette clé ne sera plus jamais affichée après cette commande — elle n\'est écrite nulle part sur ce serveur.');
        $this->newLine();
        $this->line('Clé à copier maintenant dans ton gestionnaire de mots de passe (ex. entrée "Afrolia — clé sauvegarde GitHub") :');
        $this->newLine();
        $this->line($cle);
        $this->newLine();
        $this->warn('Une fois copiée et enregistrée : ferme cet onglet. Ne la colle nulle part ailleurs (pas de message, pas de fichier, pas de capture d\'écran).');

        return self::SUCCESS;
    }
}
