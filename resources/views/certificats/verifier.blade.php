<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vérification de certificat — {{ config('ecole.nom') }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f1f5f9; color: #1a1a2e; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); max-width: 480px; width: 100%; overflow: hidden; }
    .header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 28px; text-align: center; }
    .header h1 { font-size: 16px; letter-spacing: 0.5px; }
    .body { padding: 28px; text-align: center; }
    .icon { font-size: 48px; margin-bottom: 12px; }
    .valide .icon { color: #16a34a; }
    .invalide .icon { color: #dc2626; }
    .titre { font-size: 18px; font-weight: 700; margin-bottom: 16px; }
    .valide .titre { color: #16a34a; }
    .invalide .titre { color: #dc2626; }
    .info-grid { text-align: left; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin-top: 12px; }
    .info-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
    .info-label { color: #6b7280; }
    .info-value { font-weight: 600; }
    .numero { margin-top: 16px; font-family: monospace; font-size: 12px; color: #9ca3af; letter-spacing: 1px; }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>{{ strtoupper(config('ecole.nom')) }}</h1>
    </div>
    <div class="body {{ $certificat ? 'valide' : 'invalide' }}">
        @if($certificat)
            <div class="icon">✓</div>
            <div class="titre">Certificat valide</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Élève</span>
                    <span class="info-value">{{ $certificat->eleve->nom }} {{ $certificat->eleve->prenom }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Classe</span>
                    <span class="info-value">{{ $certificat->classeAnnee->classe->niveau->nom }} {{ $certificat->classeAnnee->classe->suffixe }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Année scolaire</span>
                    <span class="info-value">{{ $certificat->classeAnnee->anneeScolaire->libelle }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Émis le</span>
                    <span class="info-value">{{ $certificat->created_at->format('d/m/Y') }}</span>
                </div>
            </div>
        @else
            <div class="icon">✕</div>
            <div class="titre">Numéro invalide</div>
            <p style="color:#6b7280; font-size:14px;">Aucun certificat ne correspond à ce numéro. Veuillez vérifier la saisie ou contacter l'établissement.</p>
        @endif
        <div class="numero">N° {{ $numero }}</div>
    </div>
</div>
</body>
</html>
