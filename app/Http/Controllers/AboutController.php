<?php

namespace App\Http\Controllers;

use App\Models\About;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AboutController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->intended('index');
        }

        $termes = About::latest()->first();
        return view('termes.about', compact('termes'));
    }

    /**
     * Page publique "À propos", même principe que la page d'accueil
     * (routes/web.php) : pas d'authentification requise.
     */
    public function page()
    {
        $languesDisponibles = ['fr', 'en'];
        $langueDemandee = request()->query('lang');

        if (in_array($langueDemandee, $languesDisponibles, true)) {
            session(['locale_site' => $langueDemandee]);
        }

        app()->setLocale(session('locale_site', 'fr'));

        $about = About::latest()->first();

        return response()
            ->view('site.apropos', ['about' => $about])
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
    {
        //
    }

    public function enregistrerOuMettreAJour(Request $request)
    {
        $terme = About::latest()->first();

        if ($terme) {
            // Mise à jour
            $terme->update([
                'about' => $request->notice,
            ]);

            return back()->with('succes',  "Mise à jour effectuée");
        } else {
            // Création
            About::create([
                'about' => $request->notice,
            ]);

            return back()->with('succes',  "Apropos a été enregistré");
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
