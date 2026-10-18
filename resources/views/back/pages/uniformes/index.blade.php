@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Uniformes — Catalogue</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Uniformes</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-24">
        <div class="card-header">
            <h5>Nouvel article</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.uniformes.store') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <input type="text" name="type" class="form-control" placeholder="Ex: Chemise garçon" value="{{ old('type') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Taille</label>
                    <input type="text" name="taille" class="form-control" placeholder="Ex: M" value="{{ old('taille') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Prix (FCFA)</label>
                    <input type="number" step="100" min="0" name="prix" class="form-control" value="{{ old('prix') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stock initial</label>
                    <input type="number" name="quantite_stock" class="form-control" value="{{ old('quantite_stock', 0) }}" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Ajouter au catalogue</button>
                </div>
            </form>
            @error('type') <div class="text-danger mt-2">{{ $message }}</div> @enderror
        </div>
    </div>

    {{-- Formulaires d'édition vides (un par article), rattachés aux inputs de la table via
         l'attribut form="..." : un <form> ne peut pas envelopper directement des <tr>/<td>. --}}
    @foreach($uniformes as $uniforme)
        <form id="edit-uniforme-{{ $uniforme->id }}" action="{{ route('admin.uniformes.update', $uniforme) }}" method="POST" class="d-none">
            @csrf
            @method('PUT')
        </form>
    @endforeach

    <div class="card h-100">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Taille</th>
                        <th>Prix (FCFA)</th>
                        <th>Stock</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($uniformes as $uniforme)
                    <tr>
                        <td><input form="edit-uniforme-{{ $uniforme->id }}" type="text" name="type" class="form-control form-control-sm" value="{{ $uniforme->type }}" required></td>
                        <td><input form="edit-uniforme-{{ $uniforme->id }}" type="text" name="taille" class="form-control form-control-sm" value="{{ $uniforme->taille }}" required></td>
                        <td><input form="edit-uniforme-{{ $uniforme->id }}" type="number" step="100" min="0" name="prix" class="form-control form-control-sm" value="{{ $uniforme->prix }}" required></td>
                        <td>
                            <input form="edit-uniforme-{{ $uniforme->id }}" type="number" name="quantite_stock" class="form-control form-control-sm" value="{{ $uniforme->quantite_stock }}" required>
                            @if($uniforme->en_rupture)
                                <span class="badge bg-danger mt-1">Rupture de stock</span>
                            @endif
                        </td>
                        <td>
                            <button form="edit-uniforme-{{ $uniforme->id }}" type="submit" class="btn btn-sm btn-outline-primary"><i class="ri-save-line"></i></button>
                            <form action="{{ route('admin.uniformes.destroy', $uniforme) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cet article ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center">Aucun article dans le catalogue.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-secondary-light mt-3" style="font-size:12px;">
        Le stock est informatif : une vente reste possible même si le stock est épuisé ou négatif ; pensez à réapprovisionner (augmenter le stock ci-dessus) quand un article est en rupture.
    </p>
</div>
@endsection
