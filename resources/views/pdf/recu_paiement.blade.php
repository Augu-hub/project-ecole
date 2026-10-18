<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a2e; background: #fff; }

  .header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; padding: 24px; text-align: center; }
  .header h1 { font-size: 22px; font-weight: 700; letter-spacing: 1px; }
  .header p { font-size: 11px; opacity: 0.7; margin-top: 4px; }

  .badge { display: inline-block; background: #4ade80; color: #14532d; padding: 4px 14px; border-radius: 20px; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; margin-top: 10px; }

  .body { padding: 24px; }

  .section-title { font-size: 10px; font-weight: 700; color: #6b7280; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #f3f4f6; }

  .info-grid { display: table; width: 100%; margin-bottom: 20px; }
  .info-row { display: table-row; }
  .info-label { display: table-cell; width: 40%; color: #6b7280; font-size: 11px; padding: 5px 0; }
  .info-value { display: table-cell; font-weight: 600; font-size: 11px; padding: 5px 0; }

  .amount-box { background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 10px; padding: 16px; text-align: center; margin: 20px 0; }
  .amount-label { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; }
  .amount-value { font-size: 32px; font-weight: 700; color: #1a1a2e; margin-top: 4px; }
  .amount-currency { font-size: 14px; color: #6b7280; }

  .tranches-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 11px; }
  .tranches-table th { background: #f8fafc; padding: 8px 10px; text-align: left; font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
  .tranches-table td { padding: 9px 10px; border-bottom: 1px solid #f3f4f6; }
  .badge-soldee { background: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 700; }
  .badge-partiel { background: #fef9c3; color: #854d0e; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 700; }

  .footer { margin-top: 24px; padding-top: 16px; border-top: 1px dashed #e5e7eb; text-align: center; color: #9ca3af; font-size: 10px; }
  .ref-box { background: #f1f5f9; border-radius: 6px; padding: 8px 14px; display: inline-block; margin-top: 8px; font-family: monospace; font-size: 11px; color: #475569; letter-spacing: 1px; }
</style>
</head>
<body>

<div class="header">
  <h1>{{ strtoupper($ecole['nom']) }}</h1>
  <p>{{ $ecole['adresse'] }} {{ $ecole['tel'] ? '• ' . $ecole['tel'] : '' }}</p>
  <div class="badge">✓ REÇU DE PAIEMENT</div>
</div>

<div class="body">

  {{-- Infos élève --}}
  <div class="section-title">Informations élève</div>
  <div class="info-grid">
    <div class="info-row">
      <div class="info-label">Élève</div>
      <div class="info-value">{{ $paiement->eleve->nom }} {{ $paiement->eleve->prenom }}</div>
    </div>
    <div class="info-row">
      <div class="info-label">Matricule</div>
      <div class="info-value">{{ $paiement->eleve->matricule }}</div>
    </div>
    <div class="info-row">
      <div class="info-label">Classe</div>
      <div class="info-value">{{ $paiement->eleve->classeAnnee->classe->niveau->nom ?? '-' }} {{ $paiement->eleve->classeAnnee->classe->suffixe ?? '' }}</div>
    </div>
  </div>

  {{-- Montant --}}
  <div class="amount-box">
    <div class="amount-label">Montant payé</div>
    <div class="amount-value">{{ number_format($paiement->montant, 0, ',', ' ') }} <span class="amount-currency">XOF</span></div>
  </div>

  {{-- Infos paiement --}}
  <div class="section-title">Détails du paiement</div>
  <div class="info-grid">
    <div class="info-row">
      <div class="info-label">Date</div>
      <div class="info-value">{{ \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</div>
    </div>
    <div class="info-row">
      <div class="info-label">Mode</div>
      <div class="info-value">{{ ucfirst(str_replace('_', ' ', $paiement->mode_paiement)) }}</div>
    </div>
    <div class="info-row">
      <div class="info-label">Tranche</div>
      <div class="info-value">{{ $paiement->tranche?->libelle ?? 'Non spécifiée' }}</div>
    </div>
    @if($paiement->reference)
    <div class="info-row">
      <div class="info-label">Référence</div>
      <div class="info-value">{{ $paiement->reference }}</div>
    </div>
    @endif
  </div>

  <div class="footer">
    <p>Ce reçu est généré automatiquement et fait foi de paiement</p>
    <div class="ref-box">N° {{ str_pad($paiement->id, 8, '0', STR_PAD_LEFT) }}</div>
    <p style="margin-top: 8px;">Généré le {{ now()->format('d/m/Y à H:i') }}</p>
  </div>

</div>
</body>
</html>
