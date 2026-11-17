@extends('layouts.app')

@section('title', 'Přístup zamítnut')

@section('content')
    <h1 class="h4">Přístup zamítnut</h1>
    <p>{{ $message ?? 'K této operaci nemáte oprávnění.' }}</p>
    <a class="btn btn-primary" href="{{ route('home') }}">Zpět na úvod</a>
@endsection
