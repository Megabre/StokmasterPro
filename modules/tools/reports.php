<?php
/**
 * Megabre StokMaster Pro
 * Reports Tool
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Initialize database connection
$db = Database::getInstance();

// Process date range parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';

// Tarih formatını doğrula ve temizle
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Tarihleri doğrula
if (!validateDate($startDate)) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
}

if (!validateDate($endDate)) {
    $endDate = date('Y-m-d');
}

// Bitiş tarihi başlangıç tarihinden önce olamaz
if (strtotime($endDate) < strtotime($startDate)) {
    $endDate = $startDate;
}

// Rapor türünü doğrula (finansal rapor kaldırıldı)
$allowedReportTypes = ['sales', 'inventory', 'customers'];
if (!in_array($reportType, $allowedReportTypes)) {
    $reportType = 'sales';
}

// Function to get sales data for the specified period
function getSalesData($startDate, $endDate) {
    $db = Database::getInstance();
    
    // Get daily sales data (finansal veriler kaldırıldı)
    $db->query("SELECT 
                DATE_FORMAT(o.order_date, '%d.%m.%Y') as date,
                DATE(o.order_date) as order_date,
                COUNT(DISTINCT o.id) as order_count,
                SUM(oi.quantity) as total_quantity
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE o.order_date BETWEEN :start_date AND :end_date
                GROUP BY DATE(o.order_date), DATE_FORMAT(o.order_date, '%d.%m.%Y')
                ORDER BY DATE(o.order_date) ASC");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $salesByDate = $db->resultSet();
    
    // Get sales by category (finansal veriler kaldırıldı)
    $db->query("SELECT 
                c.name as category,
                COUNT(DISTINCT oi.id) as item_count,
                SUM(oi.quantity) as quantity
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN products p ON oi.product_id = p.id
                JOIN categories c ON p.category_id = c.id
                WHERE o.order_date BETWEEN :start_date AND :end_date
                GROUP BY c.id, c.name
                ORDER BY quantity DESC");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $salesByCategory = $db->resultSet();
    
    // Get top products (finansal veriler kaldırıldı)
    $db->query("SELECT 
                p.name,
                c.name as category,
                SUM(oi.quantity) as quantity_sold
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN products p ON oi.product_id = p.id
                JOIN categories c ON p.category_id = c.id
                WHERE o.order_date BETWEEN :start_date AND :end_date
                GROUP BY p.id, p.name, c.id, c.name
                ORDER BY quantity_sold DESC
                LIMIT 10");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $topProducts = $db->resultSet();
    
    // Get top customers (finansal veriler kaldırıldı)
    $db->query("SELECT 
                c.first_name,
                c.last_name,
                c.company,
                COUNT(DISTINCT o.id) as order_count,
                SUM(oi.quantity) as total_quantity
                FROM customers c
                JOIN orders o ON c.id = o.customer_id
                JOIN order_items oi ON o.id = oi.order_id
                WHERE o.order_date BETWEEN :start_date AND :end_date
                GROUP BY c.id, c.first_name, c.last_name, c.company
                ORDER BY total_quantity DESC
                LIMIT 10");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $topCustomers = $db->resultSet();
    
    // Get total statistics (finansal veriler kaldırıldı)
    $db->query("SELECT 
                (SELECT COUNT(*) FROM products) as total_products,
                (SELECT COUNT(*) FROM customers) as total_customers,
                (SELECT COUNT(*) FROM orders WHERE order_date BETWEEN :orders_start AND :orders_end) as total_orders,
                (SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE type = 'in' AND date BETWEEN :stock_in_start AND :stock_in_end) as total_stock_in,
                (SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE type = 'out' AND date BETWEEN :stock_out_start AND :stock_out_end) as total_stock_out");

    $db->bind(':orders_start', $startDate);
    $db->bind(':orders_end', $endDate);
    $db->bind(':stock_in_start', $startDate);
    $db->bind(':stock_in_end', $endDate);
    $db->bind(':stock_out_start', $startDate);
    $db->bind(':stock_out_end', $endDate);
    $totals = $db->single();
    
    return [
        'sales_by_date' => $salesByDate,
        'sales_by_category' => $salesByCategory,
        'top_products' => $topProducts,
        'top_customers' => $topCustomers,
        'totals' => $totals
    ];
}

// Function to get inventory data
function getInventoryData() {
    $db = Database::getInstance();
    
    // Get stock summary (finansal veriler kaldırıldı)
    $db->query("SELECT
               COUNT(*) as total_products,
               SUM(CASE WHEN s.stock_level <= p.min_stock_level AND s.stock_level > 0 THEN 1 ELSE 0 END) as low_stock,
               SUM(CASE WHEN s.stock_level <= 0 THEN 1 ELSE 0 END) as out_of_stock
               FROM products p
               LEFT JOIN (
                   SELECT product_id, 
                   SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level
                   FROM stock_movements
                   GROUP BY product_id
               ) s ON p.id = s.product_id");
    $stockSummary = $db->single();
    
    // Get products with low stock
    $db->query("SELECT p.id, p.name, p.price, c.name as category, p.min_stock_level,
               s.stock_level,
               (p.min_stock_level - s.stock_level) as missing_quantity
               FROM products p
               JOIN categories c ON p.category_id = c.id
               LEFT JOIN (
                   SELECT product_id, 
                   SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level
                   FROM stock_movements
                   GROUP BY product_id
               ) s ON p.id = s.product_id
               WHERE s.stock_level <= p.min_stock_level AND s.stock_level > 0
               ORDER BY (p.min_stock_level - s.stock_level) DESC
               LIMIT 20");
    $lowStock = $db->resultSet();
    
    // Get out of stock products
    $db->query("SELECT p.id, p.name, p.price, c.name as category, p.min_stock_level,
               COALESCE(s.stock_level, 0) as stock_level,
               p.min_stock_level as missing_quantity
               FROM products p
               JOIN categories c ON p.category_id = c.id
               LEFT JOIN (
                   SELECT product_id, 
                   SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level
                   FROM stock_movements
                   GROUP BY product_id
               ) s ON p.id = s.product_id
               WHERE COALESCE(s.stock_level, 0) <= 0
               ORDER BY p.min_stock_level DESC
               LIMIT 20");
    $outOfStock = $db->resultSet();
    
    // Get inventory by category (finansal veriler kaldırıldı)
    $db->query("SELECT c.name as category,
               COUNT(p.id) as product_count,
               SUM(COALESCE(s.stock_level, 0)) as total_stock
               FROM products p
               JOIN categories c ON p.category_id = c.id
               LEFT JOIN (
                   SELECT product_id, 
                   SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level
                   FROM stock_movements
                   GROUP BY product_id
               ) s ON p.id = s.product_id
               GROUP BY c.id, c.name
               ORDER BY total_stock DESC");
    $inventoryByCategory = $db->resultSet();
    
    // Get top 20 products by stock quantity (finansal veriler kaldırıldı)
    $db->query("SELECT p.id, p.name, c.name as category,
               COALESCE(s.stock_level, 0) as stock_level
               FROM products p
               JOIN categories c ON p.category_id = c.id
               LEFT JOIN (
                   SELECT product_id, 
                   SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level
                   FROM stock_movements
                   GROUP BY product_id
               ) s ON p.id = s.product_id
               WHERE COALESCE(s.stock_level, 0) > 0
               ORDER BY stock_level DESC
               LIMIT 20");
    $topStockProducts = $db->resultSet();
    
    return [
        'stock_summary' => $stockSummary,
        'low_stock' => $lowStock,
        'out_of_stock' => $outOfStock,
        'inventory_by_category' => $inventoryByCategory,
        'top_stock_products' => $topStockProducts
    ];
}

// Function to get financial data
function getFinancialData($startDate, $endDate) {
    $db = Database::getInstance();
    
    // Get financial summary
    $db->query("SELECT 
               SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as total_payments,
               SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END) as total_debts,
               (SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) - SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END)) as balance
               FROM transactions
               WHERE date BETWEEN :start_date AND :end_date");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $financialSummary = $db->single();
    
    // Get transactions by date
    $db->query("SELECT DATE_FORMAT(date, '%Y-%m-%d') as date,
               SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as payments,
               SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END) as debts,
               (SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) - SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END)) as balance
               FROM transactions
               WHERE date BETWEEN :start_date AND :end_date
               GROUP BY DATE_FORMAT(date, '%Y-%m-%d')
               ORDER BY date ASC");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $transactionsByDate = $db->resultSet();
    
    // Get transactions by payment method
    $db->query("SELECT payment_method,
               COUNT(*) as transaction_count,
               SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as payments,
               SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END) as debts,
               (SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) - SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END)) as balance
               FROM transactions
               WHERE date BETWEEN :start_date AND :end_date
               GROUP BY payment_method
               ORDER BY payments DESC");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $transactionsByMethod = $db->resultSet();
    
    // Get top 10 customers with most payments
    $db->query("SELECT c.id, c.first_name, c.last_name, c.company,
               COUNT(t.id) as transaction_count,
               SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) as payments,
               SUM(CASE WHEN t.type = 'debt' THEN t.amount ELSE 0 END) as debts,
               (SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) - SUM(CASE WHEN t.type = 'debt' THEN t.amount ELSE 0 END)) as balance
               FROM transactions t
               JOIN customers c ON t.customer_id = c.id
               WHERE t.date BETWEEN :start_date AND :end_date
               GROUP BY c.id, c.first_name, c.last_name, c.company
               ORDER BY payments DESC
               LIMIT 10");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $topPayingCustomers = $db->resultSet();
    
    // Get top 10 customers with most debt
    $db->query("SELECT c.id, c.first_name, c.last_name, c.company,
               COUNT(t.id) as transaction_count,
               SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) as payments,
               SUM(CASE WHEN t.type = 'debt' THEN t.amount ELSE 0 END) as debts,
               (SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) - SUM(CASE WHEN t.type = 'debt' THEN t.amount ELSE 0 END)) as balance
               FROM transactions t
               JOIN customers c ON t.customer_id = c.id
               WHERE t.date BETWEEN :start_date AND :end_date
               GROUP BY c.id, c.first_name, c.last_name, c.company
               ORDER BY debts DESC
               LIMIT 10");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $topDebtCustomers = $db->resultSet();
    
    return [
        'financial_summary' => $financialSummary,
        'transactions_by_date' => $transactionsByDate,
        'transactions_by_method' => $transactionsByMethod,
        'top_paying_customers' => $topPayingCustomers,
        'top_debt_customers' => $topDebtCustomers
    ];
}

// Function to get customer data
function getCustomerData($startDate, $endDate) {
    $db = Database::getInstance();
    
    // Get customer summary
    $db->query("SELECT 
               COUNT(*) as total_customers,
               COUNT(DISTINCT o.customer_id) as active_customers,
               COUNT(*) - COUNT(DISTINCT o.customer_id) as inactive_customers
               FROM customers c
               LEFT JOIN orders o ON c.id = o.customer_id AND o.order_date BETWEEN :start_date AND :end_date");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $customerSummary = $db->single();
    
    // Get customers by order count (finansal veriler kaldırıldı)
    $db->query("SELECT c.id, c.first_name, c.last_name, c.company, c.phone, c.email,
               COUNT(o.id) as order_count,
               SUM(oi.quantity) as total_quantity,
               MAX(o.order_date) as last_order_date
               FROM customers c
               JOIN orders o ON c.id = o.customer_id
               JOIN order_items oi ON o.id = oi.order_id
               WHERE o.order_date BETWEEN :start_date AND :end_date
               GROUP BY c.id, c.first_name, c.last_name, c.company, c.phone, c.email
               ORDER BY order_count DESC
               LIMIT 20");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $topOrderCustomers = $db->resultSet();
    
    // Get customers by order quantity (finansal veriler kaldırıldı)
    $db->query("SELECT c.id, c.first_name, c.last_name, c.company, c.phone, c.email,
               COUNT(o.id) as order_count,
               SUM(oi.quantity) as total_quantity,
               MAX(o.order_date) as last_order_date
               FROM customers c
               JOIN orders o ON c.id = o.customer_id
               JOIN order_items oi ON o.id = oi.order_id
               WHERE o.order_date BETWEEN :start_date AND :end_date
               GROUP BY c.id, c.first_name, c.last_name, c.company, c.phone, c.email
               ORDER BY total_quantity DESC
               LIMIT 20");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $topSpendingCustomers = $db->resultSet();
    
    // Get inactive customers (no orders in the date range)
    $db->query("SELECT c.id, c.first_name, c.last_name, c.company, c.phone, c.email,
               MAX(o.order_date) as last_order_date
               FROM customers c
               LEFT JOIN orders o ON c.id = o.customer_id
               WHERE c.id NOT IN (
                   SELECT DISTINCT customer_id FROM orders 
                   WHERE order_date BETWEEN :start_date AND :end_date
               )
               GROUP BY c.id, c.first_name, c.last_name, c.company, c.phone, c.email
               ORDER BY last_order_date DESC
               LIMIT 20");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
    $inactiveCustomers = $db->resultSet();
    
    return [
        'customer_summary' => $customerSummary,
        'top_order_customers' => $topOrderCustomers,
        'top_spending_customers' => $topSpendingCustomers,
        'inactive_customers' => $inactiveCustomers
    ];
}

// Load data based on report type (finansal rapor kaldırıldı)
switch ($reportType) {
    case 'sales':
        $reportData = getSalesData($startDate, $endDate);
        break;
        
    case 'inventory':
        $reportData = getInventoryData();
        break;
        
    case 'customers':
        $reportData = getCustomerData($startDate, $endDate);
        break;
        
    default:
        $reportData = getSalesData($startDate, $endDate);
        $reportType = 'sales';
        break;
}

// Get company information from settings
$db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone', 'company_email', 'company_tax_id', 'company_logo')");
$settingsResult = $db->resultSet();
$companyInfo = [];
foreach ($settingsResult as $row) {
    $companyInfo[$row['setting_key']] = $row['setting_value'];
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Company Information (Print Only) -->
<div class="print-only" style="display: none;">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <?php if (!empty($companyInfo['company_logo'])): ?>
                    <img src="<?php echo url('uploads/company/' . $companyInfo['company_logo']); ?>" alt="Logo" class="img-fluid" style="max-height: 100px;">
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <?php if (!empty($companyInfo['company_name'])): ?>
                            <tr>
                                <th width="30%" class="bg-light"><?php echo t('settings_system_company_name', 'Firma Adı'); ?></th>
                                <td><?php echo e($companyInfo['company_name']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($companyInfo['company_address'])): ?>
                            <tr>
                                <th class="bg-light"><?php echo t('address', 'Adres'); ?></th>
                                <td><?php echo nl2br(e($companyInfo['company_address'])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th class="bg-light"><?php echo t('contact', 'İletişim'); ?></th>
                                <td>
                                    <?php if (!empty($companyInfo['company_phone'])): ?>
                                    <strong><?php echo t('phone', 'Telefon'); ?>:</strong> <?php echo e($companyInfo['company_phone']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($companyInfo['company_email'])): ?>
                                    <strong><?php echo t('email', 'E-posta'); ?>:</strong> <?php echo e($companyInfo['company_email']); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($companyInfo['company_tax_id'])): ?>
                            <tr>
                                <th class="bg-light"><?php echo t('settings_system_company_tax_id', 'Vergi No'); ?></th>
                                <td><?php echo e($companyInfo['company_tax_id']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page Header -->
<div class="page-header">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle"><?php echo t('tools_title', 'Araçlar'); ?></div>
                <h2 class="page-title"><?php echo t('reports_title', 'Detaylı Raporlar'); ?></h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <button type="button" class="btn btn-primary" id="printReport">
                    <i class="ti ti-printer"></i> <?php echo t('reports_print', 'Raporu Yazdır'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="page-body">
    <div class="container-xl">
<!-- Report Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="reportFilterForm" class="row g-3" method="get" action="<?php echo url('index.php'); ?>">
            <input type="hidden" name="module" value="tools">
            <input type="hidden" name="action" value="reports">
            
            <div class="col-md-3">
                <label for="report_type" class="form-label"><?php echo t('reports_report_type', 'Rapor Türü'); ?></label>
                <select id="report_type" name="report_type" class="form-select">
                    <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>><?php echo t('reports_sales_report', 'Satış Raporu'); ?></option>
                    <option value="inventory" <?php echo $reportType === 'inventory' ? 'selected' : ''; ?>><?php echo t('reports_inventory_report', 'Stok Raporu'); ?></option>
                    <option value="customers" <?php echo $reportType === 'customers' ? 'selected' : ''; ?>><?php echo t('reports_customer_report', 'Müşteri Raporu'); ?></option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="start_date" class="form-label"><?php echo t('reports_start_date', 'Başlangıç Tarihi'); ?></label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            
            <div class="col-md-3">
                <label for="end_date" class="form-label"><?php echo t('reports_end_date', 'Bitiş Tarihi'); ?></label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter"></i> <?php echo t('filter', 'Filtrele'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Content -->
<div id="reportContent">
    <?php if ($reportType === 'sales'): ?>
    <!-- Sales Report -->
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-primary"><?php echo htmlspecialchars($reportData['totals']['total_products']); ?></h2>
                    <p class="text-muted mb-0"><?php echo t('reports_total_products', 'Toplam Ürün'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-success"><?php echo htmlspecialchars($reportData['totals']['total_customers']); ?></h2>
                    <p class="text-muted mb-0"><?php echo t('reports_total_customers', 'Toplam Müşteri'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-info"><?php echo htmlspecialchars($reportData['totals']['total_orders']); ?></h2>
                    <p class="text-muted mb-0"><?php echo t('reports_total_orders', 'Toplam Sipariş'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_daily_sales_chart', 'Günlük Satış Grafiği'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="dailySalesChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_category_sales', 'Kategori Bazlı Satışlar'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="categorySalesChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_stock_movements', 'Stok Hareketleri'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="stats-info text-center p-3">
                                <h2 class="mb-0 text-success"><?php echo htmlspecialchars($reportData['totals']['total_stock_in']); ?></h2>
                                <p class="text-muted mb-0"><?php echo t('reports_stock_in', 'Giriş'); ?></p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stats-info text-center p-3">
                                <h2 class="mb-0 text-danger"><?php echo htmlspecialchars($reportData['totals']['total_stock_out']); ?></h2>
                                <p class="text-muted mb-0"><?php echo t('reports_stock_out', 'Çıkış'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_top_selling_products', 'En Çok Satan Ürünler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo t('reports_product', 'Ürün'); ?></th>
                                    <th><?php echo t('reports_category', 'Kategori'); ?></th>
                                    <th><?php echo t('reports_quantity_sold', 'Satılan Miktar'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['top_products'] as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($product['quantity_sold']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_top_shopping_customers', 'En Çok Alışveriş Yapan Müşteriler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo t('reports_customer', 'Müşteri'); ?></th>
                                    <th><?php echo t('reports_order', 'Sipariş'); ?></th>
                                    <th><?php echo t('reports_total_quantity', 'Toplam Miktar'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['top_customers'] as $customer): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        <?php echo !empty($customer['company']) ? '<br><small class="text-muted">' . htmlspecialchars($customer['company']) . '</small>' : ''; ?>
                                    </td>
                                    <td class="text-end"><?php echo htmlspecialchars($customer['order_count']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($customer['total_quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title"><?php echo t('reports_category_sales_detail', 'Kategori Bazlı Detaylı Satış Raporu'); ?></h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th><?php echo t('reports_category', 'Kategori'); ?></th>
                            <th><?php echo t('reports_product_count', 'Ürün Adedi'); ?></th>
                            <th><?php echo t('reports_sales_quantity', 'Satış Miktarı'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalQuantity = 0;
                        foreach ($reportData['sales_by_category'] as $category) {
                            $totalQuantity += $category['quantity'];
                        }
                        ?>
                        
                        <?php foreach ($reportData['sales_by_category'] as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['category']); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($category['item_count']); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($category['quantity']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td><?php echo t('reports_total', 'Toplam'); ?></td>
                            <td class="text-end">
                                <?php echo htmlspecialchars(array_sum(array_column($reportData['sales_by_category'], 'item_count'))); ?>
                            </td>
                            <td class="text-end">
                                <?php echo htmlspecialchars($totalQuantity); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <?php elseif ($reportType === 'inventory'): ?>
    <!-- Inventory Report -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title"><?php echo t('reports_inventory_summary', 'Stok Özeti'); ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-info text-center p-3">
                        <h2 class="mb-0 text-primary"><?php echo htmlspecialchars($reportData['stock_summary']['total_products']); ?></h2>
                        <p class="text-muted mb-0"><?php echo t('reports_total_products', 'Toplam Ürün'); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-info text-center p-3">
                        <h2 class="mb-0 text-warning"><?php echo htmlspecialchars($reportData['stock_summary']['low_stock']); ?></h2>
                        <p class="text-muted mb-0"><?php echo t('reports_critical_stock', 'Kritik Stok'); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-info text-center p-3">
                        <h2 class="mb-0 text-danger"><?php echo htmlspecialchars($reportData['stock_summary']['out_of_stock']); ?></h2>
                        <p class="text-muted mb-0"><?php echo t('reports_out_of_stock', 'Stokta Olmayan'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_category_stock_distribution', 'Kategori Bazlı Stok Dağılımı'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="inventoryValueChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_stock_status', 'Stok Durumu'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="stockStatusChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="card-title mb-0"><?php echo t('reports_critical_level_products', 'Kritik Seviyedeki Ürünler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo t('reports_product', 'Ürün'); ?></th>
                                    <th><?php echo t('reports_category', 'Kategori'); ?></th>
                                    <th><?php echo t('reports_current_stock', 'Mevcut Stok'); ?></th>
                                    <th><?php echo t('reports_min_stock', 'Min. Stok'); ?></th>
                                    <th><?php echo t('reports_missing_quantity', 'Eksik Miktar'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['low_stock'] as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td class="text-end text-warning fw-bold"><?php echo htmlspecialchars($product['stock_level']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($product['min_stock_level']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($product['missing_quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0"><?php echo t('reports_out_of_stock_products', 'Stokta Olmayan Ürünler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo t('reports_product', 'Ürün'); ?></th>
                                    <th><?php echo t('reports_category', 'Kategori'); ?></th>
                                    <th><?php echo t('reports_stock', 'Stok'); ?></th>
                                    <th><?php echo t('reports_min_stock', 'Min. Stok'); ?></th>
                                    <th><?php echo t('reports_missing_quantity', 'Eksik Miktar'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['out_of_stock'] as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td class="text-end text-danger fw-bold"><?php echo htmlspecialchars($product['stock_level']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($product['min_stock_level']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($product['missing_quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_category_stock_report', 'Kategori Bazlı Stok Raporu'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo t('reports_category', 'Kategori'); ?></th>
                                    <th><?php echo t('reports_product_count', 'Ürün Sayısı'); ?></th>
                                    <th><?php echo t('reports_total_stock', 'Toplam Stok'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['inventory_by_category'] as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['category']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($category['product_count']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($category['total_stock']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td><?php echo t('reports_total', 'Toplam'); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(array_sum(array_column($reportData['inventory_by_category'], 'product_count'))); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(array_sum(array_column($reportData['inventory_by_category'], 'total_stock'))); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_most_stocked_products', 'En Çok Stoklu Ürünler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo t('reports_product', 'Ürün'); ?></th>
                                    <th><?php echo t('reports_category', 'Kategori'); ?></th>
                                    <th><?php echo t('reports_stock_quantity', 'Stok Miktarı'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['top_stock_products'] as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($product['stock_level']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($reportType === 'customers'): ?>
    <!-- Customer Report -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title"><?php echo t('reports_customer_summary', 'Müşteri Özeti'); ?> (<?php echo date('d.m.Y', strtotime($startDate)); ?> - <?php echo date('d.m.Y', strtotime($endDate)); ?>)</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="stats-info text-center p-3">
                        <h2 class="mb-0 text-primary"><?php echo htmlspecialchars($reportData['customer_summary']['total_customers']); ?></h2>
                        <p class="text-muted mb-0"><?php echo t('reports_total_customers', 'Toplam Müşteri'); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-info text-center p-3">
                        <h2 class="mb-0 text-success"><?php echo htmlspecialchars($reportData['customer_summary']['active_customers']); ?></h2>
                        <p class="text-muted mb-0"><?php echo t('reports_active_customer', 'Aktif Müşteri'); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-info text-center p-3">
                        <h2 class="mb-0 text-warning"><?php echo htmlspecialchars($reportData['customer_summary']['inactive_customers']); ?></h2>
                        <p class="text-muted mb-0"><?php echo t('reports_inactive_customer', 'Pasif Müşteri'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_active_inactive_distribution', 'Aktif/Pasif Müşteri Dağılımı'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="customerActivityChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_most_ordering_customers', 'En Çok Sipariş Veren Müşteriler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo t('reports_customer', 'Müşteri'); ?></th>
                                    <th><?php echo t('reports_order_count_label', 'Sipariş Sayısı'); ?></th>
                                    <th><?php echo t('reports_total_quantity', 'Toplam Miktar'); ?></th>
                                    <th><?php echo t('reports_last_order', 'Son Sipariş'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['top_order_customers'] as $customer): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        <?php echo !empty($customer['company']) ? '<br><small class="text-muted">' . htmlspecialchars($customer['company']) . '</small>' : ''; ?>
                                    </td>
                                    <td class="text-end"><?php echo htmlspecialchars($customer['order_count']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($customer['total_quantity']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(date('d.m.Y', strtotime($customer['last_order_date']))); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('reports_most_spending_customers', 'En Çok Harcama Yapan Müşteriler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo t('reports_customer', 'Müşteri'); ?></th>
                                    <th><?php echo t('reports_order_count_label', 'Sipariş Sayısı'); ?></th>
                                    <th><?php echo t('reports_total_quantity', 'Toplam Miktar'); ?></th>
                                    <th><?php echo t('reports_last_order', 'Son Sipariş'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['top_spending_customers'] as $customer): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        <?php echo !empty($customer['company']) ? '<br><small class="text-muted">' . htmlspecialchars($customer['company']) . '</small>' : ''; ?>
                                    </td>
                                    <td class="text-end"><?php echo htmlspecialchars($customer['order_count']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($customer['total_quantity']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(date('d.m.Y', strtotime($customer['last_order_date']))); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header bg-warning text-white">
            <h5 class="card-title mb-0"><?php echo t('reports_inactive_customers_list', 'Pasif Müşteriler (Belirtilen Tarih Aralığında Sipariş Vermeyenler)'); ?></h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th><?php echo t('reports_customer', 'Müşteri'); ?></th>
                            <th><?php echo t('reports_phone', 'Telefon'); ?></th>
                            <th><?php echo t('reports_email', 'E-posta'); ?></th>
                            <th><?php echo t('reports_last_order', 'Son Sipariş'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['inactive_customers'] as $customer): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                <?php echo !empty($customer['company']) ? '<br><small class="text-muted">' . htmlspecialchars($customer['company']) . '</small>' : ''; ?>
                            </td>
                            <td><?php echo htmlspecialchars($customer['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></td>
                            <td>
                                <?php echo htmlspecialchars(!empty($customer['last_order_date']) ? date('d.m.Y', strtotime($customer['last_order_date'])) : t('reports_no_order', 'Hiç Sipariş Yok')); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    $(document).ready(function() {
        // Toggle date range visibility based on report type
        $('#report_type').on('change', function() {
            const reportType = $(this).val();
            
            if (reportType === 'inventory') {
                $('#start_date, #end_date').parent().hide();
            } else {
                $('#start_date, #end_date').parent().show();
            }
        });
        
        // Initialize date range visibility
        if ($('#report_type').val() === 'inventory') {
            $('#start_date, #end_date').parent().hide();
        }
        
        <?php if ($reportType === 'sales'): ?>
        // Günlük Satış Grafiği - ApexCharts (Stacked Bar Chart - Tabler.io Style)
        const dailySalesChartEl = document.getElementById('dailySalesChart');
        if (dailySalesChartEl) {
            const dailySalesOptions = {
                series: [{
                    name: '<?php echo t('reports_daily_sales_quantity', 'Günlük Satış Miktarı'); ?>',
                    data: <?php echo json_encode(array_map(function($item) { 
                        return intval($item['total_quantity']); 
                    }, $reportData['sales_by_date'])); ?>
                }],
                chart: {
                    type: 'bar',
                    height: 300,
                    toolbar: {
                        show: false
                    },
                    fontFamily: 'inherit'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 4
                    }
                },
                dataLabels: {
                    enabled: false
                },
                colors: ['color-mix(in srgb, transparent, var(--tblr-primary) 100%)'],
                xaxis: {
                    categories: <?php echo json_encode(array_column($reportData['sales_by_date'], 'date')); ?>,
                    labels: {
                        style: {
                            fontSize: '12px',
                            fontWeight: 400
                        }
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function(value) {
                            return Math.floor(value).toLocaleString('tr-TR');
                        },
                        style: {
                            fontSize: '11px',
                            fontWeight: 400
                        }
                    }
                },
                grid: {
                    borderColor: '#e0e0e0',
                    strokeDashArray: 4,
                    xaxis: {
                        lines: {
                            show: true
                        }
                    },
                    yaxis: {
                        lines: {
                            show: true
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return value.toLocaleString('tr-TR');
                        }
                    },
                    style: {
                        fontSize: '12px'
                    }
                },
                fill: {
                    opacity: 1
                }
            };
            const dailySalesChart = new ApexCharts(dailySalesChartEl, dailySalesOptions);
            dailySalesChart.render();
        }

        // Kategori Bazlı Satışlar Grafiği - ApexCharts (Donut Chart)
        const categorySalesChartEl = document.getElementById('categorySalesChart');
        if (categorySalesChartEl) {
            const categorySalesOptions = {
                series: <?php echo json_encode(array_map(function($item) { 
                    return intval($item['quantity']); 
                }, $reportData['sales_by_category'])); ?>,
                chart: {
                    type: 'donut',
                    height: 300
                },
                labels: <?php echo json_encode(array_column($reportData['sales_by_category'], 'category')); ?>,
                colors: [
                    'color-mix(in srgb, transparent, var(--tblr-primary) 100%)',
                    'color-mix(in srgb, transparent, var(--tblr-primary) 80%)',
                    'color-mix(in srgb, transparent, var(--tblr-green) 100%)',
                    'color-mix(in srgb, transparent, var(--tblr-yellow) 100%)',
                    'color-mix(in srgb, transparent, var(--tblr-red) 100%)'
                ],
                legend: {
                    position: 'bottom'
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%'
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function(val) {
                        return val.toFixed(0) + '%';
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return value.toLocaleString('tr-TR');
                        }
                    }
                }
            };
            const categorySalesChart = new ApexCharts(categorySalesChartEl, categorySalesOptions);
            categorySalesChart.render();
        }
        <?php endif; ?>
        
        <?php if ($reportType === 'inventory'): ?>
        // Kategori Bazlı Stok Dağılımı - ApexCharts (Bar Chart - Tabler.io Style)
        const inventoryValueChartEl = document.getElementById('inventoryValueChart');
        if (inventoryValueChartEl) {
            const inventoryValueOptions = {
                series: [{
                    name: '<?php echo t('reports_stock_quantity', 'Stok Miktarı'); ?>',
                    data: <?php echo json_encode(array_map(function($item) { 
                        return intval($item['total_stock']); 
                    }, $reportData['inventory_by_category'])); ?>
                }],
                chart: {
                    type: 'bar',
                    height: 300,
                    toolbar: {
                        show: false
                    },
                    fontFamily: 'inherit'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 4
                    }
                },
                dataLabels: {
                    enabled: false
                },
                colors: ['color-mix(in srgb, transparent, var(--tblr-primary) 100%)'],
                xaxis: {
                    categories: <?php echo json_encode(array_column($reportData['inventory_by_category'], 'category')); ?>,
                    labels: {
                        style: {
                            fontSize: '12px',
                            fontWeight: 400
                        }
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function(value) {
                            return Math.floor(value).toLocaleString('tr-TR');
                        },
                        style: {
                            fontSize: '11px',
                            fontWeight: 400
                        }
                    }
                },
                grid: {
                    borderColor: '#e0e0e0',
                    strokeDashArray: 4,
                    xaxis: {
                        lines: {
                            show: true
                        }
                    },
                    yaxis: {
                        lines: {
                            show: true
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return value.toLocaleString('tr-TR');
                        }
                    },
                    style: {
                        fontSize: '12px'
                    }
                },
                fill: {
                    opacity: 1
                }
            };
            const inventoryValueChart = new ApexCharts(inventoryValueChartEl, inventoryValueOptions);
            inventoryValueChart.render();
        }

        // Stok Durumu Grafiği - ApexCharts (Donut Chart)
        const stockStatusChartEl = document.getElementById('stockStatusChart');
        if (stockStatusChartEl) {
            const stockStatusOptions = {
                series: [
                    <?php echo htmlspecialchars($reportData['stock_summary']['total_products'] - $reportData['stock_summary']['low_stock'] - $reportData['stock_summary']['out_of_stock']); ?>,
                    <?php echo htmlspecialchars($reportData['stock_summary']['low_stock']); ?>,
                    <?php echo htmlspecialchars($reportData['stock_summary']['out_of_stock']); ?>
                ],
                chart: {
                    type: 'donut',
                    height: 300
                },
                labels: ['<?php echo t('reports_normal', 'Normal'); ?>', '<?php echo t('reports_critical', 'Kritik'); ?>', '<?php echo t('reports_no_stock', 'Stokta Yok'); ?>'],
                colors: [
                    'color-mix(in srgb, transparent, var(--tblr-green) 100%)',
                    'color-mix(in srgb, transparent, var(--tblr-yellow) 100%)',
                    'color-mix(in srgb, transparent, var(--tblr-red) 100%)'
                ],
                legend: {
                    position: 'bottom'
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%'
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function(val) {
                        return val.toFixed(0) + '%';
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return value + ' ürün';
                        }
                    }
                }
            };
            const stockStatusChart = new ApexCharts(stockStatusChartEl, stockStatusOptions);
            stockStatusChart.render();
        }
        <?php endif; ?>
        
        <?php if ($reportType === 'customers'): ?>
        // Aktif/Pasif Müşteri Dağılımı - ApexCharts (Donut Chart)
        const customerActivityChartEl = document.getElementById('customerActivityChart');
        if (customerActivityChartEl) {
            const customerActivityOptions = {
                series: [
                    <?php echo htmlspecialchars($reportData['customer_summary']['active_customers']); ?>,
                    <?php echo htmlspecialchars($reportData['customer_summary']['inactive_customers']); ?>
                ],
                chart: {
                    type: 'donut',
                    height: 300
                },
                labels: ['<?php echo t('reports_active_customers', 'Aktif Müşteriler'); ?>', '<?php echo t('reports_inactive_customers', 'Pasif Müşteriler'); ?>'],
                colors: [
                    'color-mix(in srgb, transparent, var(--tblr-green) 100%)',
                    'color-mix(in srgb, transparent, var(--tblr-yellow) 100%)'
                ],
                legend: {
                    position: 'bottom'
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%'
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function(val) {
                        return val.toFixed(0) + '%';
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return value + ' müşteri';
                        }
                    }
                }
            };
            const customerActivityChart = new ApexCharts(customerActivityChartEl, customerActivityOptions);
            customerActivityChart.render();
        }
        <?php endif; ?>
        
        // Print report
        $('#printReport').on('click', function() {
            window.print();
        });
    });
</script>

<style>
    @media print {
        /* Hide non-printable elements */
        .no-print, .no-print *,
        .d-print-none,
        .navbar,
        .sidebar,
        .footer,
        .breadcrumb,
        .btn,
        form,
        #printReport,
        #exportReport,
        header.navbar,
        aside.navbar-vertical,
        footer.d-print-none {
            display: none !important;
        }
        
        /* Show print-only elements */
        .print-only {
            display: block !important;
        }
        
        /* Reset body and page structure */
        body {
            background-color: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
            font-size: 12pt !important;
        }
        
        .page-wrapper {
            margin: 0 !important;
            width: 100% !important;
        }
        
        /* Company info print section */
        .print-only {
            margin-bottom: 20px !important;
            page-break-after: avoid !important;
        }
        
        .print-only .card {
            border: 2px solid #000 !important;
        }
        
        .print-only .table th {
            background-color: #f0f0f0 !important;
            font-weight: bold !important;
        }
        
        .print-only img {
            max-width: 150px !important;
            height: auto !important;
        }
        
        .page-header {
            margin-bottom: 15px !important;
            padding-bottom: 10px !important;
            border-bottom: 2px solid #000 !important;
        }
        
        .page-header .container-xl {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        
        .page-title {
            font-size: 18pt !important;
            margin-bottom: 5px !important;
        }
        
        .page-pretitle {
            font-size: 10pt !important;
            color: #666 !important;
        }
        
        /* Page body and container */
        .page-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .container-xl {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 10px !important;
        }
        
        /* Cards */
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            margin-bottom: 15px !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        
        .card-header {
            background-color: #f5f5f5 !important;
            border-bottom: 1px solid #ddd !important;
            padding: 8px 12px !important;
        }
        
        .card-title {
            font-size: 14pt !important;
            margin: 0 !important;
        }
        
        .card-body {
            padding: 12px !important;
        }
        
        /* Tables */
        .table {
            width: 100% !important;
            font-size: 10pt !important;
            border-collapse: collapse !important;
        }
        
        .table th,
        .table td {
            padding: 6px 8px !important;
            border: 1px solid #ddd !important;
        }
        
        .table thead th {
            background-color: #f5f5f5 !important;
            font-weight: bold !important;
        }
        
        .table-responsive {
            overflow: visible !important;
        }
        
        /* Charts - Hide or show placeholder */
        #dailySalesChart,
        #categorySalesChart,
        #categoryStockChart,
        #stockStatusChart,
        #customerDistributionChart {
            display: none !important;
        }
        
        /* Rows and columns */
        .row {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        
        .row > [class*="col-"] {
            padding-left: 8px !important;
            padding-right: 8px !important;
        }
        
        /* Stats cards */
        .stats-info {
            border: 1px solid #ddd !important;
            margin-bottom: 10px !important;
        }
        
        .stats-info h2 {
            font-size: 20pt !important;
        }
        
        /* Badges */
        .badge {
            padding: 4px 8px !important;
            font-size: 9pt !important;
        }
        
        /* Text sizes */
        h1, h2, h3, h4, h5, h6 {
            page-break-after: avoid !important;
        }
        
        /* Page breaks */
        .page-break-before {
            page-break-before: always !important;
        }
        
        .page-break-after {
            page-break-after: always !important;
        }
        
        /* Avoid breaking inside */
        .no-break {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        
        /* Print margins */
        @page {
            margin: 1cm;
        }
    }
</style>

    </div>
</div>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>