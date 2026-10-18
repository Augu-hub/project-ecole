<div class="card border border-danger-200">
    <div class="card-header">
        <h5 class="mb-0 text-danger-main">
            <i class="ri-delete-bin-line me-2" aria-hidden="true"></i>
            {{ __('Delete Account') }}
        </h5>
    </div>
    <div class="card-body">
        <p class="text-secondary-light mb-20">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>

        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirm-user-deletion">
            {{ __('Delete Account') }}
        </button>
    </div>
</div>

{{-- Erreurs dans le sac « userDeletion » (ProfileController::destroy) : la fenêtre se rouvre d'elle-même --}}
<div class="modal fade" id="confirm-user-deletion" tabindex="-1" aria-labelledby="confirm-user-deletion-titre" aria-hidden="true"
     @if ($errors->userDeletion->isNotEmpty()) data-ouvrir="1" @endif>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div class="modal-header">
                    <h5 class="modal-title" id="confirm-user-deletion-titre">{{ __('Are you sure you want to delete your account?') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>

                <div class="modal-body">
                    <p class="text-secondary-light">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>

                    <label for="password" class="visually-hidden">{{ __('Password') }}</label>
                    <input type="password"
                           class="form-control {{ $errors->userDeletion->has('password') ? 'is-invalid' : '' }}"
                           id="password" name="password"
                           placeholder="{{ __('Password') }}">
                    @foreach ($errors->userDeletion->get('password') as $message)
                        <div class="invalid-feedback">{{ $message }}</div>
                    @endforeach
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('Delete Account') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var fenetre = document.getElementById('confirm-user-deletion');
    if (fenetre && fenetre.dataset.ouvrir) {
        bootstrap.Modal.getOrCreateInstance(fenetre).show();
    }
});
</script>
@endpush
