<?php

namespace App\Http\Controllers;

use App\Models\Condition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TermsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->intended('index');
        }

        $termes = Condition::latest()->first();
        return view('termes.termes', compact('termes'));
    }

    /**
     * Page publique "Conditions générales d'utilisation", même principe que
     * la page d'accueil et "À propos" (routes/web.php) : pas d'authentification
     * requise.
     */
    public function page()
    {
        $languesDisponibles = ['fr', 'en'];
        $langueDemandee = request()->query('lang');

        if (in_array($langueDemandee, $languesDisponibles, true)) {
            session(['locale_site' => $langueDemandee]);
        }

        app()->setLocale(session('locale_site', 'fr'));

        $condition = Condition::latest()->first();

        return response()
            ->view('site.conditions', ['condition' => $condition])
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {}

    public function enregistrerOuMettreAJour(Request $request)
    {
        $terme = Condition::latest()->first();

        if ($terme) {
            // Mise à jour
            $terme->update([
                'condition' => $request->notice,
            ]);

            return back()->with('succes',  "Mise à jour effectuée");
        } else {
            // Création
            Condition::create([
                'condition' => $request->notice,
            ]);

            return back()->with('succes',  "Vos conditions sont enregistrées");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
