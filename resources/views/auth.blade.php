<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SSL Shop - 認証</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js CDN -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div x-data="authApp()" class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full space-y-8">
            <!-- ヘッダー -->
            <div class="text-center">
                <h1 class="text-3xl font-bold text-gray-900">SSL Shop</h1>
                <p class="mt-2 text-gray-600">SSL証明書管理システム</p>
            </div>

            <!-- エラー表示 -->
            <div x-show="error" x-text="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"></div>
            
            <!-- 成功メッセージ -->
            <div x-show="success" x-text="success" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded"></div>

            <!-- タブ選択 -->
            <div class="flex bg-gray-200 rounded-lg p-1">
                <button @click="mode = 'login'" 
                        :class="mode === 'login' ? 'bg-white shadow-sm' : ''"
                        class="flex-1 py-2 text-sm font-medium rounded-md transition-all">
                    ログイン
                </button>
                <button @click="mode = 'register'" 
                        :class="mode === 'register' ? 'bg-white shadow-sm' : ''"
                        class="flex-1 py-2 text-sm font-medium rounded-md transition-all">
                    新規登録
                </button>
            </div>

            <!-- ログインフォーム -->
            <form x-show="mode === 'login'" @submit.prevent="login" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">メールアドレス</label>
                    <input x-model="loginData.email" type="email" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">パスワード</label>
                    <input x-model="loginData.password" type="password" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" :disabled="loading"
                        class="w-full py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!loading">ログイン</span>
                    <span x-show="loading">処理中...</span>
                </button>
            </form>

            <!-- 登録フォーム -->
            <form x-show="mode === 'register'" @submit.prevent="register" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">お名前</label>
                    <input x-model="registerData.name" type="text" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">メールアドレス</label>
                    <input x-model="registerData.email" type="email" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">会社名（任意）</label>
                    <input x-model="registerData.company_name" type="text" 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">パスワード</label>
                    <input x-model="registerData.password" type="password" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">パスワード確認</label>
                    <input x-model="registerData.password_confirmation" type="password" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <button type="submit" :disabled="loading"
                        class="w-full py-2 px-4 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50">
                    <span x-show="!loading">アカウント作成</span>
                    <span x-show="loading">処理中...</span>
                </button>
            </form>

            <!-- デモユーザーでログイン -->
            <div class="text-center pt-4 border-t">
                <p class="text-sm text-gray-600 mb-2">デモアカウントでお試し</p>
                <button @click="demoLogin" :disabled="loading"
                        class="text-sm bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded border">
                    admin@ssl-shop.jp でログイン
                </button>
            </div>
        </div>
    </div>

    <script>
        function authApp() {
            return {
                mode: 'login',
                loading: false,
                error: '',
                success: '',
                loginData: {
                    email: '',
                    password: ''
                },
                registerData: {
                    name: '',
                    email: '',
                    company_name: '',
                    password: '',
                    password_confirmation: ''
                },

                async login() {
                    await this.submitAuth('/api/v1/login', this.loginData);
                },

                async register() {
                    await this.submitAuth('/api/v1/register', this.registerData);
                },

                async demoLogin() {
                    await this.submitAuth('/api/v1/login', {
                        email: 'admin@ssl-shop.jp',
                        password: 'password123'
                    });
                },

                async submitAuth(url, data) {
                    this.loading = true;
                    this.error = '';
                    this.success = '';

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(data)
                        });

                        const result = await response.json();

                        if (response.ok) {
                            // トークンをlocalStorageに保存
                            localStorage.setItem('auth_token', result.token);
                            localStorage.setItem('user', JSON.stringify(result.user));
                            
                            this.success = 'ログインしました。リダイレクト中...';
                            
                            // ダッシュボードにリダイレクト
                            setTimeout(() => {
                                window.location.href = '/dashboard';
                            }, 1000);
                        } else {
                            // エラー処理
                            if (result.errors) {
                                this.error = Object.values(result.errors).flat().join(', ');
                            } else {
                                this.error = result.message || '処理に失敗しました';
                            }
                        }
                    } catch (error) {
                        console.error('Auth error:', error);
                        this.error = 'ネットワークエラーが発生しました';
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>