@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Modifier un créneau horaire</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.creneaux-horaires.index') }}" class="text-secondary-light hover-text-primary hover-underline">Créneaux horaires</a>
                <span class="text-secondary-light">/ Modifier</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            <form action="{{ route('admin.creneaux-horaires.update', $creneau) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="heure_debut" class="form-label">Heure de début</label>
                    <input type="time" class="form-control @error('heure_debut') is-invalid @enderror" id="heure_debut" name="heure_debut" value="{{ old('heure_debut', $creneau->heure_debut_courte) }}" required>
                    @error('heure_debut') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="heure_fin" class="form-label">Heure de fin</label>
                    <input type="time" class="form-control @error('heure_fin') is-invalid @enderror" id="heure_fin" name="heure_fin" value="{{ old('heure_fin', $creneau->heure_fin_courte) }}" required>
                    @error('heure_fin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="ordre" class="form-label">Ordre d'affichage</label>
                    <input type="number" min="0" class="form-control @error('ordre') is-invalid @enderror" id="ordre" name="ordre" value="{{ old('ordre', $creneau->ordre) }}" required>
                    @error('ordre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
                <a href="{{ route('admin.creneaux-horaires.index') }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection
