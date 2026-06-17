<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'yandex_review_id' => $this->faker->unique()->slug,
            'author_name' => $this->faker->name,
            'rating' => $this->faker->randomFloat(1, 1, 5),
            'text' => $this->faker->paragraph,
            'publish_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
