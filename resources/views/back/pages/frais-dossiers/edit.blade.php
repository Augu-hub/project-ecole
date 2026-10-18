@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">
                Frais de dossier - {{ $fraisDossier->classeAnnee->classe->niveau->nom }} {{ $fraisDossier->classeAnnee->classe->suffixe }}
            </h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.frais-dossiers.index') }}" class="text-secondary-light hover-text-primary hover-underline">Frais de dossier</a>
                <span class="text-secondary-light">/ Gérer</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h5>Informations générales</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.frais-dossiers.update', $fraisDossier) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="classe_annee_id" class="form-label">Classe (secondaire uniquement)</label>
                            <select name="classe_annee_id" id="classe_annee_id" class="form-select @error('classe_annee_id') is-invalid @enderror" required>
                                @foreach($classesAnnees as $ca)
                                    <option value="{{ $ca->id }}" {{ old('classe_annee_id', $fraisDossier->classe_annee_id) == $ca->id ? 'selected' : '' }}>
                                        {{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }} - {{ $ca->anneeScolaire->libelle }}
                                    </option>
                                @endforeach
                            </select>
                            @error('classe_annee_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="montant" class="form-label">Montant (FCFA)</label>
                            <input type="number" step="500" min="0" class="form-control @error('montant') is-invalid @enderror" id="montant" name="montant" value="{{ old('montant', $fraisDossier->montant) }}" required>
                            @error('montant') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description" value="{{ old('description', $fraisDossier->description) }}">
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                        <a href="{{ route('admin.frais-dossiers.index') }}" class="btn btn-secondary">Annuler</a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h5>Élèves de la classe — {{ number_format($fraisDossier->montant, 0, ',', ' ') }} FCFA</h5>
                </div>
                <div class="card-body p-0">
                    @if($eleves->isEmpty())
                        <p class="text-muted p-3">Aucun élève dans cette classe.</p>
                    @else
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <th>Payé</th>
                                    <th>Statut</th>
                                    <th>Enregistrer un paiement</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($eleves as $eleve)
                                    @php $paye = $totalPayeParEleve[$eleve->id] ?? 0; @endphp
                                    <tr>
                                        <td>{{ $eleve->nom }} {{ $eleve->prenom }}</td>
                                        <td>{{ number_format($paye, 0, ',', ' ') }}</td>
                                        <td>
                                            @if($paye >= $fraisDossier->montant)
                                                <span class="badge bg-success">En règle</span>
                                            @else
                                                <span class="badge bg-danger">Non en règle</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($paye < $fraisDossier->montant)
                                            <form action="{{ route('admin.frais-dossiers.payer', $fraisDossier) }}" method="POST" class="d-flex gap-1">
                                                @csrf
                                                <input type="hidden" name="eleve_id" value="{{ $eleve->id }}">
                                                <input type="number" name="montant" class="form-control form-control-sm" style="width:90px" placeholder="Montant" min="1" required>
                                                <select name="mode_paiement" class="form-select form-select-sm" style="width:120px" required>
                                                    <option value="especes">Espèces</option>
                                                    <option value="mobile_money">Mobile money</option>
                                                    <option value="virement">Virement</option>
                                                    <option value="carte">Carte</option>
                                                    <option value="cheque">Chèque</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary">OK</button>
                                            </form>
                                            @endif
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
