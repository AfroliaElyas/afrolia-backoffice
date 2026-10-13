<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Condition;
use App\Models\Politicy;

class ApiLegalController extends Controller
{
    public function politique()
    {
        $terme = Politicy::latest()->first();

        if (!$terme) {
            return response()->json([
                'success' => false,
                'message' => "Politique de confidentialité non disponible.",
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->versionLisible($terme->politicy),
        ], 200);
    }

    public function conditions()
    {
        $terme = Condition::latest()->first();

        if (!$terme) {
            return response()->json([
                'success' => false,
                'message' => "Conditions générales d'utilisation non disponibles.",
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->versionLisible($terme->condition),
        ], 200);
    }

    /**
     * Le contenu est saisi en HTML par l'éditeur enrichi (Summernote) côté
     * back-office ; converti ici en texte lisible pour rester cohérent avec
     * ContentPage côté application, qui affiche du texte brut (comme pour
     * l'aide et la confidentialité, déjà servies ainsi par ApiSecurityController).
     */
    private function versionLisible(string $html): string
    {
        $texte = preg_replace('/<\/(p|div|h[1-6]|li|blockquote|pre|tr|table)>/i', "\n", $html);
        $texte = preg_replace('/<br\s*\/?>/i', "\n", $texte);
        $texte = preg_replace('/<li[^>]*>/i', '- ', $texte);
        // Garde-fou : toute autre balise restante (tableau, citation...)
        // devient un espace plutôt que de disparaître purement, pour ne
        // jamais recoller deux mots ensemble.
        $texte = preg_replace('/<[^>]+>/', ' ', $texte);
        $texte = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5);
        $texte = implode("\n", array_map('trim', explode("\n", $texte)));
        $texte = preg_replace('/\n{3,}/', "\n\n", $texte);

        return trim($texte);
    }
}
