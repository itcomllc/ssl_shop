<?php

// =============================================================================
// 更新処理のサービスクラス例
// =============================================================================

namespace App\Services;

use App\Models\CertificateOrder;
use App\Notifications\CertificateRenewalNotification;
use App\Notifications\CertificateRenewalFailedNotification;
use Illuminate\Support\Facades\Log;

class CertificateRenewalService
{
    public function processRenewal(CertificateOrder $originalOrder): array
    {
        try {
            // 新しい注文を作成
            $renewalOrder = $this->createRenewalOrder($originalOrder);
            
            // 決済処理
            $paymentResult = $this->processRenewalPayment($renewalOrder, $originalOrder);
            
            if ($paymentResult['success']) {
                // GoGetSSL APIで証明書更新
                $certResult = $this->renewCertificateWithGoGetSSL($renewalOrder);
                
                if ($certResult['success']) {
                    // 成功通知
                    $originalOrder->user->notify(
                        new CertificateRenewalNotification($originalOrder, $renewalOrder)
                    );
                    
                    return ['success' => true, 'renewal_order' => $renewalOrder];
                } else {
                    throw new \Exception('Certificate renewal failed: ' . $certResult['error']);
                }
            } else {
                throw new \Exception('Payment failed: ' . $paymentResult['error']);
            }
            
        } catch (\Exception $e) {
            // 失敗通知
            $originalOrder->user->notify(
                new CertificateRenewalFailedNotification(
                    $originalOrder, 
                    $e->getMessage(),
                    ['timestamp' => now(), 'attempt_type' => 'auto_renewal']
                )
            );
            
            Log::error('Certificate renewal failed', [
                'original_order_id' => $originalOrder->id,
                'domain_name' => $originalOrder->domain_name,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function createRenewalOrder(CertificateOrder $originalOrder): CertificateOrder
    {
        return CertificateOrder::create([
            'user_id' => $originalOrder->user_id,
            'certificate_product_id' => $originalOrder->certificate_product_id,
            'domain_name' => $originalOrder->domain_name,
            'csr' => $originalOrder->csr, // 既存のCSRを再利用
            'approver_email' => $originalOrder->approver_email,
            'total_amount' => $originalOrder->product->price, // 現在の価格
            'currency' => $originalOrder->currency,
            'status' => 'processing'
        ]);
    }
    
    private function processRenewalPayment(CertificateOrder $renewalOrder, CertificateOrder $originalOrder): array
    {
        // サブスクリプションの支払い方法を使用して決済
        // Square Subscriptions APIまたは保存済み支払い方法を使用
        // 実装は実際の決済設定に依存
        
        return ['success' => true, 'payment_id' => 'renewal_payment_id'];
    }
    
    private function renewCertificateWithGoGetSSL(CertificateOrder $renewalOrder): array
    {
        // GoGetSSL APIで証明書更新処理
        // 実装は実際のAPI仕様に依存
        
        return ['success' => true];
    }
}

