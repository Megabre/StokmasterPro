<?php
/**
 * Megabre StokMaster Pro
 * Orders Management Index
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

// Initialize cache
$cache = Cache::getInstance();

// Get page parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$customerFilter = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Process actions
switch ($action) {
    case 'add':
        include_once MODULES_PATH . 'orders/add.php';
        break;
        
    case 'edit':
        include_once MODULES_PATH . 'orders/edit.php';
        break;
        
    case 'delete':
        include_once MODULES_PATH . 'orders/delete.php';
        break;
        
    case 'view':
        include_once MODULES_PATH . 'orders/view.php';
        break;
        
    case 'print':
        include_once MODULES_PATH . 'orders/print.php';
        break;
        
    case 'status':
        include_once MODULES_PATH . 'orders/status.php';
        break;
        
    default:
        // Default action: list all orders
        
        // Create cache key based on filters
        $cacheKey = 'orders_list_' . md5($customerFilter . '_' . $statusFilter . '_' . $dateFrom . '_' . $dateTo);
        
        // Get orders from cache or database
        $orders = $cache->remember($cacheKey, function() use ($db, $customerFilter, $statusFilter, $dateFrom, $dateTo) {
        // Build query based on filters
        $query = "SELECT o.*, c.first_name as customer_name, c.last_name as customer_surname, c.phone as customer_phone,
                  (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
                  DATE_FORMAT(o.order_date, '%d.%m.%Y') as formatted_date
                  FROM orders o 
                  JOIN customers c ON o.customer_id = c.id";
        
        // Add WHERE conditions
        $where = [];
        $params = [];
        
        if ($customerFilter > 0) {
            $where[] = "o.customer_id = :customer_id";
            $params[':customer_id'] = $customerFilter;
        }
        
        if (!empty($statusFilter)) {
            $where[] = "o.status = :status";
            $params[':status'] = $statusFilter;
        }
        
        if (!empty($dateFrom)) {
            $where[] = "o.order_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $where[] = "o.order_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        $query .= " ORDER BY o.order_date DESC, o.id DESC";
        
        // Execute query
        $db->query($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $db->bind($key, $value);
        }
        
            return $db->resultSet();
        }, 300); // Cache for 5 minutes
        
        // Get customers for filter dropdown (cached)
        $customers = $cache->remember('customers_list_names', function() use ($db) {
        $db->query("SELECT id, first_name, last_name FROM customers ORDER BY first_name ASC, last_name ASC");
            return $db->resultSet();
        }, 600); // Cache for 10 minutes
        
        // Get order summary
        $db->query("SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(total_amount) as total_amount
                    FROM orders");
        $summary = $db->single();
        
        // Set page title based on status filter
        $pageTitle = t('orders_all_orders_title', 'Tüm Siparişler');
        if (!empty($statusFilter)) {
            switch ($statusFilter) {
                case 'pending':
                    $pageTitle = t('orders_pending_orders_title', 'Bekleyen Siparişler');
                    break;
                case 'processing':
                    $pageTitle = t('orders_processing_orders_title', 'İşlemdeki Siparişler');
                    break;
                case 'completed':
                    $pageTitle = t('orders_completed_orders_title', 'Tamamlanan Siparişler');
                    break;
                case 'cancelled':
                    $pageTitle = t('orders_cancelled_orders_title', 'İptal Edilen Siparişler');
                    break;
            }
        }
        
        // Include header
        include_once INCLUDES_PATH . 'header.php';
        
        // Show success/error messages
        if (Session::hasFlash('success')) {
            $flash = Session::getFlash('success');
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    ' . $flash['message'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        
        if (Session::hasFlash('error')) {
            $flash = Session::getFlash('error');
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ' . $flash['message'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle"><?php echo t('home', 'Ana Sayfa'); ?></div>
                <h2 class="page-title"><?php echo $pageTitle; ?></h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="<?php echo url('index.php?module=orders&action=add'); ?>" class="btn btn-primary">
                        <i class="ti ti-plus"></i> <?php echo t('orders_new_order', 'Yeni Sipariş'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="page-body">
    <div class="container-xl">
        <!-- Order Summary -->
        <div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo t('orders_total_orders', 'Toplam Sipariş'); ?></h5>
                <h2 class="mb-0"><?php echo $summary['total_orders']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo t('orders_status_pending', 'Bekleyen'); ?></h5>
                <h2 class="mb-0"><?php echo $summary['pending_orders']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo t('orders_status_processing', 'İşlemde'); ?></h5>
                <h2 class="mb-0"><?php echo $summary['processing_orders']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo t('orders_status_completed', 'Tamamlanan'); ?></h5>
                <h2 class="mb-0"><?php echo $summary['completed_orders']; ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mt-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <input type="hidden" name="module" value="orders">
            <?php if (!empty($statusFilter)): ?>
            <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
            <?php endif; ?>
            
            <div class="col-md-3">
                <label for="customer_id" class="form-label"><?php echo t('orders_filter_customer', 'Müşteri'); ?></label>
                <select class="form-select" id="customer_id" name="customer_id">
                    <option value=""><?php echo t('orders_filter_all', 'Tümü'); ?></option>
                    <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['id']; ?>" <?php echo $customerFilter == $customer['id'] ? 'selected' : ''; ?>>
                        <?php echo e($customer['first_name'] . ' ' . $customer['last_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="date_from" class="form-label"><?php echo t('orders_filter_start_date', 'Başlangıç Tarihi'); ?></label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
            </div>
            
            <div class="col-md-3">
                <label for="date_to" class="form-label"><?php echo t('orders_filter_end_date', 'Bitiş Tarihi'); ?></label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-search"></i> <?php echo t('orders_filter_button', 'Filtrele'); ?>
                    </button>
                    <a href="<?php echo url('index.php?module=orders' . (!empty($statusFilter) ? '&status=' . $statusFilter : '')); ?>" class="btn btn-secondary">
                        <i class="ti ti-x"></i> <?php echo t('orders_filter_clear', 'Temizle'); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card mt-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable" id="orders-table" data-page-length="50">
                <thead>
                    <tr>
                        <th width="100"><?php echo t('orders_order_no', 'Sipariş No'); ?></th>
                        <th><?php echo t('orders_customer', 'Müşteri'); ?></th>
                        <th width="100"><?php echo t('orders_date', 'Tarih'); ?></th>
                        <th width="60"><?php echo t('orders_items', 'Kalem'); ?></th>
                        <th width="100" class="text-end"><?php echo t('orders_amount', 'Tutar'); ?></th>
                        <th width="120"><?php echo t('orders_status', 'Durum'); ?></th>
                        <th width="150"><?php echo t('categories_actions', 'İşlemler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <a href="<?php echo url('index.php?module=orders&action=view&id=' . $order['id']); ?>">
                                #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?php echo url('index.php?module=customers&action=edit&id=' . $order['customer_id']); ?>">
                                <?php echo e($order['customer_name'] . ' ' . $order['customer_surname']); ?>
                            </a>
                            <br><small class="text-muted"><?php echo formatPhone($order['customer_phone']); ?></small>
                        </td>
                        <td><?php echo $order['formatted_date']; ?></td>
                        <td class="text-center"><?php echo $order['item_count']; ?></td>
                        <td class="text-end"><?php echo formatPrice($order['total_amount']); ?> ₺</td>
                        <td>
                            <?php
                            $statusLabels = [
                                'pending' => ['class' => 'warning', 'text' => t('orders_status_pending', 'Bekleyen')],
                                'processing' => ['class' => 'info', 'text' => t('orders_status_processing', 'İşlemde')],
                                'completed' => ['class' => 'success', 'text' => t('orders_status_completed', 'Tamamlandı')],
                                'cancelled' => ['class' => 'danger', 'text' => t('orders_status_cancelled', 'İptal')]
                            ];
                            $status = $statusLabels[$order['status']] ?? ['class' => 'secondary', 'text' => t('common_unknown', 'Bilinmeyen')];
                            ?>
                            <span class="badge bg-<?php echo $status['class']; ?>">
                                <?php echo $status['text']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-list">
                                <a href="<?php echo url('index.php?module=orders&action=view&id=' . $order['id']); ?>" class="btn btn-sm btn-info" title="<?php echo t('orders_view', 'Görüntüle'); ?>">
                                    <i class="ti ti-eye"></i>
                                </a>
                                <?php if ($order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                                <a href="<?php echo url('index.php?module=orders&action=edit&id=' . $order['id']); ?>" class="btn btn-sm btn-primary" title="<?php echo t('orders_edit', 'Düzenle'); ?>">
                                    <i class="ti ti-edit"></i>
                                </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-danger delete-order" data-id="<?php echo $order['id']; ?>" title="<?php echo t('orders_delete', 'Sil'); ?>">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#orders-table').DataTable({
        language: {
            url: '<?php echo asset('js/datatables-tr.json'); ?>'
        },
        order: [[2, 'desc'], [0, 'desc']],
        pageLength: 50
    });
    
    // Delete order
    $('.delete-order').on('click', function() {
        const orderId = $(this).data('id');
        
        if (confirm('<?php echo t('orders_delete_confirm', 'Bu siparişi silmek istediğinizden emin misiniz?'); ?>')) {
            window.location.href = '<?php echo url('index.php?module=orders&action=delete&id='); ?>' + orderId;
        }
    });
});
</script>
    </div>
</div>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
}

// Helper functions for status display
function getStatusClass($status) {
    $classes = [
        'pending' => 'warning',
        'processing' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusText($status) {
    $texts = [
        'pending' => t('orders_status_pending', 'Bekleyen'),
        'processing' => t('orders_status_processing', 'İşlemde'),
        'completed' => t('orders_status_completed', 'Tamamlandı'),
        'cancelled' => t('orders_status_cancelled', 'İptal')
    ];
    return $texts[$status] ?? t('common_unknown', 'Bilinmeyen');
}
?>