<?php

declare(strict_types=1);

use App\Controllers\AdminUserController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\ModerationController;
use App\Controllers\ProfileController;
use App\Controllers\VenueController;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Router;

try {
    $app = require dirname(__DIR__) . '/app/bootstrap.php';
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    error_log('Bootstrap failed: ' . $exception->getMessage());
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Chyba serveru</title></head>'
        . '<body><h1>Chyba serveru</h1><p>Aplikaci se nepodařilo spustit.</p></body></html>';
    exit;
}

$routes = require dirname(__DIR__) . '/config/routes.php';

$controllerFactory = static function (string $controller) use ($app): object {
    return match ($controller) {
        HomeController::class => new HomeController($app['view']),
        AuthController::class => new AuthController($app['view'], $app['auth'], $app['authService']),
        ProfileController::class => new ProfileController($app['view'], $app['auth'], $app['userService'], $app['venues']),
        AdminUserController::class => new AdminUserController($app['view'], $app['auth'], $app['userService']),
        VenueController::class => new VenueController($app['view'], $app['auth'], $app['venues'], $app['venueService']),
        ModerationController::class => new ModerationController($app['view'], $app['auth'], $app['venues'], $app['venueService']),
        default => throw new RuntimeException('Neznámý controller: ' . $controller),
    };
};

$router = new Router(
    $routes,
    $controllerFactory,
    $app['auth'],
    (string) $app['config']->get('app.base_path', '')
);

$renderError = static function (int $status, string $title) use ($app): Response {
    if (!in_array($status, [403, 404], true)) {
        $status = 500;
    }

    try {
        return Response::html($app['view']->render('errors/' . $status, ['title' => $title]), $status);
    } catch (Throwable $exception) {
        $app['logger']->error('Error page render failed', [
            'status' => $status,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return Response::html(
            '<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Chyba serveru</title></head>'
            . '<body><h1>Chyba serveru</h1><p>Operace se nepodařila.</p></body></html>',
            $status
        );
    }
};

try {
    $response = $router->dispatch($app['request']);
} catch (HttpException $exception) {
    $status = $exception->status();
    $title = match ($status) {
        403 => 'Přístup zamítnut',
        404 => 'Stránka nenalezena',
        default => 'Chyba serveru',
    };
    $response = $renderError($status, $title);
} catch (Throwable $exception) {
    $app['logger']->error('Unhandled exception', [
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
        'method' => $app['request']->method(),
        'route' => $app['request']->path(),
    ]);
    $response = $renderError(500, 'Chyba serveru');
}

$response->send($app['request']->method() !== 'HEAD');
