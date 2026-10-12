<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Afrolia — Réinitialisation du mot de passe</title>
<style>
:root{--bg:#FFF7ED;--surface:#FFFFFF;--fg:#92400E;--fg-soft:#B45309;--accent:#EA580C;--border:#FDE68A;--erreur:#DC2626;}
*{box-sizing:border-box}
html,body{margin:0}
body{background:var(--bg);color:var(--fg);font-family:"Manrope",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;max-width:420px;width:100%;padding:32px 28px}
h1{font-size:1.3rem;margin:0 0 12px;text-align:center}
p{color:var(--fg-soft);margin:0 0 16px;text-align:center}
label{display:block;font-size:0.9rem;margin-bottom:6px;color:var(--fg-soft)}
input{width:100%;padding:12px;border:1px solid var(--border);border-radius:10px;font-size:1rem;margin-bottom:16px}
button{width:100%;padding:14px;border:none;border-radius:10px;background:var(--accent);color:#fff;font-size:1rem;font-weight:700;cursor:pointer}
.icone{font-size:2.5rem;text-align:center;margin-bottom:8px}
.erreurs{color:var(--erreur);font-size:0.9rem;margin-bottom:12px}
</style>
</head>
<body>
<div class="card">
@if($reussi)
<div class="icone">✅</div>
<h1>Mot de passe mis à jour</h1>
<p>Vous pouvez fermer cette page et vous reconnecter dans l'application Afrolia avec votre nouveau mot de passe.</p>
@elseif(!$valide)
<div class="icone">⚠️</div>
<h1>Lien invalide ou expiré</h1>
<p>Ce lien de réinitialisation n'est plus valable. Retournez dans l'application Afrolia pour en demander un nouveau.</p>
@else
<h1>Choisissez un nouveau mot de passe</h1>
@isset($erreurs)
<div class="erreurs">
@foreach($erreurs->all() as $erreur)
<div>{{ $erreur }}</div>
@endforeach
</div>
@endisset
<form method="POST" action="{{ url('/reinitialiser-mot-de-passe/' . $token) }}">
@csrf
<label for="mot_de_passe">Nouveau mot de passe</label>
<input type="password" id="mot_de_passe" name="mot_de_passe" minlength="8" required>
<label for="mot_de_passe_confirmation">Confirmez le mot de passe</label>
<input type="password" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" minlength="8" required>
<button type="submit">Réinitialiser</button>
</form>
@endif
</div>
</body>
</html>
