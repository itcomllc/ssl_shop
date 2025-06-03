<?php

// =============================================================================
// 自動更新コマンド
// =============================================================================

namespace App\Console\Commands;

use App\Models\CertificateOrder;
use App\Services\CertificateRenewalService;
use Illuminate\Console\Command;

class ProcessCertificateRenewalsCommand extends Command
{
    protected $signature = 'certificates:process-renewals';
    protected $description = 'Process automatic certificate renewals';

    public function handle(): int
    {
        $this->info('自動更新対象の証明書を検索しています...');
        
        // 30日後に期限切れで自動更新が有効な証明書
        $renewalCandidates = CertificateOrder::where('status', 'issued')
            ->whereHas('subscription', function ($query) {
                $query->where('auto_renewal', true)
                      ->where('status', 'active');
            })
            ->whereDate('expires_at', now()->addDays(30)->toDateString())
            ->get();
            
        $renewalService = new CertificateRenewalService();
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($renewalCandidates as $certificateOrder) {
            $this->line("処理中: {$certificateOrder->domain_name}");
            
            $result = $renewalService->processRenewal($certificateOrder);
            
            if ($result['success']) {
                $this->info("✓ {$certificateOrder->domain_name} - 更新成功");
                $successCount++;
            } else {
                $this->error("✗ {$certificateOrder->domain_name} - 更新失敗: {$result['error']}");
                $failureCount++;
            }
        }
        
        $this->info("自動更新処理完了: 成功 {$successCount}件, 失敗 {$failureCount}件");
        
        return self::SUCCESS;
    }
}
