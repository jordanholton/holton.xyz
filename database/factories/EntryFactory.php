<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publishedAt = fake()->dateTimeBetween('-1 year');

        return [
            'id' => $publishedAt->format('Y-m-d').'-'.fake()->unique()->numberBetween(1, 999),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'tags' => [],
            'published_at' => $publishedAt,
        ];
    }
}
