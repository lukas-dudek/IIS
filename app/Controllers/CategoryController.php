<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\CategoryService;

final class CategoryController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private CategoryService $categoryService
    ) {
    }

    public function propose(Request $request, array $params): Response
    {
        if (!$request->isPost()) {
            return $this->renderForm(null, 'propose', [], null);
        }

        $data = category_form_data($request);
        $errors = category_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderForm(null, 'propose', $errors, null, 422);
        }

        try {
            $this->categoryService->createProposal($data, (int) $this->auth->id());
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderForm(null, 'propose', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Návrh kategorie byl odeslán ke schválení.');

        return Response::redirect(url('/moje-navrhy'));
    }

    public function editProposal(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $category = $this->categoryService->findOwned($id, (int) $this->auth->id());
        if ($category === null) {
            abort(404);
        }
        if (!in_array($category['status'], ['pending', 'rejected'], true)) {
            abort(403);
        }

        if (!$request->isPost()) {
            return $this->renderForm($category, 'edit', [], null);
        }

        $data = category_form_data($request);
        $errors = category_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderForm($category, 'edit', $errors, null, 422);
        }

        try {
            $this->categoryService->updateProposal($id, (int) $this->auth->id(), $data);
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderForm($category, 'edit', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Návrh kategorie byl uložen a čeká na schválení.');

        return Response::redirect(url('/moje-navrhy'));
    }

    private function renderForm(
        ?array $category,
        string $mode,
        array $errors,
        ?string $formError,
        int $status = 200
    ): Response {
        $edit = $mode === 'edit';
        $id = (int) ($category['id'] ?? 0);

        return Response::html($this->view->render('categories/form', [
            'title' => $edit ? 'Upravit návrh kategorie' : 'Navrhnout kategorii',
            'mode' => $mode,
            'category' => $category,
            'errors' => $errors,
            'formError' => $formError,
            'parentOptions' => $this->categoryService->approvedOptions($edit ? $id : null),
            'action' => $edit ? url('/kategorie/' . $id . '/upravit') : url('/kategorie/navrhnout'),
            'heading' => $edit ? 'Upravit návrh kategorie' : 'Navrhnout kategorii',
            'submitLabel' => $edit ? 'Uložit návrh' : 'Odeslat návrh',
            'backUrl' => url('/moje-navrhy'),
            'approvalNote' => true,
        ]), $status);
    }
}
