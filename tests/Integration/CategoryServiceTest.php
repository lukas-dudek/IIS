<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\CategoryRepository;
use App\Services\CategoryService;
use PHPUnit\Framework\TestCase;

final class CategoryServiceTest extends TestCase
{
    private ?Database $database = null;
    private ?CategoryRepository $categories = null;
    private ?CategoryService $service = null;

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
        $this->cleanTestCategories();
        $this->categories = new CategoryRepository($this->database);
        $this->service = new CategoryService($this->categories);
    }

    protected function tearDown(): void
    {
        $this->cleanTestCategories();
        $this->database = null;
        $this->categories = null;
        $this->service = null;
    }

    public function testApprovedTreeContainsSeedHierarchy(): void
    {
        $root = $this->findNode($this->service->approvedTree(), 1);
        self::assertNotNull($root);
        self::assertSame('Kultura', $root['name']);

        $music = $this->findNode($root['children'], 2);
        self::assertNotNull($music);
        self::assertSame('Hudba', $music['name']);

        $concert = $this->findNode($music['children'], 3);
        self::assertNotNull($concert);
        self::assertSame('Koncert', $concert['name']);
    }

    public function testDescendantIds(): void
    {
        $descendants = $this->service->descendantIds(1);
        self::assertContains(2, $descendants);
        self::assertContains(3, $descendants);
        self::assertContains(4, $descendants);
        self::assertNotContains(1, $descendants);

        self::assertSame([3], $this->service->descendantIds(2));
    }

    public function testDepthLimit(): void
    {
        $root = $this->service->createApproved($this->data('test_category_service_root'), 2);
        $level2 = $this->service->createApproved($this->data('test_category_service_l2', $root), 2);
        $level3 = $this->service->createApproved($this->data('test_category_service_l3', $level2), 2);
        $level4 = $this->service->createApproved($this->data('test_category_service_l4', $level3), 2);
        self::assertGreaterThan(0, $level4);

        $this->expectException(ValidationException::class);
        $this->service->createApproved($this->data('test_category_service_l5', $level4), 2);
    }

    public function testCannotMoveUnderOwnDescendant(): void
    {
        $root = $this->service->createApproved($this->data('test_category_service_move_root'), 2);
        $child = $this->service->createApproved($this->data('test_category_service_move_child', $root), 2);

        try {
            $this->service->updateCategory($root, $this->data('test_category_service_move_root', $child));
            self::fail('Přesun pod potomka měl selhat.');
        } catch (ValidationException $exception) {
            self::assertSame(
                'Kategorie nesmí být přesunuta pod sebe nebo pod svého potomka.',
                $exception->errors()['parent_id']
            );
        }
    }

    public function testCannotDeleteUsedCategory(): void
    {
        self::assertFalse($this->service->delete(3));
    }

    public function testCannotMoveSubtreeDeeperThanFour(): void
    {
        $newParent = $this->service->createApproved($this->data('test_category_service_new_parent', 8), 2);
        $root = $this->service->createApproved($this->data('test_category_service_subtree_root'), 2);
        $child = $this->service->createApproved($this->data('test_category_service_subtree_child', $root), 2);
        $this->service->createApproved($this->data('test_category_service_subtree_grand', $child), 2);

        try {
            $this->service->updateCategory(
                $root,
                $this->data('test_category_service_subtree_root', $newParent)
            );
            self::fail('Přesun příliš hlubokého podstromu měl selhat.');
        } catch (ValidationException $exception) {
            self::assertSame(
                'Přesun by překročil maximální hloubku 4.',
                $exception->errors()['parent_id']
            );
        }
    }

    public function testCannotDeleteCategoryWithChildren(): void
    {
        self::assertFalse($this->service->delete(1));
    }

    public function testCannotApproveAlreadyApprovedCategory(): void
    {
        $this->expectException(\App\Core\BusinessRuleException::class);
        $this->expectExceptionMessage('Kategorie už není ve stavu čeká na schválení.');

        $this->service->approve(1, 2);
    }

    public function testCannotApproveWithUnapprovedParent(): void
    {
        $parent = $this->service->createApproved($this->data('test_category_service_pending_parent'), 2);
        $child = $this->service->createProposal($this->data('test_category_service_pending_child', $parent), 3);

        $statement = $this->database->pdo()->prepare("UPDATE categories SET status = 'pending' WHERE id = ?");
        $statement->execute([$parent]);

        $this->expectException(\App\Core\BusinessRuleException::class);
        $this->expectExceptionMessage('Rodičovská kategorie musí být schválená.');
        $this->service->approve($child, 2);
    }

    public function testHeightOfSurvivesCycleInData(): void
    {
        $a = $this->service->createApproved($this->data('test_category_service_cycle_a'), 2);
        $b = $this->service->createApproved($this->data('test_category_service_cycle_b', $a), 2);

        $statement = $this->database->pdo()->prepare('UPDATE categories SET parent_id = ? WHERE id = ?');
        $statement->execute([$b, $a]);

        self::assertGreaterThan(0, $this->categories->heightOf($a));
    }

    public function testRejectedProposalResubmitsAsPending(): void
    {
        $id = $this->service->createProposal($this->data('test_category_service_reject', 7), 3);
        $this->service->reject($id, 2, 'Nevhodná kategorie.');

        $rejected = $this->service->find($id);
        self::assertSame('rejected', $rejected['status']);
        self::assertSame('Nevhodná kategorie.', $rejected['rejection_reason']);

        $this->service->updateProposal($id, 3, $this->data('test_category_service_reject', 7));

        $pending = $this->service->find($id);
        self::assertSame('pending', $pending['status']);
        self::assertNull($pending['rejection_reason']);
        self::assertNull($pending['reviewed_by']);
    }

    public function testSlugCollisionGetsSuffix(): void
    {
        $parentA = $this->service->createApproved($this->data('test_category_service_parent_a'), 2);
        $parentB = $this->service->createApproved($this->data('test_category_service_parent_b'), 2);

        $first = $this->service->createApproved($this->data('test_category_service_slug', $parentA), 2);
        $second = $this->service->createApproved($this->data('test_category_service_slug', $parentB), 2);

        $firstCategory = $this->service->find($first);
        $secondCategory = $this->service->find($second);
        self::assertNotSame($firstCategory['slug'], $secondCategory['slug']);
        self::assertSame('test-category-service-slug', $firstCategory['slug']);
        self::assertSame('test-category-service-slug-2', $secondCategory['slug']);
    }

    private function data(string $name, ?int $parentId = null): array
    {
        return [
            'name' => $name,
            'parent_id' => $parentId,
            'description' => 'Testovací kategorie.',
        ];
    }

    private function findNode(array $nodes, int $id): ?array
    {
        foreach ($nodes as $node) {
            if ((int) $node['id'] === $id) {
                return $node;
            }
            $found = $this->findNode($node['children'] ?? [], $id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function cleanTestCategories(): void
    {
        if ($this->database === null) {
            return;
        }

        $this->database->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->database->pdo()->exec("DELETE FROM categories WHERE name LIKE 'test_category_service%'");
        $this->database->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
