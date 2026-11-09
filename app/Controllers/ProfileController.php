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
use App\Repositories\VenueRepository;
use App\Services\UserService;

final class ProfileController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private UserService $userService,
        private VenueRepository $venues
    ) {
    }

    public function edit(Request $request, array $params): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            abort(403);
        }

        return $this->render($user, [], []);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            abort(403);
        }

        $name = trim((string) $request->post('name', ''));
        $email = mb_strtolower(trim((string) $request->post('email', '')));
        $validator = new Validator(
            ['name' => $name, 'email' => $email],
            ['name' => 'Jméno', 'email' => 'E-mail']
        );
        $validator->required('name')->length('name', 2, 120)
            ->required('email')->length('email', 3, 254)->email('email');

        if ($validator->fails()) {
            Session::set('_old', ['name' => $name, 'email' => $email]);

            return $this->render($user, $validator->errors(), [], 422);
        }

        try {
            $this->userService->updateProfile((int) $user['id'], $name, $email);
        } catch (ValidationException $exception) {
            Session::set('_old', ['name' => $name, 'email' => $email]);

            return $this->render($user, $exception->errors(), [], 422);
        }

        Flash::set('success', 'Profil byl uložen.');

        return Response::redirect(url('/profil'));
    }

    public function proposals(Request $request, array $params): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            abort(403);
        }

        return Response::html($this->view->render('profile/proposals', [
            'title' => 'Moje návrhy',
            'venues' => $this->venues->proposalsForUser((int) $user['id']),
        ]));
    }

    public function password(Request $request, array $params): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            abort(403);
        }

        $current = (string) $request->post('current_password', '');
        $new = (string) $request->post('new_password', '');
        $confirm = (string) $request->post('new_password_confirm', '');
        $validator = new Validator(
            [
                'current_password' => $current,
                'new_password' => $new,
                'new_password_confirm' => $confirm,
            ],
            [
                'current_password' => 'Současné heslo',
                'new_password' => 'Nové heslo',
                'new_password_confirm' => 'Potvrzení nového hesla',
            ]
        );
        $validator->required('current_password')
            ->required('new_password')->length('new_password', 8, 72)->noNullBytes('new_password')
            ->required('new_password_confirm')->same('new_password_confirm', 'new_password');

        if ($validator->fails()) {
            return $this->render($user, [], $validator->errors(), 422);
        }

        try {
            $updated = $this->userService->changePassword((int) $user['id'], $current, $new);
        } catch (ValidationException $exception) {
            return $this->render($user, [], $exception->errors(), 422);
        }

        $this->auth->login($updated);
        Flash::set('success', 'Heslo bylo změněno. Ostatní relace byly odhlášeny.');

        return Response::redirect(url('/profil'));
    }

    private function render(array $user, array $profileErrors, array $passwordErrors, int $status = 200): Response
    {
        return Response::html($this->view->render('profile/edit', [
            'title' => 'Profil',
            'user' => $user,
            'profileErrors' => $profileErrors,
            'passwordErrors' => $passwordErrors,
        ]), $status);
    }
}
