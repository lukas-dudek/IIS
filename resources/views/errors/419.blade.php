@extends('layouts.app')

@section('title', 'Relace vypršela')

@section('content')
    <h1 class="h4">Relace vypršela</h1>
    <p>Formulář byl otevřený příliš dlouho nebo relace vypršela. Odešlete ho prosím znovu.</p>
    <a class="btn btn-primary" href="{{ url()->previous() }}">Zpět na formulář</a>
@endsection
