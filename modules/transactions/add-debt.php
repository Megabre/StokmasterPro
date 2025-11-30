<?php
/**
 * Megabre StokMaster Pro
 * Borç Ekleme/Düzenleme Sayfası
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Kullanıcı giriş yapmış mı kontrol et
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Veritabanı bağlantısını başlat
$db = Database::getInstance();

// Düzenleme için mevcut borcu kontrol et
$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditing = ($transactionId > 0);

$transaction = null;
if ($isEditing) {
    // İşlem verilerini al
    $db->query("SELECT t.*, c.first_name as customer_name, c.last_name as customer_surname 
                FROM transactions t 
                JOIN customers c ON t.customer_id = c.id 
                WHERE t.id = :id AND t.type = 'debt'");
    $db->bind(':id', $transactionId);
    $transaction = $db->single();
    
    if (!$transaction) {
        Session::setFlash('error', t('transactions_debt_not_found', 'Borç kaydı bulunamadı.'));
        redirect('index.php?module=transactions');
    }
    
    // Düzenleme sırasında seçili müşteriyi ayarla
    $preSelectedCustomerId = $transaction['customer_id'];
} else {
    // URL'den müşteri ön seçimi (varsa)
    $preSelectedCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
}

// Tüm müşterileri al
$db->query("SELECT id, first_name, last_name, company FROM customers ORDER BY first_name ASC");
$customers = $db->resultSet();

// Aktif para birimlerini al
$db->query("SELECT id, code, name, prefix, suffix, is_default FROM currencies WHERE is_active = 1 ORDER BY is_default DESC, code ASC");
$currencies = $db->resultSet();

// Varsayılan para birimini belirle
$defaultCurrencyId = null;
foreach ($currencies as $currency) {
    if ($currency['is_default']) {
        $defaultCurrencyId = $currency['id'];
        break;
    }
}
if (!$defaultCurrencyId && !empty($currencies)) {
    $defaultCurrencyId = $currencies[0]['id'];
}

// Formdan gelen değerleri tutacak değişkenler
$formData = [
    'customer_id' => $preSelectedCustomerId,
    'amount' => '',
    'date' => date('Y-m-d'),
    'payment_method' => '',
    'reference_no' => '',
    'is_installment' => false,
    'installment_count' => 3,
    'notes' => '',
    'currency_id' => $defaultCurrencyId
];

// Düzenleme ise form verilerini doldur
if ($isEditing && $transaction) {
    $formData = [
        'customer_id' => $transaction['customer_id'],
        'amount' => formatPrice($transaction['amount'], 2),
        'date' => $transaction['date'],
        'payment_method' => $transaction['payment_method'],
        'reference_no' => $transaction['reference_no'],
        'is_installment' => false, // Düzenlemede taksit değiştirilemez
        'installment_count' => 0,
        'notes' => $transaction['notes'],
        'currency_id' => $transaction['currency_id'] ?? $defaultCurrencyId
    ];
}

// Hata mesajlarını tutacak dizi
$errors = [];

// Müşteri bakiyesini hesaplayan fonksiyon
function getCustomerBalance($customerId) {
    $db = Database::getInstance();
    
    // Toplam ödemeleri al
    $db->query("SELECT COALESCE(SUM(amount), 0) as total_payments FROM transactions 
                WHERE customer_id = :customer_id AND type = 'payment'");
    $db->bind(':customer_id', $customerId);
    $totalPayments = $db->single()['total_payments'];
    
    // Toplam borçları al
    $db->query("SELECT COALESCE(SUM(amount), 0) as total_debts FROM transactions 
                WHERE customer_id = :customer_id AND type = 'debt'");
    $db->bind(':customer_id', $customerId);
    $totalDebts = $db->single()['total_debts'];
    
    // Bakiyeyi hesapla
    $balance = $totalPayments - $totalDebts;
    
    return [
        'total_debts' => $totalDebts,
        'total_payments' => $totalPayments,
        'balance' => $balance
    ];
}

// Müşteri bakiyesi
$customerBalance = null;
if ($preSelectedCustomerId > 0) {
    $customerBalance = getCustomerBalance($preSelectedCustomerId);
}

// Form gönderilmiş mi kontrol et
if (isPost()) {
    // CSRF token doğrula
    if (!validateCsrf()) {
        redirect('index.php?module=transactions');
    }
    
    // Form verilerini al
    $formData = [
        'customer_id' => post('customer_id', $preSelectedCustomerId),
        'amount' => post('amount'),
        'date' => post('date'),
        'payment_method' => post('payment_method'),
        'reference_no' => post('reference_no'),
        'is_installment' => isset($_POST['is_installment']),
        'installment_count' => post('installment_count', 3),
        'notes' => post('notes'),
        'currency_id' => post('currency_id', $defaultCurrencyId)
    ];
    
    // Form verilerini doğrula
    if (empty($formData['customer_id'])) {
        $errors[] = t('transactions_payment_customer_required', 'Müşteri seçimi gereklidir.');
    }
    
    if (empty($formData['amount']) || !is_numeric(str_replace(',', '.', $formData['amount'])) || floatval(str_replace(',', '.', $formData['amount'])) <= 0) {
        $errors[] = t('transactions_debt_amount_required', 'Geçerli bir borç tutarı giriniz.');
    }
    
    if (empty($formData['date'])) {
        $errors[] = t('transactions_payment_date_required', 'İşlem tarihi gereklidir.');
    }
    
    if (empty($formData['payment_method'])) {
        $errors[] = t('transactions_payment_method_required', 'Ödeme yöntemi gereklidir.');
    }
    
    if (empty($formData['currency_id'])) {
        $errors[] = t('transactions_currency_required', 'Para birimi seçimi gereklidir.');
    }
    
    if (!$isEditing && $formData['is_installment'] && ($formData['installment_count'] < 2 || !is_numeric($formData['installment_count']) || $formData['installment_count'] > 60)) {
        $errors[] = t('transactions_payment_installment_count_required', 'Geçerli bir taksit sayısı giriniz (2-60 arası).');
    }
    
    // Tutar girişi
    $amount = floatval(str_replace(',', '.', $formData['amount']));
    
    // Hatalar yoksa işleme devam et
    if (empty($errors)) {
        
        // Veritabanı işlemi başlat
        $db->beginTransaction();
        
        try {
            if ($isEditing) {
                // Prepare old data for logging
                $oldData = [
                    'customer_id' => $transaction['customer_id'],
                    'amount' => $transaction['amount'],
                    'date' => $transaction['date'],
                    'payment_method' => $transaction['payment_method'] ?? '',
                    'reference_no' => $transaction['reference_no'] ?? '',
                    'notes' => $transaction['notes'] ?? '',
                    'currency_id' => $transaction['currency_id'] ?? null
                ];
                
                // Prepare new data for logging
                $newData = [
                    'customer_id' => $formData['customer_id'],
                    'amount' => $amount,
                    'date' => $formData['date'],
                    'payment_method' => $formData['payment_method'],
                    'reference_no' => $formData['reference_no'],
                    'notes' => $formData['notes'],
                    'currency_id' => $formData['currency_id']
                ];
                
                // Mevcut borcu güncelle
                $db->query("UPDATE transactions SET 
                            customer_id = :customer_id, 
                            amount = :amount, 
                            date = :date, 
                            payment_method = :payment_method, 
                            reference_no = :reference_no, 
                            notes = :notes,
                            currency_id = :currency_id,
                            updated_at = NOW() 
                            WHERE id = :id");
                
                $db->bind(':customer_id', $formData['customer_id']);
                $db->bind(':amount', $amount);
                $db->bind(':date', $formData['date']);
                $db->bind(':payment_method', $formData['payment_method']);
                $db->bind(':reference_no', $formData['reference_no']);
                $db->bind(':notes', $formData['notes']);
                $db->bind(':currency_id', $formData['currency_id']);
                $db->bind(':id', $transactionId);
                $db->execute();
                
                // Log activity with detailed changes
                logActivity('update_debt', 'transaction', $transactionId, $oldData, $newData, "Borç #{$transactionId} güncellendi");
                
                // Başarı mesajı
                Session::setFlash('success', t('transactions_debt_update_success', 'Borç başarıyla güncellendi.'));
            } else {
                if ($formData['is_installment']) {
                    // Taksit tutarını hesapla (2 ondalık basamağa yuvarla)
                    $installmentAmount = round($amount / $formData['installment_count'], 2);
                    
                    // Son taksiti hesapla (yuvarlama farklarını hesaba katmak için)
                    $lastInstallmentAmount = $amount - ($installmentAmount * ($formData['installment_count'] - 1));
                    
                    // Taksit tarihlerini oluşturmak için işlem tarihini ayrıştır
                    $baseDate = new DateTime($formData['date']);
                    
                    // Taksitleri oluştur
                    for ($i = 0; $i < $formData['installment_count']; $i++) {
                        // Taksit tarihini ayarla (geçerli ay + i)
                        $installmentDate = clone $baseDate;
                        $installmentDate->modify("+$i month");
                        
                        // Taksit tutarını ayarla (son taksit, yuvarlama nedeniyle farklı olabilir)
                        $currentAmount = ($i == $formData['installment_count'] - 1) ? $lastInstallmentAmount : $installmentAmount;
                        
                        // Taksit notunu oluştur
                        $installmentNote = $formData['notes'] . " (Taksit " . ($i + 1) . "/" . $formData['installment_count'] . ")";
                        
                        // Taksiti ekle
                        $db->query("INSERT INTO transactions (
                                    customer_id, type, amount, date, payment_method, 
                                    reference_no, is_installment, installment_number, installment_count, notes,
                                    currency_id
                                ) VALUES (
                                    :customer_id, 'debt', :amount, :date, :payment_method, 
                                    :reference_no, 1, :installment_number, :installment_count, :notes,
                                    :currency_id
                                )");
                        
                        $db->bind(':customer_id', $formData['customer_id']);
                        $db->bind(':amount', $currentAmount);
                        $db->bind(':date', $installmentDate->format('Y-m-d'));
                        $db->bind(':payment_method', $formData['payment_method']);
                        $db->bind(':reference_no', $formData['reference_no']);
                        $db->bind(':installment_number', $i + 1);
                        $db->bind(':installment_count', $formData['installment_count']);
                        $db->bind(':notes', $installmentNote);
                        $db->bind(':currency_id', $formData['currency_id']);
                        $db->execute();
                    }
                    
                    // Başarı mesajı
                    Session::setFlash('success', $formData['installment_count'] . ' ' . t('transactions_debt_installment_success', 'taksitli borç başarıyla eklendi.'));
                } else {
                    // Tek borç ekle
                    $db->query("INSERT INTO transactions (
                                customer_id, type, amount, date, payment_method, 
                                reference_no, is_installment, installment_number, installment_count, notes,
                                currency_id
                            ) VALUES (
                                :customer_id, 'debt', :amount, :date, :payment_method, 
                                :reference_no, 0, 0, 0, :notes,
                                :currency_id
                            )");
                    
                    $db->bind(':customer_id', $formData['customer_id']);
                    $db->bind(':amount', $amount);
                    $db->bind(':date', $formData['date']);
                    $db->bind(':payment_method', $formData['payment_method']);
                    $db->bind(':reference_no', $formData['reference_no']);
                    $db->bind(':notes', $formData['notes']);
                    $db->bind(':currency_id', $formData['currency_id']);
                    $db->execute();
                    
                    $transactionId = $db->lastInsertId();
                    
                    // Log activity
                    $customerName = $customers[array_search($formData['customer_id'], array_column($customers, 'id'))]['first_name'] ?? '';
                    logActivity('add_debt', 'transaction', $transactionId, null, [
                        'customer_id' => $formData['customer_id'],
                        'amount' => $amount,
                        'payment_method' => $formData['payment_method'],
                        'date' => $formData['date']
                    ], "Borç eklendi: {$amount} ₺ - Müşteri: {$customerName}");
                    
                    // Başarı mesajı
                    Session::setFlash('success', t('transactions_debt_add_success', 'Borç başarıyla eklendi.'));
                }
            }
            
            // İşlemi sonlandır
            $db->endTransaction();
            
            // İşlemler sayfasına yönlendir
            redirect('index.php?module=transactions');
            
        } catch (PDOException $e) {
            // Hata durumunda işlemi geri al
            $db->cancelTransaction();
            
            $errors[] = t('transactions_debt_add_error', 'İşlem sırasında bir hata oluştu:') . ' ' . $e->getMessage();
            error_log('Borç ekleme hatası: ' . $e->getMessage());
        }
    }
}

// Üst bilgiyi dahil et
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Sayfa Başlığı -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo $isEditing ? t('transactions_debt_edit', 'Borç Düzenle') : t('transactions_debt_add', 'Borç Ekle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=transactions'); ?>"><?php echo t('transactions_title', 'Mali İşlemler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo $isEditing ? t('transactions_debt_edit', 'Borç Düzenle') : t('transactions_debt_add', 'Borç Ekle'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Hataları Göster -->
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

<!-- Ana İçerik -->
<div class="row">
    <!-- Form Alanı -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus-circle"></i> 
                    <?php echo $isEditing ? t('transactions_debt_info_edit', 'Borç Bilgilerini Düzenle') : t('transactions_debt_info_add', 'Yeni Borç Ekle'); ?>
                </h5>
            </div>
            <div class="card-body">
                <!-- Borç açıklaması -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong><?php echo t('transactions_debt_what_is', 'Borç Nedir?'); ?></strong> <?php echo t('transactions_debt_description', 'Müşterinin size olan borcu olarak kaydedilir. Örneğin, müşteriden senet almak, veresiye satış yapmak veya vadeli çek almak gibi durumlar için kullanabilirsiniz.'); ?>
                </div>
                
                <!-- Müşteri Seçim Formu -->
                <?php if (!$isEditing): ?>
                <form id="customerSelectionForm" method="get" action="">
                    <input type="hidden" name="module" value="transactions">
                    <input type="hidden" name="action" value="add-debt">
                    
                    <div class="row mb-3">
                        <label for="customer_id" class="col-md-3 col-form-label required"><?php echo t('transactions_customer', 'Müşteri'); ?></label>
                        <div class="col-md-9">
                            <select class="form-select select2" id="customer_id" name="customer_id" onchange="this.form.submit()">
                                <option value=""><?php echo t('transactions_payment_select_customer', 'Müşteri Seçin'); ?></option>
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo $preSelectedCustomerId == $customer['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                    <?php echo !empty($customer['company']) ? ' (' . e($customer['company']) . ')' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <!-- Ana Form -->
                <form action="<?php echo url('index.php?module=transactions&action=add-debt' . ($isEditing ? '&id=' . $transactionId : '') . ($preSelectedCustomerId ? '&customer_id=' . $preSelectedCustomerId : '')); ?>" method="post" id="debtForm">
                    <?php echo csrfField(); ?>
                    
                    <!-- Düzenleme sırasında salt-okunur müşteri bilgisi -->
                    <?php if ($isEditing): ?>
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label"><?php echo t('transactions_customer', 'Müşteri'); ?></label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" value="<?php echo e($transaction['customer_name'] . ' ' . $transaction['customer_surname']); ?>" readonly>
                            <input type="hidden" name="customer_id" value="<?php echo $transaction['customer_id']; ?>">
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="customer_id" value="<?php echo $preSelectedCustomerId; ?>">
                    <?php endif; ?>
                    
                    <!-- Para Birimi Alanı -->
                    <div class="row mb-3">
                        <label for="currency_id" class="col-md-3 col-form-label required"><?php echo t('transactions_currency', 'Para Birimi'); ?></label>
                        <div class="col-md-9">
                            <select class="form-select" id="currency_id" name="currency_id" required>
                                <option value=""><?php echo t('orders_select', 'Seçiniz'); ?></option>
                                <?php if (!empty($currencies)): ?>
                                <?php foreach ($currencies as $currency): ?>
                                <option value="<?php echo $currency['id']; ?>" <?php echo ($formData['currency_id'] == $currency['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($currency['name']); ?> (<?php echo e($currency['code']); ?>)
                                    <?php if ($currency['is_default']): ?>
                                    - <?php echo t('currencies_default', 'Varsayılan'); ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <option value="" disabled><?php echo t('transactions_no_currencies', 'Aktif para birimi bulunamadı'); ?></option>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted"><?php echo t('transactions_currency_desc', 'Borç hangi para biriminde kaydedilecek'); ?></small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="amount" class="col-md-3 col-form-label required"><?php echo t('transactions_debt_amount', 'Borç Tutarı'); ?></label>
                        <div class="col-md-9">
                            <div class="input-group">
                                <input type="text" class="form-control" id="amount" name="amount" placeholder="0.00" 
                                       value="<?php echo $formData['amount']; ?>">
                                <span class="input-group-text" id="currencySymbolDisplay">₺</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="date" class="col-md-3 col-form-label required"><?php echo t('transactions_payment_date', 'İşlem Tarihi'); ?></label>
                        <div class="col-md-9">
                            <input type="date" class="form-control" id="date" name="date" required 
                                   value="<?php echo $formData['date']; ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="payment_method" class="col-md-3 col-form-label required"><?php echo t('transactions_payment_method', 'Ödeme Yöntemi'); ?></label>
                        <div class="col-md-9">
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value=""><?php echo t('orders_select', 'Seçiniz'); ?></option>
                                <option value="cash" <?php echo $formData['payment_method'] == 'cash' ? 'selected' : ''; ?>><?php echo t('transactions_cash', 'Nakit'); ?></option>
                                <option value="check" <?php echo $formData['payment_method'] == 'check' ? 'selected' : ''; ?>><?php echo t('transactions_check', 'Çek'); ?></option>
                                <option value="promissory" <?php echo $formData['payment_method'] == 'promissory' ? 'selected' : ''; ?>><?php echo t('transactions_promissory', 'Senet'); ?></option>
                                <option value="credit_card" <?php echo $formData['payment_method'] == 'credit_card' ? 'selected' : ''; ?>><?php echo t('transactions_credit_card', 'Kredi Kartı'); ?></option>
                                <option value="bank_transfer" <?php echo $formData['payment_method'] == 'bank_transfer' ? 'selected' : ''; ?>><?php echo t('transactions_bank_transfer', 'Havale / EFT'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="reference_no" class="col-md-3 col-form-label"><?php echo t('transactions_reference_no', 'Referans/Belge No'); ?></label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="reference_no" name="reference_no" 
                                   value="<?php echo e($formData['reference_no']); ?>">
                            <div class="form-text"><?php echo t('transactions_debt_reference_desc', 'Çek no, senet no, fatura no vb.'); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!$isEditing): ?>
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label"><?php echo t('transactions_payment_installment', 'Taksitli İşlem'); ?></label>
                        <div class="col-md-9">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_installment" name="is_installment" value="1" <?php echo $formData['is_installment'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_installment"><?php echo t('transactions_payment_installment_split', 'Taksitlere böl'); ?></label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="installmentOptions" class="row mb-3" style="display: <?php echo $formData['is_installment'] ? 'block' : 'none'; ?>">
                        <label for="installment_count" class="col-md-3 col-form-label required"><?php echo t('transactions_payment_installment_count', 'Taksit Sayısı'); ?></label>
                        <div class="col-md-9">
                            <div class="input-group">
                                <input type="number" class="form-control" id="installment_count" name="installment_count" min="2" max="60" value="<?php echo $formData['installment_count']; ?>">
                                <span class="input-group-text"><?php echo t('transactions_payment_installment_months', 'ay'); ?></span>
                            </div>
                            <div class="form-text"><?php echo t('transactions_payment_installment_desc', 'Taksitler aylık olarak otomatik hesaplanır. İlk taksit bugünün tarihine, diğerleri sırayla sonraki aylara kaydedilir.'); ?></div>
                            
                            <div class="mt-3" id="installmentPreview">
                                <h6 class="mb-2"><?php echo t('transactions_payment_installment_preview', 'Taksit Önizleme'); ?></h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th><?php echo t('transactions_payment_installment_number', 'Taksit'); ?></th>
                                                <th><?php echo t('transactions_payment_installment_date', 'Tarih'); ?></th>
                                                <th><?php echo t('transactions_payment_installment_amount', 'Tutar'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="installmentPreviewBody">
                                            <!-- JavaScript ile doldurulacak -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <label for="notes" class="col-md-3 col-form-label"><?php echo t('transactions_payment_notes', 'Not/Açıklama'); ?></label>
                        <div class="col-md-9">
                            <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="500"><?php echo e($formData['notes']); ?></textarea>
                            <div class="form-text"><span id="notesCounter">0</span>/500 <?php echo t('transactions_payment_notes_counter', 'karakter'); ?></div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-9 offset-md-3">
                            <a href="<?php echo url('index.php?module=transactions'); ?>" class="btn btn-secondary me-2">
                                <i class="fas fa-arrow-left"></i> <?php echo t('cancel', 'İptal'); ?>
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-save"></i> <?php echo $isEditing ? t('update', 'Güncelle') : t('save', 'Kaydet'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sağ Panel: Müşteri Bilgileri ve Son İşlemler -->
    <div class="col-md-4">
        <!-- Müşteri Bakiye Bilgileri -->
        <?php if ($preSelectedCustomerId > 0 && $customerBalance): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user"></i> <?php echo t('transactions_payment_customer_balance', 'Müşteri Bakiye Bilgileri'); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <div class="text-center">
                            <h6 class="mb-1"><?php echo t('transactions_payment_total_debts', 'Toplam Borç'); ?></h6>
                            <h5 class="text-danger mb-0"><?php echo formatPrice($customerBalance['total_debts']); ?> ₺</h5>
                        </div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <div class="text-center">
                            <h6 class="mb-1"><?php echo t('transactions_payment_total_payments', 'Toplam Ödeme'); ?></h6>
                            <h5 class="text-success mb-0"><?php echo formatPrice($customerBalance['total_payments']); ?> ₺</h5>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="text-center">
                            <h6 class="mb-1"><?php echo t('transactions_payment_net_status', 'Net Durum'); ?></h6>
                            <h5 class="<?php echo $customerBalance['balance'] >= 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                                <?php echo formatPrice(abs($customerBalance['balance'])); ?> ₺
                            </h5>
                            <span class="badge <?php echo $customerBalance['balance'] > 0 ? 'bg-success' : ($customerBalance['balance'] < 0 ? 'bg-danger' : 'bg-secondary'); ?>">
                                <?php echo $customerBalance['balance'] > 0 ? t('transactions_payment_creditor', 'Alacaklı') : ($customerBalance['balance'] < 0 ? t('transactions_payment_debtor', 'Borçlu') : t('transactions_payment_neutral', 'Nötr')); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Müşteri işlemleri bağlantısı -->
                <div class="text-center mt-3">
                    <a href="<?php echo url('index.php?module=customers&action=view&id=' . $preSelectedCustomerId); ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-history"></i> <?php echo t('transactions_payment_customer_transactions', 'Müşteri İşlemleri'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Son Borçlar -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo t('transactions_debt_recent_debts', 'Son Borçlar'); ?></h5>
            </div>
            <div class="card-body">
                <?php
                // Son 5 borcu al
                $db->query("SELECT t.*, c.first_name as customer_name, c.last_name as customer_surname, 
                           DATE_FORMAT(t.date, '%d.%m.%Y') as formatted_date
                           FROM transactions t
                           JOIN customers c ON t.customer_id = c.id
                           WHERE t.type = 'debt'
                           ORDER BY t.id DESC
                           LIMIT 5");
                $lastDebts = $db->resultSet();
                
                if (!empty($lastDebts)):
                ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th><?php echo t('transactions_payment_recent_customer', 'Müşteri'); ?></th>
                                <th><?php echo t('transactions_payment_recent_amount', 'Tutar'); ?></th>
                                <th><?php echo t('transactions_payment_recent_date', 'Tarih'); ?></th>
                                <th><?php echo t('transactions_payment_recent_method', 'Yöntem'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lastDebts as $debt): ?>
                            <tr>
                                <td><?php echo e($debt['customer_name'] . ' ' . $debt['customer_surname']); ?></td>
                                <td class="text-end"><?php echo formatPrice($debt['amount']); ?> ₺</td>
                                <td><?php echo $debt['formatted_date']; ?></td>
                                <td>
                                    <?php
                                    $paymentMethods = [
                                        'cash' => t('transactions_cash', 'Nakit'),
                                        'check' => t('transactions_check', 'Çek'),
                                        'promissory' => t('transactions_promissory', 'Senet'),
                                        'credit_card' => t('transactions_credit_card', 'Kredi Kartı'),
                                        'bank_transfer' => t('transactions_bank_transfer', 'Havale / EFT')
                                    ];
                                    echo isset($paymentMethods[$debt['payment_method']]) ? $paymentMethods[$debt['payment_method']] : $debt['payment_method'];
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> <?php echo t('transactions_debt_no_debts', 'Henüz borç kaydı bulunmuyor.'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('amount');
    const currencySelect = document.getElementById('currency_id');
    const currencySymbolDisplay = document.getElementById('currencySymbolDisplay');
    
    // Para birimi verilerini JavaScript'e aktar
    const currenciesData = <?php echo json_encode(array_map(function($c) {
        return [
            'id' => $c['id'],
            'prefix' => $c['prefix'] ?? '',
            'suffix' => $c['suffix'] ?? ''
        ];
    }, $currencies)); ?>;
    
    // Para birimi seçildiğinde sembolü güncelle
    function updateCurrencySymbols() {
        let selectedCurrencyId = null;
        if (currencySelect && currencySelect.value) {
            selectedCurrencyId = parseInt(currencySelect.value);
        } else {
            // Varsayılan para birimini kullan
            const defaultCurrencyId = <?php echo $defaultCurrencyId ? $defaultCurrencyId : 'null'; ?>;
            if (defaultCurrencyId) {
                selectedCurrencyId = defaultCurrencyId;
                if (currencySelect) {
                    currencySelect.value = defaultCurrencyId;
                }
            }
        }
        
        if (!selectedCurrencyId) {
            // Hiçbir para birimi yoksa varsayılan sembolü kullan
            if (currencySymbolDisplay) {
                currencySymbolDisplay.textContent = '₺';
            }
            return;
        }
        
        const selectedCurrency = currenciesData.find(c => c.id === selectedCurrencyId);
        
        if (selectedCurrency) {
            // Prefix varsa onu kullan, yoksa suffix'i kullan, hiçbiri yoksa varsayılan ₺
            const symbol = selectedCurrency.prefix || selectedCurrency.suffix || '₺';
            
            if (currencySymbolDisplay) {
                currencySymbolDisplay.textContent = symbol;
            }
        } else {
            if (currencySymbolDisplay) {
                currencySymbolDisplay.textContent = '₺';
            }
        }
    }
    
    // Sayfa yüklendiğinde varsayılan para birimini ayarla
    setTimeout(function() {
        const defaultCurrencyId = <?php echo $defaultCurrencyId ? $defaultCurrencyId : 'null'; ?>;
        
        if (defaultCurrencyId && currencySelect && !currencySelect.value) {
            currencySelect.value = defaultCurrencyId;
        }
        
        // Para birimi değişikliklerini dinle
        if (currencySelect) {
            currencySelect.addEventListener('change', updateCurrencySymbols);
        }
        
        // İlk yüklemede sembolleri güncelle
        updateCurrencySymbols();
    }, 100);
    
    // Notlar için karakter sayacı
    const notesInput = document.getElementById('notes');
    const notesCounter = document.getElementById('notesCounter');
    
    if (notesInput && notesCounter) {
        notesInput.addEventListener('input', function() {
            const currentLength = this.value.length;
            notesCounter.textContent = currentLength;
        });
        
        // Sayfa yüklendiğinde karakter sayısını güncelle
        notesCounter.textContent = notesInput.value.length;
    }
    
    // Taksit seçeneği göster/gizle
    const isInstallmentCheckbox = document.getElementById('is_installment');
    const installmentOptions = document.getElementById('installmentOptions');
    
    if (isInstallmentCheckbox && installmentOptions) {
        isInstallmentCheckbox.addEventListener('change', function() {
            installmentOptions.style.display = this.checked ? 'block' : 'none';
            if (this.checked) {
                updateInstallmentPreview();
            }
        });
    }
    
    // Taksit önizleme güncellemesi
    const installmentCountInput = document.getElementById('installment_count');
    const dateInput = document.getElementById('date');
    
    if (amountInput && installmentCountInput && dateInput) {
        amountInput.addEventListener('input', updateInstallmentPreview);
        installmentCountInput.addEventListener('input', updateInstallmentPreview);
        dateInput.addEventListener('change', updateInstallmentPreview);
        
        // Sayfa yüklendiğinde taksit önizlemeyi güncelle
        if (isInstallmentCheckbox && isInstallmentCheckbox.checked) {
            updateInstallmentPreview();
        }
    }
    
    // Taksit önizleme güncelleme fonksiyonu
    function updateInstallmentPreview() {
        const installmentPreviewBody = document.getElementById('installmentPreviewBody');
        if (!installmentPreviewBody) return;
        
        const amount = parseFloat(amountInput.value.replace(/\./g, '').replace(',', '.')) || 0;
        const count = parseInt(installmentCountInput.value) || 3;
        const baseDate = new Date(dateInput.value || new Date());
        
        if (amount <= 0 || count < 2) {
            installmentPreviewBody.innerHTML = '<tr><td colspan="3" class="text-center"><?php echo t('transactions_payment_preview_invalid', 'Lütfen geçerli bir tutar ve taksit sayısı girin.'); ?></td></tr>';
            return;
        }
        
        // Taksit tutarını hesapla (2 ondalık basamağa yuvarla)
        const installmentAmount = Math.round((amount / count) * 100) / 100;
        
        // Son taksiti hesapla (yuvarlama farklarını hesaba katmak için)
        const lastInstallmentAmount = amount - (installmentAmount * (count - 1));
        
        // Önizleme satırlarını oluştur
        let rows = '';
        for (let i = 0; i < count; i++) {
            const installmentDate = new Date(baseDate);
            installmentDate.setMonth(baseDate.getMonth() + i);
            
            const formattedDate = installmentDate.toLocaleDateString('tr-TR');
            const currentAmount = (i === count - 1) ? lastInstallmentAmount : installmentAmount;
            
            rows += `<tr>
                <td>${i + 1}/${count}</td>
                <td>${formattedDate}</td>
                <td class="text-end">${formatPrice(currentAmount)} ₺</td>
            </tr>`;
        }
        
        installmentPreviewBody.innerHTML = rows;
    }
    
    // Fiyat biçimlendirme yardımcı fonksiyonu
});
</script>

<?php
// Alt bilgiyi dahil et
include_once INCLUDES_PATH . 'footer.php';
?>