<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Communique;
use App\Models\Enseignant;
use App\Models\Paiement;
use App\Models\Parents; // Attention : le modèle s'appelle Parents (avec un « s »)
use App\Services\TableauDeBordService;
use App\Support\AccesMenu;
use Illuminate\Support\Collection;

class AdminController extends Controller
{
    /**
     * Pages dont dépend un bloc ou un lien du tableau de bord. Même règle que le menu latéral :
     * le bloc n'est préparé et affiché que si le rôle connecté a le droit d'ouvrir la page.
     */
    private const PAGES = [
        'finances' => 'admin.scolarites.index',
        'connexions' => 'admin.connexion-logs.index',
        'eleves' => 'admin.eleves.index',
        'historiqueNotes' => 'admin.notes.index',
        'cahierNotes' => 'admin.cahier-notes.index',
        'communiques' => 'admin.communiques.index',
        'communique' => 'admin.communiques.show',
        'anneesScolaires' => 'admin.annees-scolaires.index',
    ];

    /**
     * Accès rapides proposés en tête de page, par ordre de priorité : seuls ceux que le rôle peut
     * ouvrir sont gardés, dans la limite de NOMBRE_ACCES_RAPIDES.
     */
    private const ACCES_RAPIDES = [
        ['route' => 'admin.notes.create', 'libelle' => 'Saisir des notes', 'icone' => 'ri-edit-2-line'],
        ['route' => 'admin.eleves.create', 'libelle' => 'Inscrire un élève', 'icone' => 'ri-user-add-line'],
        ['route' => 'admin.cahier-notes.index', 'libelle' => 'Cahier de notes', 'icone' => 'ri-book-2-line'],
        ['route' => 'admin.scolarites.index', 'libelle' => 'Scolarités et tranches', 'icone' => 'ri-money-dollar-circle-line'],
        ['route' => 'admin.notes.index', 'libelle' => 'Historique des notes', 'icone' => 'ri-history-line'],
        ['route' => 'admin.communiques.create', 'libelle' => 'Publier un communiqué', 'icone' => 'ri-megaphone-line'],
        ['route' => 'admin.messagerie.index', 'libelle' => 'Messagerie', 'icone' => 'ri-message-3-line'],
        ['route' => 'admin.equipe.index', 'libelle' => 'Mon équipe', 'icone' => 'ri-shield-user-line'],
        ['route' => 'admin.creneaux-horaires.index', 'libelle' => 'Créneaux horaires', 'icone' => 'ri-time-line'],
    ];

    private const NOMBRE_ACCES_RAPIDES = 4;

    public function dashboard(TableauDeBordService $tableauDeBord)
    {
        // Les chiffres clés sont calculés sur l'année scolaire active. Sans année active,
        // l'identifiant est null et chaque compteur retombe à zéro.
        $anneeActive = AnneeScolaire::active();
        $anneeId = $anneeActive?->id;

        $peut = $this->droits();

        // L'application tourne en UTC : la date du jour est celle de l'établissement
        $fuseau = config('ecole.fuseau') ?: config('app.timezone');
        $aujourdhui = now($fuseau);

        $classeDeLAnnee = fn ($q) => $q->pourAnnee($anneeId);

        // --- Chiffres clés de l'année active ---
        $effectifs = $tableauDeBord->effectifs($anneeId, now()->subDays(30));

        // Enseignants et parents ne sont pas rattachés à une année : on retient ceux qui ont
        // une matière assignée / un enfant inscrit cette année, avec le total des comptes en rappel.
        $enseignantsDeLAnnee = fn () => Enseignant::whereHas('classeMatieres.classeAnnee', $classeDeLAnnee);
        $totalEnseignants = $enseignantsDeLAnnee()->count();
        $enseignantsInscrits = Enseignant::count();
        $totalParents = Parents::whereHas('eleves', fn ($q) => $q->pourAnnee($anneeId))->count();
        $parentsInscrits = Parents::count();

        // --- Historique par année scolaire ---
        // Les encaissements ne sont calculés que pour les rôles qui ont accès aux finances.
        $historique = $tableauDeBord->historiqueParAnnee($anneeActive, $peut['finances']);

        // Classes et notes de l'année active viennent de sa ligne dans l'historique : le chiffre clé
        // et le tableau ne peuvent pas se contredire.
        $anneeCourante = $historique['lignes']->firstWhere('active', true);
        $totalClasses = $anneeCourante['classes'] ?? 0;

        // --- Finances : uniquement pour qui a la page des scolarités ---
        $totalPaiements = $paiementsAnnee = $encaissementsParMois = $derniersPaiements = null;
        if ($peut['finances']) {
            $totalPaiements = (float) Paiement::sum('montant');
            $encaissementsParMois = $anneeActive ? $tableauDeBord->encaissementsParMois($anneeActive) : null;
            $paiementsAnnee = $encaissementsParMois['total'] ?? 0.0;
            $derniersPaiements = $tableauDeBord->derniersPaiements();
        }

        // --- Activité récente, selon les pages ouvertes au rôle ---
        $dernieresInscriptions = $peut['eleves'] ? $tableauDeBord->dernieresInscriptions($anneeId) : null;
        $dernieresSaisies = $peut['historiqueNotes'] ? $tableauDeBord->dernieresSaisiesDeNotes() : null;
        $dernieresConnexions = $peut['connexions'] ? $tableauDeBord->dernieresConnexions() : null;

        // --- Communiqués de l'année : adressés à tout l'établissement ou à une classe de l'année ---
        $communiqueDeLAnnee = fn ($q) => $q->whereDoesntHave('classesAnnees')
            ->orWhereHas('classesAnnees', $classeDeLAnnee);

        $communiques = Communique::where('actif', true)
            ->where($communiqueDeLAnnee)
            ->orderByDesc('date_publication')->orderByDesc('id')
            ->limit(TableauDeBordService::LIGNES_ACTIVITE)
            ->get();

        // --- Événements à venir (aujourd'hui compris) ---
        $evenements = Communique::where('actif', true)
            ->where('type', 'evenement')
            ->where('date_publication', '>=', $aujourdhui->toDateString())
            ->where($communiqueDeLAnnee)
            ->orderBy('date_publication')->orderBy('id')
            ->limit(TableauDeBordService::LIGNES_ACTIVITE)
            ->get();

        // --- Enseignants en poste cette année (les derniers ajoutés) ---
        $enseignantsEnPoste = $enseignantsDeLAnnee()
            ->with('user:id,email')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(TableauDeBordService::LIGNES_ACTIVITE)
            ->get();

        // --- Meilleures moyennes : dernière période de l'année active où des notes ont été saisies ---
        $periodeDesMoyennes = collect($anneeCourante['periodes'] ?? [])->where('notes', '>', 0)->last();
        $meilleuresMoyennes = $peut['notes'] && $periodeDesMoyennes
            ? $tableauDeBord->meilleuresMoyennes($periodeDesMoyennes['id'])
            : collect();

        return view('back.dashboard', [
            'anneeActive' => $anneeActive,
            'dateDuJour' => $aujourdhui->locale('fr')->isoFormat('dddd D MMMM YYYY'),
            'fuseau' => $fuseau,
            'peut' => $peut,
            'accesRapides' => $this->accesRapides(),

            'totalEleves' => $effectifs['total'],
            'elevesActifs' => $effectifs['actifs'],
            'elevesHommes' => $effectifs['garcons'],
            'elevesFemmes' => $effectifs['filles'],
            'elevesSexeNonRenseigne' => $effectifs['sexeNonRenseigne'],
            'nouvellesInscriptions' => $effectifs['nouveaux'],
            'totalClasses' => $totalClasses,
            'totalEnseignants' => $totalEnseignants,
            'enseignantsInscrits' => $enseignantsInscrits,
            'totalParents' => $totalParents,
            'parentsInscrits' => $parentsInscrits,

            'historique' => $historique['lignes'],
            'anneesPlusAnciennes' => $historique['anneesPlusAnciennes'],
            'anneeCourante' => $anneeCourante,

            'totalPaiements' => $totalPaiements,
            'paiementsAnnee' => $paiementsAnnee,
            'encaissementsParMois' => $encaissementsParMois,
            'derniersPaiements' => $derniersPaiements,

            'dernieresInscriptions' => $dernieresInscriptions,
            'dernieresSaisies' => $dernieresSaisies,
            'dernieresConnexions' => $dernieresConnexions,

            'communiques' => $communiques,
            'evenements' => $evenements,
            'enseignantsEnPoste' => $enseignantsEnPoste,
            'periodeDesMoyennes' => $periodeDesMoyennes,
            'meilleuresMoyennes' => $meilleuresMoyennes,
        ]);
    }

    /**
     * Ce que le rôle connecté peut ouvrir, pour chaque page de PAGES. « notes » : le rôle consulte
     * des notes par l'une ou l'autre de ses deux pages (historique ou cahier de notes).
     *
     * @return array<string, bool>
     */
    private function droits(): array
    {
        $peut = array_map(fn (string $route) => AccesMenu::autorise($route), self::PAGES);
        $peut['notes'] = $peut['historiqueNotes'] || $peut['cahierNotes'];

        return $peut;
    }

    /**
     * @return Collection<int, array{route: string, libelle: string, icone: string}>
     */
    private function accesRapides(): Collection
    {
        return collect(self::ACCES_RAPIDES)
            ->filter(fn (array $lien) => AccesMenu::autorise($lien['route']))
            ->take(self::NOMBRE_ACCES_RAPIDES)
            ->values();
    }
}
