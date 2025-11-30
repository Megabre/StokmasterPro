<?php
/**
 * Megabre StokMaster Pro
 * Inventory Settings
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Check if user has admin access
if (!$auth->hasAccess('admin')) {
    Session::setFlash('error', t('access_denied', 'Bu sayfaya erişim izniniz yok.'));
    redirect('index.php?module=settings');
}

// Initialize database connection
$db = Database::getInstance();

// Create settings table if not exists
$db->query("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->execute();

// Get settings
$db->query("SELECT * FROM settings WHERE setting_key = :key");
$db->bind(':key', 'inventory_settings');
$settings = $db->single();

if (!$settings) {
    // Create default settings if not exists
    $db->query("INSERT INTO settings (setting_key, setting_value, created_at) VALUES (:key, :value, NOW())");
    $db->bind(':key', 'inventory_settings');
    $db->bind(':value', json_encode([
        'low_stock_threshold' => 10,
        'enable_stock_alerts' => true,
        'enable_negative_stock' => false,
        'default_stock_unit' => 'adet',
        'enable_barcode' => true,
        'enable_serial_number' => false,
        'enable_batch_tracking' => false,
        'enable_expiry_date' => false,
        'enable_location_tracking' => false,
        'default_location' => 'Ana Depo'
    ]));
    $db->execute();
    
    // Get settings again
    $db->query("SELECT * FROM settings WHERE setting_key = :key");
    $db->bind(':key', 'inventory_settings');
    $settings = $db->single();
}

$settings = json_decode($settings['setting_value'], true);

// Get measurement units
$db->query("SELECT * FROM measurement_units ORDER BY name ASC");
$measurementUnits = $db->resultSet();

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=settings&action=inventory');
    }
    
    // Get form data
    $lowStockWarning = post('inventory_low_stock_warning');
    $defaultMeasurementUnit = post('inventory_default_unit');
    $autoSku = post('inventory_auto_sku') ? 1 : 0;
    $skuPrefix = post('inventory_sku_prefix');
    $stockMovementNotes = post('inventory_stock_movement_notes') ? 1 : 0;
    $orderAutoStatus = post('inventory_order_auto_status') ? 1 : 0;
    $orderCancelStock = post('inventory_order_cancel_stock') ? 1 : 0;
    $allowNegativeStock = post('inventory_allow_negative_stock') ? 1 : 0;
    $stockHistory = post('inventory_stock_history') ? 1 : 0;
    $stockHistoryDays = post('inventory_stock_history_days');
    
    // Validate form data
    $errors = [];
    
    if (!is_numeric($lowStockWarning) || $lowStockWarning < 0) {
        $errors[] = t('settings_inventory_low_stock_warning_invalid', 'Düşük stok uyarısı geçerli bir sayı olmalıdır.');
    }
    
    if (empty($defaultMeasurementUnit)) {
        $errors[] = t('settings_inventory_default_unit_required', 'Varsayılan ölçü birimi seçilmelidir.');
    }
    
    if ($autoSku && empty($skuPrefix)) {
        $errors[] = t('settings_inventory_sku_prefix_required', 'Otomatik SKU için bir önek belirtilmelidir.');
    }
    
    if ($stockHistory && (!is_numeric($stockHistoryDays) || $stockHistoryDays <= 0)) {
        $errors[] = t('settings_inventory_stock_history_days_invalid', 'Stok geçmişi günü geçerli bir sayı olmalıdır.');
    }
    
    // Process measurement units
    $unitNames = post('unit_name', []);
    $unitSymbols = post('unit_symbol', []);
    $unitIds = post('unit_id', []);
    $unitDeletes = post('unit_delete', []);
    
    $newUnits = [];
    $updateUnits = [];
    $deleteUnits = [];
    
    if (!empty($unitNames)) {
        foreach ($unitNames as $index => $name) {
            $symbol = $unitSymbols[$index] ?? '';
            $id = $unitIds[$index] ?? '';
            $delete = isset($unitDeletes[$index]);
            
            if (empty($name)) {
                continue;
            }
            
            if ($delete) {
                if (!empty($id)) {
                    $deleteUnits[] = $id;
                }
            } else {
                if (empty($id)) {
                    $newUnits[] = [
                        'name' => $name,
                        'symbol' => $symbol
                    ];
                } else {
                    $updateUnits[] = [
                        'id' => $id,
                        'name' => $name,
                        'symbol' => $symbol
                    ];
                }
            }
        }
    }
    
    if (empty($errors)) {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Update settings
            $settingsToUpdate = [
                'low_stock_threshold' => $lowStockWarning,
                'default_unit' => $defaultMeasurementUnit,
                'auto_sku' => $autoSku,
                'sku_prefix' => $skuPrefix,
                'stock_movement_notes' => $stockMovementNotes,
                'order_auto_status' => $orderAutoStatus,
                'order_cancel_stock' => $orderCancelStock,
                'allow_negative_stock' => $allowNegativeStock,
                'stock_history' => $stockHistory,
                'stock_history_days' => $stockHistoryDays
            ];
            
            // Get current settings
            $db->query("SELECT setting_value FROM settings WHERE setting_key = :key");
            $db->bind(':key', 'inventory_settings');
            $currentSettings = $db->single();
            
            $oldSettings = [];
            if ($currentSettings) {
                $oldSettings = json_decode($currentSettings['setting_value'], true) ?? [];
                $settingsToUpdate = array_merge($oldSettings, $settingsToUpdate);
            }
            
            // Update settings
            $db->query("UPDATE settings SET setting_value = :value, setting_description = :description WHERE setting_key = :key");
            $db->bind(':key', 'inventory_settings');
            $db->bind(':value', json_encode($settingsToUpdate));
            $db->bind(':description', t('settings_inventory_title', 'Envanter ayarları'));
            $db->execute();
            
            // If no rows were updated, insert new settings
            if ($db->rowCount() === 0) {
                $db->query("INSERT INTO settings (setting_key, setting_value, setting_description) VALUES (:key, :value, :description)");
                $db->bind(':key', 'inventory_settings');
                $db->bind(':value', json_encode($settingsToUpdate));
                $db->bind(':description', t('settings_inventory_title', 'Envanter ayarları'));
                $db->execute();
            }
            
            // Log inventory settings changes
            logActivity('update_inventory_settings', 'settings', 0, $oldSettings, $settingsToUpdate, "Envanter ayarları güncellendi");
            
            // Clean up duplicate measurement units
            $db->query("DELETE t1 FROM measurement_units t1
                       INNER JOIN measurement_units t2 
                       WHERE t1.id > t2.id 
                       AND t1.name = t2.name 
                       AND t1.symbol = t2.symbol");
            $db->execute();
            
            // Add new units
            foreach ($newUnits as $unit) {
                $db->query("INSERT INTO measurement_units (name, symbol) VALUES (:name, :symbol)");
                $db->bind(':name', $unit['name']);
                $db->bind(':symbol', $unit['symbol']);
                $db->execute();
                
                $unitId = $db->lastInsertId();
                
                // Log activity
                logActivity('add_measurement_unit', 'measurement_unit', $unitId, null, [
                    'name' => $unit['name'],
                    'symbol' => $unit['symbol']
                ], "Yeni ölçü birimi eklendi: {$unit['name']} ({$unit['symbol']})");
            }
            
            // Update existing units
            foreach ($updateUnits as $unit) {
                // Get old unit data
                $db->query("SELECT * FROM measurement_units WHERE id = :id");
                $db->bind(':id', $unit['id']);
                $oldUnit = $db->single();
                
                // Prepare old and new data
                $oldData = [
                    'name' => $oldUnit['name'],
                    'symbol' => $oldUnit['symbol']
                ];
                $newData = [
                    'name' => $unit['name'],
                    'symbol' => $unit['symbol']
                ];
                
                $db->query("UPDATE measurement_units SET name = :name, symbol = :symbol WHERE id = :id");
                $db->bind(':name', $unit['name']);
                $db->bind(':symbol', $unit['symbol']);
                $db->bind(':id', $unit['id']);
                $db->execute();
                
                // Log activity
                logActivity('update_measurement_unit', 'measurement_unit', $unit['id'], $oldData, $newData, "Ölçü birimi güncellendi: {$oldData['name']} → {$newData['name']}");
            }
            
            // Delete units
            if (!empty($deleteUnits)) {
                // Check if the default unit is being deleted
                if (in_array($defaultMeasurementUnit, $deleteUnits)) {
                    throw new Exception(t('settings_inventory_default_unit_cannot_delete_error', 'Varsayılan ölçü birimi silinemez.'));
                }
                
                // Check if units are used in products
                $deleteUnitsStr = implode(',', $deleteUnits);
                $db->query("SELECT COUNT(*) as count FROM products WHERE unit_id IN ($deleteUnitsStr)");
                $productCount = $db->single()['count'];
                
                if ($productCount > 0) {
                    throw new Exception(t('settings_inventory_unit_in_use_error', 'Silmek istediğiniz ölçü birimleri ürünlerde kullanılmaktadır.'));
                }
                
                // Get units data before deletion
                $db->query("SELECT * FROM measurement_units WHERE id IN ($deleteUnitsStr)");
                $unitsToDelete = $db->resultSet();
                
                // Delete units
                $db->query("DELETE FROM measurement_units WHERE id IN ($deleteUnitsStr)");
                $db->execute();
                
                // Log activity for each deleted unit
                foreach ($unitsToDelete as $unit) {
                    logActivity('delete_measurement_unit', 'measurement_unit', $unit['id'], [
                        'name' => $unit['name'],
                        'symbol' => $unit['symbol']
                    ], null, "Ölçü birimi silindi: {$unit['name']} ({$unit['symbol']})");
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', t('settings_inventory_update_success', 'Envanter ayarları başarıyla güncellendi.'));
            
            // Redirect to refresh settings
            redirect('index.php?module=settings&action=inventory');
            
        } catch (Exception $e) {
            // Rollback transaction
            $db->cancelTransaction();
            
            $errors[] = t('settings_inventory_update_error', 'Ayarlar güncellenirken bir hata oluştu:') . ' ' . $e->getMessage();
        }
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

// Display errors
if (!empty($errors)) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">';
    foreach ($errors as $error) {
        echo '<li>' . $error . '</li>';
    }
    echo '</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('settings_inventory_title', 'Envanter Ayarları'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('settings_inventory_title', 'Envanter Ayarları'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Settings Form -->
<form action="<?php echo url('index.php?module=settings&action=inventory'); ?>" method="post">
    <?php echo csrfField(); ?>
    
    <div class="row">
        <div class="col-lg-6">
            <!-- General Inventory Settings -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('settings_inventory_general_title', 'Genel Envanter Ayarları'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="inventory_low_stock_warning" class="form-label"><?php echo t('settings_inventory_low_stock_warning', 'Düşük Stok Uyarı Seviyesi'); ?></label>
                        <input type="number" class="form-control" id="inventory_low_stock_warning" name="inventory_low_stock_warning" value="<?php echo e($settings['low_stock_threshold'] ?? '10'); ?>" min="0">
                        <small class="text-muted"><?php echo t('settings_inventory_low_stock_warning_desc', 'Varsayılan düşük stok uyarı seviyesi. Ürün bazında farklı ayarlanabilir.'); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="inventory_default_unit" class="form-label"><?php echo t('settings_inventory_default_unit', 'Varsayılan Ölçü Birimi'); ?></label>
                        <select class="form-select" id="inventory_default_unit" name="inventory_default_unit">
                            <option value=""><?php echo t('select', 'Seçiniz'); ?></option>
                            <?php foreach ($measurementUnits as $unit): ?>
                            <option value="<?php echo $unit['id']; ?>" <?php echo ($settings['default_unit'] ?? '') == $unit['id'] ? 'selected' : ''; ?>>
                                <?php echo e($unit['name']); ?> (<?php echo e($unit['symbol']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><?php echo t('settings_inventory_default_unit_desc', 'Yeni ürünler için varsayılan ölçü birimi'); ?></small>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="inventory_allow_negative_stock" name="inventory_allow_negative_stock" value="1" <?php echo isset($settings['allow_negative_stock']) && $settings['allow_negative_stock'] == 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="inventory_allow_negative_stock"><?php echo t('settings_inventory_allow_negative_stock', 'Negatif Stoka İzin Ver'); ?></label>
                        <small class="text-muted d-block"><?php echo t('settings_inventory_allow_negative_stock_desc', 'Stokta olmayan ürünlerin sipariş edilmesine izin ver'); ?></small>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="inventory_stock_movement_notes" name="inventory_stock_movement_notes" value="1" <?php echo isset($settings['stock_movement_notes']) && $settings['stock_movement_notes'] == 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="inventory_stock_movement_notes"><?php echo t('settings_inventory_stock_movement_notes', 'Stok Hareketi Notları'); ?></label>
                        <small class="text-muted d-block"><?php echo t('settings_inventory_stock_movement_notes_desc', 'Stok hareketlerinde not zorunluluğu'); ?></small>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="inventory_stock_history" name="inventory_stock_history" value="1" <?php echo isset($settings['stock_history']) && $settings['stock_history'] == 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="inventory_stock_history"><?php echo t('settings_inventory_stock_history', 'Stok Geçmişi Tut'); ?></label>
                        <small class="text-muted d-block"><?php echo t('settings_inventory_stock_history_desc', 'Günlük stok seviyelerinin geçmişini tut'); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="inventory_stock_history_days" class="form-label"><?php echo t('settings_inventory_stock_history_days', 'Stok Geçmişi Günü'); ?></label>
                        <input type="number" class="form-control" id="inventory_stock_history_days" name="inventory_stock_history_days" value="<?php echo e($settings['stock_history_days'] ?? '90'); ?>" min="1" max="365">
                        <small class="text-muted"><?php echo t('settings_inventory_stock_history_days_desc', 'Stok geçmişinin kaç gün tutulacağı'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Order Settings -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('settings_inventory_order_settings_title', 'Sipariş Ayarları'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="inventory_order_auto_status" name="inventory_order_auto_status" value="1" <?php echo isset($settings['order_auto_status']) && $settings['order_auto_status'] == 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="inventory_order_auto_status"><?php echo t('settings_inventory_order_auto_status', 'Otomatik Sipariş Durumu'); ?></label>
                        <small class="text-muted d-block"><?php echo t('settings_inventory_order_auto_status_desc', 'Sipariş oluşturulduğunda otomatik olarak "İşlemde" durumuna geç'); ?></small>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="inventory_order_cancel_stock" name="inventory_order_cancel_stock" value="1" <?php echo isset($settings['order_cancel_stock']) && $settings['order_cancel_stock'] == 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="inventory_order_cancel_stock"><?php echo t('settings_inventory_order_cancel_stock', 'İptal Edilen Siparişleri Stoğa Geri Al'); ?></label>
                        <small class="text-muted d-block"><?php echo t('settings_inventory_order_cancel_stock_desc', 'Sipariş iptal edildiğinde ürünleri stoğa geri ekle'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- SKU Settings -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('settings_inventory_sku_settings_title', 'SKU Ayarları'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="inventory_auto_sku" name="inventory_auto_sku" value="1" <?php echo isset($settings['auto_sku']) && $settings['auto_sku'] == 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="inventory_auto_sku"><?php echo t('settings_inventory_auto_sku', 'Otomatik SKU Oluştur'); ?></label>
                        <small class="text-muted d-block"><?php echo t('settings_inventory_auto_sku_desc', 'Yeni ürünler için otomatik olarak SKU kodu oluştur'); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="inventory_sku_prefix" class="form-label"><?php echo t('settings_inventory_sku_prefix', 'SKU Öneki'); ?></label>
                        <input type="text" class="form-control" id="inventory_sku_prefix" name="inventory_sku_prefix" value="<?php echo e($settings['sku_prefix'] ?? 'PRD'); ?>">
                        <small class="text-muted"><?php echo t('settings_inventory_sku_prefix_desc', 'Örneğin: "PRD" için "PRD0001", "PRD0002" şeklinde kodlar oluşturulur'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Measurement Units -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><?php echo t('settings_inventory_measurement_units_title', 'Ölçü Birimleri'); ?></h5>
                    <button type="button" class="btn btn-sm btn-primary" id="addUnitBtn">
                        <i class="fas fa-plus"></i> <?php echo t('settings_inventory_add_unit', 'Birim Ekle'); ?>
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="unitsTable">
                            <thead>
                                <tr>
                                    <th width="40%"><?php echo t('settings_inventory_unit_name', 'Birim Adı'); ?></th>
                                    <th width="40%"><?php echo t('settings_inventory_unit_symbol', 'Sembol'); ?></th>
                                    <th width="20%"><?php echo t('settings_inventory_unit_action', 'İşlem'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($measurementUnits as $index => $unit): ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="unit_id[]" value="<?php echo $unit['id']; ?>">
                                        <input type="text" class="form-control" name="unit_name[]" value="<?php echo e($unit['name']); ?>" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="unit_symbol[]" value="<?php echo e($unit['symbol']); ?>">
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="unit_delete[]" id="unit_delete_<?php echo $index; ?>" <?php echo ($settings['default_unit'] ?? '') == $unit['id'] ? 'disabled' : ''; ?>>
                                            <label class="form-check-label" for="unit_delete_<?php echo $index; ?>">
                                                <?php echo t('settings_inventory_unit_delete', 'Sil'); ?>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle"></i> <?php echo t('settings_inventory_default_unit_cannot_delete', 'Varsayılan olarak seçilen ölçü birimi silinemez.'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo t('settings_inventory_save_settings', 'Ayarları Kaydet'); ?>
                        </button>
                        <a href="<?php echo url('index.php?module=settings'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <?php echo t('settings_inventory_back_to_settings', 'Ayarlara Dön'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    $(document).ready(function() {
        // Add new unit
        $('#addUnitBtn').on('click', function() {
            const rowCount = $('#unitsTable tbody tr').length;
            const newRow = `
                <tr>
                    <td>
                        <input type="hidden" name="unit_id[]" value="">
                        <input type="text" class="form-control" name="unit_name[]" required>
                    </td>
                    <td>
                        <input type="text" class="form-control" name="unit_symbol[]">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger remove-unit">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            
            $('#unitsTable tbody').append(newRow);
        });
        
        // Remove new unit
        $(document).on('click', '.remove-unit', function() {
            $(this).closest('tr').remove();
        });
    });
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>