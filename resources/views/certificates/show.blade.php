<!-- resources/views/certificates/show.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>証明書詳細</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="flex justify-between items-start mb-6">
                    <h2 class="text-3xl font-bold text-gray-900">証明書詳細</h2>
                    <span class="px-3 py-1 rounded-full text-sm font-medium
                        @if($order->status === 'issued') bg-green-100 text-green-800
                        @elseif($order->status === 'processing') bg-yellow-100 text-yellow-800
                        @elseif($order->status === 'pending') bg-blue-100 text-blue-800
                        @elseif($order->status === 'failed') bg-red-100 text-red-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ 
                            $order->status === 'issued' ? '発行済み' : 
                            ($order->status === 'processing' ? '処理中' : 
                            ($order->status === 'pending' ? '待機中' : 
                            ($order->status === 'failed' ? '失敗' : $order->status)))
                        }}
                    </span>
                </div>

                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-xl font-semibold mb-4">注文情報</h3>
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-gray-600">注文ID:</dt>
                                <dd class="font-medium">#{{ $order->id }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">ドメイン:</dt>
                                <dd class="font-medium">{{ $order->domain_name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">製品:</dt>
                                <dd class="font-medium">{{ $order->product->name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">金額:</dt>
                                <dd class="font-medium">${{ number_format($order->total_amount, 2) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">注文日:</dt>
                                <dd class="font-medium">{{ $order->created_at->format('Y年m月d日') }}</dd>
                            </div>
                            @if($order->expires_at)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">有効期限:</dt>
                                <dd class="font-medium">{{ $order->expires_at->format('Y年m月d日') }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>

                    <div>
                        <h3 class="text-xl font-semibold mb-4">証明書アクション</h3>
                        <div class="space-y-3">
                            @if($order->status === 'issued')
                                <a href="{{ route('certificates.download', $order) }}" 
                                   class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition duration-200 block text-center">
                                    証明書をダウンロード
                                </a>
                                
                                <button onclick="showReissueForm()" 
                                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200">
                                    証明書を再発行
                                </button>
                            @elseif($order->status === 'processing')
                                <div class="text-yellow-600 text-center p-4 bg-yellow-50 rounded-lg">
                                    証明書を処理中です。しばらくお待ちください。
                                </div>
                            @elseif($order->status === 'failed')
                                <div class="text-red-600 text-center p-4 bg-red-50 rounded-lg">
                                    証明書の発行に失敗しました。サポートにお問い合わせください。
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 再発行フォーム (初期状態では非表示) -->
                <div id="reissue-form" class="hidden border-t pt-8">
                    <h3 class="text-xl font-semibold mb-4">証明書再発行</h3>
                    <form method="POST" action="{{ route('certificates.reissue', $order) }}">
                        @csrf
                        <div class="mb-4">
                            <label for="new_csr" class="block text-sm font-medium text-gray-700 mb-2">新しいCSR</label>
                            <textarea id="new_csr" name="new_csr" rows="6" 
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="-----BEGIN CERTIFICATE REQUEST-----" required></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="approver_email" class="block text-sm font-medium text-gray-700 mb-2">承認者メールアドレス（オプション）</label>
                            <input type="email" id="approver_email" name="approver_email" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   value="{{ $order->approver_email }}">
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200">
                                再発行する
                            </button>
                            <button type="button" onclick="hideReissueForm()" class="bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 transition duration-200">
                                キャンセル
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showReissueForm() {
            document.getElementById('reissue-form').classList.remove('hidden');
        }

        function hideReissueForm() {
            document.getElementById('reissue-form').classList.add('hidden');
        }

        // 自動更新（30秒ごとにステータスチェック）
        @if($order->status === 'processing')
        setInterval(function() {
            location.reload();
        }, 30000);
        @endif
    </script>
</body>
</html>