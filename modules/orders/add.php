<?php
/**
 * Megabre StokMaster Pro
 * Add Order
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

// Get preselected customer if specified
$preselectedCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// Get all customers
$db->query("SELECT id, first_name, last_name, phone, company FROM customers ORDER BY first_name ASC, last_name ASC");
$customers = $db->resultSet();

// Get all products with stock info
$db->query("
    SELECT p.*, c.name as category_name,
           COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity WHEN sm.type = 'out' THEN -sm.quantity ELSE 0 END), 0) as stock_level
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN stock_movements sm ON p.id = sm.product_id
    WHERE p.id > 0
    GROUP BY p.id, p.category_id, p.name, p.price, p.sku, p.barcode, p.description, p.min_stock_level, p.image, p.created_at, p.updated_at, c.name
    ORDER BY p.name ASC
");
$products = $db->resultSet();

// Debug output
echo "<!-- Debug: Ürün Listesi -->";
echo "<!-- Toplam Ürün: " . count($products) . " -->";
foreach ($products as $product) {
    echo "<!-- Ürün: " . $product['name'] . " (ID: " . $product['id'] . ") -->";
}

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=orders');
    }
    
    // Get form data
    $customerId = post('customer_id');
    $orderDate = post('order_date');
    $orderNote = post('order_note');
    $status = post('status');
    $statusNote = post('status_note');
    $items = post('items');
    $useBalance = post('use_balance') ? true : false;
    $addVat = post('add_vat') ? true : false;
    $vatRate = post('vat_rate') ? floatval(post('vat_rate')) : 18;
    
    // Validate form data
    $errors = [];
    
    if (empty($customerId) || $customerId <= 0) {
        $errors[] = t('orders_customer_required', 'Müşteri seçimi gereklidir.');
    }
    
    if (empty($orderDate)) {
        $errors[] = t('orders_date_required', 'Sipariş tarihi gereklidir.');
    }
    
    if (empty($items) || !is_array($items)) {
        $errors[] = t('orders_items_required', 'En az bir ürün eklemelisiniz.');
    }
    
    // Check if customer exists
    $db->query("SELECT * FROM customers WHERE id = :id");
    $db->bind(':id', $customerId);
    $customer = $db->single();
    
    if (!$customer) {
        $errors[] = t('orders_customer_not_found', 'Seçilen müşteri bulunamadı.');
    }
    
    // Validate items and check stock
    $validItems = [];
    $subtotal = 0;
    
    if (!empty($items)) {
        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
                continue;
            }
            
            // Get product info
            $db->query("
                SELECT p.*, 
                       COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity WHEN sm.type = 'out' THEN -sm.quantity ELSE sm.quantity END), 0) as stock_level
                FROM products p
                LEFT JOIN stock_movements sm ON p.id = sm.product_id
                WHERE p.id = :id
                GROUP BY p.id
            ");
            $db->bind(':id', $item['product_id']);
            $product = $db->single();
            
            if (!$product) {
                $errors[] = t('orders_product_not_found', 'Ürün bulunamadı: ID') . ' ' . $item['product_id'];
                continue;
            }
            
            // Check stock
            if ($product['stock_level'] < $item['quantity']) {
                $errors[] = $product['name'] . t('orders_insufficient_stock_for_product', ' için yetersiz stok! Mevcut:') . ' ' . $product['stock_level'];
                continue;
            }
            
            // Calculate item total
            $unitPrice = !empty($item['unit_price']) ? floatval($item['unit_price']) : $product['price'];
            $itemTotal = $item['quantity'] * $unitPrice;
            $subtotal += $itemTotal;
            
            $validItems[] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'total' => $itemTotal
            ];
        }
    }
    
    if (empty($validItems)) {
        $errors[] = t('orders_invalid_product', 'Geçerli ürün bulunamadı.');
    }
    
    // Calculate discount from customer tag
    $discountAmount = 0;
    $appliedTagId = null;
    if ($discountPercentage > 0) {
        $discountAmount = $subtotal * ($discountPercentage / 100);
        $appliedTagId = $customerTag['id'];
    }
    
    // Calculate totals (subtotal - discount + VAT)
    $subtotalAfterDiscount = $subtotal - $discountAmount;
    $vatAmount = $addVat ? ($subtotalAfterDiscount * $vatRate / 100) : 0;
    $totalAmount = $subtotalAfterDiscount + $vatAmount;
    
    // Get customer balance
    $db->query("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END), 0) as balance
        FROM transactions 
        WHERE customer_id = :customer_id
    ");
    $db->bind(':customer_id', $customerId);
    $balanceResult = $db->single();
    $customerBalance = $balanceResult['balance'];
    
    // Get customer tags and calculate discount
    $db->query("
        SELECT ct.* FROM customer_tags ct
        INNER JOIN customer_tag_relations ctr ON ct.id = ctr.tag_id
        WHERE ctr.customer_id = :customer_id AND ct.is_active = 1
        ORDER BY ct.discount_percentage DESC
        LIMIT 1
    ");
    $db->bind(':customer_id', $customerId);
    $customerTag = $db->single();
    $discountPercentage = $customerTag ? floatval($customerTag['discount_percentage']) : 0;
    
    // Calculate payment from balance
    $balanceUsed = 0;
    $remainingAmount = $totalAmount;
    
    if ($useBalance && $customerBalance > 0) {
        $balanceUsed = min($customerBalance, $totalAmount);
        $remainingAmount = $totalAmount - $balanceUsed;
    }
    
    if (empty($errors)) {
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert order
            $db->query("INSERT INTO orders (customer_id, order_date, status, total_amount, vat_rate, vat_amount, grand_total, notes, discount_type, discount_value, discount_amount, applied_tag_id) 
                       VALUES (:customer_id, :order_date, :status, :total_amount, :vat_rate, :vat_amount, :grand_total, :notes, :discount_type, :discount_value, :discount_amount, :applied_tag_id)");
            $db->bind(':customer_id', $customerId);
            $db->bind(':order_date', $orderDate);
            $db->bind(':status', $status);
            $db->bind(':total_amount', $subtotal);
            $db->bind(':vat_rate', $addVat ? $vatRate : 0);
            $db->bind(':vat_amount', $vatAmount);
            $db->bind(':grand_total', $totalAmount);
            $db->bind(':notes', $orderNote . ' - ' . $statusNote);
            $db->bind(':discount_type', $discountPercentage > 0 ? 'percentage' : 'none');
            $db->bind(':discount_value', $discountPercentage);
            $db->bind(':discount_amount', $discountAmount);
            $db->bind(':applied_tag_id', $appliedTagId);
            $db->execute();
            
            $orderId = $db->lastInsertId();
            
            // Log activity
            logActivity('add_order', 'order', $orderId, null, [
                'customer_id' => $customerId,
                'order_date' => $orderDate,
                'status' => $status,
                'total_amount' => $subtotal,
                'grand_total' => $totalAmount,
                'vat_rate' => $addVat ? $vatRate : 0,
                'vat_amount' => $vatAmount
            ], "Yeni sipariş oluşturuldu: #{$orderId}");
            
            // Insert order items and update stock
            foreach ($validItems as $item) {
                // Insert order item
                $db->query("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) 
                           VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price)");
                $db->bind(':order_id', $orderId);
                $db->bind(':product_id', $item['product_id']);
                $db->bind(':quantity', $item['quantity']);
                $db->bind(':unit_price', $item['unit_price']);
                $db->bind(':total_price', $item['total']);
                $db->execute();
                
                // Add stock out movement
                $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes) 
                           VALUES (:product_id, 'out', :quantity, 'piece', :date, :notes)");
                $db->bind(':product_id', $item['product_id']);
                $db->bind(':quantity', $item['quantity']);
                $db->bind(':date', $orderDate);
                $db->bind(':notes', t('orders_order_note_prefix', 'Sipariş') . ' #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                $db->execute();
            }
            
            // Create transaction records
            if ($balanceUsed > 0) {
                // Payment from balance
                $db->query("INSERT INTO transactions (customer_id, type, amount, date, notes) 
                           VALUES (:customer_id, 'payment', :amount, :date, :notes)");
                $db->bind(':customer_id', $customerId);
                $db->bind(':amount', $balanceUsed);
                $db->bind(':date', $orderDate);
                $db->bind(':notes', t('orders_payment_from_balance', 'Sipariş ödemesi - Bakiyeden'));
                $db->execute();
            }
            
            if ($remainingAmount > 0) {
                // Debt for remaining amount
                $db->query("INSERT INTO transactions (customer_id, type, amount, date, notes) 
                           VALUES (:customer_id, 'debt', :amount, :date, :notes)");
                $db->bind(':customer_id', $customerId);
                $db->bind(':amount', $remainingAmount);
                $db->bind(':date', $orderDate);
                $db->bind(':notes', t('orders_debt_note_prefix', 'Sipariş borcu:') . ' #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                $db->execute();
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', t('orders_create_success', 'Sipariş başarıyla oluşturuldu.'));
            
            // Redirect to order view
            redirect('index.php?module=orders&action=view&id=' . $orderId);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            $errors[] = t('orders_create_error', 'Sipariş oluşturulurken bir hata oluştu:') . ' ' . $e->getMessage();
        }
    }
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('orders_add_title', 'Sipariş Ekle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=orders'); ?>"><?php echo t('orders_title', 'Siparişler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('orders_add_title', 'Sipariş Ekle'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                <a href="<?php echo url('index.php?module=orders'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> <?php echo t('orders_back', 'Geri Dön'); ?>
                </a>
                <button type="submit" form="orderForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo t('orders_save', 'Kaydet'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Required CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Required JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Display Errors -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Add Order Form -->
<form action="<?php echo url('index.php?module=orders&action=add'); ?>" method="post" id="orderForm">
    <?php echo csrfField(); ?>
    
    <div class="row">
        <!-- Order Information -->
        <div class="col-md-<?php echo isset($_COOKIE['help_panel_collapsed']) ? '12' : '9'; ?>">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('orders_order_info', 'Sipariş Bilgileri'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label required"><?php echo t('orders_customer_label', 'Müşteri'); ?></label>
                                <select class="form-select select2" id="customer_id" name="customer_id" required>
                                    <option value=""><?php echo t('orders_select', 'Seçiniz'); ?></option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo ($preselectedCustomerId == $customer['id'] || post('customer_id') == $customer['id']) ? 'selected' : ''; ?>
                                            data-balance="0">
                                        <?php echo e($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        <?php if (!empty($customer['company'])): ?>
                                            (<?php echo e($customer['company']); ?>)
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="order_date" class="form-label required"><?php echo t('orders_order_date_label', 'Sipariş Tarihi'); ?></label>
                                <input type="date" class="form-control" id="order_date" name="order_date" 
                                       value="<?php echo post('order_date', date('Y-m-d')); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label required"><?php echo t('orders_order_status_label', 'Sipariş Durumu'); ?></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="pending"><?php echo t('orders_status_pending', 'Bekleyen'); ?></option>
                                    <option value="processing"><?php echo t('orders_status_processing', 'İşlemde'); ?></option>
                                    <option value="completed"><?php echo t('orders_status_completed', 'Tamamlandı'); ?></option>
                                    <option value="cancelled"><?php echo t('orders_status_cancelled', 'İptal'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status_note" class="form-label"><?php echo t('orders_status_note_label', 'Durum Notu'); ?></label>
                                <textarea class="form-control" id="status_note" name="status_note" rows="1" 
                                          placeholder="<?php echo t('orders_status_note_placeholder', 'Durum hakkında not ekleyin...'); ?>"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="order_note" class="form-label"><?php echo t('orders_order_note_label', 'Sipariş Notu'); ?></label>
                        <textarea class="form-control" id="order_note" name="order_note" rows="2"><?php echo post('order_note', ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="card mt-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><?php echo t('orders_order_items', 'Sipariş Kalemleri'); ?></h5>
                        <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                            <i class="fas fa-plus"></i> <?php echo t('orders_add_item', 'Kalem Ekle'); ?>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="itemsTable">
                            <thead>
                                <tr>
                                    <th width="40%"><?php echo t('orders_product', 'Ürün'); ?></th>
                                    <th width="120"><?php echo t('orders_current_stock', 'Mevcut Stok'); ?></th>
                                    <th width="120"><?php echo t('orders_quantity', 'Miktar'); ?></th>
                                    <th width="120"><?php echo t('orders_unit_price', 'Birim Fiyat'); ?></th>
                                    <th width="150"><?php echo t('orders_total', 'Toplam'); ?></th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsContainer">
                                <!-- Items will be added here -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong><?php echo t('orders_subtotal', 'Ara Toplam:'); ?></strong></td>
                                    <td colspan="2">
                                        <strong id="subtotalDisplay">0,00 ₺</strong>
                                        <input type="hidden" id="subtotal" name="subtotal" value="0">
                                    </td>
                                </tr>
                                <tr id="vatRow" style="display: none;">
                                    <td colspan="4" class="text-end">
                                        <div class="form-check d-inline-block me-2">
                                            <input type="checkbox" class="form-check-input" id="add_vat" name="add_vat">
                                            <label class="form-check-label" for="add_vat">
                                                <?php echo t('orders_add_vat', 'KDV Ekle'); ?>
                                            </label>
                                        </div>
                                        <div class="d-inline-block" id="vatRateContainer" style="display: none;">
                                            <div class="input-group input-group-sm" style="width: 100px;">
                                                <input type="number" class="form-control" id="vat_rate" name="vat_rate" value="18" min="0" max="100">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td colspan="2">
                                        <strong id="vatDisplay">0,00 ₺</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong><?php echo t('orders_grand_total', 'Genel Toplam:'); ?></strong></td>
                                    <td colspan="2">
                                        <strong id="totalDisplay" class="text-primary">0,00 ₺</strong>
                                        <input type="hidden" id="total" name="total" value="0">
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Side Panel -->
        <div class="col-md-3" id="helpPanel" <?php echo isset($_COOKIE['help_panel_collapsed']) ? 'style="display:none;"' : ''; ?>>
            <!-- Customer Balance Info -->
            <div class="card" id="customerBalanceInfo" style="display: none;">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('orders_customer_balance_info', 'Müşteri Bakiye Bilgisi'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span><?php echo t('orders_total_debt', 'Toplam Borç:'); ?></span>
                        <strong id="totalDebt" class="text-danger">0,00 ₺</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span><?php echo t('orders_total_payment', 'Toplam Ödeme:'); ?></span>
                        <strong id="totalPayment" class="text-success">0,00 ₺</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span><?php echo t('orders_net_status', 'Net Durum:'); ?></span>
                        <strong id="netBalance">0,00 ₺</strong>
                    </div>
                    
                    <div class="form-check mt-3" id="useBalanceContainer" style="display: none;">
                        <input type="checkbox" class="form-check-input" id="use_balance" name="use_balance">
                        <label class="form-check-label" for="use_balance">
                            <?php echo t('orders_use_balance', 'Bakiyeden düş'); ?>
                        </label>
                    </div>
                    
                    <div id="balanceWarning" class="alert alert-warning mt-3" style="display: none;">
                        <small><i class="fas fa-exclamation-triangle"></i> <span id="balanceWarningText"></span></small>
                    </div>
                </div>
            </div>
            
            <!-- Payment Summary -->
            <div class="card mt-4" id="paymentSummary" style="display: none;">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('orders_payment_summary', 'Ödeme Özeti'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span><?php echo t('orders_order_amount_summary', 'Sipariş Tutarı:'); ?></span>
                        <strong id="orderTotalSummary">0,00 ₺</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2" id="balanceUsedRow" style="display: none;">
                        <span><?php echo t('orders_from_balance', 'Bakiyeden:'); ?></span>
                        <strong id="balanceUsed" class="text-success">-0,00 ₺</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span><?php echo t('orders_debt_to_write', 'Borç Yazılacak:'); ?></span>
                        <strong id="debtAmount" class="text-danger">0,00 ₺</strong>
                    </div>
                </div>
            </div>
            
            <!-- Help Box -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><?php echo t('orders_help_tips', 'Yardım & İpuçları'); ?></h5>
                    <button type="button" class="btn btn-sm btn-link text-muted" id="toggleHelpPanel">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <ul class="mb-0 small">
                            <li><?php echo t('orders_help_tip1', 'Ürün seçtiğinizde mevcut stok otomatik gösterilir'); ?></li>
                            <li><?php echo t('orders_help_tip2', 'Birim fiyat varsayılan olarak ürün fiyatı gelir ama değiştirebilirsiniz'); ?></li>
                            <li><?php echo t('orders_help_tip3', 'Müşterinin bakiyesi varsa otomatik düşülebilir'); ?></li>
                            <li><?php echo t('orders_help_tip4', 'Stokta olmayan ürünler eklenemez'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Item Template -->
<script type="text/template" id="itemTemplate">
    <tr class="order-item">
        <td style="min-width: 300px;">
            <div class="input-group">
                <input type="text" class="form-control product-search" placeholder="<?php echo t('orders_search_product', 'Ürün Ara (İsim, SKU, Barkod)'); ?>">
                <select class="form-select product-select" name="items[{{index}}][product_id]" required>
                    <option value=""><?php echo t('orders_select_product', 'Ürün Seçin'); ?></option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" 
                            data-price="<?php echo $product['price']; ?>"
                            data-stock="<?php echo $product['stock_level']; ?>"
                            data-sku="<?php echo e($product['sku']); ?>"
                            data-barcode="<?php echo e($product['barcode']); ?>"
                            data-category="<?php echo e($product['category_name']); ?>"
                            data-search="<?php echo e($product['name'] . ' ' . $product['sku'] . ' ' . $product['barcode']); ?>">
                        <?php echo e($product['name']); ?>
                        <?php if (!empty($product['sku'])): ?>
                            (<?php echo e($product['sku']); ?>)
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </td>
        <td class="text-center stock-display">-</td>
        <td>
            <input type="number" class="form-control quantity-input" name="items[{{index}}][quantity]" 
                   min="0.01" step="0.01" required>
        </td>
        <td>
            <input type="number" class="form-control unit-price-input" name="items[{{index}}][unit_price]" 
                   min="0" step="0.01" required>
        </td>
        <td class="text-end">
            <span class="item-total">0,00 ₺</span>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger remove-item">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</script>

<script>
    $(document).ready(function() {
        let itemIndex = 0;
        
        // Initialize Select2 for customer selection
        $('#customer_id').select2({
            theme: 'bootstrap-5',
            placeholder: '<?php echo t('orders_select_customer', 'Müşteri seçin veya arayın'); ?>',
            width: '100%',
            dropdownParent: $('#customer_id').parent()
        });

        // Product search functionality
        $(document).on('input', '.product-search', function() {
            const searchText = $(this).val().toLowerCase();
            const $select = $(this).next('.product-select');
            
            $select.find('option').each(function() {
                const searchData = $(this).data('search').toLowerCase();
                if (searchData.includes(searchText)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Add new item
        $('#addItemBtn').on('click', function() {
            const template = $('#itemTemplate').html();
            const html = template.replace(/{{index}}/g, itemIndex);
            $('#itemsContainer').append(html);
            itemIndex++;
            updateCalculations();
        });

        // Remove item
        $(document).on('click', '.remove-item', function() {
            $(this).closest('tr').remove();
            updateCalculations();
        });
        
        // Product selection change
        $(document).on('change', '.product-select', function() {
            const $row = $(this).closest('tr');
            const $option = $(this).find('option:selected');
            const price = $option.data('price') || 0;
            const stock = $option.data('stock') || 0;
            
            $row.find('.stock-display').text(stock);
            $row.find('.unit-price-input').val(price);
            
            // Check stock availability
            const quantity = parseFloat($row.find('.quantity-input').val()) || 0;
            if (quantity > stock) {
                $row.find('.quantity-input').addClass('is-invalid');
            } else {
                $row.find('.quantity-input').removeClass('is-invalid');
            }
            
            updateItemTotal($row);
            updateCalculations();
        });
        
        // Quantity change
        $(document).on('input', '.quantity-input', function() {
            const $row = $(this).closest('tr');
            const stock = parseFloat($row.find('.stock-display').text()) || 0;
            const quantity = parseFloat($(this).val()) || 0;
            
            if (quantity > stock) {
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
            
            updateItemTotal($row);
            updateCalculations();
        });
        
        // Price change
        $(document).on('input', '.unit-price-input', function() {
            const $row = $(this).closest('tr');
            updateItemTotal($row);
            updateCalculations();
        });
        
        // VAT checkbox
        $('#add_vat').on('change', function() {
            if ($(this).is(':checked')) {
                $('#vatRateContainer').show();
                $('#vatRow').show();
            } else {
                $('#vatRateContainer').hide();
                $('#vatDisplay').text('0,00 ₺');
            }
            updateCalculations();
        });
        
        // VAT rate change
        $('#vat_rate').on('input', function() {
            updateCalculations();
        });
        
        // Use balance checkbox
        $('#use_balance').on('change', function() {
            updatePaymentSummary();
        });
        
        // Update item total
        function updateItemTotal($row) {
            const quantity = parseFloat($row.find('.quantity-input').val()) || 0;
            const unitPrice = parseFloat($row.find('.unit-price-input').val()) || 0;
            const total = quantity * unitPrice;
            
            $row.find('.item-total').text(formatMoney(total));
        }
        
        // Update calculations
        function updateCalculations() {
            let subtotal = 0;
            
            $('.order-item').each(function() {
                const quantity = parseFloat($(this).find('.quantity-input').val()) || 0;
                const unitPrice = parseFloat($(this).find('.unit-price-input').val()) || 0;
                subtotal += quantity * unitPrice;
            });
            
            $('#subtotal').val(subtotal);
            $('#subtotalDisplay').text(formatMoney(subtotal));
            
            // Show VAT row if there are items
            if ($('.order-item').length > 0) {
                $('#vatRow').show();
            } else {
                $('#vatRow').hide();
            }
            
            // Calculate VAT
            let vatAmount = 0;
            if ($('#add_vat').is(':checked')) {
                const vatRate = parseFloat($('#vat_rate').val()) || 0;
                vatAmount = subtotal * vatRate / 100;
            }
            $('#vatDisplay').text(formatMoney(vatAmount));
            
            // Calculate total
            const total = subtotal + vatAmount;
            $('#total').val(total);
            $('#totalDisplay').text(formatMoney(total));
            
            // Update payment summary
            updatePaymentSummary();
        }
        
        // Update payment summary
        function updatePaymentSummary() {
            const total = parseFloat($('#total').val()) || 0;
            const customerBalance = parseFloat($('#customer_id option:selected').data('balance')) || 0;
            const useBalance = $('#use_balance').is(':checked');
            
            if (total > 0 && $('#customer_id').val()) {
                $('#paymentSummary').show();
                $('#orderTotalSummary').text(formatMoney(total));
                
                let balanceUsed = 0;
                let debtAmount = total;
                
                if (useBalance && customerBalance > 0) {
                    balanceUsed = Math.min(customerBalance, total);
                    debtAmount = total - balanceUsed;
                    
                    $('#balanceUsedRow').show();
                    $('#balanceUsed').text('-' + formatMoney(balanceUsed));
                } else {
                    $('#balanceUsedRow').hide();
                }
                
                $('#debtAmount').text(formatMoney(debtAmount));
            } else {
                $('#paymentSummary').hide();
            }
        }
        
        // Format money
        function formatMoney(amount) {
            return new Intl.NumberFormat('tr-TR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount) + ' ₺';
        }
        
        // Form validation
        $('#orderForm').on('submit', function(e) {
            let hasError = false;
            
            // Check if there are items
            if ($('.order-item').length === 0) {
                alert('<?php echo t('orders_add_at_least_one_product', 'En az bir ürün eklemelisiniz!'); ?>');
                e.preventDefault();
                return false;
            }
            
            // Check stock availability
            $('.order-item').each(function() {
                const quantity = parseFloat($(this).find('.quantity-input').val()) || 0;
                const stock = parseFloat($(this).find('.stock-display').text()) || 0;
                
                if (quantity > stock) {
                    hasError = true;
                }
            });
            
            if (hasError) {
                alert('<?php echo t('orders_insufficient_stock', 'Bazı ürünlerde stok yetersiz!'); ?>');
                e.preventDefault();
                return false;
            }
        });
        
        // Add first item on load
        $('#addItemBtn').click();
        
        // Trigger customer change if preselected
        if ($('#customer_id').val()) {
            $('#customer_id').trigger('change');
        }

        // Toggle help panel
        $('#toggleHelpPanel').on('click', function() {
            const $helpPanel = $('#helpPanel');
            const $mainContent = $helpPanel.prev();
            
            if ($helpPanel.is(':visible')) {
                $helpPanel.hide();
                $mainContent.removeClass('col-md-9').addClass('col-md-12');
                document.cookie = "help_panel_collapsed=1; path=/; max-age=31536000";
            } else {
                $helpPanel.show();
                $mainContent.removeClass('col-md-12').addClass('col-md-9');
                document.cookie = "help_panel_collapsed=; path=/; max-age=0";
            }
        });
    });
</script>

<!-- Debug Output -->
<script>
console.log('Sayfa yüklendi');
console.log('PHP\'den gelen ürün sayısı:', <?php echo count($products); ?>);
</script>

<style>
.select2-container {
    z-index: 9999;
}
.select2-dropdown {
    z-index: 9999;
}
.select2-container--bootstrap-5 .select2-selection {
    min-height: 38px;
}
.select2-container--bootstrap-5 .select2-selection--single {
    padding: 0.375rem 0.75rem;
}
.product-search {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    border-bottom: none;
}
.product-select {
    border-top-left-radius: 0;
    border-top-right-radius: 0;
}
.btn-group {
    gap: 0.5rem;
}
</style>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>