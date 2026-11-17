@extends('layouts.app')

@section('title', 'Přihlášení')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <h1 class="h4 mb-3">Přihlášení</h1>
            <form method="post" action="{{ route('login.store') }}" class="card card-body" novalidate>
                @csrf
                <div class="mb-3">
                    <label for="login" class="form-label">Uživatelské jméno nebo e-mail <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('login') is-invalid @enderror" id="login" name="login"
                           value="{{ old('login') }}" required autocomplete="username" autofocus>
                    @error('login')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Heslo <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                           name="password" required autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <p class="small text-muted">* povinné pole</p>
                <button type="submit" class="btn btn-primary">Přihlásit se</button>
            </form>
            <p class="mt-3 mb-0 small">
                Nemáte účet? <a href="{{ route('register') }}">Zaregistrujte se</a>.
            </p>
        </div>
    </div>
@endsection
