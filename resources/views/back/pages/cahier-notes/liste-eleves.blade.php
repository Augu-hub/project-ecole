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
                    <a href="{{ url()->route('admin.cahier-notes.classe', ['classeAnnee' => $classeAnnee->id]) }}"
                       class="hover-text-primary">{{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }}</a>
                </li>
                <li class="text-secondary-light">—</li>
                <li class="text-secondary-light fw-medium">Élèves</li>
            </ul>
            <h6 class="fw-semibold mb-0 mt-1">
                📋 Liste des élèves — {{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }}
                @if($periode)
                    <span class="text-sm text-secondary-light">({{ $periode->nom }})</span>
                @endif
            </h6>
        </div>
        <div>
            <a href="{{ url()->route('admin.cahier-notes.classe', ['classeAnnee' => $classeAnnee->id]) . '?' . http_build_query(request()->all()) }}"
               class="btn btn-outline-secondary btn-sm">
                <iconify-icon icon="solar:arrow-left-outline" class="me-1"></iconify-icon>
                Retour au cahier
            </a>
        </div>
    </div>

    {{-- Sélecteur de période --}}
    @if($allPeriodes->count() > 1)
    <div class="d-flex flex-wrap align-items-center gap-8 mb-20">
        <span class="text-secondary-light text-sm fw-semibold me-4">Période :</span>
        @foreach($allPeriodes as $p)
            <a href="{{ url()->current() . '?' . http_build_query(array_merge(request()->except('periode_id'), ['periode_id' => $p->id])) }}"
               class="btn btn-sm {{ $p->id == $periode->id ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $p->nom }}
            </a>
        @endforeach

        <span class="ms-auto text-secondary-light text-sm fw-semibold">Mode :</span>
        <a href="{{ url()->current() . '?' . http_build_query(array_merge(request()->all(), ['mode_moyenne' => 'ponderee'])) }}"
           class="btn btn-sm {{ $modeMoyenne === 'ponderee' ? 'btn-info' : 'btn-outline-secondary' }}">
            Pondérée
        </a>
        <a href="{{ url()->current() . '?' . http_build_query(array_merge(request()->all(), ['mode_moyenne' => 'simple'])) }}"
           class="btn btn-sm {{ $modeMoyenne === 'simple' ? 'btn-info' : 'btn-outline-secondary' }}">
            Simple
        </a>
    </div>
    @endif

    {{-- Tableau des élèves --}}
    <div class="card">
        <div class="card-header py-16 px-24 bg-base d-flex align-items-center justify-content-between">
            <h6 class="text-lg mb-0">Élèves ({{ $eleves->count() }})</h6>
        </div>
        <div class="card-body p-24">
            <div class="table-responsive">
                <table class="table table-bordered text-center align-middle mb-0">
                    <thead class="bg-primary-600 text-white">
                        <tr>
                            <th class="text-start ps-20">N°</th>
                            <th class="text-start">Matricule</th>
                            <th class="text-start">Nom & Prénom</th>
                            <th>Moy. générale</th>
                            <th>Rang</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($eleves as $index => $eleve)
                            <tr>
                                <td class="text-start ps-20 fw-medium">{{ $index + 1 }}</td>
                                <td class="text-start">{{ $eleve->matricule }}</td>
                                <td class="text-start">{{ $eleve->nom }} {{ $eleve->prenom }}</td>
                                <td class="fw-bold {{ ($moyGenerale[$eleve->id] ?? 0) >= 10 ? 'text-success-600' : 'text-danger-600' }}">
                                    {{ isset($moyGenerale[$eleve->id]) ? number_format($moyGenerale[$eleve->id], 2) : '—' }}
                                </td>
                                <td class="fw-semibold">
                                    @if(isset($rangs[$eleve->id]))
                                        {{ $rangs[$eleve->id] }}<sup>{{ $rangs[$eleve->id] == 1 ? 'er' : 'e' }}</sup>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.cahier-notes.bulletin-eleve', ['eleve' => $eleve->id, 'mode_moyenne' => $modeMoyenne]) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <iconify-icon icon="solar:document-text-outline"></iconify-icon>
                                        Bulletin
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-10">Aucun élève dans cette classe.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection