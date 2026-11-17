@extends('layouts.app')

@section('title', 'Registrace')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <h1 class="h4 mb-3">Registrace nového účtu</h1>
            <form method="post" action="{{ route('register.store') }}" class="card card-body" novalidate>
                @csrf
                <div class="mb-3">
                    <label for="username" class="form-label">Uživatelské jméno <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('username') is-invalid @enderror" id="username"
                           name="username" value="{{ old('username') }}" required autocomplete="username">
                    <div class="form-text">3–50 znaků, povolená písmena, číslice, tečka, pomlčka a podtržítko.</div>
                    @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="name" class="form-label">Jméno a příjmení <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                           value="{{ old('name') }}" required autocomplete="name">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                           value="{{ old('email') }}" required autocomplete="email">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Heslo <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                           name="password" required autocomplete="new-password">
                    <div class="form-text">Alespoň 8 znaků, jedno písmeno a jedna číslice.</div>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Heslo znovu <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                           required autocomplete="new-password">
                </div>
                <p class="small text-muted">* povinné pole</p>
                <button type="submit" class="btn btn-primary">Vytvořit účet</button>
            </form>
            <p class="mt-3 mb-0 small">
                Už máte účet? <a href="{{ route('login') }}">Přihlaste se</a>.
            </p>
        </div>
    </div>
@endsection
