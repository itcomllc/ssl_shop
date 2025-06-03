<?php

// =============================================================================
// 証明書発行失敗アラートメール
// =============================================================================

namespace App\Mail;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateFailureAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public CertificateOrder $certificateOrder,
        public string $errorDetails
    ) {
        $this->onQueue('alerts');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[緊急] SSL証明書発行失敗 - ' . $this->certificateOrder->domain_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.certificate-failure-alert',
            with: [
                'order' => $this->certificateOrder,
                'errorDetails' => $this->errorDetails,
                'timestamp' => now()
            ]
        );
    }
}
