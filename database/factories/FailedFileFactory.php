<?php

namespace Database\Factories;

use App\Models\FailedFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailedFile>
 */
class FailedFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_name' => $this->faker->word() . '.png',
            'url' => $this->faker->url(),
        ];
    }
}
