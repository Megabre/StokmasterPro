<?php
/**
 * Megabre StokMaster Pro
 * Edit Order
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

// Get order ID from URL
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId <= 0) {
    Session::setFlash('error', 'Geçersiz sipariş ID\'si.');
    redirect('index.php?module=orders');
}

// Get order data
$db->query("SELECT * FROM orders WHERE id = :id");
$db->bind(':id', $orderId);
$order = $db->single();

if (!$order) {
    Session::setFlash('error', 'Sipariş bulunamadı.');
    redirect('index.php?module=orders');
}

// Check if order can be edited
if ($order['status'] == 'completed' || $order['status'] == 'cancelled') {
    Session::setFlash('error', 'Tamamlanan veya iptal edilen siparişler düzenlenemez.');
    redirect('index.php?module=orders&action=view&id=' . $orderId);
}

// Get order items with stock movements
$db->query("
    SELECT oi.*, p.name as product_name, p.sku, p.barcode, p.price as default_price,
           sm.id as movement_id, sm.quantity as movement_quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN stock_movements sm ON sm.product_id = oi.product_id 
        AND sm.type = 'out' 
        AND sm.notes = CONCAT('Sipariş #', LPAD(:order_id, 6, '0'))
    WHERE oi.order_id = :order_id2
");
$db->bind(':order_id', $orderId);
$db->bind(':order_id2', $orderId);
$orderItems = $db->resultSet();

// Get all customers
$db->query("SELECT id, first_name, last_name, phone, company FROM customers ORDER BY first_name ASC, last_name ASC");
$customers = $db->resultSet();

// Get all products with stock info
$db->query("
    SELECT p.*, 
           COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity WHEN sm.type = 'out' THEN -sm.quantity ELSE sm.quantity END), 0) as stock_level
    FROM products p
    LEFT JOIN stock_movements sm ON p.id = sm.product_id
    GROUP BY p.id
    ORDER BY p.name ASC
");
$products = $db->resultSet();

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=orders&action=edit&id=' . $orderId);
    }
    
    // Get form data
    $customerId = post('customer_id');
    $orderDate = post('order_date');
    $orderNote = post('order_note');
    $items = post('items');
    $addVat = post('add_vat') ? true : false;
    $vatRate = post('vat_rate') ? floatval(post('vat_rate')) : 18;
    
    // Validate form data
    $errors = [];
    
    if (empty($customerId) || $customerId <= 0) {
        $errors[] = 'Müşteri seçimi gereklidir.';
    }
    
    if (empty($orderDate)) {
        $errors[] = 'Sipariş tarihi gereklidir.';
    }
    
    if (empty($items) || !is_array($items)) {
        $errors[] = 'En az bir ürün eklemelisiniz.';
    }
    
    // Check if customer exists
    $db->query("SELECT * FROM customers WHERE id = :id");
    $db->bind(':id', $customerId);
    $customer = $db->single();
    
    if (!$customer) {
        $errors[] = 'Seçilen müşteri bulunamadı.';
    }
    
    // Create map of existing items for comparison
    $existingItems = [];
    foreach ($orderItems as $item) {
        $existingItems[$item['product_id']] = [
            'quantity' => $item['quantity'],
            'movement_id' => $item['movement_id']
        ];
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
                $errors[] = t('orders_product_not_found_error', 'Ürün bulunamadı: ID') . ' ' . $item['product_id'];
                continue;
            }
            
            // Calculate stock difference
            $oldQuantity = isset($existingItems[$item['product_id']]) ? $existingItems[$item['product_id']]['quantity'] : 0;
            $quantityDiff = $item['quantity'] - $oldQuantity;
            
            // Check stock for new or increased quantity
            if ($quantityDiff > 0 && $product['stock_level'] < $quantityDiff) {
                $errors[] = $product['name'] . ' için yetersiz stok! Mevcut: ' . $product['stock_level'] . ', İhtiyaç: ' . $quantityDiff;
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
                'total' => $itemTotal,
                'old_quantity' => $oldQuantity
            ];
        }
    }
    
    if (empty($validItems)) {
        $errors[] = 'Geçerli ürün bulunamadı.';
    }
    
    // Calculate totals
    $vatAmount = $addVat ? ($subtotal * $vatRate / 100) : 0;
    $totalAmount = $subtotal + $vatAmount;
    
    if (empty($errors)) {
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Prepare old data for logging
            $oldData = [
                'customer_id' => $order['customer_id'],
                'order_date' => $order['order_date'],
                'status' => $order['status'],
                'total_amount' => $order['total_amount'],
                'vat_rate' => $order['vat_rate'] ?? 0,
                'vat_amount' => $order['vat_amount'] ?? 0,
                'grand_total' => $order['grand_total'],
                'notes' => $order['notes'] ?? ''
            ];
            
            // Prepare new data for logging
            $newData = [
                'customer_id' => $customerId,
                'order_date' => $orderDate,
                'status' => post('status'),
                'total_amount' => $subtotal,
                'vat_rate' => $addVat ? $vatRate : 0,
                'vat_amount' => $vatAmount,
                'grand_total' => $totalAmount,
                'notes' => $orderNote ?? ''
            ];
            
            // Update order
            $db->query("UPDATE orders SET 
                        customer_id = :customer_id, 
                        order_date = :order_date,
                        status = :status,
                        total_amount = :total_amount, 
                        vat_rate = :vat_rate, 
                        vat_amount = :vat_amount, 
                        grand_total = :grand_total,
                        notes = :notes,
                        updated_at = NOW()
                        WHERE id = :id");
            $db->bind(':customer_id', $customerId);
            $db->bind(':order_date', $orderDate);
            $db->bind(':status', post('status'));
            $db->bind(':total_amount', $subtotal);
            $db->bind(':vat_rate', $addVat ? $vatRate : 0);
            $db->bind(':vat_amount', $vatAmount);
            $db->bind(':grand_total', $totalAmount);
            $db->bind(':notes', $orderNote);
            $db->bind(':id', $orderId);
            $db->execute();
            
            // Log activity with detailed changes
            logActivity('update_order', 'order', $orderId, $oldData, $newData, "Sipariş #{$orderId} güncellendi");
            
            // Delete existing order items
            $db->query("DELETE FROM order_items WHERE order_id = :order_id");
            $db->bind(':order_id', $orderId);
            $db->execute();
            
            // Delete existing stock movements for this order
            $db->query("DELETE FROM stock_movements WHERE type = 'out' AND notes = :notes");
            $db->bind(':notes', 'Sipariş #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
            $db->execute();
            
            // Insert new order items and stock movements
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
                $db->bind(':notes', 'Sipariş #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                $db->execute();
            }
            
            // Update transaction amounts
            $db->query("UPDATE transactions SET 
                        amount = :amount,
                        date = :date
                        WHERE order_id = :order_id AND type = 'debt'");
            $db->bind(':amount', $totalAmount);
            $db->bind(':date', $orderDate);
            $db->bind(':order_id', $orderId);
            $db->execute();
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', 'Sipariş başarıyla güncellendi.');
            
            // Redirect to order view
            redirect('index.php?module=orders&action=view&id=' . $orderId);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            $errors[] = 'Sipariş güncellenirken bir hata oluştu: ' . $e->getMessage();
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
            <h3 class="page-title">Sipariş Düzenle #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=orders'); ?>">Siparişler</a></li>
                <li class="breadcrumb-item active">Sipariş Düzenle</li>
            </ul>
        </div>
    </div>
</div>

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

<!-- Edit Order Form -->
<form action="<?php echo url('index.php?module=orders&action=edit&id=' . $orderId); ?>" method="post" id="orderForm">
    <?php echo csrfField(); ?>
    
    <div class="row">
        <!-- Order Information -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Sipariş Bilgileri</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label required">Müşteri</label>
                                <select class="form-select select2" id="customer_id" name="customer_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo $order['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
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
                                <label for="order_date" class="form-label required">Sipariş Tarihi</label>
                                <input type="date" class="form-control" id="order_date" name="order_date" 
                                       value="<?php echo $order['order_date']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label required">Sipariş Durumu</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Bekleyen</option>
                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>İşlemde</option>
                                    <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="order_note" class="form-label">Sipariş Notu</label>
                                <textarea class="form-control" id="order_note" name="order_note" rows="2"><?php echo e($order['notes']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="card mt-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Sipariş Kalemleri</h5>
                        <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                            <i class="fas fa-plus"></i> Kalem Ekle
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="itemsTable">
                            <thead>
                                <tr>
                                    <th width="40%">Ürün</th>
                                    <th width="120">Mevcut Stok</th>
                                    <th width="120">Miktar</th>
                                    <th width="120">Birim Fiyat</th>
                                    <th width="150">Toplam</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsContainer">
                                <?php 
                                $subtotal = 0;
                                foreach ($orderItems as $index => $item): 
                                    $itemTotal = $item['quantity'] * $item['unit_price'];
                                    $subtotal += $itemTotal;
                                ?>
                                <tr class="order-item">
                                    <td>
                                        <select class="form-select product-select" name="items[<?php echo $index; ?>][product_id]" required>
                                            <option value="">Ürün Seçin</option>
                                            <?php foreach ($products as $product): ?>
                                            <option value="<?php echo $product['id']; ?>" 
                                                    data-price="<?php echo $product['price']; ?>"
                                                    data-stock="<?php echo $product['stock_level']; ?>"
                                                    <?php echo $item['product_id'] == $product['id'] ? 'selected' : ''; ?>>
                                                <?php echo e($product['name']); ?>
                                                <?php if (!empty($product['sku'])): ?>
                                                    (<?php echo e($product['sku']); ?>)
                                                <?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="text-center stock-display">
                                        <?php 
                                        $productStock = 0;
                                        foreach ($products as $product) {
                                            if ($product['id'] == $item['product_id']) {
                                                $productStock = $product['stock_level'];
                                                break;
                                            }
                                        }
                                        echo $productStock;
                                        ?>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control quantity-input" 
                                               name="items[<?php echo $index; ?>][quantity]" 
                                               value="<?php echo $item['quantity']; ?>"
                                               min="0.01" step="0.01" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control unit-price-input" 
                                               name="items[<?php echo $index; ?>][unit_price]" 
                                               value="<?php echo $item['unit_price']; ?>"
                                               min="0" step="0.01" required>
                                    </td>
                                    <td class="text-end">
                                        <span class="item-total"><?php echo formatPrice($itemTotal); ?> ₺</span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-danger remove-item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Ara Toplam:</strong></td>
                                    <td colspan="2">
                                        <strong id="subtotalDisplay"><?php echo formatPrice($subtotal); ?> ₺</strong>
                                        <input type="hidden" id="subtotal" name="subtotal" value="<?php echo $subtotal; ?>">
                                    </td>
                                </tr>
                                <tr id="vatRow">
                                    <td colspan="4" class="text-end">
                                        <div class="form-check d-inline-block me-2">
                                            <input type="checkbox" class="form-check-input" id="add_vat" name="add_vat" 
                                                   <?php echo $order['vat_rate'] > 0 ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="add_vat">
                                                KDV Ekle
                                            </label>
                                        </div>
                                        <div class="d-inline-block" id="vatRateContainer" style="<?php echo $order['vat_rate'] > 0 ? '' : 'display: none;'; ?>">
                                            <div class="input-group input-group-sm" style="width: 100px;">
                                                <input type="number" class="form-control" id="vat_rate" name="vat_rate" 
                                                       value="<?php echo $order['vat_rate'] ?: 18; ?>" min="0" max="100">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td colspan="2">
                                        <strong id="vatDisplay"><?php echo formatPrice($order['vat_amount']); ?> ₺</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Genel Toplam:</strong></td>
                                    <td colspan="2">
                                        <strong id="totalDisplay" class="text-primary"><?php echo formatPrice($order['total_amount']); ?> ₺</strong>
                                        <input type="hidden" id="total" name="total" value="<?php echo $order['total_amount']; ?>">
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Order Status -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Sipariş Durumu</h5>
                </div>
                <div class="card-body">
                    <?php
                    $statusLabels = [
                        'pending' => ['class' => 'warning', 'text' => 'Bekleyen'],
                        'processing' => ['class' => 'info', 'text' => 'İşlemde'],
                        'completed' => ['class' => 'success', 'text' => 'Tamamlandı'],
                        'cancelled' => ['class' => 'danger', 'text' => 'İptal']
                    ];
                    $status = $statusLabels[$order['status']] ?? ['class' => 'secondary', 'text' => 'Bilinmeyen'];
                    ?>
                    <div class="text-center">
                        <span class="badge bg-<?php echo $status['class']; ?> fs-5">
                            <?php echo $status['text']; ?>
                        </span>
                    </div>
                    <hr>
                    <p class="text-muted mb-0">
                        <i class="fas fa-info-circle"></i> Sipariş durumu ayrı bir işlem olarak güncellenir.
                    </p>
                </div>
            </div>

            <!-- Form Controls -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <a href="<?php echo url('index.php?module=orders&action=view&id=' . $orderId); ?>" class="btn btn-secondary w-100">
                                <i class="fas fa-arrow-left"></i> İptal
                            </a>
                        </div>
                        <div class="col-6">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> Güncelle
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Item Template -->
<script type="text/template" id="itemTemplate">
    <tr class="order-item">
        <td>
            <select class="form-select product-select" name="items[{{index}}][product_id]" required>
                <option value="">Ürün Seçin</option>
                <?php foreach ($products as $product): ?>
                <option value="<?php echo $product['id']; ?>" 
                        data-price="<?php echo $product['price']; ?>"
                        data-stock="<?php echo $product['stock_level']; ?>">
                    <?php echo e($product['name']); ?>
                    <?php if (!empty($product['sku'])): ?>
                        (<?php echo e($product['sku']); ?>)
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
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
    let itemIndex = <?php echo count($orderItems); ?>;
    
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5'
    });
    
    // Add new item
    $('#addItemBtn').on('click', function() {
        const template = $('#itemTemplate').html();
        const html = template.replace(/{{index}}/g, itemIndex);
        $('#itemsContainer').append(html);
        
        // Initialize select2 for new item
        $('.order-item:last .product-select').select2({
            theme: 'bootstrap-5',
            placeholder: '<?php echo t('orders_select_product_placeholder', 'Ürün seçin'); ?>'
        });
        
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
        const stock = $option.data('stock') || 0;
        const price = $option.data('price') || 0;
        
        $row.find('.stock-display').text(stock);
        if (!$row.find('.unit-price-input').val()) {
            $row.find('.unit-price-input').val(price);
        }
        
        updateItemTotal($row);
        updateCalculations();
    });
    
    // Quantity change
    $(document).on('input', '.quantity-input', function() {
        const $row = $(this).closest('tr');
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
            alert('En az bir ürün eklemelisiniz!');
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
            alert('Bazı ürünlerde stok yetersiz!');
            e.preventDefault();
            return false;
        }
    });
    
    // Initial calculation
    updateCalculations();
});
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>