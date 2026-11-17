@extends('layouts.app')

@section('title', 'Stránka nenalezena')

@section('content')
    <h1 class="h4">Stránka nenalezena</h1>
    <p>Požadovaná stránka nebo záznam neexistuje.</p>
    <a class="btn btn-primary" href="{{ route('events.index') }}">Zobrazit události</a>
@endsection
