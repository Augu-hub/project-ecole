@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Importer des parents (PhpSpreadsheet)</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Importer parents</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form action="{{ route('admin.parents.import.phpoffice.post') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="file" class="form-label">Fichier Excel (xlsx, xls, csv)</label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" required accept=".xlsx,.xls,.csv">
                    @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">Importer</button>
                <a href="" class="btn btn-secondary">Annuler</a>
            </form>

            <hr class="my-4">

            <h6>Format attendu :</h6>
            <p>Les colonnes doivent être dans cet ordre : <strong>NOM, PRÉNOM, TÉLÉPHONE, WHATSAPP, PROFESSION, ADRESSE</strong> (la première ligne d'en-tête est ignorée).</p>
            <p>Les numéros de téléphone doivent être uniques.</p>
        </div>
    </div>
</div>
@endsection