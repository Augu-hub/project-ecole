@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">
                Emploi du temps - {{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }} ({{ $classeAnnee->anneeScolaire->libelle }})
            </h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Emploi du temps</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Ajouter un créneau</h5>
                </div>
                <div class="card-body">
                    @if($classeMatieres->isEmpty())
                        <p class="text-muted">Aucune matière avec enseignant assigné pour cette classe. Assignez d'abord un enseignant depuis la gestion des coefficients.</p>
                    @elseif($creneauxHoraires->isEmpty())
                        <p class="text-muted">Aucun créneau horaire configuré. <a href="{{ route('admin.creneaux-horaires.create') }}">En créer un</a>.</p>
                    @else
                        <form action="{{ route('admin.emploi-du-temps.store', $classeAnnee) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="matiere_id" class="form-label">Matière (enseignant)</label>
                                <select name="matiere_id" id="matiere_id" class="form-select" required>
                                    <option value="">-- Choisir --</option>
                                    @foreach($classeMatieres as $cm)
                                        <option value="{{ $cm->matiere_id }}">{{ $cm->matiere->nom_matiere }} — {{ $cm->enseignant->nom }} {{ $cm->enseignant->prenom }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="jour_semaine" class="form-label">Jour</label>
                                <select name="jour_semaine" id="jour_semaine" class="form-select" required>
                                    @foreach($jours as $jour)
                                        <option value="{{ $jour }}">{{ ucfirst($jour) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="creneau_horaire_id" class="form-label">Créneau horaire</label>
                                <select name="creneau_horaire_id" id="creneau_horaire_id" class="form-select" required>
                                    @foreach($creneauxHoraires as $c)
                                        <option value="{{ $c->id }}">{{ $c->heure_debut_courte }} - {{ $c->heure_fin_courte }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="salle" class="form-label">Salle (optionnel)</label>
                                <input type="text" class="form-control" id="salle" name="salle">
                            </div>
                            <button type="submit" class="btn btn-primary">Ajouter</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="ri-calendar-2-line text-primary" style="font-size:20px;"></i>
                    <h5 class="mb-0">Emploi du temps de la classe</h5>
                </div>
                <div class="card-body">
                    @include('back.pages.emploi-du-temps._grille', ['showEnseignant' => true, 'showDelete' => true])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
