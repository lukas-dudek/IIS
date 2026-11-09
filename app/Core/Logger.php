<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public function __construct(private string $directory)
    {
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            error_log('Logger: nelze vytvořit adresář ' . $this->directory);
            return;
        }

        $context = $this->redact($context);
        $suffix = $context === [] ? '' : ' ' . (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $line = sprintf(
            "[%s] %s %s%s\n",
            gmdate('Y-m-d H:i:s'),
            $level,
            $message,
            $suffix
        );

        $file = $this->directory . '/app-' . gmdate('Y-m-d') . '.log';
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('Logger: nelze zapsat do ' . $file);
        }
    }

    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->redact($value);
                continue;
            }
            if (preg_match('/password|passwd|token|csrf|authorization|secret/i', (string) $key) === 1) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
