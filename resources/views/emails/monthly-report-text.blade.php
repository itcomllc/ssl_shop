SSL証明書 月次レポート
{{ $period }}
{{ $startDate->format('Y年m月d日') }} 〜 {{ $endDate->format('Y年m月d日') }}

========================================
主要統計
========================================
新規注文: {{ number_format($stats['new_orders']) }}件
発行完了: {{ number_format($stats['issued_certificates']) }}件  
失敗した注文: {{ number_format($stats['failed_orders']) }}件
総売上: ${{ number_format($stats['total_revenue'], 2) }}
有効な証明書: {{ number_format($stats['active_certificates'] ?? 0) }}件
期限切れ間近: {{ number_format($stats['expiring_soon'] ?? 0) }}件

========================================
パフォーマンス分析
========================================
@if($stats['new_orders'] > 0)
注文処理率: {{ number_format(($stats['issued_certificates'] / $stats['new_orders']) * 100, 1) }}%
平均注文金額: ${{ number_format($stats['total_revenue'] / $stats['new_orders'], 2) }}
失敗率: {{ number_format(($stats['failed_orders'] / $stats['new_orders']) * 100, 1) }}%
@else
今月は新規注文がありませんでした。
@endif

========================================
商品別売上
========================================
@if(count($revenueByProduct) > 0)
@foreach($revenueByProduct as $product)
{{ $product['name'] }}: ${{ number_format($product['revenue'], 2) }} ({{ $product['count'] }}件)
@endforeach
@else
商品別データがありません。
@endif

========================================
人気ドメイン TOP5
========================================
@if(count($topDomains) > 0)
@foreach(array_slice($topDomains, 0, 5) as $index => $domain)
{{ $index + 1 }}. {{ $domain['domain'] }} ({{ $domain['count'] }}件)
@endforeach
@else
ドメインデータがありません。
@endif

========================================
注意事項
========================================
@if($stats['failed_orders'] > 10)
🚨 高い失敗率: {{ $stats['failed_orders'] }}件の注文が失敗しています。
@elseif($stats['failed_orders'] > 5)
⚠️ 失敗件数増加: {{ $stats['failed_orders'] }}件の失敗した注文があります。
@else
✅ 良好な状態: 失敗率は低水準です。
@endif

@if(($stats['expiring_soon'] ?? 0) > 0)
📅 期限切れ注意: {{ $stats['expiring_soon'] }}件の証明書が30日以内に期限切れになります。
@endif

========================================
前月との比較
========================================
@if(isset($stats['previous_month']))
@php
$orderChange = $stats['new_orders'] - $stats['previous_month']['orders'];
$revenueChange = $stats['total_revenue'] - $stats['previous_month']['revenue'];
@endphp
注文数の変化: {{ $orderChange >= 0 ? '+' : '' }}{{ $orderChange }}件
売上の変化: {{ $revenueChange >= 0 ? '+' : '' }}${{ number_format($revenueChange, 2) }}
@endif

========================================
管理画面リンク
========================================
ダッシュボード: {{ route('admin.dashboard') }}
証明書管理: {{ route('admin.certificates.index') }}
@if($stats['failed_orders'] > 0)
失敗した注文: {{ route('admin.certificates.failed') }}
@endif

---
SSL Shop 管理システム
{{ now()->format('Y年m月d日 H:i') }}
