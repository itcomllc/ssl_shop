<?php

namespace Database\Factories;

use App\Models\CertificateProduct;
use App\Models\CertificateOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CertificateOrder>
 */
class CertificateOrderFactory extends Factory
{
    protected $model = CertificateOrder::class;

    public function definition()
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'certificate_product_id' => CertificateProduct::factory(),
            'square_payment_id' => 'payment_' . $this->faker->uuid,
            'gogetssl_order_id' => 'ssl_' . $this->faker->randomNumber(6),
            'domain_name' => $this->faker->domainName,
            'status' => $this->faker->randomElement(['pending', 'processing', 'issued', 'failed']),
            'csr' => "-----BEGIN CERTIFICATE REQUEST-----\ntest\n-----END CERTIFICATE REQUEST-----",
            'total_amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'approver_email' => $this->faker->email,
            'expires_at' => $this->faker->dateTimeBetween('+1 month', '+2 years')
        ];
    }
}
