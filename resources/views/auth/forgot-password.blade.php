@extends('layouts.auth')

@section('title', 'Mot de passe oublié')
@section('carte-tag', 'Mot de passe oublié')
@section('carte-titre', 'Réinitialiser mon mot de passe')
@section('carte-sous', __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.'))

@section('content')
      @if (session('status'))
        <div class="statut" role="status">{{ session('status') }}</div>
      @endif

      <form method="POST" action="{{ route('password.email') }}" data-attente="Envoi…">
        @csrf

        <div class="champ">
          <label for="email">{{ __('Email') }}</label>
          <input type="email" name="email" id="email"
                 class="@error('email') invalide @enderror"
                 value="{{ old('email') }}"
                 autocomplete="username" autocapitalize="off" spellcheck="false"
                 required autofocus>
          @foreach ($errors->get('email') as $message)
            <div class="champ-erreur">{{ $message }}</div>
          @endforeach
        </div>

        <button type="submit" class="btn-connexion">{{ __('Email Password Reset Link') }}</button>
      </form>

      <div class="actions-auth centre">
        <a href="{{ route('login') }}" class="lien-retour">Retour à la connexion</a>
      </div>
@endsection
