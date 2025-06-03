<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL証明書発行失敗アラート</title>
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
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); 
            color: white; 
            padding: 20px; 
            text-align: center; 
            border-radius: 8px 8px 0 0;
        }
        .alert-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        .content { 
            background: #fff5f5; 
            padding: 25px; 
            border-radius: 0 0 8px 8px;
            border: 2px solid #fca5a5;
        }
        .error-details {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .order-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .order-info h3 {
            color: #1f2937;
            margin-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 10px;
            margin: 10px 0;
        }
        .info-label {
            font-weight: 600;
            color: #4b5563;
        }
        .info-value {
            color: #1f2937;
        }
        .actions {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background: #dc2626;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 5px 10px 5px 0;
        }
        .button.secondary {
            background: #6b7280;
        }
        .timestamp {
            color: #6b7280;
            font-size: 0.9em;
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        .priority-high {
            background: #fee2e2;
            border: 2px solid #fca5a5;
            padding: 10px;
            border-radius: 6px;
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="alert-icon">🚨</div>
        <h1>SSL証明書発行失敗</h1>
        <p style="font-size: 1.1em; margin: 10px 0;">緊急対応が必要です</p>
    </div>
    
    <div class="content">
        <div class="priority-high">
            ⚠️ 緊急: SSL証明書の発行に失敗しました
        </div>

        <div class="order-info">
            <h3>📋 注文詳細</h3>
            <div class="info-grid">
                <div class="info-label">注文ID:</div>
                <div class="info-value">#{{ $order->id }}</div>
                
                <div class="info-label">ドメイン名:</div>
                <div class="info-value"><strong>{{ $order->domain_name }}</strong></div>
                
                <div class="info-label">商品:</div>
                <div class="info-value">{{ $order->product->name }}</div>
                
                <div class="info-label">顧客:</div>
                <div class="info-value">{{ $order->user->name }} ({{ $order->user->email }})</div>
                
                <div class="info-label">注文日時:</div>
                <div class="info-value">{{ $order->created_at->format('Y年m月d日 H:i') }}</div>
                
                <div class="info-label">現在のステータス:</div>
                <div class="info-value" style="color: #dc2626; font-weight: 600;">{{ $order->status }}</div>
                
                @if($order->square_payment_id)
                <div class="info-label">決済ID:</div>
                <div class="info-value">{{ $order->square_payment_id }}</div>
                @endif
                
                @if($order->gogetssl_order_id)
                <div class="info-label">GoGetSSL注文ID:</div>
                <div class="info-value">{{ $order->gogetssl_order_id }}</div>
                @endif
            </div>
        </div>

        <div class="error-details">
            <h4 style="color: #dc2626; margin-top: 0;">🔍 エラー詳細</h4>
            <p><strong>エラー内容:</strong></p>
            <pre style="white-space: pre-wrap; margin: 10px 0;">{{ $errorDetails }}</pre>
            
            <p><strong>発生日時:</strong> {{ $timestamp->format('Y年m月d日 H:i:s') }}</p>
        </div>

        <div class="actions">
            <h4>🔧 推奨アクション</h4>
            <ul style="margin: 15px 0; padding-left: 20px;">
                <li>管理画面で詳細なエラーログを確認</li>
                <li>GoGetSSL APIの接続状況を確認</li>
                <li>CSRの形式と内容を検証</li>
                <li>ドメイン検証状況を確認</li>
                <li>必要に応じて顧客に連絡</li>
            </ul>
            
            <div style="margin-top: 20px;">
                <a href="{{ route('admin.certificates.show', $order) }}" class="button">📊 注文詳細を確認</a>
                <a href="{{ route('admin.certificates.retry', $order) }}" class="button">🔄 再試行</a>
                <a href="{{ route('admin.logs') }}" class="button secondary">📝 ログを確認</a>
            </div>
        </div>

        <div style="background: #fef3c7; border: 1px solid #fbbf24; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <strong>💡 ヒント:</strong> 
            同様のエラーが複数発生している場合は、システム全体の問題の可能性があります。
            GoGetSSL APIの稼働状況やネットワーク接続を確認してください。
        </div>
    </div>
    
    <div class="timestamp">
        <p><strong>SSL Shop 管理システム</strong></p>
        <p>アラート生成日時: {{ $timestamp->format('Y年m月d日 H:i:s') }}</p>
        <p><a href="{{ route('admin.dashboard') }}">管理ダッシュボード</a> | <a href="{{ route('admin.alerts') }}">アラート設定</a></p>
    </div>
</body>
</html>
