<?php

namespace App\Http\Controllers;

use App\Models\CertificateOrder;
use App\Services\GoGetSSLService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * GoGetSSLサービスインスタンス
     *
     * @var GoGetSSLService
     */
    protected $gogetSSLService;

    /**
     * 注文ステータス更新
     *
     * @param CertificateOrder $order
     * @return bool 更新が成功したかどうか
     */
    protected function updateOrderStatus(CertificateOrder $order): bool
    {
        if (!$order->gogetssl_order_id) {
            Log::warning('Order has no GoGetSSL order ID', ['order_id' => $order->id]);
            return false;
        }

        try {
            $response = $this->gogetSSLService->getOrderStatus($order->gogetssl_order_id);
            
            if (!$response || !isset($response['status'])) {
                Log::warning('Invalid response from GoGetSSL API', [
                    'order_id' => $order->id,
                    'gogetssl_order_id' => $order->gogetssl_order_id,
                    'response' => $response
                ]);
                return false;
            }

            $oldStatus = $order->status;
            $newStatus = $this->mapGoGetSSLStatus($response['status']);
            
            if ($newStatus && $newStatus !== $oldStatus) {
                $updateData = ['status' => $newStatus];
                
                // 証明書が発行済みの場合、有効期限を設定
                if ($response['status'] === 'active' && isset($response['valid_till'])) {
                    try {
                        $expiresAt = Carbon::parse($response['valid_till']);
                        $updateData['expires_at'] = $expiresAt;
                    } catch (\Exception $e) {
                        Log::warning('Failed to parse expires_at date', [
                            'valid_till' => $response['valid_till'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // 証明書コンテンツが含まれている場合
                if (isset($response['certificate'])) {
                    $updateData['certificate_content'] = $response['certificate'];
                }

                // CA Bundle が含まれている場合
                if (isset($response['ca_bundle'])) {
                    $updateData['ca_bundle'] = $response['ca_bundle'];
                }

                $order->update($updateData);

                Log::info('Order status updated successfully', [
                    'order_id' => $order->id,
                    'gogetssl_order_id' => $order->gogetssl_order_id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'gogetssl_status' => $response['status']
                ]);

                return true;
            }

            return false; // ステータス変更なし

        } catch (\Exception $e) {
            Log::error('Failed to update order status', [
                'order_id' => $order->id,
                'gogetssl_order_id' => $order->gogetssl_order_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * GoGetSSLのステータスを内部ステータスにマッピング
     *
     * @param string $gogetSSLStatus
     * @return string|null
     */
    protected function mapGoGetSSLStatus(string $gogetSSLStatus): ?string
    {
        $statusMap = [
            'active' => 'issued',
            'processing' => 'processing', 
            'pending' => 'processing',
            'pending_validation' => 'processing',
            'domain_validation_required' => 'processing',
            'expired' => 'expired',
            'cancelled' => 'failed',
            'rejected' => 'failed',
            'revoked' => 'failed'
        ];

        return $statusMap[$gogetSSLStatus] ?? null;
    }

    /**
     * 複数の注文のステータスを一括更新
     *
     * @param \Illuminate\Database\Eloquent\Collection $orders
     * @return array 更新結果の統計
     */
    protected function bulkUpdateOrderStatus($orders): array
    {
        $stats = [
            'total' => $orders->count(),
            'updated' => 0,
            'failed' => 0,
            'no_change' => 0
        ];

        foreach ($orders as $order) {
            $result = $this->updateOrderStatus($order);
            
            if ($result === true) {
                $stats['updated']++;
            } elseif ($result === false) {
                $stats['failed']++;
            } else {
                $stats['no_change']++;
            }
        }

        Log::info('Bulk order status update completed', $stats);

        return $stats;
    }

    /**
     * エラーレスポンスを生成
     *
     * @param string $message
     * @param array $errors
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function errorResponse(string $message, array $errors = [], int $statusCode = 400)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors
            ], $statusCode);
        }

        return back()
            ->withErrors($errors ?: ['general' => $message])
            ->withInput();
    }

    /**
     * 成功レスポンスを生成
     *
     * @param string $message
     * @param mixed $data
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function successResponse(string $message, $data = null, string $redirectRoute = null)
    {
        if (request()->expectsJson()) {
            $response = [
                'success' => true,
                'message' => $message
            ];

            if ($data !== null) {
                $response['data'] = $data;
            }

            return response()->json($response);
        }

        $redirect = $redirectRoute ? redirect()->route($redirectRoute) : back();
        
        return $redirect->with('success', $message);
    }

    /**
     * GoGetSSLサービスの接続をチェック
     *
     * @return bool
     */
    protected function checkGoGetSSLConnection(): bool
    {
        try {
            return $this->gogetSSLService->testConnection();
        } catch (\Exception $e) {
            Log::error('GoGetSSL connection check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
