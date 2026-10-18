@extends('back.layouts.master')
@section('title', 'Liste des parents')
@section('content')

<div class="dashboard-main-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <h6 class="fw-semibold mb-0">Liste des parents</h6>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.parents.import.phpoffice') }}" class="btn btn-primary-600 d-flex align-items-center gap-2">
                <i class="ri-upload-line"></i> Importer
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Formulaire de recherche avancée -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.parents.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="search" class="form-label fw-semibold">Recherche générale</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Nom, prénom, téléphone, email..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label for="enfant" class="form-label fw-semibold">Nom de l'enfant</label>
                    <input type="text" name="enfant" id="enfant" class="form-control" placeholder="Rechercher par enfant" value="{{ request('enfant') }}">
                </div>
                <div class="col-md-3">
                    <label for="classe_id" class="form-label fw-semibold">Classe de l'enfant</label>
                    <select name="classe_id" id="classe_id" class="form-select">
                        <option value="">Toutes les classes</option>
                        @foreach($classes as $classe)
                            <option value="{{ $classe->id }}" {{ request('classe_id') == $classe->id ? 'selected' : '' }}>
                                {{ $classe->classe->niveau->nom ?? '' }} {{ $classe->classe->suffixe ?? '' }}
                                ({{ $classe->anneeScolaire->libelle ?? '' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="ri-filter-3-line"></i> Filtrer
                    </button>
                    <a href="{{ route('admin.parents.index') }}" class="btn btn-outline-secondary" title="Réinitialiser">
                        <i class="ri-refresh-line"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des parents -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-primary-600 text-white">
                        <tr>
                            <th class="text-white">ID</th>
                            <th class="text-white">Parent</th>
                            <th class="text-white">Téléphone</th>
                            <th class="text-white">WhatsApp</th>
                            <th class="text-white">Profession</th>
                            <th class="text-white">Email</th>
                            <th class="text-white text-center">Enfants</th>
                            <th class="text-white text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($parents as $parent)
                        <tr>
                            <td><span class="badge bg-light text-dark">#{{ $parent->id }}</span></td>
                            <td>
                                <div class="fw-semibold">{{ $parent->nom }} {{ $parent->prenom }}</div>
                                <small class="text-secondary-light">ID user: {{ $parent->user_id }}</small>
                            </td>
                            <td>
                                <i class="ri-phone-line text-success me-1"></i>{{ $parent->telephone }}
                            </td>
                            <td>
                                @if($parent->whatsapp)
                                    <i class="ri-whatsapp-line text-success me-1"></i>{{ $parent->whatsapp }}
                                @else
                                    <span class="text-secondary-light">—</span>
                                @endif
                            </td>
                            <td>{{ $parent->profession ?? '—' }}</td>
                            <td>
                                @if($parent->user && $parent->user->email)
                                    <span class="d-inline-block text-truncate" style="max-width: 180px;" data-bs-toggle="tooltip" title="{{ $parent->user->email }}">
                                        {{ $parent->user->email }}
                                    </span>
                                @else
                                    <span class="text-secondary-light">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info rounded-pill">{{ $parent->eleves->count() }}</span>
                                @if($parent->eleves->isNotEmpty())
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-1" data-bs-toggle="popover" data-bs-trigger="hover" title="Liste des enfants" data-bs-content="{{ $parent->eleves->pluck('prenom')->join(', ') }}">
                                        <i class="ri-information-line text-info"></i>
                                    </button>
                                @endif
                            </td>
                            <td>
                                <div class="dropdown text-end">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewParentModal{{ $parent->id }}">
                                                <i class="ri-eye-line me-2"></i>Voir
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.parents.edit', $parent) }}">
                                                <i class="ri-pencil-line me-2"></i>Modifier
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.parents.reset-password.form', $parent) }}">
                                                <i class="ri-lock-password-line me-2"></i>Réinitialiser mot de passe
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('admin.parents.destroy', $parent) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce parent ? Cette action est irréversible.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="ri-delete-bin-line me-2"></i>Supprimer
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="ri-user-unfollow-line" style="font-size: 3rem; color: #ccc;"></i>
                                <p class="mt-3 text-secondary-light">Aucun parent trouvé</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center p-3 border-top">
                <div class="text-secondary-light small">
                    @if($parents->total() > 0)
                        Affichage de {{ $parents->firstItem() }} à {{ $parents->lastItem() }} sur {{ $parents->total() }} parents
                    @endif
                </div>
                <div>
                    {{ $parents->appends(request()->query())->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals pour voir les détails (optionnel, à développer si besoin) -->
@foreach($parents as $parent)
<div class="modal fade" id="viewParentModal{{ $parent->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du parent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Nom :</strong> {{ $parent->nom }} {{ $parent->prenom }}</p>
                <p><strong>Téléphone :</strong> {{ $parent->telephone }}</p>
                <p><strong>WhatsApp :</strong> {{ $parent->whatsapp ?? 'Non renseigné' }}</p>
                <p><strong>Profession :</strong> {{ $parent->profession ?? 'Non renseignée' }}</p>
                <p><strong>Adresse :</strong> {{ $parent->adresse ?? 'Non renseignée' }}</p>
                <p><strong>Email :</strong> {{ $parent->user->email ?? 'Non renseigné' }}</p>
                <p><strong>Enfants :</strong></p>
                <ul>
                    @foreach($parent->eleves as $eleve)
                        <li>{{ $eleve->prenom }} {{ $eleve->nom }} ({{ $eleve->classeAnnee->classe->niveau->nom ?? '' }} {{ $eleve->classeAnnee->classe->suffixe ?? '' }})</li>
                    @endforeach
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
@endforeach

@endsection

@push('scripts')
<script>
    // Initialiser les tooltips Bootstrap pour les emails tronqués
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Initialiser les popovers pour la liste des enfants
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                trigger: 'hover',
                placement: 'top',
                html: true
            })
        });
    });
</script>
@endpush