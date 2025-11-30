<?php
/**
 * Megabre StokMaster Pro
 * Products Index
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
$categoryFilter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Process actions
switch ($action) {
    case 'add':
        include_once MODULES_PATH . 'products/add.php';
        break;
        
    case 'edit':
        include_once MODULES_PATH . 'products/edit.php';
        break;
        
    case 'delete':
        include_once MODULES_PATH . 'products/delete.php';
        break;
        
    case 'view':
        include_once MODULES_PATH . 'products/view.php';
        break;
        
    case 'fields':
        include_once MODULES_PATH . 'products/fields.php';
        break;
        
    default:
        // Default action: list all products
        
        // Create cache key based on filters
        $cacheKey = 'products_list_' . $categoryFilter;
        
        // Get products from cache or database
        $products = $cache->remember($cacheKey, function() use ($db, $categoryFilter) {
            // Build query based on filters
            $query = "SELECT p.*, c.name as category_name 
                      FROM products p 
                      JOIN categories c ON p.category_id = c.id";
            
            // Add category filter if specified
            if ($categoryFilter > 0) {
                $query .= " WHERE p.category_id = :category_id";
            }
            
            $query .= " ORDER BY p.id DESC";
            
            // Execute query
            $db->query($query);
            
            // Bind parameters if needed
            if ($categoryFilter > 0) {
                $db->bind(':category_id', $categoryFilter);
            }
            
            return $db->resultSet();
        }, 300); // Cache for 5 minutes
        
        // Get categories for filter dropdown (cache separately)
        $categories = $cache->remember('categories_list_all', function() use ($db) {
            $db->query("SELECT id, name FROM categories ORDER BY name ASC");
            return $db->resultSet();
        }, 600); // Cache for 10 minutes
        
        // Get product stock levels (cache by product IDs)
        $productStocks = [];
        if (!empty($products)) {
            $productIds = array_column($products, 'id');
            sort($productIds); // Sort for consistent cache key
            $productIdsHash = md5(implode(',', $productIds));
            $stockCacheKey = 'products_stocks_' . $productIdsHash;
            
            $productStocks = $cache->remember($stockCacheKey, function() use ($db, $productIds) {
                $productIdsStr = implode(',', $productIds);
                
                $db->query("SELECT product_id, SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level 
                            FROM stock_movements 
                            WHERE product_id IN ($productIdsStr) 
                            GROUP BY product_id");
                
                $stockResults = $db->resultSet();
                $stocks = [];
                foreach ($stockResults as $stock) {
                    $stocks[$stock['product_id']] = $stock['stock_level'];
                }
                return $stocks;
            }, 300); // Cache for 5 minutes
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
                <h2 class="page-title"><?php echo t('products_title', 'Ürünler'); ?></h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <button type="button" class="btn btn-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="ti ti-trash"></i> <span id="selectedCount">0</span> Seçili Ürünü Sil
                    </button>
                    <button type="button" class="btn btn-primary" id="columnFilterBtn" data-bs-toggle="modal" data-bs-target="#columnFilterModal">
                        <i class="ti ti-filter"></i> <?php echo t('products_column_filter', 'Sütun Filtresi'); ?>
                    </button>
                    <a href="<?php echo url('index.php?module=products&action=add'); ?>" class="btn btn-primary">
                        <i class="ti ti-plus"></i> <?php echo t('products_new_product', 'Yeni Ürün'); ?>
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
        <form id="filterForm" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="category_filter" class="form-label"><?php echo t('products_category', 'Kategori'); ?></label>
                <select id="category_filter" name="category_id" class="form-select">
                    <option value="0"><?php echo t('customers_all', 'Tümü'); ?></option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo e($category['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="search" class="form-label"><?php echo t('search', 'Ara'); ?></label>
                <input type="text" class="form-control" id="search" placeholder="<?php echo t('products_search_placeholder', 'Ürün adı, barkod veya SKU'); ?>">
            </div>
            <div class="col-md-2">
                <label for="stock_status" class="form-label"><?php echo t('products_stock_status', 'Stok Durumu'); ?></label>
                <select id="stock_status" class="form-select">
                    <option value="all"><?php echo t('customers_all', 'Tümü'); ?></option>
                    <option value="in_stock"><?php echo t('products_stock_in_stock', 'Stokta Var'); ?></option>
                    <option value="low_stock"><?php echo t('products_stock_low_stock', 'Kritik Seviye'); ?></option>
                    <option value="out_of_stock"><?php echo t('products_stock_out_of_stock', 'Stokta Yok'); ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-search"></i> <?php echo t('customers_filter', 'Filtrele'); ?>
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" id="resetFilter" class="btn w-100">
                    <i class="ti ti-refresh"></i> <?php echo t('ui_reset', 'Sıfırla'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="products-table" data-page-length="50">
                <thead>
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAll" title="Tümünü Seç/Kaldır">
                        </th>
                        <th width="60"><?php echo t('categories_id', 'ID'); ?></th>
                        <th width="80"><?php echo t('products_image', 'Resim'); ?></th>
                        <th><?php echo t('products_name', 'Ürün Adı'); ?></th>
                        <th><?php echo t('products_category', 'Kategori'); ?></th>
                        <th><?php echo t('products_price', 'Fiyat'); ?></th>
                        <th><?php echo t('products_barcode_sku', 'Barkod/SKU'); ?></th>
                        <th><?php echo t('products_stock_status', 'Stok Durumu'); ?></th>
                        <th width="150"><?php echo t('categories_actions', 'İşlemler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <?php 
                        // Get stock level
                        $stockLevel = isset($productStocks[$product['id']]) ? $productStocks[$product['id']] : 0;
                        
                        // Determine stock status
                        $stockStatus = 'out_of_stock';
                        $stockBadgeClass = 'danger';
                        $stockText = t('products_stock_out_of_stock', 'Stokta Yok');
                        
                        if ($stockLevel > 0) {
                            if ($stockLevel <= $product['min_stock_level']) {
                                $stockStatus = 'low_stock';
                                $stockBadgeClass = 'warning';
                                $stockText = t('products_stock_critical_level', 'Kritik Seviye') . ' (' . $stockLevel . ')';
                            } else {
                                $stockStatus = 'in_stock';
                                $stockBadgeClass = 'success';
                                $stockText = t('products_stock_in_stock', 'Stokta Var') . ' (' . $stockLevel . ')';
                            }
                        }
                    ?>
                    <tr data-stock-status="<?php echo $stockStatus; ?>" data-category-id="<?php echo $product['category_id']; ?>">
                        <td>
                            <input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>" data-product-name="<?php echo e($product['name']); ?>">
                        </td>
                        <td><?php echo $product['id']; ?></td>
                        <td>
                            <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo url('uploads/products/' . $product['image']); ?>" alt="<?php echo e($product['name']); ?>" class="img-thumbnail" width="50">
                            <?php else: ?>
                            <img src="<?php echo asset('img/no-image.png'); ?>" alt="No Image" class="img-thumbnail" width="50">
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo url('index.php?module=products&action=edit&id=' . $product['id']); ?>">
                                <?php echo e($product['name']); ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?php echo url('index.php?module=products&category_id=' . $product['category_id']); ?>">
                                <?php echo e($product['category_name']); ?>
                            </a>
                        </td>
                        <td class="text-end"><?php 
                            // Display price without formatting, remove trailing zeros
                            $priceDisplay = $product['price'];
                            if (is_numeric($priceDisplay)) {
                                $priceDisplay = rtrim(rtrim(sprintf('%.10f', $priceDisplay), '0'), '.');
                            }
                            echo $priceDisplay;
                        ?> ₺</td>
                        <td>
                            <?php if (!empty($product['barcode'])): ?>
                            <span class="d-block"><i class="ti ti-barcode"></i> <?php echo e($product['barcode']); ?></span>
                            <?php endif; ?>
                            
                            <?php if (!empty($product['sku'])): ?>
                            <span class="d-block"><i class="ti ti-tag"></i> <?php echo e($product['sku']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $stockBadgeClass; ?>"><?php echo $stockText; ?></span>
                        </td>
                        <td>
                            <div class="btn-list">
                                <a href="<?php echo url('index.php?module=products&action=view&id=' . $product['id']); ?>" class="btn btn-sm btn-info" title="<?php echo t('view', 'Görüntüle'); ?>">
                                    <i class="ti ti-eye"></i>
                                </a>
                                <a href="<?php echo url('index.php?module=products&action=edit&id=' . $product['id']); ?>" class="btn btn-sm btn-primary" title="<?php echo t('edit', 'Düzenle'); ?>">
                                    <i class="ti ti-edit"></i>
                                </a>
                                <a href="<?php echo url('index.php?module=products&action=delete&id=' . $product['id']); ?>" class="btn btn-sm btn-danger" title="<?php echo t('delete', 'Sil'); ?>">
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

<!-- Product Statistics -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Ürün İstatistikleri</h5>
            </div>
            <div class="card-body">
                <?php
                // Get total products count
                $totalProducts = count($products);
                
                // Get products with low stock
                $lowStockProducts = 0;
                foreach ($products as $product) {
                    $stockLevel = isset($productStocks[$product['id']]) ? $productStocks[$product['id']] : 0;
                    if ($stockLevel > 0 && $stockLevel <= $product['min_stock_level']) {
                        $lowStockProducts++;
                    }
                }
                
                // Get out of stock products
                $outOfStockProducts = 0;
                foreach ($products as $product) {
                    $stockLevel = isset($productStocks[$product['id']]) ? $productStocks[$product['id']] : 0;
                    if ($stockLevel <= 0) {
                        $outOfStockProducts++;
                    }
                }
                
                // Get total product value
                $totalValue = 0;
                foreach ($products as $product) {
                    $stockLevel = isset($productStocks[$product['id']]) ? $productStocks[$product['id']] : 0;
                    if ($stockLevel > 0) {
                        $totalValue += $stockLevel * $product['price'];
                    }
                }
                ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('dashboard_total_products', 'Toplam Ürün'); ?></h6>
                            <h4><?php echo $totalProducts; ?></h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('products_stock_critical_level', 'Kritik Seviye'); ?></h6>
                            <h4><?php echo $lowStockProducts; ?></h4>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('reports_out_of_stock', 'Stokta Olmayan'); ?></h6>
                            <h4><?php echo $outOfStockProducts; ?></h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('reports_category_stock_value', 'Toplam Stok Değeri'); ?></h6>
                            <h4><?php echo formatPrice($totalValue); ?> ₺</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('categories_help_tips', 'Yardım & İpuçları'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <h6><?php echo t('products_product_management', 'Ürün Yönetimi'); ?></h6>
                    <ul class="mb-0">
                        <li><?php echo t('products_add_first_message', 'Yeni ürün eklemek için sağ üstteki "Yeni Ürün" butonunu kullanın.'); ?></li>
                        <li><?php echo t('products_filter_by_category', 'Ürünlerinizi kategorilere göre filtreleyebilirsiniz.'); ?></li>
                        <li><?php echo t('products_stock_status_info', 'Kritik stok seviyesi altındaki ürünler sarı ile, stokta olmayan ürünler kırmızı ile işaretlenir.'); ?></li>
                        <li><?php echo t('products_add_stock_info', 'Ürün stoğu eklemek için işlemler sütunundaki "+" butonunu kullanın.'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Column Filter Modal -->
<div class="modal fade" id="columnFilterModal" tabindex="-1" aria-labelledby="columnFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="columnFilterModalLabel"><?php echo t('products_column_filter', 'Sütun Filtresi'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo t('ui_aria_close', 'Close'); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo t('categories_column_filter_desc', 'Tabloda görmek istediğiniz sütunları seçin:'); ?></p>
                <form id="columnFilterForm">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_checkbox" checked disabled>
                        <label class="form-check-label" for="column_checkbox">Seçim</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_id" checked disabled>
                        <label class="form-check-label" for="column_id">ID</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_image" checked>
                        <label class="form-check-label" for="column_image"><?php echo t('products_image', 'Resim'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_name" checked>
                        <label class="form-check-label" for="column_name"><?php echo t('products_name', 'Ürün Adı'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_category" checked>
                        <label class="form-check-label" for="column_category"><?php echo t('products_category', 'Kategori'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_price" checked>
                        <label class="form-check-label" for="column_price"><?php echo t('products_price', 'Fiyat'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_barcode_sku" checked>
                        <label class="form-check-label" for="column_barcode_sku"><?php echo t('products_barcode_sku', 'Barkod/SKU'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_stock" checked>
                        <label class="form-check-label" for="column_stock"><?php echo t('products_stock_status', 'Stok Durumu'); ?></label>
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
            const selected = $('.product-checkbox:checked').length;
            if (selected > 0) {
                $('#bulkDeleteBtn').show();
                $('#selectedCount').text(selected);
            } else {
                $('#bulkDeleteBtn').hide();
            }
        }
        
        // Check if table exists before initializing DataTable
        if ($('#products-table').length === 0) {
            console.error('Products table not found');
            updateBulkDeleteButton();
            return;
        }
        
        // Declare productsTable in outer scope
        let productsTable;
        
        // Wait a bit to ensure main.js doesn't interfere, then initialize
        setTimeout(function() {
            // Destroy existing DataTable if already initialized
            if ($.fn.DataTable && $.fn.DataTable.isDataTable('#products-table')) {
                $('#products-table').DataTable().destroy();
            }
            
            // Check if DataTable is available
            if (!$.fn.DataTable) {
                console.error('DataTable plugin not loaded');
                updateBulkDeleteButton();
                return;
            }
            
            // Initialize DataTable
            try {
                productsTable = $('#products-table').DataTable({
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
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                pageLength: 50,
                responsive: true,
                drawCallback: function() {
                    // Update bulk delete button visibility after table draw
                    updateBulkDeleteButton();
                }
            });
            
            // Load column visibility state from localStorage
            try {
                const columnVisibility = JSON.parse(localStorage.getItem('productsColumnVisibility')) || {};
                
                // Apply saved column visibility
                if (Object.keys(columnVisibility).length > 0) {
                    Object.keys(columnVisibility).forEach(key => {
                        try {
                            const colIndex = parseInt(key);
                            productsTable.column(colIndex).visible(columnVisibility[key]);
                            
                            // Update checkbox state based on column index
                            if (colIndex === 2) $('#column_image').prop('checked', columnVisibility[key]);
                            else if (colIndex === 3) $('#column_name').prop('checked', columnVisibility[key]);
                            else if (colIndex === 4) $('#column_category').prop('checked', columnVisibility[key]);
                            else if (colIndex === 5) $('#column_price').prop('checked', columnVisibility[key]);
                            else if (colIndex === 6) $('#column_barcode_sku').prop('checked', columnVisibility[key]);
                            else if (colIndex === 7) $('#column_stock').prop('checked', columnVisibility[key]);
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
                if (!productsTable) {
                    alert('Tablo henüz yüklenmedi. Lütfen bekleyin.');
                    return;
                }
                
                const newColumnVisibility = {};
                
                // Update column visibility
                // Column indexes: 0=checkbox, 1=ID, 2=Image, 3=Name, 4=Category, 5=Price, 6=Barcode/SKU, 7=Stock, 8=Actions
                newColumnVisibility[2] = $('#column_image').is(':checked');      // Image (index 2)
                newColumnVisibility[3] = $('#column_name').is(':checked');        // Name (index 3)
                newColumnVisibility[4] = $('#column_category').is(':checked');    // Category (index 4)
                newColumnVisibility[5] = $('#column_price').is(':checked');       // Price (index 5)
                newColumnVisibility[6] = $('#column_barcode_sku').is(':checked'); // Barcode/SKU (index 6)
                newColumnVisibility[7] = $('#column_stock').is(':checked');      // Stock (index 7)
                
                // Apply changes
                Object.keys(newColumnVisibility).forEach(key => {
                    try {
                        const colIndex = parseInt(key);
                        productsTable.column(colIndex).visible(newColumnVisibility[key]);
                    } catch (e) {
                        console.error('Error applying column visibility for column ' + key + ':', e);
                    }
                });
                
                // Save to localStorage
                try {
                    localStorage.setItem('productsColumnVisibility', JSON.stringify(newColumnVisibility));
                } catch (e) {
                    console.error('Error saving column visibility:', e);
                }
                
                // Close modal
                $('#columnFilterModal').modal('hide');
            });
            
            // Filter variables - must be accessible to filter function
            window.stockStatusFilter = 'all';
            window.categoryFilter = <?php echo $categoryFilter; ?>;
            
            // Initialize filters from URL if present
            if (window.categoryFilter > 0) {
                $('#category_filter').val(window.categoryFilter);
            }
            
            // Custom filter function
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    // Only apply to products table
                    const tableId = $(settings.nTable).attr('id');
                    if (tableId !== 'products-table') {
                        return true;
                    }
                    
                    try {
                        // Get row from DataTable
                        const api = new $.fn.dataTable.Api(settings);
                        const rowData = api.row(dataIndex).data();
                        const rowNode = api.row(dataIndex).node();
                        
                        if (!rowNode) {
                            return true;
                        }
                        
                        const $row = $(rowNode);
                        
                        // Filter by stock status
                        if (window.stockStatusFilter && window.stockStatusFilter !== 'all') {
                            const rowStockStatus = $row.attr('data-stock-status');
                            if (!rowStockStatus || rowStockStatus !== window.stockStatusFilter) {
                                return false;
                            }
                        }
                        
                        // Filter by category
                        if (window.categoryFilter && window.categoryFilter > 0) {
                            const rowCategoryId = parseInt($row.attr('data-category-id')) || 0;
                            if (rowCategoryId !== window.categoryFilter) {
                                return false;
                            }
                        }
                        
                        // Filter by search text (product name, SKU, barcode)
                        const searchInput = $('#search');
                        if (searchInput.length) {
                            const searchText = searchInput.val().trim().toLowerCase();
                            if (searchText) {
                                // Get product name (column index 3, after checkbox, ID, image)
                                const productName = $row.find('td:eq(3)').text().toLowerCase();
                                
                                // Get SKU and barcode (column index 6)
                                const skuBarcode = $row.find('td:eq(6)').text().toLowerCase();
                                
                                // Search in product name, SKU, or barcode
                                if (productName.indexOf(searchText) === -1 && skuBarcode.indexOf(searchText) === -1) {
                                    return false;
                                }
                            }
                        }
                        
                        return true;
                    } catch (e) {
                        console.error('Filter error:', e, dataIndex);
                        return true;
                    }
                }
            );
            
            // Function to apply all filters
            function applyFilters() {
                if (productsTable) {
                    productsTable.draw();
                }
            }
            
            // Filter form submit
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                
                const categoryId = parseInt($('#category_filter').val()) || 0;
                const stockStatus = $('#stock_status').val();
                
                // Update filter variables
                window.categoryFilter = categoryId;
                window.stockStatusFilter = stockStatus;
                
                // Apply all filters
                applyFilters();
            });
            
            // Reset filter button
            $('#resetFilter').on('click', function() {
                $('#category_filter').val(0);
                $('#search').val('');
                $('#stock_status').val('all');
                
                // Reset filter variables
                window.categoryFilter = 0;
                window.stockStatusFilter = 'all';
                
                // Apply filters
                applyFilters();
            });
            
            // Category filter change
            $('#category_filter').on('change', function() {
                window.categoryFilter = parseInt($(this).val()) || 0;
                applyFilters();
            });
            
            // Stock status filter change
            $('#stock_status').on('change', function() {
                window.stockStatusFilter = $(this).val();
                applyFilters();
            });
            
            // Search input - real-time search
            let searchTimeout;
            $('#search').on('keyup', function() {
                clearTimeout(searchTimeout);
                
                searchTimeout = setTimeout(function() {
                    applyFilters();
                }, 300); // Wait 300ms after user stops typing
            });
            
            // Apply initial filters if category filter is set from URL
            if (window.categoryFilter > 0) {
                setTimeout(function() {
                    applyFilters();
                }, 100);
            }
            
            } catch (e) {
                console.error('Error initializing DataTable:', e);
                // Fallback: Check bulk delete button visibility even if DataTable fails
                updateBulkDeleteButton();
            }
        }, 100); // Wait 100ms to ensure main.js doesn't interfere
        
        // Select all checkbox
        $('#selectAll').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.product-checkbox').prop('checked', isChecked);
            updateBulkDeleteButton();
        });
        
        // Individual checkbox change
        $(document).on('change', '.product-checkbox', function() {
            const total = $('.product-checkbox').length;
            const checked = $('.product-checkbox:checked').length;
            $('#selectAll').prop('checked', total === checked);
            updateBulkDeleteButton();
        });
        
        // Bulk delete button click
        $('#bulkDeleteBtn').on('click', function() {
            const selectedIds = [];
            const selectedNames = [];
            
            $('.product-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
                selectedNames.push($(this).data('product-name'));
            });
            
            if (selectedIds.length === 0) {
                alert('Lütfen silmek istediğiniz ürünleri seçin.');
                return;
            }
            
            const confirmMessage = `Seçili ${selectedIds.length} ürünü silmek istediğinizden emin misiniz?\n\n` +
                `Silinecek ürünler:\n${selectedNames.slice(0, 10).join(', ')}${selectedNames.length > 10 ? '...' : ''}\n\n` +
                `Bu işlem geri alınamaz!`;
            
            if (confirm(confirmMessage)) {
                // Show loading
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Siliniyor...');
                
                // Send bulk delete request
                $.ajax({
                    url: '<?php echo url('api/products.php?action=bulk-delete'); ?>',
                    type: 'POST',
                    data: {
                        ids: selectedIds,
                        force_delete: true // Otomatik olarak stokları da sil
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.message || `${selectedIds.length} ürün başarıyla silindi.`);
                            location.reload();
                        } else {
                            alert('Hata: ' + (response.message || 'Bilinmeyen bir hata oluştu.'));
                            $('#bulkDeleteBtn').prop('disabled', false).html('<i class="fas fa-trash"></i> <span id="selectedCount">' + selectedIds.length + '</span> Seçili Ürünü Sil');
                        }
                    },
                    error: function() {
                        alert('Sunucu hatası oluştu. Lütfen tekrar deneyin.');
                        $('#bulkDeleteBtn').prop('disabled', false).html('<i class="fas fa-trash"></i> <span id="selectedCount">' + selectedIds.length + '</span> Seçili Ürünü Sil');
                    }
                });
            }
        });
    });
</script>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
}
?>