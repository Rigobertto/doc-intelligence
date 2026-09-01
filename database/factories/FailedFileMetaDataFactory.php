<?php

namespace Database\Factories;

use App\Models\FailedFileMetaData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailedFileMetaData>
 */
class FailedFileMetaDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'failed_file_id' => \App\Models\FailedFile::factory(),
            'data' => [
                'error' => 'Could not parse image',
            ],
            'confidence_level' => $this->faker->randomFloat(2, 0.1, 0.5),
        ];
    }
}
