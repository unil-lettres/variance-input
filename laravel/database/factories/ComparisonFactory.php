<?php

namespace Database\Factories;

use App\Models\Comparison;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comparison>
 */
class ComparisonFactory extends Factory
{
    protected $model = Comparison::class;

    public function definition(): array
    {
        return [
            'source_id' => Version::factory(),
            'target_id' => Version::factory(),
            'folder' => fake()->unique()->slug(),
            'number' => fake()->numberBetween(1, 99),
            'prefix_label' => 'Auto',
            'is_legacy' => false,
            'lg_pivot' => 7,
            'ratio' => 15,
            'case_sensitive' => false,
            'diacri_sensitive' => true,
            'created_by' => null,
            'publication_scope' => null,
        ];
    }
}
