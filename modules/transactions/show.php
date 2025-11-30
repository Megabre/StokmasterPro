<?php
/**
 * Megabre StokMaster Pro
 * Show Transaction
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Get transaction ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Initialize database connection
    $db = Database::getInstance();
    
    // Get transaction details
    $db->query("SELECT t.*, 
        CONCAT(c.first_name, ' ', c.last_name) as customer_name,
        c.phone as customer_phone,
        c.email as customer_email,
        c.company as customer_company,
        DATE_FORMAT(t.date, '%d.%m.%Y') as formatted_date
        FROM transactions t
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE t.id = :id");
    $db->bind(':id', $id);
    $transaction = $db->single();
    
    if ($transaction) {
        // Include header
        include_once INCLUDES_PATH . 'header.php';
        
        // Payment method names
        $paymentMethods = [
            'cash' => t('transactions_cash', 'Nakit'),
            'check' => t('transactions_check', 'Çek'),
            'promissory' => t('transactions_promissory', 'Senet'),
            'credit_card' => t('transactions_credit_card', 'Kredi Kartı'),
            'bank_transfer' => t('transactions_bank_transfer', 'Havale / EFT')
        ];
        ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title"><?php echo t('transactions_transaction_details', 'İşlem Detayları'); ?></h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=transactions'); ?>"><?php echo t('transactions_title', 'Mali İşlemler'); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo t('transactions_transaction_details', 'İşlem Detayları'); ?></li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="<?php echo url('index.php?module=transactions'); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?php echo t('ui_go_back', 'Geri Dön'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Transaction Details -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><?php echo t('transactions_transaction_info', 'İşlem Bilgileri'); ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 40%;"><?php echo t('transactions_transaction_id', 'İşlem ID'); ?></th>
                                <td><?php echo $transaction['id']; ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('transactions_type', 'Tür'); ?></th>
                                <td>
                                    <?php if ($transaction['type'] == 'payment'): ?>
                                    <span class="badge bg-success"><?php echo t('transactions_type_payment', 'ÖDEME'); ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-danger"><?php echo t('transactions_type_debt', 'BORÇ'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php echo t('transactions_amount', 'Tutar'); ?></th>
                                <td class="fw-bold"><?php echo formatPrice($transaction['amount']); ?> ₺</td>
                            </tr>
                            <tr>
                                <th><?php echo t('transactions_date', 'Tarih'); ?></th>
                                <td><?php echo $transaction['formatted_date']; ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('transactions_payment_method', 'Ödeme Yöntemi'); ?></th>
                                <td><?php echo isset($paymentMethods[$transaction['payment_method']]) ? $paymentMethods[$transaction['payment_method']] : $transaction['payment_method']; ?></td>
                            </tr>
                            <?php if (!empty($transaction['reference_no'])): ?>
                            <tr>
                                <th><?php echo t('transactions_reference_no', 'Referans No'); ?></th>
                                <td><?php echo e($transaction['reference_no']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($transaction['notes'])): ?>
                            <tr>
                                <th><?php echo t('transactions_notes', 'Not'); ?></th>
                                <td><?php echo nl2br(e($transaction['notes'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><?php echo t('transactions_customer_info', 'Müşteri Bilgileri'); ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 40%;"><?php echo t('transactions_customer_name', 'Müşteri'); ?></th>
                                <td>
                                    <a href="<?php echo url('index.php?module=customers&action=edit&id=' . $transaction['customer_id']); ?>">
                                        <?php echo e($transaction['customer_name']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th><?php echo t('transactions_customer_phone', 'Telefon'); ?></th>
                                <td><?php echo e($transaction['customer_phone'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('transactions_customer_email', 'E-posta'); ?></th>
                                <td><?php echo e($transaction['customer_email'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('transactions_customer_company', 'Şirket/Firma'); ?></th>
                                <td><?php echo e($transaction['customer_company'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
    } else {
        // Set error message
        Session::setFlash('error', t('transactions_not_found', 'İşlem bulunamadı.'));
        redirect('index.php?module=transactions');
    }
} else {
    // Set error message
    Session::setFlash('error', t('transactions_invalid_id', 'Geçersiz işlem ID\'si.'));
    redirect('index.php?module=transactions');
}
?> 