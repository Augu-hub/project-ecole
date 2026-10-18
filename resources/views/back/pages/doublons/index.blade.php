@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">

    {{-- Breadcrumb --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Parents en doublon</h1>
            <div>
                <a href="{{ route('admin.dashboard') }}" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Parents doublons</span>
            </div>
        </div>
        <div class="badge bg-danger-focus text-danger-main px-16 py-8 radius-8 fw-semibold fs-14">
            {{ count($groupes) }} numéro(s) en doublon détecté(s)
        </div>
    </div>

    {{-- Alertes --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-checkbox-circle-line me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ri-error-warning-line me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(count($groupes) === 0)
        <div class="card">
            <div class="card-body p-24 text-center">
                <i class="ri-checkbox-circle-fill text-success-main" style="font-size: 48px;"></i>
                <h5 class="mt-3">Aucun doublon détecté</h5>
                <p class="text-secondary-light">Tous les numéros de téléphone sont uniques dans la base.</p>
            </div>
        </div>
    @else

        {{-- Un bloc par groupe de doublons --}}
        @foreach($groupes as $groupe)
        <div class="card mb-4 border border-warning">
            <div class="card-header bg-warning-focus d-flex align-items-center justify-content-between">
                <div>
                    <i class="ri-phone-line me-2 text-warning-main"></i>
                    <span class="fw-semibold">Téléphone : <span class="text-warning-main">{{ $groupe['telephone'] }}</span></span>
                    <span class="badge bg-warning-main text-white ms-2">{{ $groupe['total'] }} comptes</span>
                </div>
            </div>

            <div class="card-body p-24">
                <div class="row g-3">
                    @foreach($groupe['parents'] as $parent)
                    <div class="col-md-6">
                        <div class="border radius-8 p-16 h-100">
                            {{-- En-tête parent --}}
                            <div class="d-flex align-items-center justify-content-between mb-12">
                                <div>
                                    <span class="badge bg-primary-100 text-primary-600 px-8 py-4 radius-4 fs-12 me-2">
                                        ID #{{ $parent->id }}
                                    </span>
                                    <span class="fw-semibold fs-15">{{ $parent->nom }} {{ $parent->prenom }}</span>
                                </div>
                                <span class="text-secondary-light fs-12">User #{{ $parent->user_id }}</span>
                            </div>

                            {{-- Infos parent --}}
                            <div class="mb-12">
                                <p class="mb-4 fs-13 text-secondary-light">
                                    <i class="ri-phone-line me-1"></i> {{ $parent->telephone }}
                                    @if($parent->whatsapp)
                                        &nbsp;|&nbsp;<i class="ri-whatsapp-line me-1 text-success-main"></i> {{ $parent->whatsapp }}
                                    @endif
                                </p>
                                @if($parent->profession)
                                    <p class="mb-4 fs-13 text-secondary-light">
                                        <i class="ri-briefcase-line me-1"></i> {{ $parent->profession }}
                                    </p>
                                @endif
                            </div>

                            {{-- Enfants associés --}}
                            <div class="mb-16">
                                <p class="fw-medium fs-13 mb-8">
                                    <i class="ri-user-3-line me-1"></i>
                                    Enfants associés : <strong>{{ $parent->eleves->count() }}</strong>
                                </p>
                                @if($parent->eleves->count() > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="fs-12">Matricule</th>
                                                    <th class="fs-12">Nom & Prénom</th>
                                                    <th class="fs-12">Classe</th>
                                                    <th class="fs-12">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($parent->eleves as $eleve)
                                                <tr>
                                                    <td class="fs-12">{{ $eleve->matricule }}</td>
                                                    <td class="fs-12">{{ $eleve->nom }} {{ $eleve->prenom }}</td>
                                                    <td class="fs-12">
                                                        {{ $eleve->classeAnnee?->classe?->niveau?->nom }}
                                                        {{ $eleve->classeAnnee?->classe?->suffixe }}
                                                    </td>
                                                    <td>
                                                        {{-- Bouton pour réassigner cet élève --}}
                                                        <button type="button"
                                                            class="btn btn-xs btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalReassigner{{ $eleve->id }}">
                                                            <i class="ri-arrow-left-right-line"></i>
                                                        </button>

                                                        {{-- Modal réassignation --}}
                                                        <div class="modal fade" id="modalReassigner{{ $eleve->id }}" tabindex="-1">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Réassigner {{ $eleve->nom }} {{ $eleve->prenom }}</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <form action="{{ route('admin.doublons.reassigner') }}" method="POST">
                                                                        @csrf
                                                                        @method('PATCH')
                                                                        <input type="hidden" name="eleve_id" value="{{ $eleve->id }}">
                                                                        <div class="modal-body">
                                                                            <p class="fs-14">Choisissez le parent auquel rattacher cet élève :</p>
                                                                            <select name="nouveau_parent_id" class="form-select" required>
                                                                                <option value="">-- Choisir un parent --</option>
                                                                                @foreach($groupe['parents'] as $p)
                                                                                    @if($p->id !== $parent->id)
                                                                                    <option value="{{ $p->id }}">
                                                                                        #{{ $p->id }} — {{ $p->nom }} {{ $p->prenom }}
                                                                                    </option>
                                                                                    @endif
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                            <button type="submit" class="btn btn-primary">Réassigner</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>

                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-secondary-light fs-12 fst-italic">Aucun enfant associé à ce parent.</p>
                                @endif
                            </div>

                            {{-- Bouton fusionner : ce parent absorbe l'autre --}}
                            @foreach($groupe['parents'] as $autre)
                                @if($autre->id !== $parent->id)
                                <button type="button"
                                    class="btn btn-sm btn-outline-success w-100 mt-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalFusionner{{ $parent->id }}_{{ $autre->id }}">
                                    <i class="ri-merge-cells-horizontal me-1"></i>
                                    Garder CE parent &amp; absorber #{{ $autre->id }}
                                </button>

                                {{-- Modal fusion --}}
                                <div class="modal fade" id="modalFusionner{{ $parent->id }}_{{ $autre->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success-focus">
                                                <h5 class="modal-title text-success-main">Confirmer la fusion</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="fs-14">
                                                    Vous allez <strong>garder le parent #{{ $parent->id }} ({{ $parent->nom }} {{ $parent->prenom }})</strong>
                                                    et <strong class="text-danger">supprimer le parent #{{ $autre->id }} ({{ $autre->nom }} {{ $autre->prenom }})</strong>.
                                                </p>
                                                <p class="fs-13 text-secondary-light">
                                                    Tous les enfants du parent supprimé seront automatiquement rattachés au parent conservé.
                                                    Cette action est <strong>irréversible</strong>.
                                                </p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                <form action="{{ route('admin.doublons.fusionner') }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="parent_a_garder_id" value="{{ $parent->id }}">
                                                    <input type="hidden" name="parent_a_supprimer_id" value="{{ $autre->id }}">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="ri-check-line me-1"></i> Confirmer la fusion
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endforeach

                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach

    @endif

</div>
@endsection