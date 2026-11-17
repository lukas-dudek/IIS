<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    <li class="nav-item">
        <a class="nav-link @if(request()->routeIs('moderation.index')) active @endif"
           href="{{ route('moderation.index') }}">Přehled</a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if(request()->routeIs('moderation.events', 'moderation.event')) active @endif"
           href="{{ route('moderation.events') }}">Události</a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if(request()->routeIs('moderation.venues', 'moderation.venue', 'moderation.venues.*')) active @endif"
           href="{{ route('moderation.venues') }}">Místa</a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if(request()->routeIs('moderation.categories', 'moderation.category', 'moderation.categories.*')) active @endif"
           href="{{ route('moderation.categories') }}">Kategorie</a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if(request()->routeIs('moderation.comments')) active @endif"
           href="{{ route('moderation.comments') }}">Komentáře</a>
    </li>
</ul>
