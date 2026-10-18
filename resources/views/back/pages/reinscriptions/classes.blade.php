@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">
                Réinscription — {{ $anneeSource->libelle }} → {{ $anneeActive->libelle }}
            </h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.reinscriptions.index') }}" class="text-secondary-light hover-text-primary hover-underline">Réinscriptions</a>
                <span class="text-secondary-light">/ {{ $anneeSource->libelle }}</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info">
        La campagne se traite <strong>classe par classe</strong> : choisissez une classe ci-dessous pour décider,
        élève par élève, s'il passe, redouble, ou ne se réinscrit pas.
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if($classesSources->isEmpty())
                <p class="text-muted p-3">Aucune classe secondaire trouvée pour {{ $anneeSource->libelle }}.</p>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Effectif actif</th>
                            <th>Non traités</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($classesSources as $ca)
                        <tr>
                            <td>{{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }}</td>
                            <td>{{ $ca->effectif_actif }}</td>
                            <td>
                                @if($ca->non_traites > 0)
                                    <span class="badge bg-warning">{{ $ca->non_traites }} restant(s)</span>
                                @else
                                    <span class="badge bg-success">Terminé</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.reinscriptions.campagne-classe', [$anneeSource, $ca]) }}" class="btn btn-sm btn-primary">
                                    Traiter cette classe
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
