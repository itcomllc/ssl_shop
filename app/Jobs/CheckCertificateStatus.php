<?php

// Certificate Status Check Job
namespace App\Jobs;

use App\Models\CertificateOrder;
use App\Services\GoGetSSLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Notifications\CertificateIssuedNotification;

class CheckCertificateStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $order;

    public function __construct(CertificateOrder $order)
    {
        $this->order = $order;
    }

    public function handle(GoGetSSLService $gogetSSLService)
    {
        if (!$this->order->gogetssl_order_id || $this->order->status === 'issued') {
            return;
        }

        try {
            $status = $gogetSSLService->getOrderStatus($this->order->gogetssl_order_id);
            
            $statusMap = [
                'active' => 'issued',
                'processing' => 'processing',
                'expired' => 'expired',
                'cancelled' => 'failed'
            ];

            if (isset($statusMap[$status['status']])) {
                $newStatus = $statusMap[$status['status']];
                
                $this->order->update(['status' => $newStatus]);
                
                if ($status['status'] === 'active') {
                    // 証明書が発行された場合
                    $this->order->update(['expires_at' => $status['valid_till']]);
                    
                    // ユーザーに通知
                    $this->order->user->notify(new CertificateIssuedNotification($this->order));
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to check certificate status: ' . $e->getMessage(), [
                'order_id' => $this->order->id
            ]);
        }
    }
}