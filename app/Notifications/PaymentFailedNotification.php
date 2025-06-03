<?php

// =============================================================================
// 支払い失敗通知
// =============================================================================

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificateOrder $certificateOrder,
        public string $failureReason = ''
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
            ->subject('お支払いが完了できませんでした - ' . $this->certificateOrder->domain_name)
            ->greeting('お支払いについてのお知らせ')
            ->line('SSL証明書のご注文のお支払い処理が完了できませんでした。')
            ->line('**注文詳細:**')
            ->line('- 注文ID: #' . $this->certificateOrder->id)
            ->line('- ドメイン名: ' . $this->certificateOrder->domain_name)
            ->line('- 商品: ' . $this->certificateOrder->product->name)
            ->line('- 金額: ' . $this->certificateOrder->currency . ' ' . number_format($this->certificateOrder->total_amount, 2))
            ->when($this->failureReason, function ($mail) {
                return $mail->line('- 失敗理由: ' . $this->failureReason);
            })
            ->line('')
            ->line('お支払い方法をご確認いただき、再度お試しください。')
            ->action('再度お支払い', route('certificates.retry-payment', $this->certificateOrder))
            ->line('ご不明な点がございましたら、サポートまでお問い合わせください。')
            ->salutation('SSL Shop サポートチーム');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'certificate_order_id' => $this->certificateOrder->id,
            'domain_name' => $this->certificateOrder->domain_name,
            'amount' => $this->certificateOrder->total_amount,
            'currency' => $this->certificateOrder->currency,
            'failure_reason' => $this->failureReason,
            'message' => "SSL証明書「{$this->certificateOrder->domain_name}」のお支払いが完了できませんでした。",
            'action_url' => route('certificates.retry-payment', $this->certificateOrder),
            'action_text' => '再度お支払い'
        ];
    }
}