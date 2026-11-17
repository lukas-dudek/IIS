@extends('layouts.app')

@section('title', $user === null ? 'Nový uživatel' : 'Úprava uživatele')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Uživatelé</a></li>
            <li class="breadcrumb-item active" aria-current="page">
                {{ $user === null ? 'Nový uživatel' : $user->name }}
            </li>
        </ol>
    </nav>

    <h1 class="h4 mb-3">{{ $user === null ? 'Nový uživatel' : 'Úprava uživatele' }}</h1>

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="post"
                  action="{{ $user === null ? route('admin.users.store') : route('admin.users.update', $user) }}"
                  class="card card-body" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="username" class="form-label">Uživatelské jméno <span class="text-danger">*</span></label>
                    @if($user === null)
                        <input type="text" class="form-control @error('username') is-invalid @enderror" id="username"
                               name="username" value="{{ $username }}" required maxlength="50"
                               autocomplete="off">
                        <div class="form-text">3–50 znaků, povolená písmena, číslice, tečka, pomlčka a podtržítko.</div>
                    @else
                        <p class="form-control-plaintext">{{ $user->username }}</p>
                    @endif
                    @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label">Jméno a příjmení <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                           value="{{ $name }}" required maxlength="120">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                           value="{{ $email }}" required maxlength="254">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                        @foreach($roles as $option)
                            <option value="{{ $option }}" @selected($role === $option)>{{ role_label($option) }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @if($user === null)
                    <div class="mb-3">
                        <label for="password" class="form-label">Počáteční heslo <span class="text-danger">*</span></label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                               name="password" required autocomplete="new-password">
                        <div class="form-text">Alespoň 8 znaků, jedno písmeno a jedna číslice.</div>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Heslo znovu <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password_confirmation"
                               name="password_confirmation" required autocomplete="new-password">
                    </div>
                @endif

                <p class="small text-muted">* povinné pole</p>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Uložit</button>
                    <a class="btn btn-link" href="{{ route('admin.users.index') }}">Zrušit</a>
                </div>
            </form>
        </div>

        @if($user !== null)
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">Reset hesla</div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.users.password', $user) }}" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label for="new_password_field" class="form-label">
                                    Nové heslo <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control" id="new_password_field" name="password"
                                       required autocomplete="new-password">
                                <div class="form-text">Alespoň 8 znaků, jedno písmeno a jedna číslice.</div>
                            </div>
                            <div class="mb-3">
                                <label for="new_password_confirmation" class="form-label">
                                    Heslo znovu <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control" id="new_password_confirmation"
                                       name="password_confirmation" required autocomplete="new-password">
                            </div>
                            <p class="small text-muted">Změna hesla ukončí existující přihlášení uživatele.</p>
                            <button type="submit" class="btn btn-outline-primary">Změnit heslo</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
