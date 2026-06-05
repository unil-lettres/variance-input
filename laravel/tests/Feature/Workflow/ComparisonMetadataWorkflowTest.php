<?php

namespace Tests\Feature\Workflow;

use App\Models\Comparison;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComparisonMetadataWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_public_catalog_number_and_prefix(): void
    {
        $user = $this->signInEditor();
        $comparison = $this->createComparisonForUser($user, [
            'number' => 1,
            'prefix_label' => 'Auto Run',
            'publication_scope' => 'dev',
        ]);

        $response = $this->patchJson("/comparisons/{$comparison->id}/metadata", [
            'number' => '0.1',
            'prefix_label' => '« La Belle au bois dormant »,',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('number', 0.1)
            ->assertJsonPath('prefix_label', '« La Belle au bois dormant »,');

        $comparison->refresh();
        $this->assertEquals(0.1, $comparison->number);
        $this->assertSame('« La Belle au bois dormant »,', $comparison->prefix_label);
    }

    public function test_saving_number_only_preserves_existing_prefix(): void
    {
        $user = $this->signInEditor();
        $comparison = $this->createComparisonForUser($user, [
            'number' => 1,
            'prefix_label' => 'Fragment',
        ]);

        $this->patchJson("/comparisons/{$comparison->id}/metadata", [
            'number' => '0.4',
        ])->assertOk()
            ->assertJsonPath('number', 0.4)
            ->assertJsonPath('prefix_label', 'Fragment');

        $comparison->refresh();
        $this->assertEquals(0.4, $comparison->number);
        $this->assertSame('Fragment', $comparison->prefix_label);
    }

    public function test_admin_can_save_legacy_public_catalog_metadata(): void
    {
        $admin = $this->signInAdmin();
        $comparison = $this->createComparisonForUser($admin, [
            'created_by' => null,
            'is_legacy' => true,
            'number' => 1,
            'prefix_label' => null,
        ]);

        $this->patchJson("/comparisons/{$comparison->id}/metadata", [
            'number' => '0.2',
            'prefix_label' => 'Préface',
        ])->assertOk()
            ->assertJsonPath('number', 0.2)
            ->assertJsonPath('prefix_label', 'Préface');

        $comparison->refresh();
        $this->assertEquals(0.2, $comparison->number);
        $this->assertSame('Préface', $comparison->prefix_label);
    }

    public function test_non_admin_cannot_save_legacy_public_catalog_metadata(): void
    {
        $owner = User::factory()->create([
            'is_admin' => false,
            'password' => bcrypt('password'),
        ]);
        $comparison = $this->createComparisonForUser($owner, [
            'created_by' => null,
            'is_legacy' => true,
            'number' => 1,
        ]);

        $this->signInEditor();

        $this->patchJson("/comparisons/{$comparison->id}/metadata", [
            'number' => '0.3',
        ])->assertForbidden();

        $comparison->refresh();
        $this->assertEquals(1, $comparison->number);
    }

    public function test_reorder_preserves_custom_decimal_numbers(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Ordre public',
            'short_title' => 'orp',
        ]);
        $firstSource = Version::factory()->for($work)->create(['folder' => '1orp']);
        $firstTarget = Version::factory()->for($work)->create(['folder' => '2orp']);
        $secondSource = Version::factory()->for($work)->create(['folder' => '3orp']);
        $secondTarget = Version::factory()->for($work)->create(['folder' => '4orp']);
        $first = Comparison::factory()->create([
            'source_id' => $firstSource->id,
            'target_id' => $firstTarget->id,
            'folder' => '1orp-2orp',
            'created_by' => $user->id,
            'number' => 0.1,
            'sort_order' => 1,
        ]);
        $second = Comparison::factory()->create([
            'source_id' => $secondSource->id,
            'target_id' => $secondTarget->id,
            'folder' => '3orp-4orp',
            'created_by' => $user->id,
            'number' => 1,
            'sort_order' => 2,
        ]);

        $this->postJson("/comparisons/{$second->id}/reorder", [
            'direction' => 'up',
        ])->assertOk()
            ->assertJsonPath('status', 'ok');

        $first->refresh();
        $second->refresh();

        $this->assertEquals(0.1, $first->number);
        $this->assertEquals(1, $second->number);
        $this->assertEquals(2, $first->sort_order);
        $this->assertEquals(1, $second->sort_order);
    }

    public function test_deleting_comparison_compacts_sort_order_without_changing_public_numbers(): void
    {
        $user = $this->signInEditor();
        $work = $this->createEditableWork($user, [], [
            'title' => 'Ordre après suppression',
            'short_title' => 'oas',
        ]);
        $firstSource = Version::factory()->for($work)->create(['folder' => '1oas']);
        $firstTarget = Version::factory()->for($work)->create(['folder' => '2oas']);
        $secondSource = Version::factory()->for($work)->create(['folder' => '3oas']);
        $secondTarget = Version::factory()->for($work)->create(['folder' => '4oas']);
        $thirdSource = Version::factory()->for($work)->create(['folder' => '5oas']);
        $thirdTarget = Version::factory()->for($work)->create(['folder' => '6oas']);

        $first = Comparison::factory()->create([
            'source_id' => $firstSource->id,
            'target_id' => $firstTarget->id,
            'folder' => '1oas-2oas',
            'created_by' => $user->id,
            'number' => 0.1,
            'sort_order' => 1,
        ]);
        $second = Comparison::factory()->create([
            'source_id' => $secondSource->id,
            'target_id' => $secondTarget->id,
            'folder' => '3oas-4oas',
            'created_by' => $user->id,
            'number' => 1,
            'sort_order' => 2,
        ]);
        $third = Comparison::factory()->create([
            'source_id' => $thirdSource->id,
            'target_id' => $thirdTarget->id,
            'folder' => '5oas-6oas',
            'created_by' => $user->id,
            'number' => 2,
            'sort_order' => 3,
        ]);

        $this->deleteJson("/comparisons/{$first->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Comparison deleted');

        $second->refresh();
        $third->refresh();

        $this->assertEquals(1, $second->number);
        $this->assertEquals(2, $third->number);
        $this->assertEquals(1, $second->sort_order);
        $this->assertEquals(2, $third->sort_order);
    }

    private function createComparisonForUser(User $user, array $comparisonAttributes = []): Comparison
    {
        $work = $this->createEditableWork($user, [], [
            'title' => 'Métadonnées comparaison',
            'short_title' => 'mdc',
        ]);
        $source = Version::factory()->for($work)->create(['folder' => '1mdc']);
        $target = Version::factory()->for($work)->create(['folder' => '2mdc']);

        return Comparison::factory()->create($comparisonAttributes + [
            'source_id' => $source->id,
            'target_id' => $target->id,
            'folder' => '1mdc-2mdc',
            'created_by' => $user->id,
        ]);
    }
}
