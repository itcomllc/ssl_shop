<?php

// =============================================================================
// resources/views/emails/weekly-report-text.blade.php (テキスト版)
// =============================================================================
?>

SSL証明書 週次レポート
{{ $period }}

=== 統計 ===
新規注文: {{ number_format($stats['new_orders']) }}件
発行完了: {{ number_format($stats['issued_certificates']) }}件  
失敗した注文: {{ number_format($stats['failed_orders']) }}件
売上: ${{ number_format($stats['total_revenue'], 2) }}

=== サマリー ===
@if($stats['new_orders'] > 0)
注文処理率: {{ number_format(($stats['issued_certificates'] / $stats['new_orders']) * 100, 1) }}%
平均注文金額: ${{ number_format($stats['total_revenue'] / $stats['new_orders'], 2) }}
失敗率: {{ number_format(($stats['failed_orders'] / $stats['new_orders']) * 100, 1) }}%
@else
今週は新規注文がありませんでした。
@endif

@if($stats['failed_orders'] > 0)
⚠️ 注意: {{ $stats['failed_orders'] }}件の注文が失敗しています。
@endif

管理画面: {{ route('admin.dashboard') }}

---
SSL Shop 管理システム
{{ now()->format('Y年m月d日 H:i') }}
