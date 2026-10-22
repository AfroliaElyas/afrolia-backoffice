<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Afrolia — Vérification de l'e-mail</title>
<style>
:root{--bg:#FFF7ED;--surface:#FFFFFF;--fg:#92400E;--fg-soft:#B45309;--accent:#EA580C;--border:#FDE68A;}
*{box-sizing:border-box}
html,body{margin:0}
body{background:var(--bg);color:var(--fg);font-family:"Manrope",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;max-width:420px;width:100%;padding:32px 28px;text-align:center}
h1{font-size:1.3rem;margin:0 0 12px}
p{color:var(--fg-soft);margin:0 0 16px}
.icone{font-size:2.5rem;margin-bottom:8px}
button{width:100%;padding:14px;border:none;border-radius:10px;background:var(--accent);color:#fff;font-size:1rem;font-weight:700;cursor:pointer}
</style>
</head>
<body>
<div class="card">
@if($reussi)
<div class="icone">✅</div>
<h1>Adresse e-mail confirmée</h1>
<p>Vous pouvez fermer cette page et retourner dans l'application Afrolia.</p>
@elseif($valide)
<h1>Confirmer votre adresse e-mail</h1>
<p>Cliquez sur le bouton ci-dessous pour confirmer votre adresse e-mail Afrolia.</p>
<form method="POST" action="{{ url('/verifier-email/' . $token) }}">
@csrf
<button type="submit">Confirmer mon adresse</button>
</form>
@else
<div class="icone">⚠️</div>
<h1>Lien invalide ou expiré</h1>
<p>Ce lien de vérification n'est plus valable. Retournez dans l'application Afrolia pour en demander un nouveau.</p>
@endif
</div>
</body>
</html>
