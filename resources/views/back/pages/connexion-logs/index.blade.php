@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Journal des connexions parents</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Connexions</span>
            </div>
        </div>
    </div>

    <div class="card mb-24">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.connexion-logs.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Rechercher un parent</label>
                    <input type="text" name="q" class="form-control" placeholder="Nom, prénom ou téléphone" value="{{ request('q') }}">
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
                        <th>Date/heure</th>
                        <th>Parent</th>
                        <th>Téléphone</th>
                        <th>Adresse IP</th>
                        <th>Appareil</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $log->parent ? trim($log->parent->prenom.' '.$log->parent->nom) : '—' }}</td>
                        <td>{{ $log->parent?->telephone }}</td>
                        <td>{{ $log->ip_address }}</td>
                        <td style="font-size:12px;" class="text-secondary-light">{{ \Illuminate\Support\Str::limit($log->user_agent, 60) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center">Aucune connexion enregistrée.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-center mt-4 mb-3">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
