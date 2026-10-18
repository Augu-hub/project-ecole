<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a2e; background: #fff; }

  .header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; padding: 28px; text-align: center; }
  .header h1 { font-size: 20px; font-weight: 700; letter-spacing: 1px; }
  .header p { font-size: 11px; opacity: 0.75; margin-top: 4px; }

  .badge { display: inline-block; background: #4ade80; color: #14532d; padding: 5px 16px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; margin-top: 12px; }

  .body { padding: 36px 44px; }

  .titre { text-align: center; font-size: 22px; font-weight: 700; letter-spacing: 2px; color: #1a1a2e; margin-bottom: 28px; text-transform: uppercase; border-bottom: 2px solid #C8A951; display: inline-block; padding-bottom: 6px; }
  .titre-wrap { text-align: center; margin-bottom: 30px; }

  .corps { font-size: 13px; line-height: 1.9; text-align: justify; margin-bottom: 30px; }
  .corps strong { color: #1a1a2e; }

  .info-grid { display: table; width: 100%; margin: 20px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; }
  .info-row { display: table-row; }
  .info-label { display: table-cell; width: 35%; color: #6b7280; font-size: 11px; padding: 6px 0; }
  .info-value { display: table-cell; font-weight: 600; font-size: 12px; padding: 6px 0; }

  .signature { margin-top: 60px; text-align: right; padding-right: 20px; }
  .signature .lieu-date { font-size: 12px; margin-bottom: 50px; }
  .signature .titre-signataire { font-size: 11px; font-weight: 700; color: #1a1a2e; }

  .footer { margin-top: 40px; padding-top: 16px; border-top: 1px dashed #e5e7eb; text-align: center; color: #9ca3af; font-size: 10px; }
  .ref-box { background: #f1f5f9; border-radius: 6px; padding: 8px 14px; display: inline-block; margin-top: 8px; font-family: monospace; font-size: 11px; color: #475569; letter-spacing: 1px; }
</style>
</head>
<body>

<div class="header">
  <h1>{{ strtoupper($ecole['nom']) }}</h1>
  <p>{{ $ecole['adresse'] }} {{ $ecole['tel'] ? '• '.$ecole['tel'] : '' }}</p>
  <div class="badge">✓ CERTIFICAT DE SCOLARITÉ</div>
</div>

<div class="body">

  <div class="titre-wrap">
    <span class="titre">Certificat de scolarité</span>
  </div>

  <div class="corps">
    Je soussigné(e), {{ $ecole['directeur'] ?: 'le Directeur / la Directrice' }} de l'établissement <strong>{{ $ecole['nom'] }}</strong>,
    certifie que l'élève <strong>{{ strtoupper($certificat->eleve->nom) }} {{ $certificat->eleve->prenom }}</strong>,
    né(e) le {{ optional($certificat->eleve->date_naissance)->format('d/m/Y') ?? '—' }},
    est régulièrement inscrit(e) dans notre établissement en classe de
    <strong>{{ $certificat->classeAnnee->classe->niveau->nom }} {{ $certificat->classeAnnee->classe->suffixe }}</strong>
    au titre de l'année scolaire <strong>{{ $certificat->classeAnnee->anneeScolaire->libelle }}</strong>.
    @if($certificat->motif)
        <br><br>Le présent certificat est délivré à l'intéressé(e) pour servir à : <strong>{{ $certificat->motif }}</strong>.
    @else
        <br><br>Le présent certificat est délivré à l'intéressé(e) pour servir et valoir ce que de droit.
    @endif
  </div>

  <div class="info-grid">
    <div class="info-row">
      <div class="info-label">Matricule</div>
      <div class="info-value">{{ $certificat->eleve->matricule }}</div>
    </div>
    <div class="info-row">
      <div class="info-label">Classe</div>
      <div class="info-value">{{ $certificat->classeAnnee->classe->niveau->nom }} {{ $certificat->classeAnnee->classe->suffixe }}</div>
    </div>
    <div class="info-row">
      <div class="info-label">Année scolaire</div>
      <div class="info-value">{{ $certificat->classeAnnee->anneeScolaire->libelle }}</div>
    </div>
  </div>

  <div class="signature">
    <div class="lieu-date">Fait à {{ $ecole['adresse'] }}, le {{ $certificat->created_at->format('d/m/Y') }}</div>
    <div class="titre-signataire">Le Directeur / La Directrice</div>
  </div>

  <div class="footer">
    <p>Ce certificat est vérifiable en ligne à l'adresse : {{ $urlVerification }}</p>
    <div class="ref-box">N° {{ $certificat->numero_unique }}</div>
    <p style="margin-top: 8px;">Généré le {{ now()->format('d/m/Y à H:i') }}</p>
  </div>

</div>
</body>
</html>
