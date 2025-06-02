<?php

// Square Webhook Controller
namespace App\Http\Controllers;

use App\Models\CertificateOrder;
use App\Models\CertificateSubscription;
use App\Services\SquarePaymentService;
use App\Jobs\ProcessCertificateRenewal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SquareWebhookController extends Controller
{
    private $squareService;

    public function __construct(SquarePaymentService $squareService)
    {
        $this->squareService = $squareService;
    }

    public function handle(Request $request)
    {
        $signature = $request->header('X-Square-Signature');
        $body = $request->getContent();
        $url = $request->fullUrl();

        // Webhook検証
        if (!$this->squareService->verifyWebhook($signature, $body, $url)) {
            return response('Unauthorized', 401);
        }

        $data = json_decode($body, true);
        $eventType = $data['type'];

        switch ($eventType) {
            case 'payment.updated':
                $this->handlePaymentUpdate($data['data']['object']['payment']);
                break;
            
            case 'subscription.updated':
                $this->handleSubscriptionUpdate($data['data']['object']['subscription']);
                break;
            
            case 'invoice.payment_made':
                $this->handleSubscriptionPayment($data['data']['object']['invoice']);
                break;
        }

        return response('OK', 200);
    }

    private function handlePaymentUpdate($payment)
    {
        $order = CertificateOrder::where('square_payment_id', $payment['id'])->first();
        
        if ($order && $payment['status'] === 'COMPLETED') {
            Log::info("Payment completed for order {$order->id}");
        }
    }

    private function handleSubscriptionUpdate($subscription)
    {
        $certSubscription = CertificateSubscription::where('square_subscription_id', $subscription['id'])->first();
        
        if ($certSubscription) {
            $certSubscription->update(['status' => strtolower($subscription['status'])]);
        }
    }

    private function handleSubscriptionPayment($invoice)
    {
        $certSubscription = CertificateSubscription::where('square_subscription_id', $invoice['subscription_id'])->first();
        
        if ($certSubscription && $invoice['status'] === 'PAID') {
            // 証明書の自動更新処理をキューに追加
            ProcessCertificateRenewal::dispatch($certSubscription);
        }
    }
}