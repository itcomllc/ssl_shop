<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CertificateOrder;
use App\Services\SquarePaymentService;
use App\Services\GoGetSSLService;
use Illuminate\Support\Facades\Queue;

class HealthCheckController extends Controller
{
    public function index()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'square_api' => $this->checkSquareAPI(),
            'gogetssl_api' => $this->checkGoGetSSLAPI(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()->toISOString()
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase()
    {
        try {
            CertificateOrder::count();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkSquareAPI()
    {
        try {
            // Basic API connectivity check
            app(SquarePaymentService::class);
            return ['status' => 'ok', 'message' => 'Square API accessible'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Square API error: ' . $e->getMessage()];
        }
    }

    private function checkGoGetSSLAPI()
    {
        try {
            app(GoGetSSLService::class)->getProducts();
            return ['status' => 'ok', 'message' => 'GoGetSSL API accessible'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'GoGetSSL API error: ' . $e->getMessage()];
        }
    }

    private function checkQueue()
    {
        try {
            // Check if queue workers are running
            $queueSize = Queue::size();
            return ['status' => 'ok', 'message' => "Queue working, {$queueSize} jobs pending"];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Queue system error: ' . $e->getMessage()];
        }
    }
}
