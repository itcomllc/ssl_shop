<?php

// =============================================================================
// サブスクリプション更新通知
// =============================================================================

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificateOrder $certificateOrder,
        public CertificateOrder $renewalOrder
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SSL証明書の自動更新が完了しました - ' . $this->certificateOrder->domain_name)
            ->greeting('自動更新完了のお知らせ')
            ->line('SSL証明書の自動更新が正常に完了いたしました。')
            ->line('**更新詳細:**')
            ->line('- ドメイン名: ' . $this->certificateOrder->domain_name)
            ->line('- 証明書タイプ: ' . $this->certificateOrder->product->name)
            ->line('- 新しい有効期限: ' . $this->renewalOrder->expires_at?->format('Y年m月d日'))
            ->line('- 更新金額: ' . $this->renewalOrder->currency . ' ' . number_format($this->renewalOrder->total_amount, 2))
            ->line('- 新注文ID: #' . $this->renewalOrder->id)
            ->action('新しい証明書をダウンロード', route('certificates.download', $this->renewalOrder))
            ->line('古い証明書は自動的に置き換えられます。Webサーバーでの設定更新をお忘れなく。')
            ->line('自動更新の停止をご希望の場合は、サポートまでご連絡ください。')
            ->salutation('SSL Shop サポートチーム');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_renewal',
            'original_order_id' => $this->certificateOrder->id,
            'renewal_order_id' => $this->renewalOrder->id,
            'domain_name' => $this->certificateOrder->domain_name,
            'new_expires_at' => $this->renewalOrder->expires_at,
            'renewal_amount' => $this->renewalOrder->total_amount,
            'message' => "SSL証明書「{$this->certificateOrder->domain_name}」の自動更新が完了しました。",
            'action_url' => route('certificates.download', $this->renewalOrder),
            'action_text' => '新しい証明書をダウンロード'
        ];
    }
}