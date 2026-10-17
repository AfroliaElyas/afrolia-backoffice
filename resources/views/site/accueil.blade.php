<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Afrolia</title>
<meta name="description" content="{{ __('accueil.meta.description') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
:root{
  --bg:#FFF7ED;
  --surface:#FFFFFF;
  --fg:#92400E;
  --fg-soft:#B45309;
  --fg-mute:#A35A29;
  --accent:#EA580C;
  --accent2:#F59C0B;
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
  line-height:1.5;
}
h1,h2,h3{
  font-family:"Fraunces", Georgia, "Times New Roman", serif;
  text-wrap:balance;
  margin:0;
  color:var(--fg);
}
p{margin:0}
a{color:inherit}
img{max-width:100%;display:block}

.wrap{max-width:1040px;margin:0 auto;padding-inline:20px}

header.top{
  position:sticky;
  top:0;
  z-index:20;
  background:var(--header-bg);
  color:var(--header-fg);
  border-bottom:1px solid rgba(255,255,255,.08);
}
.nav{
  display:flex;
  align-items:center;
  justify-content:space-between;
  flex-wrap:wrap;
  gap:10px 16px;
  padding-block:12px;
}
.brand{display:flex;align-items:center;gap:10px;min-width:0}
.brand img{width:34px;height:34px;flex:none}
.brand span{font-family:"Fraunces", Georgia, serif;font-weight:600;font-size:1.25rem;color:var(--header-fg)}
.nav-links{display:none;gap:24px;font-size:0.92rem}
.nav-links a{text-decoration:none;opacity:.85}
.nav-links a:hover{opacity:1}
@media (min-width:760px){ .nav-links{display:flex} }
.nav-right{display:flex;align-items:center;gap:12px;flex:none}
.lang-switch{display:flex;gap:4px;background:rgba(255,255,255,.1);border-radius:99px;padding:3px}
.lang-switch a{
  text-decoration:none;
  font-size:0.78rem;
  font-weight:700;
  padding:5px 11px;
  border-radius:99px;
  color:rgba(255,255,255,.75);
}
.lang-switch a.active{background:#fff;color:var(--header-bg)}
.nav-cta{
  flex:none;
  background:var(--accent);
  color:#fff;
  border:none;
  border-radius:99px;
  padding:9px 18px;
  font-size:0.85rem;
  font-weight:700;
  text-decoration:none;
  white-space:nowrap;
}

.hero{
  padding-block:40px 32px;
  display:grid;
  gap:28px;
  align-items:center;
}
.hero-content{
  text-align:center;
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:18px;
}
.hero-photo{
  border-radius:24px;
  overflow:hidden;
  box-shadow:0 24px 44px -24px rgba(146,64,14,.4);
  aspect-ratio:4/3;
}
.hero-photo img{width:100%;height:100%;object-fit:cover}
@media (min-width:760px){
  .hero{grid-template-columns:1.05fr 0.95fr}
  .hero-content{align-items:flex-start;text-align:left;order:1}
  .hero-ctas{justify-content:flex-start}
  .hero-photo{aspect-ratio:3/4;order:2}
}
.hero img.logo{width:92px;height:92px}
.badge{
  display:inline-flex;
  align-items:center;
  gap:6px;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:99px;
  padding:6px 14px;
  font-size:0.78rem;
  font-weight:700;
  letter-spacing:.02em;
  color:var(--fg-soft);
}
.badge::before{content:"●";color:var(--accent);font-size:.6rem}
.hero h1{font-size:clamp(2rem,6vw,2.9rem);max-width:16ch;font-weight:600}
@media (max-width:480px){ .hero h1{font-size:1.65rem} }
.hero .lede{max-width:46ch;color:var(--fg-mute);font-size:1.05rem}
.hero-ctas{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-top:6px}
.btn{
  display:inline-flex;align-items:center;gap:8px;
  border-radius:99px;
  padding:12px 24px;
  font-weight:700;
  font-size:0.95rem;
  text-decoration:none;
  border:1.5px solid transparent;
}
.btn.primary{background:var(--accent);color:#fff}
.btn.ghost{background:transparent;color:var(--fg);border-color:var(--border)}

.store-soon{margin-top:24px}
.store-badges{display:flex;flex-direction:column;gap:12px;max-width:320px;margin-inline:auto}
@media (min-width:760px){ .store-badges{flex-direction:row;max-width:none;margin-inline:0} }
.store-badge{
  display:flex;align-items:center;justify-content:center;gap:12px;
  background:var(--accent);color:#fff;
  border-radius:16px;padding:15px 20px;
  text-decoration:none;
  flex:1;
  box-shadow:0 10px 20px -12px rgba(234,88,12,.55);
}
.store-badge svg{flex:none}
.store-badge .txt{display:flex;flex-direction:column;line-height:1.2;text-align:left}
.store-badge .txt strong{font-size:1rem;font-weight:700;font-family:"Manrope",sans-serif;white-space:nowrap}
.store-badge .txt small{font-size:0.75rem;letter-spacing:.02em;opacity:.9;margin-top:2px;white-space:nowrap}

section{padding-block:40px}
.section-head{text-align:center;max-width:46ch;margin:0 auto 26px}
.section-head .eyebrow{
  display:block;
  font-size:0.72rem;
  font-weight:700;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:var(--accent);
  margin-bottom:8px;
}
.section-head h2{font-size:clamp(1.5rem,4vw,2rem)}
.section-head p{color:var(--fg-mute);margin-top:10px;font-size:0.98rem}

.tracks{display:grid;gap:36px}
@media (min-width:760px){ .tracks{grid-template-columns:1fr 1fr;gap:28px} }
.track{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:20px;
  padding:28px 24px;
}
.track-title{display:flex;align-items:center;gap:10px;margin-bottom:22px}
.track-title .dot{
  width:30px;height:30px;border-radius:50%;
  background:var(--accent);color:#fff;
  display:flex;align-items:center;justify-content:center;
  font-size:0.85rem;font-weight:700;flex:none;
}
.track-title h3{font-size:1.15rem}
.step{display:flex;gap:14px;padding-block:12px}
.step + .step{border-top:1px solid var(--border)}
.step .num{
  flex:none;width:26px;height:26px;border-radius:50%;
  border:1.5px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:0.78rem;font-weight:700;color:var(--fg-soft);
}
.step-body{flex:1;min-width:0}
.step-body h4{font-size:0.98rem;font-weight:700;margin-bottom:3px}
.step-body p{font-size:0.88rem;color:var(--fg-mute)}

.phone-mock{flex:none;width:88px;border:3px solid var(--fg);border-radius:16px;background:var(--fg);padding:3px 3px 6px}
.phone-mock .notch{width:26px;height:4px;border-radius:3px;background:rgba(255,255,255,.3);margin:3px auto 4px}
.phone-screen{background:var(--surface);border-radius:11px;padding:8px 7px;display:flex;flex-direction:column;gap:6px;min-height:150px}
.phone-screen .m-bar{font-size:0.52rem;font-weight:800;color:var(--fg);text-align:center;line-height:1.2}
.phone-screen .m-input{background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:4px 5px;font-size:0.4rem;color:var(--fg-mute);line-height:1.2}
.phone-screen .m-row{display:flex;align-items:center;gap:5px}
.phone-screen .m-avatar{flex:none;width:14px;height:14px;border-radius:50%;background:var(--border)}
.phone-screen .m-lines{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px}
.phone-screen .m-line{height:4px;border-radius:2px;background:var(--border)}
.phone-screen .m-line.w70{width:70%}
.phone-screen .m-line.w60{width:60%}
.phone-screen .m-line.w50{width:50%}
.phone-screen .m-line.w40{width:40%}
.phone-screen .m-line.w30{width:30%}
.phone-screen .m-line.muted{background:rgba(146,64,14,.18)}
.phone-screen .m-chips{display:flex;gap:4px;justify-content:center;margin-top:2px}
.phone-screen .m-chip{width:12px;height:12px;border-radius:50%}
.phone-screen .m-amount{text-align:center;margin-top:1px}
.phone-screen .m-amount .label{font-size:0.38rem;color:var(--fg-mute)}
.phone-screen .m-amount .value{font-size:0.62rem;font-weight:700;color:var(--fg)}
.phone-screen .m-btn{background:var(--accent);color:#fff;border-radius:99px;font-size:0.4rem;font-weight:700;text-align:center;padding:4px 3px;margin-top:auto}
.phone-screen .m-cal{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
.phone-screen .m-cal span{aspect-ratio:1;border-radius:2px;background:var(--border)}
.phone-screen .m-cal span.on{background:var(--accent2)}
.phone-screen .m-legend{display:flex;gap:4px;font-size:0.36rem;color:var(--fg-mute);align-items:center}
.phone-screen .m-dot{width:6px;height:6px;border-radius:50%;flex:none}
.phone-screen .m-price-row{display:flex;justify-content:space-between;align-items:center;gap:6px}
.phone-screen .m-price-row .m-line{flex:1}
.phone-screen .m-price{font-size:0.4rem;color:var(--fg-mute);flex:none}
.phone-screen .m-actions{display:flex;gap:4px;margin-top:auto}
.phone-screen .m-actions span{flex:1;text-align:center;border-radius:99px;padding:3px 2px;font-size:0.36rem;font-weight:700}
.phone-screen .m-actions .refuse{border:1px solid var(--border);color:var(--fg-mute)}
.phone-screen .m-actions .confirm{background:var(--accent);color:#fff}

.duo-photos{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.duo-photos img{border-radius:16px;aspect-ratio:3/4;object-fit:cover;width:100%}

.cards{display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(230px,1fr))}
.card{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:16px;
  padding:22px;
}
.card .icon{
  width:42px;height:42px;border-radius:12px;
  background:rgba(234,88,12,.16);
  display:flex;align-items:center;justify-content:center;
  margin-bottom:14px;
  font-size:1.2rem;
}
.card h4{font-size:1rem;margin-bottom:6px}
.card p{font-size:0.88rem;color:var(--fg-mute)}

.pricing{
  display:grid;gap:18px;
  grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
  max-width:680px;margin:0 auto;
}
.price-card{
  background:var(--surface);
  border:1.5px solid var(--border);
  border-radius:18px;
  padding:26px 22px;
}
.price-card.featured{border-color:var(--accent)}
.price-card .tag{
  font-size:0.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
  color:var(--accent);margin-bottom:10px;display:block;
}
.price-card .amount{font-family:"Fraunces",serif;font-size:1.9rem;font-weight:600;margin-bottom:4px}
.price-card .amount small{font-size:0.95rem;font-weight:400;color:var(--fg-mute)}
.price-card ul{list-style:none;margin:16px 0 0;padding:0;display:flex;flex-direction:column;gap:9px}
.price-card li{font-size:0.88rem;color:var(--fg-mute);display:flex;gap:8px}
.price-card li::before{content:"✓";color:var(--accent);font-weight:700;flex:none}

details.faq{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:14px;
  padding:4px 20px;
  margin-bottom:12px;
}
details.faq summary{
  padding-block:16px;
  font-weight:700;
  font-size:0.95rem;
  cursor:pointer;
  list-style:none;
  display:flex;justify-content:space-between;align-items:center;gap:12px;
}
details.faq summary::-webkit-details-marker{display:none}
details.faq summary::after{content:"+";font-size:1.3rem;color:var(--accent);flex:none}
details.faq[open] summary::after{content:"–"}
details.faq p{padding-bottom:18px;color:var(--fg-mute);font-size:0.9rem}

.contact{
  background:var(--header-bg);
  color:var(--header-fg);
  border-radius:24px;
  padding:32px 24px;
  text-align:center;
  display:flex;flex-direction:column;align-items:center;gap:16px;
}
.contact h2{color:var(--header-fg);font-size:clamp(1.4rem,4vw,1.9rem)}
.contact p{color:rgba(255,255,255,.82);max-width:40ch}
.copy-row{
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:center;
}
.email-pill{
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.2);
  border-radius:99px;
  padding:10px 18px;
  font-size:0.92rem;
  font-weight:600;
  color:inherit;
  text-decoration:none;
  display:inline-block;
}
.email-pill:hover{background:rgba(255,255,255,.16)}
.copy-btn{
  background:var(--accent);
  color:#fff;border:none;border-radius:99px;
  padding:10px 18px;font-size:0.85rem;font-weight:700;cursor:pointer;
}

.lancement-form{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;max-width:420px;width:100%;margin-top:2px}
.lancement-form input{
  flex:1;min-width:200px;
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.25);
  border-radius:99px;padding:12px 18px;color:#fff;font-size:0.95rem;font-family:inherit;
}
.lancement-form input::placeholder{color:rgba(255,255,255,.6)}
.lancement-form input:focus{outline:2px solid #fff;outline-offset:2px}
.lancement-form button{
  background:var(--accent);color:#fff;border:none;border-radius:99px;
  padding:12px 22px;font-weight:700;font-size:0.9rem;cursor:pointer;white-space:nowrap;
}
.lancement-form button:disabled{opacity:.6;cursor:not-allowed}
.lancement-feedback{font-size:0.88rem;margin:0}
.lancement-feedback.ok{color:#BBF7D0}
.lancement-feedback.err{color:#FECACA}

.values{text-align:center}
.values .card{text-align:center}
.values .icon{margin-inline:auto;background:var(--accent);color:#fff;font-size:1.3rem}

footer{background:var(--header-bg);color:var(--header-fg);padding-block:32px 18px}
.footer-grid{
  display:grid;gap:32px;
  grid-template-columns:1.4fr 1fr 1fr 1fr;
}
@media (max-width:760px){ .footer-grid{grid-template-columns:1fr 1fr} }
@media (max-width:460px){ .footer-grid{grid-template-columns:1fr} }
.social-row{display:flex;gap:10px}
.social-row a{
  width:36px;height:36px;border-radius:50%;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.18);
  display:flex;align-items:center;justify-content:center;
  text-decoration:none;color:rgba(255,255,255,.85);
  font-size:1rem;
}
.social-row a:hover{background:rgba(255,255,255,.16)}
.footer-brand{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.footer-brand img{width:28px;height:28px}
.footer-brand span{font-family:"Fraunces",serif;font-weight:600;font-size:1.1rem}
footer .tagline{color:rgba(255,255,255,.82);font-size:0.88rem;max-width:30ch}
footer h5{color:rgba(255,255,255,.9);font-size:0.78rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px}
footer ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
footer ul a, footer ul span{color:rgba(255,255,255,.82);text-decoration:none;font-size:0.88rem}
footer ul a:hover{color:#fff}
.legal-soon{font-size:0.72rem;color:rgba(255,255,255,.75);margin-top:2px}
.footer-bottom{
  border-top:1px solid rgba(255,255,255,.12);
  margin-top:28px;padding-top:16px;
  text-align:center;
}
.footer-bottom p{color:rgba(255,255,255,.82);font-size:0.8rem;margin-bottom:4px}

@media (max-width:600px){
  .hero{padding-block:24px 18px}
  section{padding-block:20px}
  .section-head{margin-bottom:16px}
  .tracks{gap:16px}
  .contact{padding:24px 18px}
  footer{padding-block:24px 14px}
  .footer-grid{gap:20px}
  .footer-bottom{margin-top:18px;padding-top:12px}
  .hero-photo{margin-top:4px}
  .duo-photos{margin-top:14px !important}
  .gallery-grid{gap:12px}
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap">
</head>
<body>

<header class="top">
  <div class="wrap nav">
    <div class="brand">
      <img src="{{ asset('images/afrolia-logo.png') }}" alt="">
      <span>Afrolia</span>
    </div>
    <nav class="nav-links">
      <a href="#comment-ca-marche">{{ __('accueil.nav.comment_ca_marche') }}</a>
      <a href="#boutique">{{ __('accueil.nav.boutique') }}</a>
      <a href="#coiffeuses">{{ __('accueil.nav.coiffeuses') }}</a>
      <a href="#faq">{{ __('accueil.nav.faq') }}</a>
    </nav>
    <div class="nav-right">
      <div class="lang-switch" role="group" aria-label="{{ __('accueil.langue.selecteur_aria') }}">
        <a href="{{ request()->fullUrlWithQuery(['lang' => 'fr']) }}" class="{{ app()->getLocale() === 'fr' ? 'active' : '' }}">{{ __('accueil.langue.fr') }}</a>
        <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" class="{{ app()->getLocale() === 'en' ? 'active' : '' }}">{{ __('accueil.langue.en') }}</a>
      </div>
      <a class="nav-cta" href="#lancement">{{ __('accueil.nav.cta_lancement') }}</a>
    </div>
  </div>
</header>

<main>
  <section class="hero wrap">
    <div class="hero-content">
      <img class="logo" src="{{ asset('images/afrolia-logo.png') }}" alt="Afrolia">
      <span class="badge">{{ __('accueil.hero.badge') }}</span>
      <h1>{{ __('accueil.hero.titre') }}</h1>
      <p class="lede">{{ __('accueil.hero.texte') }}</p>
      <div class="hero-ctas">
        <a class="btn primary" href="#lancement">{{ __('accueil.nav.cta_lancement') }}</a>
        <a class="btn ghost" href="#comment-ca-marche">{{ __('accueil.hero.cta_comment') }}</a>
      </div>
      <div class="store-soon">
        <div class="store-badges">
          <a class="store-badge" href="#lancement" aria-label="{{ __('accueil.hero.play_aria') }}">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="#fff"><path d="M3 2.6c-.3.3-.5.8-.5 1.4v16c0 .6.2 1.1.5 1.4l.1.1L12.6 12 3.1 2.5z"/><path d="M15.8 15.2 12.6 12l3.2-3.2 4.2 2.4c1.2.7 1.2 1.9 0 2.6z"/><path d="M15.8 15.2 12.6 12 3.1 21.5c.4.4 1 .4 1.8 0z"/><path d="M15.8 8.8 4.9 2.5c-.8-.4-1.4-.4-1.8 0L12.6 12z"/></svg>
            <span class="txt"><strong>{{ __('accueil.hero.play_bientot') }}</strong><small>{{ __('accueil.hero.play_store') }}</small></span>
          </a>
          <a class="store-badge" href="#lancement" aria-label="{{ __('accueil.hero.appstore_aria') }}">
            <svg width="23" height="23" viewBox="0 0 24 24" fill="#fff"><path d="M17.05 12.3c-.03-2.1 1.72-3.1 1.8-3.15-1-1.44-2.52-1.64-3.06-1.66-1.3-.13-2.54.76-3.2.76-.66 0-1.68-.74-2.76-.72-1.42.02-2.73.82-3.46 2.08-1.48 2.56-.38 6.35 1.06 8.43.7 1.02 1.54 2.16 2.63 2.12 1.06-.04 1.46-.68 2.74-.68 1.27 0 1.64.68 2.76.66 1.14-.02 1.86-1.03 2.56-2.06.81-1.18 1.14-2.33 1.15-2.39-.02-.01-2.2-.84-2.22-3.39z"/><path d="M14.86 5.98c.58-.71.98-1.68.87-2.66-.84.04-1.87.56-2.47 1.26-.54.63-1.02 1.63-.89 2.59.94.07 1.9-.48 2.49-1.19z"/></svg>
            <span class="txt"><strong>{{ __('accueil.hero.appstore_bientot') }}</strong><small>{{ __('accueil.hero.appstore_store') }}</small></span>
          </a>
        </div>
      </div>
    </div>
    <div class="hero-photo">
      <img src="{{ asset('images/site/coiffure-tresses.jpg') }}" alt="{{ __('accueil.images.hero_alt') }}" loading="eager">
    </div>
  </section>

  <section id="comment-ca-marche" class="wrap">
    <div class="section-head">
      <span class="eyebrow">{{ __('accueil.comment.eyebrow') }}</span>
      <h2>{{ __('accueil.comment.titre') }}</h2>
      <p>{{ __('accueil.comment.texte') }}</p>
    </div>
    <div class="tracks">
      <div class="track">
        <div class="track-title"><span class="dot">C</span><h3>{{ __('accueil.comment.clientes.titre') }}</h3></div>
        <div class="step">
          <span class="num">1</span>
          <div class="step-body">
            <h4>{{ __('accueil.comment.clientes.etape1_titre') }}</h4>
            <p>{{ __('accueil.comment.clientes.etape1_texte') }}</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">{{ __('accueil.comment.clientes.etape1_mock_bar') }}</div>
              <div class="m-input">{{ __('accueil.comment.clientes.etape1_mock_input') }}</div>
              <div class="m-row"><span class="m-avatar"></span><div class="m-lines"><span class="m-line w70"></span><span class="m-line w40 muted"></span></div></div>
              <div class="m-row"><span class="m-avatar"></span><div class="m-lines"><span class="m-line w50"></span><span class="m-line w30 muted"></span></div></div>
            </div>
          </div>
        </div>
        <div class="step">
          <span class="num">2</span>
          <div class="step-body">
            <h4>{{ __('accueil.comment.clientes.etape2_titre') }}</h4>
            <p>{{ __('accueil.comment.clientes.etape2_texte') }}</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">{{ __('accueil.comment.clientes.etape2_mock_bar') }}</div>
              <div class="m-row"><span class="m-avatar"></span><div class="m-lines"><span class="m-line w60"></span><span class="m-line w40 muted"></span></div></div>
              <div class="m-price-row"><span class="m-line w40"></span><span class="m-price">—</span></div>
              <div class="m-price-row"><span class="m-line w50"></span><span class="m-price">—</span></div>
            </div>
          </div>
        </div>
        <div class="step">
          <span class="num">3</span>
          <div class="step-body">
            <h4>{{ __('accueil.comment.clientes.etape3_titre') }}</h4>
            <p>{{ __('accueil.comment.clientes.etape3_texte') }}</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">{{ __('accueil.comment.clientes.etape3_mock_bar') }}</div>
              <div class="m-chips">
                <span class="m-chip" style="background:#00A9E0"></span>
                <span class="m-chip" style="background:#FF6600"></span>
                <span class="m-chip" style="background:#FFCC00"></span>
                <span class="m-chip" style="background:#0066B3"></span>
              </div>
              <div class="m-amount"><div class="label">{{ __('accueil.comment.clientes.etape3_mock_montant') }}</div><div class="value">—</div></div>
              <div class="m-btn">{{ __('accueil.comment.clientes.etape3_mock_payer') }}</div>
            </div>
          </div>
        </div>
      </div>
      <div class="track">
        <div class="track-title"><span class="dot">P</span><h3>{{ __('accueil.comment.coiffeuses.titre') }}</h3></div>
        <div class="step">
          <span class="num">1</span>
          <div class="step-body">
            <h4>{{ __('accueil.comment.coiffeuses.etape1_titre') }}</h4>
            <p>{{ __('accueil.comment.coiffeuses.etape1_texte') }}</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">{{ __('accueil.comment.coiffeuses.etape1_mock_bar') }}</div>
              <div class="m-price-row"><span class="m-line w50"></span><span class="m-price">—</span></div>
              <div class="m-price-row"><span class="m-line w60"></span><span class="m-price">—</span></div>
              <div class="m-btn">{{ __('accueil.comment.coiffeuses.etape1_mock_ajouter') }}</div>
            </div>
          </div>
        </div>
        <div class="step">
          <span class="num">2</span>
          <div class="step-body">
            <h4>{{ __('accueil.comment.coiffeuses.etape2_titre') }}</h4>
            <p>{{ __('accueil.comment.coiffeuses.etape2_texte') }}</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">{{ __('accueil.comment.coiffeuses.etape2_mock_bar') }}</div>
              <div class="m-cal">
                <span class="on"></span><span class="on"></span><span></span><span class="on"></span><span></span><span class="on"></span><span></span>
                <span></span><span class="on"></span><span class="on"></span><span></span><span class="on"></span><span></span><span class="on"></span>
              </div>
              <div class="m-legend"><span class="m-dot" style="background:var(--accent2)"></span>{{ __('accueil.comment.coiffeuses.etape2_mock_legende') }}</div>
            </div>
          </div>
        </div>
        <div class="step">
          <span class="num">3</span>
          <div class="step-body">
            <h4>{{ __('accueil.comment.coiffeuses.etape3_titre') }}</h4>
            <p>{{ __('accueil.comment.coiffeuses.etape3_texte') }}</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">{{ __('accueil.comment.coiffeuses.etape3_mock_bar') }}</div>
              <div class="m-row"><span class="m-avatar"></span><div class="m-lines"><span class="m-line w60"></span><span class="m-line w40 muted"></span></div></div>
              <div class="m-actions"><span class="refuse">{{ __('accueil.comment.coiffeuses.etape3_mock_refuser') }}</span><span class="confirm">{{ __('accueil.comment.coiffeuses.etape3_mock_confirmer') }}</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="wrap">
    <div class="section-head">
      <span class="eyebrow">{{ __('accueil.clientes_section.eyebrow') }}</span>
      <h2>{{ __('accueil.clientes_section.titre') }}</h2>
    </div>
    <div class="cards">
      <div class="card">
        <div class="icon">💳</div>
        <h4>{{ __('accueil.clientes_section.carte1_titre') }}</h4>
        <p>{{ __('accueil.clientes_section.carte1_texte') }}</p>
      </div>
      <div class="card">
        <div class="icon">⭐</div>
        <h4>{{ __('accueil.clientes_section.carte2_titre') }}</h4>
        <p>{{ __('accueil.clientes_section.carte2_texte') }}</p>
      </div>
      <div class="card">
        <div class="icon">💬</div>
        <h4>{{ __('accueil.clientes_section.carte3_titre') }}</h4>
        <p>{{ __('accueil.clientes_section.carte3_texte') }}</p>
      </div>
    </div>
  </section>

  <section id="boutique" class="wrap">
    <div class="section-head">
      <span class="eyebrow">{{ __('accueil.boutique.eyebrow') }}</span>
      <h2>{{ __('accueil.boutique.titre') }}</h2>
      <p>{{ __('accueil.boutique.texte') }}</p>
    </div>
    <div class="track" style="max-width:640px;margin:0 auto">
      <div class="step">
        <span class="num">1</span>
        <div class="step-body">
          <h4>{{ __('accueil.boutique.etape1_titre') }}</h4>
          <p>{{ __('accueil.boutique.etape1_texte') }}</p>
        </div>
      </div>
      <div class="step">
        <span class="num">2</span>
        <div class="step-body">
          <h4>{{ __('accueil.boutique.etape2_titre') }}</h4>
          <p>{{ __('accueil.boutique.etape2_texte') }}</p>
        </div>
      </div>
      <div class="step">
        <span class="num">3</span>
        <div class="step-body">
          <h4>{{ __('accueil.boutique.etape3_titre') }}</h4>
          <p>{{ __('accueil.boutique.etape3_texte') }}</p>
        </div>
      </div>
    </div>
  </section>

  <section id="coiffeuses" class="wrap">
    <div class="section-head">
      <span class="eyebrow">{{ __('accueil.pricing.eyebrow') }}</span>
      <h2>{{ __('accueil.pricing.titre') }}</h2>
      <p>{{ __('accueil.pricing.texte') }}</p>
    </div>
    <div class="pricing">
      <div class="price-card">
        <span class="tag">{{ __('accueil.pricing.gratuit_tag') }}</span>
        <div class="amount">{{ __('accueil.pricing.gratuit_montant') }} <small>{{ __('accueil.pricing.gratuit_unite') }}</small></div>
        <ul>
          <li>{{ __('accueil.pricing.gratuit_item1') }}</li>
          <li>{{ __('accueil.pricing.gratuit_item2') }}</li>
          <li>{{ __('accueil.pricing.gratuit_item3') }}</li>
        </ul>
      </div>
      <div class="price-card featured">
        <span class="tag">{{ __('accueil.pricing.premium_tag') }}</span>
        <div class="amount">{{ __('accueil.pricing.premium_montant') }} <small>{{ __('accueil.pricing.premium_unite') }}</small></div>
        <ul>
          <li>{{ __('accueil.pricing.premium_item1') }}</li>
          <li>{{ __('accueil.pricing.premium_item2') }}</li>
          <li>{{ __('accueil.pricing.premium_item3') }}</li>
        </ul>
      </div>
    </div>
    <p style="color:var(--fg-mute);font-size:0.85rem;text-align:center;max-width:46ch;margin:18px auto 0">
      {{ __('accueil.pricing.annulation_note') }}
    </p>
  </section>

  <section class="wrap values">
    <div class="section-head">
      <span class="eyebrow">{{ __('accueil.adn.eyebrow') }}</span>
      <h2>{{ __('accueil.adn.titre') }}</h2>
    </div>
    <div class="cards">
      <div class="card">
        <div class="icon">❤️</div>
        <h4>{{ __('accueil.adn.carte1_titre') }}</h4>
        <p>{{ __('accueil.adn.carte1_texte') }}</p>
      </div>
      <div class="card">
        <div class="icon">🇨🇮</div>
        <h4>{{ __('accueil.adn.carte2_titre') }}</h4>
        <p>{{ __('accueil.adn.carte2_texte') }}</p>
      </div>
      <div class="card">
        <div class="icon">🎯</div>
        <h4>{{ __('accueil.adn.carte3_titre') }}</h4>
        <p>{{ __('accueil.adn.carte3_texte') }}</p>
      </div>
    </div>
  </section>

  <section class="wrap">
    <div class="section-head">
      <span class="eyebrow">{{ __('accueil.qui_sommes_nous.eyebrow') }}</span>
      <h2>{{ __('accueil.qui_sommes_nous.titre') }}</h2>
    </div>
    <div class="track" style="max-width:640px;margin:0 auto">
      <p style="color:var(--fg-mute);font-size:0.98rem">
        {{ __('accueil.qui_sommes_nous.texte') }}
      </p>
      <div class="duo-photos" style="margin-top:22px">
        <img src="{{ asset('images/site/coiffure-trio-1.jpg') }}" alt="{{ __('accueil.images.trio1_alt') }}" loading="lazy" style="object-position:25% 55%">
        <img src="{{ asset('images/site/coiffure-trio-2.jpg') }}" alt="{{ __('accueil.images.trio2_alt') }}" loading="lazy">
      </div>
    </div>
  </section>

  <section id="faq" class="wrap">
    <div class="section-head">
      <span class="eyebrow">{{ __('accueil.faq.eyebrow') }}</span>
      <h2>{{ __('accueil.faq.titre') }}</h2>
    </div>
    <div style="max-width:640px;margin:0 auto">
      <details class="faq">
        <summary>{{ __('accueil.faq.q1') }}</summary>
        <p>{{ __('accueil.faq.a1') }}</p>
      </details>
      <details class="faq">
        <summary>{{ __('accueil.faq.q2') }}</summary>
        <p>{{ __('accueil.faq.a2') }}</p>
      </details>
      <details class="faq">
        <summary>{{ __('accueil.faq.q3') }}</summary>
        <p>{{ __('accueil.faq.a3') }}</p>
      </details>
      <details class="faq">
        <summary>{{ __('accueil.faq.q4') }}</summary>
        <p>{{ __('accueil.faq.a4') }}</p>
      </details>
      <details class="faq">
        <summary>{{ __('accueil.faq.q5') }}</summary>
        <p>{{ __('accueil.faq.a5_avant') }}<a href="#lancement" style="color:var(--accent);font-weight:700">{{ __('accueil.faq.a5_lien') }}</a>{{ __('accueil.faq.a5_apres') }}</p>
      </details>
    </div>
  </section>

  <section class="wrap">
    <div id="lancement" class="contact">
      <h2>{{ __('accueil.lancement.titre') }}</h2>
      <p>{{ __('accueil.lancement.texte') }}</p>
      <form class="lancement-form" id="lancement-form" novalidate>
        <input type="email" id="lancement-email" name="email" placeholder="{{ __('accueil.lancement.placeholder') }}" required autocomplete="email">
        <button type="submit" id="lancement-submit">{{ __('accueil.lancement.bouton') }}</button>
      </form>
      <p class="lancement-feedback" id="lancement-feedback" role="status" hidden></p>
    </div>
  </section>

  <section class="wrap">
    <div id="contact" class="contact">
      <h2>{{ __('accueil.contact.titre') }}</h2>
      <p>{{ __('accueil.contact.texte') }}</p>
      <div class="copy-row">
        <a class="email-pill" id="email-text" href="mailto:afroliaofficiel@gmail.com">afroliaofficiel@gmail.com</a>
        <button class="copy-btn" id="copy-btn" type="button">{{ __('accueil.contact.copier') }}</button>
      </div>
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">
          <img src="{{ asset('images/afrolia-logo.png') }}" alt="">
          <span>Afrolia</span>
        </div>
        <p class="tagline">{{ __('accueil.footer.tagline') }}</p>
      </div>
      <div>
        <h5>{{ __('accueil.footer.nav_titre') }}</h5>
        <ul>
          <li><a href="#comment-ca-marche">{{ __('accueil.nav.comment_ca_marche') }}</a></li>
          <li><a href="#boutique">{{ __('accueil.nav.boutique') }}</a></li>
          <li><a href="#coiffeuses">{{ __('accueil.nav.coiffeuses') }}</a></li>
          <li><a href="#faq">{{ __('accueil.nav.faq') }}</a></li>
          <li><a href="#contact">{{ __('accueil.nav.contact') }}</a></li>
          <li><a href="{{ url('a-propos') }}">{{ __('accueil.nav.apropos') }}</a></li>
        </ul>
      </div>
      <div>
        <h5>{{ __('accueil.footer.legal_titre') }}</h5>
        <ul>
          <li><a href="{{ url('politique') }}">{{ __('accueil.footer.politique') }}</a></li>
          <li><a href="{{ url('conditions') }}">{{ __('accueil.footer.conditions') }}</a></li>
        </ul>
      </div>
      <div>
        <h5>{{ __('accueil.footer.suivez_titre') }}</h5>
        <div class="social-row">
          <a href="https://www.facebook.com/profile.php?id=61579847484402" target="_blank" rel="noopener" aria-label="Facebook">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor">
              <path d="M13.5 21v-8h2.7l.4-3.1h-3.1V8c0-.9.25-1.5 1.55-1.5H16.7V3.7C16.4 3.65 15.4 3.55 14.2 3.55c-2.4 0-4.05 1.45-4.05 4.15V9.9H7.4V13h2.75v8h3.35z"/>
            </svg>
          </a>
          <a href="https://instagram.com/afrolia7" target="_blank" rel="noopener" aria-label="Instagram">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="3" width="18" height="18" rx="5"/>
              <circle cx="12" cy="12" r="4"/>
              <circle cx="17.3" cy="6.7" r="1.1" fill="currentColor" stroke="none"/>
            </svg>
          </a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>{{ __('accueil.footer.copyright', ['annee' => date('Y')]) }}</p>
      <p>{{ __('accueil.footer.droits') }}</p>
    </div>
  </div>
</footer>

<script>
(function(){
  var form = document.getElementById('lancement-form');
  var input = document.getElementById('lancement-email');
  var submit = document.getElementById('lancement-submit');
  var feedback = document.getElementById('lancement-feedback');
  if (!form) return;

  var texteEnvoi = @json(__('accueil.lancement.envoi'));
  var texteSuccesDefaut = @json(__('accueil.lancement.succes_defaut'));
  var texteErreurDefaut = @json(__('accueil.lancement.erreur_defaut'));
  var texteConnexionImpossible = @json(__('accueil.lancement.connexion_impossible'));

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    var email = input.value.trim();
    feedback.hidden = true;
    feedback.classList.remove('ok', 'err');
    submit.disabled = true;
    var texteOriginal = submit.textContent;
    submit.textContent = texteEnvoi;

    try {
      var tokenMeta = document.querySelector('meta[name="csrf-token"]');
      var reponse = await fetch('/lancement/inscription', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': tokenMeta ? tokenMeta.content : ''
        },
        body: JSON.stringify({ email: email })
      });
      var donnees = await reponse.json().catch(function(){ return {}; });

      feedback.hidden = false;
      if (reponse.ok && donnees.success) {
        feedback.textContent = donnees.message || texteSuccesDefaut;
        feedback.classList.add('ok');
        form.reset();
      } else {
        feedback.textContent = donnees.message || texteErreurDefaut;
        feedback.classList.add('err');
      }
    } catch (err) {
      feedback.hidden = false;
      feedback.textContent = texteConnexionImpossible;
      feedback.classList.add('err');
    } finally {
      submit.disabled = false;
      submit.textContent = texteOriginal;
    }
  });
})();

(function(){
  var btn = document.getElementById('copy-btn');
  var text = document.getElementById('email-text');
  if (!btn || !text) return;
  var texteCopie = @json(__('accueil.contact.copie'));
  var texteSelectionne = @json(__('accueil.contact.selectionne'));
  btn.addEventListener('click', async function(){
    var original = btn.textContent;
    try {
      await navigator.clipboard.writeText(text.textContent.trim());
      btn.textContent = texteCopie;
    } catch (e) {
      try {
        var range = document.createRange();
        range.selectNode(text);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        btn.textContent = texteSelectionne;
      } catch (e2) {
        btn.textContent = 'afroliaofficiel@gmail.com';
      }
    }
    setTimeout(function(){ btn.textContent = original; }, 2000);
  });
})();
</script>
</body>
</html>
