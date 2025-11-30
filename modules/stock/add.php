<?php
/**
 * Megabre StokMaster Pro
 * Add Stock Movement
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

// Get preselected product if specified
$preselectedProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Get all products
$db->query("SELECT id, name, sku, barcode FROM products ORDER BY name ASC");
$products = $db->resultSet();

// Get dynamic fields for stock
$db->query("SELECT * FROM stock_fields");
$dynamicFields = $db->resultSet();

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=stock');
    }
    
    // Get form data
    $productId = post('product_id');
    $type = post('type');
    $date = post('date');
    $quantity = post('quantity');
    $unit = post('unit');
    $notes = post('notes');
    $dynamicFieldValues = post('dynamic_fields');
    
    // Validate form data
    $errors = [];
    
    if (empty($productId) || $productId <= 0) {
        $errors[] = t('stock_product_required', 'Ürün seçimi gereklidir.');
    }
    
    if (empty($type)) {
        $errors[] = t('stock_type_required', 'Hareket tipi seçimi gereklidir.');
    } elseif (!in_array($type, ['in', 'out', 'adjustment'])) {
        $errors[] = t('stock_type_invalid', 'Geçersiz hareket tipi.');
    }
    
    if (empty($date)) {
        $errors[] = t('stock_date_required', 'Tarih gereklidir.');
    }
    
    if (empty($quantity) || !is_numeric($quantity) || floatval($quantity) <= 0) {
        $errors[] = t('stock_quantity_required', 'Miktar geçerli bir sayı olmalıdır.');
    }
    
    if (empty($unit)) {
        $errors[] = t('stock_unit_required', 'Birim seçimi gereklidir.');
    }
    
    // Check if product exists
    $db->query("SELECT * FROM products WHERE id = :id");
    $db->bind(':id', $productId);
    $product = $db->single();
    
    if (!$product) {
        $errors[] = t('stock_product_not_found', 'Seçilen ürün bulunamadı.');
    }
    
    // Check stock level for out movements
    if ($type == 'out' && !empty($product)) {
        $db->query("SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN quantity WHEN type = 'out' THEN -quantity ELSE quantity END), 0) as current_stock 
                   FROM stock_movements 
                   WHERE product_id = :product_id");
        $db->bind(':product_id', $productId);
        $stockResult = $db->single();
        $currentStock = $stockResult['current_stock'];
        
        if ($currentStock < floatval($quantity)) {
            $errors[] = t('stock_insufficient', 'Yetersiz stok! Mevcut stok:') . ' ' . number_format($currentStock, 2) . ' ' . $unit;
        }
    }
    
    // Validate dynamic fields
    if (!empty($dynamicFields)) {
        foreach ($dynamicFields as $field) {
            if ($field['is_required'] && empty($dynamicFieldValues[$field['id']])) {
                $errors[] = $field['field_name'] . ' alanı zorunludur.';
            }
        }
    }
    
    if (empty($errors)) {
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert stock movement
            $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes) 
                       VALUES (:product_id, :type, :quantity, :unit, :date, :notes)");
            $db->bind(':product_id', $productId);
            $db->bind(':type', $type);
            $db->bind(':quantity', $quantity);
            $db->bind(':unit', $unit);
            $db->bind(':date', $date);
            $db->bind(':notes', $notes);
            $db->execute();
            
            $movementId = $db->lastInsertId();
            
            // Log activity
            $typeLabels = [
                'in' => t('stock_type_in', 'Giriş'),
                'out' => t('stock_type_out', 'Çıkış'),
                'adjustment' => t('stock_type_adjustment', 'Düzeltme')
            ];
            logActivity('add_stock_movement', 'stock', $movementId, null, [
                'product_id' => $productId,
                'type' => $type,
                'quantity' => $quantity,
                'unit' => $unit,
                'date' => $date
            ], "Stok hareketi eklendi: {$product['name']} - {$typeLabels[$type]} ({$quantity} {$unit})");
            
            // Insert dynamic field values
            if (!empty($dynamicFieldValues)) {
                foreach ($dynamicFieldValues as $fieldId => $fieldValue) {
                    if (!empty($fieldValue)) {
                        $db->query("INSERT INTO stock_field_values (movement_id, field_id, field_value) 
                                   VALUES (:movement_id, :field_id, :field_value)");
                        $db->bind(':movement_id', $movementId);
                        $db->bind(':field_id', $fieldId);
                        $db->bind(':field_value', $fieldValue);
                        $db->execute();
                    }
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', t('stock_add_success', 'Stok hareketi başarıyla eklendi.'));
            
            // Redirect to stock list
            redirect('index.php?module=stock');
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            $errors[] = t('stock_add_error', 'Stok hareketi eklenirken bir hata oluştu:') . ' ' . $e->getMessage();
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
            <h3 class="page-title"><?php echo t('stock_add_title', 'Stok Hareketi Ekle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=stock'); ?>"><?php echo t('stock_title', 'Stok Yönetimi'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('stock_add_title', 'Stok Hareketi Ekle'); ?></li>
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

<!-- Add Stock Movement Form -->
<form action="<?php echo url('index.php?module=stock&action=add'); ?>" method="post" id="stockForm">
    <?php echo csrfField(); ?>
    
    <div class="row">
        <!-- Basic Information -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('stock_stock_info', 'Stok Bilgileri'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="product_id" class="form-label required"><?php echo t('stock_product_label', 'Ürün'); ?></label>
                                <select class="form-select select2" id="product_id" name="product_id" required>
                                    <option value=""><?php echo t('orders_select', 'Seçiniz'); ?></option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                            <?php echo ($preselectedProductId == $product['id'] || post('product_id') == $product['id']) ? 'selected' : ''; ?>
                                            data-sku="<?php echo e($product['sku']); ?>"
                                            data-barcode="<?php echo e($product['barcode']); ?>">
                                        <?php echo e($product['name']); ?>
                                        <?php if (!empty($product['sku'])): ?>
                                            (<?php echo e($product['sku']); ?>)
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label required"><?php echo t('stock_type_label', 'Hareket Tipi'); ?></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value=""><?php echo t('orders_select', 'Seçiniz'); ?></option>
                                    <option value="in" <?php echo post('type') == 'in' ? 'selected' : ''; ?>><?php echo t('stock_movement_in', 'Stok Girişi'); ?></option>
                                    <option value="out" <?php echo post('type') == 'out' ? 'selected' : ''; ?>><?php echo t('stock_movement_out', 'Stok Çıkışı'); ?></option>
                                    <option value="adjustment" <?php echo post('type') == 'adjustment' ? 'selected' : ''; ?>><?php echo t('stock_movement_adjustment', 'Düzeltme'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="date" class="form-label required"><?php echo t('stock_date_label', 'Tarih'); ?></label>
                                <input type="date" class="form-control" id="date" name="date" 
                                       value="<?php echo post('date', date('Y-m-d')); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="quantity" class="form-label required"><?php echo t('stock_quantity_label', 'Miktar'); ?></label>
                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                       value="<?php echo post('quantity', ''); ?>" step="any" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="unit" class="form-label required"><?php echo t('stock_unit_label', 'Birim'); ?></label>
                                <select class="form-select" id="unit" name="unit" required>
                                    <option value=""><?php echo t('orders_select', 'Seçiniz'); ?></option>
                                    <optgroup label="<?php echo t('stock_unit_group_piece', 'Parça Bazlı'); ?>">
                                        <option value="piece" <?php echo post('unit') == 'piece' ? 'selected' : ''; ?>><?php echo t('stock_unit_piece', 'Adet'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php echo t('stock_unit_group_weight', 'Ağırlık/Hacim'); ?>">
                                        <option value="kg" <?php echo post('unit') == 'kg' ? 'selected' : ''; ?>><?php echo t('stock_unit_kg', 'Kg'); ?></option>
                                        <option value="lt" <?php echo post('unit') == 'lt' ? 'selected' : ''; ?>><?php echo t('stock_unit_lt', 'Lt'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php echo t('stock_unit_group_length', 'Uzunluk/Alan/Hacim'); ?>">
                                        <option value="m" <?php echo post('unit') == 'm' ? 'selected' : ''; ?>><?php echo t('stock_unit_m', 'Metre'); ?></option>
                                        <option value="m2" <?php echo post('unit') == 'm2' ? 'selected' : ''; ?>><?php echo t('stock_unit_m2_label', 'Metrekare'); ?></option>
                                        <option value="m3" <?php echo post('unit') == 'm3' ? 'selected' : ''; ?>><?php echo t('stock_unit_m3_label', 'Metreküp'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php echo t('stock_unit_group_package', 'Ambalaj'); ?>">
                                        <option value="package" <?php echo post('unit') == 'package' ? 'selected' : ''; ?>><?php echo t('stock_unit_package', 'Paket'); ?></option>
                                        <option value="box" <?php echo post('unit') == 'box' ? 'selected' : ''; ?>><?php echo t('stock_unit_box', 'Kutu'); ?></option>
                                        <option value="pallet" <?php echo post('unit') == 'pallet' ? 'selected' : ''; ?>><?php echo t('stock_unit_pallet', 'Palet'); ?></option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label"><?php echo t('stock_notes_label', 'Not'); ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo post('notes', ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Dynamic Fields -->
            <?php if (!empty($dynamicFields)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('stock_additional_info', 'Ek Bilgiler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($dynamicFields as $field): ?>
                        <div class="col-md-6 mb-3">
                            <label for="field_<?php echo $field['id']; ?>" class="form-label">
                                <?php echo e($field['field_name']); ?>
                                <?php if ($field['is_required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($field['field_type'] == 'text'): ?>
                                <input type="text" 
                                       class="form-control" 
                                       id="field_<?php echo $field['id']; ?>" 
                                       name="dynamic_fields[<?php echo $field['id']; ?>]"
                                       <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                       
                            <?php elseif ($field['field_type'] == 'number'): ?>
                                <input type="number" 
                                       class="form-control" 
                                       id="field_<?php echo $field['id']; ?>" 
                                       name="dynamic_fields[<?php echo $field['id']; ?>]"
                                       step="any"
                                       <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                       
                            <?php elseif ($field['field_type'] == 'select'): ?>
                                <select class="form-select" 
                                        id="field_<?php echo $field['id']; ?>" 
                                        name="dynamic_fields[<?php echo $field['id']; ?>]"
                                        <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                    <option value=""><?php echo t('orders_select', 'Seçiniz'); ?></option>
                                    <?php 
                                    $options = explode(',', $field['field_options']);
                                    foreach ($options as $option): 
                                        $option = trim($option);
                                    ?>
                                        <option value="<?php echo e($option); ?>"><?php echo e($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                            <?php elseif ($field['field_type'] == 'textarea'): ?>
                                <textarea class="form-control" 
                                          id="field_<?php echo $field['id']; ?>" 
                                          name="dynamic_fields[<?php echo $field['id']; ?>]"
                                          rows="3"
                                          <?php echo $field['is_required'] ? 'required' : ''; ?>></textarea>
                                          
                            <?php elseif ($field['field_type'] == 'date'): ?>
                                <input type="date" 
                                       class="form-control" 
                                       id="field_<?php echo $field['id']; ?>" 
                                       name="dynamic_fields[<?php echo $field['id']; ?>]"
                                       <?php echo $field['is_required'] ? 'required' : ''; ?>>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Side Panel -->
        <div class="col-md-4">
            <!-- Product Stock Info -->
            <div class="card" id="productStockInfo" style="display: none;">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('stock_product_stock_info', 'Ürün Stok Bilgisi'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <h3 class="mb-0" id="currentStockLevel">-</h3>
                        <p class="text-muted mb-0"><?php echo t('stock_current_stock_label', 'Mevcut Stok'); ?></p>
                    </div>
                    <hr>
                    <div id="stockWarning" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i> <span id="stockWarningText"></span>
                    </div>
                    <div class="small text-muted">
                        <div class="d-flex justify-content-between mb-1">
                            <span>SKU:</span>
                            <span id="productSku">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Barkod:</span>
                            <span id="productBarcode">-</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Help Box -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('stock_help_tips_title', 'Yardım & İpuçları'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <h6><?php echo t('stock_movement_types', 'Stok Hareketi Tipleri'); ?></h6>
                        <ul class="mb-0">
                            <li><strong><?php echo t('stock_movement_in_desc', 'Stok Girişi: Ürün alımı veya üretimi'); ?></strong></li>
                            <li><strong><?php echo t('stock_movement_out_desc', 'Stok Çıkışı: Satış veya kullanım'); ?></strong></li>
                            <li><strong><?php echo t('stock_movement_adjustment_desc', 'Düzeltme: Sayım farkları için'); ?></strong></li>
                        </ul>
                        <hr>
                        <p class="mb-0">
                            <i class="fas fa-info-circle"></i> <?php echo t('stock_movement_info', 'Stok çıkışında mevcut stoktan fazla miktar giremezsiniz.'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Form Controls -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <a href="<?php echo url('index.php?module=stock'); ?>" class="btn btn-secondary w-100">
                                <i class="fas fa-arrow-left"></i> <?php echo t('orders_back', 'Geri Dön'); ?>
                            </a>
                        </div>
                        <div class="col-6">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> <?php echo t('orders_save', 'Kaydet'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            placeholder: '<?php echo t('stock_select_product', 'Ürün seçin veya arayın'); ?>'
        });
        
        // Product selection change
        $('#product_id').on('change', function() {
            const productId = $(this).val();
            const selectedOption = $(this).find('option:selected');
            
            if (productId) {
                // Show product info
                $('#productStockInfo').show();
                $('#productSku').text(selectedOption.data('sku') || '-');
                $('#productBarcode').text(selectedOption.data('barcode') || '-');
                
                // Get current stock level
                $.ajax({
                    url: '<?php echo url('api/products.php?action=get_stock_level'); ?>',
                    type: 'GET',
                    data: { id: productId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#currentStockLevel').text(response.stock_level);
                            
                            // Show warning if low stock
                            if (response.stock_status === 'low_stock') {
                                $('#stockWarning').show();
                                $('#stockWarningText').text('<?php echo t('stock_low_stock_warning', 'Stok kritik seviyede!'); ?>');
                            } else if (response.stock_status === 'out_of_stock') {
                                $('#stockWarning').show();
                                $('#stockWarningText').text('<?php echo t('stock_out_of_stock_warning', 'Stokta yok!'); ?>');
                            } else {
                                $('#stockWarning').hide();
                            }
                            
                            // Store current stock for validation
                            $('#quantity').data('max-stock', response.stock_level);
                        }
                    }
                });
            } else {
                $('#productStockInfo').hide();
            }
        });
        
        // Trigger change if product is preselected
        if ($('#product_id').val()) {
            $('#product_id').trigger('change');
        }
        
        // Validate stock on quantity or type change
        $('#quantity, #type').on('change', function() {
            const type = $('#type').val();
            const quantity = parseFloat($('#quantity').val()) || 0;
            const maxStock = parseFloat($('#quantity').data('max-stock')) || 0;
            
            if (type === 'out' && quantity > maxStock) {
                $('#quantity').addClass('is-invalid');
                if (!$('#quantity').next('.invalid-feedback').length) {
                    $('#quantity').after('<div class="invalid-feedback"><?php echo t('stock_insufficient_alert', 'Yetersiz stok! Mevcut stok:'); ?> ' + maxStock + '</div>');
                }
            } else {
                $('#quantity').removeClass('is-invalid');
                $('#quantity').next('.invalid-feedback').remove();
            }
        });
        
        // Form validation
        $('#stockForm').on('submit', function(e) {
            const type = $('#type').val();
            const quantity = parseFloat($('#quantity').val()) || 0;
            const maxStock = parseFloat($('#quantity').data('max-stock')) || 0;
            
            if (type === 'out' && quantity > maxStock) {
                e.preventDefault();
                alert('<?php echo t('stock_insufficient_alert', 'Yetersiz stok! Mevcut stok:'); ?> ' + maxStock);
                return false;
            }
        });
    });
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>