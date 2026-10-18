@extends('back.layouts.master')

@section('title', 'Réinitialiser mot de passe')
@section('content')

<div class="dashboard-main-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <h6 class="fw-semibold mb-0">Réinitialiser le mot de passe de {{ $parent->prenom }} {{ $parent->nom }}</h6>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.parents.reset-password', $parent) }}">
                @csrf

                <div class="mb-3">
                    <label for="password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                    <small class="text-muted">Minimum 8 caractères.</small>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Réinitialiser</button>
                    <a href="{{ route('admin.parents.index') }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection