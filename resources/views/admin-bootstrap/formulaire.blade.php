<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Créer le compte administrateur</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#FFF7ED;margin:0;padding:24px 16px;color:#92400E}
  .carte{max-width:420px;margin:0 auto;background:#fff;border:1px solid #FDE68A;border-radius:16px;padding:28px 22px}
  h1{font-size:1.25rem;margin:0 0 6px}
  p{font-size:0.92rem;color:#A35A29;margin:0 0 20px}
  label{display:block;font-size:0.85rem;font-weight:700;margin-bottom:6px}
  input{width:100%;box-sizing:border-box;padding:11px 14px;border:1px solid #FDE68A;border-radius:10px;font-size:1rem;margin-bottom:16px}
  button{width:100%;background:#EA580C;color:#fff;border:none;border-radius:10px;padding:13px;font-size:0.95rem;font-weight:700}
  .erreur{background:#FEE2E2;color:#991B1B;border-radius:10px;padding:10px 14px;font-size:0.88rem;margin-bottom:16px}
  .deja{background:#FEF3C7;color:#92400E;border-radius:10px;padding:14px;font-size:0.9rem}
</style>
</head>
<body>
<div class="carte">
  <h1>Créer le compte administrateur</h1>
  @if ($emailExistant)
    <div class="deja">Un compte existe déjà avec l'adresse afroliaofficiel@gmail.com. Aucune action possible ici pour ne pas l'écraser.</div>
  @else
    <p>Adresse : <strong>afroliaofficiel@gmail.com</strong>. Choisis le mot de passe de ton choix, au moins 8 caractères.</p>
    @if ($errors->any())
      <div class="erreur">{{ $errors->first() }}</div>
    @endif
    <form method="POST">
      @csrf
      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
      <label for="password_confirmation">Confirme le mot de passe</label>
      <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
      <button type="submit">Créer le compte</button>
    </form>
  @endif
</div>
</body>
</html>
