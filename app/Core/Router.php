<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

final class Router
{
    private array $compiled = [];

    public function __construct(
        array $routes,
        private Closure $controllerFactory,
        private Auth $auth,
        private string $basePath = ''
    ) {
        foreach ($routes as $route) {
            $this->compiled[] = [
                'method' => strtoupper((string) $route['method']),
                'pattern' => (string) $route['pattern'],
                'regex' => $this->compilePattern((string) $route['pattern']),
                'controller' => (string) $route['controller'],
                'action' => (string) $route['action'],
                'middleware' => $route['middleware'] ?? [],
            ];
        }
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->stripBasePath($request->path());
        $method = $request->method();

        foreach ($this->compiled as $route) {
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                && !Csrf::validate($request->post('_csrf'))) {
                throw new HttpException(403, 'Neplatný CSRF token.');
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $redirect = $this->runMiddleware($route['middleware'], $request);
            if ($redirect !== null) {
                return $redirect;
            }

            $controller = ($this->controllerFactory)($route['controller']);
            if (!method_exists($controller, $route['action'])) {
                throw new RuntimeException('Akce ' . $route['action'] . ' neexistuje.');
            }

            $response = $controller->{$route['action']}($request, $params);
            if (!$response instanceof Response) {
                throw new RuntimeException('Akce ' . $route['action'] . ' nevrátila Response.');
            }

            return $response;
        }

        throw new HttpException(404, 'Stránka nenalezena.');
    }

    private function runMiddleware(array $middleware, Request $request): ?Response
    {
        foreach ($middleware as $item) {
            if ($item === 'auth') {
                if (!$this->auth->check()) {
                    $return = $request->path();
                    $location = \url('/prihlaseni');
                    if ($return !== '/') {
                        $location .= '?return=' . rawurlencode($return);
                    }

                    return Response::redirect($location);
                }
                continue;
            }

            if (str_starts_with($item, 'role:')) {
                $role = substr($item, 5);
                if (!in_array($role, ['admin', 'moderator', 'user'], true)) {
                    throw new RuntimeException('Neznámá role v middleware: ' . $role);
                }
                if (!$this->auth->hasRole($role)) {
                    throw new HttpException(403, 'Přístup zamítnut.');
                }
                continue;
            }

            throw new RuntimeException('Neznámé middleware: ' . $item);
        }

        return null;
    }

    private function stripBasePath(string $path): string
    {
        $base = rtrim($this->basePath, '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
            if ($path === '') {
                return '/';
            }
            if ($path[0] !== '/') {
                return '/' . $path;
            }
        }

        return $path === '' ? '/' : $path;
    }

    private function compilePattern(string $pattern): string
    {
        $parts = preg_split('/(\{[a-z]+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new RuntimeException('Neplatný vzor routy: ' . $pattern);
        }

        $regex = '';
        foreach ($parts as $part) {
            if (preg_match('/^\{([a-z]+)\}$/', $part, $match) === 1) {
                $regex .= match ($match[1]) {
                    'id' => '(?P<id>[1-9][0-9]*)',
                    'slug' => '(?P<slug>[a-z0-9-]+)',
                    default => throw new RuntimeException('Neznámý parametr routy: ' . $match[1]),
                };
                continue;
            }

            $regex .= preg_quote($part, '#');
        }

        return '#^' . $regex . '$#';
    }
}
