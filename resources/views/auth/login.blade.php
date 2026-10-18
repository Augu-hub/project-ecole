@extends('layouts.auth')

@section('title', 'Connexion')
@section('carte-tag', 'Connexion')
@section('carte-titre', 'Accéder à mon espace')
@section('carte-sous', "Réservé au personnel. Saisissez les identifiants qui vous ont été remis par l'établissement.")

@section('content')
@php
    // Messages d'authentification : déjà en français (lang/fr/auth.php)
    $erreurLogin = $errors->first('login');
@endphp

      @if (session('error'))
        <div class="alerte" role="alert">{{ session('error') }}</div>
      @endif

      <form method="POST" action="{{ route('login') }}" id="form-connexion" data-attente="Connexion…">
        @csrf

        <div class="champ">
          <label for="login">Email ou téléphone</label>
          <input type="text" name="login" id="login"
                 class="{{ $erreurLogin ? 'invalide' : '' }}"
                 value="{{ old('login') }}"
                 placeholder="Votre email ou numéro de téléphone"
                 autocomplete="username" autocapitalize="off" spellcheck="false"
                 required autofocus>
          @if ($erreurLogin)
            <div class="champ-erreur">{{ $erreurLogin }}</div>
          @endif
        </div>

        <div class="champ">
          <label for="password">Mot de passe</label>
          <div class="mdp">
            <input type="password" name="password" id="password"
                   class="@error('password') invalide @enderror"
                   placeholder="••••••••"
                   autocomplete="current-password"
                   required>
            <button type="button" class="mdp-bascule" id="mdp-bascule" aria-controls="password" aria-pressed="false">Afficher</button>
          </div>
          @error('password')
            <div class="champ-erreur">{{ $message }}</div>
          @enderror
        </div>

        <label class="souvenir">
          <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
          Rester connecté sur cet appareil
        </label>

        <button type="submit" class="btn-connexion" id="btn-connexion">Se connecter</button>
      </form>

      <div class="aide">
        <div class="aide-titre">Mot de passe oublié ou première connexion ?</div>
        <div class="aide-texte">Adressez-vous à l'administration du collège : vos identifiants vous seront communiqués ou réinitialisés.</div>
      </div>

      <div class="aide aide-parent">
        <div class="aide-titre">Vous êtes parent ?</div>
        <div class="aide-texte">Le suivi de vos enfants se fait uniquement depuis l'application mobile du collège, avec les identifiants remis par le secrétariat.</div>
      </div>
@endsection
