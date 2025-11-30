<?php
/**
 * Megabre StokMaster Pro
 * Delete Order
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

// Get order data with customer info
$db->query("SELECT o.*, c.name as customer_name, c.surname as customer_surname,
            DATE_FORMAT(o.order_date, '%d.%m.%Y') as formatted_date
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = :id");
$db->bind(':id', $orderId);
$order = $db->single();

if (!$order) {
    Session::setFlash('error', 'Sipariş bulunamadı.');
    redirect('index.php?module=orders');
}

// Check if order can be deleted (only cancelled orders)
if ($order['status'] != 'cancelled') {
    Session::setFlash('error', 'Sadece iptal edilen siparişler silinebilir.');
    redirect('index.php?module=orders&action=view&id=' . $orderId);
}

// Get order items
$db->query("SELECT oi.*, p.name as product_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id");
$db->bind(':order_id', $orderId);
$orderItems = $db->resultSet();

// Get related transactions
$db->query("SELECT * FROM transactions 
            WHERE reference_type = 'order' AND reference_id = :order_id");
$db->bind(':order_id', $orderId);
$transactions = $db->resultSet();

// Get related stock movements
$db->query("SELECT * FROM stock_movements 
            WHERE notes = :notes");
$db->bind(':notes', 'Sipariş #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
$stockMovements = $db->resultSet();

// Process deletion
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=orders');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Delete order items
        $db->query("DELETE FROM order_items WHERE order_id = :order_id");
        $db->bind(':order_id', $orderId);
        $db->execute();
        
        // Delete related transactions
        $db->query("DELETE FROM transactions 
                   WHERE reference_type = 'order' AND reference_id = :order_id");
        $db->bind(':order_id', $orderId);
        $db->execute();
        
        // Delete related stock movements
        $db->query("DELETE FROM stock_movements 
                   WHERE notes = :notes");
        $db->bind(':notes', 'Sipariş #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
        $db->execute();
        
        // Log activity before deletion
        logActivity('delete_order', 'order', $orderId, [
            'customer_id' => $order['customer_id'],
            'order_date' => $order['order_date'],
            'status' => $order['status'],
            'total_amount' => $order['total_amount'] ?? 0,
            'grand_total' => $order['grand_total'] ?? 0
        ], null, "Sipariş silindi: #{$orderId}");
        
        // Delete order
        $db->query("DELETE FROM orders WHERE id = :id");
        $db->bind(':id', $orderId);
        $db->execute();
        
        // Commit transaction
        $db->endTransaction();
        
        // Set success message
        Session::setFlash('success', 'Sipariş başarıyla silindi.');
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->cancelTransaction();
        
        // Set error message
        Session::setFlash('error', 'Sipariş silinirken bir hata oluştu: ' . $e->getMessage());
    }
    
    // Redirect to orders list
    redirect('index.php?module=orders');
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Sipariş Sil</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=orders'); ?>">Siparişler</a></li>
                <li class="breadcrumb-item active">Sipariş Sil</li>
            </ul>
        </div>
    </div>
</div>

<!-- Delete Order Confirmation -->
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">Sipariş Silme Onayı</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Uyarı:</strong> Bu işlem geri alınamaz!
                </div>
                
                <p>Aşağıdaki siparişi ve ilgili tüm kayıtları silmek üzeresiniz:</p>
                
                <!-- Order Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Sipariş Bilgileri</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Sipariş No:</strong> #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                <p><strong>Müşteri:</strong> <?php echo e($order['customer_name'] . ' ' . $order['customer_surname']); ?></p>
                                <p><strong>Tarih:</strong> <?php echo $order['formatted_date']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Durum:</strong> <span class="badge bg-danger">İptal</span></p>
                                <p><strong>Toplam Tutar:</strong> <?php echo formatPrice($order['total_amount']); ?> ₺</p>
                                <p><strong>Ürün Sayısı:</strong> <?php echo count($orderItems); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items -->
                <?php if (!empty($orderItems)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Sipariş Kalemleri</h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <?php foreach ($orderItems as $item): ?>
                            <li>
                                <?php echo e($item['product_name']); ?> - 
                                <?php echo number_format($item['quantity'], 2); ?> adet x 
                                <?php echo formatPrice($item['unit_price']); ?> ₺ = 
                                <?php echo formatPrice($item['total']); ?> ₺
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Related Records -->
                <div class="alert alert-danger">
                    <h6 class="alert-heading">Silinecek İlişkili Kayıtlar:</h6>
                    <ul class="mb-0">
                        <li><?php echo count($orderItems); ?> adet sipariş kalemi</li>
                        <li><?php echo count($transactions); ?> adet mali işlem kaydı</li>
                        <li><?php echo count($stockMovements); ?> adet stok hareketi</li>
                    </ul>
                </div>
                
                <?php if (!empty($order['notes'])): ?>
                <div class="alert alert-info">
                    <strong>Sipariş Notu:</strong><br>
                    <?php echo nl2br(e($order['notes'])); ?>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo url('index.php?module=orders&action=delete&id=' . $orderId); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <a href="<?php echo url('index.php?module=orders&action=view&id=' . $orderId); ?>" class="btn btn-secondary w-100">
                                <i class="fas fa-times"></i> İptal
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-trash"></i> Siparişi Sil
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Important Notes -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle"></i> Önemli Notlar</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Bu işlem geri alınamaz ve tüm ilişkili kayıtlar kalıcı olarak silinecektir</li>
                    <li>Stok hareketleri silinecek ancak ürün stok seviyeleri değişmeyecektir</li>
                    <li>Müşteri mali kayıtları (borç/alacak) silinecektir</li>
                    <li>Sipariş geçmişi tamamen kaybolacaktır</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>