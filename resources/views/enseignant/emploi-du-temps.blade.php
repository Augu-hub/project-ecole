@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Mon emploi du temps</h1>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            @if(!$enseignant)
                <p class="text-muted">Aucune fiche enseignant associée à votre compte.</p>
            @else
                @include('back.pages.emploi-du-temps._grille', ['showClasse' => true])
            @endif
        </div>
    </div>
</div>
@endsection
