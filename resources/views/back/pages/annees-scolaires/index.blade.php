@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Années scolaires</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Années scolaires</span>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.annees-scolaires.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
                <i class="ri-add-line"></i> Ajouter une année
            </a>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body p-0">
            @if(session('success'))
                <div class="alert alert-success m-3">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger m-3">{{ session('error') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Libellé</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($annees as $annee)
                        <tr>
                            <td>{{ $annee->id }}</td>
                            <td>{{ $annee->libelle }}</td>
                            <td>{{ $annee->date_debut->format('d/m/Y') }}</td>
                            <td>{{ $annee->date_fin->format('d/m/Y') }}</td>
                            <td>
                                @if($annee->est_active)
                                    <span class="badge bg-success">Oui</span>
                                @else
                                    <span class="badge bg-secondary">Non</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.annees-scolaires.edit', $annee->id) }}">
                                                <i class="ri-pencil-line me-2"></i>Modifier
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('admin.annees-scolaires.destroy', $annee->id) }}" method="POST" onsubmit="return confirm('Supprimer cette année scolaire ?')">
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
                            <td colspan="6" class="text-center">Aucune année scolaire trouvée.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-4">
                {{ $annees->links() }}
            </div>
        </div>
    </div>
</div>
@endsection