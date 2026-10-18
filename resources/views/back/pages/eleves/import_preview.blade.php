@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">

    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Prévisualisation de l'import</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Élèves / Import / Prévisualisation</span>
            </div>
        </div>
    </div>

    {{-- Classe-année sélectionnée --}}
    <div class="alert alert-primary d-flex align-items-center gap-2 mb-3">
        <i class="ri-group-line fs-5"></i>
        <div>
            <strong>Classe ciblée :</strong>
            {{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }}
            — {{ $classeAnnee->anneeScolaire->libelle }}
        </div>
    </div>

    {{-- Avertissements (non bloquants : matricules doublons, etc.) --}}
    @if(!empty($errors))
        <div class="card border-warning mb-3">
            <div class="card-header bg-warning bg-opacity-25 d-flex align-items-center gap-2 py-2">
                <i class="ri-alert-line text-warning fs-5"></i>
                <strong>{{ count($errors) }} avertissement(s) détecté(s)</strong>
                <button class="btn btn-sm btn-link ms-auto text-warning p-0"
                        type="button" data-bs-toggle="collapse" data-bs-target="#warningList">
                    Afficher / Masquer
                </button>
            </div>
            <div id="warningList" class="collapse show">
                <div class="card-body py-2">
                    <ul class="mb-0 small">
                        @foreach($errors as $line => $errs)
                            <li>
                                <strong>Ligne {{ $line }} :</strong>
                                {{ implode(' — ', $errs) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span>
                <i class="ri-file-list-3-line me-1"></i>
                <strong>{{ count($data) }}</strong> ligne(s) valide(s) à importer
            </span>
            <div class="d-flex gap-2 align-items-center">
                <span class="badge bg-success">
                    <i class="ri-checkbox-circle-line me-1"></i>Sélectionné : <span id="count-selected">{{ count($data) }}</span>
                </span>
            </div>
        </div>

        <div class="card-body p-0">

            @if(empty($data))
                <div class="p-4 text-center text-muted">
                    <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                    Aucune donnée valide à importer.
                </div>
                <div class="p-3">
                    <a href="{{ route('admin.eleves.import.create') }}" class="btn btn-secondary">
                        <i class="ri-arrow-left-line me-1"></i> Retour
                    </a>
                </div>

            @else
                <form action="{{ route('admin.eleves.import.store') }}" method="POST" id="import-form">
                    @csrf

                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width:40px">
                                        <input type="checkbox" id="select-all" checked
                                               title="Tout sélectionner / désélectionner">
                                    </th>
                                    <th>#</th>
                                    <th>Matricule</th>
                                    <th>Nom</th>
                                    <th>Prénoms</th>
                                    <th>Classe (Excel)</th>
                                    <th>N° GSM</th>
                                    <th>Parent</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data as $i => $item)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected[]"
                                               value="{{ $item['index'] }}" checked
                                               class="row-checkbox">
                                    </td>
                                    <td class="text-muted small">{{ $item['line_number'] }}</td>
                                    <td>
                                        <code class="small">{{ $item['matricule'] }}</code>
                                    </td>
                                    <td class="fw-semibold">{{ $item['nom'] }}</td>
                                    <td>{{ $item['prenom'] }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ $item['classe_excel'] ?: '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($item['tel_eleve'])
                                            <span class="font-monospace small">{{ $item['tel_eleve'] }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if(str_starts_with($item['parent_status'], '✓'))
                                            <span class="text-success">{{ $item['parent_status'] }}</span>
                                        @elseif(str_starts_with($item['parent_status'], '+'))
                                            <span class="text-primary">{{ $item['parent_status'] }}</span>
                                        @else
                                            <span class="text-muted">{{ $item['parent_status'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 border-top d-flex align-items-center gap-3 flex-wrap">
                        <button type="submit" class="btn btn-primary" id="btn-import">
                            <i class="ri-upload-line me-1"></i>
                            Importer les lignes sélectionnées (<span id="btn-count">{{ count($data) }}</span>)
                        </button>
                        <a href="{{ route('admin.eleves.import.create') }}" class="btn btn-outline-secondary">
                            <i class="ri-arrow-left-line me-1"></i> Annuler
                        </a>
                        <div class="ms-auto small text-muted">
                            <i class="ri-information-line"></i>
                            <span class="text-success fw-semibold">✓ Existant</span> = parent déjà en base &nbsp;|&nbsp;
                            <span class="text-primary fw-semibold">+ Créé</span> = nouveau parent généré automatiquement
                        </div>
                    </div>
                </form>
            @endif

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const selectAll    = document.getElementById('select-all');
    const checkboxes   = document.querySelectorAll('.row-checkbox');
    const countBadge   = document.getElementById('count-selected');
    const btnCount     = document.getElementById('btn-count');

    function updateCount() {
        const n = document.querySelectorAll('.row-checkbox:checked').length;
        if (countBadge) countBadge.textContent = n;
        if (btnCount)   btnCount.textContent   = n;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateCount();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            selectAll.checked = [...checkboxes].every(c => c.checked);
            selectAll.indeterminate = !selectAll.checked && [...checkboxes].some(c => c.checked);
            updateCount();
        });
    });
})();
</script>
@endpush