<?php

// =============================================================================
// App\Mail\WeeklyReportMail.php
// =============================================================================

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class WeeklyReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $stats,
        public Carbon $startDate,
        public Carbon $endDate
    ) {
        $this->onQueue('reports');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '週次SSL証明書レポート - ' . $this->startDate->format('Y年m月d日') . '〜' . $this->endDate->format('m月d日'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.weekly-report',
            text: 'emails.weekly-report-text',
            with: [
                'stats' => $this->stats,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'period' => $this->startDate->format('Y年m月d日') . '〜' . $this->endDate->format('m月d日')
            ]
        );
    }
}
