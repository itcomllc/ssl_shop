<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\SquarePaymentService;
use Square\SquareClient;
use Mockery;

class SquarePaymentServiceMockTest extends TestCase
{
 /** @test */
    public function it_handles_successful_payment()
    {
        // Squareクライアントをモック
        $mockClient = Mockery::mock(SquareClient::class);
        $mockPaymentsApi = Mockery::mock();
        
        $mockClient->shouldReceive('payments')->andReturn($mockPaymentsApi);
        
        $mockPayment = (object)[
            'getId' => 'payment_123',
            'getAmountMoney' => (object)[
                'getAmount' => 1000,
                'getCurrency' => 'USD'
            ],
            'getStatus' => 'COMPLETED'
        ];
        
        $mockResponse = (object)['getPayment' => fn() => $mockPayment];
        
        $mockPaymentsApi->shouldReceive('create')
            ->once()
            ->andReturn($mockResponse);
        
        // テスト実行
        $squareService = new SquarePaymentService();
        
        // リフレクションでクライアントを置き換え
        $reflection = new \ReflectionClass($squareService);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($squareService, $mockClient);
        
        $result = $squareService->processPayment(10.00, 'USD', 'test_token', uniqid());
        
        $this->assertEquals('payment_123', $result->getId());
        $this->assertEquals('COMPLETED', $result->getStatus());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
