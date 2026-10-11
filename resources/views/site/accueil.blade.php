<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Afrolia</title>
<meta name="description" content="Afrolia — réservez votre coiffeuse ou votre coiffeur en Côte d'Ivoire.">
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
  gap:16px;
  padding-block:12px;
}
.brand{display:flex;align-items:center;gap:10px;min-width:0}
.brand img{width:34px;height:34px;flex:none}
.brand span{font-family:"Fraunces", Georgia, serif;font-weight:600;font-size:1.25rem;color:var(--header-fg)}
.nav-links{display:none;gap:24px;font-size:0.92rem}
.nav-links a{text-decoration:none;opacity:.85}
.nav-links a:hover{opacity:1}
@media (min-width:760px){ .nav-links{display:flex} }
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
      <a href="#comment-ca-marche">Comment ça marche</a>
      <a href="#boutique">Boutique</a>
      <a href="#coiffeuses">Pour les coiffeuses</a>
      <a href="#faq">FAQ</a>
    </nav>
    <a class="nav-cta" href="#contact">Être prévenue du lancement</a>
  </div>
</header>

<main>
  <section class="hero wrap">
    <div class="hero-content">
      <img class="logo" src="{{ asset('images/afrolia-logo.png') }}" alt="Afrolia">
      <span class="badge">Lancement à venir en Côte d'Ivoire</span>
      <h1>Trouvez et réservez votre coiffeuse, simplement</h1>
      <p class="lede">Afrolia met en relation les clientes et les coiffeuses/coiffeurs indépendants en Côte d'Ivoire : comparez les profils, réservez en ligne et payez en toute sécurité.</p>
      <div class="hero-ctas">
        <a class="btn primary" href="#contact">Être prévenue du lancement</a>
        <a class="btn ghost" href="#comment-ca-marche">Voir comment ça marche</a>
      </div>
    </div>
    <div class="hero-photo">
      <img src="{{ asset('images/site/coiffure-tresses.jpg') }}" alt="Une coiffeuse tresse les cheveux de sa cliente" loading="eager">
    </div>
  </section>

  <section id="comment-ca-marche" class="wrap">
    <div class="section-head">
      <span class="eyebrow">Comment ça marche</span>
      <h2>Une expérience pensée pour les deux côtés</h2>
      <p>Simple pour les clientes, efficace pour les coiffeuses.</p>
    </div>
    <div class="tracks">
      <div class="track">
        <div class="track-title"><span class="dot">C</span><h3>Pour les clientes</h3></div>
        <div class="step">
          <span class="num">1</span>
          <div class="step-body">
            <h4>Cherchez une coiffeuse ou un coiffeur</h4>
            <p>Recherchez par nom ou autour de vous, dans votre ville.</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">Recherche</div>
              <div class="m-input">🔍 Rechercher par nom...</div>
              <div class="m-row"><span class="m-avatar"></span><div class="m-lines"><span class="m-line w70"></span><span class="m-line w40 muted"></span></div></div>
              <div class="m-row"><span class="m-avatar"></span><div class="m-lines"><span class="m-line w50"></span><span class="m-line w30 muted"></span></div></div>
            </div>
          </div>
        </div>
        <div class="step">
          <span class="num">2</span>
          <div class="step-body">
            <h4>Comparez les profils</h4>
            <p>Prestations, tarifs, disponibilités et avis clients avant de réserver.</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">Profil</div>
              <div class="m-row"><span class="m-avatar"></span><div class="m-lines"><span class="m-line w60"></span><span class="m-line w40 muted"></span></div></div>
              <div class="m-price-row"><span class="m-line w40"></span><span class="m-price">—</span></div>
              <div class="m-price-row"><span class="m-line w50"></span><span class="m-price">—</span></div>
            </div>
          </div>
        </div>
        <div class="step">
          <span class="num">3</span>
          <div class="step-body">
            <h4>Réservez et payez en sécurité</h4>
            <p>Par carte bancaire ou Mobile Money (Wave, Orange, MTN, Moov, Djamo), directement dans l'application.</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">Mobile Money</div>
              <div class="m-chips">
                <span class="m-chip" style="background:#00A9E0"></span>
                <span class="m-chip" style="background:#FF6600"></span>
                <span class="m-chip" style="background:#FFCC00"></span>
                <span class="m-chip" style="background:#0066B3"></span>
              </div>
              <div class="m-amount"><div class="label">Montant à payer</div><div class="value">—</div></div>
              <div class="m-btn">Payer</div>
            </div>
          </div>
        </div>
      </div>
      <div class="track">
        <div class="track-title"><span class="dot">P</span><h3>Pour les coiffeuses</h3></div>
        <div class="step">
          <span class="num">1</span>
          <div class="step-body">
            <h4>Créez votre profil professionnel</h4>
            <p>Gratuitement : vos prestations, vos tarifs, vos photos.</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">Mes tarifs et services</div>
              <div class="m-price-row"><span class="m-line w50"></span><span class="m-price">—</span></div>
              <div class="m-price-row"><span class="m-line w60"></span><span class="m-price">—</span></div>
              <div class="m-btn">+ Ajouter</div>
            </div>
          </div>
        </div>
        <div class="step">
          <span class="num">2</span>
          <div class="step-body">
            <h4>Gérez votre agenda</h4>
            <p>Vos disponibilités se mettent à jour en temps réel dans l'application.</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">Disponibilités</div>
              <div class="m-cal">
                <span class="on"></span><span class="on"></span><span></span><span class="on"></span><span></span><span class="on"></span><span></span>
                <span></span><span class="on"></span><span class="on"></span><span></span><span class="on"></span><span></span><span class="on"></span>
              </div>
              <div class="m-legend"><span class="m-dot" style="background:var(--accent2)"></span>Disponible</div>
            </div>
          </div>
        </div>
        <div class="step">
          <span class="num">3</span>
          <div class="step-body">
            <h4>Recevez vos réservations</h4>
            <p>Acceptez les demandes et développez votre clientèle.</p>
          </div>
          <div class="phone-mock">
            <div class="notch"></div>
            <div class="phone-screen">
              <div class="m-bar">En attente</div>
              <div class="m-row"><span class="m-avatar"></span><div class="m-lines"><span class="m-line w60"></span><span class="m-line w40 muted"></span></div></div>
              <div class="m-actions"><span class="refuse">Refuser</span><span class="confirm">Confirmer</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="wrap">
    <div class="section-head">
      <span class="eyebrow">Côté clientes</span>
      <h2>Pensé pour réserver l'esprit tranquille</h2>
    </div>
    <div class="cards">
      <div class="card">
        <div class="icon">💳</div>
        <h4>Paiement sécurisé, deux façons</h4>
        <p>Carte bancaire ou Mobile Money (Wave, Orange, MTN, Moov, Djamo) — à vous de choisir.</p>
      </div>
      <div class="card">
        <div class="icon">⭐</div>
        <h4>Avis de clientes</h4>
        <p>Les avis laissés après chaque prestation vous aident à choisir en confiance.</p>
      </div>
      <div class="card">
        <div class="icon">💬</div>
        <h4>Un assistant pour vous aider</h4>
        <p>Posez vos questions à l'assistant intégré pour vous orienter dans l'application.</p>
      </div>
    </div>
  </section>

  <section id="boutique" class="wrap">
    <div class="section-head">
      <span class="eyebrow">Boutique</span>
      <h2>Vos produits capillaires préférés, à portée de main</h2>
      <p>Chaque coiffeuse peut ouvrir sa propre boutique dans l'application, en plus de ses prestations.</p>
    </div>
    <div class="track" style="max-width:640px;margin:0 auto">
      <div class="step">
        <span class="num">1</span>
        <div class="step-body">
          <h4>Parcourez les produits de votre coiffeuse</h4>
          <p>Shampoings, soins, accessoires : chaque coiffeuse présente ses propres produits, avec prix et stock disponible.</p>
        </div>
      </div>
      <div class="step">
        <span class="num">2</span>
        <div class="step-body">
          <h4>Commandez et payez en toute sécurité</h4>
          <p>Même paiement que pour une réservation : par carte bancaire ou Mobile Money, directement dans l'application.</p>
        </div>
      </div>
      <div class="step">
        <span class="num">3</span>
        <div class="step-body">
          <h4>Suivez votre commande jusqu'à la livraison</h4>
          <p>Commande confirmée, expédiée puis livrée par la coiffeuse — suivi visible à chaque étape dans l'application.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="coiffeuses" class="wrap">
    <div class="section-head">
      <span class="eyebrow">Côté coiffeuses</span>
      <h2>Développez votre activité, à votre rythme</h2>
      <p>Commencez gratuitement, passez à la formule Premium quand vous voulez. La cliente paie toujours le prix affiché, sans supplément.</p>
    </div>
    <div class="pricing">
      <div class="price-card">
        <span class="tag">Gratuit</span>
        <div class="amount">0 <small>FCFA / mois</small></div>
        <ul>
          <li>Profil professionnel complet</li>
          <li>Agenda et réservations illimitées</li>
          <li>8 % de frais retenus sur vos gains par prestation réalisée (commission Afrolia et frais de paiement inclus)</li>
        </ul>
      </div>
      <div class="price-card featured">
        <span class="tag">Premium</span>
        <div class="amount">4 500 <small>FCFA / mois</small></div>
        <ul>
          <li>Tout ce qui est inclus dans Gratuit</li>
          <li>Frais réduits à 6 % par prestation réalisée</li>
          <li>Résiliable à tout moment</li>
        </ul>
      </div>
    </div>
    <p style="color:var(--fg-mute);font-size:0.85rem;text-align:center;max-width:46ch;margin:18px auto 0">
      En cas d'annulation, des frais restent applicables : la commission Afrolia reste due, à la charge de la personne responsable de l'annulation (cliente ou coiffeuse). Détails dans nos conditions générales.
    </p>
  </section>

  <section class="wrap values">
    <div class="section-head">
      <span class="eyebrow">Notre ADN</span>
      <h2>Pourquoi Afrolia</h2>
    </div>
    <div class="cards">
      <div class="card">
        <div class="icon">❤️</div>
        <h4>Notre mission</h4>
        <p>Rendre la réservation d'une prestation de coiffure aussi simple qu'un rendez-vous pris en quelques clics.</p>
      </div>
      <div class="card">
        <div class="icon">🇨🇮</div>
        <h4>Notre terrain</h4>
        <p>Pensée pour la Côte d'Ivoire : paiement par carte ou par Mobile Money, comme on paie déjà au quotidien.</p>
      </div>
      <div class="card">
        <div class="icon">🎯</div>
        <h4>Notre objectif</h4>
        <p>Devenir la référence pour réserver une coiffeuse ou un coiffeur en Côte d'Ivoire.</p>
      </div>
    </div>
  </section>

  <section class="wrap">
    <div class="section-head">
      <span class="eyebrow">Qui sommes-nous</span>
      <h2>Afrolia Expertise SARLU</h2>
    </div>
    <div class="track" style="max-width:640px;margin:0 auto">
      <p style="color:var(--fg-mute);font-size:0.98rem">
        Afrolia est une application mobile qui met en relation des clientes et des coiffeuses/coiffeurs indépendants en Côte d'Ivoire, pour la prise de rendez-vous et l'achat de produits de coiffure. Notre objectif : rendre la réservation d'une prestation de coiffure aussi simple qu'un rendez-vous pris en quelques clics, pour les clientes comme pour les professionnelles.
      </p>
      <div class="duo-photos" style="margin-top:22px">
        <img src="{{ asset('images/site/coiffure-trio-1.jpg') }}" alt="Trois clientes avec des coiffures afro différentes" loading="lazy" style="object-position:25% 55%">
        <img src="{{ asset('images/site/coiffure-trio-2.jpg') }}" alt="Trois clientes, cheveux afro naturels et tresses colorées" loading="lazy">
      </div>
    </div>
  </section>

  <section id="faq" class="wrap">
    <div class="section-head">
      <span class="eyebrow">FAQ</span>
      <h2>Questions fréquentes</h2>
    </div>
    <div style="max-width:640px;margin:0 auto">
      <details class="faq">
        <summary>Comment réserver un rendez-vous ?</summary>
        <p>Téléchargez l'application, créez votre compte, recherchez une coiffeuse ou un coiffeur près de chez vous, consultez ses disponibilités et réservez en quelques clics.</p>
      </details>
      <details class="faq">
        <summary>Comment fonctionne le paiement ?</summary>
        <p>Vous payez directement dans l'application, par carte bancaire ou par Mobile Money (Wave, Orange, MTN, Moov, Djamo), au moment de la réservation.</p>
      </details>
      <details class="faq">
        <summary>Puis-je annuler ou modifier ma réservation ?</summary>
        <p>Oui, depuis l'application, dans les délais indiqués au moment de la réservation.</p>
      </details>
      <details class="faq">
        <summary>L'application est-elle gratuite ?</summary>
        <p>Oui pour les clientes. Les coiffeuses ont le choix entre une formule gratuite et une formule Premium à petit prix, avec une commission réduite.</p>
      </details>
      <details class="faq">
        <summary>Où et quand l'application sera-t-elle disponible ?</summary>
        <p>Afrolia est en cours de finalisation pour la Côte d'Ivoire. Laissez-nous votre e-mail pour être prévenue dès le lancement.</p>
      </details>
    </div>
  </section>

  <section class="wrap">
    <div id="contact" class="contact">
      <h2>Une question, une envie de collaborer ?</h2>
      <p>Écrivez-nous, nous vous répondons rapidement.</p>
      <div class="copy-row">
        <a class="email-pill" id="email-text" href="mailto:afroliaofficiel@gmail.com">afroliaofficiel@gmail.com</a>
        <button class="copy-btn" id="copy-btn" type="button">Copier l'e-mail</button>
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
        <p class="tagline">Réservez votre coiffeuse ou votre coiffeur en Côte d'Ivoire, simplement.</p>
      </div>
      <div>
        <h5>Navigation</h5>
        <ul>
          <li><a href="#comment-ca-marche">Comment ça marche</a></li>
          <li><a href="#boutique">Boutique</a></li>
          <li><a href="#coiffeuses">Pour les coiffeuses</a></li>
          <li><a href="#faq">FAQ</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </div>
      <div>
        <h5>Légal</h5>
        <ul>
          <li><span>Politique de confidentialité</span><div class="legal-soon">bientôt en ligne</div></li>
          <li><span>Conditions générales</span><div class="legal-soon">bientôt en ligne</div></li>
        </ul>
      </div>
      <div>
        <h5>Suivez-nous</h5>
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
      <p>© {{ date('Y') }} Afrolia Expertise SARLU — Côte d'Ivoire</p>
      <p>Tous droits réservés</p>
    </div>
  </div>
</footer>

<script>
(function(){
  var btn = document.getElementById('copy-btn');
  var text = document.getElementById('email-text');
  if (!btn || !text) return;
  btn.addEventListener('click', async function(){
    var original = btn.textContent;
    try {
      await navigator.clipboard.writeText(text.textContent.trim());
      btn.textContent = 'Copié !';
    } catch (e) {
      try {
        var range = document.createRange();
        range.selectNode(text);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        btn.textContent = 'Sélectionné';
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
