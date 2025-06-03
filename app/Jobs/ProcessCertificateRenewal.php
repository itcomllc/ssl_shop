<?php
// Certificate Renewal Job
namespace App\Jobs;

use App\Models\CertificateSubscription;
use App\Models\CertificateOrder;
use App\Services\GoGetSSLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Notifications\CertificateRenewalNotification;
use App\Notifications\CertificateRenewalFailedNotification;
use Illuminate\Support\Facades\Log;

class ProcessCertificateRenewal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $subscription;

    public function __construct(CertificateSubscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function handle(GoGetSSLService $gogetSSLService)
    {
        $originalOrder = $this->subscription->order;
        
        try {
            // 新しい証明書注文を作成
            $renewalOrder = CertificateOrder::create([
                'user_id' => $originalOrder->user_id,
                'certificate_product_id' => $originalOrder->certificate_product_id,
                'domain_name' => $originalOrder->domain_name,
                'status' => 'pending',
                'csr' => $originalOrder->csr, // 既存のCSRを再利用、または新規生成
                'total_amount' => $originalOrder->total_amount,
                'currency' => $originalOrder->currency,
                'approver_email' => $originalOrder->approver_email,
            ]);

            // GoGetSSLで新しい証明書を注文
            $sslOrder = $gogetSSLService->createOrder(
                $originalOrder->product->gogetssl_product_id,
                $originalOrder->csr,
                $originalOrder->product->validity_period,
                $originalOrder->approver_email,
                $originalOrder->domain_name
            );

            $renewalOrder->update([
                'gogetssl_order_id' => $sslOrder['order_id'],
                'status' => 'processing'
            ]);

            // サブスクリプションの次回請求日を更新
            $this->subscription->update([
                'certificate_order_id' => $renewalOrder->id,
                'next_billing_date' => now()->addMonths($originalOrder->product->validity_period)
            ]);

            // ユーザーに通知
            $renewalOrder->user->notify(new CertificateRenewalNotification($originalOrder , $renewalOrder));

        } catch (\Exception $e) {
            Log::error('Certificate renewal failed: ' . $e->getMessage(), [
                'subscription_id' => $this->subscription->id,
                'order_id' => $originalOrder->id
            ]);

            // エラー通知
            $originalOrder->user->notify(new CertificateRenewalFailedNotification($originalOrder, $e->getMessage()));
        }
    }
}