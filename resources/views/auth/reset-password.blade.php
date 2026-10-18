@extends('layouts.auth')

@section('title', 'Nouveau mot de passe')
@section('carte-tag', 'Réinitialisation')
@section('carte-titre', 'Choisir un nouveau mot de passe')
@section('carte-sous', 'Saisissez votre adresse email puis votre nouveau mot de passe, deux fois.')

@section('content')
      <form method="POST" action="{{ route('password.store') }}" data-attente="Enregistrement…">
        @csrf

        <!-- Jeton de réinitialisation -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="champ">
          <label for="email">{{ __('Email') }}</label>
          <input type="email" name="email" id="email"
                 class="@error('email') invalide @enderror"
                 value="{{ old('email', $request->email) }}"
                 autocomplete="username" autocapitalize="off" spellcheck="false"
                 required autofocus>
          @foreach ($errors->get('email') as $message)
            <div class="champ-erreur">{{ $message }}</div>
          @endforeach
        </div>

        <div class="champ">
          <label for="password">{{ __('Password') }}</label>
          <div class="mdp">
            <input type="password" name="password" id="password"
                   class="@error('password') invalide @enderror"
                   autocomplete="new-password"
                   required>
            <button type="button" class="mdp-bascule" aria-controls="password" aria-pressed="false">Afficher</button>
          </div>
          @foreach ($errors->get('password') as $message)
            <div class="champ-erreur">{{ $message }}</div>
          @endforeach
        </div>

        <div class="champ">
          <label for="password_confirmation">{{ __('Confirm Password') }}</label>
          <div class="mdp">
            <input type="password" name="password_confirmation" id="password_confirmation"
                   class="@error('password_confirmation') invalide @enderror"
                   autocomplete="new-password"
                   required>
            <button type="button" class="mdp-bascule" aria-controls="password_confirmation" aria-pressed="false">Afficher</button>
          </div>
          @foreach ($errors->get('password_confirmation') as $message)
            <div class="champ-erreur">{{ $message }}</div>
          @endforeach
        </div>

        <button type="submit" class="btn-connexion">{{ __('Reset Password') }}</button>
      </form>

      <div class="actions-auth centre">
        <a href="{{ route('login') }}" class="lien-retour">Retour à la connexion</a>
      </div>
@endsection
