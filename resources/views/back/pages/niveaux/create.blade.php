@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Ajouter un niveau</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.niveaux.index') }}" class="text-secondary-light hover-text-primary hover-underline">Niveaux</a>
                <span class="text-secondary-light">/ Ajouter</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            <form action="{{ route('admin.niveaux.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="nom" class="form-label">Nom du niveau <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" value="{{ old('nom') }}" required>
                    @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="ordre" class="form-label">Ordre <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('ordre') is-invalid @enderror" id="ordre" name="ordre" value="{{ old('ordre') }}" required min="0">
                    <small class="form-text text-muted">Plus le chiffre est petit, plus le niveau apparaîtra en premier (ex: 6ème = 6, CM2 = 10).</small>
                    @error('ordre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="cycle" class="form-label">Cycle</label>
                    <select class="form-control @error('cycle') is-invalid @enderror" id="cycle" name="cycle">
                        <option value="">-- Non catégorisé --</option>
                        <option value="maternelle" @selected(old('cycle') === 'maternelle')>Maternelle</option>
                        <option value="primaire" @selected(old('cycle') === 'primaire')>Primaire</option>
                        <option value="secondaire" @selected(old('cycle') === 'secondaire')>Secondaire</option>
                    </select>
                    @error('cycle') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('admin.niveaux.index') }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection