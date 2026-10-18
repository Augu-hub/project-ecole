@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">

    {{-- Breadcrumb --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <ul class="d-flex align-items-center gap-2">
                <li class="fw-medium">
                    <a href="{{ route('admin.dashboard') }}"
                       class="d-flex align-items-center gap-1 hover-text-primary">
                        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                        Dashboard
                    </a>
                </li>
                <li class="text-secondary-light">—</li>
                <li class="fw-medium">
                    <a href="{{ route('admin.cahier-notes.index') }}"
                       class="hover-text-primary">Cahier de Notes</a>
                </li>
                <li class="text-secondary-light">—</li>
                <li class="fw-medium">
                    <a href="{{ route('admin.cahier-notes.classe', ['classeAnnee' => $classeAnnee->id]) }}"
                       class="hover-text-primary">{{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }}</a>
                </li>
                <li class="text-secondary-light">—</li>
                <li class="text-secondary-light fw-medium">{{ $eleve->nom }} {{ $eleve->prenom }}</li>
            </ul>
            <h6 class="fw-semibold mb-0 mt-1">
                📄 Bulletin de {{ $eleve->nom }} {{ $eleve->prenom }}
                <span class="text-sm text-secondary-light">({{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }})</span>
            </h6>
        </div>
        <div class="d-flex gap-8">
            <a href="{{ url()->route('admin.cahier-notes.liste-eleves', ['classeAnnee' => $classeAnnee->id]) . '?' . http_build_query(['periode_id' => request('periode_id'), 'mode_moyenne' => $modeMoyenne]) }}"
               class="btn btn-outline-secondary btn-sm">
                <iconify-icon icon="solar:arrow-left-outline" class="me-1"></iconify-icon>
                Retour à la liste
            </a>
            <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                <iconify-icon icon="solar:printer-minimalistic-bold" class="me-1"></iconify-icon>
                Imprimer
            </button>
        </div>
    </div>

    {{-- Mode de calcul --}}
    <div class="d-flex justify-content-end mb-20">
        <span class="badge bg-info-600 text-white px-12 py-8 radius-8">
            Mode : {{ $modeMoyenne === 'ponderee' ? 'Moyenne pondérée' : 'Moyenne simple' }}
        </span>
    </div>

    {{-- Tableau des notes --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 align-middle" style="font-size:0.85rem;">
                    <thead class="bg-primary-600 text-white">
                        <tr>
                            <th class="ps-12" style="min-width:200px;">Matière</th>
                            @foreach($allPeriodes as $p)
                                <th colspan="{{ $typesNotes->count() + 2 }}" class="text-center border-start border-white">
                                    {{ $p->nom }}
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            <th class="ps-12"></th>
                            @foreach($allPeriodes as $p)
                                @foreach($typesNotes as $tn)
                                    <th class="text-center" style="min-width:45px;">{{ $tn->abreviation ?? $tn->libelle }}</th>
                                @endforeach
                                <th class="text-center bg-primary-700">Moy.</th>
                                <th class="text-center bg-primary-800">Classe</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matieres as $mat)
                            <tr>
                                <td class="ps-12 fw-medium">{{ $mat->nom_matiere }} <small class="text-secondary-light">(coef. {{ number_format($mat->coefficient, 2) }})</small></td>
                                @foreach($allPeriodes as $p)
                                    @foreach($typesNotes as $tn)
                                        @php $vs = $notes[$p->id][$mat->id][$tn->id] ?? []; @endphp
                                        <td class="text-center {{ count($vs) ? '' : 'text-secondary-light' }}"
                                            @if(count($vs) > 1) title="{{ implode(' · ', array_map(fn($x) => number_format($x, 2), $vs)) }}" @endif>
                                            @if(count($vs) > 1)
                                                {{ implode('·', array_map(fn($x) => number_format($x, 1), $vs)) }}
                                            @elseif(count($vs) === 1)
                                                {{ number_format($vs[0], 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-center fw-bold {{ $moyMatPeriode[$p->id][$mat->id] >= 10 ? 'text-success-600' : 'text-danger-600' }}">
                                        {{ $moyMatPeriode[$p->id][$mat->id] !== null ? number_format($moyMatPeriode[$p->id][$mat->id], 2) : '—' }}
                                    </td>
                                    <td class="text-center text-primary-600 fw-semibold">
                                        {{ $statsMoyClasse[$p->id][$mat->id] !== null ? number_format($statsMoyClasse[$p->id][$mat->id], 2) : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        {{-- Ligne moyenne générale --}}
                        <tr class="bg-primary-50 fw-bold">
                            <td class="ps-12">Moyenne générale</td>
                            @foreach($allPeriodes as $p)
                                <td colspan="{{ $typesNotes->count() }}" class="text-center"></td>
                                <td class="text-center {{ isset($moyGenPeriode[$p->id]) && $moyGenPeriode[$p->id] >= 10 ? 'text-success-600' : 'text-danger-600' }}">
                                    {{ $moyGenPeriode[$p->id] ?? '—' }}
                                </td>
                                <td class="text-center text-primary-600">{{ $statsGeneralesParPeriode[$p->id]['moy_classe'] ?? '—' }}</td>
                            @endforeach
                        </tr>

                        {{-- Ligne rang --}}
                        <tr class="bg-secondary-100">
                            <td class="ps-12">Rang / {{ $tousEleves->count() }}</td>
                            @foreach($allPeriodes as $p)
                                <td colspan="{{ $typesNotes->count() + 1 }}" class="text-center"></td>
                                <td class="text-center fw-bold">
                                    @if(isset($rangsParPeriode[$p->id]))
                                        {{ $rangsParPeriode[$p->id] }}<sup>{{ $rangsParPeriode[$p->id] == 1 ? 'er' : 'e' }}</sup>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Appréciation --}}
    @if($appreciation)
    <div class="mt-24 p-16 radius-12 bg-primary-50 border-start border-4 border-primary-600">
        <p class="mb-0 fw-semibold text-primary-800">{{ $appreciation }}</p>
        @if($moyAnnuelle)
            <p class="mt-8 mb-0 text-secondary-light">Moyenne annuelle : <strong class="text-dark">{{ number_format($moyAnnuelle, 2) }}/20</strong></p>
        @endif
    </div>
    @endif

</div>

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/cahier-notes-bulletin-eleve.css') }}">
@endpush

@endsection