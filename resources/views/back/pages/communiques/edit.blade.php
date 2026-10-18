@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h6 class="fw-semibold mb-1">Modifier le communiqué</h6>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.communiques.index') }}">Communiqués</a></li>
                    <li class="breadcrumb-item active">Modifier</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.communiques.update', $communique) }}" method="POST">
                @csrf @method('PUT')

                <div class="mb-20">
                    <label class="form-label fw-semibold">Titre</label>
                    <input type="text" name="titre" class="form-control @error('titre') is-invalid @enderror"
                        value="{{ old('titre', $communique->titre) }}">
                    @error('titre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-20">
                    <label class="form-label fw-semibold">Contenu</label>
                    <textarea name="contenu" class="form-control @error('contenu') is-invalid @enderror"
                        rows="8">{{ old('contenu', $communique->contenu) }}</textarea>
                    @error('contenu') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-20">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Type</label>
                        <select name="type" class="form-select">
                            @foreach(['general' => '📢 Général', 'urgent' => '🚨 Urgent', 'evenement' => '📅 Événement', 'academique' => '📚 Académique'] as $val => $label)
                                <option value="{{ $val }}" {{ old('type', $communique->type) == $val ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Date de publication</label>
                        <input type="date" name="date_publication" class="form-control"
                            value="{{ old('date_publication', $communique->date_publication->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Date d'expiration</label>
                        <input type="date" name="date_expiration" class="form-control"
                            value="{{ old('date_expiration', $communique->date_expiration?->format('Y-m-d')) }}">
                    </div>
                </div>

                <div class="mb-20">
                    <label class="form-label fw-semibold">Classes concernées</label>
                    <div class="row g-2">
                        @foreach($classesAnnees as $ca)
                        <div class="col-md-3 col-sm-4 col-6">
                            <label class="d-flex align-items-center gap-2 p-2 border rounded cursor-pointer
                                {{ in_array($ca->id, old('classe_annee_ids', $selectedIds)) ? 'border-primary bg-primary-subtle' : '' }}">
                                <input type="checkbox" name="classe_annee_ids[]"
                                    value="{{ $ca->id }}"
                                    class="classe-checkbox"
                                    {{ in_array($ca->id, old('classe_annee_ids', $selectedIds)) ? 'checked' : '' }}>
                                <span class="small fw-semibold">
                                    {{ $ca->classe->niveau->nom }}{{ $ca->classe->suffixe }}
                                </span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <a href="{{ route('admin.communiques.index') }}" class="btn btn-light">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.classe-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        this.closest('label').classList.toggle('border-primary', this.checked);
        this.closest('label').classList.toggle('bg-primary-subtle', this.checked);
    });
});
</script>
@endpush
@endsection
