@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Créneaux horaires</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Créneaux horaires</span>
            </div>
        </div>
        <a href="{{ route('admin.creneaux-horaires.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Ajouter un créneau
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
                        <th>Ordre</th>
                        <th>Heure de début</th>
                        <th>Heure de fin</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($creneaux as $creneau)
                    <tr>
                        <td>{{ $creneau->ordre }}</td>
                        <td>{{ $creneau->heure_debut_courte }}</td>
                        <td>{{ $creneau->heure_fin_courte }}</td>
                        <td>
                            <a href="{{ route('admin.creneaux-horaires.edit', $creneau) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form action="{{ route('admin.creneaux-horaires.destroy', $creneau) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce créneau ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center">Aucun créneau horaire configuré.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
