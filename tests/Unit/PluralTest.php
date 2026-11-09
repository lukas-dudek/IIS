<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PluralTest extends TestCase
{
    public function testCzechResultPlural(): void
    {
        self::assertSame('výsledek', plural_results(1));
        self::assertSame('výsledky', plural_results(2));
        self::assertSame('výsledky', plural_results(4));
        self::assertSame('výsledků', plural_results(5));
        self::assertSame('výsledků', plural_results(11));
        self::assertSame('výsledků', plural_results(14));
        self::assertSame('výsledek', plural_results(21));
        self::assertSame('výsledky', plural_results(22));
        self::assertSame('výsledků', plural_results(25));
        self::assertSame('výsledků', plural_results(0));
    }
}
