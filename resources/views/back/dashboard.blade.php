@extends('back.layouts.master')
@section('title', 'Tableau de bord')

@section('content')
@php
    // Mise en forme commune à toute la page
    $nombre = fn ($valeur) => number_format((float) $valeur, 0, ',', ' ');
    $fcfa = fn ($valeur) => $nombre($valeur).' FCFA';
    $pluriel = fn ($quantite, string $singulier, ?string $pluriel = null) => $nombre($quantite).' '.($quantite > 1 ? ($pluriel ?? $singulier.'s') : $singulier);
    $part = fn ($valeur, $total) => $total > 0 ? (int) round($valeur / $total * 100) : 0;
    // Largeur d'une barre, en pourcentage de la plus grande valeur de la série
    $largeur = fn ($valeur, $max) => $max > 0 && $valeur > 0 ? max(2, round($valeur / $max * 100, 1)) : 0;
    // Les dates sont enregistrées en UTC : elles sont affichées à l'heure de l'établissement
    $jour = fn ($date) => \Illuminate\Support\Carbon::parse($date)->timezone($fuseau)->format('d/m/Y');

    $notesAnnee = $anneeCourante['notes'] ?? 0;
    $periodesAnnee = collect($anneeCourante['periodes'] ?? []);

    // Colonnes de l'historique : les notes et les encaissements dépendent des droits du rôle
    $colonnes = collect([
        ['cle' => 'eleves', 'titre' => 'Élèves inscrits', 'format' => $nombre],
        ['cle' => 'classes', 'titre' => 'Classes', 'format' => $nombre],
        $peut['notes'] ? ['cle' => 'notes', 'titre' => 'Notes saisies', 'format' => $nombre] : null,
        $peut['finances'] ? ['cle' => 'encaissements', 'titre' => 'Encaissements', 'format' => $fcfa] : null,
    ])->filter()->map(fn (array $colonne) => $colonne + [
        'max' => $historique->max($colonne['cle']),
        'chiffres' => $historique->max(fn ($ligne) => mb_strlen($colonne['format']($ligne[$colonne['cle']]))),
    ])->values();

    $noteHistorique = 'Élèves inscrits dans une classe de l\'année ; notes rattachées aux périodes de l\'année'
        .($peut['finances'] ? ' ; paiements rattachés à ce qui est payé (scolarité, frais de dossier, cantine, uniformes)' : '').'.';

    // Chiffres clés : libellé, valeur, icône, précision
    $tuiles = [
        ['Élèves', $totalEleves, 'ri-group-line', $pluriel($elevesActifs, 'actif').' · '.$pluriel($nouvellesInscriptions, 'nouveau', 'nouveaux').' sur 30 jours'],
        ['Classes', $totalClasses, 'ri-layout-grid-line', $totalClasses > 0 ? 'En moyenne '.$pluriel(round($totalEleves / $totalClasses), 'élève').' par classe' : 'Aucune classe ouverte cette année'],
        ['Enseignants en poste', $totalEnseignants, 'ri-presentation-line', 'Sur '.$pluriel($enseignantsInscrits, 'compte enseignant', 'comptes enseignants')],
        ['Parents d\'élèves inscrits', $totalParents, 'ri-parent-line', 'Sur '.$pluriel($parentsInscrits, 'compte parent', 'comptes parents')],
    ];

    // Répartition par sexe : libellé, effectif, barre mise en avant
    $repartition = array_filter([
        ['Garçons', $elevesHommes, true],
        ['Filles', $elevesFemmes, true],
        $elevesSexeNonRenseigne > 0 ? ['Sexe non renseigné', $elevesSexeNonRenseigne, false] : null,
    ]);

    $activites = collect(['finances', 'eleves', 'historiqueNotes', 'connexions'])->filter(fn ($bloc) => $peut[$bloc])->count();
    $colonneActivite = match ($activites) { 1 => 'col-12', 2, 4 => 'col-xl-6', default => 'col-xxl-4 col-md-6' };
@endphp

<div class="dashboard-main-body tdb">

    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Tableau de bord</h1>
            <p class="text-secondary-light mb-0">{{ config('ecole.nom') }} · {{ $dateDuJour }}</p>
        </div>
        @if($anneeActive)
            <span class="tdb-annee" title="Du {{ $anneeActive->date_debut->format('d/m/Y') }} au {{ $anneeActive->date_fin->format('d/m/Y') }}">
                <i class="ri-calendar-check-line" aria-hidden="true"></i>
                Année scolaire {{ $anneeActive->libelle }}
            </span>
        @endif
    </div>

    @if($accesRapides->isNotEmpty())
        <nav class="d-flex flex-wrap gap-2 mb-24" aria-label="Accès rapides">
            @foreach($accesRapides as $lien)
                <a href="{{ route($lien['route']) }}" class="btn btn-sm {{ $loop->first ? 'btn-primary-600' : 'btn-outline-primary-600' }} d-inline-flex align-items-center gap-6">
                    <i class="{{ $lien['icone'] }}" aria-hidden="true"></i> {{ $lien['libelle'] }}
                </a>
            @endforeach
        </nav>
    @endif

    @unless($anneeActive)
        <div class="alert alert-warning d-flex align-items-start gap-2 mb-24" role="alert">
            <i class="ri-error-warning-line text-lg" aria-hidden="true"></i>
            <div>
                Aucune année scolaire n'est active : les chiffres de l'année restent à zéro tant qu'une année n'est pas activée.
                @if($peut['anneesScolaires'])
                    <a href="{{ route('admin.annees-scolaires.index') }}" class="alert-link">Gérer les années scolaires</a>
                @endif
            </div>
        </div>
    @endunless

    {{-- ==================== CHIFFRES CLÉS DE L'ANNÉE ACTIVE ==================== --}}
    <section class="mb-24" aria-labelledby="tdb-chiffres">
        <h2 id="tdb-chiffres" class="tdb-titre-section">
            Chiffres clés @if($anneeActive)<span>· année {{ $anneeActive->libelle }}</span>@endif
        </h2>

        <div class="row g-3">
            @foreach($tuiles as [$libelle, $valeur, $icone, $detail])
                <div class="col-xxl-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-body p-20">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-12">
                                <span class="tdb-tuile__libelle">{{ $libelle }}</span>
                                <span class="tdb-tuile__icone"><i class="{{ $icone }}" aria-hidden="true"></i></span>
                            </div>
                            <div class="tdb-tuile__valeur">{{ $nombre($valeur) }}</div>
                            <p class="tdb-tuile__detail">{{ $detail }}</p>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Répartition filles / garçons --}}
            <div class="col-md">
                <div class="card h-100">
                    <div class="card-body p-20">
                        <h3 class="tdb-carte__titre mb-12">Répartition des élèves</h3>
                        @if($totalEleves > 0)
                            @foreach($repartition as [$libelle, $effectif, $accent])
                                <div class="tdb-jauge">
                                    <span class="tdb-jauge__libelle">{{ $libelle }}</span>
                                    <span class="tdb-jauge__piste"><span @class(['tdb-barre', 'tdb-barre--accent' => $accent]) style="width: {{ $largeur($effectif, $totalEleves) }}%"></span></span>
                                    <span class="tdb-jauge__valeur">{{ $nombre($effectif) }} <small>({{ $part($effectif, $totalEleves) }} %)</small></span>
                                </div>
                            @endforeach
                        @else
                            <p class="tdb-tuile__detail">Aucun élève inscrit cette année pour l'instant.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Notes saisies sur l'année, période par période --}}
            @if($peut['notes'])
                <div class="col-md">
                    <div class="card h-100">
                        <div class="card-body p-20">
                            <h3 class="tdb-carte__titre mb-12">Notes saisies</h3>
                            <div class="tdb-tuile__valeur mb-8">{{ $nombre($notesAnnee) }}</div>
                            @if($notesAnnee > 0)
                                @foreach($periodesAnnee as $periode)
                                    <div class="tdb-jauge">
                                        <span class="tdb-jauge__libelle">{{ $periode['nom'] }}</span>
                                        <span class="tdb-jauge__piste"><span class="tdb-barre tdb-barre--accent" style="width: {{ $largeur($periode['notes'], $periodesAnnee->max('notes')) }}%"></span></span>
                                        <span class="tdb-jauge__valeur">{{ $nombre($periode['notes']) }}</span>
                                    </div>
                                @endforeach
                            @else
                                <p class="tdb-tuile__detail">Aucune note saisie cette année pour l'instant.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Encaissements : réservés aux rôles qui ont la page des scolarités --}}
            @if($peut['finances'])
                <div class="col-md">
                    <div class="card h-100">
                        <div class="card-body p-20">
                            <h3 class="tdb-carte__titre mb-12">Encaissements de l'année</h3>
                            <div class="tdb-tuile__valeur text-nowrap">{{ $fcfa($paiementsAnnee) }}</div>
                            <p class="tdb-tuile__detail">
                                @if($encaissementsParMois && $encaissementsParMois['nombre'] > 0)
                                    {{ $pluriel($encaissementsParMois['nombre'], 'paiement') }} rattachés à l'année.
                                @else
                                    Aucun encaissement cette année pour l'instant.
                                @endif
                            </p>
                            <p class="tdb-tuile__detail">Toutes années confondues : <strong class="text-primary-light text-nowrap">{{ $fcfa($totalPaiements) }}</strong></p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- ==================== HISTORIQUES ==================== --}}
    <section class="mb-24" aria-labelledby="tdb-historiques">
        <h2 id="tdb-historiques" class="tdb-titre-section">Historiques</h2>

        <div class="row g-3">
            {{-- Historique par année scolaire --}}
            {{-- Pleine largeur : avec ses cinq colonnes et leurs barres, le tableau ne tient pas dans une demi-largeur --}}
            <div class="col-12">
                <div class="card h-100">
                    <div class="tdb-carte__entete">
                        <h3 class="tdb-carte__titre">Historique par année scolaire</h3>
                        @if($peut['anneesScolaires'])
                            <a href="{{ route('admin.annees-scolaires.index') }}" class="tdb-carte__lien">Gérer les années</a>
                        @endif
                    </div>

                    @if($historique->isEmpty())
                        <div class="tdb-vide">
                            <i class="ri-calendar-line" aria-hidden="true"></i>
                            <strong>Aucune année scolaire enregistrée</strong>
                            <span>L'historique apparaîtra dès qu'une année scolaire aura été créée.</span>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="tdb-tableau">
                                <thead>
                                    <tr>
                                        <th scope="col">Année scolaire</th>
                                        @foreach($colonnes as $colonne)
                                            <th scope="col">{{ $colonne['titre'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($historique as $ligne)
                                        <tr @class(['est-active' => $ligne['active']])>
                                            <th scope="row">
                                                {{ $ligne['libelle'] }}
                                                @if($ligne['active'])
                                                    <span class="tdb-pastille">Année active</span>
                                                @endif
                                            </th>
                                            @foreach($colonnes as $colonne)
                                                <td>
                                                    <div class="tdb-mesure">
                                                        <span class="tdb-mesure__valeur" style="min-width: {{ $colonne['chiffres'] }}ch">{{ $colonne['format']($ligne[$colonne['cle']]) }}</span>
                                                        <span class="tdb-mesure__barre"><span @class(['tdb-barre', 'tdb-barre--accent' => $ligne['active']]) style="width: {{ $largeur($ligne[$colonne['cle']], $colonne['max']) }}%"></span></span>
                                                    </div>
                                                    @if($colonne['cle'] === 'eleves' && $ligne['elevesPartis'] > 0)
                                                        <div class="tdb-mesure__note">dont {{ $pluriel($ligne['elevesPartis'], 'réinscrit') }} ensuite dans une autre année</div>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="tdb-carte__note">
                            {{ $noteHistorique }}
                            @if($anneesPlusAnciennes)
                                Seules les {{ \App\Services\TableauDeBordService::ANNEES_AFFICHEES }} années les plus récentes sont affichées.
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            {{-- Encaissements de l'année active, mois par mois --}}
            @if($peut['finances'])
                <div class="col-12">
                    <div class="card h-100">
                        <div class="tdb-carte__entete">
                            <h3 class="tdb-carte__titre">Encaissements par mois</h3>
                            <a href="{{ route('admin.scolarites.index') }}" class="tdb-carte__lien">Scolarités et tranches</a>
                        </div>

                        @if(! $anneeActive)
                            <div class="tdb-vide">
                                <i class="ri-calendar-line" aria-hidden="true"></i>
                                <strong>Aucune année scolaire active</strong>
                                <span>Les encaissements mensuels s'affichent pour l'année active.</span>
                            </div>
                        @elseif($encaissementsParMois['nombre'] === 0)
                            <div class="tdb-vide">
                                <i class="ri-wallet-3-line" aria-hidden="true"></i>
                                <strong>Aucun encaissement pour {{ $anneeActive->libelle }}</strong>
                                <span>Les paiements apparaîtront ici, mois par mois, dès le premier enregistrement.</span>
                            </div>
                        @else
                            @php $maxMois = $encaissementsParMois['mois']->max('total'); @endphp
                            <div class="table-responsive">
                                <table class="tdb-tableau tdb-tableau--serre">
                                    <thead>
                                        <tr>
                                            <th scope="col">Mois</th>
                                            <th scope="col" class="text-end">Paiements</th>
                                            <th scope="col">Montant encaissé</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($encaissementsParMois['mois'] as $mois)
                                            <tr>
                                                <th scope="row">{{ \Illuminate\Support\Str::ucfirst($mois['libelle']) }}</th>
                                                <td class="text-end">{{ $mois['nombre'] > 0 ? $nombre($mois['nombre']) : '—' }}</td>
                                                <td>
                                                    <div class="tdb-mesure">
                                                        <span class="tdb-mesure__valeur" style="min-width: {{ mb_strlen($fcfa($maxMois)) }}ch">{{ $mois['total'] > 0 ? $fcfa($mois['total']) : '—' }}</span>
                                                        <span class="tdb-mesure__barre"><span class="tdb-barre tdb-barre--accent" style="width: {{ $largeur($mois['total'], $maxMois) }}%"></span></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th scope="row">Total {{ $anneeActive->libelle }}</th>
                                            <td class="text-end">{{ $nombre($encaissementsParMois['nombre']) }}</td>
                                            <td><span class="tdb-mesure__valeur">{{ $fcfa($encaissementsParMois['total']) }}</span></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            @if($encaissementsParMois['horsPeriode'] > 0)
                                <p class="tdb-carte__note">Dont {{ $fcfa($encaissementsParMois['horsPeriode']) }} enregistrés à une date trop éloignée de l'année pour figurer dans ce tableau.</p>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- ==================== ACTIVITÉ RÉCENTE (selon les pages ouvertes au rôle) ==================== --}}
    @if($activites > 0)
        <section class="mb-24" aria-labelledby="tdb-activite">
            <h2 id="tdb-activite" class="tdb-titre-section">Activité récente</h2>

            <div class="row g-3">
                @if($peut['finances'])
                    <div class="{{ $colonneActivite }}">
                        <div class="card h-100">
                            <div class="tdb-carte__entete">
                                <h3 class="tdb-carte__titre">Derniers paiements</h3>
                            </div>
                            @if($derniersPaiements->isEmpty())
                                <div class="tdb-vide">
                                    <i class="ri-wallet-3-line" aria-hidden="true"></i>
                                    <strong>Aucun paiement enregistré</strong>
                                </div>
                            @else
                                <ul class="tdb-liste">
                                    @foreach($derniersPaiements as $paiement)
                                        <li>
                                            <div>
                                                <div class="tdb-liste__principal">{{ $paiement['eleve'] }}</div>
                                                <div class="tdb-liste__secondaire">{{ $paiement['objet'] }} · {{ $paiement['mode'] }} · {{ $paiement['date']->format('d/m/Y') }}</div>
                                            </div>
                                            <div class="tdb-liste__valeur">{{ $fcfa($paiement['montant']) }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif

                @if($peut['eleves'])
                    <div class="{{ $colonneActivite }}">
                        <div class="card h-100">
                            <div class="tdb-carte__entete">
                                <h3 class="tdb-carte__titre">Dernières inscriptions</h3>
                                <a href="{{ route('admin.eleves.index') }}" class="tdb-carte__lien">Liste des élèves</a>
                            </div>
                            @if($dernieresInscriptions->isEmpty())
                                <div class="tdb-vide">
                                    <i class="ri-user-add-line" aria-hidden="true"></i>
                                    <strong>Aucune inscription cette année</strong>
                                </div>
                            @else
                                <ul class="tdb-liste">
                                    @foreach($dernieresInscriptions as $eleve)
                                        <li>
                                            <div>
                                                <div class="tdb-liste__principal">{{ $eleve->prenom }} {{ $eleve->nom }}</div>
                                                <div class="tdb-liste__secondaire">{{ $eleve->matricule }} · inscrit le {{ $jour($eleve->created_at) }}</div>
                                            </div>
                                            <div class="tdb-liste__valeur">{{ $eleve->classeAnnee?->classe?->full_name }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif

                @if($peut['historiqueNotes'])
                    <div class="{{ $colonneActivite }}">
                        <div class="card h-100">
                            <div class="tdb-carte__entete">
                                <h3 class="tdb-carte__titre">Dernières saisies de notes</h3>
                                <a href="{{ route('admin.notes.index') }}" class="tdb-carte__lien">Historique des notes</a>
                            </div>
                            @if($dernieresSaisies->isEmpty())
                                <div class="tdb-vide">
                                    <i class="ri-file-list-3-line" aria-hidden="true"></i>
                                    <strong>Aucune note saisie</strong>
                                </div>
                            @else
                                <ul class="tdb-liste">
                                    @foreach($dernieresSaisies as $saisie)
                                        <li>
                                            <div>
                                                <div class="tdb-liste__principal">{{ $saisie['classe'] ?? 'Classe non renseignée' }} · {{ $saisie['matiere'] }}</div>
                                                <div class="tdb-liste__secondaire">
                                                    {{ $saisie['type'] }} · {{ $saisie['periode'] }}@if($saisie['anneeId'] !== $anneeActive?->id) ({{ $saisie['annee'] }})@endif · {{ $jour($saisie['date']) }}
                                                </div>
                                            </div>
                                            <div class="tdb-liste__valeur">{{ $pluriel($saisie['nombre'], 'note') }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif

                @if($peut['connexions'])
                    <div class="{{ $colonneActivite }}">
                        <div class="card h-100">
                            <div class="tdb-carte__entete">
                                <h3 class="tdb-carte__titre">Dernières connexions des parents</h3>
                                <a href="{{ route('admin.connexion-logs.index') }}" class="tdb-carte__lien">Journal des connexions</a>
                            </div>
                            @if($dernieresConnexions->isEmpty())
                                <div class="tdb-vide">
                                    <i class="ri-smartphone-line" aria-hidden="true"></i>
                                    <strong>Aucune connexion enregistrée</strong>
                                </div>
                            @else
                                <ul class="tdb-liste">
                                    @foreach($dernieresConnexions as $connexion)
                                        <li>
                                            <div>
                                                <div class="tdb-liste__principal">{{ $connexion->parent ? trim($connexion->parent->prenom.' '.$connexion->parent->nom) : 'Parent non renseigné' }}</div>
                                                <div class="tdb-liste__secondaire">Application mobile</div>
                                            </div>
                                            <div class="tdb-liste__secondaire text-nowrap">{{ $connexion->created_at?->locale('fr')->diffForHumans() }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ==================== VIE DE L'ÉTABLISSEMENT ==================== --}}
    <section aria-labelledby="tdb-vie">
        <h2 id="tdb-vie" class="tdb-titre-section">Vie de l'établissement</h2>

        <div class="row g-3">
            {{-- Communiqués de l'année et événements à venir --}}
            <div class="col-xxl-4 col-md-6">
                <div class="card h-100">
                    <div class="tdb-carte__entete">
                        <h3 class="tdb-carte__titre">Communiqués</h3>
                        @if($peut['communiques'])
                            <a href="{{ route('admin.communiques.index') }}" class="tdb-carte__lien">Tous les communiqués</a>
                        @endif
                    </div>
                    @if($communiques->isEmpty())
                        <div class="tdb-vide">
                            <i class="ri-megaphone-line" aria-hidden="true"></i>
                            <strong>Aucun communiqué cette année</strong>
                        </div>
                    @else
                        <ul class="tdb-liste">
                            @foreach($communiques as $communique)
                                <li>
                                    <div>
                                        <div class="tdb-liste__principal">{{ $communique->titre }}</div>
                                        <div class="tdb-liste__secondaire">{{ \Illuminate\Support\Str::limit(strip_tags($communique->contenu), 90) }}</div>
                                        <div class="tdb-liste__secondaire">{{ $communique->label_type }} · {{ $communique->date_publication->format('d/m/Y') }}</div>
                                    </div>
                                    @if($peut['communique'])
                                        <a href="{{ route('admin.communiques.show', $communique) }}" class="tdb-carte__lien">Voir</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($evenements->isNotEmpty())
                        <div class="tdb-carte__entete tdb-carte__entete--suite">
                            <h3 class="tdb-carte__titre">Événements à venir</h3>
                        </div>
                        <ul class="tdb-liste">
                            @foreach($evenements as $evenement)
                                <li>
                                    <div>
                                        <div class="tdb-liste__principal">{{ $evenement->titre }}</div>
                                        <div class="tdb-liste__secondaire">{{ $evenement->date_publication->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</div>
                                    </div>
                                    @if($peut['communique'])
                                        <a href="{{ route('admin.communiques.show', $evenement) }}" class="tdb-carte__lien">Voir</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Meilleures moyennes de la dernière période notée --}}
            @if($peut['notes'])
                <div class="col-xxl-4 col-md-6">
                    <div class="card h-100">
                        <div class="tdb-carte__entete">
                            <h3 class="tdb-carte__titre">Meilleures moyennes @if($periodeDesMoyennes)<span>· {{ $periodeDesMoyennes['nom'] }}</span>@endif</h3>
                        </div>
                        @if($meilleuresMoyennes->isEmpty())
                            <div class="tdb-vide">
                                <i class="ri-medal-line" aria-hidden="true"></i>
                                <strong>Aucune note saisie cette année</strong>
                            </div>
                        @else
                            <ul class="tdb-liste">
                                @foreach($meilleuresMoyennes as $eleve)
                                    <li>
                                        <div>
                                            <div class="tdb-liste__principal">{{ $eleve->prenom }} {{ $eleve->nom }}</div>
                                            <div class="tdb-liste__secondaire">{{ $eleve->classeAnnee?->classe?->full_name }} · {{ $pluriel($eleve->nombre_notes, 'note') }}</div>
                                        </div>
                                        <div class="tdb-liste__valeur">{{ number_format((float) $eleve->moyenne, 2, ',', ' ') }} <small>/ 20</small></div>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="tdb-carte__note">Moyenne simple des notes saisies sur la période, sans coefficient : ce n'est pas la moyenne du bulletin.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Enseignants en poste cette année --}}
            <div class="col-xxl-4 col-md-6">
                <div class="card h-100">
                    <div class="tdb-carte__entete">
                        <h3 class="tdb-carte__titre">Enseignants en poste</h3>
                    </div>
                    @if($enseignantsEnPoste->isEmpty())
                        <div class="tdb-vide">
                            <i class="ri-presentation-line" aria-hidden="true"></i>
                            <strong>Aucun enseignant en poste cette année</strong>
                            <span>Un enseignant apparaît ici dès qu'une matière lui est attribuée dans une classe de l'année.</span>
                        </div>
                    @else
                        <ul class="tdb-liste">
                            @foreach($enseignantsEnPoste as $enseignant)
                                <li>
                                    <div>
                                        <div class="tdb-liste__principal">{{ $enseignant->prenom }} {{ $enseignant->nom }}</div>
                                        @if($enseignant->user?->email)
                                            <div class="tdb-liste__secondaire">{{ $enseignant->user->email }}</div>
                                        @endif
                                    </div>
                                    @if($enseignant->matiere_principale)
                                        <div class="tdb-liste__secondaire">{{ $enseignant->matiere_principale }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if($totalEnseignants > $enseignantsEnPoste->count())
                            <p class="tdb-carte__note">Les {{ $enseignantsEnPoste->count() }} derniers ajoutés, sur {{ $nombre($totalEnseignants) }} en poste cette année.</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/dashboard.css') }}">
@endpush
