<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testScalarPostHelpersIgnoreArrays(): void
    {
        $request = new Request(
            [],
            ['name' => ['x'], 'value' => '  text  ', 'id' => '42', 'bad_id' => '1x'],
            [],
            [],
            []
        );

        self::assertSame('', $request->postString('name'));
        self::assertSame('  text  ', $request->postString('value', 'fallback'));
        self::assertSame('fallback', $request->postString('missing', 'fallback'));
        self::assertSame(0, $request->postInt('name'));
        self::assertSame(42, $request->postInt('id'));
        self::assertSame(0, $request->postInt('bad_id'));
    }
}
