@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">

    {{-- ── Breadcrumb ──────────────────────────────────────────────── --}}
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
                <li class="text-secondary-light fw-medium">
                    {{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }}
                </li>
            </ul>
            <h6 class="fw-semibold mb-0 mt-1">
                📒 {{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }}
                — {{ $periode->nom }}
                — {{ $classeAnnee->anneeScolaire->libelle }}
            </h6>
        </div>

        <div class="d-flex gap-8 flex-wrap">
            <a href="{{ route('admin.cahier-notes.liste-eleves', ['classeAnnee' => $classeAnnee->id, 'periode_id' => $periode->id, 'mode_moyenne' => $modeMoyenne]) }}"
               class="btn btn-outline-secondary btn-sm">
                <iconify-icon icon="solar:users-group-rounded-outline" class="me-1"></iconify-icon>
                Liste élèves
            </a>
            <a href="{{ route('admin.cahier-notes.index') }}"
               class="btn btn-outline-secondary btn-sm">
                <iconify-icon icon="solar:arrow-left-outline" class="me-1"></iconify-icon>
                Retour
            </a>
            <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                <iconify-icon icon="solar:printer-minimalistic-bold" class="me-1"></iconify-icon>
                Imprimer
            </button>
        </div>
    </div>

    {{-- ── Sélecteur de période (navigation rapide) ────────────────── --}}
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

    {{-- ── Stats rapides ───────────────────────────────────────────── --}}
    @php $nbEleves = $eleves->count(); @endphp
    <div class="row gy-3 mb-24">
        <div class="col-xl-2 col-md-4 col-sm-6 col-6">
            <div class="card radius-12 h-100">
                <div class="card-body p-16 text-center">
                    <div class="fw-bold text-2xl text-primary-600 mb-4">{{ $nbEleves }}</div>
                    <div class="text-secondary-light text-xs">Élèves</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 col-6">
            <div class="card radius-12 h-100">
                <div class="card-body p-16 text-center">
                    <div class="fw-bold text-2xl mb-4
                        {{ $statsGenerales['moy_classe'] !== null && $statsGenerales['moy_classe'] >= 10 ? 'text-success-600' : 'text-danger-600' }}">
                        {{ $statsGenerales['moy_classe'] !== null ? number_format($statsGenerales['moy_classe'], 2) : '—' }}
                    </div>
                    <div class="text-secondary-light text-xs">Moy. générale /20</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 col-6">
            <div class="card radius-12 h-100">
                <div class="card-body p-16 text-center">
                    <div class="fw-bold text-2xl text-success-600 mb-4">
                        {{ $statsGenerales['max'] !== null ? number_format($statsGenerales['max'], 2) : '—' }}
                    </div>
                    <div class="text-secondary-light text-xs">Meilleure moy.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 col-6">
            <div class="card radius-12 h-100">
                <div class="card-body p-16 text-center">
                    <div class="fw-bold text-2xl text-danger-600 mb-4">
                        {{ $statsGenerales['min'] !== null ? number_format($statsGenerales['min'], 2) : '—' }}
                    </div>
                    <div class="text-secondary-light text-xs">Moy. la plus basse</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 col-6">
            <div class="card radius-12 h-100">
                <div class="card-body p-16 text-center">
                    <div class="fw-bold text-2xl text-warning-600 mb-4">
                        {{ $statsGenerales['nb_admis'] }} / {{ $nbEleves }}
                    </div>
                    <div class="text-secondary-light text-xs">Admis (≥ 10)</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 col-6">
            <div class="card radius-12 h-100">
                <div class="card-body p-16 text-center">
                    <div class="fw-bold text-2xl mb-4
                        {{ $statsGenerales['taux_reussite'] >= 50 ? 'text-success-600' : 'text-warning-600' }}">
                        {{ $statsGenerales['taux_reussite'] }}%
                    </div>
                    <div class="text-secondary-light text-xs">Taux de réussite</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Tableau principal ───────────────────────────────────────── --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height:75vh; overflow:auto">
                <table class="table table-bordered mb-0"
                       id="tableCahier"
                       style="font-size:0.75rem; min-width:{{ 220 + ($eleves->count() * 60) }}px">

                    {{-- ══ ENTÊTE : Noms des élèves ══════════════════ --}}
                    <thead style="position:sticky; top:0; z-index:20">

                        {{-- Ligne 1 : matricule --}}
                        <tr>
                            <th colspan="2"
                                style="position:sticky; left:0; z-index:30;
                                       background:#1D4ED8; color:#fff;
                                       min-width:200px; vertical-align:middle">
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-sm">
                                        {{ $classeAnnee->classe->niveau->nom }}
                                        {{ $classeAnnee->classe->suffixe }}
                                    </span>
                                    <span style="font-size:.68rem; opacity:.85">
                                        {{ $periode->nom }}
                                        — {{ $classeAnnee->anneeScolaire->libelle }}
                                    </span>
                                </div>
                            </th>

                            @foreach($eleves as $i => $eleve)
                                <th class="text-center p-4"
                                    style="background:#1E3A8A; color:#fff;
                                           min-width:58px; max-width:70px;
                                           writing-mode:vertical-rl;
                                           text-orientation:mixed;
                                           transform:rotate(180deg);
                                           height:110px;
                                           vertical-align:bottom;
                                           font-weight:600; font-size:.7rem">
                                    {{ $eleve->nom }}
                                    {{ mb_substr($eleve->prenom, 0, 1) }}.
                                </th>
                            @endforeach
                        </tr>

                        {{-- Ligne 2 : numéro / rang --}}
                        <tr>
                            <th style="position:sticky; left:0; z-index:30;
                                       background:#1D4ED8; color:#fff;
                                       min-width:160px; font-size:.72rem">
                                Matière / Type de note
                            </th>
                            <th class="text-center"
                                style="position:sticky; left:160px; z-index:30;
                                       background:#1D4ED8; color:#fff;
                                       min-width:42px; font-size:.7rem">
                                Coef.
                            </th>

                            @foreach($eleves as $i => $eleve)
                                <th class="text-center"
                                    style="background:#1E3A8A; color:#93C5FD;
                                           font-size:.65rem; padding:2px 4px">
                                    N°{{ $i + 1 }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    {{-- ══ CORPS ═════════════════════════════════════ --}}
                    <tbody>

                        @foreach($matieres as $mat)

                            {{-- ── Sous-lignes : une note par type ─── --}}
                            @foreach($typesNotes as $tn)
                                <tr>
                                    {{-- Matière + type --}}
                                    <td style="position:sticky; left:0; z-index:10;
                                               background:#F8FAFF; min-width:160px;
                                               font-size:.72rem; color:#374151"
                                        class="ps-12">
                                        @if($loop->first)
                                            <span class="fw-bold text-primary-700">
                                                {{ $mat->nom_matiere }}
                                            </span>
                                            <br>
                                        @endif
                                        <span class="text-secondary-light ps-8">
                                            {{ $tn->libelle }}
                                        </span>
                                    </td>

                                    {{-- Coeff (affiché seulement sur la 1ère sous-ligne) --}}
                                    <td class="text-center"
                                        style="position:sticky; left:160px; z-index:10;
                                               background:#F8FAFF; font-size:.7rem;
                                               color:#6B7280; min-width:42px">
                                        @if($loop->first)
                                            <span class="fw-semibold text-primary-600">
                                                {{ number_format($mat->coefficient, 2) }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Note(s) de chaque élève : plusieurs valeurs possibles pour un
                                         type comme Interrogation (n°1, n°2...), affichées séparées par
                                         « · ». La couleur reflète alors leur moyenne. --}}
                                    @foreach($eleves as $eleve)
                                        @php
                                            $vs   = $notes[$eleve->id][$mat->id][$tn->id] ?? [];
                                            $v    = count($vs) ? array_sum($vs) / count($vs) : null;
                                            $bg   = '';
                                            $color= '';
                                            $fw   = '';
                                            if ($v !== null) {
                                                if ($v >= 16)     { $bg='#DCFCE7'; $color='#166534'; $fw='600'; }
                                                elseif ($v >= 14) { $bg='#DBEAFE'; $color='#1E40AF'; }
                                                elseif ($v >= 10) { $bg=''; $color='#374151'; }
                                                elseif ($v >= 8)  { $bg='#FEF3C7'; $color='#92400E'; }
                                                else              { $bg='#FEE2E2'; $color='#991B1B'; $fw='600'; }
                                            }
                                        @endphp
                                        <td class="text-center p-0"
                                            style="background:{{ $bg }};
                                                   color:{{ $color }};
                                                   font-weight:{{ $fw ?: '400' }};
                                                   font-size:.76rem; width:58px"
                                            @if(count($vs) > 1) title="{{ implode(' · ', array_map(fn($x) => number_format($x, 2), $vs)) }}" @endif>
                                            @if(count($vs) > 1)
                                                {{ implode(' · ', array_map(fn($x) => number_format($x, 1), $vs)) }}
                                            @elseif($v !== null)
                                                {{ number_format($v, 2) }}
                                            @else
                                                <span style="color:#D1D5DB">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach

                            {{-- ── Ligne Moyenne de la matière ────── --}}
                            <tr style="border-top:2px solid #BFDBFE">
                                <td style="position:sticky; left:0; z-index:10;
                                           background:#DBEAFE; min-width:160px;
                                           font-size:.72rem"
                                    class="ps-12 fw-bold text-primary-700">
                                    ⌀ Moy. {{ $mat->nom_matiere }}
                                </td>
                                <td class="text-center fw-semibold text-primary-600"
                                    style="position:sticky; left:160px; z-index:10;
                                           background:#DBEAFE; font-size:.7rem">
                                    {{ number_format($mat->coefficient, 2) }}
                                </td>

                                @foreach($eleves as $eleve)
                                    @php
                                        $mm = $moyMatiere[$eleve->id][$mat->id] ?? null;
                                        $bg = '#EFF6FF';
                                        $color = '#1E40AF';
                                        $fw = '700';
                                        if ($mm !== null) {
                                            if ($mm >= 16)     { $bg='#DCFCE7'; $color='#166534'; }
                                            elseif ($mm >= 10) { $bg='#DBEAFE'; $color='#1E40AF'; }
                                            elseif ($mm >= 8)  { $bg='#FEF3C7'; $color='#92400E'; }
                                            else               { $bg='#FEE2E2'; $color='#991B1B'; }
                                        }
                                    @endphp
                                    <td class="text-center"
                                        style="background:{{ $bg }};
                                               color:{{ $color }};
                                               font-weight:{{ $fw }};
                                               font-size:.76rem;
                                               border-top:2px solid #BFDBFE">
                                        {{ $mm !== null ? number_format($mm, 2) : '—' }}
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Ligne stats matière (moy classe min max) --}}
                            <tr style="background:#F9FAFB; border-bottom:3px solid #E5E7EB">
                                <td colspan="2"
                                    style="position:sticky; left:0; z-index:10;
                                           background:#F9FAFB; font-size:.67rem;
                                           color:#6B7280; padding:2px 8px 2px 20px">
                                    Moy. classe :
                                    <strong style="color:#1D4ED8">
                                        {{ $statsMat[$mat->id]['moy'] !== null
                                            ? number_format($statsMat[$mat->id]['moy'], 2)
                                            : '—' }}
                                    </strong>
                                    &nbsp;|&nbsp; Min :
                                    <strong style="color:#DC2626">
                                        {{ $statsMat[$mat->id]['min'] !== null
                                            ? number_format($statsMat[$mat->id]['min'], 2)
                                            : '—' }}
                                    </strong>
                                    &nbsp;|&nbsp; Max :
                                    <strong style="color:#16A34A">
                                        {{ $statsMat[$mat->id]['max'] !== null
                                            ? number_format($statsMat[$mat->id]['max'], 2)
                                            : '—' }}
                                    </strong>
                                </td>
                                @foreach($eleves as $eleve)
                                    <td style="background:#F9FAFB"></td>
                                @endforeach
                            </tr>

                        @endforeach

                        {{-- ══ LIGNE MOYENNE GÉNÉRALE ══════════════ --}}
                        <tr style="border-top:3px solid #1D4ED8; background:#1E3A8A">
                            <td style="position:sticky; left:0; z-index:10;
                                       background:#1E3A8A; color:#fff;
                                       font-size:.78rem; min-width:160px"
                                class="ps-12 fw-bold">
                                🏆 Moyenne Générale
                                <br>
                                <small style="opacity:.75; font-size:.65rem">
                                    {{ $modeMoyenne === 'ponderee' ? 'Pondérée · Σ coeff. ' . number_format($totalCoeff, 2) : 'Simple (sans coeff.)' }}
                                </small>
                            </td>
                            <td style="position:sticky; left:160px; z-index:10;
                                       background:#1E3A8A; color:#93C5FD;
                                       text-align:center; font-size:.7rem">
                                {{ number_format($totalCoeff, 2) }}
                            </td>

                            @foreach($eleves as $eleve)
                                @php
                                    $mg = $moyGenerale[$eleve->id] ?? null;
                                    $bg = '#1E3A8A';
                                    $color = '#fff';
                                    if ($mg !== null) {
                                        if ($mg >= 16)     { $bg='#14532D'; $color='#BBF7D0'; }
                                        elseif ($mg >= 14) { $bg='#1D4ED8'; $color='#BFDBFE'; }
                                        elseif ($mg >= 10) { $bg='#1E40AF'; $color='#EFF6FF'; }
                                        elseif ($mg >= 8)  { $bg='#92400E'; $color='#FEF3C7'; }
                                        else               { $bg='#991B1B'; $color='#FEE2E2'; }
                                    }
                                @endphp
                                <td class="text-center fw-bold"
                                    style="background:{{ $bg }};
                                           color:{{ $color }};
                                           font-size:.82rem">
                                    {{ $mg !== null ? number_format($mg, 2) : '—' }}
                                </td>
                            @endforeach
                        </tr>

                        {{-- ══ LIGNE RANG ════════════════════════════ --}}
                        @if($afficherRang)
                            <tr style="background:#0F172A">
                                <td style="position:sticky; left:0; z-index:10;
                                           background:#0F172A; color:#94A3B8;
                                           font-size:.72rem; min-width:160px"
                                    class="ps-12 fw-bold">
                                    🥇 Rang / {{ $nbEleves }}
                                </td>
                                <td style="position:sticky; left:160px; z-index:10;
                                           background:#0F172A"></td>

                                @foreach($eleves as $eleve)
                                    @php $r = $rangs[$eleve->id] ?? null; @endphp
                                    <td class="text-center fw-bold p-4"
                                        style="background:#0F172A;
                                               color:{{ $r <= 3 ? '#FCD34D' : '#94A3B8' }};
                                               font-size:.78rem">
                                        @if($r !== null)
                                            {{ $r }}{{ $r == 1 ? 'er' : 'e' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endif

                    </tbody>

                </table>
            </div>
        </div>
    </div>

    {{-- ── Légende + Total élèves ──────────────────────────────────── --}}
    <div class="d-flex flex-wrap gap-12 mt-16 align-items-center">
        <span class="text-secondary-light text-xs fw-semibold">Légende :</span>
        <span class="badge px-8 py-4 radius-4 text-xs fw-semibold"
              style="background:#DCFCE7; color:#166534">≥ 16 Excellent</span>
        <span class="badge px-8 py-4 radius-4 text-xs fw-semibold"
              style="background:#DBEAFE; color:#1E40AF">≥ 14 Bien</span>
        <span class="badge px-8 py-4 radius-4 text-xs fw-semibold"
              style="background:#F0F9FF; color:#374151">≥ 10 Passable</span>
        <span class="badge px-8 py-4 radius-4 text-xs fw-semibold"
              style="background:#FEF3C7; color:#92400E">≥ 8 Insuffisant</span>
        <span class="badge px-8 py-4 radius-4 text-xs fw-semibold"
              style="background:#FEE2E2; color:#991B1B">< 8 Faible</span>
        <span class="ms-auto text-secondary-light text-xs">
            {{ $nbEleves }} élève(s) · {{ $matieres->count() }} matière(s)
            · {{ $typesNotes->count() }} type(s) de notes
        </span>
    </div>

</div>

@push('styles')
<style>
/* ── Impression ───────────────────────────────────────── */
@media print {
    .sidebar, nav, .breadcrumb-parent, .btn,
    .row.gy-3.mb-24 { display: none !important; }
    .table-responsive { overflow: visible !important; max-height: none !important; }
    table { font-size: 6.5pt !important; }
    thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
/* ── Hover ligne ─────────────────────────────────────── */
#tableCahier tbody tr:hover td:not([style*='sticky']) {
    filter: brightness(.95);
}
/* ── Bordures ────────────────────────────────────────── */
#tableCahier.table-bordered td,
#tableCahier.table-bordered th {
    border-color: #E2E8F0 !important;
}
</style>
@endpush

@endsection