<?php

namespace App\Services;

use App\Models\CertificatScolarite;
use Barryvdh\DomPDF\Facade\Pdf;

class CertificatScolaritePdfService
{
    public function download(CertificatScolarite $certificat)
    {
        $certificat->load(['eleve', 'classeAnnee.classe.niveau', 'classeAnnee.anneeScolaire', 'generePar']);

        $pdf = Pdf::loadView('pdf.certificat_scolarite', [
            'certificat' => $certificat,
            'ecole' => config('ecole'),
            'urlVerification' => route('certificats.verifier', $certificat->numero_unique),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("certificat_scolarite_{$certificat->numero_unique}.pdf");
    }
}
