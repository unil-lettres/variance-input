<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Work>
 */
class WorkFactory extends Factory
{
    protected $model = Work::class;

    public function definition(): array
    {
        return [
            'author_id' => Author::factory(),
            'title' => fake()->unique()->sentence(3),
            'short_title' => fake()->unique()->lexify('w??'),
            'catalog_group' => 'main',
            'desc' => null,
            'image_url' => null,
            'pdf_url' => null,
            'folder' => null,
            'is_legacy' => false,
        ];
    }
}
