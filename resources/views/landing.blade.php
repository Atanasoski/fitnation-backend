<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Fit Nation: The Movement | Train Smarter. Track Everything.') }}</title>
<meta name="description" content="{{ __("Structured plans, live workout tracking, and personal records. Fit Nation: The Movement is the fitness app built for anyone who wants to improve, wherever they're starting from.") }}">
<link rel="canonical" href="{{ url()->current() }}">
<link rel="alternate" hreflang="en" href="{{ route('landing') }}">
<link rel="alternate" hreflang="mk" href="{{ route('landing.mk') }}">
<link rel="alternate" hreflang="x-default" href="{{ route('landing') }}">
<meta name="theme-color" content="#00B4C5">

<!-- iOS Safari smart app banner -->
<meta name="apple-itunes-app" content="app-id=6766201705">

<!-- Social sharing -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Fit Nation: The Movement">
<meta property="og:locale" content="{{ app()->getLocale() === 'mk' ? 'mk_MK' : 'en_US' }}">
<meta property="og:title" content="{{ __('Fit Nation: The Movement | Train Smarter. Track Everything.') }}">
<meta property="og:description" content="{{ __('Structured plans, live workout tracking, and personal records. Free on iOS & Android.') }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('images/landing/og-image.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ __('Fit Nation: The Movement | Train Smarter. Track Everything.') }}">
<meta name="twitter:description" content="{{ __('Structured plans, live workout tracking, and personal records. Free on iOS & Android.') }}">
<meta name="twitter:image" content="{{ asset('images/landing/og-image.png') }}">

<!-- Icons -->
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/landing/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/landing/apple-touch-icon.png') }}">

<!-- Structured data -->
@verbatim
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "MobileApplication",
  "name": "Fit Nation: The Movement",
  "alternateName": "FitNation",
  "operatingSystem": "iOS, Android",
  "applicationCategory": "HealthApplication",
  "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" },
  "installUrl": [
    "https://apps.apple.com/mk/app/fit-nation-the-movement/id6766201705",
    "https://play.google.com/store/apps/details?id=com.fitnation.app"
  ]
}
</script>
@endverbatim

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --white: #ffffff;
  --bg: #F4F7FF;
  --surface: #ffffff;
  --teal: #00B4C5;
  --teal-dark: #008D9B;
  --teal-lo: rgba(0,180,197,0.10);
  --teal-lo2: rgba(0,180,197,0.06);
  --orange: #F97316;
  --orange-lo: rgba(249,115,22,0.10);
  --dark: #0D1117;
  --text: #1C2333;
  --muted: #64748B;
  --border: #E2E8F0;
  --shadow-sm: 0 1px 4px rgba(0,0,0,0.06);
  --shadow: 0 4px 20px rgba(0,0,0,0.08);
  --shadow-lg: 0 16px 48px rgba(0,0,0,0.12);
  --r: 16px;
  --r-sm: 10px;
  --font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
}

html { scroll-behavior: smooth; }

body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
}

/* ── UTILS ── */
.wrap { max-width: 1100px; margin: 0 auto; padding: 0 24px; }
.teal { color: var(--teal); }
.orange { color: var(--orange); }
.eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: 12px; font-weight: 700; letter-spacing: 0.12em;
  text-transform: uppercase; color: var(--teal);
  background: var(--teal-lo); border-radius: 100px;
  padding: 5px 14px; margin-bottom: 18px;
}
.eyebrow-orange {
  color: var(--orange); background: var(--orange-lo);
}

/* ── NAV ── */
nav {
  position: sticky; top: 0; z-index: 100;
  background: rgba(255,255,255,0.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  padding: 0;
}
.nav-inner {
  display: flex; align-items: center; justify-content: space-between;
  height: 64px;
}
.nav-logo {
  display: flex; align-items: center; gap: 10px;
  text-decoration: none; color: var(--dark);
}
/* nav-logo-mark replaced by img */
.nav-logo-text { font-size: 18px; font-weight: 800; letter-spacing: -0.02em; }
.nav-logo-sub { font-weight: 600; color: var(--muted); }
@media (max-width: 720px) {
  .nav-logo-sub { display: none; }
}
.nav-cta {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--teal); color: #fff; font-weight: 700;
  font-size: 14px; padding: 10px 20px; border-radius: 100px;
  text-decoration: none; transition: background 0.18s, transform 0.15s;
}
.nav-cta:hover { background: var(--teal-dark); transform: translateY(-1px); }
.nav-right { display: flex; align-items: center; gap: 18px; }
.nav-login { font-size: 14px; font-weight: 600; color: var(--muted); text-decoration: none; }
.nav-login:hover { color: var(--dark); }

/* ── HERO ── */
.hero {
  padding: 80px 0 0;
  background: var(--white);
  overflow: hidden;
}
.hero-inner {
  display: grid; grid-template-columns: 1fr 420px;
  gap: 48px; align-items: flex-end;
}
.hero-copy { padding-bottom: 80px; }
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--teal-lo); border-radius: 100px;
  padding: 6px 14px 6px 10px; margin-bottom: 28px;
  font-size: 13px; font-weight: 600; color: var(--teal);
}
.hero-badge-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--teal); flex-shrink: 0;
}
h1 {
  font-size: clamp(40px, 5.5vw, 66px);
  font-weight: 900; line-height: 1.05;
  letter-spacing: -0.03em; color: var(--dark);
  text-wrap: balance; margin-bottom: 20px;
}
h1 .line-accent { color: var(--teal); display: block; }
.hero-sub {
  font-size: clamp(16px, 1.8vw, 19px); color: var(--muted);
  max-width: 480px; line-height: 1.65; margin-bottom: 36px;
}
.hero-btns { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 40px; }

/* Store buttons */
.store-btn {
  display: inline-flex; align-items: center; gap: 12px;
  padding: 13px 22px; background: var(--dark);
  border-radius: var(--r-sm); text-decoration: none;
  color: #fff; transition: transform 0.15s, box-shadow 0.15s;
  box-shadow: var(--shadow);
}
.store-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.store-btn .store-text small {
  display: block; font-size: 10px; font-weight: 400;
  color: rgba(255,255,255,0.65); line-height: 1;
}
.store-btn .store-text strong {
  display: block; font-size: 17px; font-weight: 700;
  letter-spacing: -0.01em; line-height: 1.2;
}
.store-logo { flex-shrink: 0; }
.play-logo { flex-shrink: 0; }

.hero-trust {
  display: flex; align-items: center; gap: 10px;
  font-size: 13px; color: var(--muted);
}
.hero-trust-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--border); }
.hero-trust span { font-weight: 600; color: var(--text); }

/* Phone mockup (hero) */
.hero-phone { position: relative; display: flex; justify-content: center; }

/* ── Realistic titanium phone frame (shared: hero / feature / library) ── */
.phone-wrap      { --r: 42px; --pad: 11px; width: 220px; }
.feat-phone-wrap { --r: 38px; --pad: 10px; width: 200px; }
.lib-phone-wrap  { --r: 38px; --pad: 10px; width: 200px; }

.phone-wrap, .feat-phone-wrap, .lib-phone-wrap {
  position: relative;
  padding: var(--pad);
  border-radius: var(--r);
  background: #0a0a0c;                 /* black bezel */
  box-shadow: 0 30px 70px rgba(0,0,0,0.30), 0 2px 6px rgba(0,0,0,0.25);
}
/* brushed-titanium outer rail */
.phone-wrap::before, .feat-phone-wrap::before, .lib-phone-wrap::before {
  content: "";
  position: absolute;
  inset: -3px;
  z-index: -1;
  border-radius: calc(var(--r) + 3px);
  background: linear-gradient(135deg,
    #efece2 0%, #c7c0b0 24%, #a9a293 42%, #f3efe5 52%,
    #a9a293 62%, #ccc5b5 82%, #efece2 100%);
}
/* screen */
.phone-screen, .feat-phone-screen, .lib-screen {
  position: relative;
  border-radius: calc(var(--r) - 9px);
  overflow: hidden;
  background: #000;
}
.phone-screen img, .feat-phone-screen img, .lib-screen img { width: 100%; height: auto; display: block; }
/* notch (with earpiece + front camera) */
.phone-notch, .feat-phone-notch, .lib-notch {
  position: absolute;
  top: var(--pad);
  left: 50%;
  transform: translateX(-50%);
  width: 42%;
  height: 18px;
  background: #000;
  border-radius: 0 0 13px 13px;
  z-index: 3;
}
.phone-notch::before, .feat-phone-notch::before, .lib-notch::before {
  content: ""; position: absolute; top: 7px; left: 50%;
  transform: translateX(-72%);
  width: 24px; height: 4px; border-radius: 3px; background: #17171a;
}
.phone-notch::after, .feat-phone-notch::after, .lib-notch::after {
  content: ""; position: absolute; top: 6px; right: 15px;
  width: 6px; height: 6px; border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #3c5074, #0b0e15 70%);
}
/* titanium side buttons */
.pbtn {
  position: absolute; z-index: 1; border-radius: 2px;
  background: linear-gradient(90deg, #d9d3c4, #b1aa9a);
}
.pbtn-silent { left: -4px; top: 18%; width: 3px; height: 3.5%; }
.pbtn-vup    { left: -4px; top: 26%; width: 3px; height: 8%; }
.pbtn-vdown  { left: -4px; top: 37%; width: 3px; height: 8%; }
.pbtn-power  { right: -4px; top: 30%; width: 3px; height: 11%; }

/* floating highlights on hero phone */
.phone-float {
  position: absolute;
  background: var(--white);
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  padding: 10px 14px;
  font-size: 12px; font-weight: 700; color: var(--dark);
  white-space: nowrap;
  border: 1px solid var(--border);
}
.phone-float.top-right {
  top: 40px; right: -50px;
}
.phone-float.bottom-left {
  bottom: 80px; left: -50px;
}
.float-val { font-size: 18px; font-weight: 900; color: var(--teal); line-height: 1; margin-bottom: 2px; }
.float-val-orange { color: var(--orange); }

/* ── SECTION BASE ── */
section { padding: 100px 0; }
.sec-head { text-align: center; max-width: 600px; margin: 0 auto 64px; }
h2 {
  font-size: clamp(30px, 4vw, 46px); font-weight: 900;
  letter-spacing: -0.025em; color: var(--dark); line-height: 1.1;
  text-wrap: balance; margin-bottom: 14px;
}
.sec-desc {
  font-size: 17px; color: var(--muted); line-height: 1.65;
}

/* ── FEATURES ── */
.features { background: var(--bg); }
.feature-row {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 64px; align-items: center; margin-bottom: 100px;
}
.feature-row:last-child { margin-bottom: 0; }
.feature-row.flip { direction: rtl; }
.feature-row.flip > * { direction: ltr; }

.feature-phone {
  display: flex; justify-content: center;
}
/* .feat-phone-* frame styling lives in the shared titanium-frame block above */

.feature-copy { max-width: 440px; }
.feat-icon {
  width: 52px; height: 52px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 20px; font-size: 24px;
}
.feat-icon-teal { background: var(--teal-lo); }
.feat-icon-orange { background: var(--orange-lo); }

h3 {
  font-size: clamp(22px, 2.5vw, 30px); font-weight: 800;
  letter-spacing: -0.02em; color: var(--dark);
  line-height: 1.15; margin-bottom: 14px;
}
.feat-desc {
  font-size: 16px; color: var(--muted); line-height: 1.7;
  margin-bottom: 24px;
}
.feat-bullets { list-style: none; display: flex; flex-direction: column; gap: 10px; }
.feat-bullets li {
  display: flex; align-items: flex-start; gap: 10px;
  font-size: 15px; color: var(--text); font-weight: 500;
}
.bullet-dot {
  width: 20px; height: 20px; border-radius: 50%;
  background: var(--teal-lo); display: flex; align-items: center;
  justify-content: center; flex-shrink: 0; margin-top: 2px;
}
.bullet-dot svg { width: 10px; height: 10px; stroke: var(--teal); stroke-width: 2.5; fill: none; }
.bullet-dot-orange { background: var(--orange-lo); }
.bullet-dot-orange svg { stroke: var(--orange); }

/* ── HOW IT WORKS ── */
.how { background: var(--white); }
.steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
.step {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r); padding: 32px 28px;
  box-shadow: var(--shadow-sm);
  position: relative; overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}
.step:hover { transform: translateY(-4px); box-shadow: var(--shadow); }
.step-num {
  font-size: 72px; font-weight: 900; letter-spacing: -0.04em;
  color: var(--teal-lo); line-height: 1;
  position: absolute; top: 16px; right: 20px;
  user-select: none;
}
.step-icon {
  width: 48px; height: 48px; border-radius: 12px;
  background: var(--teal-lo); display: flex;
  align-items: center; justify-content: center;
  margin-bottom: 20px; font-size: 22px;
}
.step h3 {
  font-size: 20px; margin-bottom: 10px;
}
.step p {
  font-size: 14px; color: var(--muted); line-height: 1.65;
}

/* ── LIBRARY STRIP ── */
.library-strip { background: var(--bg); padding: 80px 0; }
.library-inner {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 64px; align-items: center;
}
.lib-stat {
  display: inline-flex; align-items: baseline; gap: 8px;
  margin-bottom: 16px;
}
.lib-num {
  font-size: 96px; font-weight: 900;
  letter-spacing: -0.04em; color: var(--dark); line-height: 1;
}
.lib-unit {
  font-size: 32px; font-weight: 700; color: var(--teal);
}
.lib-phone { display: flex; justify-content: center; }
/* .lib-* frame styling lives in the shared titanium-frame block above */

/* ── DOWNLOAD CTA ── */
.download { background: var(--dark); padding: 100px 0; text-align: center; }
.dl-inner { max-width: 600px; margin: 0 auto; }
.download h2 { color: #fff; margin-bottom: 14px; }
.download p { color: rgba(255,255,255,0.6); font-size: 17px; margin-bottom: 40px; }
.dl-btns { display: flex; justify-content: center; flex-wrap: wrap; gap: 14px; }
.dl-qr {
  display: inline-flex; align-items: center; gap: 18px;
  margin-top: 40px; padding: 16px 22px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: var(--r-sm); text-align: left;
}
.dl-qr img {
  display: block; width: 116px; height: 116px;
  background: #fff; border-radius: 8px; padding: 8px;
}
.dl-qr-text { font-size: 14px; color: rgba(255,255,255,0.65); max-width: 230px; line-height: 1.5; }
.dl-qr-text strong { display: block; color: #fff; font-size: 15px; margin-bottom: 4px; }
@media (max-width: 860px) {
  .dl-qr { display: none; }
}
.dl-btn {
  display: inline-flex; align-items: center; gap: 12px;
  padding: 14px 24px; background: rgba(255,255,255,0.1);
  border: 1.5px solid rgba(255,255,255,0.15);
  border-radius: var(--r-sm); text-decoration: none;
  color: #fff; transition: background 0.18s, transform 0.15s, border-color 0.18s;
}
.dl-btn:hover {
  background: rgba(255,255,255,0.16);
  border-color: rgba(255,255,255,0.3);
  transform: translateY(-2px);
}
.dl-btn .store-text small {
  display: block; font-size: 10px; font-weight: 400;
  color: rgba(255,255,255,0.6); line-height: 1;
}
.dl-btn .store-text strong {
  display: block; font-size: 17px; font-weight: 700;
  letter-spacing: -0.01em; line-height: 1.2;
}

/* ── FOOTER ── */
footer {
  background: var(--dark); border-top: 1px solid rgba(255,255,255,0.07);
  padding: 40px 0;
}
.foot-inner {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 16px;
}
.foot-logo { display: flex; align-items: center; gap: 10px; }
.foot-logo-mark {
  width: 30px; height: 30px; border-radius: 7px;
  background: linear-gradient(135deg, var(--teal), var(--teal-dark));
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 900; color: #fff;
}
.foot-logo-text { font-size: 16px; font-weight: 700; color: #fff; }
.foot-copy { font-size: 13px; color: rgba(255,255,255,0.35); }
.foot-links { display: flex; align-items: center; gap: 20px; }
.foot-links a { font-size: 13px; color: rgba(255,255,255,0.55); text-decoration: none; }
.foot-links a:hover { color: #fff; }

/* ── RESPONSIVE ── */
/* ── TABLET ── */
@media (max-width: 860px) {
  section { padding: 64px 0; }
  .hero { padding: 48px 0 0; }

  /* Hero: stack copy above, phone below */
  .hero-inner {
    grid-template-columns: 1fr;
    gap: 40px;
    text-align: center;
  }
  .hero-copy { padding-bottom: 0; order: 1; }
  .hero-sub { margin-left: auto; margin-right: auto; }
  .hero-btns { justify-content: center; }
  .hero-trust { justify-content: center; flex-wrap: wrap; }
  .hero-badge { margin-left: auto; margin-right: auto; display: inline-flex; }

  /* Hero phone: show centered, smaller, no floating badges */
  .hero-phone { order: 2; }
  .phone-float { display: none; }
  .phone-wrap { width: 180px; }

  /* Feature rows: screenshot above, text below, no rtl tricks */
  .feature-row,
  .feature-row.flip {
    display: flex;
    flex-direction: column;
    direction: ltr;
    gap: 32px;
    align-items: center;
    text-align: center;
    margin-bottom: 64px;
  }
  .feature-row:last-child { margin-bottom: 0; }
  .feature-phone { order: 1; }
  .feature-copy { order: 2; max-width: 100%; }
  .feat-phone-wrap { width: 180px; }
  .feat-bullets { align-items: flex-start; text-align: left; }

  /* Steps: single column */
  .steps { grid-template-columns: 1fr; gap: 16px; }

  /* Library: stack, phone above */
  .library-inner {
    display: flex;
    flex-direction: column;
    gap: 32px;
    align-items: center;
    text-align: center;
  }
  .lib-phone { order: 1; }
  .library-inner > div:not(.lib-phone) { order: 2; }
  .lib-phone-wrap { width: 180px; }
  .lib-num { font-size: 72px; }
  .sec-desc[style] { text-align: center !important; }

  /* Footer */
  .foot-inner { flex-direction: column; align-items: center; text-align: center; gap: 12px; }
}

/* ── MOBILE ── */
@media (max-width: 480px) {
  .wrap { padding: 0 18px; }
  section { padding: 52px 0; }
  .hero { padding: 40px 0 0; }

  h1 { font-size: 36px; }
  h2 { font-size: 28px; }
  h3 { font-size: 20px; }

  /* Store buttons: stack full-width on very small screens */
  .hero-btns { flex-direction: column; align-items: center; }
  .store-btn, .dl-btn {
    width: 100%;
    max-width: 240px;
    justify-content: center;
  }
  .dl-btns { flex-direction: column; align-items: center; }

  /* Nav: compact single line down to 320px */
  .nav-inner { height: 52px; }
  .nav-logo { gap: 8px; }
  .nav-logo img { width: 28px !important; height: 28px !important; }
  .nav-logo-text { font-size: 15px; white-space: nowrap; }
  .nav-login { font-size: 13px; white-space: nowrap; }
  .nav-cta { font-size: 12px; padding: 8px 12px; white-space: nowrap; }
  .nav-cta svg { display: none; }
  .nav-right { gap: 10px; }

  /* Phone sizes */
  .phone-wrap { width: 160px; }
  .feat-phone-wrap { width: 160px; }
  .lib-phone-wrap { width: 160px; }

  /* Steps */
  .step { padding: 24px 20px; }
  .step-num { font-size: 56px; }

  /* Download section */
  .download { padding: 72px 0; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
}
</style>


</head>
<body>
<!-- NAV -->
<nav>
  <div class="wrap">
    <div class="nav-inner">
      <a href="{{ app()->getLocale() === 'mk' ? route('landing.mk') : route('landing') }}" class="nav-logo">
        <img src="{{ asset('images/landing/logo.png') }}" alt="Fit Nation logo" style="width:36px;height:36px;object-fit:contain;display:block;">
        <span class="nav-logo-text">Fit Nation<span class="nav-logo-sub">: The Movement</span></span>
      </a>
      <div class="nav-right">
        <a href="{{ app()->getLocale() === 'mk' ? route('landing') : route('landing.mk') }}" class="nav-login" aria-label="Switch language">{{ app()->getLocale() === 'mk' ? 'EN' : 'МК' }}</a>
        <a href="#download" class="nav-cta">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16l-6-6h4V4h4v6h4l-6 6z"/><path d="M4 20h16"/></svg>
          {{ __('Get the app') }}
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="wrap">
    <div class="hero-inner">
      <div class="hero-copy">
        <div class="hero-badge">
          <div class="hero-badge-dot"></div>
          {{ __('Your personal fitness app') }}
        </div>
        <h1>{{ __('Train smarter.') }}<br><span class="line-accent">{{ __('Track everything.') }}</span></h1>
        <p class="hero-sub">{{ __("Structured workout plans, live session tracking, and personal records. All in one app. Whether you're on day one or chasing a new PR, Fit Nation keeps you moving forward.") }}</p>
        <div class="hero-btns">
          <a href="https://apps.apple.com/mk/app/fit-nation-the-movement/id6766201705" target="_blank" rel="noopener" class="store-btn" aria-label="Download on the App Store">
            <svg class="store-logo" width="22" height="27" viewBox="0 0 24 24" fill="white">
              <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
            </svg>
            <div class="store-text">
              <small>{{ __('Download on the') }}</small>
              <strong>App Store</strong>
            </div>
          </a>
          <a href="https://play.google.com/store/apps/details?id=com.fitnation.app" target="_blank" rel="noopener" class="store-btn" aria-label="Get it on Google Play">
            <svg class="play-logo" width="22" height="24" viewBox="0 0 24 26">
              <path d="M1.5 0.75 L13.5 12.75 L1.5 24.75 Q0.75 24.375 0.75 23.25 L0.75 2.25 Q0.75 1.125 1.5 0.75Z" fill="#4285F4"/>
              <path d="M18 8.25 L13.5 12.75 L1.5 0.75 Q2.25 0.375 3.375 1.125 Z" fill="#EA4335"/>
              <path d="M18 17.25 L3.375 24.375 Q2.25 25.125 1.5 24.75 L13.5 12.75 Z" fill="#34A853"/>
              <path d="M22.5 12.75 Q22.5 13.875 18 17.25 L13.5 12.75 L18 8.25 Q22.5 11.625 22.5 12.75Z" fill="#FBBC04"/>
            </svg>
            <div class="store-text">
              <small>{{ __('Get it on') }}</small>
              <strong>Google Play</strong>
            </div>
          </a>
        </div>
        <div class="hero-trust">
          <span>{{ __('Free to download') }}</span>
          <div class="hero-trust-dot"></div>
          <span>iOS &amp; Android</span>
          <div class="hero-trust-dot"></div>
          <span>{{ $exerciseCount }} {{ __('exercises') }}</span>
        </div>
      </div>
      <div class="hero-phone">
        <div style="position:relative;">
          <div class="phone-wrap">
            <span class="pbtn pbtn-silent"></span>
            <span class="pbtn pbtn-vup"></span>
            <span class="pbtn pbtn-vdown"></span>
            <span class="pbtn pbtn-power"></span>
            <div class="phone-notch"></div>
            <div class="phone-screen">
              <img src="{{ asset('images/landing/screen-dashboard.jpg') }}" alt="Fit Nation dashboard" width="500" height="1115" loading="eager" fetchpriority="high" decoding="async">
            </div>
          </div>
          <div class="phone-float top-right">
            <div class="float-val">{{ $exerciseCount }}</div>
            <div>{{ __('exercises') }}</div>
          </div>
          <div class="phone-float bottom-left">
            <div class="float-val float-val-orange">{{ __('New PR!') }}</div>
            <div>{{ __('personal record') }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features" id="features">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">{{ __('Everything you need') }}</div>
      <h2>{{ __('Built around the way you actually train') }}</h2>
      <p class="sec-desc">{{ __('No clutter, no guesswork. Every feature is here because real workouts need it.') }}</p>
    </div>

    <!-- Feature 1: Plans -->
    <div class="feature-row">
      <div class="feature-phone">
        <div class="feat-phone-wrap">
          <span class="pbtn pbtn-silent"></span>
          <span class="pbtn pbtn-vup"></span>
          <span class="pbtn pbtn-vdown"></span>
          <span class="pbtn pbtn-power"></span>
          <div class="feat-phone-notch"></div>
          <div class="feat-phone-screen">
            <img src="{{ asset('images/landing/screen-dashboard.jpg') }}" alt="Workout dashboard" width="500" height="1115" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
      <div class="feature-copy">
        <div class="feat-icon feat-icon-teal">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00B4C5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="12" y2="14"/><line x1="8" y1="18" x2="8" y2="18"/><line x1="12" y1="18" x2="12" y2="18"/></svg>
        </div>
        <div class="eyebrow">{{ __('Workout plans') }}</div>
        <h3>{{ __('Your plan, ready and waiting') }}</h3>
        <p class="feat-desc">{{ __("Walk into every session knowing exactly what you're doing. Structured plans tell you each exercise, set, and rep. No decisions, just work.") }}</p>
        <ul class="feat-bullets">
          <li>
            <div class="bullet-dot"><svg viewBox="0 0 10 10"><polyline points="2,5 4,7 8,3"/></svg></div>
            {{ __('Plans tailored to your goals and level') }}
          </li>
          <li>
            <div class="bullet-dot"><svg viewBox="0 0 10 10"><polyline points="2,5 4,7 8,3"/></svg></div>
            {{ __('Day-by-day sessions, always ready to start') }}
          </li>
          <li>
            <div class="bullet-dot"><svg viewBox="0 0 10 10"><polyline points="2,5 4,7 8,3"/></svg></div>
            {{ __('Never wonder what to do next') }}
          </li>
        </ul>
      </div>
    </div>

    <!-- Feature 2: Live tracking (flip) -->
    <div class="feature-row flip">
      <div class="feature-phone">
        <div class="feat-phone-wrap">
          <span class="pbtn pbtn-silent"></span>
          <span class="pbtn pbtn-vup"></span>
          <span class="pbtn pbtn-vdown"></span>
          <span class="pbtn pbtn-power"></span>
          <div class="feat-phone-notch"></div>
          <div class="feat-phone-screen">
            <img src="{{ asset('images/landing/screen-live-tracking.jpg') }}" alt="Live workout tracking" width="500" height="1115" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
      <div class="feature-copy">
        <div class="feat-icon feat-icon-teal">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00B4C5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div class="eyebrow">{{ __('Live session tracking') }}</div>
        <h3>{{ __('Log every set as you do it') }}</h3>
        <p class="feat-desc">{{ __('During your workout, tap to log each set: weight, reps, rest time. It all gets saved automatically so you can focus on lifting, not writing.') }}</p>
        <ul class="feat-bullets">
          <li>
            <div class="bullet-dot"><svg viewBox="0 0 10 10"><polyline points="2,5 4,7 8,3"/></svg></div>
            {{ __('One tap to complete a set') }}
          </li>
          <li>
            <div class="bullet-dot"><svg viewBox="0 0 10 10"><polyline points="2,5 4,7 8,3"/></svg></div>
            {{ __('Built-in rest timer between sets') }}
          </li>
          <li>
            <div class="bullet-dot"><svg viewBox="0 0 10 10"><polyline points="2,5 4,7 8,3"/></svg></div>
            {{ __('Every session auto-saved to your history') }}
          </li>
        </ul>
      </div>
    </div>

    <!-- Feature 3: Progress & PRs -->
    <div class="feature-row">
      <div class="feature-phone">
        <div class="feat-phone-wrap">
          <span class="pbtn pbtn-silent"></span>
          <span class="pbtn pbtn-vup"></span>
          <span class="pbtn pbtn-vdown"></span>
          <span class="pbtn pbtn-power"></span>
          <div class="feat-phone-notch"></div>
          <div class="feat-phone-screen">
            <img src="{{ asset('images/landing/screen-records.jpg') }}" alt="Personal records screen" width="500" height="1115" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
      <div class="feature-copy">
        <div class="feat-icon feat-icon-orange">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div class="eyebrow eyebrow-orange">{{ __('Progress & records') }}</div>
        <h3>{{ __("See how far you've come") }}</h3>
        <p class="feat-desc">{{ __('After each workout, Fit Nation shows which lifts you improved. Every personal record gets celebrated. Progress, even small progress, deserves recognition.') }}</p>
        <ul class="feat-bullets">
          <li>
            <div class="bullet-dot bullet-dot-orange"><svg viewBox="0 0 10 10"><polyline points="2,5 4,7 8,3"/></svg></div>
            {{ __('Automatic personal record detection') }}
          </li>
          <li>
            <div class="bullet-dot bullet-dot-orange"><svg viewBox="0 0 10 10"><polyline points="2,5 4,7 8,3"/></svg></div>
            {{ __('Completion summary after every session') }}
          </li>
          <li>
            <div class="bullet-dot bullet-dot-orange"><svg viewBox="0 0 10 10"><polyline points="2,5 4,7 8,3"/></svg></div>
            {{ __('Full workout history, always accessible') }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- EXERCISE LIBRARY -->
<section class="library-strip">
  <div class="wrap">
    <div class="library-inner">
      <div>
        <div class="eyebrow">{{ __('Exercise library') }}</div>
        <div class="lib-stat">
          <div class="lib-num">{{ $exerciseCount }}</div>
          <div class="lib-unit">{{ __('exercises') }}</div>
        </div>
        <h2 style="margin-bottom:14px;">{{ __('Every movement, explained') }}</h2>
        <p class="sec-desc" style="text-align:left; max-width:420px;">{{ __('From barbell squats to mobility work: :count exercises with step-by-step guidance, so you always know exactly how to move.', ['count' => $exerciseCount]) }}</p>
      </div>
      <div class="lib-phone">
        <div class="lib-phone-wrap">
          <span class="pbtn pbtn-silent"></span>
          <span class="pbtn pbtn-vup"></span>
          <span class="pbtn pbtn-vdown"></span>
          <span class="pbtn pbtn-power"></span>
          <div class="lib-notch"></div>
          <div class="lib-screen">
            <img src="{{ asset('images/landing/screen-library.jpg') }}" alt="Exercise library" width="500" height="1115" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how" id="how">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow">{{ __('Getting started') }}</div>
      <h2>{{ __('Up and training in three steps') }}</h2>
      <p class="sec-desc">{{ __('No gym membership required. No complicated setup. Just download and start.') }}</p>
    </div>
    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <div class="step-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00B4C5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <h3>{{ __('Download and sign up') }}</h3>
        <p>{{ __('Get the app free from the App Store or Google Play, and create your account right in the app.') }}</p>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div class="step-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00B4C5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
        </div>
        <h3>{{ __('Pick a plan and start') }}</h3>
        <p>{{ __('Choose a structured plan that matches your goals. Your first session is ready the moment you sign up.') }}</p>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div class="step-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <h3>{{ __('Track, improve, repeat') }}</h3>
        <p>{{ __('Log every set, watch your progress build, and celebrate every personal record along the way.') }}</p>
      </div>
    </div>
  </div>
</section>

<!-- DOWNLOAD CTA -->
<section class="download" id="download">
  <div class="wrap">
    <div class="dl-inner">
      <div class="eyebrow" style="background:rgba(0,180,197,0.15); margin-bottom:24px;">{{ __('Free to download') }}</div>
      <h2>{{ __('Start your first workout today') }}</h2>
      <p>{{ __('Available on iPhone and Android. Free to download, with sign-up right in the app.') }}</p>
      <div class="dl-btns">
        <a href="https://apps.apple.com/mk/app/fit-nation-the-movement/id6766201705" target="_blank" rel="noopener" class="dl-btn" aria-label="Download on the App Store">
          <svg class="store-logo" width="22" height="27" viewBox="0 0 24 24" fill="white">
            <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
          </svg>
          <div class="store-text">
            <small>{{ __('Download on the') }}</small>
            <strong>App Store</strong>
          </div>
        </a>
        <a href="https://play.google.com/store/apps/details?id=com.fitnation.app" target="_blank" rel="noopener" class="dl-btn" aria-label="Get it on Google Play">
          <svg class="play-logo" width="22" height="24" viewBox="0 0 24 26">
            <path d="M1.5 0.75 L13.5 12.75 L1.5 24.75 Q0.75 24.375 0.75 23.25 L0.75 2.25 Q0.75 1.125 1.5 0.75Z" fill="#4285F4"/>
            <path d="M18 8.25 L13.5 12.75 L1.5 0.75 Q2.25 0.375 3.375 1.125 Z" fill="#EA4335"/>
            <path d="M18 17.25 L3.375 24.375 Q2.25 25.125 1.5 24.75 L13.5 12.75 Z" fill="#34A853"/>
            <path d="M22.5 12.75 Q22.5 13.875 18 17.25 L13.5 12.75 L18 8.25 Q22.5 11.625 22.5 12.75Z" fill="#FBBC04"/>
          </svg>
          <div class="store-text">
            <small>{{ __('Get it on') }}</small>
            <strong>Google Play</strong>
          </div>
        </a>
      </div>
      <div class="dl-qr">
        <img src="{{ asset('images/landing/qr-download.svg') }}" alt="{{ __('QR code to download the app') }}" width="116" height="116">
        <div class="dl-qr-text">
          <strong>{{ __('On your computer?') }}</strong>
          {{ __('Scan the code with your phone and it takes you straight to the right store.') }}
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="wrap">
    <div class="foot-inner">
      <div class="foot-logo">
        <div style="width:30px;height:30px;background:#fff;border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;"><img src="{{ asset('images/landing/logo.png') }}" alt="Fit Nation logo" style="width:26px;height:26px;object-fit:contain;"></div>
        <span class="foot-logo-text">Fit Nation: The Movement</span>
      </div>
      <div class="foot-links">
        <a href="https://fitnation.mk/privacy" target="_blank" rel="noopener">{{ __('Privacy Policy') }}</a>
        <a href="mailto:support@fitnation.mk">{{ __('Contact') }}</a>
      </div>
      <div class="foot-copy">&copy; {{ date('Y') }} Fit Nation: The Movement. {{ __('All rights reserved.') }}</div>
    </div>
  </div>
</footer>


</body>
</html>
