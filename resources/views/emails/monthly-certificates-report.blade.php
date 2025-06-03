# 証明書発注管理 月次レポート

{{ $targetMonth->format('Y年m月') }}の証明書発注管理レポートをお送りします。

## 📊 サマリー

- **発注総数**: {{ number_format($statistics['total_count']) }}件
- **総発注金額**: ¥{{ number_format($statistics['total_amount']) }}
- **レポート期間**: {{ $targetMonth->format('Y年m月1日') }} ～ {{ $targetMonth->endOfMonth()->format('m月d日') }}

## 📈 統計情報

### ステータス別
@if(isset($statistics['by_status']) && count($statistics['by_status']) > 0)
@foreach($statistics['by_status'] as $status => $count)
- **{{ $status }}**: {{ number_format($count) }}件
@endforeach
@else
データがありません
@endif

### 証明書タイプ別
@if(isset($statistics['by_type']) && count($statistics['by_type']) > 0)
@foreach($statistics['by_type'] as $type => $count)
- **{{ $type }}**: {{ number_format($count) }}件
@endforeach
@else
データがありません
@endif

### ユーザー別（上位5名）
@if(isset($statistics['by_user']) && count($statistics['by_user']) > 0)
@foreach(array_slice($statistics['by_user'], 0, 5, true) as $user => $count)
- **{{ $user }}**: {{ number_format($count) }}件
@endforeach
@else
データがありません
@endif

## ⚠️ 注意事項

@if($pendingOrders->count() > 0)
### 処理中の発注
{{ $pendingOrders->count() }}件の発注が処理中です。

@foreach($pendingOrders->take(10) as $order)
- **{{ $order->order_number }}** ({{ $order->user->name ?? '不明' }}) - {{ $order->certificate_type->name ?? '不明' }} - ¥{{ number_format($order->amount) }}
@endforeach

@if($pendingOrders->count() > 10)
その他 {{ $pendingOrders->count() - 10 }}件
@endif
@else
現在、処理中の発注はありません。
@endif

## 📅 日別発注数推移

@if(isset($statistics['daily_count']) && count($statistics['daily_count']) > 0)
@php
$maxCount = max($statistics['daily_count']);
$days = array_keys($statistics['daily_count']);
$firstWeek = array_slice($days, 0, 7);
@endphp

直近1週間の発注数:
@foreach($firstWeek as $date)
@php $count = $statistics['daily_count'][$date] @endphp
- {{ \Carbon\Carbon::parse($date)->format('m/d') }}: {{ $count }}件
@endforeach
@endif

---

このレポートは自動生成されました。  
詳細な情報については、管理画面をご確認ください。

@component('mail::button', ['url' => config('app.url') . '/admin/certificate-orders'])
管理画面を開く
@endcomponent

何かご質問がございましたら、システム管理者までお問い合わせください。

{{ config('app.name') }}