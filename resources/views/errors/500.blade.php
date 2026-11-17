@extends('layouts.app')

@section('title', 'Chyba serveru')

@section('content')
    <h1 class="h4">Chyba serveru</h1>
    <p>Operaci se nepodařilo dokončit. Zkuste to prosím později.</p>
    <a class="btn btn-primary" href="{{ route('home') }}">Zpět na úvod</a>
@endsection
