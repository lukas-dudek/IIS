@extends('layouts.app')

@section('title', 'Profil')

@section('content')
    <h1 class="h4 mb-3">Můj profil</h1>

    <div class="row g-4">
        <div class="col-lg-6">
            <form method="post" action="{{ route('profile.update') }}" class="card card-body" novalidate>
                @csrf
                <h2 class="h6">Osobní údaje</h2>
                <div class="mb-3">
                    <label class="form-label">Uživatelské jméno</label>
                    <p class="form-control-plaintext">{{ $user->username }}</p>
                </div>
                <div class="mb-3">
                    <label for="name" class="form-label">Jméno a příjmení <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                           value="{{ old('name', $user->name) }}" required maxlength="120">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                           value="{{ old('email', $user->email) }}" required maxlength="254">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <p class="small text-muted">Role: {{ role_label($user->role) }}</p>
                <button type="submit" class="btn btn-primary">Uložit profil</button>
            </form>
        </div>

        <div class="col-lg-6">
            <form method="post" action="{{ route('profile.password') }}" class="card card-body" novalidate>
                @csrf
                <h2 class="h6">Změna hesla</h2>
                <div class="mb-3">
                    <label for="current_password" class="form-label">Současné heslo <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                           id="current_password" name="current_password" required autocomplete="current-password">
                    @error('current_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">Nové heslo <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('new_password') is-invalid @enderror"
                           id="new_password" name="new_password" required autocomplete="new-password">
                    <div class="form-text">Alespoň 8 znaků, jedno písmeno a jedna číslice.</div>
                    @error('new_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="new_password_confirmation" class="form-label">
                        Nové heslo znovu <span class="text-danger">*</span>
                    </label>
                    <input type="password" class="form-control" id="new_password_confirmation"
                           name="new_password_confirmation" required autocomplete="new-password">
                </div>
                <p class="small text-muted">* povinné pole</p>
                <button type="submit" class="btn btn-outline-primary">Změnit heslo</button>
            </form>
        </div>
    </div>
@endsection
