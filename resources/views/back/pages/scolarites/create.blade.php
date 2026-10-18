@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Nouvelle scolarité</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ <a href="{{ route('admin.scolarites.index') }}">Scolarités</a></span>
                <span class="text-secondary-light">/ Créer</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.scolarites.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="classe_annee_id" class="form-label">Classe - Année</label>
                    <select name="classe_annee_id" id="classe_annee_id" class="form-select @error('classe_annee_id') is-invalid @enderror" required>
                        <option value="">-- Choisir --</option>
                        @foreach($classesAnnees as $ca)
                            <option value="{{ $ca->id }}" {{ old('classe_annee_id') == $ca->id ? 'selected' : '' }}>
                                {{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }} - {{ $ca->anneeScolaire->libelle }}
                            </option>
                        @endforeach
                    </select>
                    @error('classe_annee_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="montant_annuel" class="form-label">Montant annuel (FCFA)</label>
                    <input type="number" step="1000" min="0" class="form-control @error('montant_annuel') is-invalid @enderror" id="montant_annuel" name="montant_annuel" value="{{ old('montant_annuel') }}" required>
                    @error('montant_annuel')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description (optionnelle)</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('admin.scolarites.index') }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection