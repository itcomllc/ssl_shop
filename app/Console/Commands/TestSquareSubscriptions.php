<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SquarePaymentService;

class TestSquareSubscriptions extends Command
{
    protected $signature = 'square:test-subscriptions';
    protected $description = 'Test Square subscriptions API';

    public function handle()
    {
        $squareService = app(SquarePaymentService::class);
        
        try {
            $this->info('Testing Square Subscriptions API...');
            
            // サブスクリプション一覧テスト
            $subscriptions = $squareService->listSubscriptions();
            $this->info('Found ' . count($subscriptions['subscriptions']) . ' subscriptions');
            
            // 特定のサブスクリプション取得テスト（IDが存在する場合）
            if (!empty($subscriptions['subscriptions'])) {
                $firstSubscription = $subscriptions['subscriptions'][0];
                $subscription = $squareService->getSubscription($firstSubscription->getId());
                $this->info('Retrieved subscription: ' . $subscription->getId());
                $this->info('Status: ' . $subscription->getStatus());
            }
            
            $this->info('All subscription tests passed!');
            
        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
