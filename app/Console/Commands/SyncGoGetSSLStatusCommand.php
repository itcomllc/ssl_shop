<?php

namespace App\Console\Commands;

use App\Models\CertificateOrder;
use Illuminate\Console\Command;

class SyncGoGetSSLStatusCommand extends Command
{
    protected $signature = 'certificates:sync-gogetssl-status';
    protected $description = 'Sync certificate order status with GoGetSSL API';

    public function handle(): int
    {
        $this->info('GoGetSSLステータスを同期しています...');
        
        // 処理中の注文を取得
        $processingOrders = CertificateOrder::whereIn('status', ['processing', 'pending'])
            ->whereNotNull('gogetssl_order_id')
            ->get();
            
        $updatedCount = 0;
        
        foreach ($processingOrders as $order) {
            try {
                // GoGetSSL APIでステータスチェック
                $status = $this->checkGoGetSSLStatus($order->gogetssl_order_id);
                
                if ($status !== $order->status) {
                    $order->update(['status' => $status]);
                    $updatedCount++;
                    $this->line("✓ {$order->domain_name} - ステータス更新: {$status}");
                }
                
            } catch (\Exception $e) {
                $this->error("✗ {$order->domain_name} - エラー: {$e->getMessage()}");
            }
        }
        
        $this->info("同期完了: {$updatedCount}件のステータスを更新しました");
        
        return self::SUCCESS;
    }
    
    private function checkGoGetSSLStatus(string $goGetSSLOrderId): string
    {
        // GoGetSSL APIの実装
        // 実際のAPI仕様に合わせて実装
        return 'processing';
    }
}
