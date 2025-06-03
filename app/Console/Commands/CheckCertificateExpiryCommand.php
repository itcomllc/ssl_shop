<?php

namespace App\Console\Commands;

use App\Models\CertificateOrder;
use App\Notifications\CertificateExpiryWarningNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckCertificateExpiryCommand extends Command
{
    protected $signature = 'certificates:check-expiry';
    protected $description = 'Check for certificates that are expiring soon and send notifications';

    public function handle(): int
    {
        $this->info('証明書の有効期限をチェックしています...');

        // 期限切れ前の証明書を取得（90日、30日、7日、1日前）
        $warningPeriods = [90, 30, 7, 1];
        $totalNotifications = 0;

        foreach ($warningPeriods as $days) {
            // 有効期限が指定日数後の発行済み証明書を取得
            $expiringCertificates = CertificateOrder::where('status', 'issued')
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', Carbon::now()->addDays($days)->toDateString())
                ->with(['user', 'product'])
                ->get();

            foreach ($expiringCertificates as $certificateOrder) {
                try {
                    // ユーザーに通知を送信
                    $certificateOrder->user->notify(
                        new CertificateExpiryWarningNotification($certificateOrder, $days)
                    );

                    $totalNotifications++;
                    
                    $this->line("✓ {$certificateOrder->domain_name} (残り{$days}日) - 通知送信完了");
                    
                    // 通知履歴をログに記録
                    Log::info('Certificate expiry warning sent', [
                        'certificate_order_id' => $certificateOrder->id,
                        'domain_name' => $certificateOrder->domain_name,
                        'days_until_expiry' => $days,
                        'user_email' => $certificateOrder->user->email,
                        'gogetssl_order_id' => $certificateOrder->gogetssl_order_id
                    ]);

                } catch (\Exception $e) {
                    $this->error("✗ {$certificateOrder->domain_name} - 通知送信失敗: {$e->getMessage()}");
                    
                    Log::error('Certificate expiry warning failed', [
                        'certificate_order_id' => $certificateOrder->id,
                        'domain_name' => $certificateOrder->domain_name,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // 既に期限切れの証明書もチェック
        $expiredCertificates = CertificateOrder::where('status', 'issued')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        foreach ($expiredCertificates as $certificateOrder) {
            // ステータスを期限切れに更新
            $certificateOrder->update(['status' => 'expired']);
            $this->warn("⚠ {$certificateOrder->domain_name} - 期限切れのためステータスを更新しました");
            
            Log::info('Certificate expired status updated', [
                'certificate_order_id' => $certificateOrder->id,
                'domain_name' => $certificateOrder->domain_name,
                'expired_at' => $certificateOrder->expires_at
            ]);
        }

        // 結果表示
        if ($totalNotifications > 0) {
            $this->info("合計 {$totalNotifications} 件の有効期限警告通知を送信しました。");
        } else {
            $this->info('通知が必要な証明書はありませんでした。');
        }

        if ($expiredCertificates->count() > 0) {
            $this->info("期限切れ証明書: {$expiredCertificates->count()} 件のステータスを更新しました。");
        }

        return self::SUCCESS;
    }
}