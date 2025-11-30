<?php
/**
 * Megabre StokMaster Pro
 * Add Product
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

// Get inventory settings
$db->query("SELECT setting_value FROM settings WHERE setting_key = :key");
$db->bind(':key', 'inventory_settings');
$settings = $db->single();

$inventorySettings = [];
if ($settings) {
    $inventorySettings = json_decode($settings['setting_value'], true);
}

// Debug database connection
$db->debugConnection();

// Initialize dynamic fields class
$dynamicFields = new DynamicFields();

// Get categories
$db->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $db->resultSet();

// Get active product fields (system-wide dynamic fields)
$db->query("SELECT * FROM product_fields WHERE status = 1 ORDER BY `order` ASC, id ASC");
$systemFields = $db->resultSet();

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=products');
    }
    
    // Get form data
    $categoryId = post('category_id');
    $name = post('name');
    $price = post('price');
    $sku = post('sku');
    $barcode = post('barcode');
    $description = post('description');
    $minStockLevel = post('min_stock_level');
    $categoryFields = post('category_fields');
    $initialStock = post('initial_stock');
    $initialStockQuantity = post('initial_stock_quantity');
    $initialStockUnit = post('initial_stock_unit');
    $initialStockNote = post('initial_stock_note');
    
    // Generate SKU if auto SKU is enabled and no SKU provided
    if (empty($sku) && isset($inventorySettings['auto_sku']) && $inventorySettings['auto_sku'] == 1) {
        $prefix = $inventorySettings['sku_prefix'] ?? 'PRD';
        
        // Generate random string (6 characters)
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < 6; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Generate SKU with prefix and random string
        $sku = $prefix . '-' . $randomString;
        
        // Check if SKU already exists
        $db->query("SELECT COUNT(*) as count FROM products WHERE sku = :sku");
        $db->bind(':sku', $sku);
        $result = $db->single();
        
        // If SKU exists, generate a new one
        while ($result['count'] > 0) {
            $randomString = '';
            for ($i = 0; $i < 6; $i++) {
                $randomString .= $characters[rand(0, strlen($characters) - 1)];
            }
            $sku = $prefix . '-' . $randomString;
            
            $db->query("SELECT COUNT(*) as count FROM products WHERE sku = :sku");
            $db->bind(':sku', $sku);
            $result = $db->single();
        }
    }
    
    // Validate form data
    $errors = [];
    
    if (empty($categoryId)) {
        $errors[] = 'Kategori seçimi gereklidir.';
    }
    
    if (empty($name)) {
        $errors[] = 'Ürün adı gereklidir.';
    } elseif (strlen($name) < 2 || strlen($name) > 255) {
        $errors[] = 'Ürün adı 2-255 karakter arasında olmalıdır.';
    }
    
    if (empty($price) && $price !== '0') {
        $errors[] = 'Fiyat gereklidir.';
    } elseif (!is_numeric(str_replace(',', '.', $price)) || floatval(str_replace(',', '.', $price)) < 0) {
        $errors[] = 'Fiyat geçerli bir sayı olmalıdır.';
    }
    
    if (!empty($barcode) && !preg_match('/^[0-9]+$/', $barcode)) {
        $errors[] = 'Barkod sadece rakam içermelidir.';
    }
    
    if ($initialStock && (empty($initialStockQuantity) || !is_numeric($initialStockQuantity) || floatval($initialStockQuantity) <= 0)) {
        $errors[] = 'Başlangıç stok miktarı geçerli bir sayı olmalıdır.';
    }
    
    if ($initialStock && empty($initialStockUnit)) {
        $errors[] = 'Başlangıç stok birimi seçilmelidir.';
    }
    
    // Handle file upload
    $uploadedImage = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $temp = $_FILES['image']['tmp_name'];
        $filesize = $_FILES['image']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Geçersiz dosya formatı. Sadece JPG, JPEG, PNG ve GIF dosyaları yüklenebilir.';
        } elseif ($filesize > 500 * 1024) { // 500KB limit
            $errors[] = 'Dosya boyutu çok büyük. Maksimum 500KB olmalıdır.';
        } else {
            // Generate unique filename
            $newFilename = uniqid() . '.' . $ext;
            $uploadPath = UPLOADS_PATH . 'products/';
            
            // Create directory if not exists
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }
            
            // Move uploaded file
            if (move_uploaded_file($temp, $uploadPath . $newFilename)) {
                $uploadedImage = $newFilename;
            } else {
                $errors[] = 'Dosya yüklenirken bir hata oluştu.';
            }
        }
    }
    
    if (empty($errors)) {
        // Format price
        $price = floatval(str_replace(',', '.', $price));
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Build INSERT query with dynamic fields
            $columns = ['category_id', 'name', 'price', 'sku', 'barcode', 'description', 'min_stock_level', 'image'];
            $placeholders = [':category_id', ':name', ':price', ':sku', ':barcode', ':description', ':min_stock_level', ':image'];
            $values = [
                ':category_id' => $categoryId,
                ':name' => $name,
                ':price' => $price,
                ':sku' => $sku,
                ':barcode' => $barcode,
                ':description' => $description,
                ':min_stock_level' => $minStockLevel,
                ':image' => $uploadedImage
            ];
            
            // Add system fields (product_fields table columns)
            foreach ($systemFields as $field) {
                $fieldName = $field['name'];
                $fieldValue = post($fieldName, '');
                
                // Process field value based on type
                if ($field['type'] == 'number') {
                    $fieldValue = !empty($fieldValue) ? floatval(str_replace(',', '.', $fieldValue)) : null;
                } elseif ($field['type'] == 'checkbox') {
                    $fieldValue = $fieldValue ? 1 : 0;
                }
                
                $columns[] = "`{$fieldName}`";
                $placeholders[] = ":{$fieldName}";
                $values[":{$fieldName}"] = $fieldValue;
            }
            
            $columnsStr = implode(', ', $columns);
            $placeholdersStr = implode(', ', $placeholders);
            
            // Insert product
            $db->query("INSERT INTO products ({$columnsStr}) VALUES ({$placeholdersStr})");
            foreach ($values as $key => $value) {
                $db->bind($key, $value);
            }
            
            // Debug: Print the SQL query and parameters
            error_log("Product Insert Query: " . $db->getQuery());
            error_log("Product Parameters: " . print_r([
                'category_id' => $categoryId,
                'name' => $name,
                'price' => $price,
                'sku' => $sku,
                'barcode' => $barcode,
                'description' => $description,
                'min_stock_level' => $minStockLevel,
                'image' => $uploadedImage
            ], true));
            
            $db->execute();
            
            // Get the inserted product ID
            $productId = $db->lastInsertId();
            error_log("Inserted Product ID: " . $productId);
            
            // Log activity
            logActivity('add_product', 'product', $productId, null, [
                'name' => $name,
                'price' => $price,
                'sku' => $sku ?? '',
                'category_id' => $categoryId
            ], "Yeni ürün eklendi: {$name}");
            
            // Insert category fields
            if ($categoryFields && is_array($categoryFields)) {
                foreach ($categoryFields as $fieldIndex => $field) {
                    // Field can be either an array with field_id, field_name, field_type, field_value
                    // or just a value (string/number) where we need to get field info from category_fields table
                    if (is_array($field)) {
                        $fieldId = $field['field_id'] ?? '';
                        $fieldName = $field['field_name'] ?? '';
                        $fieldType = $field['field_type'] ?? '';
                        $fieldValue = $field['field_value'] ?? '';
                        
                        // If field_name or field_type is missing, try to get from category_fields table
                        if (empty($fieldName) || empty($fieldType)) {
                            if (!empty($fieldId)) {
                                try {
                                    $db->query("SELECT field_name, field_type FROM category_fields WHERE id = :id");
                                    $db->bind(':id', $fieldId);
                                    $fieldInfo = $db->single();
                                    
                                    if ($fieldInfo) {
                                        $fieldName = $fieldName ?: $fieldInfo['field_name'];
                                        $fieldType = $fieldType ?: $fieldInfo['field_type'];
                                    }
                                } catch (PDOException $e) {
                                    // Skip if field not found
                                    continue;
                                }
                            }
                        }
                        
                        if (!empty($fieldName) && !empty($fieldType)) {
                            try {
                                $dynamicFields->createProductField($productId, $fieldName, $fieldType, $fieldValue);
                            } catch (Exception $e) {
                                // Ignore errors - product fields are optional
                                error_log("Error creating product field: " . $e->getMessage());
                            }
                        }
                    } else {
                        // Field is just a value, get field info from category_fields table using index
                        try {
                            $db->query("SELECT id, field_name, field_type FROM category_fields WHERE id = :id");
                            $db->bind(':id', $fieldIndex);
                            $fieldInfo = $db->single();
                            
                            if ($fieldInfo) {
                                $dynamicFields->createProductField($productId, $fieldInfo['field_name'], $fieldInfo['field_type'], $field);
                            }
                        } catch (PDOException $e) {
                            // Skip if field not found
                            continue;
                        }
                    }
                }
            }
            
            // Add initial stock if specified
            if ($initialStock && $initialStockQuantity > 0) {
                try {
                    // Debug: Print product ID
                    error_log("Product ID: " . $productId);
                    
                    // Check if product exists
                    $db->query("SELECT id, name FROM products WHERE id = :id");
                    $db->bind(':id', $productId);
                    $product = $db->single();
                    
                    if (!$product) {
                        error_log("Product not found with ID: " . $productId);
                        throw new Exception("Ürün bulunamadı");
                    }
                    
                    error_log("Found product: " . print_r($product, true));
                    
                    // Validate unit
                    $validUnits = ['piece', 'kg', 'lt', 'm', 'm2', 'm3', 'package', 'box', 'pallet'];
                    if (!in_array($initialStockUnit, $validUnits)) {
                        error_log("Invalid unit: " . $initialStockUnit);
                        throw new Exception("Geçersiz birim: " . $initialStockUnit);
                    }
                    
                    // Stok hareketi ekle - basitleştirilmiş sorgu
                    $sql = "INSERT INTO stock_movements SET 
                           product_id = :product_id,
                           type = 'in',
                           quantity = :quantity,
                           unit = :unit,
                           date = CURDATE(),
                           notes = :notes,
                           created_by = :created_by";
                    
                    error_log("SQL Query: " . $sql);
                    
                    $db->query($sql);
                    $db->bind(':product_id', $productId);
                    $db->bind(':quantity', $initialStockQuantity);
                    $db->bind(':unit', $initialStockUnit);
                    $db->bind(':notes', $initialStockNote ?: 'İlk stok girişi');
                    $db->bind(':created_by', $_SESSION['username'] ?? 'system');
                    
                    // Debug: Print parameters
                    error_log("Parameters: " . print_r([
                        'product_id' => $productId,
                        'quantity' => $initialStockQuantity,
                        'unit' => $initialStockUnit,
                        'notes' => $initialStockNote ?: 'İlk stok girişi',
                        'created_by' => $_SESSION['username'] ?? 'system'
                    ], true));
                    
                    $result = $db->execute();
                    
                    if (!$result) {
                        error_log("Stock Movement Insert Failed");
                        error_log("PDO Error Info: " . print_r($db->errorInfo(), true));
                        throw new Exception("Stok hareketi eklenemedi");
                    }
                    
                    $movementId = $db->lastInsertId();
                    error_log("Stock Movement Inserted Successfully. ID: " . $movementId);
                    
                } catch (Exception $e) {
                    error_log("Stock Movement Error: " . $e->getMessage());
                    error_log("SQL State: " . $e->getCode());
                    error_log("Error Info: " . print_r($db->errorInfo(), true));
                    throw $e;
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', 'Ürün başarıyla eklendi.');
            
            // Redirect to products list
            redirect('index.php?module=products');
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            // Delete uploaded image if exists
            if ($uploadedImage && file_exists(UPLOADS_PATH . 'products/' . $uploadedImage)) {
                unlink(UPLOADS_PATH . 'products/' . $uploadedImage);
            }
            
            $errors[] = 'Ürün eklenirken bir hata oluştu: ' . $e->getMessage();
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
            <h3 class="page-title"><?php echo t('products_add_title', 'Ürün Ekle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=products'); ?>"><?php echo t('products_title', 'Ürünler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('products_add_title', 'Ürün Ekle'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                <a href="<?php echo url('index.php?module=products'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> <?php echo t('ui_go_back', 'Geri Dön'); ?>
                </a>
                <button type="submit" form="productForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo t('save', 'Kaydet'); ?>
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

<!-- Add Product Form -->
<form action="<?php echo url('index.php?module=products&action=add'); ?>" method="post" id="productForm" enctype="multipart/form-data">
    <?php echo csrfField(); ?>
    
    <div class="row">
        <!-- Product Information -->
        <div class="col-md-<?php echo isset($_COOKIE['help_panel_collapsed']) ? '12' : '9'; ?>">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('products_product_info', 'Ürün Bilgileri'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label required"><?php echo t('products_category', 'Kategori'); ?></label>
                                <select class="form-select field-type-select" id="category_id" name="category_id" required>
                                    <option value=""><?php echo t('products_category_select', 'Seçiniz'); ?></option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo post('category_id') == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label required"><?php echo t('products_name', 'Ürün Adı'); ?></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo post('name', ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label required"><?php echo t('products_price_label', 'Fiyat (₺)'); ?></label>
                                <input type="text" class="form-control" id="price" name="price" value="<?php echo post('price', ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="sku" class="form-label"><?php echo t('products_sku', 'SKU'); ?></label>
                                <input type="text" class="form-control" id="sku" name="sku" value="<?php echo isset($sku) ? $sku : ''; ?>">
                                <?php if (isset($inventorySettings['auto_sku']) && $inventorySettings['auto_sku'] == 1): ?>
                                    <small class="form-text text-muted">
                                        <?php echo t('products_auto_sku_active', 'Otomatik SKU oluşturma aktif. Boş bırakırsanız, sistem otomatik olarak SKU oluşturacaktır.'); ?>
                                        <?php if (!empty($inventorySettings['sku_prefix'])): ?>
                                            <?php echo t('products_sku_prefix', 'SKU öneki:'); ?> <?php echo $inventorySettings['sku_prefix']; ?>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="barcode" class="form-label"><?php echo t('products_barcode', 'Barkod'); ?></label>
                                <input type="text" class="form-control" id="barcode" name="barcode" value="<?php echo post('barcode', ''); ?>">
                                <small class="text-muted"><?php echo t('products_barcode_numeric_only', 'Sadece rakam (opsiyonel)'); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label"><?php echo t('products_description', 'Açıklama'); ?></label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo post('description', ''); ?></textarea>
                        <small class="text-muted"><span id="descriptionCounter">0</span>/1000 <?php echo t('products_characters_count', 'karakter'); ?></small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="min_stock_level" class="form-label"><?php echo t('products_min_stock_level', 'Min. Stok Seviyesi'); ?></label>
                                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" value="<?php echo post('min_stock_level', '0'); ?>" min="0">
                                <small class="text-muted"><?php echo t('products_min_stock_level_desc', 'Stok bu seviyenin altına düştüğünde uyarı verilecek'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="image" class="form-label"><?php echo t('products_image_upload', 'Ürün Resmi'); ?></label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="text-muted"><?php echo t('products_image_max_size', 'Maksimum 500KB (JPG, PNG, GIF)'); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- Initial Stock Section -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title"><?php echo t('products_initial_stock', 'Başlangıç Stok Bilgisi'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="initial_stock" name="initial_stock" value="1" <?php echo post('initial_stock') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="initial_stock">
                                            <?php echo t('products_initial_stock_add', 'Başlangıç stok bilgisi ekle'); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="initialStockFields" style="display: <?php echo post('initial_stock') ? 'block' : 'none'; ?>;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="initial_stock_quantity" class="form-label"><?php echo t('products_initial_stock_quantity', 'Miktar'); ?></label>
                                            <input type="number" class="form-control" id="initial_stock_quantity" name="initial_stock_quantity" value="<?php echo post('initial_stock_quantity', '0'); ?>" min="0" step="0.01">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="initial_stock_unit" class="form-label"><?php echo t('products_initial_stock_unit', 'Birim'); ?></label>
                                            <select class="form-select" id="initial_stock_unit" name="initial_stock_unit">
                                                <option value="piece" <?php echo post('initial_stock_unit') == 'piece' ? 'selected' : ''; ?>><?php echo t('products_unit_piece', 'Adet'); ?></option>
                                                <option value="kg" <?php echo post('initial_stock_unit') == 'kg' ? 'selected' : ''; ?>><?php echo t('products_unit_kg', 'Kilogram'); ?></option>
                                                <option value="lt" <?php echo post('initial_stock_unit') == 'lt' ? 'selected' : ''; ?>><?php echo t('products_unit_lt', 'Litre'); ?></option>
                                                <option value="m" <?php echo post('initial_stock_unit') == 'm' ? 'selected' : ''; ?>><?php echo t('products_unit_m', 'Metre'); ?></option>
                                                <option value="m2" <?php echo post('initial_stock_unit') == 'm2' ? 'selected' : ''; ?>><?php echo t('products_unit_m2', 'Metrekare'); ?></option>
                                                <option value="m3" <?php echo post('initial_stock_unit') == 'm3' ? 'selected' : ''; ?>><?php echo t('products_unit_m3', 'Metreküp'); ?></option>
                                                <option value="package" <?php echo post('initial_stock_unit') == 'package' ? 'selected' : ''; ?>><?php echo t('products_unit_package', 'Paket'); ?></option>
                                                <option value="box" <?php echo post('initial_stock_unit') == 'box' ? 'selected' : ''; ?>><?php echo t('products_unit_box', 'Kutu'); ?></option>
                                                <option value="pallet" <?php echo post('initial_stock_unit') == 'pallet' ? 'selected' : ''; ?>><?php echo t('products_unit_pallet', 'Palet'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="initial_stock_note" class="form-label"><?php echo t('products_initial_stock_note', 'Not'); ?></label>
                                            <input type="text" class="form-control" id="initial_stock_note" name="initial_stock_note" value="<?php echo post('initial_stock_note', ''); ?>" placeholder="<?php echo t('products_initial_stock_note_placeholder', 'İlk stok girişi'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Category Dynamic Fields -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('products_category_properties', 'Kategori Özellikleri'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="categoryFieldsContainer">
                        <div class="alert alert-info">
                            <?php echo t('products_category_fields_select', 'Lütfen önce bir kategori seçin. Seçtiğiniz kategoriye ait özel alanlar burada görüntülenecektir.'); ?>
                        </div>
                    </div>
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
                            <label for="field_<?php echo $field['name']; ?>" class="form-label">
                                <?php echo e($field['label']); ?>
                                <?php if ($field['required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php
                            $fieldValue = post($field['name'], '');
                            $fieldOptions = !empty($field['options']) ? explode("\n", $field['options']) : [];
                            
                            switch ($field['type']):
                                case 'text':
                                    ?>
                                    <input type="text" 
                                           class="form-control" 
                                           id="field_<?php echo $field['name']; ?>" 
                                           name="<?php echo $field['name']; ?>" 
                                           value="<?php echo e($fieldValue); ?>"
                                           placeholder="<?php echo e($field['placeholder']); ?>"
                                           <?php echo $field['required'] ? 'required' : ''; ?>>
                                    <?php
                                    break;
                                    
                                case 'textarea':
                                    ?>
                                    <textarea class="form-control" 
                                              id="field_<?php echo $field['name']; ?>" 
                                              name="<?php echo $field['name']; ?>" 
                                              rows="3"
                                              placeholder="<?php echo e($field['placeholder']); ?>"
                                              <?php echo $field['required'] ? 'required' : ''; ?>><?php echo e($fieldValue); ?></textarea>
                                    <?php
                                    break;
                                    
                                case 'number':
                                    ?>
                                    <input type="number" 
                                           class="form-control" 
                                           id="field_<?php echo $field['name']; ?>" 
                                           name="<?php echo $field['name']; ?>" 
                                           value="<?php echo e($fieldValue); ?>"
                                           placeholder="<?php echo e($field['placeholder']); ?>"
                                           step="any"
                                           <?php echo $field['required'] ? 'required' : ''; ?>>
                                    <?php
                                    break;
                                    
                                case 'select':
                                    ?>
                                    <select class="form-select" 
                                            id="field_<?php echo $field['name']; ?>" 
                                            name="<?php echo $field['name']; ?>"
                                            <?php echo $field['required'] ? 'required' : ''; ?>>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($fieldOptions as $option): ?>
                                            <option value="<?php echo e(trim($option)); ?>" <?php echo $fieldValue == trim($option) ? 'selected' : ''; ?>>
                                                <?php echo e(trim($option)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php
                                    break;
                                    
                                case 'radio':
                                    ?>
                                    <div>
                                        <?php foreach ($fieldOptions as $option): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="radio" 
                                                       name="<?php echo $field['name']; ?>" 
                                                       id="field_<?php echo $field['name']; ?>_<?php echo e(trim($option)); ?>" 
                                                       value="<?php echo e(trim($option)); ?>"
                                                       <?php echo $fieldValue == trim($option) ? 'checked' : ''; ?>
                                                       <?php echo $field['required'] ? 'required' : ''; ?>>
                                                <label class="form-check-label" for="field_<?php echo $field['name']; ?>_<?php echo e(trim($option)); ?>">
                                                    <?php echo e(trim($option)); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                    break;
                                    
                                case 'checkbox':
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="field_<?php echo $field['name']; ?>" 
                                               name="<?php echo $field['name']; ?>" 
                                               value="1"
                                               <?php echo $fieldValue ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="field_<?php echo $field['name']; ?>">
                                            <?php echo e($field['placeholder'] ?: 'Evet'); ?>
                                        </label>
                                    </div>
                                    <?php
                                    break;
                                    
                                case 'date':
                                    ?>
                                    <input type="date" 
                                           class="form-control" 
                                           id="field_<?php echo $field['name']; ?>" 
                                           name="<?php echo $field['name']; ?>" 
                                           value="<?php echo e($fieldValue); ?>"
                                           <?php echo $field['required'] ? 'required' : ''; ?>>
                                    <?php
                                    break;
                                    
                                case 'file':
                                    ?>
                                    <input type="file" 
                                           class="form-control" 
                                           id="field_<?php echo $field['name']; ?>" 
                                           name="<?php echo $field['name']; ?>"
                                           <?php echo $field['required'] ? 'required' : ''; ?>>
                                    <?php
                                    break;
                                    
                                default:
                                    ?>
                                    <input type="text" 
                                           class="form-control" 
                                           id="field_<?php echo $field['name']; ?>" 
                                           name="<?php echo $field['name']; ?>" 
                                           value="<?php echo e($fieldValue); ?>"
                                           placeholder="<?php echo e($field['placeholder']); ?>"
                                           <?php echo $field['required'] ? 'required' : ''; ?>>
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
            
            <!-- Product Dynamic Fields -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('products_custom_properties', 'Özel Özellikler'); ?></h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><?php echo t('products_custom_properties_desc', 'Bu ürüne özel ekstra özellikler ekleyebilirsiniz. Bu özellikler sadece bu ürün için geçerli olacaktır.'); ?></p>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-primary" id="addFieldBtn">
                            <i class="fas fa-plus"></i> <?php echo t('products_add_property', 'Özellik Ekle'); ?>
                        </button>
                        <span id="fieldCountWarning" class="text-danger ms-2" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo t('products_max_properties_warning', 'Maksimum 20 özellik ekleyebilirsiniz.'); ?>
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
                    <h5 class="card-title mb-0">Yardım & İpuçları</h5>
                    <button type="button" class="btn btn-sm btn-link text-muted" id="toggleHelpPanel">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <ul class="mb-0 small">
                            <li>Ürün adı ve kategori seçimi zorunludur</li>
                            <li>SKU ve barkod benzersiz olmalıdır</li>
                            <li>Minimum stok seviyesi stok uyarıları için önemlidir</li>
                            <li>Ürün resmi opsiyoneldir</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    $(document).ready(function() {
        // Description character counter
        $('#description').on('input', function() {
            $('#descriptionCounter').text($(this).val().length);
        });
        
        // Initial stock checkbox toggle
        $('#initial_stock').on('change', function() {
            $('#initialStockFields').toggle(this.checked);
        });
        
        // Category change event for dynamic fields
        $('#category_id').on('change', function() {
            const categoryId = $(this).val();
            const container = $('#categoryFieldsContainer');
            
            if (!categoryId) {
                container.html('<div class="alert alert-info">Lütfen bir kategori seçin</div>');
                return;
            }
            
            // Debug log
            console.log('Loading fields for category:', categoryId);
            
            // Show loading state
            container.html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div></div>');
            
            $.ajax({
                url: window.location.pathname.replace(/\/[^\/]*$/, '/api/categories.php'),
                method: 'GET',
                data: {
                    action: 'get_fields',
                    category_id: categoryId
                },
                dataType: 'json',
                beforeSend: function(xhr) {
                    console.log('Sending request to:', this.url);
                    console.log('Request data:', this.data);
                },
                success: function(response) {
                    console.log('API Response:', response);
                    
                    if (response.success && response.fields) {
                        let html = '<div class="row">';
                        
                        response.fields.forEach(function(field) {
                            console.log('Processing field:', field);
                            
                            html += '<div class="col-md-6 mb-3">';
                            html += '<label class="form-label">' + field.field_name + '</label>';
                            
                            try {
                                const options = field.field_options ? JSON.parse(field.field_options) : [];
                                
                                switch(field.field_type) {
                                    case 'text':
                                        html += '<input type="text" class="form-control" name="category_fields[' + field.id + ']" required>';
                                        break;
                                    case 'number':
                                        html += '<input type="number" class="form-control" name="category_fields[' + field.id + ']" required>';
                                        break;
                                    case 'textarea':
                                        html += '<textarea class="form-control" name="category_fields[' + field.id + ']" required></textarea>';
                                        break;
                                    case 'select':
                                        html += '<select class="form-control" name="category_fields[' + field.id + ']" required>';
                                        html += '<option value="">Seçiniz</option>';
                                        options.forEach(function(option) {
                                            html += '<option value="' + option + '">' + option + '</option>';
                                        });
                                        html += '</select>';
                                        break;
                                    default:
                                        html += '<input type="text" class="form-control" name="category_fields[' + field.id + ']" required>';
                                }
                            } catch (e) {
                                console.error('Error parsing field options:', e);
                                html += '<input type="text" class="form-control" name="category_fields[' + field.id + ']" required>';
                            }
                            
                            html += '</div>';
                        });
                        
                        html += '</div>';
                        container.html(html);
                    } else {
                        console.error('Invalid API response:', response);
                        container.html('<div class="alert alert-warning">Bu kategoriye ait özel alan bulunamadı</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText,
                        url: this.url,
                        data: this.data
                    });
                    
                    let errorMessage = 'Kategori alanları yüklenirken bir hata oluştu.';
                    
                    if (xhr.status === 404) {
                        errorMessage = 'API endpoint bulunamadı. Lütfen sistem yöneticisi ile iletişime geçin.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Sunucu hatası oluştu. Lütfen daha sonra tekrar deneyin.';
                    }
                    
                    container.html('<div class="alert alert-danger">' + errorMessage + '</div>');
                }
            });
        });
        
        // Trigger change event if category is pre-selected
        if ($('#category_id').val()) {
            $('#category_id').trigger('change');
        }
        
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
        
        // Add new custom field
        let fieldCounter = 0;
        $('#addFieldBtn').on('click', function() {
            // Check maximum limit
            const currentFields = $('.dynamic-field').length;
            if (currentFields >= 20) {
                $('#fieldCountWarning').show();
                return;
            }
            
            $('#fieldCountWarning').hide();
            
            const fieldId = `field_new_${fieldCounter++}`;
            
            const fieldHtml = `
                <div class="dynamic-field mb-3" id="${fieldId}">
                    <button type="button" class="btn btn-danger dynamic-field-remove" data-field-id="${fieldId}" title="Kaldır">
                        <i class="ti ti-x"></i>
                    </button>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="${fieldId}_name" class="form-label">Alan Adı</label>
                                <input type="text" class="form-control" id="${fieldId}_name" name="product_fields[${fieldId}][name]" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="${fieldId}_type" class="form-label">Alan Türü</label>
                                <select class="form-select field-type-select" id="${fieldId}_type" name="product_fields[${fieldId}][type]" data-field-id="${fieldId}" required>
                                    <option value="">Seçiniz</option>
                                    <option value="text">Metin</option>
                                    <option value="number">Sayı</option>
                                    <option value="select">Seçim</option>
                                    <option value="textarea">Metin Alanı</option>
                                    <option value="date">Tarih</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="${fieldId}_value" class="form-label">Değer</label>
                                <input type="text" class="form-control" id="${fieldId}_value" name="product_fields[${fieldId}][value]">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#dynamicFieldsContainer').append(fieldHtml);
        });
        
        // Remove custom field
        $(document).on('click', '.dynamic-field-remove', function() {
            const fieldId = $(this).data('field-id');
            $(`#${fieldId}`).remove();
            
            // Hide warning if under limit
            const currentFields = $('.dynamic-field').length;
            if (currentFields < 20) {
                $('#fieldCountWarning').hide();
            }
        });
        
        // Form submit handler - remove empty fields before submission
        $('#productForm').on('submit', function(e) {
            // Remove empty custom fields from form data
            $('.dynamic-field').each(function() {
                const $field = $(this);
                const fieldName = $field.find('input[name*="[name]"]').val();
                const fieldType = $field.find('select[name*="[type]"]').val();
                
                // If field name or type is empty, remove the field from DOM
                if (!fieldName || fieldName.trim() === '' || !fieldType || fieldType.trim() === '') {
                    $field.remove();
                }
            });
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