<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredAndLength(): void
    {
        $validator = new Validator(['username' => 'ab'], ['username' => 'Uživatelské jméno']);
        $validator->required('username')->length('username', 3, 50);

        self::assertTrue($validator->fails());
        self::assertSame(
            'Uživatelské jméno musí mít 3–50 znaků.',
            $validator->errors()['username']
        );
    }

    public function testEmailAndFormat(): void
    {
        $validator = new Validator(['email' => 'neplatny'], ['email' => 'E-mail']);
        $validator->required('email')->email('email');

        self::assertSame(
            'E-mail musí být platná e-mailová adresa.',
            $validator->errors()['email']
        );
    }

    public function testDecimalAcceptsComma(): void
    {
        $validator = new Validator(['price' => '150,50'], ['price' => 'Cena']);
        $validator->decimal('price', 0, 1000);

        self::assertFalse($validator->fails());
    }

    public function testDecimalRejectsMoreThanTwoPlaces(): void
    {
        $validator = new Validator(['price' => '150,555'], ['price' => 'Cena']);
        $validator->decimal('price', 0, 1000);

        self::assertTrue($validator->fails());
    }

    public function testDateTimeRejectsInvalidDate(): void
    {
        $validator = new Validator(['starts_at' => '2026-02-30T10:00'], ['starts_at' => 'Začátek']);
        $validator->dateTime('starts_at');

        self::assertTrue($validator->fails());
    }

    public function testSameAndIn(): void
    {
        $validator = new Validator(
            ['password' => 'heslo123', 'password_confirm' => 'jine', 'role' => 'owner'],
            ['password_confirm' => 'Potvrzení hesla', 'role' => 'Role']
        );
        $validator->same('password_confirm', 'password')->in('role', ['user', 'moderator', 'admin']);

        self::assertArrayHasKey('password_confirm', $validator->errors());
        self::assertArrayHasKey('role', $validator->errors());
    }

    public function testOptionalEmptyFieldIsSkipped(): void
    {
        $validator = new Validator(['description' => ''], ['description' => 'Popis']);
        $validator->length('description', 20, 2000);

        self::assertFalse($validator->fails());
    }

    public function testNullByteInPasswordIsRejected(): void
    {
        $validator = new Validator(['password' => "heslo\0navic"], ['password' => 'Heslo']);
        $validator->noNullBytes('password');

        self::assertSame('Heslo obsahuje neplatný znak.', $validator->errors()['password']);
    }
}
