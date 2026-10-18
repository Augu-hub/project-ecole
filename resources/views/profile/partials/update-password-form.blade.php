<div class="card h-100">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="ri-lock-password-line me-2 text-primary-600" aria-hidden="true"></i>
            {{ __('Update Password') }}
        </h5>
    </div>
    <div class="card-body">
        <p class="text-secondary-light mb-20">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>

        @if (session('status') === 'password-updated')
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="status">
                <i class="ri-checkbox-circle-line" aria-hidden="true"></i> {{ __('Saved.') }}
            </div>
        @endif

        {{-- Erreurs dans le sac « updatePassword » (PasswordController::update) --}}
        <form method="post" action="{{ route('password.update') }}">
            @csrf
            @method('put')

            <div class="mb-3">
                <label for="update_password_current_password" class="form-label fw-medium">{{ __('Current Password') }}</label>
                <input type="password"
                       class="form-control {{ $errors->updatePassword->has('current_password') ? 'is-invalid' : '' }}"
                       id="update_password_current_password" name="current_password"
                       autocomplete="current-password">
                @foreach ($errors->updatePassword->get('current_password') as $message)
                    <div class="invalid-feedback">{{ $message }}</div>
                @endforeach
            </div>

            <div class="mb-3">
                <label for="update_password_password" class="form-label fw-medium">{{ __('New Password') }}</label>
                <input type="password"
                       class="form-control {{ $errors->updatePassword->has('password') ? 'is-invalid' : '' }}"
                       id="update_password_password" name="password"
                       autocomplete="new-password">
                @foreach ($errors->updatePassword->get('password') as $message)
                    <div class="invalid-feedback">{{ $message }}</div>
                @endforeach
            </div>

            <div class="mb-3">
                <label for="update_password_password_confirmation" class="form-label fw-medium">{{ __('Confirm Password') }}</label>
                <input type="password"
                       class="form-control {{ $errors->updatePassword->has('password_confirmation') ? 'is-invalid' : '' }}"
                       id="update_password_password_confirmation" name="password_confirmation"
                       autocomplete="new-password">
                @foreach ($errors->updatePassword->get('password_confirmation') as $message)
                    <div class="invalid-feedback">{{ $message }}</div>
                @endforeach
            </div>

            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </form>
    </div>
</div>
