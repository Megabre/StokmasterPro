<?php
/**
 * Megabre StokMaster Pro
 * Dashboard Statistics
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Initialize Cache
$cache = Cache::getInstance();
$db = Database::getInstance();

// Today's payments
$todayPayments = $cache->remember('today_payments', function() use ($db) {
    $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                WHERE type = 'payment' AND date = CURDATE()");
    $result = $db->single();
    return $result ? $result['total'] : 0;
}, 3600); // Cache for 1 hour

// Today's debts
$todayDebts = $cache->remember('today_debts', function() use ($db) {
    $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                WHERE type = 'debt' AND date = CURDATE()");
    $result = $db->single();
    return $result ? $result['total'] : 0;
}, 3600); // Cache for 1 hour

// Low stock count
$lowStockCount = $cache->remember('low_stock_count', function() use ($db) {
    $db->query("SELECT COUNT(*) as count FROM products p
                JOIN (
                    SELECT product_id, 
                           SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level
                    FROM stock_movements
                    GROUP BY product_id
                ) sm ON p.id = sm.product_id
                WHERE sm.stock_level <= p.min_stock_level");
    $result = $db->single();
    return $result ? $result['count'] : 0;
}, 3600); // Cache for 1 hour

// Pending orders count
$pendingOrdersCount = $cache->remember('pending_orders_count', function() use ($db) {
    $db->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('pending', 'processing')");
    $result = $db->single();
    return $result ? $result['count'] : 0;
}, 3600); // Cache for 1 hour

// Monthly transactions chart data (current month)
$transactionData = $cache->remember('monthly_transactions', function() use ($db) {
    // Get current month dates
    $startDate = date('Y-m-01'); // First day of current month
    $endDate = date('Y-m-t'); // Last day of current month
    
    // Initialize data arrays
    $dates = [];
    $payments = [];
    $debts = [];
    
    // Generate all days in current month
    $period = new DatePeriod(
        new DateTime($startDate),
        new DateInterval('P1D'),
        new DateTime($endDate . ' +1 day') // Include end date
    );
    
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        $dates[] = $date->format('d.m');
        $payments[$dateStr] = 0;
        $debts[$dateStr] = 0;
    }
    
    // Get payments
    $db->query("SELECT date, COALESCE(SUM(amount), 0) as total 
                FROM transactions 
                WHERE type = 'payment' AND date BETWEEN :start_date AND :end_date 
                GROUP BY date");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $paymentsResult = $db->resultSet();
    
    foreach ($paymentsResult as $row) {
        $payments[$row['date']] = $row['total'];
    }
    
    // Get debts
    $db->query("SELECT date, COALESCE(SUM(amount), 0) as total 
                FROM transactions 
                WHERE type = 'debt' AND date BETWEEN :start_date AND :end_date 
                GROUP BY date");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $debtsResult = $db->resultSet();
    
    foreach ($debtsResult as $row) {
        $debts[$row['date']] = $row['total'];
    }
    
    // Convert to simple arrays for JSON
    $paymentsArray = array_values($payments);
    $debtsArray = array_values($debts);
    
    return [
        'dates' => $dates,
        'payments' => $paymentsArray,
        'debts' => $debtsArray
    ];
}, 3600); // Cache for 1 hour

$transactionDates = $transactionData['dates'];
$transactionPayments = $transactionData['payments'];
$transactionDebts = $transactionData['debts'];

// Summary chart data
$summaryData = $cache->remember('summary_data', function() use ($db) {
    // Total sales (orders)
    $db->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM orders");
    $salesResult = $db->single();
    $totalSales = $salesResult ? $salesResult['total'] : 0;
    
    // Total payments
    $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'payment'");
    $paymentsResult = $db->single();
    $totalPayments = $paymentsResult ? $paymentsResult['total'] : 0;
    
    // Total debts
    $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'debt'");
    $debtsResult = $db->single();
    $totalDebts = $debtsResult ? $debtsResult['total'] : 0;
    
    // Calculate profit/loss
    $profitLoss = $totalPayments - $totalDebts;
    
    return [
        'total_sales' => $totalSales,
        'total_payments' => $totalPayments,
        'profit_loss' => $profitLoss
    ];
}, 3600); // Cache for 1 hour

$totalSales = $summaryData['total_sales'];
$totalPayments = $summaryData['total_payments'];
$profitLoss = $summaryData['profit_loss'];

// Products by category chart data
$productCategoryData = $cache->remember('product_categories', function() use ($db) {
    $db->query("SELECT c.name, COUNT(p.id) as count 
                FROM products p 
                JOIN categories c ON p.category_id = c.id 
                GROUP BY c.id, c.name 
                ORDER BY count DESC
                LIMIT 10");
    $result = $db->resultSet();
    
    $categories = [];
    $counts = [];
    
    foreach ($result as $row) {
        $categories[] = $row['name'];
        $counts[] = $row['count'];
    }
    
    return [
        'categories' => $categories,
        'counts' => $counts
    ];
}, 3600); // Cache for 1 hour

$productCategories = $productCategoryData['categories'];
$productCounts = $productCategoryData['counts'];

// Orders by status chart data
$orderStatusData = $cache->remember('order_statuses', function() use ($db) {
    $db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $result = $db->resultSet();
    
    $statusCounts = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    
    foreach ($result as $row) {
        $statusCounts[$row['status']] = $row['count'];
    }
    
    return $statusCounts;
}, 3600); // Cache for 1 hour

$orderStatusCounts = $orderStatusData;

// Get customer statistics
$customerStats = $cache->remember('customer_stats', function() use ($db) {
    // Get total customers
    $db->query("SELECT COUNT(*) as count FROM customers");
    $totalCustomers = $db->single()['count'];
    
    // Get customers with debt
    $db->query("SELECT COUNT(DISTINCT c.id) as count 
                FROM customers c 
                LEFT JOIN transactions t ON c.id = t.customer_id 
                WHERE t.type = 'debt' 
                AND t.amount > 0");
    $debtCustomers = $db->single()['count'];
    
    // Get customers with credit
    $db->query("SELECT COUNT(DISTINCT c.id) as count 
                FROM customers c 
                LEFT JOIN transactions t ON c.id = t.customer_id 
                WHERE t.type = 'payment' 
                AND t.amount > 0");
    $creditCustomers = $db->single()['count'];
    
    // Get total debt amount
    $db->query("SELECT COALESCE(SUM(amount), 0) as total 
                FROM transactions 
                WHERE type = 'debt'");
    $totalDebt = $db->single()['total'];
    
    // Get total credit amount
    $db->query("SELECT COALESCE(SUM(amount), 0) as total 
                FROM transactions 
                WHERE type = 'payment'");
    $totalCredit = $db->single()['total'];
    
    return [
        'total_customers' => $totalCustomers,
        'debt_customers' => $debtCustomers,
        'credit_customers' => $creditCustomers,
        'total_debt' => $totalDebt,
        'total_credit' => $totalCredit
    ];
}, 3600); // Cache for 1 hour

// Get order statistics
$orderStats = $cache->remember('order_stats', function() use ($db) {
    // Get total orders
    $db->query("SELECT COUNT(*) as count FROM orders");
    $totalOrders = $db->single()['count'];
    
    // Get total order amount
    $db->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM orders");
    $totalAmount = $db->single()['total'];
    
    // Get orders by status
    $db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $statusCounts = $db->resultSet();
    
    $ordersByStatus = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    
    foreach ($statusCounts as $row) {
        $ordersByStatus[$row['status']] = $row['count'];
    }
    
    return [
        'total_orders' => $totalOrders,
        'total_amount' => $totalAmount,
        'by_status' => $ordersByStatus
    ];
}, 3600); // Cache for 1 hour

// Get product statistics
$productStats = $cache->remember('product_stats', function() use ($db) {
    // Get total products
    $db->query("SELECT COUNT(*) as count FROM products");
    $totalProducts = $db->single()['count'];
    
    // Get low stock products
    $db->query("SELECT COUNT(*) as count FROM products p
                JOIN (
                    SELECT product_id, 
                           SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level
                    FROM stock_movements
                    GROUP BY product_id
                ) sm ON p.id = sm.product_id
                WHERE sm.stock_level <= p.min_stock_level");
    $lowStockCount = $db->single()['count'];
    
    // Get out of stock products
    $db->query("SELECT COUNT(*) as count FROM products p
                JOIN (
                    SELECT product_id, 
                           SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level
                    FROM stock_movements
                    GROUP BY product_id
                ) sm ON p.id = sm.product_id
                WHERE sm.stock_level <= 0");
    $outOfStockCount = $db->single()['count'];
    
    return [
        'total_products' => $totalProducts,
        'low_stock' => $lowStockCount,
        'out_of_stock' => $outOfStockCount
    ];
}, 3600); // Cache for 1 hour

// Clear cache if requested
if (isset($_GET['clear_cache'])) {
    $cache->forget('customer_stats');
    $cache->forget('order_stats');
    $cache->forget('product_stats');
    $cache->forget('monthly_transactions');
    $cache->forget('summary_data');
    $cache->forget('product_categories');
    $cache->forget('order_statuses');
    $cache->forget('today_payments');
    $cache->forget('today_debts');
    $cache->forget('low_stock_count');
    $cache->forget('pending_orders_count');
}

// Return all statistics
return [
    'customer_stats' => $customerStats,
    'order_stats' => $orderStats,
    'product_stats' => $productStats,
    'transaction_data' => [
        'dates' => $transactionDates,
        'payments' => $transactionPayments,
        'debts' => $transactionDebts
    ],
    'summary_data' => [
        'total_sales' => $totalSales,
        'total_payments' => $totalPayments,
        'profit_loss' => $profitLoss
    ],
    'product_categories' => [
        'categories' => $productCategories,
        'counts' => $productCounts
    ],
    'order_statuses' => $orderStatusCounts,
    'today' => [
        'payments' => $todayPayments,
        'debts' => $todayDebts
    ],
    'alerts' => [
        'low_stock' => $lowStockCount,
        'pending_orders' => $pendingOrdersCount
    ]
];