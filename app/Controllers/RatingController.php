<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\EventRepository;
use App\Services\FeedbackService;

final class RatingController
{
    public function __construct(
        private Auth $auth,
        private EventRepository $events,
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
            $this->feedbackService->saveRating(
                (int) $event['id'],
                (int) $this->auth->id(),
                (int) $request->post('value', 0)
            );
            Flash::set('success', 'Hodnocení bylo uloženo.');
        } catch (ValidationException $exception) {
            Flash::set('danger', (array_values($exception->errors())[0] ?? null) ?: 'Hodnocení se nepodařilo uložit.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/udalosti/' . $event['slug'] . '#hodnoceni'));
    }
}
