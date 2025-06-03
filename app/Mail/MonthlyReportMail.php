<?php

// =============================================================================
// 月次レポートメールクラス
// =============================================================================

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class MonthlyReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $stats,
        public Carbon $startDate,
        public Carbon $endDate,
        public array $topDomains = [],
        public array $revenueByProduct = []
    ) {
        $this->onQueue('reports');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '月次SSL証明書レポート - ' . $this->startDate->format('Y年m月'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.monthly-report',
            text: 'emails.monthly-report-text',
            with: [
                'stats' => $this->stats,
                'topDomains' => $this->topDomains,
                'revenueByProduct' => $this->revenueByProduct,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'period' => $this->startDate->format('Y年m月')
            ]
        );
    }
}
