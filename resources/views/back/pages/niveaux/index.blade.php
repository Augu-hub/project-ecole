@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Niveaux</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Niveaux</span>
            </div>
        </div>
        <a href="{{ route('admin.niveaux.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Ajouter un niveau
        </a>
    </div>

    <div class="card h-100">
        <div class="card-body p-0">
            @if(session('success'))
                <div class="alert alert-success m-3">{{ session('success') }}</div>
            @endif

            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Cycle</th>
                        <th>Ordre</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($niveaux as $niveau)
                    <tr>
                        <td>{{ $niveau->id }}</td>
                        <td>{{ $niveau->nom }}</td>
                        <td>
                            @if($niveau->cycle)
                                <span class="badge bg-primary-subtle text-primary-600">{{ ucfirst($niveau->cycle) }}</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning-600">Non catégorisé</span>
                            @endif
                        </td>
                        <td>{{ $niveau->ordre }}</td>
                        <td>
                            <a href="{{ route('admin.niveaux.edit', $niveau) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form action="{{ route('admin.niveaux.destroy', $niveau) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce niveau ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center">Aucun niveau enregistré.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection