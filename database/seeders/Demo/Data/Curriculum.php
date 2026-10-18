<?php

namespace Database\Seeders\Demo\Data;

/**
 * Organisation pédagogique d'un collège/lycée d'enseignement général au Bénin : niveaux de la
 * Sixième à la Terminale (une classe par niveau au collège, une par série au lycée : A, C, D), matières
 * avec leurs coefficients et leur volume horaire hebdomadaire selon le niveau et la série.
 */
final class Curriculum
{
    /**
     * Matières : code interne => [nom affiché, description, difficulté relative (points /20)].
     * La difficulté décale la moyenne générée (les mathématiques et la PCT sont plus sévères,
     * l'EPS et la conduite plus clémentes).
     */
    public const MATIERES = [
        'FR' => ['Français', 'Expression écrite, lecture méthodique et étude de textes', -0.4],
        'ANG' => ['Anglais', 'Langue vivante 1', 0.1],
        'ESP' => ['Espagnol', 'Langue vivante 2', 0.4],
        'HG' => ['Histoire-Géographie', 'Histoire, géographie et éducation civique', -0.2],
        'MATH' => ['Mathématiques', 'Algèbre, analyse, géométrie et statistiques', -1.3],
        'PCT' => ['PCT', 'Physique, Chimie et Technologie', -1.0],
        'SVT' => ['SVT', 'Sciences de la Vie et de la Terre', -0.3],
        'PHILO' => ['Philosophie', 'Philosophie (second cycle : Première et Terminale)', -1.4],
        'ECO' => ['Économie', 'Économie générale (série B)', -0.5],
        'EPS' => ['EPS', 'Éducation Physique et Sportive', 2.6],
        'INFO' => ['Informatique', 'Initiation à l\'informatique et à la bureautique', 1.6],
        'COND' => ['Conduite', 'Note de conduite trimestrielle (vie scolaire)', 3.2],
    ];

    /**
     * Niveaux : nom => [ordre, abréviation, profil de programme, classes].
     * Chaque classe : suffixe => [série, LV2 éventuelle, effectif visé].
     * L'ordre se suit de 1 en 1 pour que la campagne de réinscription trouve le niveau suivant.
     */
    public const NIVEAUX = [
        // Collège : une classe par niveau (suffixe vide : « Sixième », « Cinquième »...)
        'Sixième' => [7, '6e', ['' => ['college1', null, 29]]],
        'Cinquième' => [8, '5e', ['' => ['college1', null, 28]]],
        'Quatrième' => [9, '4e', ['' => ['college2', 'ESP', 28]]],
        'Troisième' => [10, '3e', ['' => ['college2', 'ESP', 27]]],
        // Lycée : une classe par série
        'Seconde' => [11, '2nde', [
            'A' => ['2nde-AB', 'ESP', 26], 'C' => ['2nde-C', null, 24],
        ]],
        'Première' => [12, '1ère', [
            'A' => ['1ere-A1', 'ESP', 22], 'C' => ['1ere-C', null, 18], 'D' => ['1ere-D', null, 26],
        ]],
        'Terminale' => [13, 'Tle', [
            'A' => ['tle-A1', 'ESP', 22], 'C' => ['tle-C', null, 18], 'D' => ['tle-D', null, 26],
        ]],
    ];

    /**
     * Programmes : profil => [matière => [coefficient, heures hebdomadaires]].
     * « LV2 » est remplacée par la langue vivante 2 de la classe (Espagnol).
     */
    public const PROGRAMMES = [
        'college1' => ['FR' => [3, 6], 'ANG' => [2, 3], 'HG' => [2, 3], 'MATH' => [3, 5], 'SVT' => [2, 3], 'PCT' => [2, 3], 'EPS' => [1, 2], 'INFO' => [1, 1], 'COND' => [1, 0]],
        'college2' => ['FR' => [3, 5], 'ANG' => [2, 3], 'LV2' => [2, 2], 'HG' => [2, 3], 'MATH' => [3, 5], 'SVT' => [2, 3], 'PCT' => [2, 3], 'EPS' => [1, 2], 'INFO' => [1, 1], 'COND' => [1, 0]],
        '2nde-AB' => ['FR' => [4, 5], 'ANG' => [3, 4], 'LV2' => [2, 3], 'HG' => [3, 4], 'MATH' => [2, 3], 'SVT' => [1, 2], 'PCT' => [1, 2], 'EPS' => [1, 2], 'INFO' => [1, 1], 'COND' => [1, 0]],
        '2nde-C' => ['FR' => [3, 4], 'ANG' => [2, 3], 'HG' => [2, 2], 'MATH' => [5, 6], 'PCT' => [4, 5], 'SVT' => [2, 3], 'EPS' => [1, 2], 'INFO' => [1, 1], 'COND' => [1, 0]],
        '2nde-D' => ['FR' => [3, 4], 'ANG' => [2, 3], 'HG' => [2, 2], 'MATH' => [4, 5], 'PCT' => [3, 4], 'SVT' => [4, 5], 'EPS' => [1, 2], 'INFO' => [1, 1], 'COND' => [1, 0]],
        '1ere-A1' => ['FR' => [4, 5], 'PHILO' => [3, 3], 'ANG' => [3, 4], 'LV2' => [3, 4], 'HG' => [3, 4], 'MATH' => [1, 2], 'SVT' => [1, 2], 'EPS' => [1, 2], 'COND' => [1, 0]],
        '1ere-A2' => ['FR' => [4, 5], 'PHILO' => [3, 3], 'ANG' => [3, 4], 'LV2' => [3, 4], 'HG' => [4, 4], 'MATH' => [1, 2], 'SVT' => [1, 2], 'EPS' => [1, 2], 'COND' => [1, 0]],
        '1ere-B' => ['ECO' => [4, 5], 'MATH' => [3, 4], 'HG' => [3, 4], 'FR' => [3, 4], 'PHILO' => [2, 2], 'ANG' => [2, 3], 'LV2' => [2, 2], 'SVT' => [1, 1], 'EPS' => [1, 2], 'COND' => [1, 0]],
        '1ere-C' => ['MATH' => [6, 7], 'PCT' => [5, 6], 'SVT' => [2, 2], 'FR' => [2, 3], 'PHILO' => [2, 2], 'ANG' => [2, 3], 'HG' => [2, 2], 'EPS' => [1, 2], 'COND' => [1, 0]],
        '1ere-D' => ['MATH' => [4, 5], 'PCT' => [4, 5], 'SVT' => [5, 5], 'FR' => [2, 3], 'PHILO' => [2, 2], 'ANG' => [2, 3], 'HG' => [2, 2], 'EPS' => [1, 2], 'COND' => [1, 0]],
        'tle-A1' => ['FR' => [4, 4], 'PHILO' => [5, 5], 'ANG' => [3, 4], 'LV2' => [3, 4], 'HG' => [3, 4], 'MATH' => [1, 2], 'SVT' => [1, 1], 'EPS' => [1, 2], 'COND' => [1, 0]],
        'tle-A2' => ['FR' => [4, 4], 'PHILO' => [5, 5], 'ANG' => [3, 4], 'LV2' => [3, 4], 'HG' => [4, 4], 'MATH' => [1, 2], 'SVT' => [1, 1], 'EPS' => [1, 2], 'COND' => [1, 0]],
        'tle-B' => ['ECO' => [5, 5], 'MATH' => [3, 4], 'HG' => [3, 4], 'FR' => [2, 3], 'PHILO' => [3, 3], 'ANG' => [2, 3], 'LV2' => [2, 2], 'SVT' => [1, 1], 'EPS' => [1, 2], 'COND' => [1, 0]],
        'tle-C' => ['MATH' => [7, 7], 'PCT' => [6, 6], 'SVT' => [2, 2], 'FR' => [2, 2], 'PHILO' => [3, 3], 'ANG' => [2, 2], 'HG' => [2, 2], 'EPS' => [1, 2], 'COND' => [1, 0]],
        'tle-D' => ['MATH' => [5, 5], 'PCT' => [5, 5], 'SVT' => [6, 5], 'FR' => [2, 2], 'PHILO' => [3, 3], 'ANG' => [2, 2], 'HG' => [2, 2], 'EPS' => [1, 2], 'COND' => [1, 0]],
    ];

    /** Niveau d'aptitude moyen des élèves selon la série (orientation sélective en C). */
    public const BONUS_SERIE = [
        'college1' => 0.0, 'college2' => 0.0,
        '2nde-AB' => -0.05, '2nde-C' => 0.7, '2nde-D' => 0.25,
        '1ere-A1' => -0.1, '1ere-A2' => -0.1, '1ere-B' => 0.1, '1ere-C' => 0.75, '1ere-D' => 0.25,
        'tle-A1' => -0.05, 'tle-A2' => -0.05, 'tle-B' => 0.1, 'tle-C' => 0.8, 'tle-D' => 0.3,
    ];

    /**
     * Frais annuels de scolarité (FCFA) par niveau pour l'année active ; l'année précédente est
     * environ 5 % moins chère. Frais de dossier (inscription/réinscription) par cycle.
     */
    public const SCOLARITE = [
        'Sixième' => 165000, 'Cinquième' => 165000, 'Quatrième' => 180000, 'Troisième' => 185000,
        'Seconde' => 205000, 'Première' => 215000, 'Terminale' => 230000,
    ];

    public const FRAIS_DOSSIER = ['college' => 15000, 'lycee' => 20000];

    /**
     * Créneaux horaires : 5 heures le matin (récréation de 10h à 10h15), 3 l'après-midi.
     * [heure_debut, heure_fin, demi-journée]
     */
    public const CRENEAUX = [
        1 => ['07:00:00', '08:00:00', 'matin'],
        2 => ['08:00:00', '09:00:00', 'matin'],
        3 => ['09:00:00', '10:00:00', 'matin'],
        4 => ['10:15:00', '11:15:00', 'matin'],
        5 => ['11:15:00', '12:15:00', 'matin'],
        6 => ['15:00:00', '16:00:00', 'soir'],
        7 => ['16:00:00', '17:00:00', 'soir'],
        8 => ['17:00:00', '18:00:00', 'soir'],
    ];

    /** Créneaux ouverts par jour (mercredi après-midi libre, samedi réservé aux devoirs surveillés). */
    public const JOURS = [
        'lundi' => [1, 2, 3, 4, 5, 6, 7, 8],
        'mardi' => [1, 2, 3, 4, 5, 6, 7, 8],
        'mercredi' => [1, 2, 3, 4, 5],
        'jeudi' => [1, 2, 3, 4, 5, 6, 7, 8],
        'vendredi' => [1, 2, 3, 4, 5, 6, 7, 8],
    ];

    /**
     * Matières de la classe (code => [coefficient, heures]) avec la LV2 résolue.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function programmeDeClasse(string $profil, ?string $lv2): array
    {
        $programme = [];
        foreach (self::PROGRAMMES[$profil] as $code => $valeurs) {
            if ($code === 'LV2') {
                if ($lv2 === null) {
                    continue;
                }
                $code = $lv2;
            }
            $programme[$code] = $valeurs;
        }

        return $programme;
    }

    public static function estLycee(string $niveau): bool
    {
        return in_array($niveau, ['Seconde', 'Première', 'Terminale'], true);
    }
}
