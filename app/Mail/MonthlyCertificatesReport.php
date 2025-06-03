<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class MonthlyCertificatesReport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $reportData
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $targetMonth = $this->reportData['target_month']->format('Y年m月');
        
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name')
            ),
            subject: "【月次レポート】証明書発注管理レポート - {$targetMonth}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.monthly-certificates-report',
            with: [
                'targetMonth' => $this->reportData['target_month'],
                'statistics' => $this->reportData['statistics'],
                'certificateOrders' => $this->reportData['certificate_orders'],
                'pendingOrders' => $this->reportData['pending_orders'],
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        // CSVレポートを添付する場合
        if (config('reports.attach_csv', false)) {
            $csvPath = $this->generateCsvReport();
            if ($csvPath) {
                $attachments[] = Attachment::fromPath($csvPath)
                    ->as('certificates_report.csv')
                    ->withMime('text/csv');
            }
        }

        return $attachments;
    }

    /**
     * CSVレポートファイルを生成
     */
    private function generateCsvReport(): ?string
    {
        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'certificate_orders_report_');
            $handle = fopen($tempPath, 'w');

            // UTF-8 BOM を追加（Excel対応）
            fwrite($handle, "\xEF\xBB\xBF");

            // ヘッダー行
            fputcsv($handle, [
                'ID',
                '発注番号',
                '証明書タイプ',
                'ユーザー',
                'ステータス',
                '発注金額',
                '発注日',
                '完了予定日',
                '備考'
            ]);

            // データ行
            foreach ($this->reportData['certificate_orders'] as $order) {
                fputcsv($handle, [
                    $order->id,
                    $order->order_number,
                    $order->certificate_type->name ?? '',
                    $order->user->name ?? '',
                    $order->status,
                    $order->amount,
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->expected_completion_date?->format('Y-m-d'),
                    $order->notes,
                ]);
            }

            fclose($handle);
            return $tempPath;

        } catch (\Exception $e) {
            return null;
        }
    }
}