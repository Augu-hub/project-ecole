@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">

    {{-- Breadcrumb --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Mon équipe</h1>
            <div>
                <a href="{{ route('admin.dashboard') }}"
                   class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Mon équipe</span>
            </div>
        </div>
        <a href="{{ route('admin.equipe.create') }}"
           class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-user-add-line"></i> Ajouter un surveillant
        </a>
    </div>

    {{-- Alerte succès --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-24" role="alert">
            <i class="ri-checkbox-circle-line me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-info py-2 mb-24" role="alert">
        <i class="ri-information-line me-1"></i>
        Les surveillants de votre équipe ont les mêmes accès que vous, sauf la gestion de l'équipe.
    </div>

    {{-- Tableau --}}
    <div class="card h-100">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($membres as $membre)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center
                                                    fw-bold bg-neutral-200"
                                             style="width:34px;height:34px;min-width:34px;font-size:13px;">
                                            {{ strtoupper(substr($membre->name, 0, 2)) }}
                                        </div>
                                        <span class="fw-medium">{{ $membre->name }}</span>
                                    </div>
                                </td>
                                <td class="text-secondary-light">{{ $membre->email }}</td>
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
                                        <a href="{{ route('admin.equipe.edit', $membre) }}"
                                           class="btn btn-sm btn-outline-primary" title="Modifier">
                                            <i class="ri-edit-line"></i>
                                        </a>

                                        {{-- Reset mot de passe --}}
                                        <form action="{{ route('admin.equipe.reset-password', $membre) }}"
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
                                        <form action="{{ route('admin.equipe.destroy', $membre) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('Supprimer ce surveillant et son compte ?')">
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
                                <td colspan="4" class="text-center py-5 text-secondary-light">
                                    <i class="ri-shield-user-line ri-2x mb-2 d-block"></i>
                                    Aucun surveillant dans votre équipe pour le moment.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($membres->hasPages())
                <div class="p-3 border-top">
                    {{ $membres->links() }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
