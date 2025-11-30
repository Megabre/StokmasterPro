<?php
/**
 * Megabre StokMaster Pro
 * Dış Gider Ekleme/Düzenleme Sayfası
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

// Check if editing
$expenseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditing = ($expenseId > 0);

$expense = null;
if ($isEditing) {
    $db->query("SELECT * FROM expenses WHERE id = :id");
    $db->bind(':id', $expenseId);
    $expense = $db->single();
    
    if (!$expense) {
        Session::setFlash('error', t('expenses_not_found', 'Gider kaydı bulunamadı.'));
        redirect('index.php?module=transactions&action=expenses');
    }
}

// Form data
$formData = [
    'category' => '',
    'description' => '',
    'amount' => '',
    'date' => date('Y-m-d'),
    'payment_method' => 'cash',
    'reference_no' => '',
    'supplier' => '',
    'notes' => ''
];

if ($isEditing && $expense) {
    $formData = [
        'category' => $expense['category'],
        'description' => $expense['description'],
        'amount' => formatPrice($expense['amount'], 2),
        'date' => $expense['date'],
        'payment_method' => $expense['payment_method'],
        'reference_no' => $expense['reference_no'],
        'supplier' => $expense['supplier'],
        'notes' => $expense['notes']
    ];
}

// Common expense categories - These are actual category names, not translations
$expenseCategories = [
    'Elektrik',
    'Su',
    'Doğalgaz',
    'İnternet',
    'Telefon',
    'Kira',
    'Maaş',
    'Vergi',
    'Sigorta',
    'Yakıt',
    'Bakım/Onarım',
    'Temizlik',
    'Kırtasiye',
    'Pazarlama',
    'Danışmanlık',
    'Diğer'
];
// Note: Categories are stored as-is in database, not translated

// Errors
$errors = [];

// Process form
if (isPost()) {
    if (!validateCsrf()) {
        redirect('index.php?module=transactions&action=expenses');
    }
    
    $formData = [
        'category' => post('category'),
        'description' => post('description'),
        'amount' => post('amount'),
        'date' => post('date'),
        'payment_method' => post('payment_method'),
        'reference_no' => post('reference_no'),
        'supplier' => post('supplier'),
        'notes' => post('notes')
    ];
    
    // Validation
    if (empty($formData['category'])) {
        $errors[] = t('expenses_category_required', 'Kategori gereklidir.');
    }
    
    if (empty($formData['amount']) || !is_numeric(str_replace(',', '.', $formData['amount'])) || floatval(str_replace(',', '.', $formData['amount'])) <= 0) {
        $errors[] = t('expenses_amount_required', 'Geçerli bir tutar giriniz.');
    }
    
    if (empty($formData['date'])) {
        $errors[] = t('expenses_date_required', 'Tarih gereklidir.');
    }
    
    if (empty($formData['payment_method'])) {
        $errors[] = t('expenses_payment_method_required', 'Ödeme yöntemi gereklidir.');
    }
    
    if (empty($errors)) {
        $amount = floatval(str_replace(',', '.', $formData['amount']));
        $userId = $auth->getUserId();
        
        $db->beginTransaction();
        
        try {
            if ($isEditing) {
                // Prepare old data for logging
                $oldData = [
                    'category' => $expense['category'],
                    'description' => $expense['description'] ?? '',
                    'amount' => $expense['amount'],
                    'date' => $expense['date'],
                    'payment_method' => $expense['payment_method'] ?? 'cash',
                    'reference_no' => $expense['reference_no'] ?? '',
                    'supplier' => $expense['supplier'] ?? '',
                    'notes' => $expense['notes'] ?? ''
                ];
                
                $db->query("UPDATE expenses SET 
                            category = :category,
                            description = :description,
                            amount = :amount,
                            date = :date,
                            payment_method = :payment_method,
                            reference_no = :reference_no,
                            supplier = :supplier,
                            notes = :notes,
                            updated_at = NOW()
                            WHERE id = :id");
                
                $db->bind(':category', $formData['category']);
                $db->bind(':description', $formData['description']);
                $db->bind(':amount', $amount);
                $db->bind(':date', $formData['date']);
                $db->bind(':payment_method', $formData['payment_method']);
                $db->bind(':reference_no', $formData['reference_no']);
                $db->bind(':supplier', $formData['supplier']);
                $db->bind(':notes', $formData['notes']);
                $db->bind(':id', $expenseId);
                $db->execute();
                
                // Prepare new data for logging
                $newData = [
                    'category' => $formData['category'],
                    'description' => $formData['description'] ?? '',
                    'amount' => $amount,
                    'date' => $formData['date'],
                    'payment_method' => $formData['payment_method'],
                    'reference_no' => $formData['reference_no'] ?? '',
                    'supplier' => $formData['supplier'] ?? '',
                    'notes' => $formData['notes'] ?? ''
                ];
                
                // Log activity
                logActivity('update_expense', 'expense', $expenseId, $oldData, $newData, "Gider güncellendi: {$formData['category']} - " . formatPrice($amount) . " ₺");
                
                Session::setFlash('success', t('expenses_update_success', 'Gider başarıyla güncellendi.'));
            } else {
                $db->query("INSERT INTO expenses (
                            category, description, amount, date, payment_method,
                            reference_no, supplier, notes, created_by
                        ) VALUES (
                            :category, :description, :amount, :date, :payment_method,
                            :reference_no, :supplier, :notes, :created_by
                        )");
                
                $db->bind(':category', $formData['category']);
                $db->bind(':description', $formData['description']);
                $db->bind(':amount', $amount);
                $db->bind(':date', $formData['date']);
                $db->bind(':payment_method', $formData['payment_method']);
                $db->bind(':reference_no', $formData['reference_no']);
                $db->bind(':supplier', $formData['supplier']);
                $db->bind(':notes', $formData['notes']);
                $db->bind(':created_by', $userId);
                $db->execute();
                
                $newExpenseId = $db->lastInsertId();
                
                // Log activity
                logActivity('add_expense', 'expense', $newExpenseId, null, [
                    'category' => $formData['category'],
                    'description' => $formData['description'] ?? '',
                    'amount' => $amount,
                    'date' => $formData['date'],
                    'payment_method' => $formData['payment_method']
                ], "Yeni gider eklendi: {$formData['category']} - " . formatPrice($amount) . " ₺");
                
                Session::setFlash('success', t('expenses_add_success', 'Gider başarıyla eklendi.'));
            }
            
            $db->endTransaction();
            redirect('index.php?module=transactions&action=expenses');
            
        } catch (PDOException $e) {
            $db->cancelTransaction();
            $errors[] = t('expenses_add_error', 'İşlem sırasında bir hata oluştu:') . ' ' . $e->getMessage();
            error_log('Gider ekleme hatası: ' . $e->getMessage());
        }
    }
}

include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo $isEditing ? t('expenses_edit_title', 'Gider Düzenle') : t('expenses_add_title', 'Gider Ekle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=transactions'); ?>"><?php echo t('transactions_title', 'Mali İşlemler'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=transactions&action=expenses'); ?>"><?php echo t('expenses_title', 'Dış Giderler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo $isEditing ? t('edit', 'Düzenle') : t('add', 'Ekle'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="<?php echo url('index.php?module=transactions&action=expenses'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo t('ui_go_back', 'Geri Dön'); ?>
            </a>
        </div>
    </div>
</div>

<!-- Errors -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Form -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-minus-circle"></i> 
                    <?php echo $isEditing ? t('expenses_edit_info', 'Gider Bilgilerini Düzenle') : t('expenses_add_info', 'Yeni Gider Ekle'); ?>
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=transactions&action=' . ($isEditing ? 'edit-expense' : 'add-expense') . ($isEditing ? '&id=' . $expenseId : '')); ?>" method="post" id="expenseForm">
                    <?php echo csrfField(); ?>
                    
                    <div class="row mb-3">
                        <label for="category" class="col-md-3 col-form-label required"><?php echo t('expenses_category', 'Kategori'); ?></label>
                        <div class="col-md-9">
                            <select class="form-select" id="category" name="category" required>
                                <option value=""><?php echo t('ui_select', 'Seçiniz'); ?></option>
                                <?php foreach ($expenseCategories as $cat): ?>
                                <option value="<?php echo e($cat); ?>" <?php echo $formData['category'] == $cat ? 'selected' : ''; ?>>
                                    <?php echo e($cat); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="description" class="col-md-3 col-form-label"><?php echo t('expenses_description', 'Açıklama'); ?></label>
                        <div class="col-md-9">
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo e($formData['description']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="supplier" class="col-md-3 col-form-label"><?php echo t('expenses_supplier', 'Tedarikçi/Firma'); ?></label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="supplier" name="supplier" value="<?php echo e($formData['supplier']); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="amount" class="col-md-3 col-form-label required"><?php echo t('transactions_amount', 'Tutar (₺)'); ?></label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="amount" name="amount" value="<?php echo e($formData['amount']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="date" class="col-md-3 col-form-label required"><?php echo t('transactions_date', 'Tarih'); ?></label>
                        <div class="col-md-9">
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo e($formData['date']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="payment_method" class="col-md-3 col-form-label required"><?php echo t('transactions_payment_method', 'Ödeme Yöntemi'); ?></label>
                        <div class="col-md-9">
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash" <?php echo $formData['payment_method'] == 'cash' ? 'selected' : ''; ?>>
                                    <?php echo t('transactions_cash', 'Nakit'); ?>
                                </option>
                                <option value="check" <?php echo $formData['payment_method'] == 'check' ? 'selected' : ''; ?>>
                                    <?php echo t('transactions_check', 'Çek'); ?>
                                </option>
                                <option value="promissory_note" <?php echo $formData['payment_method'] == 'promissory_note' ? 'selected' : ''; ?>>
                                    <?php echo t('transactions_promissory', 'Senet'); ?>
                                </option>
                                <option value="credit_card" <?php echo $formData['payment_method'] == 'credit_card' ? 'selected' : ''; ?>>
                                    <?php echo t('transactions_credit_card', 'Kredi Kartı'); ?>
                                </option>
                                <option value="bank_transfer" <?php echo $formData['payment_method'] == 'bank_transfer' ? 'selected' : ''; ?>>
                                    <?php echo t('transactions_bank_transfer', 'Havale / EFT'); ?>
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="reference_no" class="col-md-3 col-form-label"><?php echo t('transactions_reference_no', 'Referans No'); ?></label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="reference_no" name="reference_no" value="<?php echo e($formData['reference_no']); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="notes" class="col-md-3 col-form-label"><?php echo t('transactions_notes', 'Notlar'); ?></label>
                        <div class="col-md-9">
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo e($formData['notes']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-9 offset-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo t('save', 'Kaydet'); ?>
                            </button>
                            <a href="<?php echo url('index.php?module=transactions&action=expenses'); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> <?php echo t('cancel', 'İptal'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('expenses_help_tips', 'Yardım & İpuçları'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <ul class="mb-0">
                        <li><?php echo t('expenses_help_tip1', 'Gider kategorisi seçimi zorunludur.'); ?></li>
                        <li><?php echo t('expenses_help_tip2', 'Tutar ve tarih bilgileri mutlaka doldurulmalıdır.'); ?></li>
                        <li><?php echo t('expenses_help_tip3', 'Referans numarası fatura, çek, senet numarası olabilir.'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Amount formatting
    $('#amount').on('input', function() {
        var value = $(this).val().replace(/[^\d,.-]/g, '');
        $(this).val(value);
    });
});
</script>

<?php
include_once INCLUDES_PATH . 'footer.php';
?>

