@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Modifier le dossier de {{ $eleve->nom }} {{ $eleve->prenom }}</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.eleves.index') }}" class="text-secondary-light hover-text-primary hover-underline">Élèves</a>
                <span class="text-secondary-light">/ Modifier</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.eleves.update', $eleve) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <h5 class="mb-3">Informations de l'élève</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" value="{{ old('nom', $eleve->nom) }}" required>
                                @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('prenom') is-invalid @enderror" id="prenom" name="prenom" value="{{ old('prenom', $eleve->prenom) }}" required>
                                @error('prenom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="sexe" class="form-label">Sexe</label>
                                <select name="sexe" id="sexe" class="form-select @error('sexe') is-invalid @enderror">
                                    <option value="">--</option>
                                    <option value="M" {{ old('sexe', $eleve->sexe) === 'M' ? 'selected' : '' }}>Masculin</option>
                                    <option value="F" {{ old('sexe', $eleve->sexe) === 'F' ? 'selected' : '' }}>Féminin</option>
                                </select>
                                @error('sexe') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="date_naissance" class="form-label">Date de naissance</label>
                                <input type="date" class="form-control @error('date_naissance') is-invalid @enderror" id="date_naissance" name="date_naissance" value="{{ old('date_naissance', optional($eleve->date_naissance)->format('Y-m-d')) }}">
                                @error('date_naissance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Matricule</label>
                                <input type="text" class="form-control" value="{{ $eleve->matricule }}" disabled>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="classe_annee_id" class="form-label">Classe <span class="text-danger">*</span></label>
                                <select name="classe_annee_id" id="classe_annee_id" class="form-select @error('classe_annee_id') is-invalid @enderror" required>
                                    @foreach($classes->groupBy(fn($ca) => ucfirst($ca->classe->niveau->cycle)) as $cycle => $classesDuCycle)
                                        <optgroup label="{{ $cycle }}">
                                            @foreach($classesDuCycle as $ca)
                                                <option value="{{ $ca->id }}" {{ old('classe_annee_id', $eleve->classe_annee_id) == $ca->id ? 'selected' : '' }}>
                                                    {{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }} - {{ $ca->anneeScolaire->libelle }}
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
                                <label for="parent_id" class="form-label">Parent</label>
                                <select name="parent_id" id="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                    <option value="">-- Changer via les champs ci-dessous --</option>
                                    @foreach($parents as $p)
                                        <option value="{{ $p->id }}" {{ old('parent_id', $eleve->parent_id) == $p->id ? 'selected' : '' }}>
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

                        <h5 class="mb-3">Ajouter des documents</h5>
                        @for($i = 0; $i < 2; $i++)
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <input type="file" class="form-control @error('fichiers.'.$i) is-invalid @enderror" name="fichiers[{{ $i }}]">
                                @error('fichiers.'.$i) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5 mb-3">
                                <input type="text" class="form-control" name="fichiers_libelles[{{ $i }}]" placeholder="Libellé">
                            </div>
                        </div>
                        @endfor

                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                        <a href="{{ route('admin.eleves.index') }}" class="btn btn-secondary">Annuler</a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Documents existants</h5>
                </div>
                <div class="card-body p-0">
                    @if($eleve->fichiers->isEmpty())
                        <p class="text-muted p-3">Aucun document.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($eleve->fichiers as $fichier)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="{{ route('admin.fichiers.download', $fichier) }}">{{ $fichier->libelle ?: $fichier->nom_fichier }}</a>
                                        <div class="text-secondary-light" style="font-size:11px;">{{ $fichier->nom_fichier }}</div>
                                    </div>
                                    <form action="{{ route('admin.fichiers.destroy', $fichier) }}" method="POST" onsubmit="return confirm('Supprimer ce document ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5>Certificat de scolarité</h5>
                </div>
                <div class="card-body">
                    @if(!$estSecondaire)
                        <p class="text-muted mb-0">Disponible uniquement pour le cycle secondaire.</p>
                    @else
                        <form action="{{ route('admin.certificats.store', $eleve) }}" method="POST" class="mb-3">
                            @csrf
                            <div class="mb-2">
                                <label for="motif" class="form-label">Motif (optionnel)</label>
                                <input type="text" class="form-control form-control-sm" id="motif" name="motif" placeholder="Ex: dossier de bourse">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ri-file-text-line"></i> Générer un certificat
                            </button>
                        </form>

                        @if($eleve->certificatsScolarite->isNotEmpty())
                            <hr>
                            <h6 class="text-secondary-light" style="font-size:12px;">Historique</h6>
                            <ul class="list-group list-group-flush">
                                @foreach($eleve->certificatsScolarite->sortByDesc('created_at') as $certificat)
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <div>
                                            <a href="{{ route('admin.certificats.download', $certificat) }}">{{ $certificat->numero_unique }}</a>
                                            <div class="text-secondary-light" style="font-size:11px;">{{ $certificat->created_at->format('d/m/Y H:i') }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Cantine et uniformes : affichés seulement si ces menus sont ouverts au compte --}}
            @if(\App\Support\AccesMenu::autorise('admin.cantine.payer'))
            <div class="card mt-3">
                <div class="card-header">
                    <h5>Cantine — {{ $anneeActive->libelle }}</h5>
                </div>
                <div class="card-body p-0">
                    @if($offresCantine->isEmpty())
                        <p class="text-muted p-3 mb-0">Aucun tarif cantine configuré pour cette année. <a href="{{ route('admin.cantine.tarifs') }}">Configurer les tarifs</a>.</p>
                    @else
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Mois</th>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Payer</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($offresCantine as $offre)
                                    @php $paye = $totalPayeParOffre[$offre->id] ?? 0; @endphp
                                    <tr>
                                        <td>{{ $offre->mois_label }}</td>
                                        <td>{{ $offre->type === 'ticket' ? 'Ticket' : 'Petit-déjeuner' }}</td>
                                        <td>{{ number_format($offre->montant, 0, ',', ' ') }}</td>
                                        <td>
                                            @if($paye >= $offre->montant)
                                                <span class="badge bg-success">Payé</span>
                                            @else
                                                <span class="badge bg-danger">Non payé</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($paye < $offre->montant)
                                            <form action="{{ route('admin.cantine.payer', [$eleve, $offre]) }}" method="POST" class="d-flex gap-1">
                                                @csrf
                                                <input type="number" name="montant" class="form-control form-control-sm" style="width:80px" value="{{ $offre->montant }}" min="1" required>
                                                <select name="mode_paiement" class="form-select form-select-sm" style="width:110px" required>
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
            @endif

            @if(\App\Support\AccesMenu::autorise('admin.uniformes.vendre'))
            <div class="card mt-3">
                <div class="card-header">
                    <h5>Uniformes</h5>
                </div>
                <div class="card-body">
                    @if(!$estSecondaire)
                        <p class="text-muted mb-0">Disponible uniquement pour le cycle secondaire.</p>
                    @else
                        <form action="{{ route('admin.uniformes.vendre', $eleve) }}" method="POST" class="row g-2 align-items-end mb-3">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Article</label>
                                <select name="uniforme_id" class="form-select form-select-sm" required>
                                    <option value="">-- Choisir --</option>
                                    @foreach($uniformesCatalogue as $article)
                                        <option value="{{ $article->id }}">
                                            {{ $article->type }} — {{ $article->taille }} ({{ number_format($article->prix, 0, ',', ' ') }} FCFA)
                                            @if($article->en_rupture) [rupture] @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Qté</label>
                                <input type="number" name="quantite" class="form-control form-control-sm" min="1" value="1" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Payer (opt.)</label>
                                <input type="number" name="montant" class="form-control form-control-sm" min="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mode</label>
                                <select name="mode_paiement" class="form-select form-select-sm">
                                    <option value="especes">Espèces</option>
                                    <option value="mobile_money">Mobile money</option>
                                    <option value="virement">Virement</option>
                                    <option value="carte">Carte</option>
                                    <option value="cheque">Chèque</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-sm btn-primary">Vendre</button>
                            </div>
                        </form>

                        @if($ventesUniformes->isNotEmpty())
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Article</th>
                                        <th>Qté</th>
                                        <th>Total</th>
                                        <th>Statut</th>
                                        <th>Payer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ventesUniformes as $vente)
                                        <tr>
                                            <td>{{ \Illuminate\Support\Carbon::parse($vente->date_vente)->format('d/m/Y') }}</td>
                                            <td>{{ $vente->uniforme->type }} — {{ $vente->uniforme->taille }}</td>
                                            <td>{{ $vente->quantite }}</td>
                                            <td>{{ number_format($vente->montant_total, 0, ',', ' ') }}</td>
                                            <td>
                                                @if($vente->statut === 'paye')
                                                    <span class="badge bg-success">Payé</span>
                                                @elseif($vente->statut === 'partiel')
                                                    <span class="badge bg-warning text-dark">Partiel</span>
                                                @else
                                                    <span class="badge bg-danger">Impayé</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($vente->statut !== 'paye')
                                                <form action="{{ route('admin.uniformes.payer', $vente) }}" method="POST" class="d-flex gap-1">
                                                    @csrf
                                                    <input type="number" name="montant" class="form-control form-control-sm" style="width:80px" value="{{ $vente->montant_total - $vente->montant_paye }}" min="1" required>
                                                    <select name="mode_paiement" class="form-select form-select-sm" style="width:110px" required>
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
                        @else
                            <p class="text-muted mb-0">Aucune vente enregistrée pour cet élève.</p>
                        @endif
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
