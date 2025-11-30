<?php
/**
 * Megabre StokMaster Pro
 * Customers Index
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

// Initialize dynamic fields class
$dynamicFields = new DynamicFields();

// Get page parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'index';

// Process actions
switch ($action) {
    case 'add':
        include_once MODULES_PATH . 'customers/add.php';
        break;
        
    case 'view':
        include_once MODULES_PATH . 'customers/view.php';
        break;
        
    case 'edit':
        include_once MODULES_PATH . 'customers/edit.php';
        break;
        
    case 'delete':
        include_once MODULES_PATH . 'customers/delete.php';
        break;
        
    case 'fields':
        include_once MODULES_PATH . 'customers/fields.php';
        break;
        
    default:
        // Default action: list all customers
        
        // Get customers with their balances (cached)
        $customers = $cache->remember('customers_list_with_stats', function() use ($db) {
            $db->query("SELECT 
                c.*,
                CONCAT(c.first_name, ' ', c.last_name) AS full_name,
                (SELECT COUNT(DISTINCT id) FROM orders WHERE customer_id = c.id) AS order_count,
                (SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE customer_id = c.id) AS total_order_amount
            FROM customers c
            ORDER BY c.id DESC");
            return $db->resultSet();
        }, 300); // Cache for 5 minutes
        
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
                <h2 class="page-title"><?php echo t('customers_title', 'Müşteriler'); ?></h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <button type="button" class="btn btn-primary" id="columnFilterBtn" data-bs-toggle="modal" data-bs-target="#columnFilterModal">
                        <i class="ti ti-filter"></i> <?php echo t('products_column_filter', 'Sütun Filtresi'); ?>
                    </button>
                    <a href="<?php echo url('index.php?module=customers&action=add'); ?>" class="btn btn-primary">
                        <i class="ti ti-plus"></i> <?php echo t('customers_new_customer', 'Yeni Müşteri'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="page-body">
    <div class="container-xl">
        <!-- Filter Form -->
        <div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end" onsubmit="return false;">
            <div class="col-md-3">
                <label for="search" class="form-label"><?php echo t('search', 'Ara'); ?></label>
                <input type="text" class="form-control" id="search" placeholder="<?php echo t('customers_search_placeholder', 'Ad, soyad, telefon veya e-posta'); ?>">
            </div>
            <div class="col-md-3">
                <label for="order_status" class="form-label"><?php echo t('customers_order_status', 'Sipariş Durumu'); ?></label>
                <select id="order_status" class="form-select">
                    <option value="all"><?php echo t('customers_all', 'Tümü'); ?></option>
                    <option value="has_orders"><?php echo t('customers_has_orders', 'Siparişi Olanlar'); ?></option>
                    <option value="no_orders"><?php echo t('customers_no_orders', 'Siparişi Olmayanlar'); ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="balance_status" class="form-label"><?php echo t('customers_balance_status', 'Borç/Alacak Durumu'); ?></label>
                <select id="balance_status" class="form-select">
                    <option value="all"><?php echo t('customers_all', 'Tümü'); ?></option>
                    <option value="debt"><?php echo t('customers_debt', 'Borçlu Olanlar'); ?></option>
                    <option value="credit"><?php echo t('customers_credit', 'Alacaklı Olanlar'); ?></option>
                    <option value="zero"><?php echo t('customers_zero_balance', 'Bakiye 0'); ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" onclick="filterCustomers()">
                    <i class="ti ti-search"></i> <?php echo t('customers_filter', 'Filtrele'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th width="60"><?php echo t('categories_id', 'ID'); ?></th>
                        <th><?php echo t('customers_customer_name', 'Müşteri Adı'); ?></th>
                        <th><?php echo t('customers_contact_info', 'İletişim Bilgileri'); ?></th>
                        <th><?php echo t('customers_company_firm', 'Şirket/Firma'); ?></th>
                        <th><?php echo t('customers_order_info', 'Sipariş Bilgileri'); ?></th>
                        <th width="150"><?php echo t('categories_actions', 'İşlemler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle"></i> <?php echo t('customers_no_customers', 'Henüz müşteri bulunmuyor.'); ?>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td class="align-middle"><?php echo $customer['id']; ?></td>
                            <td class="align-middle">
                                <a href="<?php echo url('index.php?module=customers&action=view&id=' . $customer['id']); ?>" class="text-decoration-none">
                                    <?php echo e($customer['full_name']); ?>
                                </a>
                            </td>
                            <td class="align-middle">
                                <div class="mb-1">
                                    <i class="fas fa-phone text-muted me-2"></i> <?php echo e($customer['phone']); ?>
                                </div>
                                <?php if (!empty($customer['email'])): ?>
                                <div>
                                    <i class="fas fa-envelope text-muted me-2"></i> <?php echo e($customer['email']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle"><?php echo e($customer['company']); ?></td>
                            <td class="align-middle">
                                <div class="mb-1">
                                    <strong><?php echo t('customers_total_order', 'Toplam Sipariş'); ?>:</strong> <?php echo $customer['order_count']; ?>
                                </div>
                                <?php if ($customer['order_count'] > 0): ?>
                                <div>
                                    <strong><?php echo t('customers_total_amount', 'Toplam Tutar'); ?>:</strong> <?php echo formatPrice($customer['total_order_amount']); ?> ₺
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle">
                                <div class="btn-group">
                                    <a href="<?php echo url('index.php?module=customers&action=edit&id=' . $customer['id']); ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="<?php echo t('customers_show_edit', 'Göster/Düzenle'); ?>">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a href="<?php echo url('index.php?module=transactions&action=add-payment&customer_id=' . $customer['id']); ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="<?php echo t('customers_add_payment', 'Ödeme Ekle'); ?>">
                                        <i class="ti ti-circle-plus"></i>
                                    </a>
                                    <a href="<?php echo url('index.php?module=customers&action=delete&id=' . $customer['id']); ?>" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="<?php echo t('customers_delete', 'Sil'); ?>">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Customer Statistics -->
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo t('customers_total_customers', 'Toplam Müşteri'); ?></h5>
                <h2 class="mb-0"><?php echo count($customers); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo t('customers_with_orders', 'Siparişi Olan Müşteri'); ?></h5>
                <h2 class="mb-0"><?php echo count(array_filter($customers, function($c) { return $c['order_count'] > 0; })); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo t('customers_no_orders', 'Siparişi Olmayan Müşteri'); ?></h5>
                <h2 class="mb-0"><?php echo count(array_filter($customers, function($c) { return $c['order_count'] == 0; })); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo t('customers_total_order_amount', 'Toplam Sipariş Tutarı'); ?></h5>
                <h2 class="mb-0 text-primary">
                    <?php 
                    $totalOrders = array_sum(array_map(function($c) { 
                        return $c['total_order_amount']; 
                    }, $customers));
                    echo formatPrice($totalOrders); 
                    ?> ₺
                </h2>
            </div>
        </div>
    </div>
</div>

<!-- Column Filter Modal -->
<div class="modal fade" id="columnFilterModal" tabindex="-1" aria-labelledby="columnFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="columnFilterModalLabel"><?php echo t('customers_column_filter_title', 'Sütun Filtresi'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo t('ui_aria_close', 'Kapat'); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo t('customers_column_filter_desc', 'Tabloda görmek istediğiniz sütunları seçin:'); ?></p>
                <form id="columnFilterForm">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_id" checked disabled>
                        <label class="form-check-label" for="column_id"><?php echo t('categories_id', 'ID'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_name" checked>
                        <label class="form-check-label" for="column_name"><?php echo t('customers_column_name', 'Müşteri Adı'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_contact" checked>
                        <label class="form-check-label" for="column_contact"><?php echo t('customers_column_contact', 'İletişim Bilgileri'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_company" checked>
                        <label class="form-check-label" for="column_company"><?php echo t('customers_column_company', 'Şirket/Firma'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_orders" checked>
                        <label class="form-check-label" for="column_orders"><?php echo t('customers_column_orders', 'Sipariş Bilgisi'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_actions" checked disabled>
                        <label class="form-check-label" for="column_actions"><?php echo t('categories_actions', 'İşlemler'); ?></label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel', 'İptal'); ?></button>
                <button type="button" class="btn btn-primary" id="applyColumnFilter"><?php echo t('categories_apply', 'Uygula'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Filtreleme fonksiyonu
    window.filterCustomers = function() {
        var search = $('#search').val();
        var orderStatus = $('#order_status').val();
        var balanceStatus = $('#balance_status').val();
        
        // AJAX ile filtreleme yapılacak
        $.ajax({
            url: '<?php echo url('index.php?module=customers&action=filter'); ?>',
            type: 'POST',
            data: {
                search: search,
                order_status: orderStatus,
                balance_status: balanceStatus
            },
            success: function(response) {
                // Tablo içeriğini güncelle
                $('.table tbody').html(response);
            }
        });
    };
    
    // Enter tuşuna basıldığında form submit'i engelle
    $('#filterForm').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            filterCustomers();
        }
    });
    
    // Sütun filtresi uygulama
    $('#applyColumnFilter').click(function() {
        // Seçili sütunları kontrol et ve tabloyu güncelle
        $('.table th, .table td').each(function() {
            var columnId = $(this).data('column');
            if (columnId) {
                var isVisible = $('#column_' + columnId).is(':checked');
                $(this).toggle(isVisible);
            }
        });
        $('#columnFilterModal').modal('hide');
    });
});
</script>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
}
?>