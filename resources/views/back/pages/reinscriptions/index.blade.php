@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Réinscriptions</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Réinscriptions</span>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        Année active (cible de la réinscription) : <strong>{{ $anneeActive->libelle }}</strong>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Choisir l'année source</h5>
        </div>
        <div class="card-body p-0">
            @if($anneesSources->isEmpty())
                <p class="text-muted p-3">Aucune autre année scolaire disponible.</p>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Année</th>
                            <th>Période</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($anneesSources as $annee)
                        <tr>
                            <td>{{ $annee->libelle }}</td>
                            <td>{{ $annee->date_debut->format('d/m/Y') }} - {{ $annee->date_fin->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('admin.reinscriptions.campagne', $annee) }}" class="btn btn-sm btn-primary">
                                    Lancer la campagne
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
