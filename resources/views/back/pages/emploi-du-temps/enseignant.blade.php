@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">
                Emploi du temps - {{ $enseignant->nom }} {{ $enseignant->prenom }}
            </h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Emploi du temps enseignant</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            @include('back.pages.emploi-du-temps._grille', ['showClasse' => true])
        </div>
    </div>
</div>
@endsection
