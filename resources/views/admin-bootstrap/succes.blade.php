<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Compte créé</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#FFF7ED;margin:0;padding:24px 16px;color:#92400E}
  .carte{max-width:420px;margin:0 auto;background:#fff;border:1px solid #FDE68A;border-radius:16px;padding:28px 22px;text-align:center}
  h1{font-size:1.25rem;margin:0 0 10px}
  p{font-size:0.92rem;color:#A35A29}
  a{display:inline-block;margin-top:14px;background:#EA580C;color:#fff;text-decoration:none;border-radius:10px;padding:12px 22px;font-weight:700}
</style>
</head>
<body>
<div class="carte">
  <h1>✅ Compte créé</h1>
  <p>Le compte administrateur <strong>{{ $email }}</strong> est prêt. Tu peux te connecter dès maintenant avec le mot de passe que tu viens de choisir.</p>
  <a href="/admin">Aller à la connexion</a>
</div>
</body>
</html>
