<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TarifController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->intended('index');
        }

        $fraisAnnulation = DB::table('parametres')->where('cle', 'frais_annulation')->value('valeur') ?? 0;

        // Tarification moyenne par spécialité (vue d'ensemble du marché)
        $tarifsParSpecialite = DB::table('services')
            ->join('specialites', 'services.id_speciale', '=', 'specialites.id_specialite')
            ->select(
                'specialites.id_specialite',
                'specialites.libelle',
                DB::raw('COUNT(services.id_service) as nb_offres'),
                DB::raw('AVG(services.prix) as prix_moyen'),
                DB::raw('MIN(services.prix) as prix_min'),
                DB::raw('MAX(services.prix) as prix_max')
            )
            ->groupBy('specialites.id_specialite', 'specialites.libelle')
            ->orderBy('specialites.libelle')
            ->get();

        return view('tarifs.tarifs', compact('fraisAnnulation', 'tarifsParSpecialite'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'frais_annulation' => 'required|numeric|min:0|max:100',
        ]);

        DB::table('parametres')->updateOrInsert(
            ['cle' => 'frais_annulation'],
            ['valeur' => $request->frais_annulation, 'updated_at' => now()]
        );

        return back()->with('succes', 'Les paramètres de tarification ont été mis à jour.');
    }
}
