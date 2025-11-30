<?php
/**
 * Megabre StokMaster Pro
 * Dış Gider Silme Sayfası
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

// Get expense ID
$expenseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($expenseId <= 0) {
    Session::setFlash('error', t('expenses_invalid_id', 'Geçersiz gider ID\'si.'));
    redirect('index.php?module=transactions&action=expenses');
}

// Get expense
$db->query("SELECT * FROM expenses WHERE id = :id");
$db->bind(':id', $expenseId);
$expense = $db->single();

if (!$expense) {
    Session::setFlash('error', t('expenses_not_found', 'Gider kaydı bulunamadı.'));
    redirect('index.php?module=transactions&action=expenses');
}

// Process deletion
if (isPost()) {
    if (!validateCsrf()) {
        redirect('index.php?module=transactions&action=expenses');
    }
    
    try {
        // Log activity before deletion
        logActivity('delete_expense', 'expense', $expenseId, [
            'category' => $expense['category'],
            'description' => $expense['description'] ?? '',
            'amount' => $expense['amount'],
            'date' => $expense['date'],
            'payment_method' => $expense['payment_method'] ?? 'cash'
        ], null, "Gider silindi: {$expense['category']} - " . formatPrice($expense['amount']) . " ₺");
        
        $db->query("DELETE FROM expenses WHERE id = :id");
        $db->bind(':id', $expenseId);
        $db->execute();
        
        Session::setFlash('success', t('expenses_delete_success', 'Gider başarıyla silindi.'));
        redirect('index.php?module=transactions&action=expenses');
        
    } catch (PDOException $e) {
        Session::setFlash('error', t('expenses_delete_error', 'Gider silinirken bir hata oluştu:') . ' ' . $e->getMessage());
        redirect('index.php?module=transactions&action=expenses');
    }
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('expenses_delete_title', 'Gider Sil'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=transactions'); ?>"><?php echo t('transactions_title', 'Mali İşlemler'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=transactions&action=expenses'); ?>"><?php echo t('expenses_title', 'Dış Giderler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('delete', 'Sil'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Confirmation Card -->
<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <?php echo t('expenses_delete_confirm_title', 'Gider Silme Onayı'); ?>
                </h5>
            </div>
            <div class="card-body">
                <p><?php echo t('expenses_delete_confirm_message', 'Bu gideri silmek istediğinizden emin misiniz?'); ?></p>
                
                <div class="alert alert-warning">
                    <strong><?php echo t('expenses_category', 'Kategori'); ?>:</strong> <?php echo e($expense['category']); ?><br>
                    <strong><?php echo t('transactions_amount', 'Tutar'); ?>:</strong> <?php echo formatPrice($expense['amount']); ?> ₺<br>
                    <strong><?php echo t('transactions_date', 'Tarih'); ?>:</strong> <?php echo date('d.m.Y', strtotime($expense['date'])); ?>
                </div>
                
                <form action="<?php echo url('index.php?module=transactions&action=delete-expense&id=' . $expenseId); ?>" method="post">
                    <?php echo csrfField(); ?>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> <?php echo t('delete', 'Sil'); ?>
                    </button>
                    <a href="<?php echo url('index.php?module=transactions&action=expenses'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo t('cancel', 'İptal'); ?>
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include_once INCLUDES_PATH . 'footer.php';
?>

