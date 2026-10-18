@extends('back.layouts.master')

@section('content')
<div class="dashboard-main-body">
    <div class="breadcrumb d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <div>
            <h1 class="fw-semibold mb-4 h6 text-primary-light">Messagerie</h1>
            <div>
                <a href="" class="text-secondary-light hover-text-primary hover-underline">Dashboard</a>
                <span class="text-secondary-light">/ Messagerie</span>
            </div>
        </div>
    </div>

    <div class="card h-100">
        <div class="card-body p-0">
            <div class="row g-0" style="height:75vh;">
                {{-- Colonne gauche : recherche + liste des conversations --}}
                <div class="col-md-4 border-end d-flex flex-column h-100">
                    <div class="p-3 border-bottom flex-shrink-0">
                        <input type="text" id="parent-search-input" class="form-control form-control-sm"
                               placeholder="Rechercher un parent ou un élève...">
                        <div id="parent-search-results" class="list-group mt-2 d-none"
                             style="max-height:260px; overflow-y:auto;"></div>
                    </div>
                    <ul id="conversations-list" class="list-group list-group-flush flex-grow-1" style="overflow-y:auto;">
                        <li class="list-group-item text-center text-secondary-light" id="conversations-empty">
                            Chargement...
                        </li>
                    </ul>
                </div>

                {{-- Colonne droite : fil de la conversation active --}}
                <div class="col-md-8 d-flex flex-column h-100">
                    <div class="p-3 border-bottom flex-shrink-0">
                        <h6 id="conversation-titre" class="mb-0 text-secondary-light">
                            Sélectionnez une conversation
                        </h6>
                    </div>
                    <div id="messages-thread" class="flex-grow-1 p-3" style="overflow-y:auto; min-height:0;"></div>
                    <div class="p-3 border-top flex-shrink-0">
                        <form id="message-form" class="d-flex gap-2">
                            <input type="text" id="message-input" class="form-control" placeholder="Votre message..." disabled autocomplete="off">
                            <button type="submit" class="btn btn-primary" id="message-submit" disabled>
                                <i class="ri-send-plane-2-line"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/messagerie.css') }}">
@endpush

<script>
    window.MESSAGERIE_CONFIG = {
        csrfToken: @json(csrf_token()),
        currentUserId: @json($currentUserId),
        currentUserName: @json($currentUserName),
        reverb: @json($reverbConfig),
        urls: {
            conversations: @json(route('admin.messagerie.conversations')),
            messages: @json(route('admin.messagerie.messages', ['conversation' => '__ID__'])),
            creerConversation: @json(route('admin.messagerie.creer-conversation')),
            envoyerMessage: @json(route('admin.messagerie.envoyer-message', ['conversation' => '__ID__'])),
            marquerLu: @json(route('admin.messagerie.marquer-lu', ['conversation' => '__ID__'])),
            rechercherParent: @json(route('admin.messagerie.rechercher-parent')),
        },
    };
</script>
<script src="{{ asset('assets/js/lib/pusher.min.js') }}"></script>
<script src="{{ asset('assets/js/lib/echo.iife.js') }}"></script>
<script src="{{ asset('assets/js/messagerie.js') }}"></script>
@endsection
