@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Modifier scolarité</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ <a href="{{ route('admin.scolarites.index') }}">Scolarités</a></span>
                <span class="text-secondary-light">/ Modifier</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Informations générales</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.scolarites.update', $scolarite) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="classe_annee_id" class="form-label">Classe - Année</label>
                            <select name="classe_annee_id" id="classe_annee_id" class="form-select @error('classe_annee_id') is-invalid @enderror" required>
                                <option value="">-- Choisir --</option>
                                @foreach($classesAnnees as $ca)
                                    <option value="{{ $ca->id }}" {{ old('classe_annee_id', $scolarite->classe_annee_id) == $ca->id ? 'selected' : '' }}>
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
                            <input type="number" step="1000" min="0" class="form-control @error('montant_annuel') is-invalid @enderror" id="montant_annuel" name="montant_annuel" value="{{ old('montant_annuel', $scolarite->montant_annuel) }}" required>
                            @error('montant_annuel')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $scolarite->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                        <a href="{{ route('admin.scolarites.index') }}" class="btn btn-secondary">Annuler</a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Tranches de paiement</h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <h6>Ajouter une tranche</h6>
                    <form action="{{ route('admin.tranches.store', $scolarite) }}" method="POST" class="mb-4">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <input type="text" name="libelle" class="form-control @error('libelle') is-invalid @enderror" placeholder="Libellé (ex: 1er versement)" value="{{ old('libelle') }}" required>
                                @error('libelle') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-2">
                                <input type="number" step="0.01" name="montant" class="form-control @error('montant') is-invalid @enderror" placeholder="Montant" value="{{ old('montant') }}" required>
                                @error('montant') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-2">
                                <input type="date" name="date_echeance" class="form-control @error('date_echeance') is-invalid @enderror" value="{{ old('date_echeance') }}" required>
                                @error('date_echeance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-2">
                                <input type="number" name="ordre" class="form-control @error('ordre') is-invalid @enderror" placeholder="Ordre (1,2,3...)" value="{{ old('ordre') }}" required>
                                @error('ordre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-sm btn-primary">Ajouter</button>
                            </div>
                        </div>
                    </form>

                    @if($scolarite->tranches->isEmpty())
                        <p class="text-muted">Aucune tranche définie.</p>
                    @else
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Ordre</th>
                                    <th>Libellé</th>
                                    <th>Échéance</th>
                                    <th>Montant</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($scolarite->tranches as $tranche)
                                <tr>
                                    <td>{{ $tranche->ordre }}</td>
                                    <td>{{ $tranche->libelle }}</td>
                                    <td>{{ $tranche->date_echeance->locale('fr')->isoFormat('D MMMM YYYY') }}</td>
                                    <td>{{ number_format($tranche->montant, 0, ',', ' ') }}</td>
                                    <td>
                                        <a href="{{ route('admin.tranches.edit', [$scolarite, $tranche]) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                                        <form action="{{ route('admin.tranches.destroy', [$scolarite, $tranche]) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette tranche ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection