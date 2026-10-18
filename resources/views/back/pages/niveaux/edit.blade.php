@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Modifier un niveau</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.niveaux.index') }}" class="text-secondary-light hover-text-primary hover-underline">Niveaux</a>
                <span class="text-secondary-light">/ Modifier</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            <form action="{{ route('admin.niveaux.update', $niveau) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="nom" class="form-label">Nom du niveau <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" value="{{ old('nom', $niveau->nom) }}" required>
                    @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="ordre" class="form-label">Ordre <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('ordre') is-invalid @enderror" id="ordre" name="ordre" value="{{ old('ordre', $niveau->ordre) }}" required min="0">
                    @error('ordre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="cycle" class="form-label">Cycle</label>
                    <select class="form-control @error('cycle') is-invalid @enderror" id="cycle" name="cycle">
                        <option value="">-- Non catégorisé --</option>
                        <option value="maternelle" @selected(old('cycle', $niveau->cycle) === 'maternelle')>Maternelle</option>
                        <option value="primaire" @selected(old('cycle', $niveau->cycle) === 'primaire')>Primaire</option>
                        <option value="secondaire" @selected(old('cycle', $niveau->cycle) === 'secondaire')>Secondaire</option>
                    </select>
                    @error('cycle') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">Mettre à jour</button>
                <a href="{{ route('admin.niveaux.index') }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection