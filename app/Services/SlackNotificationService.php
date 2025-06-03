<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackNotificationService
{
    public static function sendNewOrderNotification($certificateOrder): void
    {
        $webhookUrl = config('services.slack.webhook_url');
        
        if (!$webhookUrl) {
            return;
        }

        try {
            Http::timeout(5)->post($webhookUrl, [
                'text' => '🔐 新規SSL証明書注文',
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*新規SSL証明書注文が入りました*\n" .
                                     ":globe_with_meridians: ドメイン: `{$certificateOrder->domain_name}`\n" .
                                     ":bust_in_silhouette: 顧客: {$certificateOrder->user->name}\n" .
                                     ":moneybag: 金額: {$certificateOrder->currency} " . number_format($certificateOrder->total_amount, 2) . "\n" .
                                     ":package: 商品: {$certificateOrder->product->name}"
                        ]
                    ],
                    [
                        'type' => 'actions',
                        'elements' => [
                            [
                                'type' => 'button',
                                'text' => [
                                    'type' => 'plain_text',
                                    'text' => '管理画面で確認',
                                    'emoji' => true
                                ],
                                'value' => "order_{$certificateOrder->id}",
                                'url' => route('admin.certificates.show', $certificateOrder),
                                'style' => 'primary'
                            ]
                        ]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Slack notification failed', [
                'order_id' => $certificateOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function sendCertificateIssuedNotification($certificateOrder): void
    {
        $webhookUrl = config('services.slack.webhook_url');
        
        if (!$webhookUrl) {
            return;
        }

        try {
            Http::timeout(5)->post($webhookUrl, [
                'text' => '✅ SSL証明書発行完了',
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*SSL証明書の発行が完了しました*\n" .
                                     ":globe_with_meridians: ドメイン: `{$certificateOrder->domain_name}`\n" .
                                     ":calendar: 有効期限: {$certificateOrder->expires_at?->format('Y年m月d日')}\n" .
                                     ":bust_in_silhouette: 顧客: {$certificateOrder->user->name}"
                        ]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Slack notification failed', [
                'order_id' => $certificateOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
