<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\MonthlyCertificatesReport;
use App\Models\CertificateOrder;
use App\Models\User;
use Carbon\Carbon;

class MonthlyCertificatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:monthly-certificates
                           {--month= : 対象月 (YYYY-MM形式, 未指定の場合は前月)}
                           {--recipients= : 送信先メールアドレス (カンマ区切り)}
                           {--dry-run : 実際にメールを送信せずに内容のみ確認}';

    /**
     * The console command description.
     */
    protected $description = '月次証明書発注レポートを送信します';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('月次証明書発注レポートの生成を開始します...');

        // 対象月の決定
        $targetMonth = $this->option('month') 
            ? Carbon::createFromFormat('Y-m', $this->option('month'))
            : Carbon::now()->subMonth();

        $this->info("対象月: {$targetMonth->format('Y年m月')}");

        try {
            // レポートデータの生成
            $reportData = $this->generateReportData($targetMonth);
            
            if (empty($reportData['certificate_orders'])) {
                $this->warn('対象期間にデータが見つかりませんでした。');
                return self::SUCCESS;
            }

            // 送信先の決定
            $recipients = $this->getRecipients();
            
            if ($this->option('dry-run')) {
                $this->displayReportPreview($reportData, $recipients);
                return self::SUCCESS;
            }

            // メール送信
            $this->sendReport($reportData, $recipients, $targetMonth);
            
            $this->info('月次証明書発注レポートの送信が完了しました。');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("エラーが発生しました: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * レポートデータを生成
     */
    private function generateReportData(Carbon $targetMonth): array
    {
        $startDate = $targetMonth->copy()->startOfMonth();
        $endDate = $targetMonth->copy()->endOfMonth();

        $this->info('データを取得中...');

        // 証明書発注データの取得
        $certificateOrders = CertificateOrder::whereBetween('created_at', [$startDate, $endDate])
            ->with(['user', 'certificate_type'])
            ->get();

        // 統計データの生成
        $statistics = [
            'total_count' => $certificateOrders->count(),
            'by_status' => $certificateOrders->countBy('status'),
            'by_type' => $certificateOrders->countBy('certificate_type.name'),
            'by_user' => $certificateOrders->countBy('user.name'),
            'daily_count' => $this->getDailyCounts($certificateOrders, $startDate, $endDate),
            'total_amount' => $certificateOrders->sum('amount'),
        ];

        // 処理中の発注
        $pendingOrders = CertificateOrder::whereIn('status', ['pending', 'processing'])
            ->with(['user', 'certificate_type'])
            ->get();

        return [
            'target_month' => $targetMonth,
            'certificate_orders' => $certificateOrders,
            'statistics' => $statistics,
            'pending_orders' => $pendingOrders,
        ];
    }

    /**
     * 日別カウントデータを取得
     */
    private function getDailyCounts($certificateOrders, Carbon $startDate, Carbon $endDate): array
    {
        $dailyCounts = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $count = $certificateOrders->filter(function ($order) use ($current) {
                return $order->created_at->isSameDay($current);
            })->count();

            $dailyCounts[$current->format('Y-m-d')] = $count;
            $current->addDay();
        }

        return $dailyCounts;
    }

    /**
     * 送信先メールアドレスを取得
     */
    private function getRecipients(): array
    {
        if ($recipients = $this->option('recipients')) {
            return array_map('trim', explode(',', $recipients));
        }

        // デフォルトの送信先（管理者など）
        return User::where('role', 'admin')
            ->pluck('email')
            ->toArray();
    }

    /**
     * レポートプレビューを表示
     */
    private function displayReportPreview(array $reportData, array $recipients): void
    {
        $this->info('=== レポートプレビュー ===');
        $this->line("対象月: {$reportData['target_month']->format('Y年m月')}");
        $this->line("証明書発注総数: {$reportData['statistics']['total_count']}件");
        $this->line("総発注金額: ¥" . number_format($reportData['statistics']['total_amount']));
        
        $this->newLine();
        $this->info('ステータス別:');
        foreach ($reportData['statistics']['by_status'] as $status => $count) {
            $this->line("  {$status}: {$count}件");
        }

        $this->newLine();
        $this->info('証明書タイプ別:');
        foreach ($reportData['statistics']['by_type'] as $type => $count) {
            $this->line("  {$type}: {$count}件");
        }

        if ($reportData['pending_orders']->isNotEmpty()) {
            $this->newLine();
            $this->warn("処理中の発注: {$reportData['pending_orders']->count()}件");
        }

        $this->newLine();
        $this->info('送信先:');
        foreach ($recipients as $email) {
            $this->line("  {$email}");
        }
    }

    /**
     * レポートメールを送信
     */
    private function sendReport(array $reportData, array $recipients, Carbon $targetMonth): void
    {
        $this->info('メールを送信中...');

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new MonthlyCertificatesReport($reportData));
                $this->line("✓ {$email} に送信完了");
            } catch (\Exception $e) {
                $this->error("✗ {$email} への送信に失敗: {$e->getMessage()}");
            }
        }
    }
}