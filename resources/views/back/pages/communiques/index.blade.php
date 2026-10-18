@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h6 class="fw-semibold mb-1">Communiqués</h6>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="">Tableau de bord</a></li>
                    <li class="breadcrumb-item active">Communiqués</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.communiques.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
            <i class="ri-add-line"></i> Nouveau communiqué
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-24" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Type</th>
                            <th>Classes</th>
                            <th>Publication</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($communiques as $c)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $c->titre }}</div>
                                <div class="text-muted small">{{ Str::limit(strip_tags($c->contenu), 60) }}</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $c->couleur_type }}-subtle text-{{ $c->couleur_type }} fw-semibold">
                                    {{ $c->label_type }}
                                </span>
                            </td>
                            <td>
                                @foreach($c->classesAnnees->take(2) as $ca)
                                    <span class="badge bg-light text-dark border me-1">
                                        {{ $ca->classe->niveau->nom }}{{ $ca->classe->suffixe }}
                                    </span>
                                @endforeach
                                @if($c->classesAnnees->count() > 2)
                                    <span class="text-muted small">+{{ $c->classesAnnees->count() - 2 }}</span>
                                @endif
                            </td>
                            <td>{{ $c->date_publication->format('d/m/Y') }}</td>
                            <td>
                                <div class="form-check form-switch">
                                    <form action="{{ route('admin.communiques.toggle', $c) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <input class="form-check-input" type="checkbox"
                                            {{ $c->actif ? 'checked' : '' }}
                                            onchange="this.form.submit()">
                                    </form>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('admin.communiques.show', $c) }}"
                                        class="btn btn-sm btn-light-secondary">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                    <a href="{{ route('admin.communiques.edit', $c) }}"
                                        class="btn btn-sm btn-light-primary">
                                        <i class="ri-edit-line"></i>
                                    </a>
                                    <form action="{{ route('admin.communiques.destroy', $c) }}" method="POST"
                                        onsubmit="return confirm('Supprimer ce communiqué ?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light-danger">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Aucun communiqué pour l'instant
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $communiques->links() }}</div>
        </div>
    </div>
</div>
@endsection
