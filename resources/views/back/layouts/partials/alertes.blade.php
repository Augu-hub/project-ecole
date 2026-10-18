{{-- ========================================================================
     Messages à l'utilisateur, communs à toutes les pages du back-office :
     succès, erreur, avertissement, information et erreurs de formulaire.
     Un message que la page affiche déjà elle-même n'est pas répété.
     ======================================================================== --}}
@php
    $dejaAffiche = fn (string $message) => str_contains($contenuPage ?? '', e($message));

    $alertes = collect([
        ['success', 'ri-checkbox-circle-line', session('success')],
        ['danger', 'ri-error-warning-line', session('error')],
        ['warning', 'ri-alert-line', session('warning')],
        ['info', 'ri-information-line', session('info')],
    ])->filter(fn ($alerte) => is_string($alerte[2]) && $alerte[2] !== '' && ! $dejaAffiche($alerte[2]));

    $erreursFormulaire = isset($errors) && $errors->any() && ! $dejaAffiche($errors->first())
        ? $errors->all()
        : [];
@endphp

@if($alertes->isNotEmpty() || $erreursFormulaire)
  <div class="px-24 pt-24" id="messages-page">
    @foreach($alertes as [$type, $icone, $message])
      <div class="alert alert-{{ $type }} alert-dismissible fade show d-flex align-items-start gap-2 mb-12" role="alert">
        <i class="{{ $icone }} text-lg" aria-hidden="true"></i>
        <div>{{ $message }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
      </div>
    @endforeach

    @if($erreursFormulaire)
      <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2 mb-12" role="alert">
        <i class="ri-error-warning-line text-lg" aria-hidden="true"></i>
        <div>
          <strong>Le formulaire n'a pas pu être envoyé. Merci de vérifier :</strong>
          <ul class="mb-0 ps-3">
            @foreach($erreursFormulaire as $erreur)
              <li>{{ $erreur }}</li>
            @endforeach
          </ul>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
      </div>
    @endif
  </div>
@endif
