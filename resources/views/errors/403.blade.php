@extends('back.layouts.master')
@section('title', '403 — Accès interdit')
@section('content')

<div class="dashboard-main-body">
    <div class="min-vh-75 d-flex align-items-center justify-content-center py-48">
        <div class="text-center" style="max-width: 560px;">

            {{-- Illustration SVG --}}
            <div class="mb-32 position-relative d-inline-block">
                <svg viewBox="0 0 200 160" xmlns="http://www.w3.org/2000/svg" style="width:260px; height:auto;">
                    <!-- Ombre sol -->>
                    <ellipse cx="100" cy="148" rx="58" ry="8" fill="#fee2e2" />

                    <!-- Coffre-fort corps -->>
                    <rect x="48" y="45" width="104" height="90" rx="12" fill="#f1f5f9" stroke="#e2e8f0" stroke-width="2"/>
                    <!-- Coffre-fort reflet -->>
                    <rect x="55" y="52" width="90" height="76" rx="8" fill="#f8fafc"/>

                    <!-- Roue -->>
                    <circle cx="100" cy="88" r="24" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="2.5"/>
                    <circle cx="100" cy="88" r="16" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
                    <circle cx="100" cy="88" r="5" fill="#94a3b8"/>
                    <!-- Rayons roue -->>
                    <line x1="100" y1="72" x2="100" y2="80" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                    <line x1="100" y1="96" x2="100" y2="104" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                    <line x1="84" y1="88" x2="92" y2="88" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                    <line x1="108" y1="88" x2="116" y2="88" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                    <line x1="88.7" y1="76.7" x2="94.3" y2="82.3" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                    <line x1="105.7" y1="93.7" x2="111.3" y2="99.3" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                    <line x1="88.7" y1="99.3" x2="94.3" y2="93.7" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                    <line x1="105.7" y1="82.3" x2="111.3" y2="76.7" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>

                    <!-- Poignée -->>
                    <rect x="122" y="84" width="18" height="8" rx="4" fill="#94a3b8"/>

                    <!-- Charnières -->>
                    <rect x="48" y="60" width="8" height="10" rx="2" fill="#cbd5e1"/>
                    <rect x="48" y="108" width="8" height="10" rx="2" fill="#cbd5e1"/>

                    <!-- Cadenas sur le coffre -->>
                    <rect x="60" y="110" width="22" height="16" rx="4" fill="#fca5a5" stroke="#f87171" stroke-width="1.5"/>
                    <path d="M65 110 Q65 103 71 103 Q77 103 77 110" fill="none" stroke="#f87171" stroke-width="2.5" stroke-linecap="round"/>
                    <circle cx="71" cy="118" r="3" fill="#ef4444"/>

                    <!-- Bouclier avec croix -->>
                    <path d="M155 20 L155 38 Q155 48 147 52 Q139 48 139 38 L139 20 Z"
                          fill="#fca5a5" stroke="#f87171" stroke-width="1.5"/>
                    <line x1="147" y1="28" x2="147" y2="44" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                    <line x1="140" y1="36" x2="154" y2="36" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>

                    <!-- Étoile avertissement -->>
                    <circle cx="38" cy="35" r="12" fill="#fef3c7" stroke="#fbbf24" stroke-width="1.5"/>
                    <text x="38" y="39.5" text-anchor="middle" font-size="13" fill="#d97706">!</text>

                    <!-- Points déco -->>
                    <circle cx="170" cy="70"  r="3" fill="#fca5a5" opacity=".6"/>
                    <circle cx="30"  cy="110" r="2" fill="#fca5a5" opacity=".6"/>
                    <circle cx="175" cy="120" r="2" fill="#fbbf24" opacity=".5"/>
                </svg>

                {{-- Badge 403 flottant --}}
                <div class="position-absolute"
                     style="top: -12px; right: -12px;
                            background: linear-gradient(135deg, #ef4444, #dc2626);
                            color: white; border-radius: 16px;
                            padding: 6px 16px; font-weight: 800;
                            font-size: 1.1rem; letter-spacing: 1px;
                            box-shadow: 0 4px 16px rgba(239,68,68,.4);">
                    403
                </div>
            </div>

            {{-- Texte --}}
            <h2 class="fw-bold mb-12" style="font-size: 1.75rem; color: #1e293b;">
                Zone interdite ! 🔒
            </h2>
            <p class="text-secondary-light mb-8" style="font-size: 1rem; line-height: 1.7;">
                Vous n'avez pas les droits nécessaires pour accéder à cette section.
                Cette zone est réservée à des profils autorisés.
            </p>
            <p class="text-secondary-light mb-32" style="font-size: 0.875rem;">
                Si vous pensez qu'il s'agit d'une erreur, contactez votre administrateur.
            </p>

            {{-- Message exception si disponible --}}
            {{-- Uniquement nos messages métier (abort(403, '...')), jamais le message anglais par défaut du framework --}}
            @if(isset($exception) && ! in_array($exception->getMessage(), ['', 'This action is unauthorized.', 'Forbidden'], true))
                <div class="alert py-10 px-16 radius-8 mb-24 text-sm d-inline-flex align-items-center gap-2"
                     style="background:#fef2f2; border: 1px solid #fecaca; color:#b91c1c;">
                    <iconify-icon icon="mdi:information-outline"></iconify-icon>
                    {{ $exception->getMessage() }}
                </div>
            @endif

            {{-- Actions --}}
            <div class="d-flex align-items-center justify-content-center gap-12 flex-wrap">
                <a href="{{ auth()->user()?->role === 'enseignant' ? route('enseignant.dashboard') : route('admin.dashboard') }}"
                   class="btn btn-primary d-inline-flex align-items-center gap-2 px-24 py-12 radius-8"
                   style="font-weight: 600;">
                    <iconify-icon icon="mdi:home-outline"></iconify-icon>
                    Retour à l'accueil
                </a>
                <button onclick="history.back()"
                        class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-24 py-12 radius-8"
                        style="font-weight: 600;">
                    <iconify-icon icon="mdi:arrow-left"></iconify-icon>
                    Page précédente
                </button>
            </div>

            {{-- Code erreur discret --}}
            <p class="mt-32 text-secondary-light" style="font-size: 0.75rem; opacity: .6;">
                Erreur <strong>403</strong> — Accès refusé
            </p>

        </div>
    </div>
</div>

@endsection
