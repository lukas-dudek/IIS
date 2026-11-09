<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private AuthService $authService
    ) {
    }

    public function showLogin(Request $request, array $params): Response
    {
        if ($this->auth->check()) {
            return Response::redirect(url('/'));
        }

        return $this->renderLogin([], null, $this->safeReturn($request->query('return')));
    }

    public function login(Request $request, array $params): Response
    {
        if ($this->auth->check()) {
            return Response::redirect(url('/'));
        }

        $username = trim((string) $request->post('username', ''));
        $password = (string) $request->post('password', '');
        $return = $this->safeReturn($request->post('return'));

        $validator = new Validator(
            ['username' => $username, 'password' => $password],
            ['username' => 'Uživatelské jméno nebo e-mail', 'password' => 'Heslo']
        );
        $validator->required('username')->required('password');
        if ($validator->fails()) {
            Session::set('_old', ['username' => $username]);

            return $this->renderLogin($validator->errors(), null, $return, 422);
        }

        $result = $this->authService->login($username, $password, $request->ip());
        if ($result['user'] === null) {
            Session::set('_old', ['username' => $username]);

            return $this->renderLogin([], $result['error'], $return, 422);
        }

        $this->auth->login($result['user']);
        Flash::set('success', 'Přihlášení proběhlo.');

        return Response::redirect($return);
    }

    public function showRegister(Request $request, array $params): Response
    {
        if ($this->auth->check()) {
            return Response::redirect(url('/'));
        }

        return $this->renderRegister([]);
    }

    public function register(Request $request, array $params): Response
    {
        if ($this->auth->check()) {
            return Response::redirect(url('/'));
        }

        $data = [
            'username' => trim((string) $request->post('username', '')),
            'email' => mb_strtolower(trim((string) $request->post('email', ''))),
            'name' => trim((string) $request->post('name', '')),
            'password' => (string) $request->post('password', ''),
            'password_confirm' => (string) $request->post('password_confirm', ''),
        ];

        $validator = new Validator($data, [
            'username' => 'Uživatelské jméno',
            'email' => 'E-mail',
            'name' => 'Jméno',
            'password' => 'Heslo',
            'password_confirm' => 'Potvrzení hesla',
        ]);
        $validator
            ->required('username')
            ->length('username', 3, 50)
            ->regex('username', '/^[a-zA-Z0-9_.-]+$/', 'může obsahovat jen písmena, čísla, tečku, podtržítko a pomlčku.')
            ->required('email')
            ->length('email', 3, 254)
            ->email('email')
            ->required('name')
            ->length('name', 2, 120)
            ->required('password')
            ->length('password', 8, 72)
            ->noNullBytes('password')
            ->required('password_confirm')
            ->same('password_confirm', 'password');

        if ($validator->fails()) {
            Session::set('_old', [
                'username' => $data['username'],
                'email' => $data['email'],
                'name' => $data['name'],
            ]);

            return $this->renderRegister($validator->errors(), 422);
        }

        try {
            $user = $this->authService->register($data);
        } catch (ValidationException $exception) {
            Session::set('_old', [
                'username' => $data['username'],
                'email' => $data['email'],
                'name' => $data['name'],
            ]);

            return $this->renderRegister($exception->errors(), 422);
        }

        $this->auth->login($user);
        Flash::set('success', 'Účet byl vytvořen. Jste přihlášeni.');

        return Response::redirect(url('/'));
    }

    public function logout(Request $request, array $params): Response
    {
        $this->auth->logout();
        Session::regenerate();
        Flash::set('success', 'Byli jste odhlášeni.');

        return Response::redirect(url('/'));
    }

    private function renderLogin(array $errors, ?string $formError, string $return, int $status = 200): Response
    {
        return Response::html($this->view->render('auth/login', [
            'title' => 'Přihlášení',
            'errors' => $errors,
            'formError' => $formError,
            'return' => $return,
        ]), $status);
    }

    private function renderRegister(array $errors, int $status = 200): Response
    {
        return Response::html($this->view->render('auth/register', [
            'title' => 'Registrace',
            'errors' => $errors,
            'formError' => null,
        ]), $status);
    }

    private function safeReturn(mixed $value): string
    {
        if (!is_string($value) || $value === '' || $value[0] !== '/') {
            return '/';
        }
        if (str_starts_with($value, '//') || str_contains($value, '\\')) {
            return '/';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return '/';
        }

        return $value;
    }
}
