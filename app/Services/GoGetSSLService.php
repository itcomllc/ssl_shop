<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GoGetSSLService
{
    private $client;
    private $username;
    private $password;
    private $baseUrl;
    private $partnerCode;
    private $authKey;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => config('services.gogetssl.timeout', 30),
            'verify' => true,
        ]);

        $this->username = config('services.gogetssl.username');
        $this->password = config('services.gogetssl.password');
        $this->baseUrl = config('services.gogetssl.base_url', 'https://my.gogetssl.com/api');
        $this->partnerCode = config('services.gogetssl.partner_code');
    }

    /**
     * Auth keyを取得（キャッシュ付き）
     */
    private function getAuthKey(): string
    {
        if ($this->authKey) {
            return $this->authKey;
        }

        // キャッシュから取得を試行（有効期限: 23時間）
        $cacheKey = 'gogetssl_auth_key_' . md5($this->username);
        $cachedAuthKey = Cache::get($cacheKey);

        if ($cachedAuthKey) {
            $this->authKey = $cachedAuthKey;
            return $this->authKey;
        }

        // 新しいauth_keyを取得（form-urlencoded形式）
        try {
            $response = $this->client->post($this->baseUrl . '/auth', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'user' => $this->username,
                    'pass' => $this->password
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (!isset($data['key'])) {
                throw new \Exception('Auth key not found in response: ' . json_encode($data));
            }

            $this->authKey = $data['key'];

            // 23時間キャッシュ（24時間より少し短く設定）
            Cache::put($cacheKey, $this->authKey, now()->addHours(23));

            Log::info('GoGetSSL auth key obtained successfully', [
                'username' => $this->username,
                'expires_at' => now()->addHours(23)->toDateTimeString()
            ]);

            return $this->authKey;
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null;

            Log::error('Failed to get GoGetSSL auth key', [
                'username' => $this->username,
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody
            ]);

            throw new \Exception('Failed to authenticate with GoGetSSL: ' . $e->getMessage());
        }
    }

    /**
     * 利用可能な証明書商品を取得
     */
    public function getProducts()
    {
        try {
            // GETリクエストでクエリパラメータとしてauth_keyを送信
            $response = $this->client->get($this->baseUrl . '/products', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL products fetched successfully', [
                'success' => $data['success'] ?? false,
                'product_count' => isset($data['products']) && is_array($data['products']) ? count($data['products']) : 0
            ]);

            return $data['products'] ?? [];
        } catch (RequestException $e) {
            $this->handleApiError('Failed to fetch SSL products', $e, []);
            throw new \Exception('Failed to fetch SSL products: ' . $e->getMessage());
        }
    }

    /**
     * 証明書を注文
     */
    public function createOrder($productId, $csr, $validityPeriod, $approverEmail, $domainName, $additionalParams = [])
    {
        return $this->createSSLOrderInternal('/orders/add_ssl_order', $productId, $csr, $validityPeriod, $approverEmail, $domainName, $additionalParams, 'SSL order');
    }

    public function addSSLRenewOrder($productId, $csr, $validityPeriod, $approverEmail, $domainName, $additionalParams = [])
    {
        return $this->createSSLOrderInternal('/orders/add_ssl_renew_order', $productId, $csr, $validityPeriod, $approverEmail, $domainName, $additionalParams, 'SSL renew order');
    }

    private function createSSLOrderInternal($endpoint, $productId, $csr, $validityPeriod, $approverEmail, $domainName, $additionalParams = [], $orderType = 'SSL order')
    {
        try {
            // 基本パラメータ
            $orderData = [
                'auth_key' => $this->getAuthKey(),
                'product_id' => $productId,
                'period' => $validityPeriod, // monthsで指定（12/24など）
                'csr' => $csr,
                'server_count' => $additionalParams['server_count'] ?? -1, // Unlimited servers
                'webserver_type' => $additionalParams['webserver_type'] ?? 1, // Apache
                'dcv_method' => $additionalParams['dcv_method'] ?? 'email',
                'signature_hash' => $additionalParams['signature_hash'] ?? 'SHA2',
            ];

            // DCV方法がemailの場合のみapprover_emailを追加
            if (($additionalParams['dcv_method'] ?? 'email') === 'email') {
                $orderData['approver_email'] = $approverEmail;
            }

            // SAN/Multi-Domain SSL用のDNS names
            if (isset($additionalParams['dns_names']) && is_array($additionalParams['dns_names'])) {
                $orderData['dns_names'] = implode(',', $additionalParams['dns_names']);
            }

            // 管理者情報（必須）
            $requiredAdminFields = [
                'admin_firstname',
                'admin_lastname',
                'admin_phone',
                'admin_title',
                'admin_email'
            ];

            foreach ($requiredAdminFields as $field) {
                if (isset($additionalParams[$field])) {
                    $orderData[$field] = $additionalParams[$field];
                } else {
                    throw new \Exception("Required parameter '{$field}' is missing");
                }
            }

            // OV/EV SSL用の追加管理者情報
            $ovEvAdminFields = [
                'admin_organization',
                'admin_addressline1',
                'admin_city',
                'admin_country',
                'admin_fax'
            ];

            foreach ($ovEvAdminFields as $field) {
                if (isset($additionalParams[$field])) {
                    $orderData[$field] = $additionalParams[$field];
                }
            }

            // その他の管理者情報（オプション）
            $optionalAdminFields = ['admin_postalcode', 'admin_region'];
            foreach ($optionalAdminFields as $field) {
                if (isset($additionalParams[$field])) {
                    $orderData[$field] = $additionalParams[$field];
                }
            }

            // 技術者情報（必須）
            $requiredTechFields = [
                'tech_firstname',
                'tech_lastname',
                'tech_phone',
                'tech_title',
                'tech_email'
            ];

            foreach ($requiredTechFields as $field) {
                if (isset($additionalParams[$field])) {
                    $orderData[$field] = $additionalParams[$field];
                } else {
                    throw new \Exception("Required parameter '{$field}' is missing");
                }
            }

            // OV/EV SSL用の追加技術者情報
            $ovEvTechFields = [
                'tech_organization',
                'tech_city',
                'tech_country'
            ];

            foreach ($ovEvTechFields as $field) {
                if (isset($additionalParams[$field])) {
                    $orderData[$field] = $additionalParams[$field];
                }
            }

            // その他の技術者情報（オプション）
            $optionalTechFields = ['tech_addressline1', 'tech_fax', 'tech_postalcode', 'tech_region'];
            foreach ($optionalTechFields as $field) {
                if (isset($additionalParams[$field])) {
                    $orderData[$field] = $additionalParams[$field];
                }
            }

            // 組織情報（OV/EV SSL用）
            $orgFields = [
                'org_name',
                'org_division',
                'org_duns',
                'org_addressline1',
                'org_city',
                'org_country',
                'org_fax',
                'org_phone',
                'org_postalcode',
                'org_region',
                'org_lei'
            ];

            foreach ($orgFields as $field) {
                if (isset($additionalParams[$field])) {
                    $orderData[$field] = $additionalParams[$field];
                }
            }

            // その他のオプション
            if (isset($additionalParams['approver_emails'])) {
                $orderData['approver_emails'] = is_array($additionalParams['approver_emails'])
                    ? implode(',', $additionalParams['approver_emails'])
                    : $additionalParams['approver_emails'];
            }

            if (isset($additionalParams['unique_code'])) {
                $orderData['unique_code'] = $additionalParams['unique_code'];
            }

            // テストモード
            if (isset($additionalParams['test']) && $additionalParams['test']) {
                $orderData['test'] = 'Y';
            }

            // Partner Codeが設定されている場合は追加
            if ($this->partnerCode) {
                $orderData['partner_order_id'] = $this->partnerCode . '_' . uniqid();
            }

            $response = $this->client->post($this->baseUrl . $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $orderData
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL ' . $orderType . ' created successfully', [
                'product_id' => $productId,
                'domain_name' => $domainName,
                'period' => $validityPeriod,
                'dcv_method' => $additionalParams['dcv_method'] ?? 'email',
                'order_id' => $data['order_id'] ?? null,
                'test_mode' => isset($additionalParams['test']) && $additionalParams['test'],
                'endpoint' => $endpoint
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to create ' . $orderType, $e, [
                'product_id' => $productId,
                'domain_name' => $domainName,
                'period' => $validityPeriod,
                'endpoint' => $endpoint
            ]);

            throw new \Exception('Failed to create ' . $orderType . ': ' . $e->getMessage());
        }
    }

    /**
     * シンプルなDV SSL注文作成（後方互換性用）
     */
    public function createSimpleDVOrder($productId, $csr, $validityPeriod, $approverEmail, $domainName, $adminInfo = [])
    {
        // DV SSL用の最小限のパラメータ
        $params = array_merge([
            'dcv_method' => 'email',
            'server_count' => -1,
            'webserver_type' => 1,
            'admin_firstname' => 'Admin',
            'admin_lastname' => 'User',
            'admin_phone' => '+1-555-123-4567',
            'admin_title' => 'Administrator',
            'admin_email' => $approverEmail,
            'tech_firstname' => 'Tech',
            'tech_lastname' => 'User',
            'tech_phone' => '+1-555-123-4567',
            'tech_title' => 'Technical Contact',
            'tech_email' => $approverEmail,
        ], $adminInfo);

        return $this->createOrder($productId, $csr, $validityPeriod, $approverEmail, $domainName, $params);
    }

    /**
     * Webサーバータイプを取得
     */
    public function getWebservers()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/tools/webservers', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL webservers fetched successfully');

            return $data['webservers'] ?? [];
        } catch (RequestException $e) {
            $this->handleApiError('Failed to fetch webservers', $e, []);
            throw new \Exception('Failed to fetch webservers: ' . $e->getMessage());
        }
    }

    /**
     * ドメインのDCV用メールアドレス一覧を取得
     */
    public function getDomainEmails($domain)
    {
        try {
            $response = $this->client->post($this->baseUrl . '/tools/domain/emails?auth_key=' . $this->getAuthKey(), [
                'form_params' => [
                    'domain' => $domain
                ]
            ]);
            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL domain emails fetched', [
                'domain' => $domain,
                'success' => $data['success'] ?? false,
                'comodo_emails' => isset($data['ComodoApprovalEmails']) ? count($data['ComodoApprovalEmails']) : 0,
                'geotrust_emails' => isset($data['GeotrustApprovalEmails']) ? count($data['GeotrustApprovalEmails']) : 0
            ]);
            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get domain emails', $e, [
                'domain' => $domain
            ]);

            throw new \Exception('Failed to get domain emails: ' . $e->getMessage());
        }
    }

    /**
     * ドメインの承認用メールアドレス一覧を取得（統合版）
     */
    public function getApprovalEmails($domain)
    {
        $response = $this->getDomainEmails($domain);

        if (!isset($response['success']) || !$response['success']) {
            return [];
        }

        // ComodoとGeotrustのメールアドレスを統合
        $emails = [];

        if (isset($response['ComodoApprovalEmails'])) {
            $emails = array_merge($emails, $response['ComodoApprovalEmails']);
        }

        if (isset($response['GeotrustApprovalEmails'])) {
            $emails = array_merge($emails, $response['GeotrustApprovalEmails']);
        }

        // 重複を除去して返す
        return array_unique($emails);
    }

    /**
     * 注文ステータスを確認
     */
    public function getOrderStatus($orderId)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/orders/status/' . $orderId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);
            $data = json_decode($response->getBody(), true);
            Log::debug('GoGetSSL order status retrieved', [
                'order_id' => $orderId,
                'status' => $data['status'] ?? 'unknown'
            ]);
            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get order status', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to get order status: ' . $e->getMessage());
        }
    }

    /**
     * 証明書をダウンロード
     */
    public function downloadCertificate($orderId)
    {
        try {
            $orderStatus = $this->getOrderStatus($orderId);

            if (empty($orderStatus['crt_code'])) {
                throw new \Exception('Certificate not ready yet. Current status: ' . ($orderStatus['status'] ?? 'unknown'));
            }

            $data = [
                'certificate' => $orderStatus['crt_code'],
                'ca_bundle' => $orderStatus['ca_code'] ?? '',
                'order_status' => $orderStatus['status'],
                'valid_from' => $orderStatus['valid_from'] ?? null,
                'valid_till' => $orderStatus['valid_till'] ?? null
            ];

            Log::info('GoGetSSL certificate downloaded', [
                'order_id' => $orderId,
                'has_certificate' => !empty($data['certificate']),
                'has_ca_bundle' => !empty($data['ca_bundle']),
                'status' => $orderStatus['status']
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to download certificate', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to download certificate: ' . $e->getMessage());
        }
    }

    /**
     * ドメイン検証用の情報を取得
     */
    public function getDomainValidationFile($orderId)
    {
        try {
            // まずオーダーステータスからCSRを取得
            $orderStatus = $this->getOrderStatus($orderId);

            if (empty($orderStatus['csr_code'])) {
                throw new \Exception('CSR code not found for order: ' . $orderId);
            }

            $response = $this->client->post($this->baseUrl . '/tools/domain/alternative/', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'auth_key' => $this->getAuthKey(),
                    'csr' => $orderStatus['csr_code']
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL domain validation info retrieved', [
                'order_id' => $orderId,
                'has_http_validation' => isset($data['validation']['http']),
                'has_https_validation' => isset($data['validation']['https']),
                'has_dns_validation' => isset($data['validation']['dns'])
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get domain validation file', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to get domain validation file: ' . $e->getMessage());
        }
    }

    /**
     * 証明書を再発行
     */
    public function reissue($orderId, $csr, $options = [])
    {
        try {
            $data = [
                'csr' => $csr,
                'webserver_type' => $options['webserver_type'] ?? 3,
                'dcv_method' => $options['dcv_method'] ?? 'http',
                'signature_hash' => $options['signature_hash'] ?? 'SHA2'
            ];

            // オプショナルパラメータ
            if (!empty($options['dns_names'])) {
                $data['dns_names'] = $options['dns_names'];
            }
            if (!empty($options['approver_emails'])) {
                $data['approver_emails'] = $options['approver_emails'];
            }
            if (!empty($options['approver_email'])) {
                $data['approver_email'] = $options['approver_email'];
            }
            if (!empty($options['unique_code'])) {
                $data['unique_code'] = $options['unique_code'];
            }

            $response = $this->client->put($this->baseUrl . '/orders/ssl/reissue/' . $orderId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $data
            ]);

            $responseData = json_decode($response->getBody(), true);

            Log::info('GoGetSSL certificate reissued', [
                'order_id' => $orderId,
                'order_status' => $responseData['order_status'] ?? 'unknown',
                'success' => $responseData['success'] ?? false,
                'has_validation' => isset($responseData['validation']),
                'webserver_type' => $data['webserver_type'],
                'dcv_method' => $data['dcv_method']
            ]);

            return $responseData;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to reissue certificate', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to reissue certificate: ' . $e->getMessage());
        }
    }

    /**
     * アカウント情報を取得
     */
    public function getAccountInfo()
    {
        try {
            $response = $this->client->post($this->baseUrl . '/account', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL account info retrieved successfully');

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get account info', $e, []);
            throw new \Exception('Failed to get account info: ' . $e->getMessage());
        }
    }

    /**
     * 残高を取得
     */
    public function getBalance()
    {
        try {
            $response = $this->client->post($this->baseUrl . '/account/balance', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL balance retrieved', [
                'balance' => $data['balance'] ?? 'unknown'
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get balance', $e, []);
            throw new \Exception('Failed to get balance: ' . $e->getMessage());
        }
    }

    /**
     * 全商品の価格を取得
     */
    public function getAllProductPrices()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/products/all_prices', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL all product prices retrieved', [
                'product_count' => isset($data['product_prices']) ? count($data['product_prices']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get all product prices', $e, []);
            throw new \Exception('Failed to get all product prices: ' . $e->getMessage());
        }
    }

    /**
     * 特定の商品の詳細を取得
     */
    public function getProductDetails($productId)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/products/details/' . $productId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL product details retrieved', [
                'product_id' => $productId,
                'product_name' => $data['product_name'] ?? 'unknown',
                'product_type' => $data['product_type'] ?? 'unknown',
                'product_brand' => $data['product_brand'] ?? 'unknown',
                'is_multidomain' => $data['product_is_multidomain'] ?? 'no',
                'supports_wildcard' => $data['product_wildcard'] ?? 'no',
                'price_options_count' => isset($data['product_prices']) ? count($data['product_prices']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get product details', $e, [
                'product_id' => $productId
            ]);
            throw new \Exception('Failed to get product details: ' . $e->getMessage());
        }
    }

    /**
     * 特定商品の価格を取得
     */
    public function getProductPrice($productId)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/products/price/' . $productId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL product price retrieved', [
                'product_id' => $productId,
                'price_options_count' => isset($data['product_price']) ? count($data['product_price']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get product price', $e, [
                'product_id' => $productId
            ]);
            throw new \Exception('Failed to get product price: ' . $e->getMessage());
        }
    }

    /**
     * SSL商品一覧を取得
     */
    public function getSslProducts()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/products/ssl', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL SSL products retrieved', [
                'products_count' => isset($data['products']) ? count($data['products']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get SSL products', $e, []);
            throw new \Exception('Failed to get SSL products: ' . $e->getMessage());
        }
    }

    /**
     * 特定のSSL商品の詳細を取得
     */
    public function getSslProduct($productId)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/products/ssl/' . $productId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL SSL product retrieved', [
                'product_id' => $productId,
                'product_name' => $data['product']['product'] ?? 'unknown',
                'brand' => $data['product']['brand'] ?? 'unknown',
                'product_type' => $data['product']['product_type'] ?? 'unknown',
                'san_enabled' => $data['product']['san_enabled'] ?? 0,
                'wildcard_enabled' => $data['product']['wildcard_enabled'] ?? 0,
                'max_period' => $data['product']['max_period'] ?? 'unknown',
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get SSL product', $e, [
                'product_id' => $productId
            ]);
            throw new \Exception('Failed to get SSL product: ' . $e->getMessage());
        }
    }

    /**
     * CSRをデコード
     */
    public function decodeCSR($csr)
    {
        try {
            $response = $this->client->post($this->baseUrl . '/tools/csr/decode', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'csr' => $csr
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL CSR decoded', [
                'common_name' => $data['csrResult']['CN'] ?? 'unknown',
                'organization' => $data['csrResult']['O'] ?? 'unknown',
                'country' => $data['csrResult']['C'] ?? 'unknown',
                'key_size' => $data['csrResult']['Key Size'] ?? 'unknown',
                'has_san_items' => !empty($data['csrResult']['dnsName(s)']),
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to decode CSR', $e, [
                'csr_length' => strlen($csr)
            ]);
            throw new \Exception('Failed to decode CSR: ' . $e->getMessage());
        }
    }

    /**
     * CSRを生成
     * @param array $csrData CSR生成に必要なデータ ['csr_commonname', 'csr_organization', 'csr_department', 'csr_city', 'csr_state', 'csr_country', 'csr_email']
     * @return array CSR生成結果
     */
    public function generateCSR($csrData)
    {
        try {
            $requiredFields = ['csr_commonname', 'csr_organization', 'csr_department', 'csr_city', 'csr_state', 'csr_country', 'csr_email'];

            // 必須フィールドのチェック
            foreach ($requiredFields as $field) {
                if (empty($csrData[$field])) {
                    throw new \Exception("Required field '{$field}' is missing");
                }
            }

            $response = $this->client->post($this->baseUrl . '/tools/csr/generate', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'csr_commonname' => $csrData['csr_commonname'],
                    'csr_organization' => $csrData['csr_organization'],
                    'csr_department' => $csrData['csr_department'],
                    'csr_city' => $csrData['csr_city'],
                    'csr_state' => $csrData['csr_state'],
                    'csr_country' => $csrData['csr_country'],
                    'csr_email' => $csrData['csr_email']
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL CSR generated', [
                'common_name' => $csrData['csr_commonname'],
                'organization' => $csrData['csr_organization'],
                'country' => $csrData['csr_country'],
                'has_csr_code' => !empty($data['csr_code']),
                'has_private_key' => !empty($data['csr_key']),
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to generate CSR', $e, [
                'common_name' => $csrData['csr_commonname'] ?? 'unknown'
            ]);
            throw new \Exception('Failed to generate CSR: ' . $e->getMessage());
        }
    }

    /**
     * SANオーダーを追加
     * @param int $orderId オーダーID
     * @param int|null $singleSanCount シングルSANの数（nullの場合は指定なし）
     * @param int|null $wildcardSanCount ワイルドカードSANの数（nullの場合は指定なし）
     * @return array APIレスポンス
     */
    public function addSSLSANOrder($orderId, $singleSanCount = null, $wildcardSanCount = null)
    {
        try {
            if (empty($singleSanCount) && empty($wildcardSanCount)) {
                throw new \Exception('At least one of single_san_count or wildcard_san_count must be specified');
            }

            $formParams = [
                'order_id' => $orderId
            ];

            if (!empty($singleSanCount)) {
                $formParams['single_san_count'] = $singleSanCount;
            }

            if (!empty($wildcardSanCount)) {
                $formParams['wildcard_san_count'] = $wildcardSanCount;
            }

            $response = $this->client->post($this->baseUrl . '/orders/add_ssl_san_order', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $formParams
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL SAN order added', [
                'order_id' => $orderId,
                'single_san_count' => $singleSanCount,
                'wildcard_san_count' => $wildcardSanCount,
                'invoice_id' => $data['invoice_id'] ?? 'unknown',
                'order_amount' => $data['order_amount'] ?? 'unknown',
                'currency' => $data['currency'] ?? 'unknown',
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to add SSL SAN order', $e, [
                'order_id' => $orderId,
                'single_san_count' => $singleSanCount,
                'wildcard_san_count' => $wildcardSanCount
            ]);
            throw new \Exception('Failed to add SSL SAN order: ' . $e->getMessage());
        }
    }

    /**
     * SSLオーダーをキャンセル
     * @param int $orderId オーダーID
     * @param string $reason キャンセル理由
     * @return array APIレスポンス
     */
    public function cancelOrder($orderId, $reason)
    {
        try {
            $response = $this->client->post($this->baseUrl . '/orders/cancel_ssl_order/', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'order_id' => $orderId,
                    'reason' => $reason
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL order canceled', [
                'order_id' => $orderId,
                'reason' => $reason,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to cancel order', $e, [
                'order_id' => $orderId,
                'reason' => $reason
            ]);
            throw new \Exception('Failed to cancel order: ' . $e->getMessage());
        }
    }

    /**
     * 注文の共通情報を取得
     * @param string|null $status ステータスフィルター（nullの場合は全ての注文を取得）
     * @return array 注文の共通情報
     */
    public function getOrderCommonDetails($status = null)
    {
        try {
            $queryParams = [
                'auth_key' => $this->getAuthKey()
            ];

            if ($status) {
                $queryParams['status'] = $status;
            }

            $response = $this->client->get($this->baseUrl . '/orders', [
                'query' => $queryParams
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL order common details retrieved', [
                'status_filter' => $status ?? 'all',
                'orders_count' => is_array($data) ? count($data) : 0,
                'success' => true
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get order common details', $e, [
                'status_filter' => $status ?? 'all'
            ]);
            throw new \Exception('Failed to get order common details: ' . $e->getMessage());
        }
    }

    /**
     * 注文のステータスを取得
     * @param array|string|null $orderIds 注文IDの配列または単一の注文ID（nullの場合は全ての注文を取得）
     * @param int|null $limit 取得する件数（nullの場合は制限なし）
     * @param int|null $offset オフセット（nullの場合は0）
     * @return array 注文ステータスの情報
     */
    public function getOrderStatuses($orderIds = null, $limit = null, $offset = null)
    {
        try {
            $queryParams = [
                'auth_key' => $this->getAuthKey()
            ];

            if ($limit !== null) {
                $queryParams['limit'] = $limit;
            }

            if ($offset !== null) {
                $queryParams['offset'] = $offset;
            }

            $formParams = [];

            if ($orderIds !== null) {
                if (is_array($orderIds)) {
                    $formParams['cids'] = implode(',', $orderIds);
                } else {
                    $formParams['cids'] = $orderIds;
                }
            }

            $response = $this->client->post($this->baseUrl . '/orders/statuses', [
                'query' => $queryParams,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $formParams
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL order statuses retrieved', [
                'order_ids_filter' => $orderIds ? (is_array($orderIds) ? implode(',', $orderIds) : $orderIds) : 'all',
                'limit' => $limit,
                'offset' => $offset,
                'certificates_count' => isset($data['certificates']) ? count($data['certificates']) : 0,
                'success' => $data['success'] ?? false,
                'time_stamp' => $data['time_stamp'] ?? null
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get order statuses', $e, [
                'order_ids_filter' => $orderIds ? (is_array($orderIds) ? implode(',', $orderIds) : $orderIds) : 'all',
                'limit' => $limit,
                'offset' => $offset
            ]);
            throw new \Exception('Failed to get order statuses: ' . $e->getMessage());
        }
    }

    /**
     * CAAレコードの再チェック
     * @param int $orderId オーダーID
     * @return array APIレスポンス
     */
    public function recheckCAA($orderId)
    {
        try {
            $response = $this->client->post($this->baseUrl . '/orders/ssl/recheck-caa/' . $orderId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['error'] ?? false) {
                Log::warning('GoGetSSL CAA recheck failed', [
                    'order_id' => $orderId,
                    'error' => $data['error'],
                    'message' => $data['message'] ?? null,
                    'description' => $data['description'] ?? null
                ]);
            } else {
                Log::info('GoGetSSL CAA recheck successful', [
                    'order_id' => $orderId,
                    'success' => true
                ]);
            }

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to recheck CAA', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to recheck CAA: ' . $e->getMessage());
        }
    }

    /**
     * 未払いの注文を取得
     */
    public function getUnpaidOrders()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/orders/list/unpaid', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL unpaid orders retrieved', [
                'unpaid_orders_count' => isset($data['orders']) ? count($data['orders']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get unpaid orders', $e, []);
            throw new \Exception('Failed to get unpaid orders: ' . $e->getMessage());
        }
    }

    /**
     * 全てのSSL注文を取得
     * @param int|null $limit 取得する件数（nullの場合は制限なし）
     * @param int|null $offset オフセット（nullの場合は0）
     * @return array 全てのSSL注文の情報
     */
    public function getAllSSLOrders($limit = null, $offset = null)
    {
        try {
            $queryParams = [
                'auth_key' => $this->getAuthKey()
            ];

            if ($limit !== null) {
                $queryParams['limit'] = $limit;
            }

            if ($offset !== null) {
                $queryParams['offset'] = $offset;
            }

            $response = $this->client->get($this->baseUrl . '/orders/ssl/all', [
                'query' => $queryParams
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL all SSL orders retrieved', [
                'limit' => $data['limit'] ?? $limit,
                'offset' => $data['offset'] ?? $offset,
                'count' => $data['count'] ?? 0,
                'orders_returned' => isset($data['orders']) ? count($data['orders']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get all SSL orders', $e, [
                'limit' => $limit,
                'offset' => $offset
            ]);
            throw new \Exception('Failed to get all SSL orders: ' . $e->getMessage());
        }
    }

    /**
     * 全ての注文の合計数を取得
     */
    public function getTotalOrders()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/account/total_orders', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL total orders retrieved', [
                'total_orders' => $data['total_orders'] ?? 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get total orders', $e, []);
            throw new \Exception('Failed to get total orders: ' . $e->getMessage());
        }
    }

    /**
     * ドメインの検証方法を変更
     * @param int $orderId オーダーID
     * @param array|string $domains ドメイン名の配列またはカンマ区切りの文字列
     * @param array|string $newMethods 新しい検証方法の配列またはカンマ区切りの文字列
     * @return array APIレスポンス
     */
    public function changeDomainsValidationMethod($orderId, $domains, $newMethods)
    {
        try {
            $formParams = [];

            if (is_array($domains)) {
                $formParams['domains'] = implode(',', $domains);
            } else {
                $formParams['domains'] = $domains;
            }

            if (is_array($newMethods)) {
                $formParams['new_methods'] = implode(',', $newMethods);
            } else {
                $formParams['new_methods'] = $newMethods;
            }

            $response = $this->client->post($this->baseUrl . '/orders/ssl/change_domains_validation_method/' . $orderId . '/', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $formParams
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL domain validation method changed', [
                'order_id' => $orderId,
                'domains' => $formParams['domains'],
                'new_methods' => $formParams['new_methods'],
                'message' => $data['message'] ?? null,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to change domain validation method', $e, [
                'order_id' => $orderId,
                'domains' => is_array($domains) ? implode(',', $domains) : $domains,
                'new_methods' => is_array($newMethods) ? implode(',', $newMethods) : $newMethods
            ]);
            throw new \Exception('Failed to change domain validation method: ' . $e->getMessage());
        }
    }

    /**
     * ドメインの検証方法を変更
     * @param int $orderId オーダーID
     * @param string $domain ドメイン名
     * @param string $newMethod 新しい検証方法
     * @return array APIレスポンス
     */
    public function changeValidationMethod($orderId, $domain, $newMethod)
    {
        try {
            $response = $this->client->post($this->baseUrl . '/orders/ssl/change_validation_method/' . $orderId . '/', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'domain' => $domain,
                    'new_method' => $newMethod
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL validation method changed', [
                'order_id' => $orderId,
                'domain' => $domain,
                'new_method' => $newMethod,
                'message' => $data['message'] ?? null,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to change validation method', $e, [
                'order_id' => $orderId,
                'domain' => $domain,
                'new_method' => $newMethod
            ]);
            throw new \Exception('Failed to change validation method: ' . $e->getMessage());
        }
    }

    /**
     * 検証メールアドレスを変更
     * @param int $orderId オーダーID
     * @param string $approverEmail 新しい承認者メールアドレス
     * @param array|null $sanApproval SAN承認配列（オプション）
     * @return array APIレスポンス
     */
    public function changeValidationEmail($orderId, $approverEmail, $sanApproval = null)
    {
        try {
            $formParams = [
                'approver_email' => $approverEmail
            ];

            // SAN承認配列の処理
            if ($sanApproval && is_array($sanApproval)) {
                foreach ($sanApproval as $index => $san) {
                    if (isset($san['name']) && isset($san['method'])) {
                        $formParams["san_approval[{$index}][name]"] = $san['name'];
                        $formParams["san_approval[{$index}][method]"] = $san['method'];
                    }
                }
            }

            $response = $this->client->post($this->baseUrl . '/orders/ssl/change_validation_email/' . $orderId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $formParams
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL validation email changed', [
                'order_id' => $orderId,
                'approver_email' => $approverEmail,
                'san_approval_count' => $sanApproval ? count($sanApproval) : 0,
                'dcv_method' => $data['dcv_method'] ?? 'unknown',
                'has_http_validation' => isset($data['http']),
                'has_https_validation' => isset($data['https']),
                'has_dns_validation' => isset($data['dns']),
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to change validation email', $e, [
                'order_id' => $orderId,
                'approver_email' => $approverEmail,
                'san_approval_count' => $sanApproval ? count($sanApproval) : 0
            ]);
            throw new \Exception('Failed to change validation email: ' . $e->getMessage());
        }
    }

    /**
     * 検証メールを再送信
     * @param int $orderId オーダーID
     * @return array APIレスポンス
     */
    public function resendValidationEmail($orderId)
    {
        try {
            $response = $this->client->post($this->baseUrl . '/orders/ssl/resend_validation_email/' . $orderId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL validation email resent', [
                'order_id' => $orderId,
                'message' => $data['message'] ?? null,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to resend validation email', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to resend validation email: ' . $e->getMessage());
        }
    }

    /**
     * ドメイン検証方法を変更
     * @param int $orderId オーダーID
     * @param string $domainName ドメイン名
     * @param string $newMethod 新しい検証方法（例: 'email', 'http', 'https', 'dns'）
     * @param string|null $approverEmail メール検証の場合の承認者メールアドレス（オプション）
     * @return array APIレスポンス
     */
    public function changeDcv($orderId, $domainName, $newMethod, $approverEmail = null)
    {
        try {
            $formParams = [
                'domain_name' => $domainName,
                'new_method' => $newMethod
            ];

            // メール検証の場合は承認者メールが必須
            if ($newMethod === 'email') {
                if (empty($approverEmail)) {
                    throw new \Exception('approver_email is required when new_method is email');
                }
                $formParams['approver_email'] = $approverEmail;
            }

            $response = $this->client->post($this->baseUrl . '/orders/ssl/change_dcv/' . $orderId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $formParams
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL DCV method changed', [
                'order_id' => $orderId,
                'domain_name' => $domainName,
                'new_method' => $newMethod,
                'approver_email' => $approverEmail,
                'product_id' => $data['product_id'] ?? null,
                'has_validation' => isset($data['validation']),
                'success_message' => $data['success_message'] ?? null,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to change DCV method', $e, [
                'order_id' => $orderId,
                'domain_name' => $domainName,
                'new_method' => $newMethod,
                'approver_email' => $approverEmail
            ]);
            throw new \Exception('Failed to change DCV method: ' . $e->getMessage());
        }
    }

    /**
     * ドメインの再検証をリクエスト
     * @param int $orderId オーダーID
     * @param string $domain ドメイン名
     * @return array APIレスポンス
     */
    public function revalidate($orderId, $domain)
    {
        try {
            $response = $this->client->post($this->baseUrl . '/orders/ssl/revalidate/' . $orderId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'domain' => $domain
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL domain revalidation requested', [
                'order_id' => $orderId,
                'domain' => $domain,
                'message' => $data['message'] ?? null,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to revalidate domain', $e, [
                'order_id' => $orderId,
                'domain' => $domain
            ]);
            throw new \Exception('Failed to revalidate domain: ' . $e->getMessage());
        }
    }

    /**
     * Use 'resend' method in order to resend validation email for specified domain from order matching “order_id” parameter.
     * @param int $orderId オーダーID
     * @param string $domain ドメイン名
     * @return array APIレスポンス
     */
    public function resend($orderId, $domain)
    {
        try {
            $response = $this->client->post($this->baseUrl . '/orders/ssl/resend/' . $orderId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'domain' => $domain
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL domain validation resent', [
                'order_id' => $orderId,
                'domain' => $domain,
                'message' => $data['message'] ?? null,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to resend domain validation', $e, [
                'order_id' => $orderId,
                'domain' => $domain
            ]);
            throw new \Exception('Failed to resend domain validation: ' . $e->getMessage());
        }
    }

    /**
     * 全ての請求書を取得
     */
    public function getAllInvoices()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/account/invoices', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL all invoices retrieved', [
                'invoices_count' => isset($data['invoices']) ? count($data['invoices']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get all invoices', $e, []);
            throw new \Exception('Failed to get all invoices: ' . $e->getMessage());
        }
    }

    /**
     * 未払いの請求書を取得
     */
    public function getUnpaidInvoices()
    {
        try {
            $response = $this->client->get($this->baseUrl . '/account/invoices/unpaid', [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL unpaid invoices retrieved', [
                'unpaid_invoices_count' => isset($data['invoices']) ? count($data['invoices']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get unpaid invoices', $e, []);
            throw new \Exception('Failed to get unpaid invoices: ' . $e->getMessage());
        }
    }

    /**
     * 請求書の主要情報を取得
     */
    public function getOrderInvoice($orderId)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/orders/invoice/' . $orderId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL order invoice retrieved', [
                'order_id' => $orderId,
                'invoice_number' => $data['number'] ?? 'unknown',
                'total' => $data['total'] ?? 'unknown',
                'currency' => $data['currency'] ?? 'unknown',
                'status' => $data['status'] ?? 'unknown',
                'payment_method' => $data['payment_method'] ?? null,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get order invoice', $e, [
                'order_id' => $orderId
            ]);
            throw new \Exception('Failed to get order invoice: ' . $e->getMessage());
        }
    }

    /**
     * 指定期間の請求書リストを取得
     * @param string $dateFrom 開始日（YYYY-MM-DD形式）
     * @param string $dateTill 終了日（YYYY-MM-DD形式）
     * @return array APIレスポンス
     */
    public function getInvoiceListByPeriod($dateFrom, $dateTill)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/invoice', [
                'query' => [
                    'auth_key' => $this->getAuthKey(),
                    'date_from' => $dateFrom,
                    'date_till' => $dateTill
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL invoice list by period retrieved', [
                'date_from' => $dateFrom,
                'date_till' => $dateTill,
                'invoices_count' => isset($data['invoices']) ? count($data['invoices']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get invoice list by period', $e, [
                'date_from' => $dateFrom,
                'date_till' => $dateTill
            ]);
            throw new \Exception('Failed to get invoice list by period: ' . $e->getMessage());
        }
    }

    /**
     * 請求書の詳細を取得
     * @param int $invoiceId 請求書ID
     * @return array APIレスポンス
     */
    public function getInvoiceDetails($invoiceId)
    {
        try {
            $response = $this->client->get($this->baseUrl . '/invoice/' . $invoiceId, [
                'query' => [
                    'auth_key' => $this->getAuthKey()
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('GoGetSSL invoice details retrieved', [
                'invoice_id' => $invoiceId,
                'invoice_number' => $data['invoice']['number'] ?? 'unknown',
                'total' => $data['invoice']['total'] ?? 'unknown',
                'currency' => $data['invoice']['currency'] ?? 'unknown',
                'status' => $data['invoice']['status'] ?? 'unknown',
                'payment_method' => $data['invoice']['payment_method'] ?? null,
                'items_count' => isset($data['invoice']['items']) ? count($data['invoice']['items']) : 0,
                'success' => $data['success'] ?? false
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->handleApiError('Failed to get invoice details', $e, [
                'invoice_id' => $invoiceId
            ]);
            throw new \Exception('Failed to get invoice details: ' . $e->getMessage());
        }
    }

    /**
     * 接続テスト
     */
    public function testConnection(): bool
    {
        try {
            $this->getAuthKey();
            return true;
        } catch (\Exception $e) {
            Log::error('GoGetSSL connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Auth keyキャッシュをクリア
     */
    public function clearAuthCache(): void
    {
        $cacheKey = 'gogetssl_auth_key_' . md5($this->username);
        Cache::forget($cacheKey);
        $this->authKey = null;

        Log::info('GoGetSSL auth key cache cleared');
    }

    /**
     * CSRからドメイン名を抽出
     */
    public function extractDomainFromCSR($csr)
    {
        try {
            // CSRの形式を正規化
            $csr = trim($csr);
            if (!str_starts_with($csr, '-----BEGIN')) {
                $csr = "-----BEGIN CERTIFICATE REQUEST-----\n" .
                    chunk_split($csr, 64, "\n") .
                    "-----END CERTIFICATE REQUEST-----";
            }

            $csrResource = openssl_csr_get_subject($csr);

            if ($csrResource && isset($csrResource['CN'])) {
                Log::debug('Domain extracted from CSR', [
                    'domain' => $csrResource['CN']
                ]);
                return $csrResource['CN'];
            }

            throw new \Exception('Common Name (CN) not found in CSR');
        } catch (\Exception $e) {
            Log::error('Failed to extract domain from CSR', [
                'error' => $e->getMessage(),
                'csr_length' => strlen($csr)
            ]);

            throw new \Exception('Unable to extract domain from CSR: ' . $e->getMessage());
        }
    }

    /**
     * CSRを検証
     */
    public function validateCSR($csr)
    {
        try {
            // CSRの形式を正規化
            $csr = trim($csr);
            if (!str_starts_with($csr, '-----BEGIN')) {
                $csr = "-----BEGIN CERTIFICATE REQUEST-----\n" .
                    chunk_split($csr, 64, "\n") .
                    "-----END CERTIFICATE REQUEST-----";
            }

            $result = openssl_csr_get_subject($csr) !== false;

            Log::debug('CSR validation result', [
                'is_valid' => $result,
                'csr_length' => strlen($csr)
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('CSR validation failed', [
                'error' => $e->getMessage(),
                'csr_length' => strlen($csr)
            ]);

            return false;
        }
    }

    /**
     * APIエラーの共通処理
     */
    private function handleApiError(string $message, RequestException $e, array $context): void
    {
        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
        $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null;

        // 認証エラーの場合はキャッシュをクリア
        if ($statusCode === 401 || $statusCode === 403) {
            $this->clearAuthCache();
        }

        Log::error($message, array_merge($context, [
            'error' => $e->getMessage(),
            'status_code' => $statusCode,
            'response_body' => $responseBody
        ]));
    }
}
