<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SquarePaymentService;

class TestSquareIntegration extends Command
{
    protected $signature = 'square:test-integration';
    protected $description = 'Test Square API integration';

    public function handle()
    {
        $squareService = app(SquarePaymentService::class);
        
        try {
            // 1. ロケーション取得テスト
            $this->info('Testing locations...');
            $locations = $squareService->getLocations();
            $this->info('Found ' . count($locations) . ' locations');
            
            // 2. 顧客作成テスト
            $this->info('Testing customer creation...');
            $customer = $squareService->createCustomer(
                'Test',
                'Integration',
                'test+' . uniqid() . '@example.com'
            );
            $this->info('Created customer: ' . $customer->getId());
            
            // 3. 顧客検索テスト
            $this->info('Testing customer search...');
            $foundCustomer = $squareService->findCustomerByEmail($customer->getEmailAddress());
            $this->info('Found customer: ' . ($foundCustomer ? $foundCustomer->getId() : 'Not found'));
            
            $this->info('All tests passed!');
            
        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
