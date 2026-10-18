@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Nouveau frais de dossier</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.frais-dossiers.index') }}" class="text-secondary-light hover-text-primary hover-underline">Frais de dossier</a>
                <span class="text-secondary-light">/ Nouveau</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            <form action="{{ route('admin.frais-dossiers.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="classe_annee_id" class="form-label">Classe (secondaire uniquement)</label>
                    <select name="classe_annee_id" id="classe_annee_id" class="form-select @error('classe_annee_id') is-invalid @enderror" required>
                        <option value="">-- Choisir une classe --</option>
                        @foreach($classesAnnees as $ca)
                            <option value="{{ $ca->id }}" {{ old('classe_annee_id') == $ca->id ? 'selected' : '' }}>
                                {{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }} - {{ $ca->anneeScolaire->libelle }}
                            </option>
                        @endforeach
                    </select>
                    @error('classe_annee_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="montant" class="form-label">Montant (FCFA)</label>
                    <input type="number" step="500" min="0" class="form-control @error('montant') is-invalid @enderror" id="montant" name="montant" value="{{ old('montant') }}" required>
                    @error('montant') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description" value="{{ old('description') }}">
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('admin.frais-dossiers.index') }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection
