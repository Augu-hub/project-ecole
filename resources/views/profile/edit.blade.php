@extends('back.layouts.master')
@section('title', __('Profile'))

@section('content')
<div class="dashboard-main-body">

    {{-- Fil d'Ariane --}}
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">{{ __('Profile') }}</h1>
            <div>
                <a href="{{ route($user->routeTableauDeBord()) }}"
                   class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ {{ __('Profile') }}</span>
            </div>
        </div>
    </div>

    <div class="row gy-4">
        <div class="col-lg-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="col-lg-6">
            @include('profile.partials.update-password-form')
        </div>

        <div class="col-12">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</div>
@endsection
