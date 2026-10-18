@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Ajouter une classe</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.classes.index') }}" class="text-secondary-light hover-text-primary hover-underline">Classes</a>
                <span class="text-secondary-light">/ Ajouter</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            <form action="{{ route('admin.classes.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="niveau_id" class="form-label">Niveau <span class="text-danger">*</span></label>
                    <select class="form-select @error('niveau_id') is-invalid @enderror" id="niveau_id" name="niveau_id" required>
                        <option value="">Sélectionnez un niveau</option>
                        @foreach($niveaux as $niveau)
                            <option value="{{ $niveau->id }}" {{ old('niveau_id') == $niveau->id ? 'selected' : '' }}>{{ $niveau->nom }}</option>
                        @endforeach
                    </select>
                    @error('niveau_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="suffixe" class="form-label">Suffixe</label>
                    <input type="text" class="form-control @error('suffixe') is-invalid @enderror" id="suffixe" name="suffixe" value="{{ old('suffixe') }}" maxlength="10">
                    <small class="form-text text-muted">Exemple : A, B, C (laissez vide pour les classes sans division)</small>
                    @error('suffixe') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('admin.classes.index') }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection