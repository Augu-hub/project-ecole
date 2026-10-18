<div class="card h-100">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="ri-user-line me-2 text-primary-600" aria-hidden="true"></i>
            {{ __('Profile Information') }}
        </h5>
    </div>
    <div class="card-body">
        <p class="text-secondary-light mb-20">{{ __("Update your account's profile information and email address.") }}</p>

        @if (session('status') === 'profile-updated')
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="status">
                <i class="ri-checkbox-circle-line" aria-hidden="true"></i> {{ __('Saved.') }}
            </div>
        @endif

        <form id="send-verification" method="post" action="{{ route('verification.send') }}">
            @csrf
        </form>

        <form method="post" action="{{ route('profile.update') }}">
            @csrf
            @method('patch')

            <div class="mb-3">
                <label for="name" class="form-label fw-medium">{{ __('Name') }} <span class="text-danger">*</span></label>
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name" name="name"
                       value="{{ old('name', $user->name) }}"
                       required autofocus autocomplete="name">
                @foreach ($errors->get('name') as $message)
                    <div class="invalid-feedback">{{ $message }}</div>
                @endforeach
            </div>

            <div class="mb-3">
                <label for="email" class="form-label fw-medium">{{ __('Email') }} <span class="text-danger">*</span></label>
                <input type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email"
                       value="{{ old('email', $user->email) }}"
                       required autocomplete="username">
                @foreach ($errors->get('email') as $message)
                    <div class="invalid-feedback">{{ $message }}</div>
                @endforeach

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <p class="text-sm mt-2 mb-0">
                        {{ __('Your email address is unverified.') }}
                        <button form="send-verification" class="btn btn-link p-0 align-baseline text-sm">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 mb-0 text-sm text-success-main fw-medium">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                @endif
            </div>

            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </form>
    </div>
</div>
