<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name')) | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('home') }}">{{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation"
                aria-controls="mainNavigation" aria-expanded="false" aria-label="Přepnout navigaci">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('home')) active @endif" href="{{ route('home') }}">Domů</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('events.*')) active @endif"
                       href="{{ route('events.index') }}">Události</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('venues.*')) active @endif"
                       href="{{ route('venues.index') }}">Místa</a>
                </li>
                @auth
                    @if(auth()->user()->canModerate())
                        <li class="nav-item">
                            <a class="nav-link @if(request()->routeIs('moderation.*')) active @endif"
                               href="{{ route('moderation.index') }}">Moderace</a>
                        </li>
                    @endif
                    @if(auth()->user()->isAdmin())
                        <li class="nav-item">
                            <a class="nav-link @if(request()->routeIs('admin.*')) active @endif"
                               href="{{ route('admin.users.index') }}">Uživatelé</a>
                        </li>
                    @endif
                @endauth
            </ul>
            <ul class="navbar-nav align-items-lg-center">
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                           aria-expanded="false">{{ auth()->user()->name }}</a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="px-3 py-1 small text-muted">{{ role_label(auth()->user()->role) }}</li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profil</a></li>
                            <li><a class="dropdown-item" href="{{ route('events.mine') }}">Moje události</a></li>
                            <li><a class="dropdown-item" href="{{ route('registrations.index') }}">Moje registrace</a></li>
                            <li><a class="dropdown-item" href="{{ route('calendar.index') }}">Můj kalendář</a></li>
                            <li><a class="dropdown-item" href="{{ route('proposals.index') }}">Moje návrhy</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Odhlásit se</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link @if(request()->routeIs('login')) active @endif"
                           href="{{ route('login') }}">Přihlásit se</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(request()->routeIs('register')) active @endif"
                           href="{{ route('register') }}">Registrovat se</a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    @include('partials.flash')
    @yield('content')
</main>

<footer class="border-top bg-white py-3">
    <div class="container text-muted small">
        {{ config('app.name') }} · Informační systém FIT VUT · časy zobrazené v Europe/Prague
    </div>
</footer>

<script src="{{ asset('assets/vendor/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
