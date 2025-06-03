<?php 

// =============================================================================
// 管理者向け新規注文通知
// =============================================================================

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificateOrder $certificateOrder
    ) {
        $this->onQueue('admin-notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'slack']; // Slack通知も追加可能
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[管理者通知] 新規SSL証明書注文 - ' . $this->certificateOrder->domain_name)
            ->greeting('新規注文のお知らせ')
            ->line('新しいSSL証明書の注文が入りました。')
            ->line('**注文詳細:**')
            ->line('- 注文ID: #' . $this->certificateOrder->id)
            ->line('- 顧客: ' . $this->certificateOrder->user->name . ' (' . $this->certificateOrder->user->email . ')')
            ->line('- ドメイン名: ' . $this->certificateOrder->domain_name)
            ->line('- 商品: ' . $this->certificateOrder->product->name)
            ->line('- 金額: ' . $this->certificateOrder->currency . ' ' . number_format($this->certificateOrder->total_amount, 2))
            ->line('- 注文日時: ' . $this->certificateOrder->created_at->format('Y年m月d日 H:i'))
            ->when($this->certificateOrder->square_payment_id, function ($mail) {
                return $mail->line('- Square決済ID: ' . $this->certificateOrder->square_payment_id);
            })
            ->action('管理画面で確認', route('admin.certificates.show', $this->certificateOrder))
            ->salutation('SSL Shop システム');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_order_admin',
            'certificate_order_id' => $this->certificateOrder->id,
            'customer_name' => $this->certificateOrder->user->name,
            'customer_email' => $this->certificateOrder->user->email,
            'domain_name' => $this->certificateOrder->domain_name,
            'product_name' => $this->certificateOrder->product->name,
            'amount' => $this->certificateOrder->total_amount,
            'currency' => $this->certificateOrder->currency,
            'message' => "新規注文: {$this->certificateOrder->domain_name} (#{$this->certificateOrder->id})",
            'action_url' => route('admin.certificates.show', $this->certificateOrder),
            'action_text' => '管理画面で確認'
        ];
    }

}