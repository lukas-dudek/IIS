<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    private array $shared = [];

    public function __construct(private string $viewsPath)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $templateFile = $this->viewsPath . '/' . $template . '.php';
        if (!is_file($templateFile)) {
            throw new RuntimeException('Šablona neexistuje: ' . $template);
        }

        $vars = $data + $this->shared;
        extract($vars, EXTR_SKIP);

        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        if ($layout === null || $layout === '') {
            $this->clearOldInput();

            return $content;
        }

        $layoutFile = $this->viewsPath . '/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new RuntimeException('Layout neexistuje: ' . $layout);
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        require $layoutFile;
        $html = (string) ob_get_clean();

        $this->clearOldInput();

        return $html;
    }

    private function clearOldInput(): void
    {
        if (isset($_SESSION['_old'])) {
            unset($_SESSION['_old']);
        }
    }
}
