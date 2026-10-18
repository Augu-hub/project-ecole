@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Nouvelle tranche</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ <a href="{{ route('admin.scolarites.index') }}">Scolarités</a></span>
                <span class="text-secondary-light">/ <a href="{{ route('admin.tranches.index', $scolarite) }}">Tranches</a></span>
                <span class="text-secondary-light">/ Ajouter</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.tranches.store', $scolarite) }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="libelle" class="form-label">Libellé</label>
                    <input type="text" class="form-control @error('libelle') is-invalid @enderror" id="libelle" name="libelle" value="{{ old('libelle') }}" required>
                    @error('libelle')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="montant" class="form-label">Montant (FCFA)</label>
                    <input type="number" step="100" class="form-control @error('montant') is-invalid @enderror" id="montant" name="montant" value="{{ old('montant') }}" required>
                    @error('montant')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="date_echeance" class="form-label">Date d'échéance</label>
                    <input type="date" class="form-control @error('date_echeance') is-invalid @enderror" id="date_echeance" name="date_echeance" value="{{ old('date_echeance') }}" required>
                    @error('date_echeance')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Doit être comprise entre {{ $scolarite->classeAnnee->anneeScolaire->date_debut->locale('fr')->isoFormat('D MMMM YYYY') }} et {{ $scolarite->classeAnnee->anneeScolaire->date_fin->locale('fr')->isoFormat('D MMMM YYYY') }}</small>
                </div>

                <div class="mb-3">
                    <label for="ordre" class="form-label">Ordre</label>
                    <input type="number" min="1" class="form-control @error('ordre') is-invalid @enderror" id="ordre" name="ordre" value="{{ old('ordre') }}" required>
                    @error('ordre')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('admin.tranches.index', $scolarite) }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection