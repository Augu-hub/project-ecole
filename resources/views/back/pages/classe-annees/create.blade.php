@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Associer une classe à une année scolaire</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.classe-annees.index') }}" class="text-secondary-light hover-text-primary hover-underline">Associations</a>
                <span class="text-secondary-light">/ Ajouter</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            <form action="{{ route('admin.classe-annees.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="classe_id" class="form-label">Classe <span class="text-danger">*</span></label>
                    <select class="form-select @error('classe_id') is-invalid @enderror" id="classe_id" name="classe_id" required>
                        <option value="">Sélectionnez une classe</option>
                        @foreach($classes as $id => $nom)
                            <option value="{{ $id }}" {{ old('classe_id') == $id ? 'selected' : '' }}>{{ $nom }}</option>
                        @endforeach
                    </select>
                    @error('classe_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="annee_scolaire_id" class="form-label">Année scolaire <span class="text-danger">*</span></label>
                    <select class="form-select @error('annee_scolaire_id') is-invalid @enderror" id="annee_scolaire_id" name="annee_scolaire_id" required>
                        <option value="">Sélectionnez une année</option>
                        @foreach($anneesScolaires as $id => $libelle)
                            <option value="{{ $id }}" {{ old('annee_scolaire_id', $anneeActiveId) == $id ? 'selected' : '' }}>{{ $libelle }}{{ $id == $anneeActiveId ? ' (active)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('annee_scolaire_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('admin.classe-annees.index') }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection