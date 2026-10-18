@extends('layouts.auth')

@section('title', 'Créer un compte')
@section('carte-tag', 'Inscription')
@section('carte-titre', 'Créer un compte')

@section('content')
      <form method="POST" action="{{ route('register') }}" data-attente="Création…">
        @csrf

        <div class="champ">
          <label for="name">{{ __('Name') }}</label>
          <input type="text" name="name" id="name"
                 class="@error('name') invalide @enderror"
                 value="{{ old('name') }}"
                 autocomplete="name"
                 required autofocus>
          @foreach ($errors->get('name') as $message)
            <div class="champ-erreur">{{ $message }}</div>
          @endforeach
        </div>

        <div class="champ">
          <label for="email">{{ __('Email') }}</label>
          <input type="email" name="email" id="email"
                 class="@error('email') invalide @enderror"
                 value="{{ old('email') }}"
                 autocomplete="username" autocapitalize="off" spellcheck="false"
                 required>
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

        <button type="submit" class="btn-connexion">{{ __('Register') }}</button>
      </form>

      <div class="actions-auth centre">
        <a href="{{ route('login') }}" class="lien-retour">{{ __('Already registered?') }}</a>
      </div>
@endsection
