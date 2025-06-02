<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Services\SquarePaymentService;
use Square\Exceptions\SquareApiException;

class SquarePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private $squareService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->squareService = new SquarePaymentService();
    }

    /** @test */
    public function it_can_get_locations()
    {
        try {
            $locations = $this->squareService->getLocations();
            $this->assertIsArray($locations);

            if (!empty($locations)) {
                $this->assertArrayHasKey('id', $locations[0]);
                $this->assertArrayHasKey('name', $locations[0]);
            }
        } catch (SquareApiException $e) {
            $this->markTestSkipped('Square API not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_payment_errors_gracefully()
    {
        $this->expectException(\Exception::class);

        // 無効なpayment tokenでテスト
        $this->squareService->processPayment(
            10.00,
            'USD',
            'invalid_token',
            uniqid()
        );
    }

    /** @test */
    public function it_can_create_customer()
    {
        try {
            $customer = $this->squareService->createCustomer(
                'Test',
                'User',
                'test+' . uniqid() . '@example.com'
            );

            $this->assertNotNull($customer);
            $this->assertEquals('Test', $customer->getGivenName());
            $this->assertEquals('User', $customer->getFamilyName());
        } catch (SquareApiException $e) {
            $this->markTestSkipped('Square API not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_can_find_existing_customer()
    {
        $email = 'existing+' . uniqid() . '@example.com';

        try {
            // 顧客作成
            $originalCustomer = $this->squareService->createCustomer(
                'Existing',
                'Customer',
                $email
            );

            // 同じメールで検索
            $foundCustomer = $this->squareService->findCustomerByEmail($email);

            $this->assertNotNull($foundCustomer);
            $this->assertEquals($originalCustomer->getId(), $foundCustomer->getId());
        } catch (SquareApiException $e) {
            $this->markTestSkipped('Square API not available: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_returns_null_for_non_existent_customer()
    {
        $foundCustomer = $this->squareService->findCustomerByEmail('nonexistent@example.com');
        $this->assertNull($foundCustomer);
    }

    /** @test */
    public function webhook_verification_works()
    {
        $body = '{"test": "data"}';
        $url = 'https://example.com/webhook';
        $secret = 'test_secret';

        // 正しい署名を生成
        $expectedSignature = base64_encode(hash_hmac('sha256', $url . $body, $secret, true));

        // 設定をモック
        config(['services.square.webhook_signature_key' => $secret]);

        $isValid = $this->squareService->verifyWebhook($expectedSignature, $body, $url);
        $this->assertTrue($isValid);

        // 無効な署名をテスト
        $isInvalid = $this->squareService->verifyWebhook('invalid_signature', $body, $url);
        $this->assertFalse($isInvalid);
    }
}
