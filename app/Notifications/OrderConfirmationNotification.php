<?php

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificateOrder $certificateOrder
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
            ->subject('ご注文を承りました - SSL証明書: ' . $this->certificateOrder->domain_name)
            ->greeting('ご注文ありがとうございます')
            ->line('以下の内容でSSL証明書のご注文を承りました。')
            ->line('**注文詳細:**')
            ->line('- 注文ID: #' . $this->certificateOrder->id)
            ->line('- 商品: ' . $this->certificateOrder->product->name)
            ->line('- ドメイン名: ' . $this->certificateOrder->domain_name)
            ->line('- 金額: ' . $this->certificateOrder->currency . ' ' . number_format($this->certificateOrder->total_amount, 2))
            ->line('- 注文日時: ' . $this->certificateOrder->created_at->format('Y年m月d日 H:i'))
            ->when($this->certificateOrder->square_payment_id, function ($mail) {
                return $mail->line('- 決済ID: ' . $this->certificateOrder->square_payment_id);
            })
            ->line('')
            ->line('SSL証明書の発行には通常1-3営業日お時間をいただいております。')
            ->line('ドメイン検証が必要な場合は、別途ご連絡いたします。')
            ->line('発行が完了いたしましたら、メールにてご連絡いたします。')
            ->action('注文詳細を確認', route('certificates.show', $this->certificateOrder))
            ->salutation('SSL Shop サポートチーム');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_confirmation',
            'certificate_order_id' => $this->certificateOrder->id,
            'domain_name' => $this->certificateOrder->domain_name,
            'product_name' => $this->certificateOrder->product->name,
            'total_amount' => $this->certificateOrder->total_amount,
            'currency' => $this->certificateOrder->currency,
            'message' => "SSL証明書「{$this->certificateOrder->domain_name}」のご注文を承りました。",
            'action_url' => route('certificates.show', $this->certificateOrder),
            'action_text' => '注文詳細を確認'
        ];
    }
}