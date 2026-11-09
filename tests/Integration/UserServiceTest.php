<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BusinessRuleException;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Repositories\UserRepository;
use App\Services\UserService;
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    private ?Database $database = null;
    private ?UserService $userService = null;

    protected function setUp(): void
    {
        $password = getenv('IIS_DB_PASSWORD');
        if ($password === false || $password === '') {
            self::markTestSkipped('Pro integrační test nastav IIS_DB_PASSWORD.');
        }

        $config = new Config([
            'db' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'name' => 'iis_test',
                'user' => 'iis',
                'password' => $password,
                'charset' => 'utf8mb4',
            ],
        ]);

        $this->database = new Database($config);
        $this->database->pdo()->exec("DELETE FROM users WHERE username LIKE 'test_user_service%'");
        $this->userService = new UserService(new UserRepository($this->database));
    }

    protected function tearDown(): void
    {
        $this->database?->pdo()->exec("DELETE FROM users WHERE username LIKE 'test_user_service%'");
        $this->database = null;
        $this->userService = null;
    }

    public function testCreateAndDeleteUser(): void
    {
        $user = $this->userService->createUser([
            'username' => 'test_user_service',
            'email' => 'test_user_service@example.test',
            'name' => 'Test User Service',
            'role' => 'moderator',
            'password' => 'TestPass123!',
        ]);

        self::assertSame('moderator', $user['role']);
        self::assertSame(1, (int) $user['is_active']);
        self::assertArrayNotHasKey('password_hash', $user);

        $this->userService->delete((int) $user['id'], 1);

        $this->expectException(HttpException::class);
        $this->userService->get((int) $user['id']);
    }

    public function testCannotDeactivateSelf(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Nemůžete deaktivovat sám sebe.');

        $this->userService->deactivate(1, 1);
    }

    public function testCannotDeactivateLastActiveAdminByAnotherActor(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Posledního aktivního administrátora nelze deaktivovat.');

        $this->userService->deactivate(1, 2);
    }

    public function testCannotRemoveRoleFromLastActiveAdmin(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Poslednímu aktivnímu administrátorovi nelze odebrat roli.');

        $this->userService->updateUser(1, 'Administrátor', 'admin@example.test', 'user');
    }

    public function testCannotDeleteUserWithReferences(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Uživatele nelze smazat, protože má navázané záznamy.');

        $this->userService->delete(3, 1);
    }
}
