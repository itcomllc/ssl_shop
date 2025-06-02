<!-- resources/views/certificates/create.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL証明書購入 - {{ $product->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @if(config('services.square.environment') === 'production')
        <script src="https://web.squarecdn.com/v1/square.js"></script>
    @else
        <script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        /* Square Web Payments SDK用のカスタムスタイル */
        #card-container {
            font-family: inherit;
            min-height: 200px;
            position: relative;
        }
        
        /* Square SDKのiframeが正しく表示されるようにする */
        #card-container iframe {
            width: 100% !important;
            height: 50px !important;
            min-height: 50px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.375rem !important;
            padding: 0 !important;
            margin-bottom: 12px !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            z-index: 1 !important;
            background: white !important;
        }
        
        /* フォーカス状態 */
        #card-container iframe:focus-within {
            outline: 2px solid #3b82f6 !important;
            outline-offset: 2px !important;
            border-color: #3b82f6 !important;
        }
        
        /* Squareが生成する要素の表示を確実にする */
        #card-container > div {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: 100% !important;
            height: auto !important;
            margin-bottom: 12px !important;
        }
        
        /* Square Web Payments SDKの内部要素 */
        #card-container [data-testid] {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        /* TailwindCSSのリセットが影響しないようにする */
        #card-container * {
            box-sizing: border-box !important;
        }
        
        /* 読み込み中の表示 */
        .sq-loading {
            color: #6b7280;
            text-align: center;
            padding: 20px;
        }
        
        /* ボタンとの間に確実にスペースを作る */
        #card-button {
            margin-top: 24px !important;
            clear: both !important;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-6">{{ $product->name }} を購入</h2>
                
                @if($errors->any())
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="certificate-form" method="POST" action="{{ route('certificates.store', $product) }}">
                    @csrf
                    
                    <div class="mb-6">
                        <label for="domain_name" class="block text-sm font-medium text-gray-700 mb-2">ドメイン名</label>
                        <input type="text" id="domain_name" name="domain_name" 
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="example.com" required value="{{ old('domain_name') }}">
                    </div>

                    <div class="mb-6">
                        <label for="csr" class="block text-sm font-medium text-gray-700 mb-2">CSR (Certificate Signing Request)</label>
                        <textarea id="csr" name="csr" rows="6" 
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="-----BEGIN CERTIFICATE REQUEST-----
...
-----END CERTIFICATE REQUEST-----" required>{{ old('csr') }}</textarea>
                        <p class="text-sm text-gray-500 mt-1">CSRファイルの内容をペーストしてください</p>
                    </div>

                    <div class="mb-6">
                        <label for="approver_email" class="block text-sm font-medium text-gray-700 mb-2">承認者メールアドレス</label>
                        <input type="email" id="approver_email" name="approver_email" 
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="admin@example.com" required value="{{ old('approver_email') }}">
                        <p class="text-sm text-gray-500 mt-1">ドメイン検証に使用されるメールアドレス</p>
                    </div>

                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" name="enable_subscription" value="1" class="mr-2">
                            <span class="text-sm text-gray-700">自動更新を有効にする（サブスクリプション）</span>
                        </label>
                    </div>

                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-semibold mb-2">注文内容</h3>
                        <div class="flex justify-between">
                            <span>{{ $product->name }}</span>
                            <span>${{ number_format($product->price, 2) }}</span>
                        </div>
                        <div class="border-t mt-2 pt-2 flex justify-between font-bold">
                            <span>合計</span>
                            <span>${{ number_format($product->price, 2) }}</span>
                        </div>
                    </div>

                    <!-- Square Payment Form -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-4">支払い情報</h3>
                        
                        <!-- 読み込み状態表示 -->
                        <div id="loading-indicator" class="mb-4 text-center text-gray-500">
                            決済フォームを読み込み中...
                        </div>
                        
                        <div id="card-container" class="mb-6 bg-white border border-gray-300 rounded-lg p-4" style="min-height: 200px;">
                            <!-- Square Web Payments SDKがここにカードフォームを生成します -->
                        </div>
                        
                        <!-- エラー表示エリア -->
                        <div id="payment-status" class="text-red-600 text-sm mb-4 hidden"></div>
                        
                        <div class="mt-6">
                            <button type="button" id="card-button" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition duration-200 disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                                支払いを完了する
                            </button>
                        </div>
                    </div>

                    <input type="hidden" name="payment_token" id="payment_token">
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async function () {
            console.log('DOM loaded, initializing Square payments...');
            
            // 読み込み状態を表示
            const loadingIndicator = document.getElementById('loading-indicator');
            const cardButton = document.getElementById('card-button');
            
            // Square.jsが正しく読み込まれているかチェック
            if (!window.Square) {
                console.error('Square.js failed to load properly');
                showError('決済システムの読み込みに失敗しました。ページを再読み込みしてください。');
                hideLoading();
                return;
            }
            
            console.log('Square.js loaded successfully');

            let payments;
            try {
                // Square Paymentsオブジェクトを初期化
                const applicationId = '{{ config("services.square.application_id") }}';
                const locationId = '{{ config("services.square.location_id") }}';
                
                console.log('Initializing with:', { applicationId, locationId });
                
                if (!applicationId || !locationId) {
                    throw new Error('Application ID or Location ID is missing');
                }
                
                payments = Square.payments(applicationId, locationId);
                console.log('Square payments initialized successfully');
            } catch (e) {
                console.error('Square payments initialization failed:', e);
                showError('決済システムの初期化に失敗しました。設定を確認してください。');
                hideLoading();
                return;
            }

            let card;
            try {
                console.log('Creating card element...');
                
                // カード要素を初期化（スタイルを簡素化）
                card = await payments.card();
                console.log('Card element created successfully');
                
                console.log('Attaching card to container...');
                await card.attach('#card-container');
                console.log('Card attached successfully');
                
                // デバッグ: カードコンテナの内容を確認
                const container = document.getElementById('card-container');
                console.log('Card container HTML:', container.innerHTML);
                console.log('Card container children:', container.children);
                
                // 読み込み完了
                hideLoading();
                cardButton.disabled = false;
                
            } catch (e) {
                console.error('Card initialization failed:', e);
                showError('カードフォームの初期化に失敗しました: ' + e.message);
                hideLoading();
                return;
            }

            // 支払いボタンのクリックイベント
            cardButton.addEventListener('click', async function (event) {
                event.preventDefault();
                await handlePaymentMethodSubmission(event, card);
            });

            async function handlePaymentMethodSubmission(event, card) {
                event.preventDefault();
                
                const cardButton = document.getElementById('card-button');
                
                try {
                    // ボタンを無効化
                    cardButton.disabled = true;
                    cardButton.textContent = '処理中...';
                    hideError();
                    
                    // 基本的なフォームバリデーション
                    if (!validateForm()) {
                        return;
                    }
                    
                    console.log('Tokenizing card...');
                    
                    // トークンを取得
                    const tokenResult = await card.tokenize();
                    console.log('Tokenization result:', tokenResult);
                    
                    if (tokenResult.status === 'OK') {
                        // 成功時の処理
                        console.log('Token received:', tokenResult.token);
                        document.getElementById('payment_token').value = tokenResult.token;
                        document.getElementById('certificate-form').submit();
                    } else {
                        // エラー時の処理
                        console.error('Tokenization failed:', tokenResult.errors);
                        let errorMessage = 'カード情報を確認してください。';
                        
                        if (tokenResult.errors) {
                            errorMessage = tokenResult.errors.map(error => {
                                switch(error.field) {
                                    case 'cardNumber':
                                        return 'カード番号が正しくありません。';
                                    case 'expirationDate':
                                        return '有効期限が正しくありません。';
                                    case 'cvv':
                                        return 'セキュリティコードが正しくありません。';
                                    case 'postalCode':
                                        return '郵便番号が正しくありません。';
                                    default:
                                        return error.message || 'カード情報にエラーがあります。';
                                }
                            }).join('\n');
                        }
                        
                        showError(errorMessage);
                    }
                } catch (e) {
                    console.error('Payment processing error:', e);
                    showError('支払い処理中にエラーが発生しました。しばらく時間をおいて再度お試しください。');
                } finally {
                    // ボタンを再有効化
                    cardButton.disabled = false;
                    cardButton.textContent = '支払いを完了する';
                }
            }

            function validateForm() {
                const domainName = document.getElementById('domain_name').value.trim();
                const csr = document.getElementById('csr').value.trim();
                const approverEmail = document.getElementById('approver_email').value.trim();

                if (!domainName) {
                    showError('ドメイン名を入力してください。');
                    return false;
                }

                if (!csr) {
                    showError('CSRを入力してください。');
                    return false;
                }

                if (!approverEmail) {
                    showError('承認者メールアドレスを入力してください。');
                    return false;
                }

                // 簡単なメールアドレス形式チェック
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(approverEmail)) {
                    showError('正しいメールアドレス形式で入力してください。');
                    return false;
                }

                return true;
            }

            function showError(message) {
                const statusContainer = document.getElementById('payment-status');
                statusContainer.textContent = message;
                statusContainer.classList.remove('hidden');
                
                // エラーが表示されている位置までスクロール
                statusContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function hideError() {
                const statusContainer = document.getElementById('payment-status');
                statusContainer.classList.add('hidden');
            }
            
            function hideLoading() {
                const loadingIndicator = document.getElementById('loading-indicator');
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>