<?php
/**
 * Megabre StokMaster Pro
 * Dashboard
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Initialize Cache and Database
$cache = Cache::getInstance();
$db = Database::getInstance();

// Get today's date
$today = date('Y-m-d');

// Get today's payments and debts (with cache)
$todayStats = $cache->remember('dashboard_today_stats_' . $today, function() use ($db, $today) {
    $db->query("SELECT 
        SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as total_payments,
        SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END) as total_debts
        FROM transactions 
        WHERE DATE(date) = :today");
    $db->bind(':today', $today);
    return $db->single();
}, 3600);

// Get low stock products (less than 10 units) - with cache
$lowStockCount = $cache->remember('dashboard_low_stock_count', function() use ($db) {
    $db->query("SELECT COUNT(*) as count FROM (
        SELECT p.id, COALESCE(SUM(CASE 
            WHEN sm.type = 'in' THEN sm.quantity 
            WHEN sm.type = 'out' THEN -sm.quantity 
            ELSE 0 
        END), 0) as current_stock
        FROM products p
        LEFT JOIN stock_movements sm ON p.id = sm.product_id
        GROUP BY p.id
        HAVING current_stock < 10
    ) as low_stock_products");
    $result = $db->single();
    return $result ? $result['count'] : 0;
}, 3600);

// Get pending orders - with cache
$pendingOrdersCount = $cache->remember('dashboard_pending_orders_count', function() use ($db) {
    $db->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    $result = $db->single();
    return $result ? $result['count'] : 0;
}, 3600);

// Get today's installment payments due (bugün ödemesi gereken taksitler)
$db->query("SELECT 
    t.*, 
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    DATE_FORMAT(t.date, '%d.%m.%Y') as formatted_date
    FROM transactions t
    LEFT JOIN customers c ON t.customer_id = c.id
    WHERE t.is_installment = 1 
    AND t.type = 'debt'
    AND DATE(t.date) = :today
    ORDER BY t.date ASC");
$db->bind(':today', $today);
$todayInstallments = $db->resultSet();

// Get upcoming installment payments (yaklaşan taksitler - 7 gün içinde)
$db->query("SELECT 
    t.*, 
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    DATE_FORMAT(t.date, '%d.%m.%Y') as formatted_date,
    DATEDIFF(t.date, CURDATE()) as days_until_due
    FROM transactions t
    LEFT JOIN customers c ON t.customer_id = c.id
    WHERE t.is_installment = 1 
    AND t.type = 'debt'
    AND DATE(t.date) > :today_start
    AND DATE(t.date) <= DATE_ADD(:today_end, INTERVAL 7 DAY)
    ORDER BY t.date ASC
    LIMIT 10");
$db->bind(':today_start', $today);
$db->bind(':today_end', $today);
$upcomingInstallments = $db->resultSet();

// Calculate total installment amount due today
$totalTodayInstallments = 0;
foreach ($todayInstallments as $installment) {
    $totalTodayInstallments += $installment['amount'];
}

// Get monthly transactions for chart - with cache
$monthlyTransactions = $cache->remember('dashboard_monthly_transactions', function() use ($db) {
    $db->query("SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as payments,
        SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END) as debts
        FROM transactions 
        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month ASC");
    return $db->resultSet();
}, 3600);

// Get product categories for chart - with cache
$productCategories = $cache->remember('dashboard_product_categories', function() use ($db) {
    $db->query("SELECT 
        c.name as category_name,
        COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        GROUP BY c.id
        ORDER BY product_count DESC
        LIMIT 5");
    return $db->resultSet();
}, 3600);

// Get order status counts for chart - with cache
$orderStatuses = $cache->remember('dashboard_order_statuses', function() use ($db) {
    $db->query("SELECT 
        status,
        COUNT(*) as count
        FROM orders
        GROUP BY status");
    return $db->resultSet();
}, 3600);

// Get dashboard settings
$settingsFile = ROOT_PATH . '/data/dashboard_settings.json';
$layout = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : null;

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle"><?php echo t('home', 'Ana Sayfa'); ?></div>
                <h2 class="page-title"><?php echo t('dashboard_title', 'Dashboard'); ?></h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <button class="btn" type="button" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#filterModal">
                            <i class="ti ti-filter me-2"></i><?php echo t('dashboard_view_settings', 'Görünüm Ayarları'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo t('dashboard_view_settings', 'Görünüm Ayarları'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input widget-visibility" type="checkbox" id="showStats" checked>
                        <label class="form-check-label" for="showStats"><?php echo t('dashboard_statistics_cards', 'İstatistik Kartları'); ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input widget-visibility" type="checkbox" id="showCharts" checked>
                        <label class="form-check-label" for="showCharts"><?php echo t('dashboard_charts', 'Grafikler'); ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input widget-visibility" type="checkbox" id="showQuickActions" checked>
                        <label class="form-check-label" for="showQuickActions"><?php echo t('dashboard_quick_actions_widget', 'Hızlı İşlemler'); ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input widget-visibility" type="checkbox" id="showCategories" checked>
                        <label class="form-check-label" for="showCategories"><?php echo t('dashboard_categories_widget', 'Ürün Kategorileri'); ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input widget-visibility" type="checkbox" id="showRecentTransactions" checked>
                        <label class="form-check-label" for="showRecentTransactions"><?php echo t('dashboard_recent_transactions_widget', 'Son İşlemler'); ?></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal"><?php echo t('close', 'Kapat'); ?></button>
                <button type="button" class="btn btn-primary" id="saveVisibility"><?php echo t('save', 'Kaydet'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Content -->
<div class="page-body">
    <div class="container-xl">
    <!-- Quick Actions -->
    <div class="row widget-container" id="quickActionsContainer">
        <div class="col-12">
            <div class="card draggable-card" data-card-id="quickActions">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('dashboard_quick_actions', 'Hızlı İşlemler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-lg-3 mb-4">
                            <a href="<?php echo url('index.php?module=products&action=add'); ?>" class="text-decoration-none">
                                <div class="quick-action-card">
                                    <div class="icon text-primary">
                                        <i class="ti ti-package-export"></i>
                                    </div>
                                    <h5><?php echo t('products_add', 'Ürün Ekle'); ?></h5>
                                    <p><?php echo t('dashboard_add_product_desc', 'Yeni ürün kaydı oluştur'); ?></p>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-4">
                            <a href="<?php echo url('index.php?module=orders&action=add'); ?>" class="text-decoration-none">
                                <div class="quick-action-card">
                                    <div class="icon text-success">
                                        <i class="ti ti-shopping-cart-plus"></i>
                                    </div>
                                    <h5><?php echo t('orders_add', 'Sipariş Ekle'); ?></h5>
                                    <p><?php echo t('dashboard_add_order_desc', 'Yeni sipariş oluştur'); ?></p>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-4">
                            <a href="<?php echo url('index.php?module=stock&action=add'); ?>" class="text-decoration-none">
                                <div class="quick-action-card">
                                    <div class="icon text-warning">
                                        <i class="ti ti-truck"></i>
                                    </div>
                                    <h5><?php echo t('stock_add', 'Stok Ekle'); ?></h5>
                                    <p><?php echo t('dashboard_add_stock_desc', 'Stoğa ürün giriş/çıkışı'); ?></p>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-4">
                            <a href="<?php echo url('index.php?module=customers&action=add'); ?>" class="text-decoration-none">
                                <div class="quick-action-card">
                                    <div class="icon text-info">
                                        <i class="ti ti-user-plus"></i>
                                    </div>
                                    <h5><?php echo t('customers_add', 'Müşteri Ekle'); ?></h5>
                                    <p><?php echo t('dashboard_add_customer_desc', 'Yeni müşteri kaydı oluştur'); ?></p>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 col-lg-3 mb-4">
                            <a href="<?php echo url('index.php?module=tools&action=calculators'); ?>" class="text-decoration-none">
                                <div class="quick-action-card">
                                    <div class="icon text-danger">
                                        <i class="ti ti-calculator"></i>
                                    </div>
                                    <h5><?php echo t('tools_calculators', 'Hesaplama Araçları'); ?></h5>
                                    <p><?php echo t('dashboard_calculators_desc', 'Ölçü ve hesap araçları'); ?></p>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-4">
                            <a href="<?php echo url('index.php?module=transactions&action=add-payment'); ?>" class="text-decoration-none">
                                <div class="quick-action-card">
                                    <div class="icon text-secondary">
                                        <i class="ti ti-credit-card"></i>
                                    </div>
                                    <h5><?php echo t('transactions_add_payment', 'Ödeme/Borç Ekle'); ?></h5>
                                    <p><?php echo t('dashboard_add_transaction_desc', 'Mali işlem kaydet'); ?></p>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-4">
                            <a href="<?php echo url('index.php?module=tools&action=reports'); ?>" class="text-decoration-none">
                                <div class="quick-action-card">
                                    <div class="icon" style="color: #9b59b6;">
                                        <i class="ti ti-chart-bar"></i>
                                    </div>
                                    <h5><?php echo t('tools_reports', 'Raporlar'); ?></h5>
                                    <p><?php echo t('dashboard_reports_desc', 'Detaylı istatistikler'); ?></p>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6 col-lg-3 mb-4">
                            <a href="<?php echo url('index.php?module=tools&action=backup'); ?>" class="text-decoration-none">
                                <div class="quick-action-card">
                                    <div class="icon" style="color: #e67e22;">
                                        <i class="ti ti-database"></i>
                                    </div>
                                    <h5><?php echo t('tools_backup', 'Yedekleme'); ?></h5>
                                    <p><?php echo t('dashboard_backup_desc', 'Sistem verilerini yedekle'); ?></p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mt-4 widget-container" id="statsContainer">
        <div class="col-md-6 col-xl-3">
            <div class="card draggable-card" data-card-id="todayPayments">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-2"><?php echo t('dashboard_today_receivables', 'Bugünkü Alacaklar'); ?></h6>
                            <h3 class="mb-0 text-success"><?php echo formatPrice($todayStats['total_payments'] ?? 0); ?> ₺</h3>
                            <small class="text-muted"><?php echo t('dashboard_today_receivables_desc', 'Bugün tahsil edilecek toplam alacak'); ?></small>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-success-subtle">
                                <i class="ti ti-currency-dollar fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <div class="card draggable-card" data-card-id="todayDebts">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-2"><?php echo t('dashboard_today_debts', 'Bugünkü Borçlar'); ?></h6>
                            <h3 class="mb-0 text-danger"><?php echo formatPrice($todayStats['total_debts'] ?? 0); ?> ₺</h3>
                            <small class="text-muted"><?php echo t('dashboard_today_debts_desc', 'Bugün ödenecek toplam borç'); ?></small>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-danger-subtle">
                                <i class="ti ti-credit-card fa-2x text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <div class="card draggable-card" data-card-id="lowStock">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-2"><?php echo t('dashboard_low_stocks', 'Azalan Stoklar'); ?></h6>
                            <h3 class="mb-0 text-warning"><?php echo $lowStockCount; ?></h3>
                            <small class="text-muted"><?php echo t('dashboard_low_stocks_desc', 'Stok miktarı 10\'dan az olan ürünler'); ?></small>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-warning-subtle">
                                <i class="ti ti-alert-triangle fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <div class="card draggable-card" data-card-id="pendingOrders">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-2"><?php echo t('dashboard_pending_orders', 'Bekleyen Siparişler'); ?></h6>
                            <h3 class="mb-0 text-info"><?php echo $pendingOrdersCount; ?></h3>
                            <small class="text-muted"><?php echo t('dashboard_pending_orders_desc', 'Onay bekleyen siparişler'); ?></small>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-info-subtle">
                                <i class="ti ti-shopping-cart fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Installment Notifications -->
    <?php if (!empty($todayInstallments) || !empty($upcomingInstallments)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-bell"></i> 
                        <?php echo isset($GLOBALS['L']['dashboard_installment_notifications']) ? $GLOBALS['L']['dashboard_installment_notifications'] : 'Taksit Bildirimleri'; ?>
                        <?php if (!empty($todayInstallments)): ?>
                        <span class="badge bg-danger ms-2"><?php echo count($todayInstallments); ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($todayInstallments)): ?>
                    <div class="alert alert-danger mb-3">
                        <h6 class="mb-3">
                            <i class="ti ti-alert-circle"></i> 
                            <?php echo isset($GLOBALS['L']['dashboard_today_installments_due']) ? $GLOBALS['L']['dashboard_today_installments_due'] : 'Bugün Ödemesi Gereken Taksitler'; ?>
                            <strong class="ms-2"><?php echo formatPrice($totalTodayInstallments); ?> ₺</strong>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo isset($GLOBALS['L']['customers_customer_name']) ? $GLOBALS['L']['customers_customer_name'] : 'Müşteri'; ?></th>
                                        <th><?php echo isset($GLOBALS['L']['transactions_amount']) ? $GLOBALS['L']['transactions_amount'] : 'Tutar'; ?></th>
                                        <th><?php echo isset($GLOBALS['L']['transactions_date']) ? $GLOBALS['L']['transactions_date'] : 'Tarih'; ?></th>
                                        <th><?php echo isset($GLOBALS['L']['transactions_notes']) ? $GLOBALS['L']['transactions_notes'] : 'Not'; ?></th>
                                        <th><?php echo isset($GLOBALS['L']['actions']) ? $GLOBALS['L']['actions'] : 'İşlemler'; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todayInstallments as $installment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($installment['customer_name']); ?></strong>
                                        </td>
                                        <td class="text-danger fw-bold">
                                            <?php echo formatPrice($installment['amount']); ?> ₺
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $installment['formatted_date']; ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($installment['notes'])) {
                                                $note = e($installment['notes']);
                                                echo strlen($note) > 40 ? substr($note, 0, 40) . '...' : $note;
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo url('index.php?module=transactions&action=add-payment&customer_id=' . $installment['customer_id']); ?>" class="btn btn-sm btn-success" title="<?php echo isset($GLOBALS['L']['menu_add_payment']) ? $GLOBALS['L']['menu_add_payment'] : 'Ödeme Ekle'; ?>">
                                                <i class="ti ti-currency-dollar"></i>
                                            </a>
                                            <a href="<?php echo url('index.php?module=transactions'); ?>" class="btn btn-sm btn-info" title="<?php echo isset($GLOBALS['L']['view']) ? $GLOBALS['L']['view'] : 'Görüntüle'; ?>">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($upcomingInstallments)): ?>
                    <div class="alert alert-warning mb-0">
                        <h6 class="mb-3">
                            <i class="ti ti-clock"></i> 
                            <?php echo isset($GLOBALS['L']['dashboard_upcoming_installments']) ? $GLOBALS['L']['dashboard_upcoming_installments'] : 'Yaklaşan Taksitler (7 Gün İçinde)'; ?>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo isset($GLOBALS['L']['customers_customer_name']) ? $GLOBALS['L']['customers_customer_name'] : 'Müşteri'; ?></th>
                                        <th><?php echo isset($GLOBALS['L']['transactions_amount']) ? $GLOBALS['L']['transactions_amount'] : 'Tutar'; ?></th>
                                        <th><?php echo isset($GLOBALS['L']['transactions_date']) ? $GLOBALS['L']['transactions_date'] : 'Tarih'; ?></th>
                                        <th><?php echo isset($GLOBALS['L']['dashboard_days_until_due']) ? $GLOBALS['L']['dashboard_days_until_due'] : 'Kalan Gün'; ?></th>
                                        <th><?php echo isset($GLOBALS['L']['transactions_notes']) ? $GLOBALS['L']['transactions_notes'] : 'Not'; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingInstallments as $installment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($installment['customer_name']); ?></strong>
                                        </td>
                                        <td class="fw-bold">
                                            <?php echo formatPrice($installment['amount']); ?> ₺
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?php echo $installment['formatted_date']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $installment['days_until_due']; ?> 
                                                <?php echo isset($GLOBALS['L']['dashboard_days']) ? $GLOBALS['L']['dashboard_days'] : 'gün'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($installment['notes'])) {
                                                $note = e($installment['notes']);
                                                echo strlen($note) > 40 ? substr($note, 0, 40) . '...' : $note;
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts -->
    <div class="row mt-4 widget-container" id="chartsContainer">
        <div class="col-lg-8">
            <div class="card draggable-card" data-card-id="monthlyChart">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('dashboard_monthly_payments_debts', 'Aylık Ödemeler ve Borçlar'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="transactionsChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card draggable-card" data-card-id="orderStatusChart">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('dashboard_order_statuses', 'Sipariş Durumları'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="orderStatusChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>


    <!-- Categories and Recent Transactions -->
    <div class="row mt-4">
        <!-- Categories -->
        <div class="col-lg-6 widget-container" id="categoriesContainer">
            <div class="card draggable-card" data-card-id="categoriesChart">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('dashboard_product_categories', 'Ürün Kategorileri'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="productCategoriesChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="col-lg-6 widget-container" id="recentTransactionsContainer">
            <div class="card draggable-card" data-card-id="recentTransactions">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('dashboard_recent_transactions', 'Son İşlemler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?php echo t('categories_id', 'ID'); ?></th>
                                    <th><?php echo t('customers_title', 'Müşteri'); ?></th>
                                    <th><?php echo t('dashboard_transaction_type', 'Tür'); ?></th>
                                    <th><?php echo t('transactions_amount', 'Tutar'); ?></th>
                                    <th><?php echo t('stock_date', 'Tarih'); ?></th>
                                    <th><?php echo t('dashboard_payment_method', 'Ödeme Yöntemi'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get recent transactions
                                $db->query("SELECT t.*, 
                                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                                    DATE_FORMAT(t.date, '%d.%m.%Y') as formatted_date
                                    FROM transactions t
                                    LEFT JOIN customers c ON t.customer_id = c.id
                                    ORDER BY t.date DESC, t.id DESC
                                    LIMIT 5");
                                $recentTransactions = $db->resultSet();
                                
                                foreach ($recentTransactions as $transaction):
                                ?>
                                <tr>
                                    <td><?php echo $transaction['id']; ?></td>
                                    <td>
                                        <a href="<?php echo url('index.php?module=customers&action=edit&id=' . $transaction['customer_id']); ?>">
                                            <?php echo e($transaction['customer_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($transaction['type'] == 'payment'): ?>
                                        <span class="badge bg-success"><?php echo t('dashboard_payment_type', 'ÖDEME'); ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-danger"><?php echo t('dashboard_debt_type', 'BORÇ'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?php echo formatPrice($transaction['amount']); ?> ₺
                                    </td>
                                    <td><?php echo $transaction['formatted_date']; ?></td>
                                    <td>
                                        <?php
                                        $paymentMethods = [
                                            'cash' => 'Nakit',
                                            'check' => 'Çek',
                                            'promissory' => 'Senet',
                                            'credit_card' => 'Kredi Kartı',
                                            'bank_transfer' => 'Havale / EFT'
                                        ];
                                        echo isset($paymentMethods[$transaction['payment_method']]) ? $paymentMethods[$transaction['payment_method']] : $transaction['payment_method'];
                                        ?>
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
</div>

<style>
.widget-container {
    min-height: 50px;
}

.widget-hidden {
    display: none !important;
}

.toast-container {
    z-index: 9999;
}

.quick-action-card {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid #eee;
    height: 100%;
}

.quick-action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #ddd;
}

.quick-action-card .icon {
    font-size: 2rem;
    margin-bottom: 15px;
}

.quick-action-card h5 {
    color: #333;
    margin-bottom: 10px;
    font-size: 1.1rem;
}

.quick-action-card p {
    color: #666;
    margin-bottom: 0;
    font-size: 0.9rem;
}
</style>

<script>
$(document).ready(function() {
    // Widget ID to Container ID mapping
    const widgetMapping = {
        'showStats': 'statsContainer',
        'showCharts': 'chartsContainer',
        'showQuickActions': 'quickActionsContainer',
        'showCategories': 'categoriesContainer',
        'showRecentTransactions': 'recentTransactionsContainer'
    };
    
    // Widget visibility change
    $('.widget-visibility').change(function() {
        const widgetId = $(this).attr('id');
        const containerId = widgetMapping[widgetId];
        
        if (containerId) {
            const container = $('#' + containerId);
            if ($(this).is(':checked')) {
                container.removeClass('widget-hidden');
            } else {
                container.addClass('widget-hidden');
            }
        }
    });

    // Save visibility
    function saveVisibility() {
        const visibility = {};
        $('.widget-visibility').each(function() {
            const widgetId = $(this).attr('id');
            visibility[widgetId] = $(this).is(':checked');
        });
        localStorage.setItem('dashboardVisibility', JSON.stringify(visibility));
    }

    // Load settings
    function loadSettings() {
        // Load visibility
        const savedVisibility = localStorage.getItem('dashboardVisibility');
        if (savedVisibility) {
            const visibility = JSON.parse(savedVisibility);
            Object.keys(visibility).forEach(function(widgetId) {
                const isVisible = visibility[widgetId];
                $('#' + widgetId).prop('checked', isVisible);
                
                const containerId = widgetMapping[widgetId];
                if (containerId) {
                    if (!isVisible) {
                        $('#' + containerId).addClass('widget-hidden');
                    } else {
                        $('#' + containerId).removeClass('widget-hidden');
                    }
                }
            });
        }
    }

    // Load settings on page load
    loadSettings();

    // Save visibility button click
    $('#saveVisibility').click(function() {
        saveVisibility();
        $('#filterModal').modal('hide');
        showToast('success', '<?php echo t('dashboard_view_settings_saved', 'Görünüm ayarları kaydedildi'); ?>');
    });

    // Toast notification
    function showToast(type, message) {
        const toast = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        const toastContainer = $('<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>');
        toastContainer.append(toast);
        $('body').append(toastContainer);
        
        const toastElement = toastContainer.find('.toast');
        const bsToast = new bootstrap.Toast(toastElement);
        bsToast.show();
        
        toastElement.on('hidden.bs.toast', function() {
            toastContainer.remove();
        });
    }

    // Monthly Transactions Chart - ApexCharts (Stacked Bar Chart - Tabler.io Style)
    const transactionsChartEl = document.getElementById('transactionsChart');
    if (transactionsChartEl) {
        const transactionsOptions = {
            series: [{
                name: '<?php echo t('dashboard_payments', 'Ödemeler'); ?>',
                data: <?php echo json_encode(array_map(function($item) { 
                    return floatval($item['payments']); 
                }, $monthlyTransactions)); ?>
            }, {
                name: '<?php echo t('dashboard_debts', 'Borçlar'); ?>',
                data: <?php echo json_encode(array_map(function($item) { 
                    return floatval($item['debts']); 
                }, $monthlyTransactions)); ?>
            }],
            chart: {
                type: 'bar',
                height: 300,
                stacked: true,
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false
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
            colors: ['color-mix(in srgb, transparent, var(--tblr-primary) 100%)', 'color-mix(in srgb, transparent, var(--tblr-primary) 80%)'],
            xaxis: {
                categories: <?php echo json_encode(array_map(function($item) { 
                    return date('M Y', strtotime($item['month'] . '-01')); 
                }, $monthlyTransactions)); ?>,
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
                        if (value >= 1000) {
                            return (value / 1000).toFixed(1) + 'K';
                        }
                        return Math.floor(value);
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
                },
                padding: {
                    top: 0,
                    right: 0,
                    bottom: 0,
                    left: 0
                }
            },
            legend: {
                show: false
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function(value) {
                        return value.toLocaleString('tr-TR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' ₺';
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
        const transactionsChart = new ApexCharts(transactionsChartEl, transactionsOptions);
        transactionsChart.render();
    }

    // Order Status Chart - ApexCharts
    const orderStatusChartEl = document.getElementById('orderStatusChart');
    if (orderStatusChartEl) {
        <?php
        // Prepare status translations
        $statusTranslations = [
            'pending' => t('orders_status_pending', 'Bekleyen'),
            'processing' => t('orders_status_processing', 'İşlemde'),
            'completed' => t('orders_status_completed', 'Tamamlandı'),
            'cancelled' => t('orders_status_cancelled', 'İptal')
        ];
        ?>
        const orderStatusOptions = {
            series: <?php echo json_encode(array_map(function($item) { 
                return intval($item['count']); 
            }, $orderStatuses)); ?>,
            chart: {
                type: 'donut',
                height: 300
            },
            labels: <?php echo json_encode(array_map(function($item) use ($statusTranslations) {
                return $statusTranslations[$item['status']] ?? $item['status'];
            }, $orderStatuses)); ?>,
            colors: ['#f39c12', '#3498db', '#2ecc71', '#e74c3c'],
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
                        return value + ' sipariş';
                    }
                }
            }
        };
        const orderStatusChart = new ApexCharts(orderStatusChartEl, orderStatusOptions);
        orderStatusChart.render();
    }

    // Product Categories Chart - ApexCharts
    const productCategoriesChartEl = document.getElementById('productCategoriesChart');
    if (productCategoriesChartEl) {
        const productCategoriesOptions = {
            series: [{
                name: '<?php echo t('dashboard_product_count', 'Ürün Sayısı'); ?>',
                data: <?php echo json_encode(array_map(function($item) { 
                    return intval($item['product_count']); 
                }, $productCategories)); ?>
            }],
            chart: {
                type: 'bar',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            colors: ['#3498db'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: false,
                    columnWidth: '55%'
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: <?php echo json_encode(array_map(function($item) { 
                    return $item['category_name']; 
                }, $productCategories)); ?>
            },
            yaxis: {
                tickAmount: 5,
                labels: {
                    formatter: function(value) {
                        return Math.floor(value);
                    }
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
        const productCategoriesChart = new ApexCharts(productCategoriesChartEl, productCategoriesOptions);
        productCategoriesChart.render();
    }
});
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>