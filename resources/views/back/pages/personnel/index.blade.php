@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">

    {{-- Breadcrumb --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Gestion du personnel</h1>
            <div>
                <a href="{{ route('admin.dashboard') }}"
                   class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Personnel</span>
            </div>
        </div>
        <a href="{{ route('admin.personnel.create') }}"
           class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Ajouter un membre
        </a>
    </div>

    {{-- Alerte succès --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-24" role="alert">
            <i class="ri-checkbox-circle-line me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filtres --}}
    <div class="card mb-24">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('admin.personnel.index') }}"
                  class="d-flex align-items-center gap-3 flex-wrap">
                <div class="flex-grow-1" style="min-width:200px;">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Rechercher un nom ou email…"
                           value="{{ request('search') }}">
                </div>
                <div style="min-width:180px;">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">Tous les rôles</option>
                        @foreach($rolesPersonnel as $val => $label)
                            <option value="{{ $val }}" {{ request('role') === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="ri-search-line me-1"></i>Filtrer
                </button>
                @if(request()->hasAny(['search','role']))
                    <a href="{{ route('admin.personnel.index') }}"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="ri-close-line me-1"></i>Réinitialiser
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="card h-100">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($personnel as $membre)
                            @php
                                $colors = $roleColors[$membre->role] ?? ['bg' => 'bg-neutral-200', 'text' => 'text-secondary-light'];
                            @endphp
                            <tr>
                                <td class="text-secondary-light">{{ $membre->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center
                                                    fw-bold bg-neutral-200"
                                             style="width:34px;height:34px;min-width:34px;font-size:13px;">
                                            {{ strtoupper(substr($membre->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <span class="fw-medium d-block">{{ $membre->name }}</span>
                                            @if($membre->superieur)
                                                <small class="text-secondary-light">
                                                    <i class="ri-shield-user-line me-1"></i>Équipe de {{ $membre->superieur->name }}
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="text-secondary-light">{{ $membre->email }}</td>
                                <td>
                                    <span class="badge px-12 py-6 fw-medium radius-4
                                                 {{ $colors['bg'] }} {{ $colors['text'] }}">
                                        {{ $rolesPersonnel[$membre->role] ?? $membre->role }}
                                    </span>
                                </td>
                                <td>
                                    @if($membre->actif)
                                        <span class="badge bg-success-100 text-success-600 px-12 py-6 radius-4">
                                            Actif
                                        </span>
                                    @else
                                        <span class="badge bg-danger-100 text-danger-600 px-12 py-6 radius-4">
                                            Inactif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        {{-- Modifier --}}
                                        <a href="{{ route('admin.personnel.edit', $membre) }}"
                                           class="btn btn-sm btn-outline-primary" title="Modifier">
                                            <i class="ri-edit-line"></i>
                                        </a>

                                        {{-- Reset mot de passe --}}
                                        <form action="{{ route('admin.personnel.reset-password', $membre) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('Réinitialiser le mot de passe à password123 ?')">
                                            @csrf
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-warning"
                                                    title="Réinitialiser le mot de passe">
                                                <i class="ri-lock-password-line"></i>
                                            </button>
                                        </form>

                                        {{-- Supprimer --}}
                                        <form action="{{ route('admin.personnel.destroy', $membre) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('Supprimer ce membre du personnel ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Supprimer">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary-light">
                                    <i class="ri-group-line ri-2x mb-2 d-block"></i>
                                    Aucun membre du personnel trouvé.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($personnel->hasPages())
                <div class="p-3 border-top">
                    {{ $personnel->links() }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
