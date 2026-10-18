@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Classes</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Classes</span>
            </div>
        </div>
        <a href="{{ route('admin.classes.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Ajouter une classe
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
                        <th>Niveau</th>
                        <th>Suffixe</th>
                        <th>Nom complet</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classes as $classe)
                    <tr>
                        <td>{{ $classe->id }}</td>
                        <td>{{ $classe->niveau->nom }}</td>
                        <td>{{ $classe->suffixe ?: '—' }}</td>
                        <td>{{ $classe->full_name }}</td>
                        <td>
                            <a href="{{ route('admin.classes.edit', $classe) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form action="{{ route('admin.classes.destroy', $classe) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette classe ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center">Aucune classe enregistrée.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection