<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ダッシュボード - SSL Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100" x-data="dashboardApp()" x-init="checkAuth()">
    <!-- デバッグ情報 -->
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 text-xs" x-show="user">
        <strong>デバッグ情報:</strong> 
        現在のユーザー: <span x-text="user?.name"></span> 
        (<span x-text="user?.email"></span>)
        | ID: <span x-text="user?.id"></span>
        | トークン: <span x-text="tokenPreview"></span>
    </div>

    <!-- ローディング画面 -->
    <div x-show="loading" class="min-h-screen flex items-center justify-center">
        <div class="text-center">
            <div class="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-500 mx-auto"></div>
            <p class="mt-4 text-gray-600">読み込み中...</p>
        </div>
    </div>

    <!-- メインダッシュボード -->
    <div x-show="!loading && authenticated" x-transition>
        <!-- ナビゲーション -->
        <nav class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-semibold">SSL Shop</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-700" x-text="user?.name || 'ユーザー'"></span>
                        <span class="text-sm text-gray-500" x-text="user?.email"></span>
                        <button @click="logout" class="text-red-600 hover:text-red-800 text-sm">
                            ログアウト
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- メインコンテンツ -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <!-- ウェルカムメッセージ -->
                <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-lg font-medium text-gray-900">
                            ようこそ、<span x-text="user?.name"></span>さん！
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            SSL証明書の管理ダッシュボードです。
                        </p>
                    </div>
                </div>

                <!-- 機能カード -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- 証明書一覧 -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900">SSL証明書</h3>
                                    <p class="text-sm text-gray-500">発行済み証明書の管理</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="/certificates" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    証明書一覧を見る →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- 新しい証明書 -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900">新規購入</h3>
                                    <p class="text-sm text-gray-500">SSL証明書を新規購入</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="/certificates" class="text-green-600 hover:text-green-800 text-sm font-medium">
                                    購入する →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- サブスクリプション -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900">サブスクリプション</h3>
                                    <p class="text-sm text-gray-500">定期プランの管理</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="/subscriptions" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                                    管理する →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ユーザー情報 -->
                <div class="mt-6 bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">アカウント情報</h3>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">お名前</dt>
                                <dd class="text-sm text-gray-900" x-text="user?.name"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">メールアドレス</dt>
                                <dd class="text-sm text-gray-900" x-text="user?.email"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">会社名</dt>
                                <dd class="text-sm text-gray-900" x-text="user?.company_name || '未設定'"></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">登録日</dt>
                                <dd class="text-sm text-gray-900" x-text="formatDate(user?.created_at)"></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function dashboardApp() {
            return {
                loading: true,
                authenticated: false,
                user: null,
                tokenPreview: '',

                async checkAuth() {
                    console.log('=== ダッシュボード認証チェック開始 ===');
                    
                    const token = localStorage.getItem('auth_token');
                    const storedUser = localStorage.getItem('user');
                    
                    console.log('localStorage内容:');
                    console.log('- auth_token:', token ? token.substring(0, 10) + '...' : 'なし');
                    console.log('- user:', storedUser);
                    
                    if (!token) {
                        console.log('トークンがないためログインページへリダイレクト');
                        this.redirectToLogin();
                        return;
                    }

                    this.tokenPreview = token.substring(0, 10) + '...';

                    try {
                        console.log('APIにユーザー情報を問い合わせ中...');
                        
                        // 少し待機してからAPIを呼び出し（データベース同期のため）
                        await new Promise(resolve => setTimeout(resolve, 200));
                        
                        const response = await fetch('/api/v1/user', {
                            method: 'GET',
                            headers: {
                                'Authorization': `Bearer ${token}`,
                                'Accept': 'application/json',
                                'Cache-Control': 'no-cache', // キャッシュを無効化
                            }
                        });

                        console.log('APIレスポンスステータス:', response.status);

                        if (response.ok) {
                            const data = await response.json();
                            console.log('APIから取得したユーザー情報:', data.user);
                            
                            // ローカルストレージと比較
                            if (storedUser) {
                                const localUser = JSON.parse(storedUser);
                                console.log('ローカルユーザーID:', localUser.id);
                                console.log('APIユーザーID:', data.user.id);
                                
                                if (localUser.id !== data.user.id) {
                                    console.error('⚠️ ユーザーIDが一致しません！強制ログアウトします');
                                    console.error('ローカル:', localUser.id, 'API:', data.user.id);
                                    
                                    // 不正な状態のため強制ログアウト
                                    await this.logout();
                                    return;
                                }
                            }
                            
                            this.user = data.user;
                            this.authenticated = true;
                            
                            // localStorageのユーザー情報も更新
                            localStorage.setItem('user', JSON.stringify(data.user));
                        } else {
                            // トークンが無効
                            console.log('トークンが無効でした');
                            localStorage.removeItem('auth_token');
                            localStorage.removeItem('user');
                            this.redirectToLogin();
                        }
                    } catch (error) {
                        console.error('Auth check failed:', error);
                        this.redirectToLogin();
                    } finally {
                        this.loading = false;
                        console.log('=== ダッシュボード認証チェック終了 ===');
                    }
                },

                async logout() {
                    console.log('=== ログアウト処理開始 ===');
                    const token = localStorage.getItem('auth_token');
                    
                    if (token) {
                        try {
                            await fetch('/api/v1/logout', {
                                method: 'POST',
                                headers: {
                                    'Authorization': `Bearer ${token}`,
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                }
                            });
                            console.log('サーバー側ログアウト完了');
                        } catch (error) {
                            console.error('Logout failed:', error);
                        }
                    }

                    // ローカルストレージをクリア
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user');
                    console.log('ローカルストレージをクリアしました');
                    
                    // ログインページにリダイレクト
                    window.location.href = '/auth';
                },

                redirectToLogin() {
                    window.location.href = '/auth';
                },

                formatDate(dateString) {
                    if (!dateString) return '不明';
                    const date = new Date(dateString);
                    return date.toLocaleDateString('ja-JP');
                }
            }
        }
    </script>
</body>
</html>