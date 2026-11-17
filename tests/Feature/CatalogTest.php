<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use App\Services\CategoryService;
use App\Services\VenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function testCategoryProposalIsPending(): void
    {
        $user = $this->user('navrhar');

        $category = $this->categories()->createProposal([
            'name' => 'Nová kategorie',
            'description' => null,
            'parent_id' => null,
        ], $user->id);

        $this->assertSame(Category::STATUS_PENDING, $category->status);
        $this->assertSame($user->id, $category->proposed_by);
        $this->assertSame('nova-kategorie', $category->slug);
    }

    public function testCategoryParentMustBeApproved(): void
    {
        $user = $this->user('navrhar');
        $pending = Category::query()->create([
            'name' => 'Čekající',
            'slug' => 'cekajici',
            'status' => Category::STATUS_PENDING,
            'proposed_by' => $user->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->categories()->createProposal([
            'name' => 'Potomek',
            'description' => null,
            'parent_id' => $pending->id,
        ], $user->id);
    }

    public function testCategoryDepthCannotExceedFour(): void
    {
        $user = $this->user('navrhar');
        $level1 = $this->category($user->id, null, 'Úroveň 1');
        $level2 = $this->category($user->id, $level1->id, 'Úroveň 2');
        $level3 = $this->category($user->id, $level2->id, 'Úroveň 3');
        $level4 = $this->category($user->id, $level3->id, 'Úroveň 4');

        $this->expectException(ValidationException::class);

        $this->categories()->createProposal([
            'name' => 'Úroveň 5',
            'description' => null,
            'parent_id' => $level4->id,
        ], $user->id);
    }

    public function testCategoryCannotBeMovedUnderItself(): void
    {
        $user = $this->user('navrhar');
        $parent = $this->category($user->id, null, 'Rodič');
        $child = $this->category($user->id, $parent->id, 'Potomek');

        $this->expectException(ValidationException::class);

        $this->categories()->updateCategory($parent, [
            'name' => $parent->name,
            'description' => null,
            'parent_id' => $child->id,
        ]);
    }

    public function testCategoryWithChildrenCannotBeDeleted(): void
    {
        $user = $this->user('navrhar');
        $parent = $this->category($user->id, null, 'Rodič');
        $this->category($user->id, $parent->id, 'Potomek');

        $this->expectException(DomainRuleException::class);

        $this->categories()->delete($parent);
    }

    public function testCategoryUsedByEventCannotBeDeleted(): void
    {
        $manager = $this->user('manazer');
        $event = $this->event($manager);

        $this->expectException(DomainRuleException::class);

        $this->categories()->delete($event->category);
    }

    public function testCategoryApprovalRequiresApprovedParent(): void
    {
        $user = $this->user('navrhar');
        $moderator = $this->moderator();
        $parent = Category::query()->create([
            'name' => 'Čekající rodič',
            'slug' => 'cekajici-rodic',
            'status' => Category::STATUS_PENDING,
            'proposed_by' => $user->id,
        ]);
        $child = Category::query()->create([
            'name' => 'Potomek',
            'slug' => 'potomek',
            'parent_id' => $parent->id,
            'status' => Category::STATUS_PENDING,
            'proposed_by' => $user->id,
        ]);

        $this->expectException(DomainRuleException::class);

        $this->categories()->approve($child, $moderator->id);
    }

    public function testApprovedOptionListExcludesSubtreeOfExcludedCategory(): void
    {
        $user = $this->user('navrhar');
        $root = $this->category($user->id, null, 'Kořen');
        $child = $this->category($user->id, $root->id, 'Dítě');

        $options = $this->categories()->approvedOptions($root);
        $ids = array_column($options, 'id');

        $this->assertNotContains($root->id, $ids);
        $this->assertNotContains($child->id, $ids);
    }

    public function testVenueProposalIsPending(): void
    {
        $user = $this->user('navrhar');

        $venue = $this->venues()->propose([
            'name' => 'Nové místo',
            'street' => 'Ulice 1',
            'city' => 'Brno',
            'postal_code' => '602 00',
            'description' => null,
        ], null, $user->id);

        $this->assertSame(Venue::STATUS_PENDING, $venue->status);
        $this->assertSame($user->id, $venue->proposed_by);
    }

    public function testModeratorVenueCreationIsApprovedImmediately(): void
    {
        $moderator = $this->moderator();

        $venue = $this->venues()->createApproved([
            'name' => 'Schválené místo',
            'street' => 'Ulice 2',
            'city' => 'Brno',
            'postal_code' => null,
            'description' => null,
        ], null, $moderator->id);

        $this->assertSame(Venue::STATUS_APPROVED, $venue->status);
        $this->assertSame($moderator->id, $venue->reviewed_by);
    }

    public function testVenueUsedByEventCannotBeDeleted(): void
    {
        $manager = $this->user('manazer');
        $event = $this->event($manager);

        $this->expectException(DomainRuleException::class);

        $this->venues()->delete($event->venue);
    }

    public function testModeratorApprovesVenueProposal(): void
    {
        $user = $this->user('navrhar');
        $moderator = $this->moderator();
        $venue = $this->venues()->propose([
            'name' => 'Místo ke schválení',
            'street' => 'Ulice 3',
            'city' => 'Brno',
            'postal_code' => null,
            'description' => null,
        ], null, $user->id);

        $this->venues()->approve($venue, $moderator->id);

        $this->assertSame(Venue::STATUS_APPROVED, $venue->fresh()->status);
    }

    public function testUserCannotEditApprovedVenueProposal(): void
    {
        $user = $this->user('navrhar');
        $venue = $this->venues()->createApproved([
            'name' => 'Schválené',
            'street' => 'Ulice 4',
            'city' => 'Brno',
            'postal_code' => null,
            'description' => null,
        ], null, $user->id);
        $venue->forceFill(['proposed_by' => $user->id])->save();

        $this->actingAs($user)
            ->get('/mista/' . $venue->id . '/upravit')
            ->assertForbidden();
    }

    public function testUserCanEditOwnPendingProposal(): void
    {
        $user = $this->user('navrhar');
        $venue = $this->venues()->propose([
            'name' => 'Vlastní návrh',
            'street' => 'Ulice 5',
            'city' => 'Brno',
            'postal_code' => null,
            'description' => null,
        ], null, $user->id);

        $this->actingAs($user)
            ->get('/mista/' . $venue->id . '/upravit')
            ->assertOk();
    }

    public function testProposalsPageListsOnlyOwnRecords(): void
    {
        $user = $this->user('navrhar');
        $other = $this->user('jiny');
        $own = $this->venues()->propose([
            'name' => 'Moje místo',
            'street' => 'Ulice 6',
            'city' => 'Brno',
            'postal_code' => null,
            'description' => null,
        ], null, $user->id);
        $foreign = $this->venues()->propose([
            'name' => 'Cizí místo',
            'street' => 'Ulice 7',
            'city' => 'Brno',
            'postal_code' => null,
            'description' => null,
        ], null, $other->id);

        $this->actingAs($user)
            ->get('/moje-navrhy')
            ->assertOk()
            ->assertSee($own->name)
            ->assertDontSee($foreign->name);
    }

    public function testModeratorCreatesCategoryThroughHttp(): void
    {
        $moderator = $this->moderator();

        $response = $this->actingAs($moderator)->post('/moderace/kategorie/nova', [
            'name' => 'Kategorie z formuláře',
            'description' => null,
            'parent_id' => null,
        ]);

        $response->assertRedirect('/moderace/kategorie');
        $this->assertDatabaseHas('categories', [
            'name' => 'Kategorie z formuláře',
            'status' => Category::STATUS_APPROVED,
        ]);
    }

    public function testCategoryFormKeepsValuesOnValidationError(): void
    {
        $moderator = $this->moderator();

        $response = $this->actingAs($moderator)
            ->from('/moderace/kategorie/nova')
            ->post('/moderace/kategorie/nova', ['name' => '', 'parent_id' => null]);

        $response->assertRedirect('/moderace/kategorie/nova');
        $response->assertSessionHasErrors('name');
    }

    private function categories(): CategoryService
    {
        return $this->app->make(CategoryService::class);
    }

    private function venues(): VenueService
    {
        return $this->app->make(VenueService::class);
    }
}
