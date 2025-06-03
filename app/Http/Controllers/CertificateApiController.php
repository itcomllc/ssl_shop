<?php

namespace App\Http\Controllers;

use App\Models\CertificateProduct;
use App\Models\CertificateOrder;
use App\Models\CertificateSubscription;
use App\Services\SquarePaymentService;
use App\Services\GoGetSSLService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CertificateApiController extends Controller
{
    private $squareService;

    public function __construct(SquarePaymentService $squareService, GoGetSSLService $gogetSSLService)
    {
        $this->squareService = $squareService;
        $this->gogetSSLService = $gogetSSLService;
    }

    /**
     * 証明書商品一覧（API）
     */
    public function index()
    {
        $products = CertificateProduct::where('is_active', true)->get();
        
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * 証明書注文処理（API）
     */
    public function store(Request $request, CertificateProduct $product)
    {
        $request->validate([
            'domain_name' => 'required|string|max:255',
            'csr' => 'required|string',
            'approver_email' => 'required|email',
            'payment_token' => 'required|string',
            'enable_subscription' => 'sometimes|boolean'
        ]);

        // CSR検証
        if (!$this->gogetSSLService->validateCSR($request->csr)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid CSR format'
            ], 422);
        }

        try {
            // Square決済処理
            $idempotencyKey = Str::uuid();
            $payment = $this->squareService->processPayment(
                $product->price,
                'USD',
                $request->payment_token,
                $idempotencyKey
            );

            // 注文レコード作成
            $order = CertificateOrder::create([
                'user_id' => Auth::id(),
                'certificate_product_id' => $product->id,
                'square_payment_id' => $payment->getId(),
                'domain_name' => $request->domain_name,
                'status' => 'pending',
                'csr' => $request->csr,
                'total_amount' => $product->price,
                'currency' => 'USD',
                'approver_email' => $request->approver_email,
            ]);

            // GoGetSSLで証明書注文
            $sslOrder = $this->gogetSSLService->createOrder(
                $product->gogetssl_product_id,
                $request->csr,
                $product->validity_period,
                $request->approver_email,
                $request->domain_name
            );

            $order->update([
                'gogetssl_order_id' => $sslOrder['order_id'],
                'status' => 'processing'
            ]);

            // サブスクリプション設定（オプション）
            if ($request->enable_subscription) {
                $this->createSubscription($order, $request->payment_token);
            }

            return response()->json([
                'success' => true,
                'message' => '証明書の注文が完了しました',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'payment_id' => $payment->getId()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 注文詳細表示（API）
     */
    public function show(CertificateOrder $order)
    {
        $this->authorize('view', $order);
        
        // GoGetSSLでステータス更新
        $this->updateOrderStatus($order);
        
        return response()->json([
            'success' => true,
            'data' => $order->load('product')
        ]);
    }

    /**
     * 証明書ダウンロード（API）
     */
    public function download(CertificateOrder $order)
    {
        $this->authorize('view', $order);

        if ($order->status !== 'issued') {
            return response()->json([
                'success' => false,
                'message' => '証明書はまだ発行されていません'
            ], 400);
        }

        try {
            $certificate = $this->gogetSSLService->downloadCertificate($order->gogetssl_order_id);
            
            $order->update([
                'certificate_content' => $certificate['certificate'],
                'ca_bundle' => $certificate['ca_bundle'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'certificate' => $certificate['certificate'],
                    'ca_bundle' => $certificate['ca_bundle'] ?? null,
                    'domain' => $order->domain_name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 証明書再発行（API）
     */
    public function reissue(Request $request, CertificateOrder $order)
    {
        $this->authorize('update', $order);

        $request->validate([
            'new_csr' => 'required|string',
            'approver_email' => 'sometimes|email'
        ]);

        if (!$this->gogetSSLService->validateCSR($request->new_csr)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid CSR format'
            ], 422);
        }

        try {
            $reissue = $this->gogetSSLService->reissue(
                $order->gogetssl_order_id,
                $request->new_csr,
                $request->approver_email
            );

            $order->update([
                'csr' => $request->new_csr,
                'status' => 'processing'
            ]);

            return response()->json([
                'success' => true,
                'message' => '証明書の再発行を開始しました'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * サブスクリプション一覧（API）
     */
    public function subscriptions()
    {
        $subscriptions = CertificateSubscription::with(['order.product'])
            ->where('user_id', Auth::id())
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }

    /**
     * サブスクリプション詳細（API）
     */
    public function showSubscription(CertificateSubscription $subscription)
    {
        $this->authorize('view', $subscription);

        try {
            // Square APIからサブスクリプションの最新情報を取得
            $squareSubscription = $this->squareService->getSubscription($subscription->square_subscription_id);
            
            // ローカル情報を更新
            $subscription->update([
                'status' => strtolower($squareSubscription->getStatus()),
                'next_billing_date' => $squareSubscription->getChargedThroughDate()
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to sync subscription data: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => $subscription->load('order.product')
        ]);
    }

    /**
     * サブスクリプションキャンセル（API）
     */
    public function cancelSubscription(CertificateSubscription $subscription)
    {
        $this->authorize('update', $subscription);

        try {
            $squareSubscription = $this->squareService->cancelSubscription($subscription->square_subscription_id);
            
            $subscription->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'サブスクリプションをキャンセルしました'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'サブスクリプションのキャンセルに失敗しました: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * サブスクリプション一時停止（API）
     */
    public function pauseSubscription(Request $request, CertificateSubscription $subscription)
    {
        $this->authorize('update', $subscription);

        $request->validate([
            'pause_date' => 'sometimes|date|after:today'
        ]);

        try {
            $pauseDate = $request->pause_date ?? null;
            $squareSubscription = $this->squareService->pauseSubscription(
                $subscription->square_subscription_id,
                $pauseDate
            );
            
            $subscription->update(['status' => 'paused']);

            return response()->json([
                'success' => true,
                'message' => 'サブスクリプションを一時停止しました'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'サブスクリプションの一時停止に失敗しました: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * サブスクリプション再開（API）
     */
    public function resumeSubscription(Request $request, CertificateSubscription $subscription)
    {
        $this->authorize('update', $subscription);

        $request->validate([
            'resume_date' => 'sometimes|date|after:today'
        ]);

        try {
            $resumeDate = $request->resume_date ?? null;
            $squareSubscription = $this->squareService->resumeSubscription(
                $subscription->square_subscription_id,
                $resumeDate
            );
            
            $subscription->update(['status' => 'active']);

            return response()->json([
                'success' => true,
                'message' => 'サブスクリプションを再開しました'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'サブスクリプションの再開に失敗しました: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * サブスクリプション作成（プライベートメソッド）
     */
    private function createSubscription(CertificateOrder $order, $paymentToken)
    {
        try {
            // 顧客作成または取得
            $customer = $this->squareService->createOrGetCustomer(
                Auth::user()->email,
                Auth::user()->name ?? 'SSL Customer',
                '' // family name
            );

            // カード検証（Cards APIの代替アプローチ）
            $cardVerification = $this->squareService->verifyCardForSubscription(
                $customer->getId(),
                $paymentToken
            );

            if (!$cardVerification['verified']) {
                throw new \Exception('Card verification failed for subscription');
            }

            // サブスクリプション作成
            $planVariationId = config('services.square.ssl_plan_variation_id');
            
            if (!$planVariationId) {
                throw new \Exception('Subscription plan not configured');
            }
            
            $subscription = $this->squareService->createSubscription(
                $planVariationId,
                $customer->getId(),
                $paymentToken
            );

            CertificateSubscription::create([
                'user_id' => $order->user_id,
                'certificate_order_id' => $order->id,
                'square_subscription_id' => $subscription->getId(),
                'status' => 'active',
                'next_billing_date' => now()->addMonths($order->product->validity_period),
                'billing_interval' => 'yearly'
            ]);

            Log::info('Subscription created successfully', [
                'order_id' => $order->id,
                'subscription_id' => $subscription->getId(),
                'customer_id' => $customer->getId()
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription creation failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

}