<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private ?Database $database = null;
    private ?AuthService $authService = null;

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
        $this->database->pdo()->exec('DELETE FROM login_attempts');
        $this->authService = new AuthService(new UserRepository($this->database));
    }

    protected function tearDown(): void
    {
        $this->database?->pdo()->exec('DELETE FROM login_attempts');
        $this->database = null;
        $this->authService = null;
    }

    public function testRateLimitBlocksSixthAttempt(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = $this->authService->login('rate_unit_test', 'wrong-password', '127.0.0.1');
            self::assertNull($result['user']);
            self::assertSame('Neplatné přihlašovací údaje.', $result['error']);
        }

        $result = $this->authService->login('rate_unit_test', 'wrong-password', '127.0.0.1');
        self::assertNull($result['user']);
        self::assertStringContainsString('Příliš mnoho neúspěšných pokusů', $result['error']);
    }

    public function testInactiveUserCannotLogin(): void
    {
        $users = new UserRepository($this->database);
        if ($users->findByLogin('neaktivni') === null) {
            self::markTestSkipped('V iis_test chybí seed uživatel neaktivni.');
        }

        $result = $this->authService->login('neaktivni', 'Neaktivni1234!', '127.0.0.1');
        self::assertNull($result['user']);
        self::assertSame('Neplatné přihlašovací údaje.', $result['error']);
    }

    public function testSuccessfulLoginReturnsUserWithoutPasswordHash(): void
    {
        $users = new UserRepository($this->database);
        if ($users->findByLogin('user') === null) {
            self::markTestSkipped('V iis_test chybí seed uživatel user.');
        }

        $result = $this->authService->login('user', 'User1234!', '127.0.0.1');
        self::assertNotNull($result['user']);
        self::assertSame('user', $result['user']['username']);
        self::assertArrayNotHasKey('password_hash', $result['user']);
    }
}
