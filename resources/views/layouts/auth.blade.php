<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Connexion') — Collège Catholique Père Aupiais</title>
<link rel="icon" href="{{ asset('assets/images/LOGOCCPA.jpeg') }}">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}">
@stack('styles')
</head>
<body>

{{--
    Mise en page des pages d'authentification : bandeau du collège, panneau de présentation à gauche,
    carte du formulaire à droite. Chaque page fournit :
      @section('title')       titre de l'onglet
      @section('carte-tag')   petit libellé doré au-dessus du titre
      @section('carte-titre') titre de la carte
      @section('carte-sous')  texte d'introduction (facultatif)
      @section('content')     messages, formulaire et blocs d'aide
    Styles : public/assets/css/auth.css — comportements : public/assets/js/auth.js
--}}

<nav>
  <a href="{{ route('login') }}" class="nav-brand">
    <img src="{{ asset('assets/images/LOGOCCPA.jpeg') }}" alt="CCPA" class="nav-logo-img">
    <div class="nav-brand-text">
      <strong>Collège Catholique Père Aupiais</strong>
      <span>Cotonou · Bénin</span>
    </div>
  </a>
</nav>

<main class="page">

  <section class="gauche">
    <span class="tag">Espace du personnel</span>
    <h1 class="titre">Votre espace<br><em>de travail</em></h1>
    <p class="desc">Saisie des notes, bulletins, emplois du temps, scolarité et communication avec les familles : l'outil de gestion réservé au personnel du collège.</p>
    <div class="devise">"Ora et Labora" — Prie et Travaille</div>
    <div class="publics">
      <div class="public">
        <div class="public-nom">Enseignants</div>
        <div class="public-desc">Notes et emploi du temps</div>
      </div>
      <div class="public">
        <div class="public-nom">Direction</div>
        <div class="public-desc">Pilotage du collège</div>
      </div>
      <div class="public">
        <div class="public-nom">Administration</div>
        <div class="public-desc">Scolarité et économat</div>
      </div>
    </div>
  </section>

  <section class="droite">
    <div class="carte">
      <div class="carte-tag">@yield('carte-tag')</div>
      <h2 class="carte-titre">@yield('carte-titre')</h2>
      <div class="ligne-or"></div>
      @hasSection('carte-sous')
        <p class="carte-sous">@yield('carte-sous')</p>
      @endif

      @yield('content')
    </div>

    <p class="pied">© {{ date('Y') }} Collège Catholique Père Aupiais · Cotonou, Bénin</p>
  </section>

</main>

<script src="{{ asset('assets/js/auth.js') }}"></script>
@stack('scripts')
</body>
</html>
