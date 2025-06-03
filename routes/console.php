<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 毎日午前9時に証明書有効期限チェック
Schedule::command('certificates:check-expiry')
    ->dailyAt('09:00')
    ->timezone('Asia/Tokyo')
    ->emailOutputOnFailure('admin@ssl-shop.com');

// 毎日午前3時に自動更新処理
Schedule::command('certificates:process-renewals')
    ->dailyAt('03:00')
    ->timezone('Asia/Tokyo')
    ->emailOutputOnFailure('admin@ssl-shop.com');

// 毎時GoGetSSLの注文ステータス同期（実装する場合）
Schedule::command('certificates:sync-gogetssl-status')
    ->hourly()
    ->withoutOverlapping(); // 前回の実行が完了するまで待機

// 毎週月曜日の午前10時に週次レポート送信
Schedule::command('reports:weekly-certificates')
    ->weeklyOn(1, '10:00')
    ->timezone('Asia/Tokyo');

// 毎月1日の午前10時に月次レポート送信
Schedule::command('reports:monthly-certificates')
    ->monthlyOn(1, '10:00')
    ->timezone('Asia/Tokyo');

// 毎日午前2時にデータベースのクリーンアップ
Schedule::command('queue:prune-batches --hours=48')
    ->dailyAt('02:00');

// 失敗した通知の再試行（5分毎）
Schedule::command('queue:retry --queue=notifications')
    ->everyFiveMinutes()
    ->when(function () {
        // 失敗したジョブがある場合のみ実行
        return \Illuminate\Support\Facades\DB::table('failed_jobs')->exists();
    });

