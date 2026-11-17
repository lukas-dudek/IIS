<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AuthController extends Controller
{
    public function __construct(private AuthService $auth)
    {
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string'],
        ], [], [
            'login' => 'uživatelské jméno nebo e-mail',
            'password' => 'heslo',
        ]);

        $result = $this->auth->attempt(
            $credentials['login'],
            $credentials['password'],
            (string) $request->ip()
        );

        if ($result['user'] === null) {
            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => $result['error'] ?? 'Přihlášení se nepodařilo.']);
        }

        Auth::login($result['user']);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $result['user']->auth_version);

        return redirect()->intended(route('home'))->with('status', 'Byli jste přihlášeni.');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = $this->auth->register($request->validated());

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->auth_version);

        return redirect()->route('home')->with('status', 'Účet byl vytvořen, jste přihlášeni.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Byli jste odhlášeni.');
    }
}
