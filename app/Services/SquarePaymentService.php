<?php

namespace App\Services;

use Square\SquareClient;
use Square\Environments;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Customers\Requests\CreateCustomerRequest;
use Square\Customers\Requests\ListCustomersRequest;
use Square\Payments\Requests\CancelPaymentsRequest;
use Square\Subscriptions\Requests\CreateSubscriptionRequest;
use Square\Types\Money;
use Square\Types\Currency;
use Square\Types\SubscriptionSource;
use Square\Utils\WebhooksHelper;
use Square\Exceptions\SquareApiException;
use Illuminate\Support\Facades\Log;

class SquarePaymentService
{
    private $client;
    private $locationId;

    public function __construct()
    {
        $environment = config('services.square.environment', 'sandbox');
        $baseUrl = $environment === 'production' ? Environments::Production->value : Environments::Sandbox->value;

        $this->client = new SquareClient(
            config('services.square.access_token'),
            null,
            [
                'baseUrl' => $baseUrl,
                'timeout' => 30.0,
            ]
        );
        
        $this->locationId = config('services.square.location_id');
    }

    /**
     * 一回限りの支払いを処理
     */
    public function processPayment($amount, $currency, $sourceId, $idempotencyKey)
    {
        try {
            $amountMoney = new Money([
                'amount' => $amount * 100, // Square uses cents
                'currency' => Currency::Usd->value
            ]);

            $request = new CreatePaymentRequest([
                'idempotencyKey' => $idempotencyKey,
                'sourceId' => $sourceId,
                'amountMoney' => $amountMoney,
                'locationId' => $this->locationId
            ]);

            $response = $this->client->payments->create($request);
            return $response->getPayment();

        } catch (SquareApiException $e) {
            throw new \Exception('Payment processing failed: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Payment processing failed: ' . $e->getMessage());
        }
    }

    /**
     * 顧客を作成
     */
    public function createCustomer($givenName, $familyName, $emailAddress)
    {
        try {
            $request = new CreateCustomerRequest([
                'givenName' => $givenName,
                'familyName' => $familyName,
                'emailAddress' => $emailAddress
            ]);

            $response = $this->client->customers->create($request);
            return $response->getCustomer();

        } catch (SquareApiException $e) {
            throw new \Exception('Customer creation failed: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Customer creation failed: ' . $e->getMessage());
        }
    }

    /**
     * メールアドレスで顧客を検索
     */
    public function findCustomerByEmail($emailAddress)
    {
        try {
            $request = new ListCustomersRequest([
                'limit' => 1,
                'query' => [
                    'filter' => [
                        'emailAddress' => [
                            'exact' => $emailAddress
                        ]
                    ]
                ]
            ]);

            /** @var Square\Core\Pagination\Pager */
            $response = $this->client->customers->list($request);
            $customers = $response->getCustomers();
            
            return !empty($customers) ? $customers[0] : null;

        } catch (SquareApiException $e) {
            Log::warning('Customer search failed: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::warning('Customer search failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 顧客を作成または取得
     */
    public function createOrGetCustomer($emailAddress, $givenName = null, $familyName = null)
    {
        // まず既存顧客を検索
        $existingCustomer = $this->findCustomerByEmail($emailAddress);
        if ($existingCustomer) {
            return $existingCustomer;
        }

        // 新規顧客作成
        return $this->createCustomer($givenName, $familyName, $emailAddress);
    }

    /**
     * Payment Method検証（Cards API代替）
     */
    public function verifyCardForSubscription($customerId, $cardNonce)
    {
        try {
            // 1ドルの認証のみの支払いを作成
            $verifyAmount = new Money([
                'amount' => 100, // $1.00
                'currency' => Currency::Usd->value
            ]);

            $request = new CreatePaymentRequest([
                'idempotencyKey' => uniqid(),
                'sourceId' => $cardNonce,
                'amountMoney' => $verifyAmount,
                'locationId' => $this->locationId,
                'autocomplete' => false, // 認証のみ、自動完了しない
                'customerId' => $customerId
            ]);

            $response = $this->client->payments->create($request);
            $payment = $response->getPayment();
            
            // 認証のみの支払いをキャンセル
            try {
                $cancelRequest = new CancelPaymentsRequest(['paymentId' => $payment->getId()]);
                $this->client->payments->cancel($cancelRequest);
            } catch (\Exception $e) {
                Log::warning('Failed to cancel verification payment: ' . $e->getMessage());
            }

            return [
                'verified' => true,
                'payment_id' => $payment->getId(),
                'card_details' => $payment->getCardDetails()
            ];

        } catch (SquareApiException $e) {
            throw new \Exception('Card verification failed: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Card verification failed: ' . $e->getMessage());
        }
    }

    /**
     * サブスクリプション作成
     */
    public function createSubscription($planVariationId, $customerId, $cardNonce = null)
    {
        try {
            $subscriptionSource = new SubscriptionSource([
                'name' => 'SSL Certificate Subscription'
            ]);

            $subscriptionData = [
                'locationId' => $this->locationId,
                'planVariationId' => $planVariationId,
                'customerId' => $customerId,
                'source' => $subscriptionSource
            ];

            // cardNonceを直接使用（推奨アプローチ）
            if ($cardNonce) {
                $subscriptionData['cardNonce'] = $cardNonce;
            }

            $request = new CreateSubscriptionRequest($subscriptionData);
            $response = $this->client->subscriptions->create($request);
            
            return $response->getSubscription();

        } catch (SquareApiException $e) {
            throw new \Exception('Subscription creation failed: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Subscription creation failed: ' . $e->getMessage());
        }
    }

    /**
     * サブスクリプション取得
     */
    public function getSubscription($subscriptionId)
    {
        try {
            $response = $this->client->subscriptions->get($subscriptionId);
            return $response->getSubscription();

        } catch (SquareApiException $e) {
            throw new \Exception('Failed to retrieve subscription: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve subscription: ' . $e->getMessage());
        }
    }

    /**
     * サブスクリプション一覧取得
     */
    public function listSubscriptions($cursor = null, $limit = null)
    {
        try {
            $requestData = [];
            if ($cursor) $requestData['cursor'] = $cursor;
            if ($limit) $requestData['limit'] = $limit;

            $request = new \Square\Subscriptions\Requests\SearchSubscriptionsRequest($requestData);
            $response = $this->client->subscriptions->search($request);
            
            return [
                'subscriptions' => $response->getSubscriptions(),
                'cursor' => $response->getCursor()
            ];

        } catch (SquareApiException $e) {
            throw new \Exception('Failed to list subscriptions: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Failed to list subscriptions: ' . $e->getMessage());
        }
    }

    /**
     * サブスクリプション検索（顧客IDで）
     */
    public function searchSubscriptionsByCustomer($customerId)
    {
        try {
            $query = [
                'filter' => [
                    'customerId' => $customerId
                ]
            ];

            $request = new \Square\Subscriptions\Requests\SearchSubscriptionsRequest([
                'query' => $query
            ]);
            
            $response = $this->client->subscriptions->search($request);
            return $response->getSubscriptions();

        } catch (SquareApiException $e) {
            throw new \Exception('Failed to search subscriptions: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Failed to search subscriptions: ' . $e->getMessage());
        }
    }

    /**
     * サブスクリプションキャンセル
     */
    public function cancelSubscription($subscriptionId)
    {
        try {
            $response = $this->client->subscriptions->cancel($subscriptionId);
            return $response->getSubscription();

        } catch (SquareApiException $e) {
            throw new \Exception('Subscription cancellation failed: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Subscription cancellation failed: ' . $e->getMessage());
        }
    }

    /**
     * サブスクリプション一時停止
     */
    public function pauseSubscription($subscriptionId)
    {
        try {
            $response = $this->client->subscriptions->pause($subscriptionId);
            return $response->getSubscription();

        } catch (SquareApiException $e) {
            throw new \Exception('Subscription pause failed: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Subscription pause failed: ' . $e->getMessage());
        }
    }

    /**
     * サブスクリプション再開
     */
    public function resumeSubscription($subscriptionId)
    {
        try {
            $response = $this->client->subscriptions->resume($subscriptionId);
            return $response->getSubscription();

        } catch (SquareApiException $e) {
            throw new \Exception('Subscription resume failed: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Subscription resume failed: ' . $e->getMessage());
        }
    }

    /**
     * 支払い情報を取得
     */
    public function getPayment($paymentId)
    {
        try {
            $response = $this->client->payments->get($paymentId);
            return $response->getPayment();

        } catch (SquareApiException $e) {
            throw new \Exception('Failed to retrieve payment: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve payment: ' . $e->getMessage());
        }
    }

    /**
     * 返金処理
     */
    public function refundPayment($paymentId, $amount, $currency, $reason = '')
    {
        try {
            $refundMoney = new Money([
                'amount' => $amount * 100,
                'currency' => Currency::Usd->value
            ]);

            $requestData = [
                'idempotencyKey' => uniqid(),
                'amountMoney' => $refundMoney,
                'paymentId' => $paymentId
            ];

            if ($reason) {
                $requestData['reason'] = $reason;
            }

            $request = new \Square\Refunds\Requests\RefundPaymentRequest($requestData);
            $response = $this->client->refunds->refundPayment($request);
            
            return $response->getRefund();

        } catch (SquareApiException $e) {
            throw new \Exception('Refund failed: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Refund failed: ' . $e->getMessage());
        }
    }

    /**
     * 場所一覧取得
     */
    public function getLocations()
    {
        try {
            $response = $this->client->locations->list();
            return $response->getLocations();

        } catch (SquareApiException $e) {
            throw new \Exception('Failed to retrieve locations: ' . $e->getMessage() . ' - Body: ' . $e->getBody());
        } catch (\Exception $e) {
            throw new \Exception('Failed to retrieve locations: ' . $e->getMessage());
        }
    }

    /**
     * Webhook検証
     */
    public function verifyWebhook($signature, $body, $url)
    {
        try {
            $webhookSignatureKey = config('services.square.webhook_signature_key');
            
            return WebhooksHelper::verifySignature(
                requestBody: $body,
                signatureHeader: $signature,
                signatureKey: $webhookSignatureKey,
                notificationUrl: $url
            );
        } catch (\Exception $e) {
            Log::error('Webhook verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 顧客カード情報を削除（廃止予定のCards API代替）
     */
    public function deleteCustomerCard($customerId, $cardId)
    {
        Log::warning('Cards API is deprecated. Card deletion not available in new SDK.');
        
        // Cards APIが廃止されているため、実際の削除は行えない
        // 代替として、顧客情報から関連付けを削除する等の処理を実装
        return [
            'message' => 'Card deletion not supported in current API version',
            'deprecated' => true
        ];
    }
}

// config/services.php設定例
/*
'square' => [
    'application_id' => env('SQUARE_APPLICATION_ID'),
    'access_token' => env('SQUARE_ACCESS_TOKEN'),
    'location_id' => env('SQUARE_LOCATION_ID'),
    'webhook_signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY'),
    'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
    'ssl_plan_variation_id' => env('SQUARE_SSL_PLAN_VARIATION_ID'),
],
*/