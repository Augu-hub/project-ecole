@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">
                Tranches de paiement - {{ $scolarite->classeAnnee->classe->niveau->nom }} {{ $scolarite->classeAnnee->classe->suffixe }} ({{ $scolarite->classeAnnee->anneeScolaire->libelle }})
            </h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ <a href="{{ route('admin.scolarites.index') }}">Scolarités</a></span>
                <span class="text-secondary-light">/ Tranches</span>
            </div>
        </div>
        <a href="{{ route('admin.tranches.create', $scolarite) }}" class="btn btn-primary-600 d-flex align-items-center gap-6">
            <i class="ri-add-line"></i> Nouvelle tranche
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <p><strong>Montant annuel :</strong> {{ number_format($scolarite->montant_annuel, 0, ',', ' ') }} FCFA</p>
            <p><strong>Total des tranches :</strong> {{ number_format($tranches->sum('montant'), 0, ',', ' ') }} FCFA</p>

            @if($tranches->isEmpty())
                <p class="text-muted">Aucune tranche définie pour cette scolarité.</p>
            @else
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ordre</th>
                            <th>Libellé</th>
                            <th>Date échéance</th>
                            <th>Montant</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tranches as $tranche)
                        <tr>
                            <td>{{ $tranche->ordre }}</td>
                            <td>{{ $tranche->libelle }}</td>
                            <td>{{ $tranche->date_echeance->locale('fr')->isoFormat('D MMMM YYYY') }}</td>
                            <td>{{ number_format($tranche->montant, 0, ',', ' ') }}</td>
                            <td>
                                <a href="{{ route('admin.tranches.edit', [$scolarite, $tranche]) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                                <form action="{{ route('admin.tranches.destroy', [$scolarite, $tranche]) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette tranche ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="mt-3">
                <a href="{{ route('admin.scolarites.index') }}" class="btn btn-secondary">Retour aux scolarités</a>
            </div>
        </div>
    </div>
</div>
@endsection