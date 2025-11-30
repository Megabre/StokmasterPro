<?php
/**
 * Megabre StokMaster Pro
 * Stock Management Index
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

// Get page parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$productFilter = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Process actions
switch ($action) {
    case 'add':
        include_once MODULES_PATH . 'stock/add.php';
        break;
        
    case 'edit':
        include_once MODULES_PATH . 'stock/edit.php';
        break;
        
    case 'delete':
        include_once MODULES_PATH . 'stock/delete.php';
        break;
        
    case 'fields':
        include_once MODULES_PATH . 'stock/fields.php';
        break;
        
    default:
        // Default action: list all stock movements
        
        // Create cache key based on filters
        $cacheKey = 'stock_movements_' . md5($productFilter . '_' . $typeFilter . '_' . $dateFrom . '_' . $dateTo);
        
        // Get stock movements from cache or database
        $stockMovements = $cache->remember($cacheKey, function() use ($db, $productFilter, $typeFilter, $dateFrom, $dateTo) {
            // Build query based on filters
            $query = "SELECT sm.*, p.name as product_name, p.sku, p.barcode,
                      DATE_FORMAT(sm.date, '%d.%m.%Y') as formatted_date
                      FROM stock_movements sm 
                      JOIN products p ON sm.product_id = p.id";
            
            // Add WHERE conditions
            $where = [];
            $params = [];
            
            if ($productFilter > 0) {
                $where[] = "sm.product_id = :product_id";
                $params[':product_id'] = $productFilter;
            }
            
            if (!empty($typeFilter)) {
                $where[] = "sm.type = :type";
                $params[':type'] = $typeFilter;
            }
            
            if (!empty($dateFrom)) {
                $where[] = "sm.date >= :date_from";
                $params[':date_from'] = $dateFrom;
            }
            
            if (!empty($dateTo)) {
                $where[] = "sm.date <= :date_to";
                $params[':date_to'] = $dateTo;
            }
            
            if (!empty($where)) {
                $query .= " WHERE " . implode(" AND ", $where);
            }
            
            $query .= " ORDER BY sm.date DESC, sm.id DESC";
            
            // Execute query
            $db->query($query);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $db->bind($key, $value);
            }
            
            return $db->resultSet();
        }, 300); // Cache for 5 minutes
        
        // Get products for filter dropdown (cached)
        $products = $cache->remember('products_list_names', function() use ($db) {
            $db->query("SELECT id, name FROM products ORDER BY name ASC");
            return $db->resultSet();
        }, 600); // Cache for 10 minutes
        
        // Get stock summary (cached with same filters)
        $summaryCacheKey = 'stock_summary_' . md5($productFilter . '_' . $typeFilter . '_' . $dateFrom . '_' . $dateTo);
        $stockSummary = $cache->remember($summaryCacheKey, function() use ($db, $productFilter, $typeFilter, $dateFrom, $dateTo) {
            $summaryQuery = "SELECT 
                            COUNT(DISTINCT sm.product_id) as total_products,
                            COUNT(*) as total_movements,
                            SUM(CASE WHEN sm.type = 'in' THEN sm.quantity ELSE 0 END) as total_in,
                            SUM(CASE WHEN sm.type = 'out' THEN sm.quantity ELSE 0 END) as total_out,
                            SUM(CASE WHEN sm.type = 'adjustment' THEN sm.quantity ELSE 0 END) as total_adjustment
                            FROM stock_movements sm";
            
            $where = [];
            $params = [];
            
            if ($productFilter > 0) {
                $where[] = "sm.product_id = :product_id";
                $params[':product_id'] = $productFilter;
            }
            
            if (!empty($typeFilter)) {
                $where[] = "sm.type = :type";
                $params[':type'] = $typeFilter;
            }
            
            if (!empty($dateFrom)) {
                $where[] = "sm.date >= :date_from";
                $params[':date_from'] = $dateFrom;
            }
            
            if (!empty($dateTo)) {
                $where[] = "sm.date <= :date_to";
                $params[':date_to'] = $dateTo;
            }
            
            if (!empty($where)) {
                $summaryQuery .= " WHERE " . implode(" AND ", $where);
            }
            
            $db->query($summaryQuery);
            foreach ($params as $key => $value) {
                $db->bind($key, $value);
            }
            return $db->single();
        }, 300); // Cache for 5 minutes
        
        // Get products with low stock
        $db->query("
            SELECT p.id, p.name, p.min_stock_level,
                   COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity WHEN sm.type = 'out' THEN -sm.quantity ELSE sm.quantity END), 0) as current_stock
            FROM products p
            LEFT JOIN stock_movements sm ON p.id = sm.product_id
            GROUP BY p.id
            HAVING current_stock <= p.min_stock_level AND current_stock > 0
            ORDER BY current_stock ASC
            LIMIT 10
        ");
        $lowStockProducts = $db->resultSet();
        
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
                <h2 class="page-title"><?php echo t('stock_title', 'Stok Yönetimi'); ?></h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <button type="button" class="btn btn-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="ti ti-trash"></i> <span id="selectedCount">0</span> Seçili Hareketi Sil
                    </button>
                    <button type="button" class="btn btn-primary" id="columnFilterBtn" data-bs-toggle="modal" data-bs-target="#columnFilterModal">
                        <i class="ti ti-filter"></i> <?php echo t('stock_column_filter', 'Sütun Filtresi'); ?>
                    </button>
                    <a href="<?php echo url('index.php?module=stock&action=add'); ?>" class="btn btn-primary">
                        <i class="ti ti-plus"></i> <?php echo t('stock_add_movement', 'Stok Hareketi Ekle'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="page-body">
    <div class="container-xl">
        <!-- Stock Summary Cards -->
        <div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?php echo t('stock_total_products', 'Toplam Ürün'); ?></h6>
                        <h3 class="mb-0"><?php echo number_format($stockSummary['total_products'] ?? 0); ?></h3>
                    </div>
                    <div class="text-primary">
                        <i class="ti ti-package fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?php echo t('stock_total_in', 'Toplam Giriş'); ?></h6>
                        <h3 class="mb-0 text-success"><?php echo number_format($stockSummary['total_in'] ?? 0); ?></h3>
                    </div>
                    <div class="text-success">
                        <i class="ti ti-arrow-down fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?php echo t('stock_total_out', 'Toplam Çıkış'); ?></h6>
                        <h3 class="mb-0 text-danger"><?php echo number_format($stockSummary['total_out'] ?? 0); ?></h3>
                    </div>
                    <div class="text-danger">
                        <i class="ti ti-arrow-up fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><?php echo t('stock_movement_count', 'Hareket Sayısı'); ?></h6>
                        <h3 class="mb-0"><?php echo number_format($stockSummary['total_movements'] ?? 0); ?></h3>
                    </div>
                    <div class="text-info">
                        <i class="ti ti-arrow-left-right fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="product_filter" class="form-label"><?php echo t('stock_product', 'Ürün'); ?></label>
                <select id="product_filter" name="product_id" class="form-select select2">
                    <option value="0"><?php echo t('stock_movement_all_types', 'Tümü'); ?></option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" <?php echo $productFilter == $product['id'] ? 'selected' : ''; ?>>
                        <?php echo e($product['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="type_filter" class="form-label"><?php echo t('stock_movement_type', 'Hareket Tipi'); ?></label>
                <select id="type_filter" name="type" class="form-select">
                    <option value=""><?php echo t('stock_movement_all_types', 'Tümü'); ?></option>
                    <option value="in" <?php echo $typeFilter == 'in' ? 'selected' : ''; ?>><?php echo t('stock_movement_in', 'Stok Girişi'); ?></option>
                    <option value="out" <?php echo $typeFilter == 'out' ? 'selected' : ''; ?>><?php echo t('stock_movement_out', 'Stok Çıkışı'); ?></option>
                    <option value="adjustment" <?php echo $typeFilter == 'adjustment' ? 'selected' : ''; ?>><?php echo t('stock_movement_adjustment', 'Düzeltme'); ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label"><?php echo t('orders_filter_start_date', 'Başlangıç Tarihi'); ?></label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label"><?php echo t('orders_filter_end_date', 'Bitiş Tarihi'); ?></label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-search"></i>
                </button>
            </div>
            <div class="col-md-1">
                <button type="button" id="resetFilter" class="btn btn-outline-secondary w-100">
                    <i class="ti ti-refresh"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Movements Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?php echo t('stock_movements', 'Stok Hareketleri'); ?></h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable" id="stock-table" data-page-length="50">
                <thead>
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAll" title="Tümünü Seç/Kaldır">
                        </th>
                        <th width="60"><?php echo t('categories_id', 'ID'); ?></th>
                        <th><?php echo t('stock_product', 'Ürün'); ?></th>
                        <th width="120"><?php echo t('stock_movement_type', 'Hareket Tipi'); ?></th>
                        <th width="100"><?php echo t('stock_quantity', 'Miktar'); ?></th>
                        <th width="80"><?php echo t('stock_unit', 'Birim'); ?></th>
                        <th width="100"><?php echo t('stock_date', 'Tarih'); ?></th>
                        <th><?php echo t('stock_notes', 'Not'); ?></th>
                        <th width="120"><?php echo t('categories_actions', 'İşlemler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stockMovements as $movement): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="stock-checkbox" value="<?php echo $movement['id']; ?>" 
                                   data-product-name="<?php echo e($movement['product_name']); ?>" 
                                   data-movement-type="<?php echo $movement['type']; ?>"
                                   data-quantity="<?php echo $movement['quantity']; ?>">
                        </td>
                        <td><?php echo $movement['id']; ?></td>
                        <td>
                            <a href="<?php echo url('index.php?module=products&action=edit&id=' . $movement['product_id']); ?>">
                                <?php echo e($movement['product_name']); ?>
                            </a>
                            <?php if (!empty($movement['sku'])): ?>
                                <br><small class="text-muted">SKU: <?php echo e($movement['sku']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($movement['type'] == 'in'): ?>
                                <span class="badge bg-success"><?php echo t('stock_movement_in', 'Stok Girişi'); ?></span>
                            <?php elseif ($movement['type'] == 'out'): ?>
                                <span class="badge bg-danger"><?php echo t('stock_movement_out', 'Stok Çıkışı'); ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning"><?php echo t('stock_movement_adjustment', 'Düzeltme'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($movement['type'] == 'in'): ?>
                                <span class="text-success">+<?php echo number_format($movement['quantity'], 2); ?></span>
                            <?php elseif ($movement['type'] == 'out'): ?>
                                <span class="text-danger">-<?php echo number_format($movement['quantity'], 2); ?></span>
                            <?php else: ?>
                                <span class="text-warning"><?php echo number_format($movement['quantity'], 2); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $units = [
                                'piece' => t('stock_unit_piece', 'Adet'),
                                'kg' => t('stock_unit_kg', 'Kg'),
                                'lt' => t('stock_unit_lt', 'Lt'),
                                'm' => t('stock_unit_m', 'Metre'),
                                'm2' => t('stock_unit_m2', 'M²'),
                                'm3' => t('stock_unit_m3', 'M³'),
                                'package' => t('stock_unit_package', 'Paket'),
                                'box' => t('stock_unit_box', 'Kutu'),
                                'pallet' => t('stock_unit_pallet', 'Palet')
                            ];
                            echo $units[$movement['unit']] ?? $movement['unit'];
                            ?>
                        </td>
                        <td><?php echo $movement['formatted_date']; ?></td>
                        <td>
                            <?php if (!empty($movement['notes'])): ?>
                                <small><?php echo e(strlen($movement['notes']) > 50 ? substr($movement['notes'], 0, 50) . '...' : $movement['notes']); ?></small>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="<?php echo url('index.php?module=stock&action=edit&id=' . $movement['id']); ?>" 
                                   class="btn btn-sm btn-info" 
                                   data-bs-toggle="tooltip" 
                                   title="<?php echo t('edit', 'Düzenle'); ?>">
                                    <i class="ti ti-edit"></i>
                                </a>
                                <a href="<?php echo url('api/stock.php?action=delete&id=' . $movement['id']); ?>" 
                                   class="btn btn-sm btn-danger delete-confirm" 
                                   data-bs-toggle="tooltip" 
                                   title="<?php echo t('delete', 'Sil'); ?>" 
                                   data-item-name="stok hareketini">
                                    <i class="ti ti-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Low Stock Alert -->
<?php if (!empty($lowStockProducts)): ?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0"><i class="ti ti-alert-triangle"></i> <?php echo t('stock_low_stock_alert', 'Kritik Stok Seviyeleri'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th><?php echo t('stock_product_name', 'Ürün'); ?></th>
                                <th><?php echo t('stock_current_stock', 'Mevcut Stok'); ?></th>
                                <th><?php echo t('stock_min_level', 'Min. Seviye'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockProducts as $product): ?>
                            <tr>
                                <td><?php echo e($product['name']); ?></td>
                                <td class="text-danger"><?php echo number_format($product['current_stock'], 2); ?></td>
                                <td><?php echo number_format($product['min_stock_level'], 2); ?></td>
                                <td>
                                    <a href="<?php echo url('index.php?module=stock&action=add&product_id=' . $product['id']); ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="ti ti-plus"></i>
                                    </a>
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
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0"><i class="ti ti-info-circle"></i> <?php echo t('stock_help_tips', 'Yardım & İpuçları'); ?></h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><?php echo t('stock_help_tip1', 'Stok girişi yapmak için "Stok Hareketi Ekle" butonunu kullanın'); ?></li>
                    <li><?php echo t('stock_help_tip2', 'Kritik seviyedeki ürünler sarı ile işaretlenir'); ?></li>
                    <li><?php echo t('stock_help_tip3', 'Stok hareketlerini tarih, ürün ve tipe göre filtreleyebilirsiniz'); ?></li>
                    <li><?php echo t('stock_help_tip4', 'Stok düzeltmesi için "Düzeltme" tipini kullanın'); ?></li>
                    <li><?php echo t('stock_help_tip5', 'Her hareket için not ekleyebilirsiniz'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Column Filter Modal -->
<div class="modal fade" id="columnFilterModal" tabindex="-1" aria-labelledby="columnFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="columnFilterModalLabel"><?php echo t('stock_column_filter_title', 'Sütun Filtresi'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo t('ui_aria_close', 'Kapat'); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo t('stock_column_filter_desc', 'Tabloda görmek istediğiniz sütunları seçin:'); ?></p>
                <form id="columnFilterForm">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_id" checked disabled>
                        <label class="form-check-label" for="column_id"><?php echo t('categories_id', 'ID'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_product" checked>
                        <label class="form-check-label" for="column_product"><?php echo t('stock_column_product', 'Ürün'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_type" checked>
                        <label class="form-check-label" for="column_type"><?php echo t('stock_column_movement_type', 'Hareket Tipi'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_quantity" checked>
                        <label class="form-check-label" for="column_quantity"><?php echo t('stock_column_quantity', 'Miktar'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_unit" checked>
                        <label class="form-check-label" for="column_unit"><?php echo t('stock_column_unit', 'Birim'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_date" checked>
                        <label class="form-check-label" for="column_date"><?php echo t('stock_column_date', 'Tarih'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_notes" checked>
                        <label class="form-check-label" for="column_notes"><?php echo t('stock_column_notes', 'Not'); ?></label>
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
        // Bulk delete functionality - Define function first
        function updateBulkDeleteButton() {
            const selected = $('.stock-checkbox:checked').length;
            if (selected > 0) {
                $('#bulkDeleteBtn').show();
                $('#selectedCount').text(selected);
            } else {
                $('#bulkDeleteBtn').hide();
            }
        }
        
        // Initialize Select2
        if ($('.select2').length > 0) {
            try {
                $('.select2').select2({
                    theme: 'bootstrap-5'
                });
            } catch (e) {
                console.error('Error initializing Select2:', e);
            }
        }
        
        // Check if table exists before initializing DataTable
        if ($('#stock-table').length === 0) {
            console.error('Stock table not found');
            updateBulkDeleteButton();
            return;
        }
        
        // Initialize DataTable
        try {
            const stockTable = $('#stock-table').DataTable({
                language: {
                    emptyTable: 'Tabloda veri bulunmuyor',
                    info: 'Toplam _TOTAL_ kayıttan _START_ - _END_ arası gösteriliyor',
                    infoEmpty: 'Toplam 0 kayıttan 0 - 0 arası gösteriliyor',
                    infoFiltered: '(_MAX_ kayıt içerisinden bulunan)',
                    lengthMenu: '_MENU_ kayıt göster',
                    loadingRecords: 'Yükleniyor...',
                    processing: 'İşleniyor...',
                    search: 'Ara:',
                    zeroRecords: 'Eşleşen kayıt bulunamadı',
                    paginate: {
                        first: 'İlk',
                        last: 'Son',
                        next: 'Sonraki',
                        previous: 'Önceki'
                    }
                },
                drawCallback: function() {
                    // Update bulk delete button visibility after table draw
                    updateBulkDeleteButton();
                }
            });
            
            // Load column visibility state from localStorage
            try {
                const columnVisibility = JSON.parse(localStorage.getItem('stockColumnVisibility')) || {};
                
                // Apply saved column visibility
                if (Object.keys(columnVisibility).length > 0) {
                    Object.keys(columnVisibility).forEach(key => {
                        try {
                            stockTable.column(key).visible(columnVisibility[key]);
                            $(`#column_${key}`).prop('checked', columnVisibility[key]);
                        } catch (e) {
                            console.error('Error applying column visibility:', e);
                        }
                    });
                }
            } catch (e) {
                console.error('Error loading column visibility:', e);
            }
            
            // Check bulk delete button visibility on page load
            updateBulkDeleteButton();
        
            
            // Apply column filter
            $('#applyColumnFilter').on('click', function() {
                const newColumnVisibility = {};
                
                // Update column visibility
                newColumnVisibility[1] = $('#column_product').is(':checked');
                newColumnVisibility[2] = $('#column_type').is(':checked');
                newColumnVisibility[3] = $('#column_quantity').is(':checked');
                newColumnVisibility[4] = $('#column_unit').is(':checked');
                newColumnVisibility[5] = $('#column_date').is(':checked');
                newColumnVisibility[6] = $('#column_notes').is(':checked');
                
                // Apply changes
                Object.keys(newColumnVisibility).forEach(key => {
                    try {
                        stockTable.column(key).visible(newColumnVisibility[key]);
                    } catch (e) {
                        console.error('Error applying column visibility:', e);
                    }
                });
                
                // Save to localStorage
                try {
                    localStorage.setItem('stockColumnVisibility', JSON.stringify(newColumnVisibility));
                } catch (e) {
                    console.error('Error saving column visibility:', e);
                }
                
                // Close modal
                $('#columnFilterModal').modal('hide');
            });
            
            // Filter form submit
        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize();
            window.location.href = '<?php echo url('index.php?module=stock'); ?>&' + formData;
        });
        
        // Reset filter button
        $('#resetFilter').on('click', function() {
            window.location.href = '<?php echo url('index.php?module=stock'); ?>';
        });
        
        // Delete confirmation
        $('.delete-confirm').on('click', function(e) {
            e.preventDefault();
            const itemName = $(this).data('item-name');
            const deleteUrl = $(this).attr('href');
            
            if (confirm('Bu stok hareketini silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) {
                window.location.href = deleteUrl;
            }
        });
        
        // Select all checkbox
        $('#selectAll').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.stock-checkbox').prop('checked', isChecked);
            updateBulkDeleteButton();
        });
        
        } catch (e) {
            console.error('Error initializing DataTable:', e);
            // Fallback: Check bulk delete button visibility even if DataTable fails
            updateBulkDeleteButton();
        }
        
        // Individual checkbox change
        $(document).on('change', '.stock-checkbox', function() {
            const total = $('.stock-checkbox').length;
            const checked = $('.stock-checkbox:checked').length;
            $('#selectAll').prop('checked', total === checked);
            updateBulkDeleteButton();
        });
        
        // Bulk delete button click
        $('#bulkDeleteBtn').on('click', function() {
            const selectedIds = [];
            const selectedMovements = [];
            
            $('.stock-checkbox:checked').each(function() {
                const id = $(this).val();
                const productName = $(this).data('product-name');
                const movementType = $(this).data('movement-type');
                const quantity = $(this).data('quantity');
                
                selectedIds.push(id);
                selectedMovements.push({
                    id: id,
                    product: productName,
                    type: movementType,
                    quantity: quantity
                });
            });
            
            if (selectedIds.length === 0) {
                alert('Lütfen silmek istediğiniz stok hareketlerini seçin.');
                return;
            }
            
            const confirmMessage = `Seçili ${selectedIds.length} stok hareketini silmek istediğinizden emin misiniz?\n\n` +
                `UYARI: Stok hareketleri silindiğinde stok miktarları etkilenecektir!\n\n` +
                `Silinecek hareketler:\n${selectedMovements.slice(0, 10).map(m => `- ${m.product} (${m.type}: ${m.quantity})`).join('\n')}${selectedMovements.length > 10 ? '...' : ''}\n\n` +
                `Bu işlem geri alınamaz!`;
            
            if (confirm(confirmMessage)) {
                // Show loading
                $(this).prop('disabled', true).html('<i class="ti ti-loader-2 spinner"></i> Siliniyor...');
                
                // Send bulk delete request
                $.ajax({
                    url: '<?php echo url('api/stock.php?action=bulk-delete'); ?>',
                    type: 'POST',
                    data: {
                        ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.message || `${selectedIds.length} stok hareketi başarıyla silindi.`);
                            location.reload();
                        } else {
                            alert('Hata: ' + (response.message || 'Bilinmeyen bir hata oluştu.'));
                            $('#bulkDeleteBtn').prop('disabled', false).html('<i class="ti ti-trash"></i> <span id="selectedCount">' + selectedIds.length + '</span> Seçili Hareketi Sil');
                        }
                    },
                    error: function() {
                        alert('Sunucu hatası oluştu. Lütfen tekrar deneyin.');
                        $('#bulkDeleteBtn').prop('disabled', false).html('<i class="ti ti-trash"></i> <span id="selectedCount">' + selectedIds.length + '</span> Seçili Hareketi Sil');
                    }
                });
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
?>