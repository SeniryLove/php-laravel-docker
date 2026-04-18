<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
        
    public function definition()
    {
        $status = $this->faker->randomElement(['draft', 'published']);
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->words(1, true),
            'price' => $this->faker->numberBetween(50, 1000),
            'stock' => $this->faker->numberBetween(1, 20),
            'image_url' => 'https://www.flaticon.com/free-icon/product_1311095',
            'status' => $status,
            'is_active'  => $status === 'published',
        ];
    }
}
