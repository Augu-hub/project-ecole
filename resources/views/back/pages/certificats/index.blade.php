@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Certificats de scolarité</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Certificats</span>
            </div>
        </div>
    </div>

    <div class="card mb-24">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.certificats.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="q" class="form-label">Rechercher (élève, matricule, numéro)</label>
                    <input type="text" class="form-control" id="q" name="q" value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>N° certificat</th>
                        <th>Élève</th>
                        <th>Classe</th>
                        <th>Année scolaire</th>
                        <th>Motif</th>
                        <th>Généré le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($certificats as $c)
                    <tr>
                        <td><code>{{ $c->numero_unique }}</code></td>
                        <td>{{ $c->eleve->nom }} {{ $c->eleve->prenom }}</td>
                        <td>{{ $c->classeAnnee->classe->niveau->nom }} {{ $c->classeAnnee->classe->suffixe }}</td>
                        <td>{{ $c->classeAnnee->anneeScolaire->libelle }}</td>
                        <td>{{ $c->motif ?: '—' }}</td>
                        <td>{{ $c->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('admin.certificats.download', $c) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ri-download-line"></i> Télécharger
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center">Aucun certificat généré.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-center mt-4">
                {{ $certificats->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
