@extends('errors::minimal')

@section('title', 'Trop de tentatives')
@section('code', '429')
@section('message', 'Vous avez effectué trop de tentatives en peu de temps. Patientez une minute avant de réessayer.')
