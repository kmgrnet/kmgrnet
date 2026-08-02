<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function jsonResponse($code, $message, $data = [], $status = 200): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code($status);
    echo json_encode([
        'success' => $code === 0,
        'code' => $code,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'mysql';
    $port = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('MYSQL_DATABASE') ?: 'likeshop_db';
    $user = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('MYSQL_ROOT_PASSWORD') ?: 'GuangRun_DB_Pass_2026';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function initDbSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        order_id VARCHAR(64) PRIMARY KEY,
        pay_status TINYINT(1) NOT NULL DEFAULT 0,
        pay_method VARCHAR(32) NOT NULL DEFAULT 'wechat',
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        title VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        paid_at DATETIME NULL,
        channel VARCHAR(64) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS refunds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(64) NOT NULL,
        customer VARCHAR(128) NOT NULL DEFAULT '',
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        status VARCHAR(32) NOT NULL DEFAULT '待审核',
        reason VARCHAR(255) NOT NULL DEFAULT '',
        updated_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_order_id (order_id),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function loadOrders(): array {
    $pdo = getDbConnection();
    initDbSchema($pdo);

    $stmt = $pdo->query('SELECT order_id, pay_status, pay_method, amount, title, created_at, updated_at, paid_at, channel FROM orders ORDER BY created_at DESC, order_id DESC');
    $orders = [];
    while ($row = $stmt->fetch()) {
        $orders[$row['order_id']] = $row;
    }
    return $orders;
}

function getOrderState(array $orders, string $orderId): array {
    if (!isset($orders[$orderId])) {
        return [
            'order_id' => $orderId,
            'pay_status' => 0,
            'is_paid' => false,
            'status_text' => '待支付',
        ];
    }

    $order = $orders[$orderId];
    return [
        'order_id' => $orderId,
        'pay_status' => (int)($order['pay_status'] ?? 0),
        'is_paid' => ((int)($order['pay_status'] ?? 0)) === 1,
        'status_text' => ((int)($order['pay_status'] ?? 0)) === 1 ? '已支付' : '待支付',
    ];
}

try {
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($requestUri === '/health') {
        jsonResponse(0, 'ok', ['service' => 'kmgrnet-likeshop', 'timestamp' => date('c')]);
    }

    if ($requestUri === '/api/order/create' && $method === 'POST') {
        $pdo = getDbConnection();
        initDbSchema($pdo);

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $orderId = 'ORDER-' . strtoupper(bin2hex(random_bytes(4)));
        $createdAt = date('Y-m-d H:i:s');
        $payMethod = $payload['pay_method'] ?? 'wechat';
        $amount = (float)($payload['amount'] ?? 0);
        $title = $payload['title'] ?? '订单';

        $stmt = $pdo->prepare('INSERT INTO orders (order_id, pay_status, pay_method, amount, title, created_at, updated_at, paid_at, channel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$orderId, 0, $payMethod, $amount, $title, $createdAt, $createdAt, null, null]);

        jsonResponse(0, '订单创建成功', [
            'order_id' => $orderId,
            'pay_method' => $payMethod,
            'amount' => $amount,
            'pay_data' => [
                'type' => 'qrcode',
                'code_url' => 'https://pay.example.com/qrcode/' . $orderId,
                'redirect_url' => 'https://www.kmgrnet.com/pay/result?order_id=' . $orderId,
            ],
        ]);
    }

    if ($requestUri === '/api/orders/list' && $method === 'GET') {
        $orders = loadOrders();
        $statusFilter = strtolower((string)($_GET['status'] ?? 'all'));
        $channelFilter = strtolower((string)($_GET['channel'] ?? 'all'));
        $startDate = trim((string)($_GET['start_date'] ?? ''));
        $endDate = trim((string)($_GET['end_date'] ?? ''));
        $minAmount = array_key_exists('min_amount', $_GET) ? (float)$_GET['min_amount'] : null;
        $maxAmount = array_key_exists('max_amount', $_GET) ? (float)$_GET['max_amount'] : null;

        $list = [];
        foreach ($orders as $orderId => $order) {
            $payStatus = (int)($order['pay_status'] ?? 0);
            $payMethod = strtolower((string)($order['pay_method'] ?? 'wechat'));
            $orderChannel = strtolower((string)($order['channel'] ?? ''));
            $createdAt = (string)($order['created_at'] ?? '');
            $amount = (float)($order['amount'] ?? 0);

            if ($statusFilter === 'paid' && $payStatus !== 1) {
                continue;
            }
            if ($statusFilter === 'pending' && $payStatus === 1) {
                continue;
            }
            if ($channelFilter !== 'all' && $orderChannel !== $channelFilter) {
                continue;
            }
            if ($startDate !== '' && $createdAt !== '' && $createdAt < $startDate . ' 00:00:00') {
                continue;
            }
            if ($endDate !== '' && $createdAt !== '' && $createdAt > $endDate . ' 23:59:59') {
                continue;
            }
            if ($minAmount !== null && $amount < $minAmount) {
                continue;
            }
            if ($maxAmount !== null && $amount > $maxAmount) {
                continue;
            }
            $list[] = [
                'order_id' => $orderId,
                'pay_status' => $payStatus,
                'pay_method' => $payMethod,
                'amount' => $amount,
                'title' => $order['title'] ?? '订单',
                'created_at' => $createdAt,
                'paid_at' => $order['paid_at'] ?? '',
                'channel' => $order['channel'] ?? '',
                'updated_at' => $order['updated_at'] ?? '',
            ];
        }
        jsonResponse(0, '订单列表获取成功', array_reverse($list));
    }

    if ($requestUri === '/api/order/detail' && $method === 'GET') {
        $orders = loadOrders();
        $orderId = $_GET['order_id'] ?? '';
        if ($orderId === '' || !isset($orders[$orderId])) {
            jsonResponse(404, '订单不存在', [], 404);
        }
        $order = $orders[$orderId];
        jsonResponse(0, '订单详情获取成功', [
            'order_id' => $orderId,
            'pay_status' => (int)($order['pay_status'] ?? 0),
            'is_paid' => ((int)($order['pay_status'] ?? 0)) === 1,
            'pay_method' => $order['pay_method'] ?? 'wechat',
            'amount' => (float)($order['amount'] ?? 0),
            'title' => $order['title'] ?? '订单',
            'created_at' => $order['created_at'] ?? '',
            'updated_at' => $order['updated_at'] ?? '',
            'paid_at' => $order['paid_at'] ?? '',
            'channel' => $order['channel'] ?? '',
            'status_text' => ((int)($order['pay_status'] ?? 0)) === 1 ? '已支付' : '待支付',
        ]);
    }

    if ($requestUri === '/api/refunds/list' && $method === 'GET') {
        $pdo = getDbConnection();
        initDbSchema($pdo);

        $stmt = $pdo->query('SELECT order_id, customer, amount, status, reason, updated_at FROM refunds ORDER BY updated_at DESC LIMIT 20');
        $refunds = $stmt->fetchAll();
        if (empty($refunds)) {
            $seed = [
                ['ORDER-434F8647', '测试客户', 88.88, '待审核', '重复支付', '2026-08-02 09:14:00'],
                ['ORDER-7DE91D55', '合作客户', 19.99, '已退款', '服务调整', '2026-08-02 08:41:00'],
                ['ORDER-12AB90D1', '惠州客户', 129.00, '退款中', '订单取消', '2026-08-02 07:52:00'],
                ['ORDER-88FD03C2', 'VIP 客户', 499.00, '已退款', '产品未按约定交付', '2026-08-01 21:08:00'],
            ];
            foreach ($seed as $row) {
                $insert = $pdo->prepare('INSERT INTO refunds (order_id, customer, amount, status, reason, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([$row[0], $row[1], $row[2], $row[3], $row[4], $row[5], date('Y-m-d H:i:s')]);
            }
            $stmt = $pdo->query('SELECT order_id, customer, amount, status, reason, updated_at FROM refunds ORDER BY updated_at DESC LIMIT 20');
            $refunds = $stmt->fetchAll();
        }

        $data = array_map(function ($row) {
            return [
                'order_id' => $row['order_id'],
                'customer' => $row['customer'],
                'amount' => (float)$row['amount'],
                'status' => $row['status'],
                'reason' => $row['reason'],
                'updated_at' => $row['updated_at'],
            ];
        }, $refunds);

        jsonResponse(0, '退款列表获取成功', $data);
    }

    if ($requestUri === '/api/summary/reconciliation' && $method === 'GET') {
        $orders = loadOrders();
        $pdo = getDbConnection();
        initDbSchema($pdo);
        $refundStmt = $pdo->query('SELECT order_id, customer, amount, status, reason, updated_at FROM refunds ORDER BY updated_at DESC');
        $refundRows = $refundStmt->fetchAll();

        $paidOrders = array_values(array_filter($orders, fn ($order) => ((int)($order['pay_status'] ?? 0)) === 1));
        $totalRevenue = 0.0;
        foreach ($paidOrders as $order) {
            $totalRevenue += (float)($order['amount'] ?? 0);
        }

        $refundedTotal = 0.0;
        $pendingRefundCount = 0;
        $reviewCount = 0;
        $inProcessCount = 0;
        $completedCount = 0;
        foreach ($refundRows as $refund) {
            $status = (string)($refund['status'] ?? '待审核');
            $amount = (float)($refund['amount'] ?? 0);
            if (in_array($status, ['已退款', '退款中'], true)) {
                $refundedTotal += $amount;
            }
            if ($status === '待审核') {
                $reviewCount += 1;
                $pendingRefundCount += 1;
            }
            if ($status === '退款中') {
                $inProcessCount += 1;
                $pendingRefundCount += 1;
            }
            if ($status === '已退款') {
                $completedCount += 1;
            }
        }

        $netRevenue = $totalRevenue - $refundedTotal;

        $channelMap = [];
        foreach ($paidOrders as $order) {
            $channel = strtolower((string)($order['channel'] ?? 'unknown'));
            $label = $channel === 'wechat' ? '微信' : ($channel === 'alipay' ? '支付宝' : ($channel === 'pc' ? 'PC端' : ($channel === 'h5' ? 'H5' : '未标记')));
            if (!isset($channelMap[$channel])) {
                $channelMap[$channel] = ['name' => $channel, 'label' => $label, 'count' => 0, 'amount' => 0.0];
            }
            $channelMap[$channel]['count'] += 1;
            $channelMap[$channel]['amount'] += (float)($order['amount'] ?? 0);
        }
        $channelStats = array_values($channelMap);

        $monthMap = [];
        foreach ($paidOrders as $order) {
            $createdAt = (string)($order['created_at'] ?? '');
            $month = $createdAt !== '' ? substr($createdAt, 0, 7) : date('Y-m');
            if (!isset($monthMap[$month])) {
                $monthMap[$month] = 0.0;
            }
            $monthMap[$month] += (float)($order['amount'] ?? 0);
        }
        foreach ($refundRows as $refund) {
            $status = (string)($refund['status'] ?? '待审核');
            if (!in_array($status, ['已退款', '退款中'], true)) {
                continue;
            }
            $updatedAt = (string)($refund['updated_at'] ?? '');
            $month = $updatedAt !== '' ? substr($updatedAt, 0, 7) : date('Y-m');
            if (!isset($monthMap[$month])) {
                $monthMap[$month] = 0.0;
            }
            $monthMap[$month] -= (float)($refund['amount'] ?? 0);
        }

        $months = [];
        $cursor = new DateTimeImmutable('first day of this month');
        for ($i = 5; $i >= 0; $i--) {
            $month = $cursor->modify("-$i month")->format('Y-m');
            $months[] = ['label' => $month, 'amount' => round((float)($monthMap[$month] ?? 0.0), 2)];
        }

        $summary = [
            'order_count' => count($orders),
            'paid_order_count' => count($paidOrders),
            'total_revenue' => round($totalRevenue, 2),
            'refunded_total' => round($refundedTotal, 2),
            'net_revenue' => round($netRevenue, 2),
            'pending_refund_count' => $pendingRefundCount,
            'pending_review_count' => $reviewCount,
            'refund_in_process_count' => $inProcessCount,
            'completed_refund_count' => $completedCount,
            'channels' => $channelStats,
            'monthly' => $months,
        ];

        jsonResponse(0, '经营结算概览获取成功', $summary);
    }

    if ($requestUri === '/api/refunds/update' && $method === 'POST') {
        $pdo = getDbConnection();
        initDbSchema($pdo);

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $orderId = trim((string)($payload['order_id'] ?? ''));
        $status = trim((string)($payload['status'] ?? '待审核'));
        $reason = trim((string)($payload['reason'] ?? ''));
        $customer = trim((string)($payload['customer'] ?? '客户'));
        $amount = (float)($payload['amount'] ?? 0);
        $now = date('Y-m-d H:i:s');

        if ($orderId === '') {
            jsonResponse(400, '订单号不能为空', [], 400);
        }

        $existing = $pdo->prepare('SELECT * FROM refunds WHERE order_id = ? LIMIT 1');
        $existing->execute([$orderId]);
        $row = $existing->fetch();

        if ($row) {
            $stmt = $pdo->prepare('UPDATE refunds SET customer = ?, amount = ?, status = ?, reason = ?, updated_at = ? WHERE order_id = ?');
            $stmt->execute([$customer, $amount, $status, $reason, $now, $orderId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO refunds (order_id, customer, amount, status, reason, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$orderId, $customer, $amount, $status, $reason, $now, $now]);
        }

        jsonResponse(0, '退款状态已更新', [
            'order_id' => $orderId,
            'status' => $status,
            'updated_at' => $now,
        ]);
    }

    if (preg_match('#^/api/order/status$#', $requestUri) && $method === 'GET') {
        $orders = loadOrders();
        $orderId = $_GET['order_id'] ?? 'ORDER-DEMO';
        jsonResponse(0, '订单状态查询成功', getOrderState($orders, $orderId));
    }

    if (preg_match('#^/api/payment/notify/([a-zA-Z0-9_-]+)$#', $requestUri, $m) && $method === 'POST') {
        $pdo = getDbConnection();
        initDbSchema($pdo);

        $channel = $m[1];
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $orderId = $payload['order_id'] ?? '';
        if ($orderId !== '') {
            $stmt = $pdo->prepare('UPDATE orders SET pay_status = 1, updated_at = ?, paid_at = ?, channel = ? WHERE order_id = ?');
            $now = date('Y-m-d H:i:s');
            $stmt->execute([$now, $now, $channel, $orderId]);
        }
        jsonResponse(0, '回调已接收', [
            'channel' => $channel,
            'received' => true,
            'order_id' => $orderId,
        ]);
    }

    jsonResponse(404, '接口不存在', [], 404);
} catch (Throwable $e) {
    jsonResponse(500, '数据库错误: ' . $e->getMessage(), [], 500);
}
