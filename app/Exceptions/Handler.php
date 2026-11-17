<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;

final class Handler extends ExceptionHandler
{
    /**
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (DomainRuleException $exception, Request $request) {
            if ($request->wantsJson()) {
                return null;
            }

            return back()
                ->withInput($request->except($this->dontFlash))
                ->withErrors(['general' => $exception->getMessage()]);
        });

        $this->renderable(function (AuthorizationException $exception, Request $request) {
            if ($request->wantsJson()) {
                return null;
            }

            return response()->view('errors.403', [
                'message' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'K této operaci nemáte oprávnění.',
            ], 403);
        });

        $this->renderable(function (AuthenticationException $exception, Request $request) {
            if ($request->wantsJson()) {
                return null;
            }

            return redirect()
                ->guest(route('login'))
                ->withErrors(['login' => 'Pro tuto stránku je nutné přihlášení.']);
        });
    }
}
