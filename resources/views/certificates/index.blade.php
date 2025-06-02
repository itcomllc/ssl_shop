<!-- resources/views/certificates/index.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SSL証明書販売</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @if(config('services.square.environment') === 'production')
        <script src="https://web.squarecdn.com/v1/square.js"></script>
    @else
        <script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    @endif
</head>
<body class="bg-gray-50">
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold text-gray-800">SSL証明書販売</h1>
                <div class="space-x-4" id="nav-menu">
                    <!-- JavaScript で動的に更新 -->
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">SSL証明書を購入</h2>
            <p class="text-xl text-gray-600">あなたのWebサイトを安全に保護</p>
        </div>

        <div id="auth-required" class="hidden bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-6">
            証明書を購入するにはログインが必要です。
            <button onclick="showLoginForm()" class="ml-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                ログイン
            </button>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8" id="products-grid">
            @foreach($products as $product)
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="p-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $product->name }}</h3>
                    <p class="text-gray-600 mb-4">{{ $product->description }}</p>
                    
                    <div class="mb-4">
                        <span class="text-3xl font-bold text-blue-600">${{ number_format($product->price, 2) }}</span>
                        <span class="text-gray-500">/ {{ $product->validity_period }}ヶ月</span>
                    </div>

                    <ul class="space-y-2 mb-6">
                        <li class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            {{ $product->domain_count }}ドメイン対応
                        </li>
                        <li class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            {{ $product->validity_period }}ヶ月有効
                        </li>
                        @if($product->wildcard_support)
                        <li class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            ワイルドカード対応
                        </li>
                        @endif
                        @if($product->features)
                            @foreach($product->features as $feature)
                            <li class="flex items-center text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                {{ $feature }}
                            </li>
                            @endforeach
                        @endif
                    </ul>

                    <button onclick="purchaseProduct({{ $product->id }})" 
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200 purchase-btn">
                        購入する
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- ログインモーダル -->
    <div id="login-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">ログイン</h3>
                <form id="login-form">
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">メールアドレス</label>
                        <input type="email" id="email" name="email" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">パスワード</label>
                        <input type="password" id="password" name="password" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                            ログイン
                        </button>
                        <button type="button" onclick="hideLoginForm()" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400">
                            キャンセル
                        </button>
                    </div>
                </form>
                <div class="mt-4 text-center">
                    <a href="#" onclick="showRegisterForm()" class="text-blue-600 hover:text-blue-800">アカウントを作成</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 登録モーダル -->
    <div id="register-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">アカウント作成</h3>
                <form id="register-form">
                    <div class="mb-4">
                        <label for="reg-name" class="block text-sm font-medium text-gray-700 mb-2">名前</label>
                        <input type="text" id="reg-name" name="name" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="reg-email" class="block text-sm font-medium text-gray-700 mb-2">メールアドレス</label>
                        <input type="email" id="reg-email" name="email" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="reg-password" class="block text-sm font-medium text-gray-700 mb-2">パスワード</label>
                        <input type="password" id="reg-password" name="password" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="reg-password-confirmation" class="block text-sm font-medium text-gray-700 mb-2">パスワード確認</label>
                        <input type="password" id="reg-password-confirmation" name="password_confirmation" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                            登録
                        </button>
                        <button type="button" onclick="hideRegisterForm()" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400">
                            キャンセル
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // API設定（実際のエンドポイントに合わせて修正）
        const API_BASE = '/api/v1';
        let authToken = localStorage.getItem('auth_token');
        let currentUser = null;

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            checkAuth();
            setupEventListeners();
        });

        // 認証状態チェック
        async function checkAuth() {
            if (!authToken) {
                updateNavMenu(false);
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/user`, {
                    headers: {
                        'Authorization': `Bearer ${authToken}`,
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    currentUser = data.user;
                    updateNavMenu(true);
                } else {
                    localStorage.removeItem('auth_token');
                    authToken = null;
                    updateNavMenu(false);
                }
            } catch (error) {
                console.error('Auth check failed:', error);
                updateNavMenu(false);
            }
        }

        // ナビメニュー更新
        function updateNavMenu(isAuthenticated) {
            const navMenu = document.getElementById('nav-menu');
            
            if (isAuthenticated) {
                navMenu.innerHTML = `
                    <a href="/certificates" class="text-blue-600 hover:text-blue-800">証明書一覧</a>
                    <a href="/subscriptions" class="text-blue-600 hover:text-blue-800">サブスクリプション</a>
                    <span class="text-gray-600">こんにちは、${currentUser?.name || 'ユーザー'}さん</span>
                    <button onclick="logout()" class="text-red-600 hover:text-red-800">ログアウト</button>
                `;
                document.getElementById('auth-required').classList.add('hidden');
            } else {
                navMenu.innerHTML = `
                    <button onclick="showLoginForm()" class="text-blue-600 hover:text-blue-800">ログイン</button>
                    <button onclick="showRegisterForm()" class="text-blue-600 hover:text-blue-800">新規登録</button>
                `;
                document.getElementById('auth-required').classList.remove('hidden');
            }
        }

        // イベントリスナー設定
        function setupEventListeners() {
            // ログインフォーム
            document.getElementById('login-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                
                try {
                    const response = await fetch(`${API_BASE}/login`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            email: formData.get('email'),
                            password: formData.get('password')
                        })
                    });

                    const data = await response.json();

                    if (response.ok) {
                        authToken = data.token;
                        localStorage.setItem('auth_token', authToken);
                        currentUser = data.user;
                        hideLoginForm();
                        updateNavMenu(true);
                        showSuccess(data.message || 'ログインしました');
                    } else {
                        showError(data.message || 'ログインに失敗しました');
                    }
                } catch (error) {
                    console.error('Login error:', error);
                    showError('ログインエラーが発生しました');
                }
            });

            // 登録フォーム
            document.getElementById('register-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                
                try {
                    const response = await fetch(`${API_BASE}/register`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            name: formData.get('name'),
                            email: formData.get('email'),
                            password: formData.get('password'),
                            password_confirmation: formData.get('password_confirmation')
                        })
                    });

                    const data = await response.json();

                    if (response.ok) {
                        authToken = data.token;
                        localStorage.setItem('auth_token', authToken);
                        currentUser = data.user;
                        hideRegisterForm();
                        updateNavMenu(true);
                        showSuccess(data.message || 'アカウントを作成しました');
                    } else {
                        if (data.errors) {
                            // バリデーションエラーの表示
                            const errorMessages = Object.values(data.errors).flat().join('\n');
                            showError(errorMessages);
                        } else {
                            showError(data.message || '登録に失敗しました');
                        }
                    }
                } catch (error) {
                    console.error('Register error:', error);
                    showError('登録エラーが発生しました');
                }
            });
        }

        // 製品購入
        function purchaseProduct(productId) {
            if (!authToken) {
                showLoginForm();
                return;
            }
            
            window.location.href = `/certificates/create/${productId}`;
        }

        // ログアウト
        async function logout() {
            try {
                await fetch(`${API_BASE}/logout`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${authToken}`,
                        'Accept': 'application/json'
                    }
                });
            } catch (error) {
                console.error('Logout error:', error);
            }

            localStorage.removeItem('auth_token');
            authToken = null;
            currentUser = null;
            updateNavMenu(false);
            showSuccess('ログアウトしました');
        }

        // モーダル制御
        function showLoginForm() {
            document.getElementById('login-modal').classList.remove('hidden');
        }

        function hideLoginForm() {
            document.getElementById('login-modal').classList.add('hidden');
            document.getElementById('login-form').reset();
        }

        function showRegisterForm() {
            hideLoginForm();
            document.getElementById('register-modal').classList.remove('hidden');
        }

        function hideRegisterForm() {
            document.getElementById('register-modal').classList.add('hidden');
            document.getElementById('register-form').reset();
        }

        // 成功メッセージ表示
        function showSuccess(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50';
            alertDiv.textContent = message;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (document.body.contains(alertDiv)) {
                    document.body.removeChild(alertDiv);
                }
            }, 5000);
        }

        // エラーメッセージ表示
        function showError(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50';
            alertDiv.style.whiteSpace = 'pre-line'; // 改行を有効にする
            alertDiv.textContent = message;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (document.body.contains(alertDiv)) {
                    document.body.removeChild(alertDiv);
                }
            }, 5000);
        }
    </script>
</body>
</html>