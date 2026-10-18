@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">

    {{-- Breadcrumb --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Ajouter un membre du personnel</h1>
            <div>
                <a href="{{ route('admin.dashboard') }}"
                   class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/
                    <a href="{{ route('admin.personnel.index') }}"
                       class="text-secondary-light hover-text-primary hover-underline">Personnel</a>
                </span>
                <span class="text-secondary-light">/ Nouveau</span>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ri-user-add-line me-2 text-primary-600"></i>
                        Nouveau membre du personnel
                    </h5>
                </div>
                <div class="card-body">

                    @if($errors->any())
                        <div class="alert alert-danger mb-3">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('admin.personnel.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label fw-medium">
                                Nom complet <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name"
                                   value="{{ old('name') }}"
                                   placeholder="ex : Marie KOFFI"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email"
                                   value="{{ old('email') }}"
                                   placeholder="marie.koffi@ecole.bj"
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="role" class="form-label fw-medium">
                                Rôle <span class="text-danger">*</span>
                            </label>
                            <select name="role" id="role"
                                    class="form-select @error('role') is-invalid @enderror"
                                    required>
                                <option value="">— Sélectionner un rôle —</option>
                                @foreach($rolesPersonnel as $val => $label)
                                    <option value="{{ $val }}"
                                        {{ old('role') === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info py-2 mb-4" role="alert">
                            <i class="ri-information-line me-1"></i>
                            Le mot de passe par défaut sera <strong>password123</strong>.
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary-600 flex-grow-1">
                                <i class="ri-save-line me-2"></i>Créer le compte
                            </button>
                            <a href="{{ route('admin.personnel.index') }}"
                               class="btn btn-outline-secondary">
                                Annuler
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

</div>
@endsection
