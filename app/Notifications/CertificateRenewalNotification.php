<?php

// =============================================================================
// 証明書更新成功通知
// =============================================================================

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateRenewalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificateOrder $originalOrder,
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
        $isAutoRenewal = $this->originalOrder->subscription && $this->originalOrder->subscription->auto_renewal;

        return (new MailMessage)
            ->subject('SSL証明書の更新が完了しました - ' . $this->originalOrder->domain_name)
            ->greeting($isAutoRenewal ? '自動更新完了のお知らせ' : '証明書更新完了のお知らせ')
            ->line($isAutoRenewal ? 
                'SSL証明書の自動更新が正常に完了いたしました。' : 
                'SSL証明書の更新が正常に完了いたしました。'
            )
            ->line('**更新詳細:**')
            ->line('- ドメイン名: ' . $this->originalOrder->domain_name)
            ->line('- 証明書タイプ: ' . $this->originalOrder->product->name)
            ->line('- 旧有効期限: ' . $this->originalOrder->expires_at?->format('Y年m月d日'))
            ->line('- 新有効期限: ' . $this->renewalOrder->expires_at?->format('Y年m月d日'))
            ->line('- 更新金額: ' . $this->renewalOrder->currency . ' ' . number_format($this->renewalOrder->total_amount, 2))
            ->line('- 新注文ID: #' . $this->renewalOrder->id)
            ->when($this->renewalOrder->square_payment_id, function ($mail) {
                return $mail->line('- 決済ID: ' . $this->renewalOrder->square_payment_id);
            })
            ->line('')
            ->action('新しい証明書をダウンロード', route('certificates.download', $this->renewalOrder))
            ->line('古い証明書は自動的に置き換えられます。Webサーバーでの証明書ファイルの更新をお忘れなく。')
            ->when($isAutoRenewal, function ($mail) {
                return $mail->line('自動更新を停止したい場合は、アカウント設定またはサポートまでご連絡ください。');
            })
            ->salutation('SSL Shop サポートチーム');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certificate_renewal',
            'original_order_id' => $this->originalOrder->id,
            'renewal_order_id' => $this->renewalOrder->id,
            'domain_name' => $this->originalOrder->domain_name,
            'old_expires_at' => $this->originalOrder->expires_at,
            'new_expires_at' => $this->renewalOrder->expires_at,
            'renewal_amount' => $this->renewalOrder->total_amount,
            'currency' => $this->renewalOrder->currency,
            'is_auto_renewal' => $this->originalOrder->subscription?->auto_renewal ?? false,
            'message' => "SSL証明書「{$this->originalOrder->domain_name}」の更新が完了しました。",
            'action_url' => route('certificates.download', $this->renewalOrder),
            'action_text' => '新しい証明書をダウンロード'
        ];
    }
}
