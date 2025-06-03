<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>週次SSL証明書レポート</title>
    <style>
        body { 
            font-family: 'Helvetica Neue', Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 20px;
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 20px; 
            text-align: center; 
            border-radius: 8px 8px 0 0;
        }
        .content { 
            background: #f9f9f9; 
            padding: 20px; 
            border-radius: 0 0 8px 8px;
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 15px; 
            margin: 20px 0;
        }
        .stat-card { 
            background: white; 
            padding: 15px; 
            border-radius: 8px; 
            text-align: center; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number { 
            font-size: 2em; 
            font-weight: bold; 
            color: #667eea;
        }
        .stat-label { 
            color: #666; 
            font-size: 0.9em;
        }
        .summary { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            margin-top: 20px;
        }
        .footer { 
            text-align: center; 
            color: #666; 
            font-size: 0.8em; 
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 SSL証明書 週次レポート</h1>
        <p>{{ $period }}</p>
    </div>
    
    <div class="content">
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
                <div class="stat-label">売上</div>
            </div>
        </div>
        
        <div class="summary">
            <h3>📊 週次サマリー</h3>
            <ul>
                <li><strong>注文処理率:</strong> 
                    @if($stats['new_orders'] > 0)
                        {{ number_format(($stats['issued_certificates'] / $stats['new_orders']) * 100, 1) }}%
                    @else
                        N/A
                    @endif
                </li>
                <li><strong>平均注文金額:</strong> 
                    @if($stats['new_orders'] > 0)
                        ${{ number_format($stats['total_revenue'] / $stats['new_orders'], 2) }}
                    @else
                        N/A
                    @endif
                </li>
                <li><strong>失敗率:</strong> 
                    @if($stats['new_orders'] > 0)
                        {{ number_format(($stats['failed_orders'] / $stats['new_orders']) * 100, 1) }}%
                    @else
                        N/A
                    @endif
                </li>
            </ul>
            
            @if($stats['failed_orders'] > 0)
                <div style="background: #fee; padding: 10px; border-radius: 4px; border-left: 4px solid #f00;">
                    <strong>⚠️ 注意:</strong> {{ $stats['failed_orders'] }}件の注文が失敗しています。
                    管理画面で詳細をご確認ください。
                </div>
            @endif
        </div>
    </div>
    
    <div class="footer">
        <p>SSL Shop 管理システム | {{ now()->format('Y年m月d日 H:i') }}</p>
        <p><a href="{{ route('admin.dashboard') }}">管理画面で詳細を確認</a></p>
    </div>
</body>
</html>
