@extends('back.layouts.master')

@section('title', 'Saisie des notes')

@section('content')
@php
    // Droits du compte connecté : même règle que le menu (middleware « role: » des routes)
    $peut = fn (string $route) => \App\Support\AccesMenu::autorise($route);
    $peutGererMatieres = $peut('admin.classe-matieres.index');
    $peutReprendre = $peut('admin.classe-matieres.reprise-annee');

    $libelleAnnee = $anneeActive?->libelle;
    $tableauDeBord = auth()->user()->role === 'enseignant' ? route('enseignant.dashboard') : route('admin.dashboard');

    // Choix déjà fait, conservé quand on revient sur la page après une erreur
    $ancien = fn (string $champ, $defaut = '') => is_scalar(old($champ, $defaut)) ? (string) old($champ, $defaut) : (string) $defaut;
    $ancienneClasse = $ancien('classe_annee_id');
    $ancienType = $types->firstWhere('id', (int) $ancien('type_note_id'));
    $numeroMaximum = max(1, (int) ($ancienType?->max_par_periode ?? 1));

    // Sans le script de la page, la liste des matières reste utilisable :
    // celles de la classe déjà choisie, sinon toutes les matières rattachées à une classe
    $matieresProposees = collect($matieresParClasse[$ancienneClasse] ?? $matieres);

    // Ce qu'il manque pour pouvoir saisir : quoi, pourquoi, et où le régler
    $manques = [];

    if (! $anneeActive) {
        $manques[] = [
            'icone' => 'ri-calendar-close-line',
            'titre' => "Aucune année scolaire n'est active",
            'texte' => "Les notes se saisissent pour l'année scolaire en cours : il faut d'abord en activer une.",
            'route' => 'admin.annees-scolaires.index',
            'action' => 'Gérer les années scolaires',
        ];
    } elseif ($ficheEnseignantManquante) {
        $manques[] = [
            'icone' => 'ri-user-unfollow-line',
            'titre' => "Votre compte n'est relié à aucune fiche enseignant",
            'texte' => "Demandez à l'administration de relier votre compte à votre fiche enseignant : vos classes et vos matières apparaîtront ici.",
            'route' => null,
            'action' => null,
        ];
    } elseif ($classesAnnees->isEmpty() && $estEnseignant) {
        $manques[] = [
            'icone' => 'ri-group-line',
            'titre' => "Aucune classe ne vous est attribuée pour l'année scolaire {$libelleAnnee}",
            'texte' => "Vous ne voyez ici que les classes et les matières qui vous sont attribuées. Adressez-vous au censeur ou à la direction pour qu'elles le soient.",
            'route' => null,
            'action' => null,
        ];
    } elseif ($classesAnnees->isEmpty()) {
        $manques[] = [
            'icone' => 'ri-group-line',
            'titre' => "Aucune classe n'est ouverte pour l'année scolaire {$libelleAnnee}",
            'texte' => "Les notes se saisissent classe par classe : il faut d'abord ouvrir les classes de cette année scolaire.",
            'route' => 'admin.classe-annees.index',
            'action' => "Ouvrir les classes de l'année",
        ];
    }

    if ($anneeActive && $periodes->isEmpty()) {
        $manques[] = [
            'icone' => 'ri-time-line',
            'titre' => "Aucune période n'est créée pour l'année scolaire {$libelleAnnee}",
            'texte' => 'Chaque note est rattachée à une période (trimestre ou semestre).',
            'route' => 'admin.periodes.index',
            'action' => 'Gérer les périodes',
        ];
    }

    if ($types->isEmpty()) {
        $manques[] = [
            'icone' => 'ri-price-tag-3-line',
            'titre' => "Aucun type de note n'est défini",
            'texte' => 'Il en faut au moins un : interrogation, devoir, composition…',
            'route' => 'admin.type-notes.index',
            'action' => 'Gérer les types de notes',
        ];
    }

    $reglagesHorsDePortee = collect($manques)->contains(fn (array $manque) => $manque['route'] && ! $peut($manque['route']));

    // Classes sans matière : on ne peut rien y saisir. Si aucune classe n'a de matière, le formulaire est inutilisable.
    $toutesSansMatiere = $classesAnnees->isNotEmpty() && $classesSansMatiere->count() === $classesAnnees->count();
    $saisiePossible = ! $manques && ! $toutesSansMatiere;

    // Données embarquées pour lier les listes sans appel réseau : les matières de chaque classe
    $donneesPage = [
        'classes' => (object) collect($matieresParClasse)->map(fn (array $matieresDeLaClasse, $idClasse) => [
            'matieres' => $matieresDeLaClasse,
            'lien' => $peutGererMatieres ? route('admin.classe-matieres.index', $idClasse) : null,
        ])->all(),
    ];
@endphp
<div class="dashboard-main-body">

    {{-- En-tête --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Saisie des notes</h1>
            <div>
                <a href="{{ $tableauDeBord }}" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Notes / Saisie</span>
            </div>
        </div>
        @if($anneeActive)
            <span class="badge bg-success text-md px-16 py-9">Année scolaire {{ $libelleAnnee }}</span>
        @endif
    </div>

    {{-- Ce qu'il reste à mettre en place avant de pouvoir saisir --}}
    @if($manques)
        <div class="card mb-24">
            <div class="card-body p-24">
                <div class="d-flex align-items-center gap-12 mb-8">
                    <span class="w-40-px h-40-px rounded-circle bg-warning-focus text-warning-main d-flex justify-content-center align-items-center text-xl flex-shrink-0">
                        <i class="ri-alert-line" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h6 class="text-lg fw-semibold mb-0">La saisie des notes n'est pas encore possible</h6>
                        <p class="text-secondary-light text-sm mb-0">Voici ce qu'il reste à mettre en place.</p>
                    </div>
                </div>

                @foreach($manques as $manque)
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-12 py-12 {{ $loop->first ? '' : 'border-top' }}">
                        <div class="d-flex align-items-start gap-12">
                            <i class="{{ $manque['icone'] }} text-xl text-warning-main" aria-hidden="true"></i>
                            <div>
                                <div class="fw-semibold text-primary-light">{{ $manque['titre'] }}</div>
                                <div class="text-secondary-light text-sm">{{ $manque['texte'] }}</div>
                            </div>
                        </div>
                        @if($manque['route'] && $peut($manque['route']))
                            <a href="{{ route($manque['route']) }}" class="btn btn-sm btn-outline-primary flex-shrink-0">
                                {{ $manque['action'] }} <i class="ri-arrow-right-line ms-1" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                @endforeach

                @if($reglagesHorsDePortee)
                    <p class="text-secondary-light text-sm border-top pt-12 mb-0">
                        Ces réglages sont faits par l'administration : adressez-vous au censeur ou à la direction.
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Classes de l'année sans matière --}}
    @if($classesSansMatiere->isNotEmpty())
        <div class="card mb-24">
            <div class="card-body p-24">
                <div class="d-flex align-items-start gap-12">
                    <span class="w-40-px h-40-px rounded-circle bg-warning-focus text-warning-main d-flex justify-content-center align-items-center text-xl flex-shrink-0">
                        <i class="ri-book-open-line" aria-hidden="true"></i>
                    </span>
                    <div class="flex-grow-1">
                        <h6 class="text-lg fw-semibold mb-4">
                            @if($toutesSansMatiere)
                                Aucune classe n'a encore de matières pour l'année scolaire {{ $libelleAnnee }}
                            @elseif($classesSansMatiere->count() === 1)
                                1 classe n'a pas encore de matières
                            @else
                                {{ $classesSansMatiere->count() }} classes n'ont pas encore de matières
                            @endif
                        </h6>
                        <p class="text-secondary-light mb-12">
                            Tant qu'une classe n'a pas de matières, on ne peut pas y saisir de notes.
                            @if($peutGererMatieres)
                                Cliquez sur une classe pour lui rattacher ses matières.
                            @else
                                Adressez-vous au censeur ou à la direction : ce sont eux qui rattachent les matières aux classes.
                            @endif
                        </p>

                        <div class="d-flex flex-wrap gap-8">
                            @foreach($classesSansMatiere as $classeAnnee)
                                @if($peutGererMatieres)
                                    <a href="{{ route('admin.classe-matieres.index', $classeAnnee) }}"
                                       class="border radius-8 px-12 py-6 text-sm fw-medium text-primary-600 bg-primary-50"
                                       title="Rattacher les matières de cette classe">{{ $classeAnnee->classe->full_name }}</a>
                                @else
                                    <span class="border radius-8 px-12 py-6 text-sm fw-medium text-secondary-light">{{ $classeAnnee->classe->full_name }}</span>
                                @endif
                            @endforeach
                        </div>

                        @if($reprise->isNotEmpty())
                            <div class="d-flex flex-wrap align-items-center gap-12 border-top mt-16 pt-16">
                                <button type="button" class="btn btn-primary flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modal-reprise-matieres">
                                    <i class="ri-history-line me-1" aria-hidden="true"></i> Reprendre les matières de l'année précédente
                                </button>
                                <span class="text-secondary-light text-sm">
                                    @if($reprise->count() === 1)
                                        1 classe retrouvera les matières, les coefficients et les enseignants qu'elle avait.
                                    @else
                                        {{ $reprise->count() }} classes retrouveront les matières, les coefficients et les enseignants qu'elles avaient.
                                    @endif
                                    @if($reprise->count() < $classesSansMatiere->count())
                                        Les autres n'en avaient pas les années précédentes : il faudra les rattacher à la main.
                                    @endif
                                </span>
                            </div>
                        @elseif($peutReprendre)
                            <p class="text-secondary-light text-sm border-top mt-16 pt-16 mb-0">
                                Ces classes n'avaient pas non plus de matières les années précédentes : il n'y a rien à reprendre, il faut les rattacher à la main.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Formulaire : une ligne de choix et un bouton --}}
    <div class="card">
        <div class="card-body p-24">
            <form action="{{ route('admin.notes.preview') }}" method="POST" id="formulaire-saisie">
                @csrf
                {{-- Tant qu'il manque quelque chose, le formulaire reste visible mais inactif --}}
                <fieldset @disabled(! $saisiePossible)>
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label for="classe_annee_id" class="form-label">Classe</label>
                            <select name="classe_annee_id" id="classe_annee_id"
                                    class="form-select @error('classe_annee_id') is-invalid @enderror" required>
                                <option value="">{{ $classesAnnees->isEmpty() ? 'Aucune classe disponible' : '-- Choisir --' }}</option>
                                @foreach($classesAnnees as $classeAnnee)
                                    <option value="{{ $classeAnnee->id }}" @selected($ancienneClasse === (string) $classeAnnee->id)>
                                        {{ $classeAnnee->classe->full_name }}{{ $matieresParClasse[$classeAnnee->id] === [] ? ' (sans matière)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('classe_annee_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label for="matiere_id" class="form-label">Matière</label>
                            <select name="matiere_id" id="matiere_id" aria-describedby="aide-matiere"
                                    class="form-select @error('matiere_id') is-invalid @enderror" required>
                                <option value="">{{ $matieresProposees->isEmpty() ? 'Aucune matière disponible' : '-- Choisir --' }}</option>
                                @foreach($matieresProposees as $matiere)
                                    <option value="{{ $matiere['id'] }}" @selected($ancien('matiere_id') === (string) $matiere['id'])>{{ $matiere['nom'] }}</option>
                                @endforeach
                            </select>
                            @error('matiere_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            {{-- Rempli par le script seulement quand la classe choisie n'a pas de matière --}}
                            <div class="form-text" id="aide-matiere" aria-live="polite"></div>
                        </div>

                        <div class="col-lg-2 col-md-4">
                            <label for="periode_id" class="form-label">Période</label>
                            <select name="periode_id" id="periode_id"
                                    class="form-select @error('periode_id') is-invalid @enderror" required>
                                <option value="">{{ $periodes->isEmpty() ? 'Aucune période disponible' : '-- Choisir --' }}</option>
                                @foreach($periodes as $periode)
                                    <option value="{{ $periode->id }}" @selected($ancien('periode_id') === (string) $periode->id)>{{ $periode->nom }}</option>
                                @endforeach
                            </select>
                            @error('periode_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-lg-2 col-md-4">
                            <label for="type_note_id" class="form-label">Type de note</label>
                            <select name="type_note_id" id="type_note_id"
                                    class="form-select @error('type_note_id') is-invalid @enderror" required>
                                <option value="">{{ $types->isEmpty() ? 'Aucun type de note disponible' : '-- Choisir --' }}</option>
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}" data-max="{{ max(1, (int) $type->max_par_periode) }}" @selected($ancienType?->id === $type->id)>{{ $type->libelle }}</option>
                                @endforeach
                            </select>
                            @error('type_note_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-lg-2 col-md-4">
                            <label for="numero" class="form-label">N°</label>
                            {{-- Autant de numéros que le type choisi en autorise par période (reconstruit par le script) --}}
                            <select name="numero" id="numero"
                                    class="form-select @error('numero') is-invalid @enderror">
                                @for($numero = 1; $numero <= $numeroMaximum; $numero++)
                                    <option value="{{ $numero }}" @selected($ancien('numero', 1) === (string) $numero)>N° {{ $numero }}</option>
                                @endfor
                            </select>
                            @error('numero')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @if($avertirSansEnseignant)
                        {{-- Aucun enseignant dans l'établissement : affiché par le script dès qu'une matière sans enseignant est choisie --}}
                        <div class="d-none mt-16" id="alerte-enseignant" role="status">
                            <div class="alert alert-warning d-flex align-items-start gap-2 text-sm mb-0">
                                <i class="ri-user-unfollow-line text-lg" aria-hidden="true"></i>
                                <div>
                                    Aucun enseignant n'est encore enregistré dans l'établissement.
                                    Vous pouvez préparer et imprimer la fiche, mais il en faudra au moins un pour enregistrer les notes.
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-24">
                        <button type="submit" class="btn btn-primary" id="bouton-preparer">Préparer la saisie</button>
                        @if($peut('admin.notes.index'))
                            <a href="{{ route('admin.notes.index') }}" class="btn btn-secondary">Annuler</a>
                        @endif
                    </div>
                </fieldset>
            </form>
        </div>
    </div>

    {{-- Confirmation de la reprise des matières de l'année précédente --}}
    @if($reprise->isNotEmpty())
        <div class="modal fade" id="modal-reprise-matieres" tabindex="-1" aria-labelledby="titre-reprise-matieres" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <form action="{{ route('admin.classe-matieres.reprise-annee') }}" method="POST" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="titre-reprise-matieres">
                            <i class="ri-history-line me-1" aria-hidden="true"></i>Reprendre les matières de l'année précédente
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-secondary-light">
                            Chaque classe ci-dessous retrouvera les matières qu'elle avait, avec les mêmes coefficients et les mêmes enseignants.
                            Vous pourrez ensuite les ajuster classe par classe.
                        </p>
                        <table class="table table-sm align-middle mb-12">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Matières reprises de</th>
                                    <th class="text-end">Matières</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reprise as $ligne)
                                    <tr>
                                        <td class="fw-medium">{{ $ligne['cible']->classe->full_name }}</td>
                                        <td>{{ $ligne['source']->anneeScolaire->libelle }}</td>
                                        <td class="text-end">{{ $ligne['matieres'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="text-secondary-light text-sm mb-0">
                            Les classes qui ont déjà des matières ne sont pas modifiées.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-history-line me-1" aria-hidden="true"></i>Reprendre les matières
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
@endsection

@push('scripts')
{{-- Matières de chaque classe, embarquées dans la page : les listes se lient sans appel réseau --}}
<script type="application/json" id="saisie-notes-donnees">@json($donneesPage)</script>
<script id="saisie-notes-script">
(function () {
    var formulaire = document.getElementById('formulaire-saisie');
    var source = document.getElementById('saisie-notes-donnees');
    if (!formulaire || !source) {
        return;
    }

    var classes = JSON.parse(source.textContent).classes || {};

    var listeClasses = document.getElementById('classe_annee_id');
    var listeMatieres = document.getElementById('matiere_id');
    var listeTypes = document.getElementById('type_note_id');
    var listeNumeros = document.getElementById('numero');
    var aideMatiere = document.getElementById('aide-matiere');
    var alerteEnseignant = document.getElementById('alerte-enseignant');
    var boutonPreparer = document.getElementById('bouton-preparer');

    // Remplace les choix d'une liste, en gardant le choix en cours s'il existe toujours
    function remplir(liste, choix, valeur) {
        liste.innerHTML = '';
        choix.forEach(function (unChoix) {
            var option = document.createElement('option');
            option.value = unChoix.valeur;
            option.textContent = unChoix.texte;
            liste.appendChild(option);
        });
        liste.value = valeur;
        if (liste.selectedIndex < 0) {
            liste.selectedIndex = 0;
        }
    }

    function classeChoisie() {
        return listeClasses.value ? (classes[listeClasses.value] || { matieres: [], lien: null }) : null;
    }

    // Texte sous la liste des matières, suivi d'un lien vers les matières de la classe pour ceux qui les gèrent
    function expliquer(texte, important, lien, libelleDuLien) {
        aideMatiere.textContent = texte;
        aideMatiere.className = important ? 'form-text text-danger-600 fw-medium' : 'form-text';
        if (lien) {
            var ancre = document.createElement('a');
            ancre.href = lien;
            ancre.textContent = libelleDuLien;
            ancre.className = 'fw-semibold text-primary-600 hover-underline';
            aideMatiere.appendChild(document.createTextNode(' '));
            aideMatiere.appendChild(ancre);
        }
    }

    // La liste « Matière » ne propose que les matières rattachées à la classe choisie
    function actualiserMatieres() {
        var classe = classeChoisie();
        var matiereEnCours = listeMatieres.value;

        if (!classe) {
            remplir(listeMatieres, [{ valeur: '', texte: "Choisissez d'abord une classe" }], '');
            expliquer('', false);
        } else if (classe.matieres.length === 0) {
            remplir(listeMatieres, [{ valeur: '', texte: 'Aucune matière pour cette classe' }], '');
            expliquer(
                "Aucune matière n'est rattachée à cette classe."
                    + (classe.lien ? '' : ' Adressez-vous au censeur ou à la direction.'),
                true, classe.lien, 'Rattacher ses matières'
            );
        } else {
            remplir(listeMatieres, [{ valeur: '', texte: '-- Choisir --' }].concat(
                classe.matieres.map(function (matiere) {
                    return { valeur: String(matiere.id), texte: matiere.nom };
                })
            ), matiereEnCours);
            expliquer('', false);
        }

        var classeSansMatiere = !!classe && classe.matieres.length === 0;
        listeMatieres.disabled = !classe || classeSansMatiere;
        boutonPreparer.disabled = classeSansMatiere;

        actualiserEnseignant();
    }

    // Aucun enseignant dans l'établissement : les notes ne pourront pas être enregistrées, on prévient avant
    function actualiserEnseignant() {
        if (!alerteEnseignant) {
            return;
        }

        var classe = classeChoisie();
        var matiere = classe && classe.matieres.filter(function (uneMatiere) {
            return String(uneMatiere.id) === listeMatieres.value;
        })[0];

        alerteEnseignant.classList.toggle('d-none', !matiere || matiere.enseignant);
    }

    // Le n° va de 1 au nombre d'évaluations que le type choisi autorise par période (data-max) :
    // pas de « n° 4 » pour un devoir limité à 2
    function actualiserNumeros(garderLeChoix) {
        var type = listeTypes.options[listeTypes.selectedIndex];
        var numeroEnCours = garderLeChoix ? listeNumeros.value : '1';

        if (!type || !type.value) {
            remplir(listeNumeros, [{ valeur: '1', texte: 'N° 1' }], '1');
            listeNumeros.disabled = true;
            return;
        }

        var maximum = parseInt(type.dataset.max || '1', 10) || 1;
        var choix = [];
        for (var numero = 1; numero <= maximum; numero++) {
            choix.push({ valeur: String(numero), texte: 'N° ' + numero });
        }
        remplir(listeNumeros, choix, numeroEnCours);
        listeNumeros.disabled = false;
    }

    function toutActualiser() {
        actualiserMatieres();
        actualiserNumeros(true);
    }

    listeClasses.addEventListener('change', actualiserMatieres);
    listeMatieres.addEventListener('change', actualiserEnseignant);
    listeTypes.addEventListener('change', function () {
        actualiserNumeros(false);
    });

    toutActualiser();
    // Retour arrière du navigateur : il remet les anciens choix sans prévenir, les listes sont réalignées
    window.addEventListener('pageshow', toutActualiser);
})();
</script>
@endpush
