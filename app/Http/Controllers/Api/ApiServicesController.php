<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Services;
use App\Models\UsersApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiServicesController extends Controller
{

    public function getServicesByUser($id)
    {
        // Vérifie si l'utilisateur existe
        $userExists = UsersApp::where('id_user_app', $id)->exists();
        if (!$userExists) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé',
            ], 404);
        }

        // Récupère les services de cet utilisateur
        $services = Services::with('specialite') // si tu as une relation définie
            ->where('id_utilisateur', $id)
            ->orderBy('id_service', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Liste des services récupérée avec succès',
            'data' => $services
        ]);
    }

    public function createServices(Request $request)
    {
        // Vérifie si la requête contient un seul service ou une liste
        $isMultiple = is_array($request->services);

        // Si c'est un seul service, on le transforme en tableau pour uniformiser le traitement
        $servicesData = $isMultiple ? $request->services : [$request->all()];

        // Règles de validation
        $rules = [
            '*.prix' => 'required|numeric',
            '*.minute' => 'required|integer',
            '*.description' => 'required|string',
            '*.commission' => 'required|numeric',
            '*.id_speciale' => 'required|integer|exists:specialites,id_specialite',
        ];

        $validator = Validator::make($servicesData, $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->all(),
            ], 422);
        }

        // Un service appartient toujours à l'utilisateur authentifié, jamais
        // à un id_utilisateur transmis par le client.
        $id_utilisateur = $request->user()->id_user_app;

        // Création des services
        $createdServices = [];

        foreach ($servicesData as $serviceData) {
            $createdServices[] = Services::create([
                'id_utilisateur' => $id_utilisateur,
                'prix' => $serviceData['prix'],
                'minute' => $serviceData['minute'],
                'description' => $serviceData['description'],
                'commission' => $serviceData['commission'],
                'id_speciale' => $serviceData['id_speciale'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($createdServices) > 1
                ? 'Services créés avec succès'
                : 'Service créé avec succès',
            $createdServices,
        ], 201);
    }


    public function updateService(Request $request, $id)
    {
        $service = Services::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service introuvable'
            ], 404);
        }

        if ((int) $service->id_utilisateur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez modifier que vos propres services'
            ], 403);
        }

        $rules = [
            'prix' => 'sometimes|required|numeric',
            'minute' => 'sometimes|required|integer',
            'description' => 'sometimes|required|string',
            'commission' => 'sometimes|required|numeric',
            'id_speciale' => 'sometimes|required|integer|exists:specialites,id_specialite',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all())
            ], 422);
        }

        $service->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Service mis à jour avec succès',
            'data' => $service
        ]);
    }

    public function deleteService(Request $request, $id_service)
    {
        $service = Services::find($id_service);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service introuvable'
            ], 404);
        }

        if ((int) $service->id_utilisateur !== (int) $request->user()->id_user_app) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez supprimer que vos propres services'
            ], 403);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service supprimé avec succès'
        ]);
    }
}
