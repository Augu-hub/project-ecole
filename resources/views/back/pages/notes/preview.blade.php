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

    {{-- Notes importées (Excel ou photo) : affichées, pas encore enregistrées --}}
    @if($notesNonEnregistrees)
        <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
            <i class="ri-download-2-line fs-5 flex-shrink-0"></i>
            <div>
                <strong>Notes importées</strong> — elles ne sont pas encore enregistrées.
                Vérifiez-les, corrigez si besoin, puis cliquez sur <strong>{{ $modeModification ? 'Mettre à jour les notes' : 'Enregistrer les notes' }}</strong>.
            </div>
        </div>
    {{-- ⚠️ BANDEAU MODE MODIFICATION --}}
    @elseif($modeModification)
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
                    &nbsp;|&nbsp; <strong>Type :</strong> {{ $typeNote->libelle }}
                    @if(($typeNote->max_par_periode ?? 1) > 1)
                        &nbsp;|&nbsp; <strong>N° :</strong> {{ $numero ?? 1 }} / {{ $typeNote->max_par_periode }}
                    @endif
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
        <a href="{{ route('admin.notes.export-pdf') }}" class="btn btn-danger btn-sm ms-auto js-export-pdf" target="_blank">
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
                        <h5 class="modal-title" style="font-size:1.125rem !important;">
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
                        {{-- Le thème impose une grande taille aux titres : celle-ci est ramenée à celle d'une fenêtre --}}
                        <h5 class="modal-title text-white" style="font-size:1.125rem !important;">
                            <i class="ri-sparkling-2-line me-2"></i>Lecture automatique des photos par IA
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info d-flex gap-2 align-items-start">
                            <i class="ri-information-line fs-5 mt-1 flex-shrink-0"></i>
                            <div>
                                <strong>Comment ça fonctionne ?</strong><br>
                                Photographiez chaque page de votre fiche papier — l'IA lira les notes automatiquement
                                et les associera aux élèves de cette classe.
                                Posez la fiche à plat, bien éclairée, avec tout le tableau dans la photo.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="image_files" class="form-label fw-semibold">
                                <i class="ri-image-line me-1"></i>Photos de la fiche
                            </label>
                            <input type="file"
                                   class="form-control @error('image_files') is-invalid @enderror @error('image_files.*') is-invalid @enderror"
                                   id="image_files" name="image_files[]" multiple
                                   accept="image/jpeg,image/png,image/webp" required>
                            @error('image_files')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('image_files.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted">Une photo par page, 5 au maximum.</div>
                            <div id="photosMessage" class="form-text text-danger d-none" role="alert"></div>
                        </div>
                        <div id="photosApercu" class="d-none mb-3">
                            <div id="photosCompte" class="small fw-semibold mb-2"></div>
                            <div id="photosListe" class="photos-fiche"></div>
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
            <form action="{{ route('admin.notes.store') }}" method="POST" id="formNotes"
                  data-non-enregistre="{{ $notesNonEnregistrees ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="classe_annee_id" value="{{ $classeAnnee->id }}">
                <input type="hidden" name="matiere_id"      value="{{ $matiere->id }}">
                <input type="hidden" name="periode_id"      value="{{ $periode->id }}">
                <input type="hidden" name="type_note_id"    value="{{ $typeNote->id }}">
                <input type="hidden" name="numero"          value="{{ $numero ?? 1 }}">

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
                    <a href="{{ route('admin.notes.export-pdf') }}" class="btn btn-danger js-export-pdf" target="_blank">
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

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/notes-preview.css') }}">
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Photos de la fiche : ajoutées une à une, réduites avant l'envoi ─
    installerPhotosFiche();

    // ── Fiche PDF : elle ne contient que les notes déjà enregistrées ─
    const formNotes = document.getElementById('formNotes');
    if (formNotes) {
        let nonEnregistre = formNotes.dataset.nonEnregistre === '1';
        formNotes.addEventListener('input', () => { nonEnregistre = true; });
        formNotes.addEventListener('submit', () => { nonEnregistre = false; });

        document.querySelectorAll('.js-export-pdf').forEach(function (lien) {
            lien.addEventListener('click', function (e) {
                if (nonEnregistre && !confirm("Certaines notes ne sont pas encore enregistrées : elles n'apparaîtront pas sur la fiche PDF.\n\nCliquez sur Annuler puis sur « Enregistrer les notes », ou sur OK pour exporter quand même.")) {
                    e.preventDefault();
                }
            });
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

    // ── Photos de la fiche ───────────────────────────────────────────
    // Chaque sélection s'ajoute à la liste (sur un téléphone, on prend une photo, puis une autre),
    // 5 photos au maximum. Avant d'être ajoutée, chaque photo est réduite dans le navigateur :
    // une photo de téléphone pèse plusieurs Mo, bien plus que ce que le serveur accepte.
    function installerPhotosFiche() {
        const MAX_PHOTOS = 5;
        const COTE_MAX = 2000;     // plus grand côté d'une photo réduite, en pixels
        const QUALITE_JPEG = 0.82;
        // Photo d'essai (JPEG de 2 × 1 points, orientation EXIF 6) : voir lecturesFiables()
        const PHOTO_ESSAI = '/9j/4QAiRXhpZgAASUkqAAgAAAABABIBAwABAAAABgAAAAAAAAD/4AAQSkZJRgABAQEAYABgAAD/2wBDABsSFBcUERsXFhceHBsgKEIrKCUlKFE6PTBCYFVlZF9VXVtqeJmBanGQc1tdhbWGkJ6jq62rZ4C8ybqmx5moq6T/2wBDARweHigjKE4rK06kbl1upKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKSkpKT/wAARCAABAAIDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDc0v8A5Bdp/wBcU/8AQRRRRQB//9k=';

        const champ = document.getElementById('image_files');
        const apercu = document.getElementById('photosApercu');
        const liste = document.getElementById('photosListe');
        const compte = document.getElementById('photosCompte');
        const message = document.getElementById('photosMessage');
        const bouton = document.getElementById('btnAnalyse');
        const formulaire = document.getElementById('formImportImage');
        if (!champ || !apercu || !liste || !compte || !message) return;

        // Photos déjà dans le champ à l'ouverture de la page (certains navigateurs les remettent après un retour)
        const dejaChoisies = Array.prototype.slice.call(champ.files || []);

        // Sans ces fonctions du navigateur, le champ garde son fonctionnement ordinaire :
        // sélection multiple simple, sans vignettes ni réduction.
        if (!listeRemplacable()) return;

        let lot = new DataTransfer();  // photos retenues, dans l'ordre : c'est cette liste que le formulaire envoie
        let origines = [];             // empreinte du fichier d'origine de chaque photo retenue (contre les doublons)
        let adresses = [];             // adresses temporaires des vignettes affichées
        let numeroFichier = 0;         // numérote les fichiers réduits : photo-1.jpg, photo-2.jpg…
        let preparations = 0;          // sélections en cours de préparation
        let envoye = false;            // le formulaire est parti : le bouton d'analyse n'est plus réactivé
        let suite = Promise.resolve(); // les sélections sont traitées l'une après l'autre
        let fiables = null;            // façons de lire une image qui la rendent à l'endroit (vérifié au premier usage)

        champ.addEventListener('change', function () {
            const choisies = Array.prototype.slice.call(champ.files || []);
            synchroniser(); // le champ retrouve aussitôt les photos déjà retenues
            if (choisies.length) preparer(choisies);
        });

        if (formulaire) {
            formulaire.addEventListener('submit', function () {
                synchroniser();
                envoye = true;
            });
        }

        if (dejaChoisies.length) preparer(dejaChoisies);

        function listeRemplacable() {
            try {
                const essai = new DataTransfer();
                essai.items.add(new File(['x'], 'essai.txt', { type: 'text/plain' }));
                champ.files = essai.files;
                const remplacee = champ.files.length === 1 && champ.files[0].name === 'essai.txt';
                if (remplacee) champ.value = '';
                return remplacee;
            } catch (e) {
                return false;
            }
        }

        // Le champ envoie toujours la liste complète, pas seulement la dernière sélection
        function synchroniser() {
            try {
                champ.files = lot.files;
            } catch (e) {
                // le champ garde sa propre sélection
            }
        }

        function empreinte(fichier) {
            return fichier.name + '|' + fichier.size + '|' + fichier.lastModified;
        }

        function signaler(texte) {
            message.textContent = texte;
            message.classList.toggle('d-none', texte === '');
        }

        // Le bouton d'analyse attend la fin de la préparation : aucune photo ne part à moitié prête
        function preparer(fichiers) {
            preparations += 1;
            if (bouton) bouton.disabled = true;

            suite = suite
                .then(function () { return ajouter(fichiers); })
                .catch(function () { /* une photo en erreur ne bloque jamais les suivantes */ })
                .then(function () {
                    preparations -= 1;
                    if (bouton && preparations === 0 && !envoye) bouton.disabled = false;
                });
        }

        function ajouter(fichiers) {
            let enTrop = false;

            return fichiers.reduce(function (file, fichier) {
                return file.then(function () {
                    if (origines.indexOf(empreinte(fichier)) !== -1) return null; // déjà dans la liste
                    if (origines.length >= MAX_PHOTOS) {
                        enTrop = true;
                        return null;
                    }

                    return reduire(fichier)
                        .catch(function () { return fichier; }) // réduction impossible : la photo d'origine est gardée
                        .then(function (photo) { retenir(photo, fichier); });
                });
            }, Promise.resolve()).then(function () {
                signaler(enTrop ? MAX_PHOTOS + " photos au maximum : les suivantes n'ont pas été ajoutées." : '');
            });
        }

        function retenir(photo, origine) {
            try {
                lot.items.add(photo);
            } catch (e) {
                lot.items.add(origine);
            }
            origines.push(empreinte(origine));
            synchroniser();
            afficher();
        }

        function retirer(position) {
            const reste = new DataTransfer();
            Array.prototype.slice.call(lot.files).forEach(function (photo, i) {
                if (i !== position) reste.items.add(photo);
            });
            lot = reste;
            origines.splice(position, 1);
            signaler('');
            synchroniser();
            afficher();
        }

        // Une vignette par photo, avec son numéro et un bouton pour la retirer, puis le nombre de photos
        function afficher() {
            adresses.forEach(function (adresse) { URL.revokeObjectURL(adresse); });
            adresses = [];
            liste.textContent = '';

            const photos = Array.prototype.slice.call(lot.files);
            photos.forEach(function (photo, position) {
                const adresse = URL.createObjectURL(photo);
                adresses.push(adresse);

                const vignette = document.createElement('div');
                vignette.className = 'photos-fiche__photo';

                const image = document.createElement('img');
                image.src = adresse;
                image.alt = 'Photo ' + (position + 1);

                const numero = document.createElement('span');
                numero.className = 'photos-fiche__numero';
                numero.textContent = position + 1;

                const retrait = document.createElement('button');
                retrait.type = 'button';
                retrait.className = 'photos-fiche__retirer';
                retrait.title = 'Retirer cette photo';
                retrait.setAttribute('aria-label', 'Retirer la photo ' + (position + 1));
                retrait.textContent = '×';
                retrait.addEventListener('click', function () { retirer(position); });

                vignette.appendChild(image);
                vignette.appendChild(numero);
                vignette.appendChild(retrait);
                liste.appendChild(vignette);
            });

            compte.textContent = photos.length + (photos.length > 1 ? ' photos' : ' photo');
            apercu.classList.toggle('d-none', photos.length === 0);
        }

        // Photo réduite : plus grand côté de 2000 px au plus, en JPEG, remise à l'endroit
        function reduire(fichier) {
            return decoder(fichier).then(function (image) {
                const largeur = image.naturalWidth || image.width;
                const hauteur = image.naturalHeight || image.height;
                if (!largeur || !hauteur) throw new Error('image vide');

                const echelle = Math.min(1, COTE_MAX / Math.max(largeur, hauteur));
                const toile = document.createElement('canvas');
                toile.width = Math.max(1, Math.round(largeur * echelle));
                toile.height = Math.max(1, Math.round(hauteur * echelle));

                const dessin = toile.getContext('2d');
                dessin.fillStyle = '#ffffff'; // fond blanc : une image transparente ne devient pas noire en JPEG
                dessin.fillRect(0, 0, toile.width, toile.height);
                dessin.drawImage(image, 0, 0, toile.width, toile.height);
                if (typeof image.close === 'function') image.close();

                return new Promise(function (resolve, reject) {
                    toile.toBlob(function (blob) {
                        if (blob && blob.type === 'image/jpeg') {
                            resolve(blob);
                        } else {
                            reject(new Error('conversion impossible'));
                        }
                    }, 'image/jpeg', QUALITE_JPEG);
                });
            }).then(function (blob) {
                numeroFichier += 1;

                return new File([blob], 'photo-' + numeroFichier + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
            });
        }

        // Lecture de l'image dans le sens où l'appareil a pris la photo (orientation EXIF) : une fois
        // redessinée, la photo ne porte plus cette indication, elle doit donc être lue à l'endroit.
        function decoder(fichier) {
            return lecturesFiables().then(function (lectures) {
                // Chaque lecture fiable est essayée à son tour ; s'il n'y en a aucune, la photo d'origine est gardée
                return lectures.reduce(function (suite, lire) {
                    return suite.catch(function () { return lire(fichier); });
                }, Promise.reject(new Error('aucune lecture fiable')));
            });
        }

        // Tous les navigateurs ne tiennent pas compte du sens de prise de vue. On le vérifie une fois, sur une
        // photo d'essai de 2 × 1 points à tourner d'un quart de tour : une façon de lire les images n'est
        // gardée que si elle la rend en 1 × 2.
        function lecturesFiables() {
            if (!fiables) {
                fiables = new Promise(function (resolve) {
                    const octets = atob(PHOTO_ESSAI);
                    const tableau = new Uint8Array(octets.length);
                    for (let i = 0; i < octets.length; i++) tableau[i] = octets.charCodeAt(i);
                    const essai = new Blob([tableau], { type: 'image/jpeg' });
                    const lectures = typeof window.createImageBitmap === 'function' ? [lireEnBitmap, lireParBalise] : [lireParBalise];

                    resolve(Promise.all(lectures.map(function (lire) {
                        return lire(essai).then(function (image) {
                            const debout = (image.naturalWidth || image.width) === 1 && (image.naturalHeight || image.height) === 2;
                            if (typeof image.close === 'function') image.close();

                            return debout ? lire : null;
                        }).catch(function () { return null; });
                    })));
                }).then(function (lectures) {
                    return lectures.filter(function (lire) { return lire !== null; });
                }).catch(function () { return []; });
            }

            return fiables;
        }

        function lireEnBitmap(fichier) {
            return new Promise(function (resolve) {
                resolve(window.createImageBitmap(fichier, { imageOrientation: 'from-image' }));
            });
        }

        function lireParBalise(fichier) {
            return new Promise(function (resolve, reject) {
                const adresse = URL.createObjectURL(fichier);
                const image = new Image();
                image.onload = function () {
                    URL.revokeObjectURL(adresse);
                    resolve(image);
                };
                image.onerror = function () {
                    URL.revokeObjectURL(adresse);
                    reject(new Error('image illisible'));
                };
                image.src = adresse;
            });
        }
    }
});
</script>
@endpush