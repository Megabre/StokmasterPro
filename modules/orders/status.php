<?php
/**
 * Megabre StokMaster Pro
 * Update Order Status
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

// Get parameters
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$newStatus = isset($_GET['status']) ? $_GET['status'] : '';

if ($orderId <= 0) {
    Session::setFlash('error', 'Geçersiz sipariş ID\'si.');
    redirect('index.php?module=orders');
}

// Valid statuses
$validStatuses = ['pending', 'processing', 'completed', 'cancelled'];

if (!in_array($newStatus, $validStatuses)) {
    Session::setFlash('error', 'Geçersiz sipariş durumu.');
    redirect('index.php?module=orders');
}

// Get order data
$db->query("SELECT o.*, c.name as customer_name, c.surname as customer_surname
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = :id");
$db->bind(':id', $orderId);
$order = $db->single();

if (!$order) {
    Session::setFlash('error', 'Sipariş bulunamadı.');
    redirect('index.php?module=orders');
}

// Check if status can be changed
$currentStatus = $order['status'];
$canChange = false;
$reason = '';

// Define status flow rules
switch ($currentStatus) {
    case 'pending':
        $canChange = in_array($newStatus, ['processing', 'cancelled']);
        if (!$canChange) {
            $reason = 'Bekleyen siparişler sadece işleme alınabilir veya iptal edilebilir.';
        }
        break;
        
    case 'processing':
        $canChange = in_array($newStatus, ['completed', 'cancelled']);
        if (!$canChange) {
            $reason = 'İşlemdeki siparişler sadece tamamlanabilir veya iptal edilebilir.';
        }
        break;
        
    case 'completed':
        $canChange = false;
        $reason = 'Tamamlanmış siparişlerin durumu değiştirilemez.';
        break;
        
    case 'cancelled':
        $canChange = false;
        $reason = 'İptal edilmiş siparişlerin durumu değiştirilemez.';
        break;
}

// Don't allow changing to same status
if ($currentStatus == $newStatus) {
    $canChange = false;
    $reason = 'Sipariş zaten bu durumda.';
}

if (!$canChange) {
    Session::setFlash('error', $reason);
    redirect('index.php?module=orders&action=view&id=' . $orderId);
}

// Process status update
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=orders');
    }
    
    $statusNote = post('status_note');
    
    // Get inventory settings
    $db->query("SELECT setting_value FROM settings WHERE setting_key = :key");
    $db->bind(':key', 'inventory_settings');
    $settingsResult = $db->single();
    
    $inventorySettings = [];
    $restoreStockOnCancel = true; // Default: restore stock
    
    if ($settingsResult) {
        $inventorySettings = json_decode($settingsResult['setting_value'], true);
        $restoreStockOnCancel = isset($inventorySettings['order_cancel_stock']) && $inventorySettings['order_cancel_stock'] == 1;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Update order status
        $db->query("UPDATE orders SET 
                    status = :status,
                    updated_at = NOW()
                    WHERE id = :id");
        $db->bind(':status', $newStatus);
        $db->bind(':id', $orderId);
        $db->execute();
        
        // If cancelling order, restore stock (only if setting is enabled)
        if ($newStatus == 'cancelled' && $restoreStockOnCancel) {
            // Get order items
            $db->query("SELECT * FROM order_items WHERE order_id = :order_id");
            $db->bind(':order_id', $orderId);
            $orderItems = $db->resultSet();
            
            // Create stock in movements for each item
            foreach ($orderItems as $item) {
                // Get unit from the original stock out movement for this order
                $db->query("SELECT unit FROM stock_movements 
                           WHERE product_id = :product_id 
                           AND type = 'out' 
                           AND notes = :notes 
                           ORDER BY id DESC 
                           LIMIT 1");
                $db->bind(':product_id', $item['product_id']);
                $db->bind(':notes', 'Sipariş #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                $stockMovement = $db->single();
                
                // Use unit from stock movement if available, otherwise default to 'piece'
                $unit = !empty($stockMovement['unit']) ? $stockMovement['unit'] : 'piece';
                
                // Check if stock movement already exists for this cancellation
                $db->query("SELECT COUNT(*) as count FROM stock_movements 
                           WHERE product_id = :product_id 
                           AND type = 'in' 
                           AND notes = :notes");
                $db->bind(':product_id', $item['product_id']);
                $db->bind(':notes', 'İptal edilen sipariş: #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                $existingCheck = $db->single();
                
                // Only add if not already restored
                if ($existingCheck['count'] == 0) {
                    $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes) 
                               VALUES (:product_id, 'in', :quantity, :unit, CURDATE(), :notes)");
                    $db->bind(':product_id', $item['product_id']);
                    $db->bind(':quantity', $item['quantity']);
                    $db->bind(':unit', $unit);
                    $db->bind(':notes', 'İptal edilen sipariş: #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                    $db->execute();
                }
            }
            
            // Update transaction status
            $db->query("UPDATE transactions SET 
                        notes = CONCAT(notes, ' (İptal edildi)')
                        WHERE reference_type = 'order' AND reference_id = :order_id");
            $db->bind(':order_id', $orderId);
            $db->execute();
        }
        
        // Add status log (if we had a logs table)
        // This is a placeholder for future implementation
        
        // Commit transaction
        $db->endTransaction();
        
        // Set success message
        $statusLabels = [
            'pending' => 'Bekleyen',
            'processing' => 'İşlemde',
            'completed' => 'Tamamlandı',
            'cancelled' => 'İptal'
        ];
        
        Session::setFlash('success', 'Sipariş durumu "' . $statusLabels[$newStatus] . '" olarak güncellendi.');
        
        // Redirect to order view
        redirect('index.php?module=orders&action=view&id=' . $orderId);
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->cancelTransaction();
        
        // Set error message
        Session::setFlash('error', 'Durum güncellenirken bir hata oluştu: ' . $e->getMessage());
        redirect('index.php?module=orders&action=view&id=' . $orderId);
    }
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Sipariş Durumu Güncelle</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=orders'); ?>">Siparişler</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=orders&action=view&id=' . $orderId); ?>">Sipariş #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?></a></li>
                <li class="breadcrumb-item active">Durum Güncelle</li>
            </ul>
        </div>
    </div>
</div>

<!-- Status Update Form -->
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Durum Güncelleme</h5>
            </div>
            <div class="card-body">
                <!-- Order Info -->
                <div class="alert alert-info">
                    <h6>Sipariş Bilgileri</h6>
                    <p class="mb-1"><strong>Sipariş No:</strong> #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
                    <p class="mb-1"><strong>Müşteri:</strong> <?php echo e($order['customer_name'] . ' ' . $order['customer_surname']); ?></p>
                    <p class="mb-1"><strong>Tutar:</strong> <?php echo formatPrice($order['total_amount']); ?> ₺</p>
                    <p class="mb-0"><strong>Mevcut Durum:</strong> 
                        <?php
                        $statusLabels = [
                            'pending' => ['class' => 'warning', 'text' => 'Bekleyen'],
                            'processing' => ['class' => 'info', 'text' => 'İşlemde'],
                            'completed' => ['class' => 'success', 'text' => 'Tamamlandı'],
                            'cancelled' => ['class' => 'danger', 'text' => 'İptal']
                        ];
                        $currentStatusInfo = $statusLabels[$currentStatus] ?? ['class' => 'secondary', 'text' => 'Bilinmeyen'];
                        $newStatusInfo = $statusLabels[$newStatus] ?? ['class' => 'secondary', 'text' => 'Bilinmeyen'];
                        ?>
                        <span class="badge bg-<?php echo $currentStatusInfo['class']; ?>"><?php echo $currentStatusInfo['text']; ?></span>
                    </p>
                </div>
                
                <!-- Status Change -->
                <div class="text-center mb-4">
                    <h5>
                        <span class="badge bg-<?php echo $currentStatusInfo['class']; ?>"><?php echo $currentStatusInfo['text']; ?></span>
                        <i class="fas fa-arrow-right mx-3"></i>
                        <span class="badge bg-<?php echo $newStatusInfo['class']; ?>"><?php echo $newStatusInfo['text']; ?></span>
                    </h5>
                </div>
                
                <!-- Warnings -->
                <?php if ($newStatus == 'cancelled'): ?>
                <div class="alert alert-warning">
                    <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Uyarı</h6>
                    <ul class="mb-0">
                        <li>Sipariş iptal edildiğinde stok otomatik olarak geri yüklenecektir</li>
                        <li>Müşteri borç kayıtları "İptal edildi" notu ile güncellenecektir</li>
                        <li>Bu işlem geri alınamaz</li>
                    </ul>
                </div>
                <?php elseif ($newStatus == 'completed'): ?>
                <div class="alert alert-success">
                    <h6 class="alert-heading"><i class="fas fa-check-circle"></i> Bilgi</h6>
                    <ul class="mb-0">
                        <li>Sipariş tamamlandı olarak işaretlenecektir</li>
                        <li>Tamamlanan siparişler düzenlenemez</li>
                        <li>Müşteri mali kayıtları etkilenmeyecektir</li>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo url('index.php?module=orders&action=status&id=' . $orderId . '&status=' . $newStatus); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="mb-3">
                        <label for="status_note" class="form-label">Not (Opsiyonel)</label>
                        <textarea class="form-control" id="status_note" name="status_note" rows="3" 
                                  placeholder="Durum değişikliği ile ilgili not ekleyebilirsiniz..."></textarea>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <a href="<?php echo url('index.php?module=orders&action=view&id=' . $orderId); ?>" class="btn btn-secondary w-100">
                                <i class="fas fa-times"></i> İptal
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-<?php echo $newStatusInfo['class']; ?> w-100">
                                <i class="fas fa-check"></i> Durumu Güncelle
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