@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">

    {{-- Breadcrumb --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Prévisualisation de l'import</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ <a href="{{ route('admin.notes.create') }}">Notes</a></span>
                <span class="text-secondary-light">/ Prévisualisation</span>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.notes.export-pdf') }}"
               class="btn btn-danger d-flex align-items-center gap-1" target="_blank">
                <i class="ri-file-pdf-line"></i> Télécharger PDF officiel CCPA
            </a>
        </div>
    </div>

    {{-- Contexte --}}
    <div class="alert alert-info d-flex align-items-center gap-3 mb-3 py-2">
        <img src="{{ asset('assets/images/LOGOCCPA.jpeg') }}" alt="CCPA"
             style="height:36px; border-radius:4px; object-fit:contain;">
        <div class="small">
            <strong>Classe :</strong> {{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }}
            &nbsp;|&nbsp; <strong>Matière :</strong> {{ $matiere->nom_matiere }}
            &nbsp;|&nbsp; <strong>Période :</strong> {{ $periode->nom }}
            &nbsp;|&nbsp; <strong>Type :</strong> {{ $typeNote->libelle }}
        </div>
    </div>

    {{-- Bilan de la lecture par IA : toute la classe est listée, avec la note lue ou une case à saisir --}}
    @php $lectureIa = session('ia_mode') || isset($ia_mode); @endphp
    @if($lectureIa)
        @php
            $iaMeta = session('ia_meta', []);
            $photosLues = (int) ($iaMeta['photos'] ?? 1);
            $notesLues = (int) ($iaMeta['lues'] ?? 0);
            $effectif = (int) ($iaMeta['effectif'] ?? 0);
        @endphp
        <div class="alert {{ $notesLues > 0 ? 'alert-success' : 'alert-danger' }} d-flex align-items-start gap-2 mb-3">
            <i class="ri-sparkling-2-line fs-5 mt-1"></i>
            <div>
                @if($notesLues > 0)
                    <strong>{{ $photosLues > 1 ? "Lecture de {$photosLues} photos par IA réussie" : 'Lecture de la photo par IA réussie' }}</strong> :
                    {{ $notesLues }} {{ $notesLues > 1 ? 'notes lues' : 'note lue' }} sur {{ $effectif }} {{ $effectif > 1 ? 'élèves' : 'élève' }}.
                    @if($notesLues < $effectif) Les autres sont à saisir à la main. @endif
                    Comparez-les à la fiche avant d'enregistrer.
                @else
                    <strong>Aucune note n'a pu être rattachée à un élève de cette classe.</strong>
                    Vérifiez la classe et le type de note choisis, ou saisissez les notes à la main.
                @endif
                @if(!empty($iaMeta['unmatched']))
                    &nbsp;<span class="badge bg-warning text-dark">
                        ⚠ {{ $iaMeta['unmatched'] }} élève(s) non reconnu(s) : non repris
                    </span>
                @endif
                @if(!empty($iaMeta['warnings']))
                    <ul class="mb-0 mt-1 small">
                        @foreach($iaMeta['warnings'] as $w)
                            <li>{{ $w }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

    {{-- Erreurs globales (import Excel ; la lecture par IA donne son bilan ci-dessus) --}}
    @if($hasErrors && ! $lectureIa)
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
            <i class="ri-error-warning-line fs-5"></i>
            <span>Des erreurs ont été détectées. Corrigez le fichier et réimportez, ou corrigez manuellement les lignes concernées.</span>
        </div>
    @endif

    {{-- Tableau --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <h6 class="mb-0 fw-semibold">
                <i class="ri-table-line me-1"></i>
                Notes extraites —
                <span class="text-primary">{{ count($importedData) }} ligne(s)</span>
            </h6>
            @if(!$hasErrors)
                <span class="badge bg-success">
                    <i class="ri-check-line me-1"></i>Prêt à valider
                </span>
            @else
                <span class="badge bg-danger">
                    <i class="ri-close-line me-1"></i>Erreurs à corriger
                </span>
            @endif
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead style="background:#003366; color:#fff;">
                        <tr>
                            <th class="text-center" style="width:45px">#</th>
                            <th>Matricule</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th class="text-center" style="width:110px">Note /20</th>
                            <th>Commentaire</th>
                            <th class="text-center" style="width:130px">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($importedData as $i => $item)
                            <tr class="{{ !empty($item['errors']) ? 'table-danger' : '' }}">
                                <td class="text-center text-muted small">{{ $i + 1 }}</td>
                                <td><code class="small">{{ $item['matricule'] }}</code></td>
                                <td class="fw-semibold">{{ $item['nom'] }}</td>
                                <td>{{ $item['prenom'] }}</td>
                                <td class="text-center">
                                    @if($item['note'] !== '' && $item['note'] !== null)
                                        @php $n = (float)$item['note']; @endphp
                                        <span class="badge fs-6
                                            {{ $n >= 16 ? 'bg-success' : ($n >= 10 ? 'bg-primary' : 'bg-danger') }}">
                                            {{ $item['note'] }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $item['commentaire'] ?? '' }}</td>
                                <td class="text-center">
                                    @if(!empty($item['errors']))
                                        <span class="badge bg-danger small">
                                            {{ implode(' · ', $item['errors']) }}
                                        </span>
                                    @elseif(($item['matched_by'] ?? '') === 'ia_vision' && ($item['note'] === null || $item['note'] === ''))
                                        <span class="badge bg-light text-secondary border">À saisir</span>
                                    @elseif(($item['matched_by'] ?? '') === 'ia_vision')
                                        <span class="badge bg-info text-dark">
                                            <i class="ri-sparkling-2-line me-1"></i>IA
                                        </span>
                                    @else
                                        <span class="badge bg-success">
                                            <i class="ri-check-line me-1"></i>OK
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="ri-inbox-line fs-3 d-block mb-2"></i>
                                    Aucune donnée à afficher.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
            @if(!$hasErrors)
                <form action="{{ route('admin.notes.preview') }}" method="GET">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-check-double-line me-1"></i>Appliquer ces notes dans le formulaire
                    </button>
                </form>

                <a href="{{ route('admin.notes.export-pdf') }}" class="btn btn-danger" target="_blank">
                    <i class="ri-file-pdf-line me-1"></i>Télécharger PDF officiel
                </a>
            @endif

            <a href="{{ route('admin.notes.preview') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-go-back-line me-1"></i>Retour saisie
            </a>
            <a href="{{ route('admin.notes.create') }}" class="btn btn-secondary">
                <i class="ri-close-line me-1"></i>Annuler
            </a>
        </div>
    </div>

</div>
@endsection