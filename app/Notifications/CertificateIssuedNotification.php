<?php

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CertificateIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CertificateOrder $certificateOrder
    ) {
        // キューの優先度を設定（重要な通知なので高優先度）
        $this->onQueue('notifications');
    }

    /**
     * 通知を送信するチャンネルを決定
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * メール通知の内容
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SSL証明書の発行が完了しました - ' . $this->certificateOrder->domain_name)
            ->greeting('SSL証明書発行完了のお知らせ')
            ->line('お客様がご注文いただいたSSL証明書の発行が完了いたしました。')
            ->line('**証明書詳細:**')
            ->line('- ドメイン名: ' . $this->certificateOrder->domain_name)
            ->line('- 証明書タイプ: ' . $this->certificateOrder->product->name)
            ->line('- 有効期限: ' . $this->certificateOrder->expires_at?->format('Y年m月d日'))
            ->line('- 注文金額: ' . $this->certificateOrder->currency . ' ' . number_format($this->certificateOrder->total_amount, 2))
            ->action('証明書をダウンロード', route('certificates.download', $this->certificateOrder))
            ->line('証明書のインストールについてご不明な点がございましたら、サポートまでお問い合わせください。')
            ->salutation('SSL Shop サポートチーム');
    }

    /**
     * データベース通知の内容
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certificate_issued',
            'certificate_order_id' => $this->certificateOrder->id,
            'domain_name' => $this->certificateOrder->domain_name,
            'product_name' => $this->certificateOrder->product->name,
            'expires_at' => $this->certificateOrder->expires_at,
            'message' => "SSL証明書「{$this->certificateOrder->domain_name}」の発行が完了しました。",
            'action_url' => route('certificates.download', $this->certificateOrder),
            'action_text' => '証明書をダウンロード'
        ];
    }

    /**
     * 通知が失敗した場合の処理
     */
    public function failed(\Throwable $exception): void
    {
        // ログに記録
        Log::error('Certificate issued notification failed', [
            'certificate_order_id' => $this->certificateOrder->id,
            'domain_name' => $this->certificateOrder->domain_name,
            'exception' => $exception->getMessage()
        ]);
    }
}