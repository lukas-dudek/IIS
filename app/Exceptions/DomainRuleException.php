<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Porušení doménového pravidla s hláškou určenou pro uživatele.
 */
final class DomainRuleException extends RuntimeException
{
}
