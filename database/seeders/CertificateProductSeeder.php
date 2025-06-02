<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CertificateProduct;

class CertificateProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Domain Validated SSL',
                'description' => 'Basic SSL certificate for single domain',
                'gogetssl_product_id' => '1',
                'price' => 29.99,
                'validity_period' => 12,
                'domain_count' => 1,
                'wildcard_support' => false,
                'is_active' => true,
                'features' => ['Domain validation', '256-bit encryption', '99.9% browser compatibility']
            ],
            [
                'name' => 'Organization Validated SSL',
                'description' => 'Enhanced SSL with organization validation',
                'gogetssl_product_id' => '2',
                'price' => 89.99,
                'validity_period' => 12,
                'domain_count' => 1,
                'wildcard_support' => false,
                'is_active' => true,
                'features' => ['Organization validation', '256-bit encryption', 'Trust indicators']
            ],
            [
                'name' => 'Wildcard SSL',
                'description' => 'SSL certificate for unlimited subdomains',
                'gogetssl_product_id' => '3',
                'price' => 199.99,
                'validity_period' => 12,
                'domain_count' => 999,
                'wildcard_support' => true,
                'is_active' => true,
                'features' => ['Wildcard support', 'Unlimited subdomains', '256-bit encryption']
            ]
        ];

        foreach ($products as $product) {
            CertificateProduct::create($product);
        }
    }
}
