<?php
/**
 * Megabre StokMaster Pro
 * Delete Customer
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

// Get customer ID from URL
$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customerId <= 0) {
    Session::setFlash('error', t('customers_invalid_id', 'Geçersiz müşteri ID\'si.'));
    redirect('index.php?module=customers');
}

// Get customer data
$db->query("SELECT c.* FROM customers c WHERE c.id = :id");
$db->bind(':id', $customerId);
$customer = $db->single();

if (!$customer) {
    Session::setFlash('error', t('customers_not_found', 'Müşteri bulunamadı.'));
    redirect('index.php?module=customers');
}

// Check if customer has orders
$db->query("SELECT COUNT(*) as count FROM orders WHERE customer_id = :customer_id");
$db->bind(':customer_id', $customerId);
$ordersCount = $db->single()['count'];

// Check if customer has transactions
$db->query("SELECT COUNT(*) as count FROM transactions WHERE customer_id = :customer_id");
$db->bind(':customer_id', $customerId);
$transactionsCount = $db->single()['count'];

// Process deletion
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=customers');
    }
    
    // Check force delete option
    $forceDelete = isset($_POST['force_delete']) && $_POST['force_delete'] == 1;
    
    // Check if customer can be deleted
    if (!$forceDelete && ($ordersCount > 0 || $transactionsCount > 0)) {
        Session::setFlash('error', t('customers_delete_has_orders', 'Bu müşterinin siparişleri veya mali işlemleri bulunduğu için silinemez. Zorla silmek için "Zorla Sil" seçeneğini işaretleyin.'));
        redirect('index.php?module=customers&action=delete&id=' . $customerId);
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // If force delete, delete related records
        if ($forceDelete) {
            // Delete order items for customer's orders
            $db->query("DELETE oi FROM order_items oi 
                       INNER JOIN orders o ON oi.order_id = o.id 
                       WHERE o.customer_id = :customer_id");
            $db->bind(':customer_id', $customerId);
            $db->execute();
            
            // Delete orders
            $db->query("DELETE FROM orders WHERE customer_id = :customer_id");
            $db->bind(':customer_id', $customerId);
            $db->execute();
            
            // Delete transactions
            $db->query("DELETE FROM transactions WHERE customer_id = :customer_id");
            $db->bind(':customer_id', $customerId);
            $db->execute();
        }
        
        // Log activity before deletion
        logActivity('delete_customer', 'customer', $customerId, [
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'] ?? '',
            'company' => $customer['company'] ?? ''
        ], null, "Müşteri silindi: {$customer['first_name']} {$customer['last_name']}");
        
        // Delete customer
        $db->query("DELETE FROM customers WHERE id = :id");
        $db->bind(':id', $customerId);
        $db->execute();
        
        // Commit transaction
        $db->endTransaction();
        
        // Set success message
        Session::setFlash('success', t('customers_delete_success', 'Müşteri başarıyla silindi.'));
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->cancelTransaction();
        
        // Set error message
        Session::setFlash('error', t('customers_delete_error', 'Müşteri silinirken bir hata oluştu:') . ' ' . $e->getMessage());
    }
    
    // Redirect to customers list
    redirect('index.php?module=customers');
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('customers_delete_title', 'Müşteri Sil'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=customers'); ?>"><?php echo t('customers_title', 'Müşteriler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('customers_delete_title', 'Müşteri Sil'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Delete Customer Confirmation -->
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0"><?php echo t('customers_delete_confirm_title', 'Müşteri Silme Onayı'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong><?php echo t('common_warning', 'Uyarı'); ?>:</strong> <?php echo t('common_irreversible', 'Bu işlem geri alınamaz!'); ?>
                </div>
                
                <p><?php echo t('customers_delete_confirm_text', 'Aşağıdaki müşteriyi silmek üzeresiniz:'); ?></p>
                
                <div class="customer-info mb-4">
                    <div class="row">
                        <div class="col-md-12">
                            <h5><?php echo e($customer['first_name'] . ' ' . $customer['last_name']); ?></h5>
                            <p><strong><?php echo t('customers_delete_phone', 'Telefon:'); ?></strong> <?php echo e($customer['phone']); ?></p>
                            <?php if (!empty($customer['email'])): ?>
                            <p><strong><?php echo t('customers_delete_email', 'E-posta:'); ?></strong> <?php echo e($customer['email']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($customer['company'])): ?>
                            <p><strong><?php echo t('customers_delete_company', 'Firma:'); ?></strong> <?php echo e($customer['company']); ?></p>
                            <?php endif; ?>
                            <p><strong><?php echo t('customers_delete_registration_date', 'Kayıt Tarihi:'); ?></strong> <?php echo formatDateTime($customer['created_at']); ?></p>
                        </div>
                    </div>
                </div>
                
                <?php if ($ordersCount > 0 || $transactionsCount > 0): ?>
                <div class="alert alert-danger">
                    <h6 class="alert-heading"><?php echo t('customers_delete_has_records', 'Bu müşteri aşağıdaki kayıtlarda bulunmaktadır:'); ?></h6>
                    <ul class="mb-0">
                        <?php if ($ordersCount > 0): ?>
                        <li><?php echo $ordersCount; ?> <?php echo t('customers_delete_orders_count', 'adet sipariş'); ?></li>
                        <?php endif; ?>
                        
                        <?php if ($transactionsCount > 0): ?>
                        <li><?php echo $transactionsCount; ?> <?php echo t('customers_delete_transactions_count', 'adet mali işlem'); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo url('index.php?module=customers&action=delete&id=' . $customerId); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <?php if ($ordersCount > 0 || $transactionsCount > 0): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="force_delete" name="force_delete" value="1">
                        <label class="form-check-label" for="force_delete">
                            <strong><?php echo t('customers_delete_force_delete', 'Zorla Sil'); ?></strong> - <?php echo t('customers_delete_force_delete_desc', 'İlişkili tüm kayıtları da sil (siparişler, mali işlemler)'); ?>
                        </label>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <a href="<?php echo url('index.php?module=customers'); ?>" class="btn btn-secondary w-100">
                                <i class="fas fa-times"></i> <?php echo t('customers_delete_cancel', 'İptal'); ?>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-danger w-100" <?php echo ($ordersCount > 0 || $transactionsCount > 0) ? 'id="deleteButton" disabled' : ''; ?>>
                                <i class="fas fa-trash"></i> <?php echo t('customers_delete_confirm_button', 'Müşteriyi Sil'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($ordersCount > 0 || $transactionsCount > 0): ?>
<script>
    $(document).ready(function() {
        // Enable/disable delete button based on force delete checkbox
        $('#force_delete').on('change', function() {
            if ($(this).is(':checked')) {
                $('#deleteButton').prop('disabled', false);
            } else {
                $('#deleteButton').prop('disabled', true);
            }
        });
    });
</script>
<?php endif; ?>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>