@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">

    {{-- Breadcrumb --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Saisie des notes</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ <a href="{{ route('admin.notes.create') }}">Notes</a></span>
                <span class="text-secondary-light">/ Saisie</span>
            </div>
        </div>
    </div>

    {{-- Messages --}}
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ⚠️ BANDEAU MODE MODIFICATION --}}
    @if($modeModification)
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="ri-edit-line fs-5 flex-shrink-0"></i>
            <div>
                <strong>Mode modification</strong> —
                Des notes existent déjà pour cette combinaison dans la base de données.
                Les champs sont pré-remplis avec les valeurs actuelles.
                Modifiez et cliquez sur <strong>Mettre à jour les notes</strong>.
            </div>
        </div>
    @endif

    {{-- Bandeau info classe --}}
    <div class="alert alert-info mb-3 py-2">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ asset('assets/images/LOGOCCPA.jpeg') }}" alt="CCPA"
                 style="height:36px; border-radius:4px; object-fit:contain;">
            <div class="row w-100">
                <div class="col-md-6 small">
                    <strong>Classe :</strong> {{ $classeAnnee->classe->niveau->nom }} {{ $classeAnnee->classe->suffixe }}<br>
                    <strong>Année scolaire :</strong> {{ $classeAnnee->anneeScolaire->libelle }}
                </div>
                <div class="col-md-6 small">
                    <strong>Matière :</strong> {{ $matiere->nom_matiere }}<br>
                    <strong>Période :</strong> {{ $periode->nom }}
                    &nbsp;|&nbsp; <strong>Type :</strong> {{ $typeNote->nom }}
                </div>
            </div>
        </div>
    </div>

    {{-- BOUTONS ACTIONS --}}
    <div class="d-flex flex-wrap gap-2 mb-3">

        {{-- Fiche vierge PDF --}}
        <a href="{{ route('admin.notes.export-template') }}" class="btn btn-outline-primary btn-sm">
            <i class="ri-file-pdf-line me-1"></i>Fiche vierge PDF
        </a>

        {{-- Template Excel pour import --}}
        <a href="{{ route('admin.notes.export-template-excel') }}" class="btn btn-outline-success btn-sm">
            <i class="ri-file-excel-2-line me-1"></i>Template Excel
        </a>

        {{-- Importer Excel --}}
        <button type="button" class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="ri-upload-line me-1"></i>Importer Excel
        </button>

        {{-- Photo IA --}}
        <button type="button" class="btn btn-outline-info btn-sm"
                data-bs-toggle="modal" data-bs-target="#importImageModal">
            <i class="ri-camera-line me-1"></i>Photo → IA
            <i class="ri-sparkling-2-line ms-1" aria-hidden="true"></i>
        </button>

        {{-- Export PDF officiel (à droite) --}}
        <a href="{{ route('admin.notes.export-pdf') }}" class="btn btn-danger btn-sm ms-auto" target="_blank">
            <i class="ri-file-pdf-line me-1"></i>Exporter PDF officiel CCPA
        </a>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         MODAL IMPORT EXCEL
    ══════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.notes.import-preview') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="ri-upload-line me-1"></i>Importer un fichier Excel
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small">
                            <i class="ri-information-line me-1"></i>
                            Utilisez le <strong>Template Excel</strong> téléchargeable ci-dessus pour garantir
                            un import sans erreur. Le fichier doit contenir les colonnes :
                            <strong>Matricule, Nom, Prénom, Note, Commentaire</strong>.
                        </div>
                        <div class="mb-3">
                            <label for="import_file" class="form-label">Fichier Excel (.xlsx)</label>
                            <input type="file"
                                   class="form-control @error('import_file') is-invalid @enderror"
                                   id="import_file" name="import_file"
                                   accept=".xlsx,.xls" required>
                            @error('import_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-upload-line me-1"></i>Importer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         MODAL IMPORT IMAGE IA
    ══════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="importImageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('admin.notes.import-image') }}" method="POST"
                      enctype="multipart/form-data" id="formImportImage">
                    @csrf
                    <div class="modal-header" style="background:#003366;">
                        <h5 class="modal-title text-white">
                            <i class="ri-sparkling-2-line me-2"></i>Lecture automatique d'une photo par IA
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info d-flex gap-2 align-items-start">
                            <i class="ri-information-line fs-5 mt-1 flex-shrink-0"></i>
                            <div>
                                <strong>Comment ça fonctionne ?</strong><br>
                                Photographiez votre fiche papier — l'IA lira les notes automatiquement
                                et les associera aux élèves de cette classe.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="image_file" class="form-label fw-semibold">
                                <i class="ri-image-line me-1"></i>Photo de la fiche
                            </label>
                            <input type="file"
                                   class="form-control @error('image_file') is-invalid @enderror"
                                   id="image_file" name="image_file"
                                   accept=".jpg,.jpeg,.png,.webp" required>
                            @error('image_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted">JPG, PNG, WEBP — max 10 Mo</div>
                        </div>
                        <div id="imgPreviewBox" class="d-none text-center mb-3">
                            <img id="imgPreview" src="" alt="Aperçu"
                                 style="max-height:260px; border:2px solid #003366; border-radius:8px;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="btnAnalyse">
                            <i class="ri-sparkling-2-line me-1"></i>Analyser avec l'IA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         FORMULAIRE SAISIE DES NOTES
    ══════════════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <h6 class="mb-0 fw-semibold">
                <i class="ri-table-line me-1"></i>
                @if($modeModification)
                    Modification des notes
                    <span class="badge bg-warning text-dark ms-2">
                        <i class="ri-edit-line me-1"></i>{{ collect($importedNotes)->count() }} note(s) existante(s)
                    </span>
                @else
                    Nouvelle saisie
                    <span class="badge bg-primary ms-2">Vierge</span>
                @endif
            </h6>
            <span class="text-muted small">{{ $eleves->count() }} élève(s)</span>
        </div>

        <div class="card-body p-0">
            <form action="{{ route('admin.notes.store') }}" method="POST">
                @csrf
                <input type="hidden" name="classe_annee_id" value="{{ $classeAnnee->id }}">
                <input type="hidden" name="matiere_id"      value="{{ $matiere->id }}">
                <input type="hidden" name="periode_id"      value="{{ $periode->id }}">
                <input type="hidden" name="type_note_id"    value="{{ $typeNote->id }}">

                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0">
                        <thead style="background:#003366; color:#fff;">
                            <tr>
                                <th class="text-center" style="width:45px">#</th>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th class="text-center" style="width:140px">Note /20</th>
                                <th>Commentaire</th>
                                <th class="text-center" style="width:100px">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($eleves as $index => $eleve)
                                @php
                                    $noteActuelle = $importedNotes[$eleve->id] ?? null;
                                    $aUneNote     = $noteActuelle !== null
                                                    && $noteActuelle['valeur'] !== null
                                                    && $noteActuelle['valeur'] !== '';
                                @endphp
                                <tr>
                                    <td class="text-center text-muted small">{{ $index + 1 }}</td>
                                    <td><code class="small">{{ $eleve->matricule }}</code></td>
                                    <td class="fw-semibold">{{ $eleve->nom }}</td>
                                    <td>{{ $eleve->prenom }}</td>
                                    <td class="text-center">
                                        <input type="number"
                                               step="0.01" min="0" max="20"
                                               name="notes[{{ $eleve->id }}][valeur]"
                                               class="form-control form-control-sm text-center
                                                      {{ $aUneNote ? 'border-warning fw-bold' : '' }}"
                                               value="{{ old('notes.'.$eleve->id.'.valeur', $noteActuelle['valeur'] ?? '') }}"
                                               placeholder="0 – 20">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="notes[{{ $eleve->id }}][commentaire]"
                                               class="form-control form-control-sm"
                                               value="{{ old('notes.'.$eleve->id.'.commentaire', $noteActuelle['commentaire'] ?? '') }}"
                                               placeholder="Optionnel">
                                    </td>
                                    <td class="text-center">
                                        @if($aUneNote)
                                            <span class="badge bg-warning text-dark">
                                                <i class="ri-edit-line"></i> Modifier
                                            </span>
                                        @else
                                            <span class="badge bg-light text-secondary border">Nouveau</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-user-search-line fs-3 d-block mb-2"></i>
                                        Aucun élève trouvé dans cette classe.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 p-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i>
                        {{ $modeModification ? 'Mettre à jour les notes' : 'Enregistrer les notes' }}
                    </button>
                    <a href="{{ route('admin.notes.export-pdf') }}" class="btn btn-danger" target="_blank">
                        <i class="ri-file-pdf-line me-1"></i>Exporter PDF
                    </a>
                    <a href="{{ route('admin.notes.create') }}" class="btn btn-secondary ms-auto">
                        <i class="ri-arrow-left-line me-1"></i>Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Aperçu image avant envoi IA ──────────────────────────────────
    const fileInput = document.getElementById('image_file');
    if (fileInput) {
        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = ev => {
                document.getElementById('imgPreview').src = ev.target.result;
                document.getElementById('imgPreviewBox').classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });
    }

    // ── Spinner pendant l'analyse IA ─────────────────────────────────
    const form = document.getElementById('formImportImage');
    if (form) {
        form.addEventListener('submit', function () {
            const btn = document.getElementById('btnAnalyse');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Analyse en cours…';
            }
        });
    }
});
</script>
@endpush