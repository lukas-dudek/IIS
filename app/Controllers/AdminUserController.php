<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Services\UserService;

final class AdminUserController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private UserService $userService
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $query = trim($request->queryString('q'));
        $roleParam = $request->queryString('role');
        $role = in_array($roleParam, ['admin', 'moderator', 'user'], true) ? $roleParam : '';
        $activeParam = $request->queryString('active');
        $active = match ($activeParam) {
            '1' => true,
            '0' => false,
            default => null,
        };

        return Response::html($this->view->render('admin/users/index', [
            'title' => 'Uživatelé',
            'users' => $this->userService->searchForAdmin($query, $role, $active),
            'q' => $query,
            'role' => $role,
            'active' => $activeParam,
            'currentUserId' => (int) $this->auth->id(),
            'activeAdmins' => $this->userService->activeAdminCount(),
        ]));
    }

    public function create(Request $request, array $params): Response
    {
        if (!$request->isPost()) {
            return $this->renderForm(null, [], [], null, 'create');
        }

        $data = [
            'username' => trim($request->postString('username')),
            'email' => mb_strtolower(trim($request->postString('email'))),
            'name' => trim($request->postString('name')),
            'role' => $request->postString('role', 'user'),
            'password' => $request->postString('password'),
            'password_confirm' => $request->postString('password_confirm'),
        ];

        $validator = new Validator($data, [
            'username' => 'Uživatelské jméno',
            'email' => 'E-mail',
            'name' => 'Jméno',
            'role' => 'Role',
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
            ->required('role')
            ->in('role', ['admin', 'moderator', 'user'])
            ->required('password')
            ->length('password', 8, 72)
            ->noNullBytes('password')
            ->required('password_confirm')
            ->same('password_confirm', 'password');

        if ($validator->fails()) {
            $this->rememberOld($data);

            return $this->renderForm(null, $validator->errors(), [], null, 'create', 422);
        }

        try {
            $this->userService->createUser($data);
        } catch (ValidationException $exception) {
            $this->rememberOld($data);

            return $this->renderForm(null, $exception->errors(), [], null, 'create', 422);
        } catch (BusinessRuleException $exception) {
            $this->rememberOld($data);

            return $this->renderForm(null, [], [], $exception->getMessage(), 'create', 422);
        }

        Flash::set('success', 'Uživatel byl vytvořen.');

        return Response::redirect(url('/admin/uzivatele'));
    }

    public function edit(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }
        $user = $this->userService->get($id);

        if (!$request->isPost()) {
            return $this->renderForm($user, [], [], null, 'edit');
        }

        $data = [
            'name' => trim($request->postString('name')),
            'email' => mb_strtolower(trim($request->postString('email'))),
            'role' => $request->postString('role', (string) $user['role']),
        ];
        $validator = new Validator($data, [
            'name' => 'Jméno',
            'email' => 'E-mail',
            'role' => 'Role',
        ]);
        $validator->required('name')->length('name', 2, 120)
            ->required('email')->length('email', 3, 254)->email('email')
            ->required('role')->in('role', ['admin', 'moderator', 'user']);

        if ($validator->fails()) {
            Session::set('_old', $data);

            return $this->renderForm($user, $validator->errors(), [], null, 'edit', 422);
        }

        try {
            $updated = $this->userService->updateUser($id, $data['name'], $data['email'], $data['role']);
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderForm($user, $exception->errors(), [], null, 'edit', 422);
        } catch (BusinessRuleException $exception) {
            Session::set('_old', $data);

            return $this->renderForm($user, [], [], $exception->getMessage(), 'edit', 422);
        }

        if ((int) $updated['id'] === (int) $this->auth->id()) {
            $this->auth->login($updated);
        }

        Flash::set('success', 'Uživatel byl uložen.');

        return Response::redirect(url('/admin/uzivatele'));
    }

    public function resetPassword(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }
        $user = $this->userService->get($id);

        $new = $request->postString('new_password');
        $confirm = $request->postString('new_password_confirm');
        $validator = new Validator(
            ['new_password' => $new, 'new_password_confirm' => $confirm],
            ['new_password' => 'Nové heslo', 'new_password_confirm' => 'Potvrzení nového hesla']
        );
        $validator->required('new_password')->length('new_password', 8, 72)->noNullBytes('new_password')
            ->required('new_password_confirm')->same('new_password_confirm', 'new_password');

        if ($validator->fails()) {
            return $this->renderForm($user, [], $validator->errors(), null, 'edit', 422);
        }

        try {
            $updated = $this->userService->resetPassword($id, $new);
        } catch (ValidationException $exception) {
            return $this->renderForm($user, [], $exception->errors(), null, 'edit', 422);
        }

        if ((int) $updated['id'] === (int) $this->auth->id()) {
            $this->auth->logout();
            Session::regenerate();
            Flash::set('success', 'Heslo bylo změněno. Přihlaste se prosím znovu.');

            return Response::redirect(url('/prihlaseni'));
        }

        Flash::set('success', 'Heslo bylo změněno.');

        return Response::redirect(url('/admin/uzivatele/' . $id . '/upravit'));
    }

    public function deactivate(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        try {
            $this->userService->deactivate($id, (int) $this->auth->id());
            Flash::set('success', 'Uživatel byl deaktivován.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/admin/uzivatele'));
    }

    public function activate(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $this->userService->activate($id);
        Flash::set('success', 'Uživatel byl aktivován.');

        return Response::redirect(url('/admin/uzivatele'));
    }

    public function delete(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        try {
            $this->userService->delete($id, (int) $this->auth->id());
            Flash::set('success', 'Uživatel byl smazán.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/admin/uzivatele'));
    }

    private function renderForm(
        ?array $user,
        array $errors,
        array $passwordErrors,
        ?string $formError,
        string $mode,
        int $status = 200
    ): Response {
        return Response::html($this->view->render('admin/users/form', [
            'title' => $mode === 'create' ? 'Nový uživatel' : 'Upravit uživatele',
            'mode' => $mode,
            'user' => $user,
            'errors' => $errors,
            'passwordErrors' => $passwordErrors,
            'formError' => $formError,
        ]), $status);
    }

    private function rememberOld(array $data): void
    {
        Session::set('_old', [
            'username' => $data['username'],
            'email' => $data['email'],
            'name' => $data['name'],
            'role' => $data['role'],
        ]);
    }
}
