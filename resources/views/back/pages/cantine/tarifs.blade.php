@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Tarifs cantine</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Cantine / Tarifs</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-24">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.cantine.tarifs') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="annee_scolaire_id" class="form-label">Année scolaire</label>
                    <select name="annee_scolaire_id" id="annee_scolaire_id" class="form-select" onchange="this.form.submit()">
                        @foreach($anneesScolaires as $annee)
                            <option value="{{ $annee->id }}" {{ $anneeScolaire && $anneeScolaire->id == $annee->id ? 'selected' : '' }}>
                                {{ $annee->libelle }} {{ $annee->est_active ? '(active)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="cycle" value="{{ $cycle }}">
            </form>

            {{-- Le primaire n'a pour l'instant que la cantine comme fonctionnalité mobile :
                 son tarif est géré ici séparément du secondaire, au cas où ils diffèrent. --}}
            <div class="btn-group mt-3" role="group">
                <a href="{{ route('admin.cantine.tarifs', ['annee_scolaire_id' => $anneeScolaire?->id, 'cycle' => 'secondaire']) }}"
                   class="btn btn-sm {{ $cycle === 'secondaire' ? 'btn-primary' : 'btn-outline-primary' }}">Secondaire</a>
                <a href="{{ route('admin.cantine.tarifs', ['annee_scolaire_id' => $anneeScolaire?->id, 'cycle' => 'primaire']) }}"
                   class="btn btn-sm {{ $cycle === 'primaire' ? 'btn-primary' : 'btn-outline-primary' }}">Primaire</a>
            </div>
        </div>
    </div>

    @if(!$anneeScolaire)
        <div class="alert alert-info">Veuillez choisir une année scolaire.</div>
    @else
        <div class="card h-100">
            <div class="card-header">
                <h5>Tarifs mensuels — {{ $anneeScolaire->libelle }} ({{ ucfirst($cycle) }})</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cantine.tarifs.enregistrer') }}" method="POST">
                    @csrf
                    <input type="hidden" name="annee_scolaire_id" value="{{ $anneeScolaire->id }}">
                    <input type="hidden" name="cycle" value="{{ $cycle }}">

                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Mois</th>
                                <th>Ticket cantine (FCFA)</th>
                                <th>Petit-déjeuner (FCFA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($mois as $numero => $label)
                                @php
                                    $ticketExistant = ($offres[$numero] ?? collect())->firstWhere('type', 'ticket');
                                    $petitDejExistant = ($offres[$numero] ?? collect())->firstWhere('type', 'petit_dejeuner');
                                @endphp
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td>
                                        <input type="number" step="500" min="0" class="form-control form-control-sm"
                                               name="ticket[{{ $numero }}]" value="{{ old('ticket.'.$numero, $ticketExistant?->montant) }}">
                                    </td>
                                    <td>
                                        <input type="number" step="500" min="0" class="form-control form-control-sm"
                                               name="petit_dejeuner[{{ $numero }}]" value="{{ old('petit_dejeuner.'.$numero, $petitDejExistant?->montant) }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <button type="submit" class="btn btn-primary mt-3">Enregistrer les tarifs</button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
