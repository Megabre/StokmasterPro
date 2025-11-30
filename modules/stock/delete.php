<?php
/**
 * Megabre StokMaster Pro
 * Delete Stock Movement
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

// Get movement ID from URL
$movementId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($movementId <= 0) {
    Session::setFlash('error', t('stock_invalid_movement_id', 'Geçersiz hareket ID\'si.'));
    redirect('index.php?module=stock');
}

// Get stock movement data
$db->query("SELECT sm.*, p.name as product_name, p.sku, p.barcode,
            DATE_FORMAT(sm.date, '%d.%m.%Y') as formatted_date
            FROM stock_movements sm 
            JOIN products p ON sm.product_id = p.id 
            WHERE sm.id = :id");
$db->bind(':id', $movementId);
$movement = $db->single();

if (!$movement) {
    Session::setFlash('error', t('stock_movement_not_found', 'Stok hareketi bulunamadı.'));
    redirect('index.php?module=stock');
}

// Calculate current stock after removing this movement
$db->query("SELECT COALESCE(SUM(CASE 
                WHEN type = 'in' THEN quantity 
                WHEN type = 'out' THEN -quantity 
                ELSE quantity 
            END), 0) as current_stock 
           FROM stock_movements 
           WHERE product_id = :product_id");
$db->bind(':product_id', $movement['product_id']);
$stockResult = $db->single();
$currentStock = $stockResult['current_stock'];

// Calculate what stock would be after deletion
$stockAfterDeletion = $currentStock;
if ($movement['type'] == 'in') {
    $stockAfterDeletion -= $movement['quantity'];
} elseif ($movement['type'] == 'out') {
    $stockAfterDeletion += $movement['quantity'];
} else { // adjustment
    $stockAfterDeletion -= $movement['quantity'];
}

// Process deletion
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=stock');
    }
    
    // Check if deletion would result in negative stock
    if ($stockAfterDeletion < 0) {
        Session::setFlash('error', t('stock_delete_cannot_negative', 'Bu hareket silinemez! Silme işlemi negatif stoğa neden olacaktır. Stok silindikten sonra:') . ' ' . number_format($stockAfterDeletion, 2));
        redirect('index.php?module=stock&action=delete&id=' . $movementId);
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Delete stock field values first (if table exists)
        try {
            $db->query("DELETE FROM stock_field_values WHERE movement_id = :movement_id");
            $db->bind(':movement_id', $movementId);
            $db->execute();
        } catch (PDOException $e) {
            // Table doesn't exist or column doesn't exist, skip
        }
        
        // Log activity before deletion
        $typeLabels = [
            'in' => t('stock_type_in', 'Giriş'),
            'out' => t('stock_type_out', 'Çıkış'),
            'adjustment' => t('stock_type_adjustment', 'Düzeltme')
        ];
        logActivity('delete_stock_movement', 'stock', $movementId, [
            'product_id' => $movement['product_id'],
            'product_name' => $movement['product_name'],
            'type' => $movement['type'],
            'quantity' => $movement['quantity'],
            'unit' => $movement['unit'],
            'date' => $movement['date'],
            'notes' => $movement['notes'] ?? ''
        ], null, "Stok hareketi silindi: {$movement['product_name']} - {$typeLabels[$movement['type']]} ({$movement['quantity']} {$movement['unit']})");
        
        // Delete stock movement
        $db->query("DELETE FROM stock_movements WHERE id = :id");
        $db->bind(':id', $movementId);
        $db->execute();
        
        // Commit transaction
        $db->endTransaction();
        
        // Set success message
        Session::setFlash('success', t('stock_delete_success', 'Stok hareketi başarıyla silindi.'));
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->cancelTransaction();
        
        // Set error message
        Session::setFlash('error', t('stock_delete_error', 'Stok hareketi silinirken bir hata oluştu:') . ' ' . $e->getMessage());
    }
    
    // Redirect to stock list
    redirect('index.php?module=stock');
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('stock_delete_title', 'Stok Hareketi Sil'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=stock'); ?>"><?php echo t('stock_title', 'Stok Yönetimi'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('stock_delete_title', 'Stok Hareketi Sil'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Delete Stock Movement Confirmation -->
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0"><?php echo t('stock_delete_confirm_title', 'Stok Hareketi Silme Onayı'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong><?php echo t('common_warning', 'Uyarı'); ?>:</strong> <?php echo t('common_irreversible', 'Bu işlem geri alınamaz!'); ?>
                </div>
                
                <p><?php echo t('stock_delete_confirm_text', 'Aşağıdaki stok hareketini silmek üzeresiniz:'); ?></p>
                
                <div class="movement-info mb-4">
                    <table class="table table-bordered">
                        <tr>
                            <td width="30%"><strong><?php echo t('stock_delete_product', 'Ürün:'); ?></strong></td>
                            <td><?php echo e($movement['product_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo t('stock_delete_movement_type', 'Hareket Tipi:'); ?></strong></td>
                            <td>
                                <?php if ($movement['type'] == 'in'): ?>
                                    <span class="badge bg-success"><?php echo t('stock_movement_in', 'Stok Girişi'); ?></span>
                                <?php elseif ($movement['type'] == 'out'): ?>
                                    <span class="badge bg-danger"><?php echo t('stock_movement_out', 'Stok Çıkışı'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><?php echo t('stock_movement_adjustment', 'Düzeltme'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php echo t('stock_delete_quantity', 'Miktar:'); ?></strong></td>
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
                                $unitText = $units[$movement['unit']] ?? $movement['unit'];
                                echo number_format($movement['quantity'], 2) . ' ' . $unitText;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php echo t('stock_delete_date', 'Tarih:'); ?></strong></td>
                            <td><?php echo $movement['formatted_date']; ?></td>
                        </tr>
                        <?php if (!empty($movement['notes'])): ?>
                        <tr>
                            <td><strong><?php echo t('stock_delete_notes', 'Not:'); ?></strong></td>
                            <td><?php echo e($movement['notes']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <!-- Stock Impact Warning -->
                <div class="alert alert-info">
                    <h6 class="alert-heading"><?php echo t('stock_delete_stock_impact', 'Stok Etkisi'); ?></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong><?php echo t('stock_delete_current_stock', 'Mevcut Stok:'); ?></strong> <?php echo number_format($currentStock, 2); ?>
                        </div>
                        <div class="col-md-6">
                            <strong><?php echo t('stock_delete_after_deletion', 'Silindikten Sonra:'); ?></strong> 
                            <span class="<?php echo $stockAfterDeletion < 0 ? 'text-danger' : ''; ?>">
                                <?php echo number_format($stockAfterDeletion, 2); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if ($stockAfterDeletion < 0): ?>
                <div class="alert alert-danger">
                    <h6 class="alert-heading"><?php echo t('stock_delete_blocked', 'Silme İşlemi Engellenmiştir!'); ?></h6>
                    <p class="mb-0"><?php echo t('stock_delete_blocked_message', 'Bu hareketin silinmesi stokun negatif olmasına neden olacaktır. İşlem yapılamaz.'); ?></p>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo url('index.php?module=stock&action=delete&id=' . $movementId); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <a href="<?php echo url('index.php?module=stock'); ?>" class="btn btn-secondary w-100">
                                <i class="fas fa-times"></i> <?php echo t('stock_delete_cancel', 'İptal'); ?>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-danger w-100" <?php echo $stockAfterDeletion < 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-trash"></i> <?php echo t('stock_delete_confirm_button', 'Hareketi Sil'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>