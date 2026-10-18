@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Périodes ({{ $anneeActive->libelle }})</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Périodes</span>
            </div>
        </div>
        <a href="{{ route('admin.periodes.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Nouvelle période
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
                        <th>Date début</th>
                        <th>Date fin</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($periodes as $p)
                    <tr>
                        <td>{{ $p->id }}</td>
                        <td>{{ $p->nom }}</td>
                        <td>{{ $p->date_debut_fr }}</td>
                        <td>{{ $p->date_fin_fr }}</td>
                        <td>
                            <a href="{{ route('admin.periodes.edit', $p) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form action="{{ route('admin.periodes.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette période ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center">Aucune période trouvée pour cette année.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection