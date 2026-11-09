<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;

final class Validator
{
    private array $errors = [];

    public function __construct(private array $data, private array $labels = [])
    {
    }

    public function required(string $field): self
    {
        if ($this->isEmpty($field)) {
            $this->add($field, 'je povinné.');
        }

        return $this;
    }

    public function length(string $field, int $min, int $max): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        $length = mb_strlen((string) $this->value($field));
        if ($length < $min || $length > $max) {
            $this->add($field, sprintf('musí mít %d–%d znaků.', $min, $max));
        }

        return $this;
    }

    public function email(string $field): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        if (filter_var((string) $this->value($field), FILTER_VALIDATE_EMAIL) === false) {
            $this->add($field, 'musí být platná e-mailová adresa.');
        }

        return $this;
    }

    public function integer(string $field, ?int $min = null, ?int $max = null): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        $value = filter_var($this->value($field), FILTER_VALIDATE_INT);
        if ($value === false) {
            $this->add($field, 'musí být celé číslo.');
            return $this;
        }
        if ($min !== null && $value < $min) {
            $this->add($field, sprintf('musí být alespoň %d.', $min));
        } elseif ($max !== null && $value > $max) {
            $this->add($field, sprintf('musí být nejvýše %d.', $max));
        }

        return $this;
    }

    public function decimal(string $field, float $min = 0.0, ?float $max = null): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        $value = str_replace(',', '.', trim((string) $this->value($field)));
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $value) !== 1) {
            $this->add($field, 'musí být nezáporné číslo s nejvýše dvěma desetinnými místy.');
            return $this;
        }

        $number = (float) $value;
        if ($number < $min) {
            $this->add($field, sprintf('musí být alespoň %s.', $this->formatNumber($min)));
        } elseif ($max !== null && $number > $max) {
            $this->add($field, sprintf('musí být nejvýše %s.', $this->formatNumber($max)));
        }

        return $this;
    }

    public function dateTime(string $field, string $format = 'Y-m-d\TH:i', string $timezone = 'Europe/Prague'): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        $date = DateTimeImmutable::createFromFormat('!' . $format, (string) $this->value($field), new DateTimeZone($timezone));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            $this->add($field, 'musí být platné datum a čas.');
        }

        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        if (!in_array((string) $this->value($field), $allowed, true)) {
            $this->add($field, 'obsahuje neplatnou hodnotu.');
        }

        return $this;
    }

    public function same(string $field, string $other): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        if ((string) $this->value($field) !== (string) $this->value($other)) {
            $this->add($field, 'se musí shodovat.');
        }

        return $this;
    }

    public function regex(string $field, string $pattern, string $message): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        if (preg_match($pattern, (string) $this->value($field)) !== 1) {
            $this->add($field, $message);
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function value(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    private function isEmpty(string $field): bool
    {
        $value = $this->value($field);

        return $value === null || $value === '' || (is_array($value) && $value === []);
    }

    private function skip(string $field): bool
    {
        return $this->isEmpty($field);
    }

    private function add(string $field, string $message): void
    {
        if (isset($this->errors[$field])) {
            return;
        }

        $label = $this->labels[$field] ?? str_replace('_', ' ', $field);
        $this->errors[$field] = $label . ' ' . $message;
    }

    private function formatNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, ',', ' '), '0'), ',');
    }
}
