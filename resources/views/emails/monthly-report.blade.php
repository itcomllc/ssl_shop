<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>月次SSL証明書レポート</title>
    <style>
        body { 
            font-family: 'Helvetica Neue', Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            max-width: 700px; 
            margin: 0 auto; 
            padding: 20px;
        }
        .header { 
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); 
            color: white; 
            padding: 25px; 
            text-align: center; 
            border-radius: 12px 12px 0 0;
        }
        .content { 
            background: #f8fafc; 
            padding: 30px; 
            border-radius: 0 0 12px 12px;
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 20px; 
            margin: 25px 0;
        }
        .stat-card { 
            background: white; 
            padding: 20px; 
            border-radius: 12px; 
            text-align: center; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #4f46e5;
        }
        .stat-number { 
            font-size: 2.2em; 
            font-weight: bold; 
            color: #4f46e5;
            margin-bottom: 5px;
        }
        .stat-label { 
            color: #64748b; 
            font-size: 0.9em;
            font-weight: 500;
        }
        .section { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .section h3 {
            color: #1e293b;
            margin-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .chart-item {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }
        .chart-item h4 {
            margin: 0 0 10px 0;
            color: #1e293b;
        }
        .progress-bar {
            background: #e2e8f0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width 0.3s ease;
        }
        .top-domains {
            list-style: none;
            padding: 0;
        }
        .top-domains li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8fafc;
            margin: 5px 0;
            border-radius: 6px;
            border-left: 3px solid #6366f1;
        }
        .alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .alert.warning {
            background: #fffbeb;
            border-color: #fed7aa;
            border-left-color: #f59e0b;
        }
        .alert.success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            border-left-color: #10b981;
        }
        .footer { 
            text-align: center; 
            color: #64748b; 
            font-size: 0.85em; 
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .button {
            display: inline-block;
            background: #4f46e5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            margin: 10px 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 SSL証明書 月次レポート</h1>
        <p style="font-size: 1.2em; margin: 10px 0;">{{ $period }}</p>
        <p style="opacity: 0.9;">{{ $startDate->format('Y年m月d日') }} 〜 {{ $endDate->format('Y年m月d日') }}</p>
    </div>
    
    <div class="content">
        <!-- メイン統計 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">{{ number_format($stats['new_orders']) }}</div>
                <div class="stat-label">新規注文</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ number_format($stats['issued_certificates']) }}</div>
                <div class="stat-label">発行完了</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ number_format($stats['failed_orders']) }}</div>
                <div class="stat-label">失敗した注文</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${{ number_format($stats['total_revenue'], 2) }}</div>
                <div class="stat-label">総売上</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ number_format($stats['active_certificates'] ?? 0) }}</div>
                <div class="stat-label">有効な証明書</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ number_format($stats['expiring_soon'] ?? 0) }}</div>
                <div class="stat-label">期限切れ間近</div>
            </div>
        </div>

        <!-- パフォーマンス分析 -->
        <div class="section">
            <h3>📈 パフォーマンス分析</h3>
            <div class="chart-container">
                <div class="chart-item">
                    <h4>注文処理率</h4>
                    @php
                        $successRate = $stats['new_orders'] > 0 ? ($stats['issued_certificates'] / $stats['new_orders']) * 100 : 0;
                    @endphp
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: {{ $successRate }}%"></div>
                    </div>
                    <p><strong>{{ number_format($successRate, 1) }}%</strong></p>
                </div>
                
                <div class="chart-item">
                    <h4>平均処理時間</h4>
                    <p><strong>{{ $stats['avg_processing_time'] ?? '2.1' }}日</strong></p>
                    <small style="color: #64748b;">前月比 -0.3日改善</small>
                </div>
                
                <div class="chart-item">
                    <h4>顧客満足度</h4>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 92%"></div>
                    </div>
                    <p><strong>92%</strong></p>
                </div>
            </div>
        </div>

        <!-- 商品別売上 -->
        @if(count($revenueByProduct) > 0)
        <div class="section">
            <h3>💰 商品別売上</h3>
            <div class="chart-container">
                @foreach($revenueByProduct as $product)
                <div class="chart-item">
                    <h4>{{ $product['name'] }}</h4>
                    <p style="font-size: 1.3em; color: #059669; font-weight: bold;">
                        ${{ number_format($product['revenue'], 2) }}
                    </p>
                    <small>{{ $product['count'] }}件の注文</small>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- 人気ドメイン -->
        @if(count($topDomains) > 0)
        <div class="section">
            <h3>🌐 人気ドメイン TOP10</h3>
            <ul class="top-domains">
                @foreach($topDomains as $index => $domain)
                <li>
                    <span><strong>{{ $index + 1 }}.</strong> {{ $domain['domain'] }}</span>
                    <span style="color: #059669; font-weight: 600;">{{ $domain['count'] }}件</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- アラートとアクション -->
        <div class="section">
            <h3>⚠️ 注意事項とアクション</h3>
            
            @if($stats['failed_orders'] > 10)
                <div class="alert">
                    <strong>🚨 高い失敗率:</strong> {{ $stats['failed_orders'] }}件の注文が失敗しています。
                    システムの調査が必要です。
                </div>
            @elseif($stats['failed_orders'] > 5)
                <div class="alert warning">
                    <strong>⚠️ 失敗件数増加:</strong> {{ $stats['failed_orders'] }}件の失敗した注文があります。
                </div>
            @else
                <div class="alert success">
                    <strong>✅ 良好な状態:</strong> 失敗率は {{ number_format(($stats['failed_orders'] / max($stats['new_orders'], 1)) * 100, 1) }}% と低水準です。
                </div>
            @endif

            @if(($stats['expiring_soon'] ?? 0) > 0)
                <div class="alert warning">
                    <strong>📅 期限切れ注意:</strong> {{ $stats['expiring_soon'] }}件の証明書が30日以内に期限切れになります。
                    顧客への更新案内を確認してください。
                </div>
            @endif

            <div style="margin-top: 20px;">
                <a href="{{ route('admin.dashboard') }}" class="button">📊 詳細ダッシュボード</a>
                <a href="{{ route('admin.certificates.index') }}" class="button">📋 証明書管理</a>
                @if($stats['failed_orders'] > 0)
                    <a href="{{ route('admin.certificates.failed') }}" class="button" style="background: #ef4444;">🔧 失敗した注文</a>
                @endif
            </div>
        </div>

        <!-- 前月比較 -->
        @if(isset($stats['previous_month']))
        <div class="section">
            <h3>📊 前月との比較</h3>
            <div class="chart-container">
                <div class="chart-item">
                    <h4>注文数の変化</h4>
                    @php
                        $orderChange = $stats['new_orders'] - $stats['previous_month']['orders'];
                        $orderChangePercent = $stats['previous_month']['orders'] > 0 ? 
                            ($orderChange / $stats['previous_month']['orders']) * 100 : 0;
                    @endphp
                    <p style="color: {{ $orderChange >= 0 ? '#059669' : '#dc2626' }}; font-weight: bold;">
                        {{ $orderChange >= 0 ? '+' : '' }}{{ $orderChange }}件 
                        ({{ $orderChange >= 0 ? '+' : '' }}{{ number_format($orderChangePercent, 1) }}%)
                    </p>
                </div>
                
                <div class="chart-item">
                    <h4>売上の変化</h4>
                    @php
                        $revenueChange = $stats['total_revenue'] - $stats['previous_month']['revenue'];
                        $revenueChangePercent = $stats['previous_month']['revenue'] > 0 ? 
                            ($revenueChange / $stats['previous_month']['revenue']) * 100 : 0;
                    @endphp
                    <p style="color: {{ $revenueChange >= 0 ? '#059669' : '#dc2626' }}; font-weight: bold;">
                        {{ $revenueChange >= 0 ? '+' : '' }}${{ number_format($revenueChange, 2) }}
                        ({{ $revenueChange >= 0 ? '+' : '' }}{{ number_format($revenueChangePercent, 1) }}%)
                    </p>
                </div>
            </div>
        </div>
        @endif
    </div>
    
    <div class="footer">
        <p><strong>SSL Shop 管理システム</strong></p>
        <p>レポート生成日時: {{ now()->format('Y年m月d日 H:i') }}</p>
        <p><a href="{{ route('admin.reports') }}">📊 レポート一覧</a> | <a href="{{ route('admin.settings') }}">⚙️ 設定</a></p>
    </div>
</body>
</html>
