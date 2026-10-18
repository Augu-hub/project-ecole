@extends('layouts.auth')

@section('title', 'Vérification de l\'adresse email')
@section('carte-tag', 'Vérification')
@section('carte-titre', 'Vérifier mon adresse email')
@section('carte-sous', __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.'))

@section('content')
      @if (session('status') == 'verification-link-sent')
        <div class="statut" role="status">
          {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
      @endif

      <form method="POST" action="{{ route('verification.send') }}" data-attente="Envoi…">
        @csrf

        <button type="submit" class="btn-connexion">{{ __('Resend Verification Email') }}</button>
      </form>

      <div class="actions-auth centre">
        <form method="POST" action="{{ route('logout') }}">
          @csrf

          <button type="submit" class="lien-retour">{{ __('Log Out') }}</button>
        </form>
      </div>
@endsection
