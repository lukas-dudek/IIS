<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(private int $status, string $message = '')
    {
        parent::__construct($message === '' ? 'HTTP ' . $status : $message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
