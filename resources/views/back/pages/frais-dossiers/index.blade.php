@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Frais de dossier</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Frais de dossier</span>
            </div>
        </div>
        <a href="{{ route('admin.frais-dossiers.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Nouveau frais de dossier
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
                        <th>Montant</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fraisDossiers as $f)
                    <tr>
                        <td>{{ $f->id }}</td>
                        <td>{{ $f->classeAnnee->classe->niveau->nom }} {{ $f->classeAnnee->classe->suffixe }}</td>
                        <td>{{ $f->classeAnnee->anneeScolaire->libelle }}</td>
                        <td>{{ number_format($f->montant, 0, ',', ' ') }} FCFA</td>
                        <td>{{ $f->description }}</td>
                        <td>
                            <a href="{{ route('admin.frais-dossiers.edit', $f) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ri-settings-4-line"></i> Gérer
                            </a>
                            <form action="{{ route('admin.frais-dossiers.destroy', $f) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce frais de dossier ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center">Aucun frais de dossier configuré.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-center mt-4">
                {{ $fraisDossiers->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
