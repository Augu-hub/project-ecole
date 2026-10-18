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
                <li class="text-secondary-light fw-medium">Cahier de Notes</li>
            </ul>
            <h6 class="fw-semibold mb-0 mt-1">📒 Cahier de Notes — Sélection</h6>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-7 col-lg-9 col-md-12">

            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <iconify-icon icon="solar:notebook-bold-duotone"
                                      class="me-2 text-primary-600 text-xl align-middle"></iconify-icon>
                        Ouvrir un cahier de notes
                    </h6>
                </div>

                <div class="card-body p-24">
                    <form id="form-cahier">

                        {{-- Classe --}}
                        <div class="mb-20">
                            <label class="form-label fw-semibold">
                                Classe <span class="text-danger-600">*</span>
                            </label>
                            <select name="classe_annee_id" id="classe_annee_id"
                                    class="form-select" required>
                                <option value="">— Choisir une classe —</option>
                                @foreach($classesAnnees as $ca)
                                    <option value="{{ $ca->id }}">
                                        {{ $ca->classe->niveau->nom }} {{ $ca->classe->suffixe }}
                                        &nbsp;—&nbsp;{{ $ca->anneeScolaire->libelle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Période --}}
                        <div class="mb-20">
                            <label class="form-label fw-semibold">Période</label>
                            <div class="d-flex flex-wrap gap-12">
                                @foreach($periodes as $p)
                                    <label class="d-flex align-items-center gap-8 cursor-pointer
                                                  border radius-8 px-16 py-10 hover-bg-primary-50
                                                  periode-btn"
                                           style="border-color:#E2E8F0; transition:.15s">
                                        <input type="radio" name="periode_id"
                                               value="{{ $p->id }}"
                                               class="form-check-input mt-0"
                                               {{ $loop->first ? 'checked' : '' }}>
                                        <span class="fw-medium text-secondary-light">
                                            {{ $p->nom }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <hr class="my-20">
                        <p class="text-xs fw-semibold text-uppercase text-secondary-light
                                  mb-16 letter-spacing-1">
                            Options
                        </p>

                        <div class="row gy-3 mb-24">

                            {{-- Mode calcul --}}
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Calcul de la moyenne</label>
                                <div class="d-flex flex-column gap-8">
                                    <label class="d-flex align-items-center gap-8 cursor-pointer">
                                        <input type="radio" name="mode_moyenne" value="ponderee"
                                               class="form-check-input mt-0" checked>
                                        <span class="text-secondary-light">
                                            Pondérée
                                            <small class="text-muted">(avec coefficients)</small>
                                        </span>
                                    </label>
                                    <label class="d-flex align-items-center gap-8 cursor-pointer">
                                        <input type="radio" name="mode_moyenne" value="simple"
                                               class="form-check-input mt-0">
                                        <span class="text-secondary-light">
                                            Simple
                                            <small class="text-muted">(sans coefficients)</small>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            {{-- Rang --}}
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Classement</label>
                                <div class="d-flex flex-column gap-8">
                                    <label class="d-flex align-items-center gap-8 cursor-pointer">
                                        <input type="radio" name="afficher_rang" value="1"
                                               class="form-check-input mt-0" checked>
                                        <span class="text-secondary-light">Afficher le rang</span>
                                    </label>
                                    <label class="d-flex align-items-center gap-8 cursor-pointer">
                                        <input type="radio" name="afficher_rang" value="0"
                                               class="form-check-input mt-0">
                                        <span class="text-secondary-light">Masquer le rang</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Bouton --}}
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-24 py-10 fw-semibold">
                                <iconify-icon icon="solar:eye-bold"
                                              class="me-2 text-xl align-middle"></iconify-icon>
                                Ouvrir le cahier
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    // Style visuel sur la période sélectionnée
    document.querySelectorAll('.periode-btn input').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.periode-btn').forEach(function (lbl) {
                lbl.style.borderColor = '#E2E8F0';
                lbl.style.background  = '';
            });
            if (this.checked) {
                this.closest('.periode-btn').style.borderColor = '#3B82F6';
                this.closest('.periode-btn').style.background  = '#EFF6FF';
            }
        });
        // Init
        if (radio.checked) {
            radio.closest('.periode-btn').style.borderColor = '#3B82F6';
            radio.closest('.periode-btn').style.background  = '#EFF6FF';
        }
    });

    document.getElementById('form-cahier').addEventListener('submit', function (e) {
        e.preventDefault();
        var classeId = document.getElementById('classe_annee_id').value;
        if (!classeId) { alert('Veuillez choisir une classe.'); return; }

        var params = new URLSearchParams();

        var periode = document.querySelector('input[name="periode_id"]:checked');
        if (periode) params.set('periode_id', periode.value);

        var mode = document.querySelector('input[name="mode_moyenne"]:checked');
        if (mode) params.set('mode_moyenne', mode.value);

        var rang = document.querySelector('input[name="afficher_rang"]:checked');
        if (rang) params.set('afficher_rang', rang.value);

        window.location.href = '/admin/cahier-notes/classe/' + classeId + '?' + params.toString();
    });
})();
</script>
@endsection