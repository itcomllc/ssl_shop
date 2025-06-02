<?php

namespace App\Http\Controllers;

use App\Models\CertificateProduct;
use App\Models\CertificateOrder;
use App\Models\CertificateSubscription;
use App\Services\SquarePaymentService;
use App\Services\GoGetSSLService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CertificateController extends Controller
{
    private $squareService;
    private $gogetSSLService;

    public function __construct(SquarePaymentService $squareService, GoGetSSLService $gogetSSLService)
    {
        $this->squareService = $squareService;
        $this->gogetSSLService = $gogetSSLService;
    }

    /**
     * 証明書商品一覧
     */
    public function index()
    {
        $products = CertificateProduct::where('is_active', true)->get();
        return view('certificates.index', compact('products'));
    }

    /**
     * 証明書購入フォーム
     */
    public function create(CertificateProduct $product)
    {
        return view('certificates.create', compact('product'));
    }

    /**
     * 証明書注文処理
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
            return back()->withErrors(['csr' => 'Invalid CSR format']);
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
                'user_id' => auth()->id(),
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

            return redirect()->route('certificates.show', $order)
                ->with('success', '証明書の注文が完了しました。');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * 注文詳細表示
     */
    public function show(CertificateOrder $order)
    {
        $this->authorize('view', $order);
        
        // GoGetSSLでステータス更新
        $this->updateOrderStatus($order);
        
        return view('certificates.show', compact('order'));
    }

    /**
     * 証明書ダウンロード
     */
    public function download(CertificateOrder $order)
    {
        $this->authorize('view', $order);

        if ($order->status !== 'issued') {
            return back()->withErrors(['error' => '証明書はまだ発行されていません。']);
        }

        try {
            $certificate = $this->gogetSSLService->downloadCertificate($order->gogetssl_order_id);
            
            $order->update([
                'certificate_content' => $certificate['certificate'],
                'ca_bundle' => $certificate['ca_bundle'] ?? null,
            ]);

            $filename = $order->domain_name . '_certificate.crt';
            
            return response($certificate['certificate'])
                ->header('Content-Type', 'application/x-x509-ca-cert')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * 証明書再発行
     */
    public function reissue(Request $request, CertificateOrder $order)
    {
        $this->authorize('update', $order);

        $request->validate([
            'new_csr' => 'required|string',
            'approver_email' => 'sometimes|email'
        ]);

        if (!$this->gogetSSLService->validateCSR($request->new_csr)) {
            return back()->withErrors(['new_csr' => 'Invalid CSR format']);
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

            return back()->with('success', '証明書の再発行を開始しました。');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * サブスクリプション作成（最新SDK対応）
     */
    private function createSubscription(CertificateOrder $order, $paymentToken)
    {
        try {
            // 顧客作成または取得
            $customer = $this->squareService->createOrGetCustomer(
                auth()->user()->email,
                auth()->user()->name ?? 'SSL Customer',
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

            // サブスクリプション作成（事前にSquare Dashboardでプランを作成する必要があります）
            $planVariationId = config('services.square.ssl_plan_variation_id');
            
            if (!$planVariationId) {
                throw new \Exception('Subscription plan not configured. Please set SQUARE_SSL_PLAN_VARIATION_ID');
            }
            
            $subscription = $this->squareService->createSubscription(
                $planVariationId,
                $customer->getId(),
                $paymentToken // cardNonceを直接使用
            );

            CertificateSubscription::create([
                'user_id' => $order->user_id,
                'certificate_order_id' => $order->id,
                'square_subscription_id' => $subscription->getId(),
                'status' => 'active',
                'next_billing_date' => now()->addMonths($order->product->validity_period),
                'billing_interval' => 'yearly'
            ]);

            // サブスクリプション成功ログ
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
            
            // サブスクリプション作成に失敗してもエラーにしない（一回限りの購入は成功）
            // ただし、ユーザーには通知する
            session()->flash('subscription_warning', 'サブスクリプションの設定に失敗しましたが、証明書の購入は完了しました。サポートにお問い合わせください。');
        }
    }

    /**
     * サブスクリプション管理ページ
     */
    public function subscriptions()
    {
        $subscriptions = CertificateSubscription::with(['order.product'])
            ->where('user_id', auth()->id())
            ->get();

        return view('certificates.subscriptions', compact('subscriptions'));
    }

    /**
     * サブスクリプション詳細
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

        return view('certificates.subscription-detail', compact('subscription'));
    }

    /**
     * サブスクリプションキャンセル
     */
    public function cancelSubscription(CertificateSubscription $subscription)
    {
        $this->authorize('update', $subscription);

        try {
            $squareSubscription = $this->squareService->cancelSubscription($subscription->square_subscription_id);
            
            $subscription->update(['status' => 'cancelled']);

            return redirect()->route('certificates.subscriptions')
                ->with('success', 'サブスクリプションをキャンセルしました。');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'サブスクリプションのキャンセルに失敗しました: ' . $e->getMessage()]);
        }
    }

    /**
     * サブスクリプション一時停止
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

            return redirect()->route('certificates.subscriptions')
                ->with('success', 'サブスクリプションを一時停止しました。');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'サブスクリプションの一時停止に失敗しました: ' . $e->getMessage()]);
        }
    }

    /**
     * サブスクリプション再開
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

            return redirect()->route('certificates.subscriptions')
                ->with('success', 'サブスクリプションを再開しました。');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'サブスクリプションの再開に失敗しました: ' . $e->getMessage()]);
        }
    }
}

// Route定義例
/*
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/create/{product}', [CertificateController::class, 'create'])->name('certificates.create');
    Route::post('/certificates/{product}', [CertificateController::class, 'store'])->name('certificates.store');
    Route::get('/certificates/{order}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/certificates/{order}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::post('/certificates/{order}/reissue', [CertificateController::class, 'reissue'])->name('certificates.reissue');
});
*/