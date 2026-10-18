@extends('back.layouts.master')

@section('title', 'Accès aux menus')

@section('content')
<div class="dashboard-main-body">

    {{-- Breadcrumb --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Accès aux menus</h1>
            <div>
                <a href="{{ route('admin.dashboard') }}"
                   class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Accès aux menus</span>
            </div>
        </div>
    </div>

    {{-- Réglage pas encore disponible : la liste reste consultable, sans bouton --}}
    @if($avis)
        <div class="alert alert-info py-2 mb-24" role="alert">
            <i class="ri-information-line me-1"></i>{{ $avis }}
        </div>
    @endif

    {{-- Tableau --}}
    <div class="card h-100">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Compte</th>
                            <th>Rôle</th>
                            @unless($avis)
                                <th>Menus</th>
                                <th><span class="visually-hidden">Action</span></th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($comptes as $compte)
                            <tr>
                                <td>
                                    <span class="fw-medium d-block">
                                        {{ $compte->name }}
                                        @unless($compte->actif)
                                            <span class="badge bg-neutral-200 text-secondary-light px-8 py-4 radius-4 ms-1">Inactif</span>
                                        @endunless
                                    </span>
                                    <small class="text-secondary-light">{{ $compte->email }}</small>
                                </td>
                                <td>{{ $compte->role_libelle }}</td>
                                @unless($avis)
                                    @php $etat = $menus[$compte->id]; @endphp
                                    <td>
                                        @if($etat['fermes'] === 0)
                                            <span class="badge bg-success-100 text-success-600 px-12 py-6 radius-4">Tous activés</span>
                                        @else
                                            <span class="badge bg-warning-100 text-warning-600 px-12 py-6 radius-4">{{ $etat['fermes'] }} {{ $etat['fermes'] > 1 ? 'désactivés' : 'désactivé' }} sur {{ $etat['total'] }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.acces-menus.edit', $compte) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="ri-list-settings-line me-1"></i>Régler
                                        </a>
                                    </td>
                                @endunless
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-secondary-light">
                                    <i class="ri-group-line ri-2x mb-2 d-block"></i>
                                    Aucun compte du personnel.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination « Précédent / Suivant » : celle par défaut affiche une phrase en anglais --}}
            @if($comptes->hasPages())
                <div class="p-3 border-top">
                    {{ $comptes->links('pagination::simple-bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
