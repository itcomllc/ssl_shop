<?php

// =============================================================================
// 証明書更新失敗通知
// =============================================================================

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateRenewalFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificateOrder $originalOrder,
        public string $failureReason = '',
        public array $failureDetails = []
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isAutoRenewal = $this->originalOrder->subscription && $this->originalOrder->subscription->auto_renewal;
        $daysUntilExpiry = $this->originalOrder->expires_at ?
            now()->diffInDays($this->originalOrder->expires_at, false) : 0;

        return (new MailMessage)
            ->subject('[重要] SSL証明書の更新に失敗しました - ' . $this->originalOrder->domain_name)
            ->greeting($isAutoRenewal ? '自動更新失敗のお知らせ' : '証明書更新失敗のお知らせ')
            ->line('SSL証明書の更新処理が失敗いたしました。緊急の対応が必要です。')
            ->line('**証明書詳細:**')
            ->line('- ドメイン名: ' . $this->originalOrder->domain_name)
            ->line('- 証明書タイプ: ' . $this->originalOrder->product->name)
            ->line('- 現在の有効期限: ' . $this->originalOrder->expires_at?->format('Y年m月d日'))
            ->line('- 期限まで残り: ' . max(0, $daysUntilExpiry) . '日')
            ->when($this->failureReason, function ($mail) {
                return $mail->line('- 失敗理由: ' . $this->failureReason);
            })
            ->line('')
            ->when($daysUntilExpiry <= 7, function ($mail) {
                return $mail->line('⚠️ **緊急**: 証明書の期限切れまで1週間を切っています！');
            })
            ->when($daysUntilExpiry <= 30, function ($mail) {
                return $mail->line('⚠️ **重要**: 証明書の期限切れまで1ヶ月を切っています。');
            })
            ->line('証明書の期限切れを防ぐため、以下のいずれかの対応を行ってください：')
            ->line('1. 支払い方法を確認して手動で更新')
            ->line('2. サポートにお問い合わせ')
            ->when($isAutoRenewal, function ($mail) {
                return $mail->line('3. 自動更新設定の見直し');
            })
            ->action('今すぐ更新', route('certificates.renew', $this->originalOrder))
            ->line('ご不明な点がございましたら、お急ぎサポートまでお問い合わせください。')
            ->salutation('SSL Shop サポートチーム');
    }

    public function toArray(object $notifiable): array
    {
        $daysUntilExpiry = $this->originalOrder->expires_at ?
            now()->diffInDays($this->originalOrder->expires_at, false) : 0;

        return [
            'type' => 'certificate_renewal_failed',
            'certificate_order_id' => $this->originalOrder->id,
            'domain_name' => $this->originalOrder->domain_name,
            'expires_at' => $this->originalOrder->expires_at,
            'days_until_expiry' => max(0, $daysUntilExpiry),
            'failure_reason' => $this->failureReason,
            'failure_details' => $this->failureDetails,
            'is_auto_renewal' => $this->originalOrder->subscription?->auto_renewal ?? false,
            'urgency_level' => $daysUntilExpiry <= 7 ? 'critical' : ($daysUntilExpiry <= 30 ? 'high' : 'medium'),
            'message' => "SSL証明書「{$this->originalOrder->domain_name}」の更新に失敗しました。",
            'action_url' => route('certificates.renew', $this->originalOrder),
            'action_text' => '今すぐ更新'
        ];
    }
}
