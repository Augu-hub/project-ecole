@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h6 class="fw-semibold mb-1">Nouveau communiqué</h6>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.communiques.index') }}">Communiqués</a></li>
                    <li class="breadcrumb-item active">Nouveau</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.communiques.store') }}" method="POST">
                        @csrf

                        <div class="mb-20">
                            <label class="form-label fw-semibold">Titre <span class="text-danger">*</span></label>
                            <input type="text" name="titre" class="form-control @error('titre') is-invalid @enderror"
                                value="{{ old('titre') }}" placeholder="Titre du communiqué">
                            @error('titre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-20">
                            <label class="form-label fw-semibold">Contenu <span class="text-danger">*</span></label>
                            <textarea name="contenu" id="contenu"
                                class="form-control @error('contenu') is-invalid @enderror"
                                rows="8">{{ old('contenu') }}</textarea>
                            @error('contenu') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row mb-20">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                                <select name="type" class="form-select @error('type') is-invalid @enderror">
                                    <option value="general" {{ old('type') == 'general' ? 'selected' : '' }}>📢 Général</option>
                                    <option value="urgent" {{ old('type') == 'urgent' ? 'selected' : '' }}>🚨 Urgent</option>
                                    <option value="evenement" {{ old('type') == 'evenement' ? 'selected' : '' }}>📅 Événement</option>
                                    <option value="academique" {{ old('type') == 'academique' ? 'selected' : '' }}>📚 Académique</option>
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date de publication <span class="text-danger">*</span></label>
                                <input type="date" name="date_publication"
                                    class="form-control @error('date_publication') is-invalid @enderror"
                                    value="{{ old('date_publication', now()->format('Y-m-d')) }}">
                                @error('date_publication') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date d'expiration</label>
                                <input type="date" name="date_expiration"
                                    class="form-control @error('date_expiration') is-invalid @enderror"
                                    value="{{ old('date_expiration') }}">
                                @error('date_expiration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-20">
                            <label class="form-label fw-semibold">
                                Classes concernées <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex gap-2 mb-12">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAll">
                                    Tout sélectionner
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">
                                    Tout désélectionner
                                </button>
                            </div>
                            @error('classe_annee_ids')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror
                            <div class="row g-2">
                                @foreach($classesAnnees as $ca)
                                <div class="col-md-3 col-sm-4 col-6">
                                    <div class="form-check border rounded p-2 classe-item
                                        {{ in_array($ca->id, old('classe_annee_ids', [])) ? 'border-primary bg-primary-subtle' : 'border-light-2' }}">
                                        <input
                                            class="form-check-input classe-checkbox"
                                            type="checkbox"
                                            name="classe_annee_ids[]"
                                            value="{{ $ca->id }}"
                                            id="classe_{{ $ca->id }}"
                                            {{ in_array($ca->id, old('classe_annee_ids', [])) ? 'checked' : '' }}>
                                        <label class="form-check-label w-100 cursor-pointer" for="classe_{{ $ca->id }}">
                                            <span class="fw-semibold small d-block">
                                                {{ $ca->classe->niveau->nom }}{{ $ca->classe->suffixe }}
                                            </span>
                                            <span class="text-muted" style="font-size:10px">
                                                {{ $ca->anneeScolaire->libelle ?? '' }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-send-plane-line me-1"></i> Publier et notifier
                            </button>
                            <a href="{{ route('admin.communiques.index') }}" class="btn btn-light">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Aperçu --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Aperçu notification</h6>
                </div>
                <div class="card-body">
                    <div class="border rounded p-3 bg-light">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="w-8 h-8 bg-primary rounded-circle d-flex align-items-center justify-content-center">
                                <i class="ri-school-line text-white" style="font-size:14px"></i>
                            </div>
                            <span class="fw-semibold small">SchoolLink</span>
                            <span class="text-muted ms-auto" style="font-size:11px">maintenant</span>
                        </div>
                        <div id="preview-title" class="fw-semibold small mb-1">📢 Titre du communiqué</div>
                        <div id="preview-body" class="text-muted" style="font-size:12px">
                            Aperçu du contenu...
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-warning-subtle border border-warning rounded">
                        <div class="d-flex gap-2">
                            <i class="ri-notification-3-line text-warning mt-1"></i>
                            <div class="small">
                                Une notification push sera envoyée à tous les parents
                                des classes sélectionnées à la publication.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const typeEmojis = {
    urgent: '🚨', evenement: '📅', academique: '📚', general: '📢'
};

document.querySelector('[name="titre"]').addEventListener('input', function() {
    const emoji = typeEmojis[document.querySelector('[name="type"]').value] || '📢';
    document.getElementById('preview-title').textContent = emoji + ' ' + (this.value || 'Titre du communiqué');
});

document.querySelector('[name="type"]').addEventListener('change', function() {
    const titleEl = document.getElementById('preview-title');
    const currentTitle = document.querySelector('[name="titre"]').value;
    titleEl.textContent = (typeEmojis[this.value] || '📢') + ' ' + (currentTitle || 'Titre du communiqué');
});

document.querySelector('[name="contenu"]').addEventListener('input', function() {
    const text = this.value.replace(/<[^>]*>/g, '').substring(0, 100);
    document.getElementById('preview-body').textContent = text || 'Aperçu du contenu...';
});

// Select/deselect all
document.getElementById('selectAll').addEventListener('click', function () {
    document.querySelectorAll('.classe-checkbox').forEach(cb => {
        cb.checked = true;
        cb.closest('.classe-item').classList.add('border-primary', 'bg-primary-subtle');
        cb.closest('.classe-item').classList.remove('border-light-2');
    });
});

document.getElementById('deselectAll').addEventListener('click', function () {
    document.querySelectorAll('.classe-checkbox').forEach(cb => {
        cb.checked = false;
        cb.closest('.classe-item').classList.remove('border-primary', 'bg-primary-subtle');
        cb.closest('.classe-item').classList.add('border-light-2');
    });
});

document.querySelectorAll('.classe-checkbox').forEach(cb => {
    cb.addEventListener('change', function () {
        const item = this.closest('.classe-item');
        if (this.checked) {
            item.classList.add('border-primary', 'bg-primary-subtle');
            item.classList.remove('border-light-2');
        } else {
            item.classList.remove('border-primary', 'bg-primary-subtle');
            item.classList.add('border-light-2');
        }
    });
});
</script>
@endpush
@endsection
