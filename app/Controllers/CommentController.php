<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\CommentRepository;
use App\Repositories\EventRepository;
use App\Services\FeedbackService;

final class CommentController
{
    public function __construct(
        private Auth $auth,
        private EventRepository $events,
        private CommentRepository $comments,
        private FeedbackService $feedbackService
    ) {
    }

    public function store(Request $request, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $event = $this->events->findBySlug($slug);
        if ($event === null) {
            abort(404);
        }

        try {
            $this->feedbackService->saveComment(
                (int) $event['id'],
                (int) $this->auth->id(),
                (string) $request->post('body', '')
            );
            Flash::set('success', 'Komentář byl uložen.');
        } catch (ValidationException $exception) {
            Flash::set('danger', (array_values($exception->errors())[0] ?? null) ?: 'Komentář se nepodařilo uložit.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/udalosti/' . $event['slug'] . '#komentare'));
    }

    public function update(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }
        $existing = $this->comments->find($id);
        if ($existing === null) {
            abort(404);
        }

        try {
            $comment = $this->feedbackService->updateComment(
                $id,
                (int) $this->auth->id(),
                (string) $request->post('body', '')
            );
            Flash::set('success', 'Komentář byl upraven.');
        } catch (ValidationException $exception) {
            Flash::set('danger', (array_values($exception->errors())[0] ?? null) ?: 'Komentář se nepodařilo upravit.');

            return Response::redirect(url('/udalosti/' . $existing['event_slug'] . '#komentare'));
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());

            return Response::redirect(url('/udalosti/' . $existing['event_slug'] . '#komentare'));
        }

        return Response::redirect(url('/udalosti/' . $comment['event_slug'] . '#komentare'));
    }

    public function destroy(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $comment = $this->feedbackService->deleteComment($id, (int) $this->auth->id());
        Flash::set('success', 'Komentář byl smazán.');

        return Response::redirect(url('/udalosti/' . $comment['event_slug'] . '#komentare'));
    }
}
