@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Saisie des notes</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ <a href="{{ route('admin.notes.create') }}">Notes</a></span>
                <span class="text-secondary-light">/ Saisie</span>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <strong>Classe :</strong> {{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }} -
        <strong>Année :</strong> {{ $classeAnnee->anneeScolaire->libelle }}<br>
        <strong>Matière :</strong> {{ $matiere->nom_matiere }} -
        <strong>Période :</strong> {{ $periode->nom }} -
        <strong>Type :</strong> {{ $typeNote->nom }}
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.notes.store') }}" method="POST">
                @csrf
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Note (sur 20)</th>
                            <th>Commentaire</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($eleves as $eleve)
                        <tr>
                            <td>{{ $eleve->matricule }}</td>
                            <td>{{ $eleve->nom }}</td>
                            <td>{{ $eleve->prenom }}</td>
                            <td>
                                <input type="number" step="0.1" min="0" max="20" name="notes[{{ $eleve->id }}][valeur]" class="form-control form-control-sm" style="width: 100px;" required>
                            </td>
                            <td>
                                <input type="text" name="notes[{{ $eleve->id }}][commentaire]" class="form-control form-control-sm" placeholder="Optionnel">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="submit" class="btn btn-primary">Enregistrer les notes</button>
                <a href="{{ route('admin.notes.create') }}" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>
@endsection