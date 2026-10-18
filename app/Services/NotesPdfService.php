<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * 📄 SERVICE — Génération PDF CCPA
 * ✅ 100% PHP via DomPDF (barryvdh/laravel-dompdf)
 * ✅ Fonctionne Windows (Laragon/Herd) + Linux
 * ✅ Zéro Python, Zéro TCPDF
 *
 * Deux méthodes :
 *  - generate()       → fiche de notes d'une évaluation (notes enregistrées, bilan de la classe)
 *  - generateVierge() → bordereau de saisie des notes VIERGE, pour écriture manuelle
 *
 * Les deux documents partagent le même en-tête, celui du bordereau papier du collège, et sont en
 * noir et blanc : ils sont faits pour être imprimés et photocopiés.
 *
 * Installation : composer require barryvdh/laravel-dompdf
 */
class NotesPdfService
{
    /**
     * Colonnes de notes du bordereau papier, dans l'ordre : [intitulé, classe CSS de largeur, nombre de cases].
     * « Devoirs » couvre deux cases par élève (Devoir 1 et Devoir 2).
     */
    protected const COLONNES_BORDEREAU = [
        ['Int. 1', 'c-int', 1],
        ['Int. 2', 'c-int', 1],
        ['Int. 3', 'c-int', 1],
        ['Int. 4', 'c-int', 1],
        ['Int. 5', 'c-int', 1],
        ['Moy.<br>Int.', 'c-moy', 1],
        ['Devoirs', 'c-dev', 2],
        ['Moy.<br>Dv.', 'c-moy', 1],
        ['Compo', 'c-compo', 1],
        ['Moy.<br>Excel', 'c-large', 1],
        ['Moy.<br>Coef.', 'c-large', 1],
    ];

    /** Bordereau : largeur utile du tableau en points (A4 portrait, 595,28 pt, moins deux marges de 8 mm). */
    protected const LARGEUR_TABLEAU = 549.9;

    /** Bordereau : part de la largeur réservée au nom et aux prénoms, en %. */
    protected const PART_NOMS = 37;

    /** Bordereau : taille du texte des lignes d'élèves, en points. */
    protected const TAILLE_LIGNES = 7;

    /** Bordereau : marges intérieures et bordure d'une cellule, en points. */
    protected const MARGE_CELLULE = 5;

    /**
     * Fiche de notes : hauteur que les lignes d'élèves peuvent occuper sur la première page, en points,
     * une fois retirés l'en-tête, le bilan de la classe et les signatures.
     */
    protected const PLACE_LIGNES_FICHE = 522;

    /** Styles de l'en-tête commun aux deux documents (voir enTete()). */
    protected const CSS_ENTETE = <<<'CSS'
  .souligne { text-decoration:underline; }
  .entete { width:100%; border-collapse:collapse; }
  .entete td { padding:0; vertical-align:top; }
  .e-logo { width:46pt; }
  .e-etab { width:220pt; }
  .etab { text-align:center; font-size:9pt; font-weight:bold; white-space:pre-wrap; }
  .edition { margin-top:2pt; font-size:8pt; }
  .e-infos { width:175pt; }
  .infos { border-collapse:collapse; font-size:8.5pt; }
  .infos td { padding:0 0 1pt 0; }
  .infos .lbl { width:82pt; }
  .infos .val { font-weight:bold; }
CSS;

    // ─────────────────────────────────────────────────────────────────
    // FICHE AVEC NOTES REMPLIES
    // ─────────────────────────────────────────────────────────────────

    public function generate(array $data): string
    {
        return $this->makePdf($data, false);
    }

    // ─────────────────────────────────────────────────────────────────
    // FICHE VIERGE — cases à remplir à la main
    // ─────────────────────────────────────────────────────────────────

    public function generateVierge(array $data): string
    {
        return $this->makePdf($data, true);
    }

    // ─────────────────────────────────────────────────────────────────
    // MOTEUR COMMUN
    // ─────────────────────────────────────────────────────────────────

    protected function makePdf(array $data, bool $vierge): string
    {
        $tmpDir = storage_path('app'.DIRECTORY_SEPARATOR.'temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $prefix = $vierge ? 'vierge_' : 'notes_';
        $outputFile = $tmpDir.DIRECTORY_SEPARATOR.$prefix.uniqid().'.pdf';

        // Logo base64 (DomPDF ne lit pas les chemins locaux Windows)
        $logoPath = public_path('assets'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'LOGOCCPA.jpeg');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath))
            : '';

        $stats = $vierge ? null : $this->calcStats($data['notes'] ?? []);
        $html = $vierge
            ? $this->buildHtmlVierge($data, $logoBase64)
            : $this->buildHtml($data, $stats, $logoBase64);

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->getDomPDF()->set_option('defaultFont', 'DejaVu Sans');
        $pdf->getDomPDF()->set_option('isRemoteEnabled', false);
        $pdf->getDomPDF()->set_option('isHtml5ParserEnabled', true);

        if (! $vierge) {
            $this->ajouterPiedDePage($pdf, $data);
        }

        file_put_contents($outputFile, $pdf->output());

        $type = $vierge ? 'VIERGE' : 'REMPLIE';
        Log::info("✅ PDF CCPA $type: $outputFile (".filesize($outputFile).' bytes)');

        return $outputFile;
    }

    /**
     * Pied de chaque page de la fiche de notes : rappel du document à gauche, « Page 1 / 2 » à droite.
     * Le nombre total de pages n'est connu qu'une fois le document mis en page.
     */
    protected function ajouterPiedDePage($pdf, array $data): void
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $police = $metrics->getFont('DejaVu Sans');
        $taille = 6.5;
        $marge = 22.68; // 8 mm, comme les marges de la page
        $y = $canvas->get_height() - 27;

        $rappel = implode(' — ', array_filter([
            config('ecole.bordereau.nom'),
            'Fiche de notes',
            $data['classe'] ?? '',
            $data['matiere'] ?? '',
            $data['type_note'] ?? '',
            $data['periode'] ?? '',
        ]));

        $canvas->page_line($marge, $y - 3, $canvas->get_width() - $marge, $y - 3, [0, 0, 0], 0.5);
        $canvas->page_text($marge, $y, $rappel, $police, $taille, [0, 0, 0]);
        $canvas->page_text(
            $canvas->get_width() - $marge - $metrics->getTextWidth('Page 1 / 1', $police, $taille),
            $y,
            'Page {PAGE_NUM} / {PAGE_COUNT}',
            $police,
            $taille,
            [0, 0, 0]
        );
    }

    /**
     * En-tête commun aux deux documents, repris du bordereau papier du collège : l'établissement et
     * la date d'édition à gauche ; l'année scolaire, la classe et l'effectif à droite.
     */
    protected function enTete(array $data, string $logoBase64): string
    {
        $classe = htmlspecialchars($data['classe'] ?? '', ENT_QUOTES);
        $annee = htmlspecialchars($data['annee_scolaire'] ?? '', ENT_QUOTES);
        $edition = now(config('ecole.fuseau'))->format('d/m/Y H:i:s');
        $nbEleves = count($data['notes'] ?? []);

        $logoImg = $logoBase64
            ? "<img src=\"{$logoBase64}\" style=\"height:38pt;width:auto;\">"
            : '';

        $etabNom = htmlspecialchars(config('ecole.bordereau.nom'), ENT_QUOTES);
        $etabContact = htmlspecialchars(config('ecole.bordereau.contact'), ENT_QUOTES);
        $etabVille = htmlspecialchars(config('ecole.bordereau.ville'), ENT_QUOTES);

        return <<<HTML
<table class="entete"><tr>
  <td class="e-logo">{$logoImg}</td>
  <td class="e-etab">
    <div class="etab">{$etabNom}<br>{$etabContact}<br>{$etabVille}</div>
    <div class="edition"><span class="souligne">Edition du :</span>&nbsp;&nbsp;{$edition}</div>
  </td>
  <td>&nbsp;</td>
  <td class="e-infos">
    <table class="infos">
      <tr><td class="lbl"><span class="souligne">Année Scolaire</span></td><td class="val">{$annee}</td></tr>
      <tr><td class="lbl"><span class="souligne">Classe</span></td><td class="val">{$classe}</td></tr>
      <tr><td class="lbl"><span class="souligne">Effectif</span></td><td class="val">{$nbEleves}</td></tr>
    </table>
  </td>
</tr></table>
HTML;
    }

    // ─────────────────────────────────────────────────────────────────
    // HTML — BORDEREAU DE SAISIE DES NOTES (fiche vierge, écriture manuelle)
    // Reprend l'en-tête et les colonnes du bordereau papier du collège, en A4 portrait : une ligne par élève.
    // ─────────────────────────────────────────────────────────────────

    protected function buildHtmlVierge(array $data, string $logoBase64): string
    {
        $matiere = htmlspecialchars($data['matiere'] ?? '', ENT_QUOTES);
        $periode = htmlspecialchars($data['periode'] ?? '', ENT_QUOTES);
        $enseignant = htmlspecialchars($data['enseignant'] ?? '', ENT_QUOTES);
        $nbEleves = count($data['notes'] ?? []);
        $enTete = $this->enTete($data, $logoBase64);
        $cssEnTete = self::CSS_ENTETE;

        // Sur le bordereau papier, ces mentions sont complétées à la main ; ici elles sont connues.
        $trimestre = match (true) {
            $periode === '' => '<b>Trimestre</b> ....................',
            (bool) preg_match('/mestre/i', $periode) => "<b>{$periode}</b>",
            default => "<b>Période</b>&nbsp;&nbsp;{$periode}",
        };
        $professeur = $enseignant !== '' ? $enseignant : '........................................';

        // La liste occupe la page : lignes hautes pour écrire à la main, resserrées quand la classe est
        // nombreuse (une page jusqu'à 55 élèves) ; au-delà, le tableau continue sur la page suivante.
        // 668 pt de hauteur disponible sous l'en-tête du tableau, 0,5 pt de bordure par ligne.
        $hauteurLigne = max(11.5, min(20, floor(6680 / max($nbEleves, 1) - 5) / 10));

        // Nom et prénoms tiennent chacun sur une ligne, pour des lignes toutes de même hauteur : la colonne
        // du nom prend la largeur du nom le plus long de la classe, les prénoms le reste, et un texte
        // encore trop long est écrit plus petit.
        $metrics = app('dompdf.wrapper')->getDomPDF()->getFontMetrics();
        $police = $metrics->getFont('DejaVu Sans');
        $largeur = fn (string $texte) => $metrics->getTextWidth($texte, $police, self::TAILLE_LIGNES);
        $noms = array_map(fn ($eleve) => mb_strtoupper($eleve['nom'] ?? ''), $data['notes'] ?? []);
        $largeurNoms = self::LARGEUR_TABLEAU * self::PART_NOMS / 100;
        $colonneNom = min($largeurNoms * 0.48, max(40, max([0, ...array_map($largeur, $noms)]) + self::MARGE_CELLULE + 4));
        $colonnePrenoms = $largeurNoms - $colonneNom;
        $pourcentNom = round($colonneNom / self::LARGEUR_TABLEAU * 100, 2);
        $pourcentPrenoms = round(self::PART_NOMS - $pourcentNom, 2);
        $tailleLignes = self::TAILLE_LIGNES;
        $ajuster = function (string $texte, float $colonne) use ($largeur): string {
            $place = $colonne - self::MARGE_CELLULE;
            $besoin = $largeur($texte);
            if ($besoin <= $place) {
                return '';
            }

            // En dessous de 5,5 pt le texte ne serait plus lisible : il passe alors sur deux lignes
            $taille = floor(self::TAILLE_LIGNES * $place / $besoin * 10) / 10;

            return $taille >= 5.5
                ? " style=\"font-size:{$taille}pt\""
                : ' style="font-size:5.5pt;white-space:normal"';
        };

        $enTetesHtml = '';
        $casesHtml = '';
        foreach (self::COLONNES_BORDEREAU as [$intitule, $classeCss, $nbCases]) {
            $enTetesHtml .= $nbCases > 1
                ? "<td colspan=\"{$nbCases}\">{$intitule}</td>"
                : "<td class=\"{$classeCss}\">{$intitule}</td>";
            $casesHtml .= str_repeat("<td class=\"{$classeCss}\"></td>", $nbCases);
        }

        $rowsHtml = '';
        foreach (($data['notes'] ?? []) as $i => $eleve) {
            $nomBrut = mb_strtoupper($eleve['nom'] ?? '');
            $preBrut = $eleve['prenom'] ?? '';
            $nom = htmlspecialchars($nomBrut);
            $pre = htmlspecialchars($preBrut);
            $mat = htmlspecialchars($eleve['matricule'] ?? '');
            $num = $eleve['num'] ?? $i + 1;
            $rowsHtml .= "
            <tr>
                <td class=\"c-num\">{$num}</td>
                <td class=\"c-mat\">{$mat}</td>
                <td class=\"c-nom\"{$ajuster($nomBrut, $colonneNom)}>{$nom}</td>
                <td class=\"c-pre\"{$ajuster($preBrut, $colonnePrenoms)}>{$pre}</td>
                {$casesHtml}
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  /* Pas de remise à zéro « * { margin:0 } » : DomPDF l'appliquerait aussi aux marges de la page */
  @page { margin:8mm; }
  body { font-family:DejaVu Sans,Arial,sans-serif; font-size:7.5pt; color:#000; }
{$cssEnTete}
  .titre { text-align:center; font-size:11pt; font-weight:bold; text-decoration:underline; margin:4pt 0 5pt 0; }
  .ligne { width:100%; border-collapse:collapse; font-size:8.5pt; margin-bottom:3pt; }
  .ligne td { padding:0; }
  .l-trim { width:18%; }
  .l-mat { width:45%; }
  .l-prof { width:37%; }
  .notes { width:100%; border-collapse:collapse; }
  .notes td { border:0.5pt solid #000; }
  .notes thead td { padding:2pt 0.5pt; font-size:6.5pt; font-weight:bold; text-align:center; vertical-align:middle; }
  .notes tbody td { height:{$hauteurLigne}pt; padding:0 2pt; font-size:{$tailleLignes}pt; vertical-align:middle; }
  /* Largeurs pour la page en portrait : 63 % fixés ici, les 37 % restants (PART_NOMS) répartis
     entre le nom et les prénoms selon les noms de la classe. */
  .c-num { width:3%; text-align:center; }
  .c-mat { width:8%; white-space:nowrap; }
  .notes tbody td.c-nom { width:{$pourcentNom}%; border-right:none; white-space:nowrap; }
  .notes tbody td.c-pre { width:{$pourcentPrenoms}%; border-left:none; white-space:nowrap; }
  .c-int { width:4.2%; }
  .c-moy { width:4.3%; }
  .c-dev { width:4.3%; }
  .c-compo { width:5%; }
  .c-large { width:4.4%; }
</style>
</head>
<body>

{$enTete}

<div class="titre">BORDEREAU DE SAISIE DES NOTES</div>

<table class="ligne"><tr>
  <td class="l-trim">{$trimestre}</td>
  <td class="l-mat"><b>Matière</b>&nbsp;&nbsp;{$matiere}</td>
  <td class="l-prof"><b>Professeur</b>&nbsp;&nbsp;{$professeur}</td>
</tr></table>

<table class="notes">
  <thead><tr>
    <td class="c-num">N°</td>
    <td class="c-mat">Matricule</td>
    <td colspan="2">Nom &amp; Prénom(s)</td>
    {$enTetesHtml}
  </tr></thead>
  <tbody>{$rowsHtml}</tbody>
</table>

</body>
</html>
HTML;
    }

    // ─────────────────────────────────────────────────────────────────
    // HTML — FICHE DE NOTES (notes enregistrées d'une évaluation)
    // Même en-tête que le bordereau ; la liste, le bilan de la classe et les signatures tiennent
    // sur une page pour une classe ordinaire, sinon la liste continue sur la page suivante.
    // ─────────────────────────────────────────────────────────────────

    protected function buildHtml(array $data, array $stats, string $logoBase64): string
    {
        $matiere = htmlspecialchars($data['matiere'] ?? '', ENT_QUOTES);
        $periode = htmlspecialchars($data['periode'] ?? '', ENT_QUOTES);
        $typeNote = htmlspecialchars($data['type_note'] ?? '', ENT_QUOTES);
        $enseignant = htmlspecialchars($data['enseignant'] ?? '', ENT_QUOTES);
        $enTete = $this->enTete($data, $logoBase64);
        $cssEnTete = self::CSS_ENTETE;

        // Part de la largeur donnée à l'élève et à l'observation, en % ; place pour le texte, en points
        [$partEleve, $partObservation] = [38, 24];
        [$tailleEleve, $tailleObservation] = [8, 7];
        $metrics = app('dompdf.wrapper')->getDomPDF()->getFontMetrics();
        $police = $metrics->getFont('DejaVu Sans');
        $placeEleve = self::LARGEUR_TABLEAU * $partEleve / 100 - 6.5;
        $placeObservation = self::LARGEUR_TABLEAU * $partObservation / 100 - 6.5;

        $rowsHtml = '';
        $besoins = [];
        foreach (($data['notes'] ?? []) as $i => $eleve) {
            $note = $eleve['note'] ?? null;
            $num = $eleve['num'] ?? $i + 1;
            $mat = htmlspecialchars($eleve['matricule'] ?? '');
            $nomBrut = trim(mb_strtoupper($eleve['nom'] ?? '').' '.($eleve['prenom'] ?? ''));
            $nom = htmlspecialchars($nomBrut);
            $valeur = $this->aUneNote($note) ? $this->formaterNote($note) : '&mdash;';
            $appreciation = $this->getMention($note);
            $observationBrute = trim((string) ($eleve['commentaire'] ?? ''));
            $observation = htmlspecialchars($observationBrute);

            // Nom et prénoms sur une seule ligne : trop longs pour la colonne, ils sont écrits plus
            // petit, sans descendre sous 6,5 pt (ils passent alors sur deux lignes).
            $largeurNom = $metrics->getTextWidth($nomBrut, $police, $tailleEleve);
            $taille = $largeurNom <= $placeEleve
                ? $tailleEleve
                : max(6.5, floor($tailleEleve * $placeEleve / $largeurNom * 10) / 10);
            $styleNom = $taille < $tailleEleve ? " style=\"font-size:{$taille}pt\"" : '';

            // Hauteur dont la ligne a besoin si son texte passe sur plusieurs lignes (10 % de marge :
            // la coupure se fait entre deux mots)
            $lignesNom = ceil($largeurNom * $taille / $tailleEleve * 1.1 / $placeEleve);
            $lignesObservation = ceil($metrics->getTextWidth($observationBrute, $police, $tailleObservation) * 1.1 / $placeObservation);
            $besoins[] = max($lignesNom * $taille, $lignesObservation * $tailleObservation) + 1.5;

            $rowsHtml .= "
            <tr>
                <td class=\"c-num\">{$num}</td>
                <td class=\"c-mat\">{$mat}</td>
                <td class=\"c-eleve\"{$styleNom}>{$nom}</td>
                <td class=\"c-note\">{$valeur}</td>
                <td class=\"c-app\">{$appreciation}</td>
                <td class=\"c-obs\">{$observation}</td>
            </tr>";
        }

        // Lignes hautes quand la classe est petite, resserrées quand elle est nombreuse pour que la
        // liste, le bilan et les signatures tiennent sur une page (jusqu'à 46 élèves environ). DomPDF
        // ne descend pas sous 1,28 fois la taille du texte : en dessous de 10,3 pt, la liste continue
        // sur une deuxième page, avec des lignes aérées.
        $occupe = fn (float $hauteur) => array_sum(array_map(fn ($besoin) => max($besoin, $hauteur) + 0.5, $besoins));
        $hauteurLigne = 17.0;
        while ($hauteurLigne >= 10.3 && $occupe($hauteurLigne) > self::PLACE_LIGNES_FICHE) {
            $hauteurLigne = round($hauteurLigne - 0.1, 1);
        }
        if ($hauteurLigne < 10.3) {
            $hauteurLigne = 14.0;
        }
        // La note est écrite un peu plus grand quand la hauteur des lignes le permet
        $tailleNote = $hauteurLigne >= 11.6 ? 9 : 8;

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  /* Pas de remise à zéro « * { margin:0 } » : DomPDF l'appliquerait aussi aux marges de la page.
     La marge du bas laisse la place au pied de page (voir ajouterPiedDePage()). */
  @page { margin:8mm 8mm 13mm 8mm; }
  body { font-family:DejaVu Sans,Arial,sans-serif; font-size:8pt; color:#000; }
{$cssEnTete}
  .titre { text-align:center; font-size:12pt; font-weight:bold; text-decoration:underline; margin:6pt 0 7pt 0; }
  .cadre { width:100%; border-collapse:collapse; }
  .cadre td { border:0.5pt solid #000; padding:2pt 5pt 3pt 5pt; vertical-align:top; }
  .cadre .lbl { font-size:6.5pt; color:#444; }
  .cadre .val { font-size:9pt; font-weight:bold; }
  .k-periode { width:17%; }
  .k-matiere { width:33%; }
  .k-eval { width:23%; }
  .k-prof { width:27%; }
  .notes { width:100%; border-collapse:collapse; margin-top:6pt; }
  .notes td { border:0.5pt solid #000; }
  .notes thead td { padding:3pt 2pt; font-size:7.5pt; font-weight:bold; text-align:center; vertical-align:middle; background:#e6e6e6; }
  /* « line-height:1 » : sans cela DomPDF donne à chaque ligne de texte 1,5 fois sa taille et les
     lignes d'élèves ne peuvent plus être resserrées */
  .notes tbody td { height:{$hauteurLigne}pt; padding:0 3pt; font-size:{$tailleEleve}pt; line-height:1; vertical-align:middle; }
  .c-num { width:4%; }
  .c-mat { width:10%; }
  .c-eleve { width:{$partEleve}%; }
  .c-note { width:10%; }
  .c-app { width:14%; }
  .c-obs { width:{$partObservation}%; }
  .notes tbody td.c-num { text-align:center; }
  .notes tbody td.c-mat { white-space:nowrap; }
  .notes tbody td.c-note { text-align:center; font-size:{$tailleNote}pt; font-weight:bold; }
  .notes tbody td.c-obs { font-size:{$tailleObservation}pt; }
  /* Le bilan et les signatures ne sont jamais séparés par un saut de page */
  .bilan { margin-top:8pt; page-break-inside:avoid; }
  .bilan .cadre td { width:20%; text-align:center; }
  .signatures { width:100%; border-collapse:collapse; margin-top:14pt; }
  .signatures td { width:50%; padding:0; text-align:center; vertical-align:top; font-size:8.5pt; }
  .signatures .qui { font-weight:bold; text-decoration:underline; }
  .signatures .nom { margin-top:34pt; font-size:8pt; }
</style>
</head>
<body>

{$enTete}

<div class="titre">FICHE DE NOTES</div>

<table class="cadre"><tr>
  <td class="k-periode"><div class="lbl">Période</div><div class="val">{$periode}</div></td>
  <td class="k-matiere"><div class="lbl">Matière</div><div class="val">{$matiere}</div></td>
  <td class="k-eval"><div class="lbl">Évaluation</div><div class="val">{$typeNote}</div></td>
  <td class="k-prof"><div class="lbl">Professeur</div><div class="val">{$enseignant}&nbsp;</div></td>
</tr></table>

<table class="notes">
  <thead><tr>
    <td class="c-num">N°</td>
    <td class="c-mat">Matricule</td>
    <td class="c-eleve">Nom &amp; Prénom(s)</td>
    <td class="c-note">Note / 20</td>
    <td class="c-app">Appréciation</td>
    <td class="c-obs">Observation</td>
  </tr></thead>
  <tbody>{$rowsHtml}</tbody>
</table>

<div class="bilan">
  <table class="cadre"><tr>
    <td><div class="lbl">Élèves notés</div><div class="val">{$stats['effectif']}</div></td>
    <td><div class="lbl">Moyenne de la classe</div><div class="val">{$stats['moyenne']}</div></td>
    <td><div class="lbl">Plus forte note</div><div class="val">{$stats['max']}</div></td>
    <td><div class="lbl">Plus faible note</div><div class="val">{$stats['min']}</div></td>
    <td><div class="lbl">Notes de 10 et plus</div><div class="val">{$stats['taux']}</div></td>
  </tr></table>

  <table class="signatures"><tr>
    <td><span class="qui">Le Professeur</span><div class="nom">{$enseignant}</div></td>
    <td><span class="qui">Le Censeur</span></td>
  </tr></table>
</div>

</body>
</html>
HTML;
    }

    // ─────────────────────────────────────────────────────────────────
    // BILAN DE LA CLASSE
    // ─────────────────────────────────────────────────────────────────

    protected function calcStats(array $notes): array
    {
        $vals = array_map(
            fn ($n) => (float) $n['note'],
            array_filter($notes, fn ($n) => $this->aUneNote($n['note'] ?? null))
        );
        $eff = count($vals);
        $reussites = count(array_filter($vals, fn ($v) => $v >= 10));

        return [
            'effectif' => $eff.' / '.count($notes),
            'moyenne' => $eff ? $this->formaterNote(array_sum($vals) / $eff) : '&mdash;',
            'max' => $eff ? $this->formaterNote(max($vals)) : '&mdash;',
            'min' => $eff ? $this->formaterNote(min($vals)) : '&mdash;',
            'taux' => $eff ? $reussites.' ('.round($reussites / $eff * 100).' %)' : '&mdash;',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // NOTE ET APPRÉCIATION
    // ─────────────────────────────────────────────────────────────────

    /** Un élève absent ou non évalué n'a pas de note : la case reste vide à la saisie. */
    protected function aUneNote($note): bool
    {
        return $note !== null && $note !== '';
    }

    /** Note telle qu'on l'écrit sur un document : deux chiffres, une virgule, deux décimales (« 08,50 »). */
    protected function formaterNote($note): string
    {
        return str_replace('.', ',', sprintf('%05.2f', (float) $note));
    }

    protected function getMention($note): string
    {
        if (! $this->aUneNote($note)) {
            return 'Non noté';
        }

        $n = (float) $note;

        return match (true) {
            $n >= 18 => 'Excellent',
            $n >= 16 => 'Très bien',
            $n >= 14 => 'Bien',
            $n >= 12 => 'Assez bien',
            $n >= 10 => 'Passable',
            default => 'Insuffisant',
        };
    }
}
