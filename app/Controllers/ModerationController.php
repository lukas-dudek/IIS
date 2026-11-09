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
use App\Repositories\VenueRepository;
use App\Services\CategoryService;
use App\Services\VenueService;

final class ModerationController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private VenueRepository $venues,
        private VenueService $venueService,
        private CategoryService $categoryService
    ) {
    }

    public function venues(Request $request, array $params): Response
    {
        return Response::html($this->view->render('moderation/venues', [
            'title' => 'Moderace míst',
            'venues' => $this->venues->allForModeration(),
        ]));
    }

    public function venue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $venue = $this->venues->find($id);
        if ($venue === null) {
            abort(404);
        }

        return $this->renderVenue($venue, '', [], 200);
    }

    public function createVenue(Request $request, array $params): Response
    {
        if (!$request->isPost()) {
            return $this->renderVenueForm(null, 'create', [], null);
        }

        $data = venue_form_data($request);
        $errors = venue_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderVenueForm(null, 'create', $errors, null, 422);
        }

        try {
            $this->venueService->createApproved($data, $request->file('photo'), (int) $this->auth->id());
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderVenueForm(null, 'create', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Místo bylo vytvořeno a je schválené.');

        return Response::redirect(url('/moderace/mista'));
    }

    public function editVenue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $venue = $this->venues->find($id);
        if ($venue === null) {
            abort(404);
        }

        if (!$request->isPost()) {
            return $this->renderVenueForm($venue, 'edit', [], null);
        }

        $data = venue_form_data($request);
        $errors = venue_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderVenueForm($venue, 'edit', $errors, null, 422);
        }

        try {
            $this->venueService->update($id, $data, $request->file('photo'));
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderVenueForm($venue, 'edit', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Místo bylo uloženo.');

        return Response::redirect(url('/moderace/mista/' . $id));
    }

    public function approveVenue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        try {
            $this->venueService->approve($id, (int) $this->auth->id());
            Flash::set('success', 'Místo bylo schváleno.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moderace/mista/' . $id));
    }

    public function rejectVenue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $venue = $this->venues->find($id);
        if ($venue === null) {
            abort(404);
        }

        $reason = trim((string) $request->post('reason', ''));
        $validator = new Validator(['reason' => $reason], ['reason' => 'Důvod zamítnutí']);
        $validator->required('reason')->length('reason', 3, 255);
        if ($validator->fails()) {
            return $this->renderVenue($venue, $reason, $validator->errors(), 422);
        }

        try {
            $this->venueService->reject($id, (int) $this->auth->id(), $reason);
            Flash::set('success', 'Místo bylo zamítnuto.');
        } catch (ValidationException $exception) {
            return $this->renderVenue($venue, $reason, $exception->errors(), 422);
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moderace/mista/' . $id));
    }

    public function deleteVenue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        if ($this->venueService->delete($id)) {
            Flash::set('success', 'Místo bylo smazáno.');
        } else {
            Flash::set('danger', 'Místo je použité v událostech, proto ho nelze smazat.');
        }

        return Response::redirect(url('/moderace/mista'));
    }

    public function categories(Request $request, array $params): Response
    {
        return Response::html($this->view->render('moderation/categories', [
            'title' => 'Moderace kategorií',
            'categories' => $this->categoryService->moderationFlat(),
        ]));
    }

    public function category(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $category = $this->categoryService->find($id);
        if ($category === null) {
            abort(404);
        }

        return $this->renderCategory($category, '', [], 200);
    }

    public function createCategory(Request $request, array $params): Response
    {
        if (!$request->isPost()) {
            return $this->renderCategoryForm(null, 'create', [], null);
        }

        $data = category_form_data($request);
        $errors = category_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderCategoryForm(null, 'create', $errors, null, 422);
        }

        try {
            $this->categoryService->createApproved($data, (int) $this->auth->id());
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderCategoryForm(null, 'create', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Kategorie byla vytvořena a je schválená.');

        return Response::redirect(url('/moderace/kategorie'));
    }

    public function editCategory(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $category = $this->categoryService->find($id);
        if ($category === null) {
            abort(404);
        }

        if (!$request->isPost()) {
            return $this->renderCategoryForm($category, 'edit', [], null);
        }

        $data = category_form_data($request);
        $errors = category_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderCategoryForm($category, 'edit', $errors, null, 422);
        }

        try {
            $this->categoryService->updateCategory($id, $data);
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderCategoryForm($category, 'edit', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Kategorie byla uložena.');

        return Response::redirect(url('/moderace/kategorie/' . $id));
    }

    public function approveCategory(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        try {
            $this->categoryService->approve($id, (int) $this->auth->id());
            Flash::set('success', 'Kategorie byla schválena.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moderace/kategorie/' . $id));
    }

    public function rejectCategory(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $category = $this->categoryService->find($id);
        if ($category === null) {
            abort(404);
        }

        $reason = trim((string) $request->post('reason', ''));
        $validator = new Validator(['reason' => $reason], ['reason' => 'Důvod zamítnutí']);
        $validator->required('reason')->length('reason', 3, 255);
        if ($validator->fails()) {
            return $this->renderCategory($category, $reason, $validator->errors(), 422);
        }

        try {
            $this->categoryService->reject($id, (int) $this->auth->id(), $reason);
            Flash::set('success', 'Kategorie byla zamítnuta.');
        } catch (ValidationException $exception) {
            return $this->renderCategory($category, $reason, $exception->errors(), 422);
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moderace/kategorie/' . $id));
    }

    public function deleteCategory(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        if ($this->categoryService->delete($id)) {
            Flash::set('success', 'Kategorie byla smazána.');
        } else {
            Flash::set('danger', 'Kategorie je použitá v událostech nebo má potomky, proto ji nelze smazat.');
        }

        return Response::redirect(url('/moderace/kategorie'));
    }

    private function renderCategoryForm(
        ?array $category,
        string $mode,
        array $errors,
        ?string $formError,
        int $status = 200
    ): Response {
        $edit = $mode === 'edit';
        $id = (int) ($category['id'] ?? 0);

        return Response::html($this->view->render('categories/form', [
            'title' => $edit ? 'Upravit kategorii' : 'Nová kategorie',
            'mode' => $mode,
            'category' => $category,
            'errors' => $errors,
            'formError' => $formError,
            'parentOptions' => $this->categoryService->approvedOptions($edit ? $id : null),
            'action' => $edit ? url('/moderace/kategorie/' . $id . '/upravit') : url('/moderace/kategorie/nova'),
            'heading' => $edit ? 'Upravit kategorii' : 'Nová kategorie',
            'submitLabel' => $edit ? 'Uložit' : 'Vytvořit kategorii',
            'backUrl' => $edit ? url('/moderace/kategorie/' . $id) : url('/moderace/kategorie'),
            'approvalNote' => false,
        ]), $status);
    }

    private function renderCategory(array $category, string $reason, array $reasonErrors, int $status): Response
    {
        return Response::html($this->view->render('moderation/category', [
            'title' => 'Moderace kategorie',
            'category' => $category,
            'reason' => $reason,
            'reasonErrors' => $reasonErrors,
        ]), $status);
    }

    private function renderVenueForm(
        ?array $venue,
        string $mode,
        array $errors,
        ?string $formError,
        int $status = 200
    ): Response {
        $edit = $mode === 'edit';
        $id = (int) ($venue['id'] ?? 0);

        return Response::html($this->view->render('venues/form', [
            'title' => $edit ? 'Upravit místo' : 'Nové místo',
            'mode' => $mode,
            'venue' => $venue,
            'errors' => $errors,
            'formError' => $formError,
            'action' => $edit ? url('/moderace/mista/' . $id . '/upravit') : url('/moderace/mista/novy'),
            'heading' => $edit ? 'Upravit místo' : 'Nové místo',
            'submitLabel' => $edit ? 'Uložit' : 'Vytvořit místo',
            'backUrl' => $edit ? url('/moderace/mista/' . $id) : url('/moderace/mista'),
            'approvalNote' => false,
        ]), $status);
    }

    private function renderVenue(array $venue, string $reason, array $reasonErrors, int $status): Response
    {
        return Response::html($this->view->render('moderation/venue', [
            'title' => 'Moderace místa',
            'venue' => $venue,
            'reason' => $reason,
            'reasonErrors' => $reasonErrors,
        ]), $status);
    }
}
