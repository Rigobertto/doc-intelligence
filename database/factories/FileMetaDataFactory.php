<?php

namespace Database\Factories;

use App\Models\FileMetaData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileMetaData>
 */
class FileMetaDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => \App\Models\File::factory(),
            'data' => [
                'document_type' => 'Invoice',
                'total_amount' => $this->faker->randomFloat(2, 10, 1000),
            ],
            'confidence_level' => $this->faker->randomFloat(2, 0.8, 1.0),
        ];
    }
}
