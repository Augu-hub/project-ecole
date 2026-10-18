<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vérification de certificat — {{ config('ecole.nom') }}</title>
<link rel="stylesheet" href="{{ asset('assets/css/pages/certificat-verifier.css') }}">
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
