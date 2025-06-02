<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>サブスクリプション管理</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">サブスクリプション管理</h2>
            
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if($subscriptions->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <p class="text-gray-500 text-lg">アクティブなサブスクリプションはありません。</p>
                    <a href="{{ route('certificates.index') }}" class="mt-4 inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        証明書を購入する
                    </a>
                </div>
            @else
                <div class="grid gap-6">
                    @foreach($subscriptions as $subscription)
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900">
                                    {{ $subscription->order->product->name }}
                                </h3>
                                <p class="text-gray-600">{{ $subscription->order->domain_name }}</p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-medium
                                @if($subscription->status === 'active') bg-green-100 text-green-800
                                @elseif($subscription->status === 'paused') bg-yellow-100 text-yellow-800
                                @elseif($subscription->status === 'cancelled') bg-red-100 text-red-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ 
                                    $subscription->status === 'active' ? 'アクティブ' : 
                                    ($subscription->status === 'paused' ? '一時停止' : 
                                    ($subscription->status === 'cancelled' ? 'キャンセル済み' : $subscription->status))
                                }}
                            </span>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm text-gray-600">次回請求日</p>
                                <p class="font-medium">{{ $subscription->next_billing_date ? $subscription->next_billing_date->format('Y年m月d日') : '未設定' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">請求間隔</p>
                                <p class="font-medium">{{ $subscription->billing_interval === 'yearly' ? '年間' : '月間' }}</p>
                            </div>
                        </div>

                        <div class="flex space-x-3">
                            <a href="{{ route('certificates.subscription.show', $subscription) }}" 
                               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                詳細表示
                            </a>
                            
                            @can('pause', $subscription)
                            <form method="POST" action="{{ route('certificates.subscription.pause', $subscription) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700"
                                        onclick="return confirm('サブスクリプションを一時停止しますか？')">
                                    一時停止
                                </button>
                            </form>
                            @endcan

                            @can('resume', $subscription)
                            <form method="POST" action="{{ route('certificates.subscription.resume', $subscription) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                    再開
                                </button>
                            </form>
                            @endcan

                            @can('cancel', $subscription)
                            <form method="POST" action="{{ route('certificates.subscription.cancel', $subscription) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                                        onclick="return confirm('サブスクリプションをキャンセルしますか？この操作は取り消せません。')">
                                    キャンセル
                                </button>
                            </form>
                            @endcan
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</body>
</html>
