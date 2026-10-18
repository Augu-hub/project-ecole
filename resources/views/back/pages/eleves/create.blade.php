@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Nouvelle inscription</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.eleves.index') }}" class="text-secondary-light hover-text-primary hover-underline">Élèves</a>
                <span class="text-secondary-light">/ Nouvelle inscription</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            <form action="{{ route('admin.eleves.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <h5 class="mb-3">Informations de l'élève</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" value="{{ old('nom') }}" required>
                        @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('prenom') is-invalid @enderror" id="prenom" name="prenom" value="{{ old('prenom') }}" required>
                        @error('prenom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="sexe" class="form-label">Sexe</label>
                        <select name="sexe" id="sexe" class="form-select @error('sexe') is-invalid @enderror">
                            <option value="">--</option>
                            <option value="M" {{ old('sexe') === 'M' ? 'selected' : '' }}>Masculin</option>
                            <option value="F" {{ old('sexe') === 'F' ? 'selected' : '' }}>Féminin</option>
                        </select>
                        @error('sexe') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="date_naissance" class="form-label">Date de naissance</label>
                        <input type="date" class="form-control @error('date_naissance') is-invalid @enderror" id="date_naissance" name="date_naissance" value="{{ old('date_naissance') }}">
                        @error('date_naissance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="matricule" class="form-label">Matricule</label>
                        <input type="text" class="form-control @error('matricule') is-invalid @enderror" id="matricule" name="matricule" value="{{ old('matricule') }}" placeholder="Laisser vide pour génération automatique">
                        @error('matricule') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="classe_annee_id" class="form-label">Classe ({{ $anneeActive->libelle }}) <span class="text-danger">*</span></label>
                        <select name="classe_annee_id" id="classe_annee_id" class="form-select @error('classe_annee_id') is-invalid @enderror" required>
                            <option value="">-- Choisir une classe --</option>
                            @foreach($classes->groupBy(fn($ca) => ucfirst($ca->classe->niveau->cycle)) as $cycle => $classesDuCycle)
                                <optgroup label="{{ $cycle }}">
                                    @foreach($classesDuCycle as $ca)
                                        <option value="{{ $ca->id }}" {{ old('classe_annee_id') == $ca->id ? 'selected' : '' }}>
                                            {{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('classe_annee_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Parent</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="parent_id" class="form-label">Parent déjà enregistré</label>
                        <select name="parent_id" id="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                            <option value="">-- Aucun, créer un nouveau parent ci-dessous --</option>
                            @foreach($parents as $p)
                                <option value="{{ $p->id }}" {{ old('parent_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->nom }} {{ $p->prenom }} ({{ $p->telephone }})
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <p class="text-muted">Ou renseignez un nouveau parent (recherché d'abord par téléphone, créé automatiquement s'il n'existe pas) :</p>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="parent_nom" class="form-label">Nom du parent</label>
                        <input type="text" class="form-control @error('parent_nom') is-invalid @enderror" id="parent_nom" name="parent_nom" value="{{ old('parent_nom') }}">
                        @error('parent_nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="parent_prenom" class="form-label">Prénom du parent</label>
                        <input type="text" class="form-control @error('parent_prenom') is-invalid @enderror" id="parent_prenom" name="parent_prenom" value="{{ old('parent_prenom') }}">
                        @error('parent_prenom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="parent_telephone" class="form-label">Téléphone du parent</label>
                        <input type="text" class="form-control @error('parent_telephone') is-invalid @enderror" id="parent_telephone" name="parent_telephone" value="{{ old('parent_telephone') }}">
                        @error('parent_telephone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Documents du dossier</h5>
                @for($i = 0; $i < 3; $i++)
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <input type="file" class="form-control @error('fichiers.'.$i) is-invalid @enderror" name="fichiers[{{ $i }}]">
                        @error('fichiers.'.$i) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5 mb-3">
                        <input type="text" class="form-control" name="fichiers_libelles[{{ $i }}]" placeholder="Libellé (ex: Extrait de naissance)">
                    </div>
                </div>
                @endfor

                <button type="submit" class="btn btn-primary">Inscrire l'élève</button>
                <a href="{{ route('admin.eleves.index') }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection
