<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbonnementPaiement;
use App\Models\UsersApp;
use App\Services\AbonnementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiAbonnementController extends Controller
{
    public function __construct(private readonly AbonnementService $abonnements)
    {
    }

    // ✅ 1. Statut d'abonnement d'une coiffeuse (formule actuelle, solde, formules disponibles)
    public function getStatut($id_coiffeur)
    {
        $coiffeuse = UsersApp::where('id_user_app', $id_coiffeur)->where('role', 'hair')->first();

        if (!$coiffeuse) {
            return response()->json(['success' => false, 'message' => 'Coiffeuse non trouvée'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'formule_abonnement' => $coiffeuse->formule_abonnement,
                'commission_actuelle' => $this->abonnements->tauxCommissionPourCoiffeuse($coiffeuse) * 100,
                'prochain_prelevement_le' => $coiffeuse->prochain_prelevement_le,
                'solde_disponible' => $this->abonnements->soldeDisponible($coiffeuse->id_user_app),
                'formules' => $this->formulesDisponibles(),
            ],
        ]);
    }

    // ✅ 2. Changement de formule (souscription immédiate si payante, gratuite du jour au lendemain)
    public function changerFormule(Request $request, $id_coiffeur)
    {
        $validator = Validator::make($request->all(), [
            'formule' => 'required|string|in:' . implode(',', AbonnementService::FORMULES),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        $coiffeuse = UsersApp::where('id_user_app', $id_coiffeur)->where('role', 'hair')->first();

        if (!$coiffeuse) {
            return response()->json(['success' => false, 'message' => 'Coiffeuse non trouvée'], 404);
        }

        $formule = $request->input('formule');
        $resultat = $this->abonnements->preleverPour($coiffeuse, $formule);

        if (!$resultat['success']) {
            return response()->json([
                'success' => false,
                'message' => "Solde insuffisant : {$resultat['solde']} FCFA disponible pour un abonnement à {$resultat['prix']} FCFA.",
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Formule ' . ucfirst($formule) . ' activée',
            'data' => $coiffeuse->fresh(),
        ]);
    }

    // ✅ 3. Historique des prélèvements d'abonnement d'une coiffeuse
    public function historique($id_coiffeur)
    {
        $historique = AbonnementPaiement::where('id_coiffeur', $id_coiffeur)
            ->orderBy('id_abonnement_paiement', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $historique]);
    }

    private function formulesDisponibles(): array
    {
        return collect(AbonnementService::FORMULES)->mapWithKeys(fn ($formule) => [
            $formule => [
                'prix' => $this->abonnements->prix($formule),
                'commission' => $this->abonnements->tauxCommission($formule) * 100,
            ],
        ])->all();
    }
}
