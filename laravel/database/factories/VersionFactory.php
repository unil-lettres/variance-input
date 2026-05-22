<?php

namespace Database\Factories;

use App\Models\Version;
use App\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Version>
 */
class VersionFactory extends Factory
{
    protected $model = Version::class;

    public function definition(): array
    {
        return [
            'work_id' => Work::factory(),
            'name' => fake()->sentence(2),
            'folder' => fake()->unique()->lexify('v????'),
            'is_legacy' => false,
            'pagination_done' => false,
            'pagination_done_at' => null,
            'pagination_done_by' => null,
            'ignored_pages' => [],
        ];
    }
}
