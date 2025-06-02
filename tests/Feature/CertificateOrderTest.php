<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\CertificateProduct;
use App\Models\CertificateOrder;
use Laravel\Sanctum\Sanctum;

class CertificateOrderTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->product = CertificateProduct::factory()->create([
            'price' => 99.99,
            'is_active' => true
        ]);
    }

    public function test_authenticated_user_can_view_certificate_products()
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/certificates');

        $response->assertStatus(200);
        $response->assertSee($this->product->name);
    }

    public function test_guest_cannot_purchase_certificate()
    {
        $response = $this->get("/certificates/create/{$this->product->id}");

        $response->assertRedirect('/login');
    }

    public function test_user_can_create_certificate_order()
    {
        Sanctum::actingAs($this->user);

        $orderData = [
            'domain_name' => 'example.com',
            'csr' => $this->generateTestCSR(),
            'approver_email' => 'admin@example.com',
            'payment_token' => 'test_token_123'
        ];

        // Mock Square Payment Service
        $this->mock(\App\Services\SquarePaymentService::class, function ($mock) {
            $mock->shouldReceive('processPayment')
                ->once()
                ->andReturn((object)['getId' => 'payment_123']);
        });

        // Mock GoGetSSL Service
        $this->mock(\App\Services\GoGetSSLService::class, function ($mock) {
            $mock->shouldReceive('validateCSR')->once()->andReturn(true);
            $mock->shouldReceive('createOrder')
                ->once()
                ->andReturn(['order_id' => 'ssl_order_123']);
        });

        $response = $this->post("/certificates/{$this->product->id}", $orderData);

        $response->assertRedirect();
        $this->assertDatabaseHas('certificate_orders', [
            'user_id' => $this->user->id,
            'domain_name' => 'example.com',
            'status' => 'processing'
        ]);
    }

    public function test_user_can_only_view_own_orders()
    {
        $otherUser = User::factory()->create();
        $order = CertificateOrder::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($this->user);

        $response = $this->get("/certificates/{$order->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_download_issued_certificate()
    {
        $order = CertificateOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'issued'
        ]);

        Sanctum::actingAs($this->user);

        // Mock GoGetSSL Service
        $this->mock(\App\Services\GoGetSSLService::class, function ($mock) {
            $mock->shouldReceive('downloadCertificate')
                ->once()
                ->andReturn(['certificate' => 'test_certificate_content']);
        });

        $response = $this->get("/certificates/{$order->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/x-x509-ca-cert');
    }

    private function generateTestCSR()
    {
        return "-----BEGIN CERTIFICATE REQUEST-----
MIICWjCCAUICAQAwFTETMBEGA1UEAwwKZXhhbXBsZS5jb20wggEiMA0GCSqGSIb3
DQEBAQUAA4IBDwAwggEKAoIBAQC7vTNu...
-----END CERTIFICATE REQUEST-----";
    }
}
