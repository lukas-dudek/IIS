<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zneplatní relaci, pokud se změnila role, aktivita nebo verze autentizace.
 */
final class EnsureSessionIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $sessionVersion = (int) $request->session()->get('auth_version', 0);

            if (!$user->isActive() || $sessionVersion !== (int) $user->auth_version) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->wantsJson()) {
                    abort(401, 'Relace byla ukončena.');
                }

                return redirect()
                    ->route('login')
                    ->withErrors(['login' => 'Relace byla ukončena. Přihlaste se znovu.']);
            }
        }

        return $next($request);
    }
}
