<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApiAssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant)
    {
    }

    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all()),
            ], 422);
        }

        try {
            $reponse = $this->assistant->repondre($request->input('question'));
        } catch (\Throwable $e) {
            Log::error('Assistant IA : échec de la requête à Claude', ['erreur' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => "L'assistant est momentanément indisponible, veuillez réessayer plus tard.",
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => ['reponse' => $reponse],
        ]);
    }
}
