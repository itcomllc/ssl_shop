<?php

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateExpiryWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificateOrder $certificateOrder,
        public int $daysUntilExpiry
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $urgencyLevel = $this->getUrgencyLevel();
        
        return (new MailMessage)
            ->subject($this->getSubject())
            ->greeting($this->getGreeting())
            ->line($this->getMainMessage())
            ->line('**証明書詳細:**')
            ->line('- ドメイン名: ' . $this->certificateOrder->domain_name)
            ->line('- 証明書タイプ: ' . $this->certificateOrder->product->name)
            ->line('- 有効期限: ' . $this->certificateOrder->expires_at?->format('Y年m月d日'))
            ->line('- 残り日数: ' . $this->daysUntilExpiry . '日')
            ->when($urgencyLevel === 'critical', function ($mail) {
                return $mail->line('⚠️ **緊急**: 証明書の期限切れが迫っています！');
            })
            ->action('証明書を更新', route('certificates.renew', $this->certificateOrder))
            ->line('証明書の更新についてご不明な点がございましたら、サポートまでお問い合わせください。')
            ->salutation('SSL Shop サポートチーム');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certificate_expiry_warning',
            'certificate_order_id' => $this->certificateOrder->id,
            'domain_name' => $this->certificateOrder->domain_name,
            'expires_at' => $this->certificateOrder->expires_at,
            'days_until_expiry' => $this->daysUntilExpiry,
            'urgency_level' => $this->getUrgencyLevel(),
            'message' => $this->getMainMessage(),
            'action_url' => route('certificates.renew', $this->certificateOrder),
            'action_text' => '証明書を更新'
        ];
    }

    private function getUrgencyLevel(): string
    {
        return match (true) {
            $this->daysUntilExpiry <= 7 => 'critical',
            $this->daysUntilExpiry <= 30 => 'warning',
            default => 'info'
        };
    }

    private function getSubject(): string
    {
        return match ($this->getUrgencyLevel()) {
            'critical' => '【緊急】SSL証明書の有効期限切れが迫っています - ' . $this->certificateOrder->domain_name,
            'warning' => '【重要】SSL証明書の有効期限にご注意ください - ' . $this->certificateOrder->domain_name,
            default => 'SSL証明書の有効期限のお知らせ - ' . $this->certificateOrder->domain_name
        };
    }

    private function getGreeting(): string
    {
        return match ($this->getUrgencyLevel()) {
            'critical' => '【緊急通知】証明書有効期限のお知らせ',
            'warning' => '【重要】証明書有効期限のお知らせ',
            default => '証明書有効期限のお知らせ'
        };
    }

    private function getMainMessage(): string
    {
        return "お客様のSSL証明書「{$this->certificateOrder->domain_name}」の有効期限まで残り{$this->daysUntilExpiry}日となりました。";
    }
}