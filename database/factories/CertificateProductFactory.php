<?php

namespace Database\Factories;

use App\Models\CertificateProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CertificateProduct>
 */
class CertificateProductFactory extends Factory
{
    protected $model = CertificateProduct::class;

    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph,
            'gogetssl_product_id' => $this->faker->randomNumber(3),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'validity_period' => $this->faker->randomElement([12, 24, 36]),
            'domain_count' => $this->faker->randomElement([1, 5, 10]),
            'wildcard_support' => $this->faker->boolean,
            'is_active' => true,
            'features' => ['256-bit encryption', '99.9% browser compatibility']
        ];
    }
}
