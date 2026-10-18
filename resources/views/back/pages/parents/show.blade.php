@extends('back.layouts.master')

@section('title', 'Détails du parent')
@section('content')

<div class="dashboard-main-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <h6 class="fw-semibold mb-0">Détails du parent : {{ $parent->prenom }} {{ $parent->nom }}</h6>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.parents.edit', $parent) }}" class="btn btn-warning">
                <i class="ri-pencil-line"></i> Modifier
            </a>
            <a href="{{ route('admin.parents.reset-password.form', $parent) }}" class="btn btn-info">
                <i class="ri-lock-line"></i> Réinitialiser mot de passe
            </a>
            <a href="{{ route('admin.parents.index') }}" class="btn btn-secondary">
                <i class="ri-arrow-go-back-line"></i> Retour
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informations personnelles</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="150">ID</th>
                            <td>{{ $parent->id }}</td>
                        </tr>
                        <tr>
                            <th>Nom</th>
                            <td>{{ $parent->nom }}</td>
                        </tr>
                        <tr>
                            <th>Prénom</th>
                            <td>{{ $parent->prenom }}</td>
                        </tr>
                        <tr>
                            <th>Téléphone</th>
                            <td>{{ $parent->telephone }}</td>
                        </tr>
                        <tr>
                            <th>WhatsApp</th>
                            <td>{{ $parent->whatsapp ?? 'Non renseigné' }}</td>
                        </tr>
                        <tr>
                            <th>Profession</th>
                            <td>{{ $parent->profession ?? 'Non renseignée' }}</td>
                        </tr>
                        <tr>
                            <th>Adresse</th>
                            <td>{{ $parent->adresse ?? 'Non renseignée' }}</td>
                        </tr>
                        <tr>
                            <th>Email (utilisateur)</th>
                            <td>{{ $parent->user->email ?? 'Non renseigné' }}</td>
                        </tr>
                        <tr>
                            <th>Date de création</th>
                            <td>{{ $parent->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Dernière mise à jour</th>
                            <td>{{ $parent->updated_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Enfants inscrits ({{ $parent->eleves->count() }})</h5>
                    <a href="{{ route('admin.eleves.index') }}?parent_id={{ $parent->id }}" class="btn btn-sm btn-primary">Voir tous</a>
                </div>
                <div class="card-body">
                    @if($parent->eleves->isEmpty())
                        <p class="text-muted">Aucun enfant enregistré pour ce parent.</p>
                    @else
                        <div class="list-group">
                            @foreach($parent->eleves as $eleve)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $eleve->nom }} {{ $eleve->prenom }}</strong><br>
                                    <small class="text-muted">Matricule: {{ $eleve->matricule }}</small>
                                </div>
                                <span class="badge bg-primary">{{ $eleve->classeAnnee->classe->niveau->nom ?? '' }} {{ $eleve->classeAnnee->classe->suffixe ?? '' }}</span>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection