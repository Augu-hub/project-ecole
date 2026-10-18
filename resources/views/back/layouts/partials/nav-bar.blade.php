<div class="navbar-header shadow-1">
  <div class="row align-items-center justify-content-between">

    {{-- ── Gauche : toggle + recherche ──────────────────────────── --}}
    <div class="col-auto">
      <div class="d-flex flex-wrap align-items-center gap-4">
        <button type="button" class="sidebar-mobile-toggle" aria-label="Ouvrir le menu">
          <iconify-icon icon="heroicons:bars-3-solid" class="icon"></iconify-icon>
        </button>
        <form class="navbar-search">
          <input type="text" class="bg-transparent" name="search" placeholder="Rechercher…">
          <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
        </form>
      </div>
    </div>

    {{-- ── Droite : dark mode, notifications, profil ─────────────── --}}
    <div class="col-auto">
      <div class="d-flex flex-wrap align-items-center gap-3">

        {{-- Thème clair / sombre --}}
        <button type="button" data-theme-toggle
                class="w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center"
                aria-label="Basculer thème clair/sombre">
        </button>

        {{-- ── Notifications ──────────────────────────────────────── --}}
        <div class="dropdown">
          <button class="has-indicator w-40-px h-40-px bg-neutral-200 rounded-circle
                         d-flex justify-content-center align-items-center position-relative"
                  type="button" data-bs-toggle="dropdown" aria-label="Notifications">
            <iconify-icon icon="iconoir:bell" class="text-primary-light text-xl"></iconify-icon>
            @php $nbNotifs = 0; /* À brancher sur votre modèle Notification */ @endphp
            @if($nbNotifs > 0)
              <span class="w-8-px h-8-px bg-danger-600 position-absolute end-0 top-0 rounded-circle mt-2 me-2"></span>
            @endif
          </button>

          <div class="dropdown-menu to-top dropdown-menu-lg p-0">
            <div class="m-16 py-12 px-16 radius-8 bg-primary-50 mb-16
                        d-flex align-items-center justify-content-between gap-2">
              <h6 class="text-lg text-primary-light fw-semibold mb-0">Notifications</h6>
              <span class="text-primary-600 fw-semibold text-lg w-40-px h-40-px rounded-circle
                           bg-base d-flex justify-content-center align-items-center">
                {{ $nbNotifs }}
              </span>
            </div>

            <div class="max-h-400-px overflow-y-auto scroll-sm pe-4">
              @if($nbNotifs === 0)
                <div class="px-24 py-24 text-center text-secondary-light">
                  <iconify-icon icon="iconoir:bell-off" class="text-xxl d-block mb-8"></iconify-icon>
                  <span class="text-sm">Aucune nouvelle notification</span>
                </div>
              @endif
              {{-- @foreach($notifications as $notif) ... @endforeach --}}
            </div>
          </div>
        </div>
        {{-- /Notifications --}}

        {{-- ── Profil utilisateur ─────────────────────────────────── --}}
        @php
          $authUser  = Auth::user();
          $photoPath = $authUser->photo ?? null;
          $initiales = strtoupper(mb_substr($authUser->name ?? 'U', 0, 1))
                     . strtoupper(mb_substr(strstr($authUser->name ?? ' ', ' '), 1, 1));
        @endphp

        <div class="dropdown">
          <button class="d-flex align-items-center gap-2 bg-transparent border-0 p-0"
                  type="button" data-bs-toggle="dropdown" aria-label="Menu utilisateur">

            @if($photoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($photoPath))
              <img src="{{ \Illuminate\Support\Facades\Storage::url($photoPath) }}"
                   alt="{{ $authUser->name }}"
                   class="w-40-px h-40-px rounded-circle object-fit-cover">
            @else
              <span class="w-40-px h-40-px rounded-circle d-flex align-items-center justify-content-center
                           fw-bold text-white flex-shrink-0"
                    style="background:linear-gradient(135deg,#003366,#0066cc); font-size:14px;">
                {{ $initiales ?: 'U' }}
              </span>
            @endif

            <span class="d-none d-md-block text-start">
              <span class="d-block text-sm fw-semibold text-primary-light lh-1 mb-1">
                {{ $authUser->name ?? 'Utilisateur' }}
              </span>
              <span class="d-block text-xs text-secondary-light">
                {{ $authUser->role_libelle ?? 'Utilisateur' }}
              </span>
            </span>
            <iconify-icon icon="ri:arrow-down-s-line" class="text-secondary-light ms-1"></iconify-icon>
          </button>

          <ul class="dropdown-menu dropdown-menu-end border p-12" style="min-width:200px;">
            <li class="px-12 py-8 mb-8 border-bottom">
              <span class="d-block text-sm fw-semibold text-primary-light">
                {{ $authUser->name ?? 'Utilisateur' }}
              </span>
              <span class="d-block text-xs text-secondary-light">
                {{ $authUser->email ?? '' }}
              </span>
            </li>
            <li>
              <a href="{{ route('profile.edit') }}"
                 class="dropdown-item rounded text-secondary-light bg-hover-neutral-200
                        text-hover-neutral-900 d-flex align-items-center gap-2 py-6">
                <i class="ri-user-3-line"></i> Mon profil
              </a>
            </li>
            <li><hr class="dropdown-divider my-6"></li>
            <li>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="dropdown-item rounded text-danger bg-hover-neutral-200
                               d-flex align-items-center gap-2 py-6 w-100 border-0 bg-transparent">
                  <i class="ri-shut-down-line"></i> Déconnexion
                </button>
              </form>
            </li>
          </ul>
        </div>
        {{-- /Profil --}}

      </div>
    </div>
    {{-- /Droite --}}

  </div>
</div>