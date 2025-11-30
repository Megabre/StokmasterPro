<?php
/**
 * Megabre StokMaster Pro
 * View Order
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

// Get order details
$db->query("
    SELECT o.*, 
           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           c.company as customer_company,
           c.phone as customer_phone,
           c.email as customer_email,
           c.address as customer_address,
           DATE_FORMAT(o.order_date, '%d.%m.%Y') as formatted_date,
           DATE_FORMAT(o.created_at, '%d.%m.%Y %H:%i') as created_date,
           DATE_FORMAT(o.updated_at, '%d.%m.%Y %H:%i') as updated_date
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.id = :id
");
$db->bind(':id', $orderId);
$order = $db->single();

if (!$order) {
    Session::setFlash('error', 'Sipariş bulunamadı.');
    redirect('index.php?module=orders');
}

// Get order items
$db->query("
    SELECT oi.*, p.name as product_name, p.sku, p.barcode, c.name as category_name,
           (oi.quantity * oi.unit_price) as total
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = :order_id
");
$db->bind(':order_id', $orderId);
$items = $db->resultSet();

// Calculate totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += ($item['quantity'] * $item['unit_price']);
}
$order['subtotal'] = $subtotal;

// Get related transactions
$db->query("SELECT *, DATE_FORMAT(date, '%d.%m.%Y') as formatted_date
            FROM transactions 
            WHERE order_id = :order_id
            ORDER BY date DESC, id DESC");
$db->bind(':order_id', $orderId);
$transactions = $db->resultSet();

// Get customer balance
$db->query("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END), 0) as balance,
        COALESCE(SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END), 0) as total_debt,
        COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) as total_payment
    FROM transactions 
    WHERE customer_id = :customer_id
");
$db->bind(':customer_id', $order['customer_id']);
$customerBalance = $db->single();

// Calculate order payment status
$orderDebt = 0;
$orderPayment = 0;
foreach ($transactions as $transaction) {
    if ($transaction['type'] == 'debt') {
        $orderDebt += $transaction['amount'];
    } else {
        $orderPayment += $transaction['amount'];
    }
}
$orderBalance = $orderPayment - $orderDebt;

// Define status colors and texts
$statusConfig = [
    'pending' => ['class' => 'warning', 'text' => 'Beklemede'],
    'processing' => ['class' => 'info', 'text' => 'İşleniyor'],
    'completed' => ['class' => 'success', 'text' => 'Tamamlandı'],
    'cancelled' => ['class' => 'danger', 'text' => 'İptal Edildi'],
    'shipped' => ['class' => 'primary', 'text' => 'Kargoya Verildi']
];

$status = $statusConfig[$order['status']] ?? ['class' => 'secondary', 'text' => ucfirst($order['status'])];

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
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Sipariş Detayı #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=orders'); ?>">Siparişler</a></li>
                <li class="breadcrumb-item active">Sipariş Detayı</li>
            </ul>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                <?php if ($order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                <a href="<?php echo url('index.php?module=orders&action=edit&id=' . $order['id']); ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Düzenle
                </a>
                <?php endif; ?>
                <a href="<?php echo url('index.php?module=orders&action=update_status&id=' . $order['id']); ?>" class="btn btn-info">
                    <i class="fas fa-sync"></i> Durum Güncelle
                </a>
                <a href="<?php echo url('index.php?module=orders'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Geri
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Order Details -->
    <div class="col-md-8">
        <!-- Order Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sipariş Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Sipariş No:</strong> #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
                        <p><strong>Sipariş Tarihi:</strong> <?php echo $order['formatted_date']; ?></p>
                        <p><strong>Oluşturma:</strong> <?php echo $order['created_date']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Durum:</strong> <span class="badge bg-<?php echo $status['class']; ?>"><?php echo $status['text']; ?></span></p>
                        <?php if ($order['updated_at']): ?>
                        <p><strong>Son Güncelleme:</strong> <?php echo $order['updated_date']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($order['notes'])): ?>
                <hr>
                <p><strong>Sipariş Notu:</strong></p>
                <p><?php echo nl2br(e($order['notes'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sipariş Kalemleri</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ürün</th>
                                <th>SKU/Barkod</th>
                                <th class="text-end">Miktar</th>
                                <th class="text-end">Birim Fiyat</th>
                                <th class="text-end">Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="<?php echo url('index.php?module=products&action=edit&id=' . $item['product_id']); ?>">
                                        <?php echo e($item['product_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($item['sku'])): ?>
                                        <small>SKU: <?php echo e($item['sku']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($item['barcode'])): ?>
                                        <br><small>Barkod: <?php echo e($item['barcode']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?php echo number_format($item['quantity'], 2); ?></td>
                                <td class="text-end"><?php echo formatPrice($item['unit_price']); ?> ₺</td>
                                <td class="text-end"><?php echo formatPrice($item['total']); ?> ₺</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Ara Toplam:</strong></td>
                                <td class="text-end"><strong><?php echo formatPrice($order['subtotal']); ?> ₺</strong></td>
                            </tr>
                            <?php if ($order['vat_rate'] > 0): ?>
                            <tr>
                                <td colspan="5" class="text-end">KDV (%<?php echo $order['vat_rate']; ?>):</td>
                                <td class="text-end"><?php echo formatPrice($order['vat_amount']); ?> ₺</td>
                            </tr>
                            <?php endif; ?>
                            <tr class="table-primary">
                                <td colspan="5" class="text-end"><strong>Genel Toplam:</strong></td>
                                <td class="text-end"><strong><?php echo formatPrice($order['total_amount']); ?> ₺</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Related Transactions -->
        <?php if (!empty($transactions)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Mali İşlemler</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Tür</th>
                                <th>Açıklama</th>
                                <th class="text-end">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo $transaction['formatted_date']; ?></td>
                                <td>
                                    <?php if ($transaction['type'] == 'debt'): ?>
                                        <span class="badge bg-danger">Borç</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Ödeme</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($transaction['notes']); ?></td>
                                <td class="text-end">
                                    <?php if ($transaction['type'] == 'debt'): ?>
                                        <span class="text-danger">+<?php echo formatPrice($transaction['amount']); ?> ₺</span>
                                    <?php else: ?>
                                        <span class="text-success">-<?php echo formatPrice($transaction['amount']); ?> ₺</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Sipariş Durumu:</strong></td>
                                <td class="text-end">
                                    <?php if ($orderBalance >= 0): ?>
                                        <strong class="text-success">Ödendi</strong>
                                    <?php else: ?>
                                        <strong class="text-danger"><?php echo formatPrice(abs($orderBalance)); ?> ₺ Borç</strong>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Customer Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Müşteri Bilgileri</h5>
            </div>
            <div class="card-body">
                <p>
                    <strong>
                        <a href="<?php echo url('index.php?module=customers&action=edit&id=' . $order['customer_id']); ?>">
                            <?php echo e($order['customer_name']); ?>
                        </a>
                    </strong>
                </p>
                
                <?php if (!empty($order['customer_company'])): ?>
                <p><i class="fas fa-building"></i> <?php echo e($order['customer_company']); ?></p>
                <?php endif; ?>
                
                <p><i class="fas fa-phone"></i> <?php echo formatPhone($order['customer_phone']); ?></p>
                
                <?php if (!empty($order['customer_email'])): ?>
                <p><i class="fas fa-envelope"></i> <?php echo e($order['customer_email']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($order['customer_address'])): ?>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo nl2br(e($order['customer_address'])); ?></p>
                <?php endif; ?>
                
                <hr>
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Toplam Borç:</span>
                    <strong class="text-danger"><?php echo formatPrice($customerBalance['total_debt']); ?> ₺</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Toplam Ödeme:</span>
                    <strong class="text-success"><?php echo formatPrice($customerBalance['total_payment']); ?> ₺</strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>Net Durum:</span>
                    <?php 
                    $netBalance = $customerBalance['balance'];
                    if ($netBalance >= 0): 
                    ?>
                        <strong class="text-success"><?php echo formatPrice($netBalance); ?> ₺ Alacak</strong>
                    <?php else: ?>
                        <strong class="text-danger"><?php echo formatPrice(abs($netBalance)); ?> ₺ Borç</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">İşlemler</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                    <a href="<?php echo url('index.php?module=orders&action=edit&id=' . $order['id']); ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Düzenle
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($orderBalance < 0): ?>
                    <a href="<?php echo url('index.php?module=transactions&action=add-payment&customer_id=' . $order['customer_id'] . '&order_id=' . $order['id']); ?>" class="btn btn-success">
                        <i class="fas fa-money-bill"></i> Ödeme Al
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo url('index.php?module=orders&action=print&id=' . $order['id']); ?>" class="btn btn-info" target="_blank">
                        <i class="fas fa-print"></i> Yazdır
                    </a>
                    
                    <?php if ($order['status'] == 'cancelled'): ?>
                    <a href="<?php echo url('index.php?module=orders&action=delete&id=' . $order['id']); ?>" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Sil
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Sipariş Özeti</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Ürün Sayısı:</span>
                    <strong><?php echo count($items); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Toplam Adet:</span>
                    <strong>
                        <?php 
                        $totalQuantity = 0;
                        foreach ($items as $item) {
                            $totalQuantity += $item['quantity'];
                        }
                        echo number_format($totalQuantity, 2);
                        ?>
                    </strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Sipariş Tutarı:</span>
                    <strong><?php echo formatPrice($order['total_amount']); ?> ₺</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Toplam Ödeme:</span>
                    <strong class="text-success"><?php echo formatPrice($orderPayment); ?> ₺</strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>Kalan Borç:</span>
                    <?php if ($orderBalance < 0): ?>
                        <strong class="text-danger"><?php echo formatPrice(abs($orderBalance)); ?> ₺</strong>
                    <?php else: ?>
                        <strong class="text-success">Ödendi</strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Update status
    $('.update-status').on('click', function(e) {
        e.preventDefault();
        
        const newStatus = $(this).data('status');
        const statusText = $(this).text().trim();
        
        if (confirm(`Sipariş durumunu "${statusText}" olarak güncellemek istediğinize emin misiniz?`)) {
            $.ajax({
                url: '<?php echo url('api/orders.php?action=update_status'); ?>',
                type: 'POST',
                data: {
                    order_id: <?php echo $orderId; ?>,
                    status: newStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Hata: ' + response.message);
                    }
                },
                error: function() {
                    alert('Durum güncellenirken bir hata oluştu.');
                }
            });
        }
    });
});
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>