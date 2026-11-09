<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(private array $errors)
    {
        parent::__construct('Validace selhala.');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
