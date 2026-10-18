@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Matières</h1>
            <div>
                <a href=" " class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Matières</span>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.matieres.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
                <i class="ri-add-line"></i> Nouvelle matière
            </a>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body p-0">
            @if(session('success'))
                <div class="alert alert-success m-3">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom de la matière</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matieres as $matiere)
                        <tr>
                            <td>{{ $matiere->id }}</td>
                            <td>{{ $matiere->nom_matiere }}</td>
                            <td>{{ Str::limit($matiere->description, 50) ?? '—' }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.matieres.edit', $matiere) }}">
                                                <i class="ri-pencil-line me-2"></i>Modifier
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('admin.matieres.destroy', $matiere) }}" method="POST" onsubmit="return confirm('Supprimer cette matière ?')">
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
                            <td colspan="4" class="text-center">Aucune matière trouvée.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-4">
                {{ $matieres->links() }}
            </div>
        </div>
    </div>
</div>
@endsection