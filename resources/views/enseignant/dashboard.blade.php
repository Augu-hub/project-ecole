@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">
                Bonjour {{ $enseignant->prenom ?? Auth::user()->name }}
            </h1>
            <div>
                <span class="text-secondary-light">
                    @if($anneeActive)
                        Année scolaire active : {{ $anneeActive->libelle }}
                    @else
                        Aucune année scolaire active
                    @endif
                </span>
            </div>
        </div>
        {{-- Raccourcis : seulement vers les menus ouverts au compte --}}
        @php $peutSaisirNotes = \App\Support\AccesMenu::autorise('admin.notes.create'); @endphp
        <div class="d-flex gap-2">
            @if(\App\Support\AccesMenu::autorise('enseignant.emploi-du-temps'))
            <a href="{{ route('enseignant.emploi-du-temps') }}" class="btn btn-outline-primary d-flex align-items-center gap-6">
                <i class="ri-calendar-schedule-line"></i> Mon emploi du temps
            </a>
            @endif
            @if($peutSaisirNotes)
            <a href="{{ route('admin.notes.create') }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
                <i class="ri-add-line"></i> Saisir des notes
            </a>
            @endif
        </div>
    </div>

    @if(!$enseignant)
        <div class="alert alert-warning">
            Aucune fiche enseignant n'est associée à votre compte. Veuillez contacter l'administration.
        </div>
    @elseif($classeMatieres->isEmpty())
        <div class="alert alert-info">
            Aucune classe/matière ne vous est encore assignée pour l'année scolaire active. Veuillez contacter l'administration.
        </div>
    @else
        <div class="card h-100">
            <div class="card-header">
                <h5>Mes classes et matières</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Matière</th>
                            <th>Coefficient</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($classeMatieres as $cm)
                        <tr>
                            <td>{{ $cm->classeAnnee->classe->niveau->nom }} {{ $cm->classeAnnee->classe->suffixe }}</td>
                            <td>{{ $cm->matiere->nom_matiere }}</td>
                            <td>{{ $cm->coefficient }}</td>
                            <td>
                                @if($peutSaisirNotes)
                                <a href="{{ route('admin.notes.create') }}" class="btn btn-sm btn-outline-primary">
                                    Saisir des notes
                                </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
