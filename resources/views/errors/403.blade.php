@extends('errors::minimal')

@section('title', 'Accès interdit')
@section('code', '403')
@section('message', 'Vous n\'avez pas les droits nécessaires pour accéder à cette page. Si vous pensez qu\'il s\'agit d\'une erreur, contactez l\'administration du collège.')

{{-- Uniquement nos messages métier (abort(403, '...')), jamais le message anglais par défaut du framework --}}
@if (isset($exception) && ! in_array($exception->getMessage(), ['', 'This action is unauthorized.', 'Forbidden'], true))
    @section('detail', $exception->getMessage())
@endif
