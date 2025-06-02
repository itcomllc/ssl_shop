<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\GoGetSSLService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class GoGetSSLServiceTest extends TestCase
{
    public function test_can_fetch_products()
    {
        $mockResponse = new Response(200, [], json_encode([
            'products' => [
                ['id' => 1, 'name' => 'DV SSL'],
                ['id' => 2, 'name' => 'EV SSL']
            ]
        ]));

        $mock = new MockHandler([$mockResponse]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new GoGetSSLService();
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $client);

        $products = $service->getProducts();

        $this->assertArrayHasKey('products', $products);
        $this->assertCount(2, $products['products']);
    }

    public function test_csr_validation()
    {
        $validCSR = "-----BEGIN CERTIFICATE REQUEST-----
MIICWjCCAUICAQAwFTETMBEGA1UEAwwKZXhhbXBsZS5jb20wggEiMA0GCSqGSIb3
DQEBAQUAA4IBDwAwggEKAoIBAQC7vTNu...
-----END CERTIFICATE REQUEST-----";

        $invalidCSR = "invalid csr content";

        $service = new GoGetSSLService();

        // Note: This test might need to be adjusted based on actual OpenSSL behavior
        $this->assertTrue($service->validateCSR($validCSR));
        $this->assertFalse($service->validateCSR($invalidCSR));
    }
}
