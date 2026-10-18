@extends('layouts.auth')

@section('title', 'Confirmer le mot de passe')
@section('carte-tag', 'Zone protégée')
@section('carte-titre', 'Confirmer mon mot de passe')
@section('carte-sous', __('This is a secure area of the application. Please confirm your password before continuing.'))

@section('content')
      <form method="POST" action="{{ route('password.confirm') }}" data-attente="Vérification…">
        @csrf

        <div class="champ">
          <label for="password">{{ __('Password') }}</label>
          <div class="mdp">
            <input type="password" name="password" id="password"
                   class="@error('password') invalide @enderror"
                   autocomplete="current-password"
                   required autofocus>
            <button type="button" class="mdp-bascule" aria-controls="password" aria-pressed="false">Afficher</button>
          </div>
          @foreach ($errors->get('password') as $message)
            <div class="champ-erreur">{{ $message }}</div>
          @endforeach
        </div>

        <button type="submit" class="btn-connexion">{{ __('Confirm') }}</button>
      </form>
@endsection
