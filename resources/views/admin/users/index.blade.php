@extends('layouts.app')

@section('title', 'Správa uživatelů')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Uživatelé</h1>
        <a class="btn btn-primary" href="{{ route('admin.users.create') }}">Vytvořit uživatele</a>
    </div>

    <form method="get" action="{{ route('admin.users.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label">Hledat</label>
                <input type="search" class="form-control" id="q" name="q" value="{{ $search }}"
                       placeholder="Jméno, e-mail nebo uživatelské jméno">
            </div>
            <div class="col-md-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">Všechny role</option>
                    @foreach(\App\Models\User::ROLES as $option)
                        <option value="{{ $option }}" @selected($role === $option)>{{ role_label($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="aktivni" class="form-label">Stav</label>
                <select class="form-select" id="aktivni" name="aktivni">
                    <option value="">Všechny</option>
                    <option value="1" @selected($active === '1')>Aktivní</option>
                    <option value="0" @selected($active === '0')>Deaktivovaní</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-secondary w-100">Filtrovat</button>
            </div>
        </div>
    </form>

    <p class="small text-muted">
        Aktivních administrátorů: {{ $activeAdminCount }}. Poslední aktivní administrátor nesmí být deaktivován,
        smazán ani zbaven role.
    </p>

    <div class="table-responsive">
        <table class="table align-middle bg-white">
            <thead>
            <tr>
                <th scope="col">Uživatel</th>
                <th scope="col">Role</th>
                <th scope="col">Stav</th>
                <th scope="col">Poslední přihlášení</th>
                <th scope="col"></th>
            </tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td>
                        {{ $user->name }}
                        <div class="small text-muted">{{ $user->username }} · {{ $user->email }}</div>
                    </td>
                    <td><span class="badge text-bg-secondary">{{ role_label($user->role) }}</span></td>
                    <td>
                        @if($user->isActive())
                            <span class="badge text-bg-success">Aktivní</span>
                        @else
                            <span class="badge text-bg-secondary">Deaktivovaný</span>
                        @endif
                    </td>
                    <td class="small">{{ format_datetime($user->last_login_at) ?: '—' }}</td>
                    <td class="text-end">
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.users.edit', $user) }}">
                                Upravit
                            </a>
                            @if($user->isActive())
                                @if($user->id !== auth()->id())
                                    <form method="post" action="{{ route('admin.users.deactivate', $user) }}"
                                          onsubmit="return confirm('Deaktivovat uživatele {{ $user->name }}?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Deaktivovat</button>
                                    </form>
                                @endif
                            @else
                                <form method="post" action="{{ route('admin.users.activate', $user) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Aktivovat</button>
                                </form>
                            @endif
                            @if($user->id !== auth()->id())
                                <form method="post" action="{{ route('admin.users.destroy', $user) }}"
                                      onsubmit="return confirm('Opravdu smazat uživatele {{ $user->name }}? Mazání je možné jen bez navázaných záznamů.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Smazat</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $users->links() }}</div>
@endsection
