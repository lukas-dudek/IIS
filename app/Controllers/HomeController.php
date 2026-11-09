<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\EventRepository;
use App\Services\CategoryService;

final class HomeController
{
    public function __construct(
        private View $view,
        private EventRepository $events,
        private CategoryService $categoryService
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $result = $this->events->search(['archive' => false, 'sort' => 'date'], 1, 6);

        return Response::html($this->view->render('home/index', [
            'title' => 'Úvod',
            'events' => $result['items'],
            'total' => $result['total'],
            'categories' => $this->categoryService->approvedTree(),
        ]));
    }
}
