<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_url' => 'https://yandex.ru/maps/org/test/1234567890/',
            'normalized_url' => 'https://yandex.ru/maps/org/test/1234567890/',
            'yandex_organization_id' => '1234567890',
        ];
    }
}
