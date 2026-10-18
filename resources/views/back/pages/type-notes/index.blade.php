@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Types de notes</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Types de notes</span>
            </div>
        </div>
        <a href="{{ route('admin.type-notes.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Nouveau type
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
                        <th>Code</th>
                        <th>Max par période</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($types as $t)
                    <tr>
                        <td>{{ $t->id }}</td>
                        <td>{{ $t->nom }}</td>
                        <td>{{ $t->code }}</td>
                        <td>{{ $t->max_par_periode ?? 'Illimité' }}</td>
                        <td>
                            <a href="{{ route('admin.type-notes.edit', $t) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form action="{{ route('admin.type-notes.destroy', $t) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce type de note ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center">Aucun type de note trouvé.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-center mt-4">
                {{ $types->links() }}
            </div>
        </div>
    </div>
</div>
@endsection