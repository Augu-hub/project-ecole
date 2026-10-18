@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">
                {{ $classeSource->classe->niveau->nom }} {{ $classeSource->classe->suffixe }}
                — {{ $anneeSource->libelle }} → {{ $anneeActive->libelle }}
            </h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <a href="{{ route('admin.reinscriptions.index') }}" class="text-secondary-light hover-text-primary hover-underline">Réinscriptions</a>
                <a href="{{ route('admin.reinscriptions.campagne', $anneeSource) }}" class="text-secondary-light hover-text-primary hover-underline">{{ $anneeSource->libelle }}</a>
                <span class="text-secondary-light">/ {{ $classeSource->classe->niveau->nom }} {{ $classeSource->classe->suffixe }}</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if($eleves->isEmpty())
                <p class="text-muted p-3">Aucun élève à réinscrire dans cette classe (déjà tous traités).</p>
            @else
                <form action="{{ route('admin.reinscriptions.confirmer', [$anneeSource, $classeSource]) }}" method="POST">
                    @csrf

                    <div class="p-3 border-bottom row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label" style="font-size:12px;">Classe cible pour tous les <strong>promus</strong></label>
                            <select id="bulk-classe-promu" class="form-select form-select-sm">
                                <option value="">-- Ne pas appliquer en masse --</option>
                                @foreach($classesCibles as $ca)
                                    <option value="{{ $ca->id }}" {{ $classeCibleParDefautPromu && $classeCibleParDefautPromu->id === $ca->id ? 'selected' : '' }}>
                                        {{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" style="font-size:12px;">Classe cible pour tous les <strong>redoublants</strong></label>
                            <select id="bulk-classe-redoublant" class="form-select form-select-sm">
                                <option value="">-- Ne pas appliquer en masse --</option>
                                @foreach($classesCibles as $ca)
                                    <option value="{{ $ca->id }}" {{ $classeCibleParDefautRedoublant && $classeCibleParDefautRedoublant->id === $ca->id ? 'selected' : '' }}>
                                        {{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" id="bulk-appliquer" class="btn btn-sm btn-outline-primary w-100">Appliquer à tous</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Élève</th>
                                    <th>Moyenne annuelle</th>
                                    <th>Suggestion</th>
                                    <th>Décision</th>
                                    <th>Classe cible ({{ $anneeActive->libelle }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($eleves as $eleve)
                                    @php
                                        $suggestion = $suggestions[$eleve->id];
                                        $classeCibleDefaut = match ($suggestion['type']) {
                                            'promu' => $classeCibleParDefautPromu,
                                            'redoublant' => $classeCibleParDefautRedoublant,
                                            default => null,
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $eleve->nom }} {{ $eleve->prenom }}</td>
                                        <td>{{ $suggestion['moyenne'] !== null ? number_format($suggestion['moyenne'], 2) : '—' }}</td>
                                        <td>
                                            @if($suggestion['type'] === 'promu')
                                                <span class="badge bg-success">Promu suggéré</span>
                                            @elseif($suggestion['type'] === 'redoublant')
                                                <span class="badge bg-warning">Redoublant suggéré</span>
                                            @else
                                                <span class="badge bg-secondary">Aucune note</span>
                                            @endif
                                        </td>
                                        <td>
                                            <select name="decisions[{{ $eleve->id }}][action]" class="form-select form-select-sm decision-action" data-eleve="{{ $eleve->id }}">
                                                <option value="reinscrire" selected>Réinscrire</option>
                                                <option value="ne_revient_pas">Ne se réinscrit pas</option>
                                            </select>
                                            <input type="hidden" name="decisions[{{ $eleve->id }}][moyenne_suggestion]" value="{{ $suggestion['moyenne'] }}">
                                            <select name="decisions[{{ $eleve->id }}][type]" class="form-select form-select-sm mt-1 decision-type" data-eleve="{{ $eleve->id }}">
                                                <option value="promu" {{ $suggestion['type'] === 'promu' ? 'selected' : '' }}>Promu</option>
                                                <option value="redoublant" {{ $suggestion['type'] === 'redoublant' ? 'selected' : '' }}>Redoublant</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="decisions[{{ $eleve->id }}][classe_annee_id]" class="form-select form-select-sm decision-classe" data-eleve="{{ $eleve->id }}" data-type="{{ $suggestion['type'] }}">
                                                <option value="">-- Choisir --</option>
                                                @foreach($classesCibles as $ca)
                                                    <option value="{{ $ca->id }}" {{ $classeCibleDefaut && $classeCibleDefaut->id === $ca->id ? 'selected' : '' }}>
                                                        {{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top">
                        <button type="submit" class="btn btn-primary">Valider les décisions pour cette classe</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.decision-action').forEach(function (select) {
    select.addEventListener('change', function () {
        var eleveId = this.dataset.eleve;
        var estReinscrit = this.value === 'reinscrire';
        document.querySelector('.decision-type[data-eleve="' + eleveId + '"]').disabled = !estReinscrit;
        document.querySelector('.decision-classe[data-eleve="' + eleveId + '"]').disabled = !estReinscrit;
    });
});

// Quand le type (promu/redoublant) change pour un élève, on retague sa ligne pour que le
// bouton "Appliquer à tous" sache quelle classe cible en masse lui correspond désormais.
document.querySelectorAll('.decision-type').forEach(function (select) {
    select.addEventListener('change', function () {
        var eleveId = this.dataset.eleve;
        document.querySelector('.decision-classe[data-eleve="' + eleveId + '"]').dataset.type = this.value;
    });
});

document.getElementById('bulk-appliquer').addEventListener('click', function () {
    var classePromu = document.getElementById('bulk-classe-promu').value;
    var classeRedoublant = document.getElementById('bulk-classe-redoublant').value;

    document.querySelectorAll('.decision-classe').forEach(function (select) {
        if (select.disabled) return;
        if (select.dataset.type === 'promu' && classePromu) {
            select.value = classePromu;
        } else if (select.dataset.type === 'redoublant' && classeRedoublant) {
            select.value = classeRedoublant;
        }
    });
});
</script>
@endpush
