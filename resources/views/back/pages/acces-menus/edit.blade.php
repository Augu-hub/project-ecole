@extends('back.layouts.master')

@section('title', 'Accès aux menus')

@section('content')
<div class="dashboard-main-body">

    {{-- Breadcrumb --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Accès aux menus</h1>
            <div>
                <a href="{{ route('admin.dashboard') }}"
                   class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/
                    <a href="{{ route('admin.acces-menus.index') }}"
                       class="text-secondary-light hover-text-primary hover-underline">Accès aux menus</a>
                </span>
                <span class="text-secondary-light">/ Régler</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h6 class="text-lg fw-semibold mb-0">
                <i class="ri-toggle-line me-2 text-primary-600"></i>
                {{ $compte->name }} — {{ $compte->role_libelle }}
            </h6>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.acces-menus.update', $compte) }}" method="POST">
                @csrf
                @method('PUT')

                <p class="text-secondary-light mb-16">Décochez les menus que ce compte ne doit pas utiliser.</p>

                {{-- Un interrupteur par rubrique du rôle : coché = menu activé --}}
                <div class="row g-3">
                    @foreach($rubriques as $cle => $libelle)
                        <div class="col-md-4 col-sm-6">
                            <div class="form-check form-switch d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox"
                                       id="menu-{{ $cle }}" name="menus[]" value="{{ $cle }}"
                                       @checked(! in_array($cle, $fermees, true))>
                                <label class="form-check-label fw-medium" for="menu-{{ $cle }}">
                                    {{ $libelle }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex gap-3 mt-24">
                    <button type="submit" class="btn btn-primary-600">
                        <i class="ri-save-line me-2"></i>Enregistrer
                    </button>
                    <a href="{{ route('admin.acces-menus.index') }}"
                       class="btn btn-outline-secondary">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
