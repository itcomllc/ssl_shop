<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GoGetSSLService
{
    private $client;
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.gogetssl.api_key');
        $this->baseUrl = config('services.gogetssl.base_url', 'https://my.gogetssl.com/api');
    }

    /**
     * 利用可能な証明書商品を取得
     */
    public function getProducts()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/products/', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to fetch SSL products: ' . $e->getMessage());
        }
    }

    /**
     * 証明書を注文
     */
    public function createOrder($productId, $csr, $validityPeriod, $approverEmail, $domainName)
    {
        try {
            $response = $this->client->post($this->baseUrl . '/orders/', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'product_id' => $productId,
                    'csr' => $csr,
                    'validity_period' => $validityPeriod,
                    'approver_email' => $approverEmail,
                    'webserver_type' => 'other',
                    'dns_names' => [$domainName],
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to create SSL order: ' . $e->getMessage());
        }
    }

    /**
     * 注文ステータスを確認
     */
    public function getOrderStatus($orderId)
    {
        try {
            $response = $this->client->get($this->baseUrl . "/orders/{$orderId}/", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get order status: ' . $e->getMessage());
        }
    }

    /**
     * 証明書をダウンロード
     */
    public function downloadCertificate($orderId)
    {
        try {
            $response = $this->client->get($this->baseUrl . "/orders/{$orderId}/certificate/", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to download certificate: ' . $e->getMessage());
        }
    }

    /**
     * ドメイン検証用のファイルを取得
     */
    public function getDomainValidationFile($orderId)
    {
        try {
            $response = $this->client->get($this->baseUrl . "/orders/{$orderId}/domain-validation/", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get domain validation file: ' . $e->getMessage());
        }
    }

    /**
     * 証明書を再発行
     */
    public function reissue($orderId, $csr, $approverEmail = null)
    {
        try {
            $data = [
                'csr' => $csr,
            ];

            if ($approverEmail) {
                $data['approver_email'] = $approverEmail;
            }

            $response = $this->client->post($this->baseUrl . "/orders/{$orderId}/reissue/", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $data
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to reissue certificate: ' . $e->getMessage());
        }
    }

    /**
     * CSRからドメイン名を抽出
     */
    public function extractDomainFromCSR($csr)
    {
        $csrResource = openssl_csr_get_subject($csr);
        if ($csrResource && isset($csrResource['CN'])) {
            return $csrResource['CN'];
        }
        
        throw new \Exception('Unable to extract domain from CSR');
    }

    /**
     * CSRを検証
     */
    public function validateCSR($csr)
    {
        return openssl_csr_get_subject($csr) !== false;
    }
}
