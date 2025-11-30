<?php
/**
 * Megabre StokMaster Pro
 * Add Customer
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

// Get products for order
$db->query("SELECT p.id, p.name, p.price, c.name as category_name 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            ORDER BY p.name ASC");
$products = $db->resultSet();

// Get all customer tags
$db->query("SELECT * FROM customer_tags WHERE is_active = 1 ORDER BY name ASC");
$allTags = $db->resultSet();

// Get all currencies
$db->query("SELECT * FROM currencies WHERE is_active = 1 ORDER BY is_default DESC, code ASC");
$currencies = $db->resultSet();

// Get active customer fields (system-wide dynamic fields)
$db->query("SELECT * FROM customer_fields WHERE (customer_id = 0 OR customer_id IS NULL) AND is_active = 1 ORDER BY field_order ASC, id ASC");
$systemFields = $db->resultSet();

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=customers');
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
    $defaultCurrencyId = post('default_currency_id'); // Müşteri varsayılan para birimi
    $createFirstOrder = post('create_first_order') == 1;
    
    // First order data
    $orderData = null;
    if ($createFirstOrder) {
        $orderData = [
            'product_id' => post('order_product_id'),
            'quantity' => post('order_quantity'),
            'unit_price' => post('order_unit_price'),
            'notes' => post('order_notes')
        ];
    }
    
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
    
    // Validate first order if enabled
    if ($createFirstOrder) {
        if (empty($orderData['product_id'])) {
            $errors[] = t('customers_order_product_required', 'Sipariş için ürün seçimi gereklidir.');
        }
        
        if (empty($orderData['quantity']) || !is_numeric($orderData['quantity']) || $orderData['quantity'] <= 0) {
            $errors[] = t('customers_order_quantity_required', 'Sipariş miktarı geçerli bir sayı olmalıdır.');
        }
        
        if (empty($orderData['unit_price']) || !is_numeric(str_replace(',', '.', $orderData['unit_price'])) || floatval(str_replace(',', '.', $orderData['unit_price'])) < 0) {
            $errors[] = t('customers_order_price_required', 'Birim fiyat geçerli bir sayı olmalıdır.');
        }
        
        // Get product details
        if (!empty($orderData['product_id'])) {
            $db->query("SELECT * FROM products WHERE id = :id");
            $db->bind(':id', $orderData['product_id']);
            $product = $db->single();
            
            if (!$product) {
                $errors[] = t('customers_product_not_found', 'Seçilen ürün bulunamadı.');
            } else {
                // Check stock
                $db->query("SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level 
                            FROM stock_movements 
                            WHERE product_id = :product_id");
                $db->bind(':product_id', $orderData['product_id']);
                $stockResult = $db->single();
                $stockLevel = $stockResult ? $stockResult['stock_level'] : 0;
                
                if ($stockLevel < $orderData['quantity']) {
                    $errors[] = t('customers_stock_insufficient', 'Stokta yeterli ürün yok. Mevcut stok:') . ' ' . $stockLevel;
                }
            }
        }
    }
    
    if (empty($errors)) {
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert customer
            $db->query("INSERT INTO customers (first_name, last_name, phone, email, company, address, notes) 
                       VALUES (:first_name, :last_name, :phone, :email, :company, :address, :notes)");
            $db->bind(':first_name', $firstName);
            $db->bind(':last_name', $lastName);
            $db->bind(':phone', $phone);
            $db->bind(':email', $email);
            $db->bind(':company', $company);
            $db->bind(':address', $address);
            $db->bind(':notes', $notes);
            $db->execute();
            
            $customerId = $db->lastInsertId();
            
            // Log activity
            logActivity('add_customer', 'customer', $customerId, null, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'email' => $email ?? '',
                'company' => $company ?? ''
            ], "Yeni müşteri eklendi: {$firstName} {$lastName}");
            
            // Handle customer tags
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
                if (!empty($tagIds)) {
                    $db->query("UPDATE customers SET tag_ids = :tag_ids WHERE id = :id");
                    $db->bind(':tag_ids', implode(',', $tagIds));
                    $db->bind(':id', $customerId);
                    $db->execute();
                }
            }
            
            // Müşteri varsayılan para birimi (customers tablosuna currency_id eklenirse)
            // Şimdilik ayarlar üzerinden varsayılan para birimi kullanılacak
            
            // Insert system dynamic fields (customer_fields table)
            if (!empty($systemFields)) {
                foreach ($systemFields as $field) {
                    $fieldKey = $field['field_key'];
                    $fieldValue = post($fieldKey, '');
                    
                    if (!empty($fieldValue)) {
                        // Check if customer_field_values table exists
                        try {
                            $db->query("INSERT INTO customer_field_values (customer_id, field_id, field_value) 
                                       VALUES (:customer_id, :field_id, :field_value)");
                            $db->bind(':customer_id', $customerId);
                            $db->bind(':field_id', $field['id']);
                            $db->bind(':field_value', $fieldValue);
                            $db->execute();
                        } catch (PDOException $e) {
                            // Table might not exist, skip
                            error_log('Customer field values table not found: ' . $e->getMessage());
                        }
                    }
                }
            }
            
            // Insert customer custom fields (müşteriye özel alanlar)
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
            
            // Create first order if requested
            if ($createFirstOrder && !empty($orderData['product_id'])) {
                // Format unit price
                $unitPrice = floatval(str_replace(',', '.', $orderData['unit_price']));
                $totalPrice = $unitPrice * $orderData['quantity'];
                
                // Create order
                $db->query("INSERT INTO orders (customer_id, order_date, status, notes, total_amount, grand_total) 
                           VALUES (:customer_id, CURDATE(), 'pending', :notes, :total_amount, :grand_total)");
                $db->bind(':customer_id', $customerId);
                $db->bind(':notes', $orderData['notes']);
                $db->bind(':total_amount', $totalPrice);
                $db->bind(':grand_total', $totalPrice);
                $db->execute();
                
                $orderId = $db->lastInsertId();
                
                // Create order item
                $db->query("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, notes) 
                           VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price, :notes)");
                $db->bind(':order_id', $orderId);
                $db->bind(':product_id', $orderData['product_id']);
                $db->bind(':quantity', $orderData['quantity']);
                $db->bind(':unit_price', $unitPrice);
                $db->bind(':total_price', $totalPrice);
                $db->bind(':notes', $orderData['notes']);
                $db->execute();
                
                // Reduce stock
                $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes, order_id) 
                           VALUES (:product_id, 'out', :quantity, 'piece', CURDATE(), :notes, :order_id)");
                $db->bind(':product_id', $orderData['product_id']);
                $db->bind(':quantity', $orderData['quantity']);
                $db->bind(':notes', t('orders_stock_out_note', 'Sipariş çıkışı') . ' #' . $orderId);
                $db->bind(':order_id', $orderId);
                $db->execute();
                
                // Add transaction record
                $db->query("INSERT INTO transactions (customer_id, type, amount, date, payment_method, notes, order_id) 
                           VALUES (:customer_id, 'debt', :amount, CURDATE(), 'cash', :notes, :order_id)");
                $db->bind(':customer_id', $customerId);
                $db->bind(':amount', $totalPrice);
                $db->bind(':notes', t('orders_debt_note', 'Sipariş borcu') . ': #' . $orderId);
                $db->bind(':order_id', $orderId);
                $db->execute();
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', t('customers_add_success', 'Müşteri başarıyla eklendi.') . ($createFirstOrder ? ' ' . t('customers_add_success_with_order', 'İlk sipariş de oluşturuldu.') : ''));
            
            // Redirect to customers list
            redirect('index.php?module=customers');
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            $errors[] = t('customers_add_error', 'Müşteri eklenirken bir hata oluştu:') . ' ' . $e->getMessage();
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
            <h3 class="page-title"><?php echo t('customers_add_title', 'Müşteri Ekle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=customers'); ?>"><?php echo t('customers_title', 'Müşteriler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('customers_add_title', 'Müşteri Ekle'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                <a href="<?php echo url('index.php?module=customers'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> <?php echo t('customers_add_back', 'Geri Dön'); ?>
                </a>
                <button type="submit" form="customerForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo t('customers_add_save', 'Kaydet'); ?>
                </button>
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

<!-- Add Customer Form -->
<form action="<?php echo url('index.php?module=customers&action=add'); ?>" method="post" id="customerForm">
    <?php echo csrfField(); ?>
    
    <div class="row">
        <!-- Customer Information -->
        <div class="col-md-<?php echo isset($_COOKIE['help_panel_collapsed']) ? '12' : '9'; ?>">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('customers_add_customer_info', 'Müşteri Bilgileri'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label required"><?php echo t('customers_first_name', 'Ad'); ?></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo post('first_name', ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label required"><?php echo t('customers_last_name', 'Soyad'); ?></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo post('last_name', ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label required"><?php echo t('customers_phone', 'Telefon'); ?></label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo post('phone', ''); ?>" required>
                                <small class="text-muted"><?php echo t('customers_phone_example', 'Örnek: 532 123 45 67 veya +90 532 123 45 67'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label"><?php echo t('customers_email', 'E-posta'); ?></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo post('email', ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="company" class="form-label"><?php echo t('customers_company', 'Şirket/Firma'); ?></label>
                        <input type="text" class="form-control" id="company" name="company" value="<?php echo post('company', ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label"><?php echo t('customers_address', 'Adres'); ?></label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo post('address', ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label"><?php echo t('customers_notes', 'Notlar'); ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo post('notes', ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Customer Tags -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('customers_tags', 'Müşteri Etiketleri'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle"></i> 
                        <strong><?php echo t('tags_info_title', 'Etiket Sistemi'); ?></strong>
                        <p class="mb-0">
                            <?php echo t('customers_tags_desc', 'Müşteriye etiket atayarak siparişlerde otomatik indirim uygulanabilir.'); ?>
                            <br>
                            <strong><?php echo t('tags_discount_note', 'Önemli:'); ?></strong> 
                            <?php echo t('tags_discount_note_desc', 'İndirim yüzdesini Ayarlar > Müşteri Etiketleri bölümünden belirleyebilirsiniz. Her etiket için farklı indirim yüzdesi ayarlayabilirsiniz.'); ?>
                        </p>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($allTags as $tag): ?>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>" 
                                       id="tag_<?php echo $tag['id']; ?>">
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
                        <i class="fas fa-info-circle"></i> <?php echo t('customers_no_tags', 'Henüz etiket tanımlanmamış. '); ?>
                        <a href="<?php echo url('index.php?module=settings&action=customer-tags'); ?>"><?php echo t('customers_add_tags', 'Etiket eklemek için tıklayın'); ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Default Currency -->
            <?php if (!empty($currencies)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('customers_default_currency', 'Varsayılan Para Birimi'); ?></h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><?php echo t('customers_default_currency_desc', 'Bu müşteri için varsayılan para birimini seçin (opsiyonel).'); ?></p>
                    
                    <div class="mb-3">
                        <label for="default_currency_id" class="form-label"><?php echo t('customers_currency', 'Para Birimi'); ?></label>
                        <select class="form-select" id="default_currency_id" name="default_currency_id">
                            <option value=""><?php echo t('customers_use_system_default', 'Sistem varsayılanını kullan'); ?></option>
                            <?php foreach ($currencies as $currency): ?>
                            <option value="<?php echo $currency['id']; ?>" <?php echo $currency['is_default'] ? 'selected' : ''; ?>>
                                <?php echo e($currency['code']); ?> - <?php echo e($currency['name']); ?>
                                <?php if ($currency['is_default']): ?>
                                    <span class="text-muted">(<?php echo t('default', 'Varsayılan'); ?>)</span>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
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
                            $fieldValue = post($field['field_key'], '');
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
                        <!-- Dynamic fields will be added here -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Side Panel -->
        <div class="col-md-3" id="helpPanel" <?php echo isset($_COOKIE['help_panel_collapsed']) ? 'style="display:none;"' : ''; ?>>
            <!-- Help Box -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><?php echo t('customers_add_help_tips', 'Yardım & İpuçları'); ?></h5>
                    <button type="button" class="btn btn-sm btn-link text-muted" id="toggleHelpPanel">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <ul class="mb-0 small">
                            <li><?php echo t('customers_add_help_tip1', 'Müşteri bilgilerini eksiksiz doldurun'); ?></li>
                            <li><?php echo t('customers_add_help_tip2', 'Telefon numarası ve e-posta adresi önemlidir'); ?></li>
                            <li><?php echo t('customers_add_help_tip3', 'Vergi numarası şirket müşterileri için gereklidir'); ?></li>
                            <li><?php echo t('customers_add_help_tip4', 'Adres bilgisi teslimat için önemlidir'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select2').select2({
            theme: 'bootstrap-5'
        });
        
        // Toggle first order fields
        $('#create_first_order').on('change', function() {
            if ($(this).is(':checked')) {
                $('#firstOrderFields').slideDown();
            } else {
                $('#firstOrderFields').slideUp();
            }
        });
        
        // Product selection change
        $('#order_product_id').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const price = selectedOption.data('price');
            
            if (price) {
                $('#order_unit_price').val(price.toFixed(2));
                updateTotalPrice();
            } else {
                $('#order_unit_price').val('');
                $('#order_total').val('');
            }
        });
        
        // Update total on quantity or price change
        $('#order_quantity, #order_unit_price').on('input', function() {
            updateTotalPrice();
        });
        
        // Function to update total price
        function updateTotalPrice() {
            const quantity = parseFloat($('#order_quantity').val()) || 0;
            const unitPrice = parseFloat($('#order_unit_price').val().replace(',', '.')) || 0;
            const total = quantity * unitPrice;
            
            $('#order_total').val(total.toFixed(2));
        }
        
        // Dynamic Fields
        let fieldCount = 0;
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
        
        // Add field button click
        $('#addFieldBtn').on('click', function() {
            if (fieldCount >= maxFieldCount) {
                alert('<?php echo t('customers_max_fields_reached', 'Maksimum alan sayısına ulaştınız (20).'); ?>');
                return;
            }
            
            fieldCount++;
            
            // Generate unique ID for new field
            const fieldId = 'field_' + Date.now();
            
            // Create new field HTML
            const fieldHtml = `
                <div class="dynamic-field" id="${fieldId}">
                    <button type="button" class="btn btn-danger dynamic-field-remove" data-field-id="${fieldId}" title="Kaldır">
                        <i class="ti ti-x"></i>
                    </button>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="${fieldId}_name" class="form-label"><?php echo t('customers_add_field_name', 'Alan Adı'); ?></label>
                                <input type="text" class="form-control" id="${fieldId}_name" name="fields[${fieldId}][name]" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="${fieldId}_type" class="form-label"><?php echo t('customers_add_field_type', 'Alan Türü'); ?></label>
                                <select class="form-select field-type-select" id="${fieldId}_type" name="fields[${fieldId}][type]" data-field-id="${fieldId}" required>
                                    <option value=""><?php echo t('customers_add_select', 'Seçiniz'); ?></option>
                                    <option value="text"><?php echo t('customers_add_field_type_text', 'Metin'); ?></option>
                                    <option value="number"><?php echo t('customers_add_field_type_number', 'Sayı'); ?></option>
                                    <option value="select"><?php echo t('customers_add_field_type_select', 'Seçim'); ?></option>
                                    <option value="textarea"><?php echo t('customers_add_field_type_textarea', 'Metin Alanı'); ?></option>
                                    <option value="date"><?php echo t('customers_add_field_type_date', 'Tarih'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="${fieldId}_value" class="form-label"><?php echo t('customers_add_field_value', 'Değer'); ?></label>
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
        
        // Initial triggers
        if ($('#order_product_id').val()) {
            $('#order_product_id').trigger('change');
        }
        
        updateTotalPrice();
        
        // Toggle help panel
        $('#toggleHelpPanel').on('click', function() {
            const $helpPanel = $('#helpPanel');
            const $mainContent = $helpPanel.prev();
            
            if ($helpPanel.is(':visible')) {
                $helpPanel.hide();
                $mainContent.removeClass('col-md-9').addClass('col-md-12');
                document.cookie = "help_panel_collapsed=1; path=/; max-age=31536000";
            } else {
                $helpPanel.show();
                $mainContent.removeClass('col-md-12').addClass('col-md-9');
                document.cookie = "help_panel_collapsed=; path=/; max-age=0";
            }
        });
    });
</script>

<style>
.btn-group {
    gap: 0.5rem;
}
</style>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>