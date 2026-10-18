@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Scolarités</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Scolarités</span>
            </div>
        </div>
        <a href="{{ route('admin.scolarites.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Nouvelle scolarité
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
                        <th>Montant annuel</th>
                        <th>Total tranches</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($scolarites as $s)
                    <tr>
                        <td>{{ $s->id }}</td>
                        <td>{{ $s->classeAnnee->classe->niveau->nom }} {{ $s->classeAnnee->classe->suffixe }}</td>
                        <td>{{ $s->classeAnnee->anneeScolaire->libelle }}</td>
                        <td>{{ number_format($s->montant_annuel, 0, ',', ' ') }} FCFA</td>
                        <td>
                            @php
                                $totalTranches = $s->tranches->sum('montant');
                            @endphp
                            {{ number_format($totalTranches, 0, ',', ' ') }} FCFA
                            @if($totalTranches > $s->montant_annuel)
                                <span class="badge bg-danger">Dépassement</span>
                            @elseif($totalTranches == $s->montant_annuel)
                                <span class="badge bg-success">Complet</span>
                            @else
                                <span class="badge bg-warning">Partiel</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.scolarites.edit', $s) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ri-settings-4-line"></i> Gérer
                            </a>
                            <form action="{{ route('admin.scolarites.destroy', $s) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette scolarité ?')">
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
                        <td colspan="6" class="text-center">Aucune scolarité trouvée.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-center mt-4">
                {{ $scolarites->links() }}
            </div>
        </div>
    </div>
</div>
@endsection