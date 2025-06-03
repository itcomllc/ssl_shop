<?php

// =============================================================================
// ドメイン検証必要通知
// =============================================================================

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class DomainValidationRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificateOrder $certificateOrder,
        public array $validationMethods = []
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
            ->subject('ドメイン検証が必要です - ' . $this->certificateOrder->domain_name)
            ->greeting('ドメイン検証のお願い')
            ->line('SSL証明書の発行に向けて、ドメインの所有権確認が必要です。')
            ->line('**証明書詳細:**')
            ->line('- ドメイン名: ' . $this->certificateOrder->domain_name)
            ->line('- 商品: ' . $this->certificateOrder->product->name)
            ->line('- 注文ID: #' . $this->certificateOrder->id)
            ->line('')
            ->line('以下のいずれかの方法でドメイン検証を行ってください：')
            ->when(in_array('email', $this->validationMethods), function ($mail) {
                return $mail->line('1. **メール認証** - 承認メールから認証リンクをクリック');
            })
            ->when(in_array('dns', $this->validationMethods), function ($mail) {
                return $mail->line('2. **DNS認証** - 指定されたDNSレコードを追加');
            })
            ->when(in_array('file', $this->validationMethods), function ($mail) {
                return $mail->line('3. **ファイル認証** - 指定されたファイルをWebサーバーに配置');
            })
            ->action('検証手順を確認', route('certificates.validation', $this->certificateOrder))
            ->line('検証完了後、証明書の発行を進めさせていただきます。')
            ->salutation('SSL Shop サポートチーム');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'domain_validation_required',
            'certificate_order_id' => $this->certificateOrder->id,
            'domain_name' => $this->certificateOrder->domain_name,
            'validation_methods' => $this->validationMethods,
            'message' => "SSL証明書「{$this->certificateOrder->domain_name}」のドメイン検証が必要です。",
            'action_url' => route('certificates.validation', $this->certificateOrder),
            'action_text' => '検証手順を確認'
        ];
    }
}
