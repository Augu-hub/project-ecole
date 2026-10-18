<?php

return [
    'nom' => env('ECOLE_NOM', 'Complexe Catholique Père Aupiais'),
    'adresse' => env('ECOLE_ADRESSE', 'Cotonou, Bénin'),
    'tel' => env('ECOLE_TEL', ''),
    'directeur' => env('ECOLE_DIRECTEUR', ''),

    // Fuseau de l'établissement, pour l'heure imprimée sur les documents (l'application tourne en UTC)
    'fuseau' => env('ECOLE_FUSEAU', 'Africa/Porto-Novo'),

    // En-tête du bordereau de saisie des notes (fiche vierge PDF), tel qu'imprimé sur le bordereau papier du collège
    'bordereau' => [
        'nom' => env('ECOLE_BORDEREAU_NOM', 'COLLEGE CATHOLIQUE PERE AUPIAIS'),
        'contact' => env('ECOLE_BORDEREAU_CONTACT', '04 BP 962   TEL : 21300529'),
        'ville' => env('ECOLE_BORDEREAU_VILLE', 'COTONOU BENIN'),
    ],
];
