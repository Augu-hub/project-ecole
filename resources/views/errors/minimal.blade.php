<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>@yield('title') — {{ config('ecole.nom') }}</title>
<link rel="icon" href="{{ asset('assets/images/LOGOCCPA.jpeg') }}">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
{{-- Page autonome (aucune requête base de données ni session) : elle doit s'afficher même quand l'application est en panne. --}}
<style>
:root {
  --vert: #1b4d2e;
  --vert-clair: #2d7a4a;
  --or: #b8953a;
  --or-clair: #d4aa50;
  --blanc: #ffffff;
  --gris: #f2f0eb;
  --texte: #1a1a1a;
  --texte-doux: #6b6b6b;
  --bord: #e0dcd4;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'DM Sans', sans-serif; background: var(--gris); color: var(--texte);
  min-height: 100vh; display: flex; flex-direction: column;
}
header {
  background: var(--vert); padding: 14px 5vw;
  display: flex; align-items: center; gap: 14px;
}
header img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--or); }
header strong { display: block; color: var(--blanc); font-family: 'Cormorant Garamond', serif; font-size: 16px; letter-spacing: .5px; }
header span { color: var(--or-clair); font-size: 10px; letter-spacing: 2.5px; text-transform: uppercase; }
main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
.carte {
  background: var(--blanc); border: 1px solid var(--bord);
  max-width: 520px; width: 100%; padding: 48px 40px; text-align: center;
}
.code { color: var(--or); font-size: 12px; font-weight: 600; letter-spacing: 3px; text-transform: uppercase; }
h1 { font-family: 'Cormorant Garamond', serif; font-size: 34px; line-height: 1.15; margin: 10px 0 14px; }
.trait { width: 50px; height: 3px; background: var(--or); margin: 0 auto 20px; }
p { color: var(--texte-doux); line-height: 1.7; font-size: 15px; }
.actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 30px; }
.btn {
  display: inline-block; padding: 13px 22px; font: 600 13px 'DM Sans', sans-serif;
  letter-spacing: 1px; text-transform: uppercase; text-decoration: none; cursor: pointer;
  border: 1px solid var(--vert);
}
.btn-plein { background: var(--vert); color: var(--blanc); }
.btn-plein:hover { background: var(--vert-clair); border-color: var(--vert-clair); }
.btn-vide { background: transparent; color: var(--vert); }
.btn-vide:hover { background: var(--gris); }
@media (max-width: 480px) { .carte { padding: 36px 22px; } h1 { font-size: 28px; } }
</style>
</head>
<body>
<header>
  <img src="{{ asset('assets/images/LOGOCCPA.jpeg') }}" alt="">
  <div>
    <strong>{{ config('ecole.nom') }}</strong>
    <span>{{ config('ecole.adresse') }}</span>
  </div>
</header>

<main>
  <div class="carte" role="main">
    <div class="code">Erreur @yield('code')</div>
    <h1>@yield('title')</h1>
    <div class="trait"></div>
    <p>@yield('message')</p>

    <div class="actions">
      <a href="{{ url()->previous() }}" class="btn btn-vide" onclick="if (history.length > 1) { history.back(); return false; }">Page précédente</a>
      <a href="{{ url('/') }}" class="btn btn-plein">Accueil</a>
    </div>
  </div>
</main>
</body>
</html>
