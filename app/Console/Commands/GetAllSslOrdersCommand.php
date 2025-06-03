<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoGetSSLService;
use Illuminate\Support\Facades\Log;

class GetAllSslOrdersCommand extends Command
{
    protected $signature = 'ssl:get-all-orders 
                            {--limit=100 : Number of orders to display (after filtering)}
                            {--offset=0 : Starting offset for pagination}
                            {--status= : Filter by order status (active, processing, expired, etc.)}
                            {--export= : Export to file (csv, json)}
                            {--all : Retrieve and display all orders (ignores limit)}
                            {--no-details : Skip fetching detailed information for faster execution}
                            {--debug : Output debug information for domain extraction}';

    protected $description = 'Retrieve all SSL orders from GoGetSSL';

    protected $goGetSsl;
    protected $productMap = []; // 商品情報のキャッシュ

    public function __construct(GoGetSSLService $goGetSsl)
    {
        parent::__construct();
        $this->goGetSsl = $goGetSsl;
    }

    public function handle()
    {
        try {
            $limit = (int) $this->option('limit');
            $offset = (int) $this->option('offset');
            $status = $this->option('status');
            $exportFormat = $this->option('export');
            $retrieveAll = $this->option('all');
            $skipDetails = $this->option('no-details');
            $debug = $this->option('debug');

            $this->info('Retrieving SSL orders from GoGetSSL...');
            
            // 最初に商品情報を取得
            $this->loadProductInformation();
            
            if ($debug) {
                $this->info("Parameters: limit={$limit}, offset={$offset}, status={$status}");
            }

            if ($retrieveAll) {
                if ($skipDetails) {
                    $allOrders = $this->getAllOrdersPaginatedFast($status);
                } else {
                    $allOrders = $this->getAllOrdersPaginated($status, $debug);
                }
            } else {
                // まず getAllSSLOrders でオーダーIDリストを取得
                $ordersResponse = $this->goGetSsl->getAllSSLOrders(1000, $offset);
                $orderIds = array_column($ordersResponse['orders'] ?? [], 'order_id');
                
                if (empty($orderIds)) {
                    $allOrders = [];
                } else {
                    // オーダーIDを分割して getOrderStatuses を呼び出し（APIの制限を考慮）
                    $allCertificates = [];
                    $chunkSize = 50; // 一度に50件ずつ処理
                    $chunks = array_chunk($orderIds, $chunkSize);
                    
                    $this->info("Processing " . count($orderIds) . " orders in " . count($chunks) . " chunks...");
                    
                    foreach ($chunks as $chunkIndex => $chunk) {
                        if ($debug) {
                            $this->info("Processing chunk " . ($chunkIndex + 1) . "/" . count($chunks) . " (" . count($chunk) . " orders)");
                        }
                        
                        $response = $this->goGetSsl->getOrderStatuses($chunk);
                        $certificates = $response['certificates'] ?? [];
                        $allCertificates = array_merge($allCertificates, $certificates);
                        
                        // APIレート制限を考慮
                        if ($chunkIndex < count($chunks) - 1) {
                            usleep(100000); // 0.1秒待機
                        }
                    }
                    
                    if ($debug) {
                        $this->info("getOrderStatuses returned total " . count($allCertificates) . " certificates");
                        if (!empty($allCertificates)) {
                            $responseOrderIds = array_column($allCertificates, 'order_id');
                            if (in_array('1682136', $responseOrderIds)) {
                                $this->info("✓ Order 1682136 found in response");
                            } else {
                                $this->info("✗ Order 1682136 NOT found in response");
                            }
                        }
                    }
                    
                    // certificates を orders 形式に変換
                    $orders = array_map(function($cert) {
                        return [
                            'order_id' => $cert['order_id'],
                            'status' => $cert['status'],
                            'expires' => $cert['expires'] ?? null
                        ];
                    }, $allCertificates);
                    
                    $filteredOrders = $this->filterOrdersByStatus($orders, $status);
                    
                    // ユーザー指定のlimitで表示件数を制限
                    if ($limit && count($filteredOrders) > $limit) {
                        $filteredOrders = array_slice($filteredOrders, 0, $limit);
                        $this->info("Limiting display to first {$limit} matching orders");
                    }
                    
                    if ($skipDetails) {
                        // 詳細情報なしの場合は、基本情報のみ表示
                        $allOrders = array_map(function($order) {
                            return array_merge($order, [
                                'domain' => 'N/A',
                                'product_name' => 'N/A',
                                'valid_from' => 'N/A',
                                'valid_till' => $order['expires'] ?? 'N/A',
                                'product_id' => 'N/A',
                                'period' => 'N/A',
                                'server_count' => 'N/A',
                                'dcv_method' => 'N/A',
                                'total_domains' => 'N/A',
                                'base_domain_count' => 0,
                                'single_san_count' => 0,
                                'wildcard_san_count' => 0
                            ]);
                        }, $filteredOrders);
                    } else {
                        $allOrders = $this->enrichOrdersWithDetails($filteredOrders, $debug);
                    }
                }
            }

            if (empty($allOrders)) {
                $this->warn('No SSL orders found.');
                return 0;
            }

            $this->displayOrdersSummary($allOrders);
            $this->displayOrdersTable($allOrders);

            if ($exportFormat) {
                $this->exportOrders($allOrders, $exportFormat);
            }

            $this->info('SSL orders retrieved successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to retrieve SSL orders: ' . $e->getMessage());
            Log::error('SSL orders command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    protected function getAllOrdersPaginated($statusFilter = null, $debug = false)
    {
        // まず getAllSSLOrders で全オーダーIDを取得
        $allOrderIds = [];
        $limit = 1000;
        $offset = 0;
        
        do {
            $ordersResponse = $this->goGetSsl->getAllSSLOrders($limit, $offset);
            $orders = $ordersResponse['orders'] ?? [];
            
            if (empty($orders)) {
                break;
            }
            
            $orderIds = array_column($orders, 'order_id');
            $allOrderIds = array_merge($allOrderIds, $orderIds);
            
            $offset += $limit;
            
        } while (count($orders) === $limit);
        
        if (empty($allOrderIds)) {
            return [];
        }
        
        $this->info('Processing ' . count($allOrderIds) . ' orders...');
        
        // 取得したオーダーIDで getOrderStatuses を呼び出し
        $response = $this->goGetSsl->getOrderStatuses($allOrderIds);
        $certificates = $response['certificates'] ?? [];

        // certificates を orders 形式に変換
        $orders = array_map(function($cert) {
            return [
                'order_id' => $cert['order_id'],
                'status' => $cert['status'],
                'expires' => $cert['expires'] ?? null
            ];
        }, $certificates);

        $filteredOrders = $this->filterOrdersByStatus($orders, $statusFilter);
        
        return $this->enrichOrdersWithDetails($filteredOrders, $debug);
    }

    protected function getAllOrdersPaginatedFast($statusFilter = null)
    {
        $allOrders = [];
        $limit = 1000;
        $offset = 0;
        $progressBar = null;

        do {
            $response = $this->goGetSsl->getOrderStatuses(null, $limit, $offset);
            $certificates = $response['certificates'] ?? [];
            
            if (empty($certificates)) {
                break;
            }

            if ($progressBar === null && count($certificates) > 0) {
                $progressBar = $this->output->createProgressBar(count($certificates));
                $progressBar->setFormat('Retrieving: %current%/%max% [%bar%] %percent:3s%%');
                $progressBar->start();
            }

            // certificates を orders 形式に変換
            $orders = array_map(function($cert) {
                return [
                    'order_id' => $cert['order_id'],
                    'status' => $cert['status'],
                    'expires' => $cert['expires'] ?? null,
                    'valid_till' => $cert['expires'] ?? 'N/A'
                ];
            }, $certificates);

            $filteredOrders = $this->filterOrdersByStatus($orders, $statusFilter);
            $allOrders = array_merge($allOrders, $filteredOrders);
            
            if ($progressBar) {
                $progressBar->advance(count($certificates));
            }

            $offset += $limit;
            usleep(100000);

        } while (count($certificates) === $limit);

        if ($progressBar) {
            $progressBar->finish();
            $this->newLine();
        }

        return $allOrders;
    }

    protected function enrichOrdersWithDetails($orders, $debug = false)
    {
        if (empty($orders)) {
            return $orders;
        }

        $this->info('Fetching detailed information for each order...');
        $progressBar = $this->output->createProgressBar(count($orders));
        $progressBar->setFormat('Details: %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->start();

        $enrichedOrders = [];
        
        foreach ($orders as $order) {
            $orderId = $order['order_id'];
            $progressBar->setMessage("Order {$orderId}");
            
            try {
                $orderDetails = $this->goGetSsl->getOrderStatus($orderId);
                
                // APIレスポンス全体をログに出力（特定のオーダーIDの場合）
                if (in_array($orderId, ['3048044', '3038919', '1682136']) || $debug) {
                    Log::info("Full API response for order {$orderId}", $orderDetails);
                    
                    if ($debug) {
                        $this->info("\nFull Debug - Order {$orderId} API Response:");
                        $this->line(json_encode($orderDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
                
                if ($debug) {
                    $this->info("\nDebug - Order {$orderId} details:");
                    $this->line("Domain field: " . ($orderDetails['domain'] ?? 'empty'));
                    $this->line("Domains field: " . ($orderDetails['domains'] ?? 'empty'));
                    if (!empty($orderDetails['san'])) {
                        $this->line("SAN items: " . count($orderDetails['san']));
                        foreach ($orderDetails['san'] as $san) {
                            if (!empty($san['san_name'])) {
                                $this->line("  - SAN: " . $san['san_name']);
                            }
                        }
                    }
                    if (!empty($orderDetails['csr_code'])) {
                        $this->line("CSR available: Yes");
                    }
                }
                
                $domain = $this->extractDomainFromOrderDetails($orderDetails);
                $productName = $this->getProductName($orderDetails['product_id'] ?? null);
                
                $enrichedOrder = array_merge($order, [
                    'domain' => $domain,
                    'product_name' => $productName,
                    'valid_from' => $orderDetails['valid_from'] ?? 'N/A',
                    'valid_till' => $orderDetails['valid_till'] ?? 'N/A',
                    'product_id' => $orderDetails['product_id'] ?? 'N/A',
                    'period' => $orderDetails['ssl_period'] ?? ($orderDetails['validity_period'] ?? 'N/A'),
                    'server_count' => $orderDetails['server_count'] ?? 'N/A',
                    'dcv_method' => $orderDetails['dcv_method'] ?? 'N/A',
                    'total_domains' => $orderDetails['total_domains'] ?? 'N/A',
                    'base_domain_count' => $orderDetails['base_domain_count'] ?? 0,
                    'single_san_count' => $orderDetails['single_san_count'] ?? 0,
                    'wildcard_san_count' => $orderDetails['wildcard_san_count'] ?? 0
                ]);
                
                // SAN追加オーダーを除外（base_domain_count = 0 かつ SAN数 > 0）
                if ($enrichedOrder['base_domain_count'] == 0 && ($enrichedOrder['single_san_count'] > 0 || $enrichedOrder['wildcard_san_count'] > 0)) {
                    if ($debug) {
                        $this->warn("Skipping SAN-only order {$orderId}");
                    }
                    // 進捗バーは進めるが、結果には含めない
                    $progressBar->advance();
                    continue;
                }
                
                $enrichedOrders[] = $enrichedOrder;
                usleep(50000);
                
            } catch (\Exception $e) {
                Log::warning("Failed to get details for order {$orderId}", [
                    'error' => $e->getMessage()
                ]);
                
                $enrichedOrder = array_merge($order, [
                    'domain' => 'N/A',
                    'product_name' => 'N/A',
                    'valid_from' => 'N/A',
                    'valid_till' => 'N/A',
                    'product_id' => 'N/A',
                    'period' => 'N/A',
                    'server_count' => 'N/A',
                    'dcv_method' => 'N/A',
                    'total_domains' => 'N/A',
                    'base_domain_count' => 0,
                    'single_san_count' => 0,
                    'wildcard_san_count' => 0
                ]);
                
                $enrichedOrders[] = $enrichedOrder;
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // SAN追加オーダーが除外された場合の情報表示
        $excludedCount = count($orders) - count($enrichedOrders);
        if ($excludedCount > 0) {
            $this->info("Excluded {$excludedCount} SAN-only order(s) from display");
        }

        return $enrichedOrders;
    }

    /**
     * 商品情報を事前に読み込む
     */
    protected function loadProductInformation()
    {
        try {
            $this->info('Loading product information...');
            $productsResponse = $this->goGetSsl->getSslProducts();
            
            if (!empty($productsResponse['products'])) {
                foreach ($productsResponse['products'] as $product) {
                    $this->productMap[$product['id']] = [
                        'name' => $product['product'] ?? 'Unknown',
                        'brand' => $product['brand'] ?? 'Unknown',
                        'type' => $product['product_type'] ?? 'Unknown'
                    ];
                }
                $this->info('Loaded ' . count($this->productMap) . ' products');
            } else {
                $this->warn('No products found');
            }
        } catch (\Exception $e) {
            $this->warn('Failed to load product information: ' . $e->getMessage());
            $this->productMap = [];
        }
    }

    /**
     * Product IDから商品名を取得
     */
    protected function getProductName($productId)
    {
        if (empty($productId) || !isset($this->productMap[$productId])) {
            return 'Unknown Product';
        }
        
        $product = $this->productMap[$productId];
        return $product['name'];
    }

    protected function extractDomainFromOrderDetails($orderDetails)
    {
        $candidates = [];
        
        // 1. domain フィールドをチェック
        if (!empty($orderDetails['domain'])) {
            $candidates[] = $orderDetails['domain'];
        }
        
        // 2. domains フィールドから全てのドメインを取得
        if (!empty($orderDetails['domains'])) {
            $domains = array_map('trim', explode(',', $orderDetails['domains']));
            $candidates = array_merge($candidates, $domains);
        }
        
        // 3. SANから全てのドメインを取得
        if (!empty($orderDetails['san']) && is_array($orderDetails['san'])) {
            foreach ($orderDetails['san'] as $san) {
                if (!empty($san['san_name'])) {
                    $candidates[] = $san['san_name'];
                }
            }
        }
        
        // 4. CSRからドメインを抽出
        if (!empty($orderDetails['csr_code'])) {
            try {
                $csrDomain = $this->goGetSsl->extractDomainFromCSR($orderDetails['csr_code']);
                if ($csrDomain && $csrDomain !== 'N/A') {
                    $candidates[] = $csrDomain;
                }
            } catch (\Exception $e) {
                // CSR解析に失敗した場合は続行
            }
        }
        
        // 5. approver_method から URL を解析
        if (!empty($orderDetails['approver_method']['http']['link'])) {
            $url = $orderDetails['approver_method']['http']['link'];
            $parsed = parse_url($url);
            if (!empty($parsed['host'])) {
                $candidates[] = $parsed['host'];
            }
        }
        
        // 候補を評価
        $validDomains = [];
        $fallbackCandidates = [];
        
        foreach ($candidates as $candidate) {
            if ($this->isValidDomain($candidate)) {
                $validDomains[] = $candidate;
            } elseif (!empty($candidate) && $candidate !== 'N/A') {
                $fallbackCandidates[] = $candidate;
            }
        }
        
        // 有効なドメインがあればそれを返す
        if (!empty($validDomains)) {
            return $validDomains[0];
        }
        
        // 有効なドメインがない場合の詳細情報表示
        if (!empty($fallbackCandidates)) {
            $candidate = $fallbackCandidates[0];
            
            // 明らかに内部IDの場合は詳細情報を追加
            if (preg_match('/^[A-Z]\d+$/', $candidate)) {
                $internalId = $orderDetails['internal_id'] ?? 'N/A';
                return "Domain N/A (ID: {$candidate}, Internal: {$internalId})";
            }
            
            return $candidate . ' (ID)';
        }
        
        return 'N/A';
    }

    protected function isValidDomain($domain)
    {
        if (empty($domain) || $domain === 'N/A') {
            return false;
        }
        
        // 短すぎるもの (3文字未満) は除外
        if (strlen($domain) < 3) {
            return false;
        }
        
        // 数字のみは除外
        if (preg_match('/^\d+$/', $domain)) {
            return false;
        }
        
        // ワイルドカードドメイン: *.example.com
        if (preg_match('/^\*\.[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}$/', $domain)) {
            return true;
        }
        
        // 通常のドメイン: example.com
        if (preg_match('/^[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}$/', $domain)) {
            return true;
        }
        
        return false;
    }

    protected function filterOrdersByStatus($orders, $statusFilter)
    {
        if (!$statusFilter) {
            return $orders;
        }

        $filtered = array_filter($orders, function ($order) use ($statusFilter) {
            $orderStatus = $order['status'] ?? '';
            $match = $orderStatus === $statusFilter;
            
            // デバッグ: 特定のオーダーIDをチェック
            if ($order['order_id'] == '1682136') {
                Log::info("Filter debug for order 1682136", [
                    'order_status' => $orderStatus,
                    'filter_status' => $statusFilter,
                    'match' => $match
                ]);
            }
            
            return $match;
        });
        
        return $filtered;
    }

    protected function displayOrdersSummary($orders)
    {
        $statusCounts = [];
        foreach ($orders as $order) {
            $status = $order['status'] ?? 'unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        $this->newLine();
        $this->info('=== SSL Orders Summary ===');
        $this->info('Total Orders: ' . count($orders));
        
        foreach ($statusCounts as $status => $count) {
            $this->line("  {$status}: {$count}");
        }
        $this->newLine();
    }

    protected function displayOrdersTable($orders)
    {
        if (count($orders) > 50) {
            if (!$this->confirm('Display all ' . count($orders) . ' orders in table format?', false)) {
                $this->info('Skipping table display. Use --export option to save data.');
                return;
            }
        }

        $tableData = [];
        foreach ($orders as $order) {
            $domain = $order['domain'] ?? 'N/A';
            $productName = $order['product_name'] ?? 'N/A';
            
            // ドメイン情報が長すぎる場合は切り詰める（30文字に拡張）
            if (strlen($domain) > 30) {
                $domain = substr($domain, 0, 27) . '...';
            }
            
            // 商品名が長すぎる場合は切り詰める（60文字に拡張）
            if (strlen($productName) > 60) {
                $productName = substr($productName, 0, 57) . '...';
            }
            
            // SAN情報を構築
            $sanInfo = '';
            $singleSan = $order['single_san_count'] ?? 0;
            $wildcardSan = $order['wildcard_san_count'] ?? 0;
            
            if ($singleSan > 0 || $wildcardSan > 0) {
                $sanInfo = "S:{$singleSan} W:{$wildcardSan}";
            } else {
                $sanInfo = '-';
            }
            
            $tableData[] = [
                $order['order_id'] ?? 'N/A',
                $order['status'] ?? 'N/A',
                $domain,
                $productName,
                $sanInfo,
                $order['valid_from'] ?? 'N/A',
                $order['valid_till'] ?? 'N/A',
            ];
        }

        $this->table(
            ['Order ID', 'Status', 'Domain', 'Product', 'SAN', 'Valid From', 'Valid Till'],
            $tableData
        );
        
        // データ問題がある場合の注意事項を表示
        $hasDataIssues = false;
        foreach ($orders as $order) {
            if (str_contains($order['domain'] ?? '', 'Domain N/A')) {
                $hasDataIssues = true;
                break;
            }
        }
        
        if ($hasDataIssues) {
            $this->newLine();
            $this->warn('⚠️  Some orders have data inconsistency issues between API and Web UI.');
            $this->line('   This may require manual verification or contacting GoGetSSL support.');
        }
    }

    protected function exportOrders($orders, $format)
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "ssl_orders_{$timestamp}.{$format}";
        $filepath = storage_path("app/exports/{$filename}");

        $directory = dirname($filepath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        switch (strtolower($format)) {
            case 'csv':
                $this->exportToCsv($orders, $filepath);
                break;
            case 'json':
                $this->exportToJson($orders, $filepath);
                break;
            default:
                $this->error("Unsupported export format: {$format}");
                return;
        }

        $this->info("Orders exported to: {$filepath}");
    }

    protected function exportToCsv($orders, $filepath)
    {
        $fp = fopen($filepath, 'w');
        
        fputcsv($fp, [
            'Order ID',
            'Status', 
            'Domain',
            'Product Name',
            'Valid From',
            'Valid Till',
            'Product ID',
            'Period',
            'Server Count',
            'DCV Method',
            'Total Domains',
            'Base Domains',
            'Single SAN',
            'Wildcard SAN'
        ]);

        foreach ($orders as $order) {
            fputcsv($fp, [
                $order['order_id'] ?? '',
                $order['status'] ?? '',
                $order['domain'] ?? '',
                $order['product_name'] ?? '',
                $order['valid_from'] ?? '',
                $order['valid_till'] ?? '',
                $order['product_id'] ?? '',
                $order['period'] ?? '',
                $order['server_count'] ?? '',
                $order['dcv_method'] ?? '',
                $order['total_domains'] ?? '',
                $order['base_domain_count'] ?? '',
                $order['single_san_count'] ?? '',
                $order['wildcard_san_count'] ?? ''
            ]);
        }

        fclose($fp);
    }

    protected function exportToJson($orders, $filepath)
    {
        $jsonData = [
            'exported_at' => now()->toISOString(),
            'total_orders' => count($orders),
            'orders' => $orders
        ];

        file_put_contents($filepath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}