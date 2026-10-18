@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Classes par année scolaire</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Associations classe-année</span>
            </div>
        </div>
        <a href="{{ route('admin.classe-annees.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Associer une classe
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
                        <th>Classe</th>
                        <th>Année scolaire</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classeAnnees as $ca)
                    <tr>
                        <td>{{ $ca->id }}</td>
                        <td>{{ $ca->classe->full_name ?? '—' }}</td>
                        <td>{{ $ca->anneeScolaire->libelle ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.classe-matieres.index', $ca) }}" class="btn btn-sm btn-info">
                                <i class="ri-book-open-line"></i> Matières
                            </a>
                            @if(($ca->classe->niveau->cycle ?? null) === 'secondaire' && \App\Support\AccesMenu::autorise('admin.emploi-du-temps.classe'))
                                <a href="{{ route('admin.emploi-du-temps.classe', $ca) }}" class="btn btn-sm btn-secondary">
                                    <i class="ri-calendar-schedule-line"></i> Emploi du temps
                                </a>
                            @endif
                            <a href="{{ route('admin.classe-annees.edit', $ca) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form action="{{ route('admin.classe-annees.destroy', $ca) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette association ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center">Aucune association trouvée.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection