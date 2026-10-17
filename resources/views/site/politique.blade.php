<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Afrolia — {{ __('politique.titre') }}</title>
<meta name="description" content="{{ __('politique.meta.description') }}">
<style>
:root{
  --bg:#FFF7ED;
  --surface:#FFFFFF;
  --fg:#92400E;
  --fg-soft:#B45309;
  --fg-mute:#A35A29;
  --accent:#EA580C;
  --border:#FDE68A;
  --header-bg:#92400E;
  --header-fg:#FFF7ED;
}
*{box-sizing:border-box}
html,body{margin:0}
body{
  background:var(--bg);
  color:var(--fg);
  font-family:"Manrope", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  line-height:1.6;
}
h1,h2,h3{font-family:"Fraunces", Georgia, "Times New Roman", serif;text-wrap:balance;margin:0;color:var(--fg)}
p{margin:0 0 1em}
a{color:inherit}
img{max-width:100%;display:block}

.wrap{max-width:720px;margin:0 auto;padding-inline:20px}

header.top{background:var(--header-bg);color:var(--header-fg)}
.nav{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-block:14px;max-width:1040px;margin:0 auto;padding-inline:20px}
.brand{display:flex;align-items:center;gap:10px}
.brand img{width:34px;height:34px}
.brand span{font-family:"Fraunces", Georgia, serif;font-weight:600;font-size:1.25rem;color:var(--header-fg)}
.retour{text-decoration:none;font-size:0.9rem;opacity:.9;color:var(--header-fg)}
.retour:hover{opacity:1}

main{padding-block:48px 64px}
.contenu{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px 28px;margin-top:28px}
.contenu p{color:var(--fg);font-size:1rem}
.contenu ul{padding-left:1.2em}

footer.bas{padding-block:28px;text-align:center;font-size:0.82rem;color:var(--fg-mute)}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap">
</head>
<body>

<header class="top">
  <div class="nav">
    <div class="brand">
      <img src="{{ asset('images/afrolia-logo.png') }}" alt="">
      <span>Afrolia</span>
    </div>
    <a class="retour" href="{{ url('/') }}">{{ __('politique.retour') }}</a>
  </div>
</header>

<main class="wrap">
  <h1>{{ __('politique.titre') }}</h1>
  <div class="contenu">
    @if($politicy && trim($politicy->politicy) !== '')
      {!! $politicy->politicy !!}
    @else
      <p>{{ __('politique.vide') }}</p>
    @endif
  </div>
</main>

<footer class="bas">
  <p>&copy; {{ date('Y') }} AFROLIA SARLU</p>
</footer>

</body>
</html>
