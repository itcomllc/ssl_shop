<?php

namespace App\Console\Commands;

use App\Models\CertificateOrder;
use App\Models\User;
use App\Mail\WeeklyReportMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class WeeklyReportCommand extends Command
{
    protected $signature = 'reports:weekly-certificates';
    protected $description = 'Send weekly certificate report to administrators';

    public function handle(): int
    {
        $this->info('週次レポートを生成しています...');
        
        $startDate = now()->subWeek()->startOfWeek();
        $endDate = now()->subWeek()->endOfWeek();
        
        // 詳細な統計を取得
        $stats = [
            'new_orders' => CertificateOrder::whereBetween('created_at', [$startDate, $endDate])->count(),
            'issued_certificates' => CertificateOrder::where('status', 'issued')
                ->whereBetween('updated_at', [$startDate, $endDate])->count(),
            'failed_orders' => CertificateOrder::where('status', 'failed')
                ->whereBetween('updated_at', [$startDate, $endDate])->count(),
            'total_revenue' => CertificateOrder::whereIn('status', ['issued', 'processing'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_amount'),
            'pending_orders' => CertificateOrder::where('status', 'processing')
                ->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];
        
        // 管理者にレポート送信
        $adminUsers = User::where('role', 'admin')->orWhere('email', 'like', '%@ssl-shop.com')->get();
        
        if ($adminUsers->isEmpty()) {
            $this->warn('管理者ユーザーが見つかりません');
            return self::FAILURE;
        }
        
        foreach ($adminUsers as $admin) {
            try {
                Mail::to($admin)->send(new WeeklyReportMail($stats, $startDate, $endDate));
                $this->line("✓ {$admin->email} に送信完了");
            } catch (\Exception $e) {
                $this->error("✗ {$admin->email} への送信失敗: {$e->getMessage()}");
            }
        }
        
        $this->info('週次レポートの送信が完了しました');
        
        return self::SUCCESS;
    }
}
