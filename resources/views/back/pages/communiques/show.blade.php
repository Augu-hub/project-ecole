@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h6 class="fw-semibold mb-1">{{ $communique->titre }}</h6>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.communiques.index') }}">Communiqués</a></li>
                    <li class="breadcrumb-item active">Détail</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.communiques.edit', $communique) }}" class="btn btn-primary">
                <i class="ri-edit-line me-1"></i> Modifier
            </a>
            <form action="{{ route('admin.communiques.destroy', $communique) }}" method="POST"
                onsubmit="return confirm('Supprimer ?')">
                @csrf @method('DELETE')
                <button class="btn btn-danger"><i class="ri-delete-bin-line me-1"></i> Supprimer</button>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-24">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-20">
                        <span class="badge bg-{{ $communique->couleur_type }}-subtle text-{{ $communique->couleur_type }} fs-6 fw-semibold px-3 py-2">
                            {{ $communique->label_type }}
                        </span>
                        @if(!$communique->actif)
                            <span class="badge bg-secondary-subtle text-secondary">Inactif</span>
                        @endif
                    </div>

                    <h4 class="fw-bold mb-16">{{ $communique->titre }}</h4>

                    <div class="text-body lh-lg">
                        {!! nl2br(e($communique->contenu)) !!}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-24">
                <div class="card-header"><h6 class="mb-0">Informations</h6></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-12">
                        <span class="text-muted">Publication</span>
                        <span class="fw-semibold">{{ $communique->date_publication->format('d/m/Y') }}</span>
                    </div>
                    @if($communique->date_expiration)
                    <div class="d-flex justify-content-between mb-12">
                        <span class="text-muted">Expiration</span>
                        <span class="fw-semibold">{{ $communique->date_expiration->format('d/m/Y') }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between mb-12">
                        <span class="text-muted">Créé par</span>
                        <span class="fw-semibold">{{ $communique->createur->name ?? '-' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Créé le</span>
                        <span class="fw-semibold">{{ $communique->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        Classes concernées
                        <span class="badge bg-primary-subtle text-primary ms-2">
                            {{ $communique->classesAnnees->count() }}
                        </span>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($communique->classesAnnees as $ca)
                            <span class="badge bg-light text-dark border fw-semibold">
                                {{ $ca->classe->niveau->nom }}{{ $ca->classe->suffixe }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
