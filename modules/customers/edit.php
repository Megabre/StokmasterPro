<?php
/**
 * Megabre StokMaster Pro
 * Edit Customer
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

// Initialize dynamic fields class
$dynamicFields = new DynamicFields();

// Get customer ID from URL
$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customerId <= 0) {
    Session::setFlash('error', t('customers_invalid_id', 'Geçersiz müşteri ID\'si.'));
    redirect('index.php?module=customers');
}

// Get customer data
$db->query("SELECT * FROM customers WHERE id = :id");
$db->bind(':id', $customerId);
$customer = $db->single();

if (!$customer) {
    Session::setFlash('error', t('customers_not_found', 'Müşteri bulunamadı.'));
    redirect('index.php?module=customers');
}

// Get customer balance
$db->query("SELECT COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) as total_payments,
            COALESCE(SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END), 0) as total_debts
            FROM transactions 
            WHERE customer_id = :customer_id");
$db->bind(':customer_id', $customerId);
$balance = $db->single();

$totalPayments = $balance['total_payments'];
$totalDebts = $balance['total_debts'];
$netBalance = $totalPayments - $totalDebts;

// Get customer dynamic fields
$customerFields = $dynamicFields->getCustomerFields($customerId);

// Get active customer fields (system-wide dynamic fields)
$db->query("SELECT * FROM customer_fields WHERE (customer_id = 0 OR customer_id IS NULL) AND is_active = 1 ORDER BY field_order ASC, id ASC");
$systemFields = $db->resultSet();

// Get system field values for this customer
$systemFieldValues = [];
if (!empty($systemFields)) {
    try {
        $fieldIds = array_column($systemFields, 'id');
        if (!empty($fieldIds)) {
            $placeholders = [];
            foreach ($fieldIds as $index => $fieldId) {
                $placeholders[] = ':field_id_' . $index;
            }
            $placeholdersStr = implode(',', $placeholders);
            
            $db->query("SELECT field_id, field_value FROM customer_field_values WHERE customer_id = :customer_id AND field_id IN ($placeholdersStr)");
            $db->bind(':customer_id', $customerId);
            foreach ($fieldIds as $index => $fieldId) {
                $db->bind(':field_id_' . $index, $fieldId);
            }
            $values = $db->resultSet();
            foreach ($values as $value) {
                $systemFieldValues[$value['field_id']] = $value['field_value'];
            }
        }
    } catch (PDOException $e) {
        // Table might not exist, skip
        error_log('Customer field values table not found: ' . $e->getMessage());
    }
}

// Get all customer tags
$db->query("SELECT * FROM customer_tags WHERE is_active = 1 ORDER BY name ASC");
$allTags = $db->resultSet();

// Get customer tags
$db->query("SELECT tag_id FROM customer_tag_relations WHERE customer_id = :customer_id");
$db->bind(':customer_id', $customerId);
$customerTagRelations = $db->resultSet();
$customerTagIds = array_column($customerTagRelations, 'tag_id');

// Get customer orders
$db->query("SELECT o.*, 
            COUNT(oi.id) AS item_count,
            (SELECT COUNT(*) FROM stock_movements sm WHERE sm.order_id = o.id) AS has_stock_movements
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.customer_id = :customer_id
            GROUP BY o.id
            ORDER BY o.order_date DESC, o.id DESC
            LIMIT 10");
$db->bind(':customer_id', $customerId);
$customerOrders = $db->resultSet();

// Get customer transactions
$db->query("SELECT t.*, 
            o.id AS order_id
            FROM transactions t
            LEFT JOIN orders o ON t.order_id = o.id
            WHERE t.customer_id = :customer_id
            ORDER BY t.date DESC, t.id DESC
            LIMIT 10");
$db->bind(':customer_id', $customerId);
$customerTransactions = $db->resultSet();

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=customers&action=edit&id=' . $customerId);
    }
    
    // Get form data
    $firstName = post('first_name');
    $lastName = post('last_name');
    $phone = post('phone');
    $email = post('email');
    $company = post('company');
    $address = post('address');
    $notes = post('notes');
    $fields = post('fields');
    $tags = post('tags', []); // Müşteri etiketleri
    
    // Validate form data
    $errors = [];
    
    if (empty($firstName)) {
        $errors[] = t('customers_first_name_required', 'Ad alanı gereklidir.');
    }
    
    if (empty($lastName)) {
        $errors[] = t('customers_last_name_required', 'Soyad alanı gereklidir.');
    }
    
    if (empty($phone)) {
        $errors[] = t('customers_phone_required', 'Telefon alanı gereklidir.');
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('customers_email_invalid', 'Geçerli bir e-posta adresi giriniz.');
    }
    
    if (empty($errors)) {
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Prepare old data for logging
            $oldData = [
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'] ?? '',
                'company' => $customer['company'] ?? '',
                'address' => $customer['address'] ?? '',
                'notes' => $customer['notes'] ?? ''
            ];
            
            // Prepare new data for logging
            $newData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'email' => $email ?? '',
                'company' => $company ?? '',
                'address' => $address ?? '',
                'notes' => $notes ?? ''
            ];
            
            // Update customer
            $db->query("UPDATE customers SET 
                       first_name = :first_name,
                       last_name = :last_name,
                       phone = :phone,
                       email = :email,
                       company = :company,
                       address = :address,
                       notes = :notes,
                       updated_at = NOW()
                       WHERE id = :id");
            $db->bind(':first_name', $firstName);
            $db->bind(':last_name', $lastName);
            $db->bind(':phone', $phone);
            $db->bind(':email', $email);
            $db->bind(':company', $company);
            $db->bind(':address', $address);
            $db->bind(':notes', $notes);
            $db->bind(':id', $customerId);
            $db->execute();
            
            // Log activity with detailed changes
            logActivity('update_customer', 'customer', $customerId, $oldData, $newData, "Müşteri #{$customerId} güncellendi");
            
            // Handle system dynamic fields (customer_fields table)
            if (!empty($systemFields)) {
                foreach ($systemFields as $field) {
                    $fieldKey = $field['field_key'];
                    $fieldValue = post($fieldKey, '');
                    
                    try {
                        // Delete existing value
                        $db->query("DELETE FROM customer_field_values WHERE customer_id = :customer_id AND field_id = :field_id");
                        $db->bind(':customer_id', $customerId);
                        $db->bind(':field_id', $field['id']);
                        $db->execute();
                        
                        // Insert new value if not empty
                        if (!empty($fieldValue)) {
                            $db->query("INSERT INTO customer_field_values (customer_id, field_id, field_value) 
                                       VALUES (:customer_id, :field_id, :field_value)");
                            $db->bind(':customer_id', $customerId);
                            $db->bind(':field_id', $field['id']);
                            $db->bind(':field_value', $fieldValue);
                            $db->execute();
                        }
                    } catch (PDOException $e) {
                        // Table might not exist, skip
                        error_log('Customer field values table not found: ' . $e->getMessage());
                    }
                }
            }
            
            // Handle customer custom fields (müşteriye özel alanlar)
            // First, delete existing fields
            $dynamicFields->deleteCustomerFields($customerId);
            
            // Then add new fields
            if ($fields && is_array($fields)) {
                foreach ($fields as $field) {
                    $fieldName = $field['name'] ?? '';
                    $fieldType = $field['type'] ?? '';
                    $fieldValue = $field['value'] ?? '';
                    
                    if (!empty($fieldName) && !empty($fieldType)) {
                        $dynamicFields->createCustomerField($customerId, $fieldName, $fieldType, $fieldValue);
                    }
                }
            }
            
            // Handle customer tags
            // Delete existing tag relations
            $db->query("DELETE FROM customer_tag_relations WHERE customer_id = :customer_id");
            $db->bind(':customer_id', $customerId);
            $db->execute();
            
            // Add new tag relations
            if ($tags && is_array($tags)) {
                $tagIds = [];
                foreach ($tags as $tagId) {
                    $tagId = (int)$tagId;
                    if ($tagId > 0) {
                        $tagIds[] = $tagId;
                        
                        $db->query("INSERT INTO customer_tag_relations (customer_id, tag_id) VALUES (:customer_id, :tag_id)");
                        $db->bind(':customer_id', $customerId);
                        $db->bind(':tag_id', $tagId);
                        $db->execute();
                    }
                }
                
                // Update customer tag_ids cache
                $db->query("UPDATE customers SET tag_ids = :tag_ids WHERE id = :id");
                $db->bind(':tag_ids', implode(',', $tagIds));
                $db->bind(':id', $customerId);
                $db->execute();
            } else {
                // Clear tag_ids cache if no tags
                $db->query("UPDATE customers SET tag_ids = NULL WHERE id = :id");
                $db->bind(':id', $customerId);
                $db->execute();
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', t('customers_update_success', 'Müşteri bilgileri başarıyla güncellendi.'));
            
            // Redirect to customer edit page
            redirect('index.php?module=customers&action=edit&id=' . $customerId);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            $errors[] = t('customers_update_error', 'Müşteri güncellenirken bir hata oluştu:') . ' ' . $e->getMessage();
        }
    }
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('customers_edit_title', 'Müşteri Düzenle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=customers'); ?>"><?php echo t('customers_title', 'Müşteriler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('customers_edit_title', 'Müşteri Düzenle'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                <a href="<?php echo url('index.php?module=transactions&action=add-payment&customer_id=' . $customerId); ?>" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> <?php echo t('customers_add_payment_button', 'Ödeme Ekle'); ?>
                </a>
                <a href="<?php echo url('index.php?module=transactions&action=add-debt&customer_id=' . $customerId); ?>" class="btn btn-danger">
                    <i class="fas fa-minus-circle"></i> <?php echo t('customers_add_debt_button', 'Borç Ekle'); ?>
                </a>
                <a href="<?php echo url('index.php?module=orders&action=add&customer_id=' . $customerId); ?>" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> <?php echo t('customers_create_order', 'Sipariş Oluştur'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Display Errors -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Customer Balance -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                    <div class="text-center">
                        <h6 class="mb-1"><?php echo t('orders_total_debt', 'Toplam Borç:'); ?></h6>
                        <h5 class="text-danger mb-0"><?php echo formatPrice($totalDebts); ?> ₺</h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <h6 class="mb-1"><?php echo t('orders_total_payment', 'Toplam Ödeme:'); ?></h6>
                        <h5 class="text-success mb-0"><?php echo formatPrice($totalPayments); ?> ₺</h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <h6 class="mb-1"><?php echo t('orders_net_status', 'Net Durum:'); ?></h6>
                        <h5 class="mb-0"><?php echo formatPrice(abs($netBalance)); ?> ₺</h5>
                        <span class="badge <?php echo $netBalance > 0 ? 'bg-success' : ($netBalance < 0 ? 'bg-danger' : 'bg-secondary'); ?>">
                            <?php echo $netBalance > 0 ? t('customers_balance_creditor', 'Alacaklı') : ($netBalance < 0 ? t('customers_balance_debtor', 'Borçlu') : t('customers_balance_neutral', 'Nötr')); ?>
                        </span>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Content -->
    <div class="col-md-8">
        <!-- Edit Customer Form -->
        <form action="<?php echo url('index.php?module=customers&action=edit&id=' . $customerId); ?>" method="post" id="customerForm">
            <?php echo csrfField(); ?>
            
            <!-- Basic Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('customers_edit_basic_info', 'Temel Bilgiler'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label required"><?php echo t('customers_first_name', 'Ad'); ?></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e($customer['first_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label required"><?php echo t('customers_last_name', 'Soyad'); ?></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e($customer['last_name']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label required"><?php echo t('customers_phone', 'Telefon'); ?></label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo e($customer['phone']); ?>" required>
                                <small class="text-muted"><?php echo t('customers_phone_example', 'Örnek: 532 123 45 67 veya +90 532 123 45 67'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label"><?php echo t('customers_email', 'E-posta'); ?></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo e($customer['email']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="company" class="form-label"><?php echo t('customers_company', 'Şirket/Firma'); ?></label>
                        <input type="text" class="form-control" id="company" name="company" value="<?php echo e($customer['company']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label"><?php echo t('customers_address', 'Adres'); ?></label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo e($customer['address']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label"><?php echo t('customers_notes', 'Notlar'); ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo e($customer['notes']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Customer Tags -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('customers_tags', 'Müşteri Etiketleri'); ?></h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><?php echo t('customers_tags_desc', 'Müşteriye etiket atayarak siparişlerde otomatik indirim uygulanabilir.'); ?></p>
                    
                    <div class="row">
                        <?php foreach ($allTags as $tag): ?>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>" 
                                       id="tag_<?php echo $tag['id']; ?>" 
                                       <?php echo in_array($tag['id'], $customerTagIds) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tag_<?php echo $tag['id']; ?>">
                                    <span class="badge" style="background-color: <?php echo e($tag['color']); ?>; color: white;">
                                        <?php echo e($tag['name']); ?>
                                    </span>
                                    <?php if ($tag['discount_percentage'] > 0): ?>
                                        <small class="text-muted">(%<?php echo number_format($tag['discount_percentage'], 2); ?> <?php echo t('tags_discount_label', 'indirim'); ?>)</small>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php if (!empty($tag['description'])): ?>
                            <small class="text-muted d-block ms-4"><?php echo e($tag['description']); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($allTags)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <?php echo t('customers_no_tags', 'Henüz etiket tanımlanmamış. Etiketler ayarlar bölümünden eklenebilir.'); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- System Dynamic Fields -->
            <?php if (!empty($systemFields)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Dinamik Alanlar</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($systemFields as $field): ?>
                        <div class="col-md-6 mb-3">
                            <label for="field_<?php echo $field['field_key']; ?>" class="form-label">
                                <?php echo e($field['field_name']); ?>
                                <?php if ($field['is_required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php
                            $fieldValue = post($field['field_key'], $systemFieldValues[$field['id']] ?? '');
                            $fieldOptions = !empty($field['field_options']) ? explode("\n", $field['field_options']) : [];
                            
                            switch ($field['field_type']):
                                case 'text':
                                    ?>
                                    <input type="text" 
                                           class="form-control" 
                                           id="field_<?php echo $field['field_key']; ?>" 
                                           name="<?php echo $field['field_key']; ?>" 
                                           value="<?php echo e($fieldValue); ?>"
                                           <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                    <?php
                                    break;
                                    
                                case 'textarea':
                                    ?>
                                    <textarea class="form-control" 
                                              id="field_<?php echo $field['field_key']; ?>" 
                                              name="<?php echo $field['field_key']; ?>" 
                                              rows="3"
                                              <?php echo $field['is_required'] ? 'required' : ''; ?>><?php echo e($fieldValue); ?></textarea>
                                    <?php
                                    break;
                                    
                                case 'number':
                                    ?>
                                    <input type="number" 
                                           class="form-control" 
                                           id="field_<?php echo $field['field_key']; ?>" 
                                           name="<?php echo $field['field_key']; ?>" 
                                           value="<?php echo e($fieldValue); ?>"
                                           step="any"
                                           <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                    <?php
                                    break;
                                    
                                case 'select':
                                    ?>
                                    <select class="form-select" 
                                            id="field_<?php echo $field['field_key']; ?>" 
                                            name="<?php echo $field['field_key']; ?>"
                                            <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($fieldOptions as $option): ?>
                                            <option value="<?php echo e(trim($option)); ?>" <?php echo $fieldValue == trim($option) ? 'selected' : ''; ?>>
                                                <?php echo e(trim($option)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php
                                    break;
                                    
                                case 'date':
                                    ?>
                                    <input type="date" 
                                           class="form-control" 
                                           id="field_<?php echo $field['field_key']; ?>" 
                                           name="<?php echo $field['field_key']; ?>" 
                                           value="<?php echo e($fieldValue); ?>"
                                           <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                    <?php
                                    break;
                                    
                                default:
                                    ?>
                                    <input type="text" 
                                           class="form-control" 
                                           id="field_<?php echo $field['field_key']; ?>" 
                                           name="<?php echo $field['field_key']; ?>" 
                                           value="<?php echo e($fieldValue); ?>"
                                           <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                    <?php
                                    break;
                            endswitch;
                            ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Custom Fields -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('customers_custom_fields', 'Özel Alanlar'); ?></h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><?php echo t('customers_custom_fields_desc', 'Bu müşteriye özel ekstra alanlar ekleyebilirsiniz. Bu özellikler sadece bu müşteri için geçerli olacaktır.'); ?></p>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-primary" id="addFieldBtn">
                            <i class="fas fa-plus"></i> <?php echo t('customers_add_field', 'Alan Ekle'); ?>
                        </button>
                        <span id="fieldCountWarning" class="text-danger ms-2" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo t('customers_max_fields_warning', 'Maksimum 20 alan ekleyebilirsiniz.'); ?>
                        </span>
                    </div>
                    
                    <div id="dynamicFieldsContainer" class="dynamic-fields-container">
                        <?php if (!empty($customerFields)): ?>
                            <?php foreach ($customerFields as $index => $field): ?>
                            <div class="dynamic-field" id="field_<?php echo $index; ?>">
                                <button type="button" class="btn btn-danger dynamic-field-remove" data-field-id="field_<?php echo $index; ?>" title="Kaldır">
                                    <i class="ti ti-x"></i>
                                </button>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="field_<?php echo $index; ?>_name" class="form-label">Alan Adı</label>
                                            <input type="text" class="form-control" id="field_<?php echo $index; ?>_name" name="fields[field_<?php echo $index; ?>][name]" value="<?php echo e($field['field_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="field_<?php echo $index; ?>_type" class="form-label">Alan Türü</label>
                                            <select class="form-select field-type-select" id="field_<?php echo $index; ?>_type" name="fields[field_<?php echo $index; ?>][type]" data-field-id="field_<?php echo $index; ?>" required>
                                                <option value="">Seçiniz</option>
                                                <option value="text" <?php echo $field['field_type'] == 'text' ? 'selected' : ''; ?>>Metin</option>
                                                <option value="number" <?php echo $field['field_type'] == 'number' ? 'selected' : ''; ?>>Sayı</option>
                                                <option value="select" <?php echo $field['field_type'] == 'select' ? 'selected' : ''; ?>>Seçim</option>
                                                <option value="textarea" <?php echo $field['field_type'] == 'textarea' ? 'selected' : ''; ?>>Metin Alanı</option>
                                                <option value="date" <?php echo $field['field_type'] == 'date' ? 'selected' : ''; ?>>Tarih</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="field_<?php echo $index; ?>_value" class="form-label">Değer</label>
                                            <input type="text" class="form-control" id="field_<?php echo $index; ?>_value" name="fields[field_<?php echo $index; ?>][value]" value="<?php echo e($field['field_value']); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Form Controls -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <button type="button" class="btn btn-secondary w-100" onclick="window.location.href='<?php echo url('index.php?module=customers'); ?>'">
                                <i class="fas fa-arrow-left"></i> <?php echo t('customers_edit_back', 'Geri Dön'); ?>
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> <?php echo t('customers_edit_save', 'Kaydet'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Customer Balance Info -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Bakiye Bilgisi</h5>
            </div>
            <div class="card-body">
                <div class="balance-info text-center p-3">
                    <?php if ($netBalance > 0): ?>
                    <h2 class="mb-0 text-success"><?php echo formatPrice(abs($netBalance)); ?> ₺</h2>
                    <p class="text-muted mb-0">Müşteriden Alacak</p>
                    <div class="alert alert-success mt-3 mb-0">
                        <i class="fas fa-info-circle"></i> Bu müşteri sizden alacaklı
                    </div>
                    <?php elseif ($netBalance < 0): ?>
                    <h2 class="mb-0 text-danger"><?php echo formatPrice(abs($netBalance)); ?> ₺</h2>
                    <p class="text-muted mb-0">Müşteriye Borç</p>
                    <div class="alert alert-danger mt-3 mb-0">
                        <i class="fas fa-exclamation-circle"></i> Bu müşteri size borçlu
                    </div>
                    <?php else: ?>
                    <h2 class="mb-0 text-secondary">0.00 ₺</h2>
                    <p class="text-muted mb-0">Bakiye</p>
                    <div class="alert alert-secondary mt-3 mb-0">
                        <i class="fas fa-check-circle"></i> Bakiye sıfır
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center mt-3">
                    <div class="btn-group">
                        <a href="<?php echo url('index.php?module=transactions&action=add-payment&customer_id=' . $customerId); ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-plus-circle"></i> Ödeme Ekle
                        </a>
                        <a href="<?php echo url('index.php?module=transactions&action=add-debt&customer_id=' . $customerId); ?>" class="btn btn-sm btn-danger">
                            <i class="fas fa-minus-circle"></i> Borç Ekle
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <?php if (!empty($customerOrders)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Son Siparişler</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Sipariş No</th>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerOrders as $order): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo url('index.php?module=orders&action=edit&id=' . $order['id']); ?>">
                                        #<?php echo $order['id']; ?>
                                    </a>
                                </td>
                                <td><?php echo formatDate($order['order_date']); ?></td>
                                <td><?php echo formatPrice($order['grand_total']); ?> ₺</td>
                                <td>
                                    <?php if ($order['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Bekliyor</span>
                                    <?php elseif ($order['status'] == 'processing'): ?>
                                    <span class="badge bg-info">İşlemde</span>
                                    <?php elseif ($order['status'] == 'completed'): ?>
                                    <span class="badge bg-success">Tamamlandı</span>
                                    <?php elseif ($order['status'] == 'cancelled'): ?>
                                    <span class="badge bg-danger">İptal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-2">
                    <a href="<?php echo url('index.php?module=orders&customer_id=' . $customerId); ?>" class="btn btn-sm btn-outline-secondary">
                        Tüm Siparişleri Gör
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Transactions -->
        <?php if (!empty($customerTransactions)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Son İşlemler</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Tür</th>
                                <th>Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerTransactions as $transaction): ?>
                            <tr>
                                <td><?php echo formatDate($transaction['date']); ?></td>
                                <td>
                                    <?php if ($transaction['type'] == 'payment'): ?>
                                    <span class="badge bg-success">Ödeme</span>
                                    <?php elseif ($transaction['type'] == 'debt'): ?>
                                    <span class="badge bg-danger">Borç</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatPrice($transaction['amount']); ?> ₺</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-2">
                    <a href="<?php echo url('index.php?module=transactions&customer_id=' . $customerId); ?>" class="btn btn-sm btn-outline-secondary">
                        Tüm İşlemleri Gör
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Customer Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Müşteri Bilgisi</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>ID:</span>
                    <strong><?php echo $customer['id']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Oluşturma Tarihi:</span>
                    <strong><?php echo formatDateTime($customer['created_at']); ?></strong>
                </div>
                <?php if (!empty($customer['updated_at']) && $customer['updated_at'] != $customer['created_at']): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Son Güncelleme:</span>
                    <strong><?php echo formatDateTime($customer['updated_at']); ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Dynamic Fields
        let fieldCount = <?php echo count($customerFields); ?>;
        const maxFieldCount = 20;
        
        // Update add button state based on field count
        function updateAddButtonState() {
            if (fieldCount >= maxFieldCount) {
                $('#addFieldBtn').prop('disabled', true).addClass('disabled');
                $('#fieldCountWarning').show();
            } else {
                $('#addFieldBtn').prop('disabled', false).removeClass('disabled');
                $('#fieldCountWarning').hide();
            }
        }
        
        // Initialize state
        updateAddButtonState();
        
        // Add field button click
        $('#addFieldBtn').on('click', function() {
            if (fieldCount >= maxFieldCount) {
                alert('Maksimum alan sayısına ulaştınız (20).');
                return;
            }
            
            fieldCount++;
            
            // Generate unique ID for new field
            const fieldId = 'field_new_' + Date.now();
            
            // Create new field HTML
            const fieldHtml = `
                <div class="dynamic-field" id="${fieldId}">
                    <button type="button" class="btn btn-danger dynamic-field-remove" data-field-id="${fieldId}" title="Kaldır">
                        <i class="ti ti-x"></i>
                    </button>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="${fieldId}_name" class="form-label">Alan Adı</label>
                                <input type="text" class="form-control" id="${fieldId}_name" name="fields[${fieldId}][name]" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="${fieldId}_type" class="form-label">Alan Türü</label>
                                <select class="form-select field-type-select" id="${fieldId}_type" name="fields[${fieldId}][type]" data-field-id="${fieldId}" required>
                                    <option value="">Seçiniz</option>
                                    <option value="text">Metin</option>
                                    <option value="number">Sayı</option>
                                    <option value="select">Seçim</option>
                                    <option value="textarea">Metin Alanı</option>
                                    <option value="date">Tarih</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="${fieldId}_value" class="form-label">Değer</label>
                                <input type="text" class="form-control" id="${fieldId}_value" name="fields[${fieldId}][value]">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Append to container
            $('#dynamicFieldsContainer').append(fieldHtml);
            
            // Update button state
            updateAddButtonState();
        });
        
        // Remove field
        $(document).on('click', '.dynamic-field-remove', function() {
            const fieldId = $(this).data('field-id');
            $(`#${fieldId}`).remove();
            
            fieldCount--;
            
            // Update button state
            updateAddButtonState();
        });
    });
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>