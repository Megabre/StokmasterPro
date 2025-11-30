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

// Get order ID from URL
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId <= 0) {
    Session::setFlash('error', 'Geçersiz sipariş ID\'si.');
    redirect('index.php?module=orders');
}

// Get order data
$db->query("SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name 
            FROM orders o 
            LEFT JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = :id");
$db->bind(':id', $orderId);
$order = $db->single();

if (!$order) {
    Session::setFlash('error', 'Sipariş bulunamadı.');
    redirect('index.php?module=orders');
}

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=orders&action=update_status&id=' . $orderId);
    }
    
    // Get form data
    $status = post('status');
    $statusNote = post('status_note');
    
    // Validate status
    $validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        Session::setFlash('error', 'Geçersiz sipariş durumu.');
        redirect('index.php?module=orders&action=update_status&id=' . $orderId);
    }
    
    // Prepare old data for logging
    $oldData = [
        'status' => $order['status'],
        'notes' => $order['notes'] ?? ''
    ];
    
    // Prepare new data for logging
    $newData = [
        'status' => $status,
        'notes' => $statusNote ?? ''
    ];
    
    // Update order status
    $db->query("UPDATE orders SET 
                status = :status,
                notes = :notes,
                updated_at = NOW()
                WHERE id = :id");
    $db->bind(':status', $status);
    $db->bind(':notes', $statusNote);
    $db->bind(':id', $orderId);
    
    if ($db->execute()) {
        // Log activity with detailed changes
        logActivity('update_order_status', 'order', $orderId, $oldData, $newData, "Sipariş #{$orderId} durumu güncellendi: {$oldData['status']} → {$status}");
        
        Session::setFlash('success', 'Sipariş durumu başarıyla güncellendi.');
        redirect('index.php?module=orders&action=view&id=' . $orderId);
    } else {
        Session::setFlash('error', 'Sipariş durumu güncellenirken bir hata oluştu.');
    }
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Sipariş Durumu Güncelle #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=orders'); ?>">Siparişler</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=orders&action=view&id=' . $orderId); ?>">Sipariş Detayı</a></li>
                <li class="breadcrumb-item active">Durum Güncelle</li>
            </ul>
        </div>
    </div>
</div>

<!-- Update Status Form -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Sipariş Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Müşteri</label>
                    <p class="form-control-static"><?php echo e($order['customer_name']); ?></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sipariş Tarihi</label>
                    <p class="form-control-static"><?php echo date('d.m.Y', strtotime($order['order_date'])); ?></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mevcut Durum</label>
                    <p class="form-control-static">
                        <?php
                        $statusLabels = [
                            'pending' => ['class' => 'warning', 'text' => 'Bekleyen'],
                            'processing' => ['class' => 'info', 'text' => 'İşlemde'],
                            'completed' => ['class' => 'success', 'text' => 'Tamamlandı'],
                            'cancelled' => ['class' => 'danger', 'text' => 'İptal']
                        ];
                        $status = $statusLabels[$order['status']] ?? ['class' => 'secondary', 'text' => 'Bilinmeyen'];
                        ?>
                        <span class="badge bg-<?php echo $status['class']; ?>">
                            <?php echo $status['text']; ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Durum Güncelle</h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=orders&action=update_status&id=' . $orderId); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label required">Yeni Durum</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">Seçiniz</option>
                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Bekleyen</option>
                            <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>İşlemde</option>
                            <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status_note" class="form-label">Durum Notu</label>
                        <textarea class="form-control" id="status_note" name="status_note" rows="3" 
                                  placeholder="Durum değişikliği hakkında not ekleyin..."><?php echo e($order['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="text-end">
                        <a href="<?php echo url('index.php?module=orders&action=view&id=' . $orderId); ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> İptal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Güncelle
                        </button>
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