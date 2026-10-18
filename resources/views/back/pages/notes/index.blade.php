@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Gestion des notes</h1>
            <div>
                <a href="{{ route('admin.dashboard') }}" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Notes</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.notes.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
                <i class="ri-add-line"></i> Saisir des notes
            </a>
            <button type="button" id="exportBtn" class="btn btn-success-600 d-flex align-items-center gap-6">
                <i class="ri-file-excel-2-line"></i> Exporter
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-light py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <h5 class="mb-0">
                    <i class="ri-filter-3-line me-2"></i>
                    Filtres avancés
                </h5>
                <div class="text-muted small">
                    <i class="ri-database-2-line"></i> 
                    Total : <strong class="text-primary">{{ $notes->total() }}</strong> note(s)
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Formulaire de filtres -->
            <form method="GET" action="{{ route('admin.notes.index') }}" id="filterForm" class="mb-4">
                <div class="row g-3">
                    <!-- Filtre par année scolaire -->
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">
                            <i class="ri-calendar-line me-1"></i> Année scolaire
                        </label>
                        <select name="annee_scolaire_id" class="form-select form-select-sm filter-select">
                            <option value="">Toutes les années</option>
                            @foreach($anneesScolaires as $annee)
                                <option value="{{ $annee->id }}" {{ $anneeSelectionneeId == $annee->id ? 'selected' : '' }}>
                                    {{ $annee->libelle }}
                                    @if($annee->est_active) <span class="text-success">(Active)</span> @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtre par classe -->
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">
                            <i class="ri-group-line me-1"></i> Classe
                        </label>
                        <select name="classe_id" id="classeFilter" class="form-select form-select-sm filter-select">
                            <option value="">Toutes les classes</option>
                            @foreach($classes as $classe)
                                <option value="{{ $classe->id }}" {{ request('classe_id') == $classe->id ? 'selected' : '' }}>
                                    {{ $classe->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtre par période -->
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">
                            <i class="ri-time-line me-1"></i> Période
                        </label>
                        <select name="periode_id" class="form-select form-select-sm filter-select">
                            <option value="">Toutes les périodes</option>
                            @foreach($periodes as $periode)
                                <option value="{{ $periode->id }}" {{ request('periode_id') == $periode->id ? 'selected' : '' }}>
                                    {{ $periode->nom }} ({{ $periode->anneeScolaire->libelle ?? '' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtre par matière -->
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">
                            <i class="ri-book-open-line me-1"></i> Matière
                        </label>
                        <select name="matiere_id" class="form-select form-select-sm filter-select">
                            <option value="">Toutes les matières</option>
                            @foreach($matieres as $matiere)
                                <option value="{{ $matiere->id }}" {{ request('matiere_id') == $matiere->id ? 'selected' : '' }}>
                                    {{ $matiere->nom_matiere }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtre par type de note -->
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">
                            <i class="ri-price-tag-line me-1"></i> Type de note
                        </label>
                        <select name="type_note_id" class="form-select form-select-sm filter-select">
                            <option value="">Tous les types</option>
                            @foreach($typeNotes as $type)
                                <option value="{{ $type->id }}" {{ request('type_note_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->libelle }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtre par date de saisie -->
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">
                            <i class="ri-calendar-event-line me-1"></i> Date saisie
                        </label>
                        <select name="date_range" class="form-select form-select-sm filter-select">
                            <option value="">Toutes les dates</option>
                            <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Aujourd'hui</option>
                            <option value="yesterday" {{ request('date_range') == 'yesterday' ? 'selected' : '' }}>Hier</option>
                            <option value="this_week" {{ request('date_range') == 'this_week' ? 'selected' : '' }}>Cette semaine</option>
                            <option value="last_week" {{ request('date_range') == 'last_week' ? 'selected' : '' }}>Semaine dernière</option>
                            <option value="this_month" {{ request('date_range') == 'this_month' ? 'selected' : '' }}>Ce mois</option>
                            <option value="last_month" {{ request('date_range') == 'last_month' ? 'selected' : '' }}>Mois dernier</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <!-- Recherche par élève -->
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">
                            <i class="ri-user-search-line me-1"></i> Recherche élève
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Nom, prénom ou matricule..." value="{{ request('search') }}">
                        </div>
                    </div>

                    <!-- Filtre par note minimale -->
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">
                            <i class="ri-bar-chart-2-line me-1"></i> Note min
                        </label>
                        <input type="number" name="note_min" class="form-control form-control-sm" step="0.5" min="0" max="20" value="{{ request('note_min') }}" placeholder="0">
                    </div>

                    <!-- Filtre par note maximale -->
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">
                            <i class="ri-bar-chart-2-line me-1"></i> Note max
                        </label>
                        <input type="number" name="note_max" class="form-control form-control-sm" step="0.5" min="0" max="20" value="{{ request('note_max') }}" placeholder="20">
                    </div>

                    <!-- Actions -->
                    <div class="col-md-5 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary-600 btn-sm">
                            <i class="ri-filter-3-line"></i> Appliquer les filtres
                        </button>
                        <a href="{{ route('admin.notes.index') }}" class="btn btn-secondary-600 btn-sm">
                            <i class="ri-refresh-line"></i> Réinitialiser
                        </a>
                        
                        @if(request()->anyFilled(['annee_scolaire_id', 'classe_id', 'periode_id', 'matiere_id', 'type_note_id', 'date_range', 'search', 'note_min', 'note_max']))
                            <span class="badge bg-info-soft text-info ms-2">
                                <i class="ri-information-line"></i> Filtres actifs
                            </span>
                        @endif
                    </div>
                </div>
            </form>

            <hr class="my-4">

            <!-- Barre de tri -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small fw-semibold">
                        <i class="ri-sort-ascending me-1"></i> Trier par :
                    </span>
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'created_at', 'direction' => ($sortBy == 'created_at' && $sortDirection == 'desc') ? 'asc' : 'desc'])) }}" 
                           class="btn {{ $sortBy == 'created_at' ? 'btn-primary-600' : 'btn-outline-secondary' }}">
                            <i class="ri-calendar-line"></i> Date
                            @if($sortBy == 'created_at')
                                <i class="ri-arrow-{{ $sortDirection == 'desc' ? 'down' : 'up' }}-line"></i>
                            @endif
                        </a>
                        <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'classe', 'direction' => ($sortBy == 'classe' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" 
                           class="btn {{ $sortBy == 'classe' ? 'btn-primary-600' : 'btn-outline-secondary' }}">
                            <i class="ri-group-line"></i> Classe
                            @if($sortBy == 'classe')
                                <i class="ri-arrow-{{ $sortDirection == 'asc' ? 'up' : 'down' }}-line"></i>
                            @endif
                        </a>
                        <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'eleve', 'direction' => ($sortBy == 'eleve' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" 
                           class="btn {{ $sortBy == 'eleve' ? 'btn-primary-600' : 'btn-outline-secondary' }}">
                            <i class="ri-user-line"></i> Élève
                            @if($sortBy == 'eleve')
                                <i class="ri-arrow-{{ $sortDirection == 'asc' ? 'up' : 'down' }}-line"></i>
                            @endif
                        </a>
                        <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'matiere', 'direction' => ($sortBy == 'matiere' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" 
                           class="btn {{ $sortBy == 'matiere' ? 'btn-primary-600' : 'btn-outline-secondary' }}">
                            <i class="ri-book-open-line"></i> Matière
                            @if($sortBy == 'matiere')
                                <i class="ri-arrow-{{ $sortDirection == 'asc' ? 'up' : 'down' }}-line"></i>
                            @endif
                        </a>
                        <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'valeur', 'direction' => ($sortBy == 'valeur' && $sortDirection == 'desc') ? 'asc' : 'desc'])) }}" 
                           class="btn {{ $sortBy == 'valeur' ? 'btn-primary-600' : 'btn-outline-secondary' }}">
                            <i class="ri-bar-chart-2-line"></i> Note
                            @if($sortBy == 'valeur')
                                <i class="ri-arrow-{{ $sortDirection == 'desc' ? 'down' : 'up' }}-line"></i>
                            @endif
                        </a>
                        <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'periode', 'direction' => ($sortBy == 'periode' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" 
                           class="btn {{ $sortBy == 'periode' ? 'btn-primary-600' : 'btn-outline-secondary' }}">
                            <i class="ri-time-line"></i> Période
                            @if($sortBy == 'periode')
                                <i class="ri-arrow-{{ $sortDirection == 'asc' ? 'up' : 'down' }}-line"></i>
                            @endif
                        </a>
                    </div>
                </div>
                <div class="text-muted small">
                    <i class="ri-information-line"></i> Cliquez sur les en-têtes de colonnes pour trier
                </div>
            </div>

            <!-- Tableau des notes -->
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">
                                <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'id', 'direction' => ($sortBy == 'id' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" 
                                   class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                    ID
                                    @if($sortBy == 'id')
                                        <i class="ri-arrow-{{ $sortDirection == 'asc' ? 'up' : 'down' }}-line"></i>
                                    @endif
                                </a>
                            </th>
                            <th>
                                <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'classe', 'direction' => ($sortBy == 'classe' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" 
                                   class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                    <i class="ri-group-line me-1"></i> Classe
                                    @if($sortBy == 'classe')
                                        <i class="ri-arrow-{{ $sortDirection == 'asc' ? 'up' : 'down' }}-line"></i>
                                    @endif
                                </a>
                            </th>
                            <th>
                                <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'eleve', 'direction' => ($sortBy == 'eleve' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" 
                                   class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                    <i class="ri-user-line me-1"></i> Élève
                                    @if($sortBy == 'eleve')
                                        <i class="ri-arrow-{{ $sortDirection == 'asc' ? 'up' : 'down' }}-line"></i>
                                    @endif
                                </a>
                            </th>
                            <th>
                                <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'matiere', 'direction' => ($sortBy == 'matiere' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" 
                                   class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                    <i class="ri-book-open-line me-1"></i> Matière
                                    @if($sortBy == 'matiere')
                                        <i class="ri-arrow-{{ $sortDirection == 'asc' ? 'up' : 'down' }}-line"></i>
                                    @endif
                                </a>
                            </th>
                            <th>
                                <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'periode', 'direction' => ($sortBy == 'periode' && $sortDirection == 'asc') ? 'desc' : 'asc'])) }}" 
                                   class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                    <i class="ri-time-line me-1"></i> Période
                                    @if($sortBy == 'periode')
                                        <i class="ri-arrow-{{ $sortDirection == 'asc' ? 'up' : 'down' }}-line"></i>
                                    @endif
                                </a>
                            </th>
                            <th>Type</th>
                            <th class="text-center" width="120">
                                <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'valeur', 'direction' => ($sortBy == 'valeur' && $sortDirection == 'desc') ? 'asc' : 'desc'])) }}" 
                                   class="text-decoration-none text-dark d-flex align-items-center gap-1 justify-content-center">
                                    <i class="ri-bar-chart-2-line me-1"></i> Note /20
                                    @if($sortBy == 'valeur')
                                        <i class="ri-arrow-{{ $sortDirection == 'desc' ? 'down' : 'up' }}-line"></i>
                                    @endif
                                </a>
                            </th>
                            <th>Commentaire</th>
                            <th width="150">
                                <a href="{{ route('admin.notes.index', array_merge(request()->except(['sort', 'direction']), ['sort' => 'created_at', 'direction' => ($sortBy == 'created_at' && $sortDirection == 'desc') ? 'asc' : 'desc'])) }}" 
                                   class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                    <i class="ri-calendar-line me-1"></i> Date saisie
                                    @if($sortBy == 'created_at')
                                        <i class="ri-arrow-{{ $sortDirection == 'desc' ? 'down' : 'up' }}-line"></i>
                                    @endif
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notes as $note)
                        <tr>
                            <td class="fw-medium">{{ $note->id }}</td>
                            <td>
                                @php
                                    $classeName = $note->eleve->classeAnnee->classe->full_name ?? 
                                                  ($note->eleve->classeAnnee->classe->niveau->nom ?? '') . ' ' . 
                                                  ($note->eleve->classeAnnee->classe->suffixe ?? '');
                                @endphp
                                <span class="badge bg-secondary-soft text-secondary">
                                    <i class="ri-group-line me-1"></i> {{ trim($classeName) ?: 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold">
                                        <i class="ri-user-line me-1 text-muted"></i> 
                                        {{ $note->eleve->nom }} {{ $note->eleve->prenom }}
                                    </span>
                                    <small class="text-muted">
                                        <i class="ri-id-card-line me-1"></i> {{ $note->eleve->matricule ?? 'N/A' }}
                                    </small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-soft text-primary">
                                    <i class="ri-book-open-line me-1"></i> {{ $note->matiere->nom_matiere }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info-soft text-info">
                                    <i class="ri-time-line me-1"></i> {{ $note->periode->nom }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-purple-soft text-purple">
                                    <i class="ri-price-tag-line me-1"></i> {{ $note->typeNote->libelle }}
                                </span>
                            </td>
                            <td class="text-center">
                                @php
                                    $noteValue = $note->valeur;
                                    $noteClass = $noteValue >= 16 ? 'success' : ($noteValue >= 14 ? 'info' : ($noteValue >= 12 ? 'primary' : ($noteValue >= 10 ? 'warning' : 'danger')));
                                    $noteIcon = $noteValue >= 16 ? 'ri-star-fill' : ($noteValue >= 10 ? 'ri-star-line' : 'ri-alert-line');
                                @endphp
                                <div class="d-inline-block">
                                    <span class="badge bg-{{ $noteClass }}-soft text-{{ $noteClass }} fs-6 px-3 py-2 rounded-pill">
                                        <i class="{{ $noteIcon }} me-1"></i> {{ number_format($noteValue, 2) }} / 20
                                    </span>
                                </div>
                            </td>
                            <td>
                                @if($note->commentaire)
                                    <span class="text-muted small" data-bs-toggle="tooltip" title="{{ $note->commentaire }}">
                                        <i class="ri-chat-1-line me-1"></i> {{ Str::limit($note->commentaire, 40) }}
                                    </span>
                                @else
                                    <span class="text-muted fst-italic">
                                        <i class="ri-chat-1-line me-1"></i> —
                                    </span>
                                @endif
                            </td>
                            <td class="text-muted small">
                                <div><i class="ri-calendar-line me-1"></i> {{ $note->created_at->format('d/m/Y') }}</div>
                                <small><i class="ri-time-line me-1"></i> {{ $note->created_at->format('H:i') }}</small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                                    <p class="mb-0 fw-semibold">Aucune note trouvée</p>
                                    <small>Aucune note ne correspond à vos critères de recherche</small>
                                    <div class="mt-3">
                                        <a href="{{ route('admin.notes.create') }}" class="btn btn-sm btn-primary-600">
                                            <i class="ri-add-line"></i> Saisir des notes
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination avec informations -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4 pt-2 border-top">
                <div class="text-muted small">
                    <i class="ri-database-2-line"></i> 
                    Affichage de <strong>{{ $notes->firstItem() ?? 0 }}</strong> à <strong>{{ $notes->lastItem() ?? 0 }}</strong> 
                    sur <strong>{{ $notes->total() }}</strong> note(s)
                </div>
                <div>
                    {{ $notes->appends(request()->query())->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Badges personnalisés */
.badge.bg-purple-soft {
    background-color: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
}
.badge.bg-secondary-soft {
    background-color: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}
.badge.bg-primary-soft {
    background-color: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}
.badge.bg-success-soft {
    background-color: rgba(34, 197, 94, 0.1);
    color: #16a34a;
}
.badge.bg-info-soft {
    background-color: rgba(14, 165, 233, 0.1);
    color: #0284c7;
}
.badge.bg-warning-soft {
    background-color: rgba(245, 158, 11, 0.1);
    color: #d97706;
}
.badge.bg-danger-soft {
    background-color: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}

/* Filtres */
.filter-select {
    cursor: pointer;
    transition: all 0.2s ease;
}
.filter-select:hover {
    border-color: var(--bs-primary);
}

/* Boutons de tri */
.btn-group .btn {
    transition: all 0.2s ease;
}
.btn-group .btn:hover {
    transform: translateY(-1px);
}

/* En-têtes de tableau */
.table th a {
    transition: all 0.2s ease;
    font-weight: 600;
}
.table th a:hover {
    color: var(--bs-primary) !important;
}

/* Lignes du tableau */
.table-hover tbody tr {
    transition: all 0.2s ease;
}
.table-hover tbody tr:hover {
    background-color: rgba(59, 130, 246, 0.05);
    transform: scale(1.01);
}

/* Tooltips personnalisés */
.custom-tooltip {
    --bs-tooltip-bg: var(--bs-primary);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit on filter change
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    // Debounce pour la recherche
    let debounceTimer;
    // Le champ du formulaire de filtres, pas la recherche de la barre du haut (même nom de champ)
    const searchInput = document.querySelector('#filterForm input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
    }

    // Export functionality
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const form = document.getElementById('filterForm');
            const originalAction = form.action;
            const originalTarget = form.target;
            
            form.action = '{{ route("admin.notes.export") }}';
            form.target = '_blank';
            form.submit();
            
            form.action = originalAction;
            form.target = originalTarget;
        });
    }

    // Initialisation des tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            placement: 'top'
        });
    });

    // Animation sur les badges de note
    const noteBadges = document.querySelectorAll('.badge.rounded-pill');
    noteBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.2s ease';
        });
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
});
</script>
@endpush