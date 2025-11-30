<?php
/**
 * Megabre StokMaster Pro
 * Edit Product
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

// Get product ID from URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    Session::setFlash('error', 'Geçersiz ürün ID\'si.');
    redirect('index.php?module=products');
}

// Get product data
$db->query("SELECT p.*, c.name as category_name 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE p.id = :id");
$db->bind(':id', $productId);
$product = $db->single();

if (!$product) {
    Session::setFlash('error', 'Ürün bulunamadı.');
    redirect('index.php?module=products');
}

// Get active product fields (system-wide dynamic fields)
$db->query("SELECT * FROM product_fields WHERE status = 1 ORDER BY `order` ASC, id ASC");
$systemFields = $db->resultSet();

// Get product dynamic fields
$allProductFields = $dynamicFields->getProductFields($productId);

// Filter out category fields - only keep custom fields
$productFields = [];
if (!empty($allProductFields)) {
    // Get category field names
    $categoryFieldNames = [];
    if ($product['category_id']) {
        $db->query("SELECT field_name FROM category_fields WHERE category_id = :category_id");
        $db->bind(':category_id', $product['category_id']);
        $categoryFieldsList = $db->resultSet();
        $categoryFieldNames = array_column($categoryFieldsList, 'field_name');
    }
    
    // Filter: only include fields that are NOT category fields
    foreach ($allProductFields as $field) {
        if (!in_array($field['field_name'], $categoryFieldNames)) {
            $productFields[] = $field;
        }
    }
}

// Get all categories
$db->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $db->resultSet();

// Get current stock level
$db->query("SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level 
            FROM stock_movements 
            WHERE product_id = :product_id");
$db->bind(':product_id', $productId);
$stockResult = $db->single();
$currentStock = $stockResult ? $stockResult['stock_level'] : 0;

// Get stock movements
$db->query("SELECT sm.*, DATE_FORMAT(sm.date, '%d.%m.%Y') as formatted_date 
            FROM stock_movements sm
            WHERE sm.product_id = :product_id
            ORDER BY sm.date DESC, sm.id DESC
            LIMIT 10");
$db->bind(':product_id', $productId);
$stockMovements = $db->resultSet();

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=products&action=edit&id=' . $productId);
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
    $productCustomFields = post('product_fields');
    
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
    } else {
        // Normalize price: handle Turkish format (4.064,00) and international format (4064.00)
        $priceClean = trim($price);
        
        // Replace comma with dot (Turkish decimal separator)
        $priceClean = str_replace(',', '.', $priceClean);
        
        // If there are multiple dots, the last one is decimal separator
        $dotCount = substr_count($priceClean, '.');
        if ($dotCount > 1) {
            // Multiple dots - last one is decimal separator, others are thousand separators
            $lastDotPos = strrpos($priceClean, '.');
            $beforeLastDot = substr($priceClean, 0, $lastDotPos);
            $afterLastDot = substr($priceClean, $lastDotPos + 1);
            // Remove all dots from before part (thousand separators)
            $beforeLastDot = str_replace('.', '', $beforeLastDot);
            $priceClean = $beforeLastDot . '.' . $afterLastDot;
        }
        
        // Remove spaces
        $priceClean = str_replace(' ', '', $priceClean);
        
        // Validate
        if (!is_numeric($priceClean) || floatval($priceClean) < 0) {
            $errors[] = 'Fiyat geçerli bir sayı olmalıdır.';
        } elseif (floatval($priceClean) > 999999999.99) {
            $errors[] = 'Fiyat çok büyük. Maksimum değer: 999.999.999,99';
        }
    }
    
    if (!empty($barcode) && !preg_match('/^[0-9]+$/', $barcode)) {
        $errors[] = 'Barkod sadece rakam içermelidir.';
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
        // Format price - same logic as validation
        $priceClean = trim($price);
        $priceClean = str_replace(',', '.', $priceClean);
        
        $dotCount = substr_count($priceClean, '.');
        if ($dotCount > 1) {
            $lastDotPos = strrpos($priceClean, '.');
            $beforeLastDot = str_replace('.', '', substr($priceClean, 0, $lastDotPos));
            $afterLastDot = substr($priceClean, $lastDotPos + 1);
            $priceClean = $beforeLastDot . '.' . $afterLastDot;
        }
        
        $priceClean = str_replace(' ', '', $priceClean);
        $price = floatval($priceClean);
        
        // Ensure price is within database limits
        if ($price > 999999999.99) {
            $price = 999999999.99;
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Build UPDATE query with dynamic fields
            $setParts = [
                'category_id = :category_id',
                'name = :name',
                'price = :price',
                'sku = :sku',
                'barcode = :barcode',
                'description = :description',
                'min_stock_level = :min_stock_level'
            ];
            
            $values = [
                ':category_id' => $categoryId,
                ':name' => $name,
                ':price' => $price,
                ':sku' => $sku,
                ':barcode' => $barcode,
                ':description' => $description,
                ':min_stock_level' => $minStockLevel
            ];
            
            // Add image if uploaded
            if ($uploadedImage) {
                $setParts[] = 'image = :image';
                $values[':image'] = $uploadedImage;
            }
            
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
                
                // Sanitize field name for placeholder (remove special characters, keep alphanumeric and underscore)
                $placeholderName = preg_replace('/[^a-zA-Z0-9_]/', '_', $fieldName);
                
                $setParts[] = "`{$fieldName}` = :{$placeholderName}";
                $values[":{$placeholderName}"] = $fieldValue;
            }
            
            $setParts[] = 'updated_at = NOW()';
            $setStr = implode(', ', $setParts);
            
            // Prepare old data for logging BEFORE update
            $oldData = [
                'name' => $product['name'],
                'price' => $product['price'],
                'sku' => $product['sku'] ?? '',
                'barcode' => $product['barcode'] ?? '',
                'description' => $product['description'] ?? '',
                'min_stock_level' => $product['min_stock_level'] ?? 0,
                'category_id' => $product['category_id'],
                'category_name' => $product['category_name'] ?? ''
            ];
            
            // Add system fields to old data
            foreach ($systemFields as $field) {
                $fieldName = $field['name'];
                $oldData[$fieldName] = $product[$fieldName] ?? null;
            }
            
            // Update product
            $db->query("UPDATE products SET {$setStr} WHERE id = :id");
            foreach ($values as $key => $value) {
                $db->bind($key, $value);
            }
            $db->bind(':id', $productId);
            $db->execute();
            
            // Get updated product data for logging (including category name)
            $db->query("SELECT p.*, c.name as category_name 
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       WHERE p.id = :id");
            $db->bind(':id', $productId);
            $updatedProduct = $db->single();
            
            // Prepare new data for logging
            $newData = [
                'name' => $name,
                'price' => $price,
                'sku' => $sku ?? '',
                'barcode' => $barcode ?? '',
                'description' => $description ?? '',
                'min_stock_level' => $minStockLevel ?? 0,
                'category_id' => $categoryId,
                'category_name' => $updatedProduct['category_name'] ?? ''
            ];
            
            // Add system fields to new data
            foreach ($systemFields as $field) {
                $fieldName = $field['name'];
                $newData[$fieldName] = $updatedProduct[$fieldName] ?? null;
            }
            
            // Log activity with detailed changes
            logActivity('update_product', 'product', $productId, $oldData, $newData, "Ürün #{$productId} güncellendi");
            
            if ($uploadedImage) {
                // Delete old image if exists
                if (!empty($product['image']) && file_exists(UPLOADS_PATH . 'products/' . $product['image'])) {
                    unlink(UPLOADS_PATH . 'products/' . $product['image']);
                }
            }
            
            // Get category field names first (needed for both category and custom fields handling)
            $categoryFieldNames = [];
            if ($categoryId) {
                $db->query("SELECT field_name FROM category_fields WHERE category_id = :category_id");
                $db->bind(':category_id', $categoryId);
                $categoryFieldsList = $db->resultSet();
                $categoryFieldNames = array_column($categoryFieldsList, 'field_name');
            }
            
            // Determine which table to use
            $tableName = null;
            try {
                $db->query("SELECT 1 FROM product_field_values LIMIT 1");
                $tableName = 'product_field_values';
            } catch (PDOException $e) {
                try {
                    $db->query("SELECT 1 FROM product_fields_backup LIMIT 1");
                    $tableName = 'product_fields_backup';
                } catch (PDOException $e2) {
                    $tableName = null;
                }
            }
            
            // Handle category fields - delete only category fields, preserve custom fields
            if ($categoryFields && is_array($categoryFields) && $tableName) {
                // Delete only category fields (not custom fields)
                if (!empty($categoryFieldNames)) {
                    $placeholders = [];
                    foreach ($categoryFieldNames as $index => $fieldName) {
                        $placeholders[] = ':cat_field_' . $index;
                    }
                    $placeholdersStr = implode(',', $placeholders);
                    $db->query("DELETE FROM {$tableName} WHERE product_id = :product_id AND field_name IN ({$placeholdersStr})");
                    $db->bind(':product_id', $productId);
                    foreach ($categoryFieldNames as $index => $fieldName) {
                        $db->bind(':cat_field_' . $index, $fieldName);
                    }
                    $db->execute();
                }
                
                // Insert new category fields
                foreach ($categoryFields as $field) {
                    $fieldId = $field['field_id'] ?? '';
                    $fieldName = $field['field_name'] ?? '';
                    $fieldType = $field['field_type'] ?? '';
                    $fieldValue = $field['field_value'] ?? '';
                    
                    if (!empty($fieldId) && !empty($fieldName) && !empty($fieldType)) {
                        $dynamicFields->createProductField($productId, $fieldName, $fieldType, $fieldValue);
                    }
                }
            }
            
            // Handle product custom fields - delete all custom fields first, then insert new ones
            if ($tableName) {
                // Delete all custom fields (not category fields)
                if (!empty($categoryFieldNames)) {
                    $placeholders = [];
                    foreach ($categoryFieldNames as $index => $fieldName) {
                        $placeholders[] = ':cat_field_' . $index;
                    }
                    $placeholdersStr = implode(',', $placeholders);
                    $db->query("DELETE FROM {$tableName} WHERE product_id = :product_id AND field_name NOT IN ({$placeholdersStr})");
                    $db->bind(':product_id', $productId);
                    foreach ($categoryFieldNames as $index => $fieldName) {
                        $db->bind(':cat_field_' . $index, $fieldName);
                    }
                    $db->execute();
                } else {
                    // No category fields, delete all fields
                    $db->query("DELETE FROM {$tableName} WHERE product_id = :product_id");
                    $db->bind(':product_id', $productId);
                    $db->execute();
                }
                
                // Also delete any empty fields (fields with empty name or type)
                // This cleans up any existing empty fields in database
                $db->query("DELETE FROM {$tableName} WHERE product_id = :product_id AND (field_name IS NULL OR field_name = '' OR field_type IS NULL OR field_type = '')");
                $db->bind(':product_id', $productId);
                $db->execute();
            }
            
            // Now insert new custom fields (only non-empty fields)
            if ($productCustomFields && is_array($productCustomFields)) {
                foreach ($productCustomFields as $fieldId => $field) {
                    $fieldName = trim($field['name'] ?? '');
                    $fieldType = trim($field['type'] ?? '');
                    $fieldValue = trim($field['value'] ?? '');
                    
                    // Only save fields that have both name and type filled (value can be empty)
                    if (!empty($fieldName) && !empty($fieldType)) {
                        // Create new field
                        $dynamicFields->createProductField($productId, $fieldName, $fieldType, $fieldValue);
                    }
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', 'Ürün başarıyla güncellendi.');
            
            // Redirect to product edit page
            redirect('index.php?module=products&action=edit&id=' . $productId);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            // Delete uploaded image if exists
            if ($uploadedImage && file_exists(UPLOADS_PATH . 'products/' . $uploadedImage)) {
                unlink(UPLOADS_PATH . 'products/' . $uploadedImage);
            }
            
            $errors[] = 'Ürün güncellenirken bir hata oluştu: ' . $e->getMessage();
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
            <h3 class="page-title">Ürün Düzenle</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=products'); ?>">Ürünler</a></li>
                <li class="breadcrumb-item active">Ürün Düzenle</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="<?php echo url('index.php?module=stock&action=add&product_id=' . $productId); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Stok Ekle
            </a>
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

<!-- Edit Product Form -->
<form action="<?php echo url('index.php?module=products&action=edit&id=' . $productId); ?>" method="post" id="productForm" enctype="multipart/form-data">
    <?php echo csrfField(); ?>
    
    <div class="row">
        <!-- Basic Information -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Temel Bilgiler</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label required">Kategori</label>
                                <select class="form-select select2" id="category_id" name="category_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label required">Ürün Adı</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($product['name']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label required">Fiyat (₺)</label>
                                <input type="text" class="form-control" id="price" name="price" value="<?php 
                                    // Display price as entered, remove trailing zeros
                                    $priceValue = $product['price'];
                                    if (is_numeric($priceValue)) {
                                        // Use number_format to ensure proper formatting, then remove trailing zeros
                                        $priceValue = number_format((float)$priceValue, 10, '.', '');
                                        // Remove trailing zeros and decimal point if not needed
                                        $priceValue = rtrim($priceValue, '0');
                                        $priceValue = rtrim($priceValue, '.');
                                    }
                                    echo $priceValue;
                                ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="sku" class="form-label">SKU Kodu</label>
                                <input type="text" class="form-control" id="sku" name="sku" value="<?php echo e($product['sku']); ?>">
                                <small class="text-muted">Stok takip kodu (opsiyonel)</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="barcode" class="form-label">Barkod</label>
                                <input type="text" class="form-control" id="barcode" name="barcode" value="<?php echo e($product['barcode']); ?>">
                                <small class="text-muted">Sadece rakam (opsiyonel)</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($product['description']); ?></textarea>
                        <small class="text-muted"><span id="descriptionCounter">0</span>/1000 karakter</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="min_stock_level" class="form-label">Min. Stok Seviyesi</label>
                                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" value="<?php echo $product['min_stock_level']; ?>" min="0">
                                <small class="text-muted">Stok bu seviyenin altına düştüğünde uyarı verilecek</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="image" class="form-label">Ürün Resmi</label>
                                <?php if (!empty($product['image'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo url('uploads/products/' . $product['image']); ?>" alt="<?php echo e($product['name']); ?>" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="text-muted">Maksimum 500KB (JPG, PNG, GIF)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Category Dynamic Fields -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Kategori Özellikleri</h5>
                </div>
                <div class="card-body">
                    <div id="categoryFieldsContainer">
                        <div class="alert alert-info">
                            Lütfen önce bir kategori seçin. Seçtiğiniz kategoriye ait özel alanlar burada görüntülenecektir.
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
                            $fieldValue = post($field['name'], $product[$field['name']] ?? '');
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
                                    <?php if (!empty($product[$field['name']])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Mevcut dosya: <?php echo e($product[$field['name']]); ?></small>
                                    </div>
                                    <?php endif; ?>
                                    <input type="file" 
                                           class="form-control" 
                                           id="field_<?php echo $field['name']; ?>" 
                                           name="<?php echo $field['name']; ?>"
                                           <?php echo $field['required'] && empty($product[$field['name']]) ? 'required' : ''; ?>>
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
            
            <!-- Product Custom Fields -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Özel Özellikler</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Bu ürüne özel ekstra özellikler ekleyebilirsiniz. Bu özellikler sadece bu ürün için geçerli olacaktır.</p>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-primary" id="addFieldBtn">
                            <i class="fas fa-plus"></i> Özellik Ekle
                        </button>
                        <span id="fieldCountWarning" class="text-danger ms-2" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i> Maksimum 20 özellik ekleyebilirsiniz.
                        </span>
                    </div>
                    
                    <div id="dynamicFieldsContainer" class="dynamic-fields-container">
                        <!-- Custom fields will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Side Panel -->
        <div class="col-md-4">
            <!-- Stock Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Stok Bilgisi</h5>
                </div>
                <div class="card-body">
                    <div class="stock-info text-center p-3">
                        <h2 class="mb-0 <?php echo $currentStock > 0 ? ($currentStock <= $product['min_stock_level'] ? 'text-warning' : 'text-success') : 'text-danger'; ?>">
                            <?php 
                                // Remove trailing zeros from stock display
                                $stockDisplay = $currentStock;
                                if (is_numeric($stockDisplay)) {
                                    $stockDisplay = rtrim(rtrim(sprintf('%.10f', $stockDisplay), '0'), '.');
                                }
                                echo $stockDisplay;
                            ?>
                        </h2>
                        <p class="text-muted mb-0">Mevcut Stok</p>
                        
                        <?php if ($currentStock <= 0): ?>
                        <div class="alert alert-danger mt-3 mb-0">
                            <i class="fas fa-exclamation-circle"></i> Stokta yok
                        </div>
                        <?php elseif ($currentStock <= $product['min_stock_level']): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle"></i> Kritik seviye
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success mt-3 mb-0">
                            <i class="fas fa-check-circle"></i> Stok yeterli
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="<?php echo url('index.php?module=stock&action=add&product_id=' . $productId); ?>" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Stok Ekle
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Stock History -->
            <?php if (!empty($stockMovements)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Son Stok Hareketleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Tür</th>
                                    <th>Miktar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stockMovements as $movement): ?>
                                <tr>
                                    <td><?php echo $movement['formatted_date']; ?></td>
                                    <td>
                                        <?php if ($movement['type'] == 'in'): ?>
                                        <span class="badge bg-success">Giriş</span>
                                        <?php elseif ($movement['type'] == 'out'): ?>
                                        <span class="badge bg-danger">Çıkış</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">Düzeltme</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php 
                                        $qtyDisplay = $movement['quantity'];
                                        if (is_numeric($qtyDisplay)) {
                                            $qtyDisplay = rtrim(rtrim(sprintf('%.10f', $qtyDisplay), '0'), '.');
                                        }
                                        echo $qtyDisplay;
                                    ?> <?php echo $movement['unit']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-center mt-2">
                        <a href="<?php echo url('index.php?module=stock&product_id=' . $productId); ?>" class="btn btn-sm btn-outline-secondary">
                            Tüm Hareketleri Gör
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Product Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Ürün Bilgisi</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>ID:</span>
                        <strong><?php echo $product['id']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Oluşturma Tarihi:</span>
                        <strong><?php echo formatDateTime($product['created_at']); ?></strong>
                    </div>
                    <?php if (!empty($product['updated_at']) && $product['updated_at'] != $product['created_at']): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Son Güncelleme:</span>
                        <strong><?php echo formatDateTime($product['updated_at']); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Form Controls -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <button type="button" class="btn btn-secondary w-100" onclick="window.location.href='<?php echo url('index.php?module=products'); ?>'">
                                <i class="fas fa-arrow-left"></i> Geri Dön
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> Kaydet
                            </button>
                        </div>
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
        
        // Character counter for description
        $('#description').on('input', function() {
            const max = 1000;
            const current = $(this).val().length;
            
            $('#descriptionCounter').text(current);
            
            if (current > max) {
                $(this).val($(this).val().substring(0, max));
                $('#descriptionCounter').text(max);
            }
        });
        
        // Trigger description counter on load
        $('#description').trigger('input');
        
        // Load category fields when category changes
        $('#category_id').on('change', function() {
            const categoryId = $(this).val();
            
            if (!categoryId) {
                $('#categoryFieldsContainer').html('<div class="alert alert-info">Lütfen önce bir kategori seçin. Seçtiğiniz kategoriye ait özel alanlar burada görüntülenecektir.</div>');
                return;
            }
            
            // Show loading
            $('#categoryFieldsContainer').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</div>');
            
            // Load fields for selected category
            $.ajax({
                url: '<?php echo url('api/categories.php?action=get_fields'); ?>',
                type: 'GET',
                data: { category_id: categoryId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Clear container
                        $('#categoryFieldsContainer').html('');
                        
                        // Add fields
                        if (response.fields && response.fields.length > 0) {
                            // Get product fields data
                            const productFields = <?php echo json_encode($productFields); ?>;
                            
                            response.fields.forEach(function(field, index) {
                                // Find field value if exists
                                let fieldValue = '';
                                if (productFields && productFields.length > 0) {
                                    const matchingField = productFields.find(pf => pf.field_name === field.field_name);
                                    if (matchingField) {
                                        fieldValue = matchingField.field_value;
                                    }
                                }
                                
                                addCategoryField(field, index, fieldValue);
                            });
                        } else {
                            $('#categoryFieldsContainer').html('<div class="alert alert-info">Bu kategori için henüz dinamik alan tanımlanmamış.</div>');
                        }
                    } else {
                        $('#categoryFieldsContainer').html('<div class="alert alert-danger">Kategori alanları yüklenirken bir hata oluştu: ' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#categoryFieldsContainer').html('<div class="alert alert-danger">Kategori alanları yüklenirken bir hata oluştu.</div>');
                }
            });
        });
        
        // Function to add category field
        function addCategoryField(field, index, fieldValue = '') {
            const fieldId = `cat_field_${index}`;
            
            let fieldHtml = `
                <div class="mb-3" id="${fieldId}">
                    <label for="${fieldId}_value" class="form-label">${field.field_name}</label>
                    <input type="hidden" name="category_fields[${index}][field_id]" value="${field.id}">
                    <input type="hidden" name="category_fields[${index}][field_name]" value="${field.field_name}">
                    <input type="hidden" name="category_fields[${index}][field_type]" value="${field.field_type}">
            `;
            
            // Create field based on type
            switch (field.field_type) {
                case 'text':
                    fieldHtml += `<input type="text" class="form-control" id="${fieldId}_value" name="category_fields[${index}][field_value]" value="${fieldValue}">`;
                    break;
                    
                case 'number':
                    fieldHtml += `<input type="number" class="form-control" id="${fieldId}_value" name="category_fields[${index}][field_value]" step="any" value="${fieldValue}">`;
                    break;
                    
                case 'select':
                    fieldHtml += `<select class="form-select" id="${fieldId}_value" name="category_fields[${index}][field_value]">
                        <option value="">Seçiniz</option>`;
                    
                    // Add options
                    try {
                        const options = JSON.parse(field.field_options);
                        if (Array.isArray(options)) {
                            options.forEach(function(option) {
                                const selected = fieldValue === option ? 'selected' : '';
                                fieldHtml += `<option value="${option}" ${selected}>${option}</option>`;
                            });
                        }
                    } catch (e) {
                        console.error('Options parsing error:', e);
                    }
                    
                    fieldHtml += `</select>`;
                    break;
                    
                case 'textarea':
                    fieldHtml += `<textarea class="form-control" id="${fieldId}_value" name="category_fields[${index}][field_value]" rows="3">${fieldValue}</textarea>`;
                    break;
                    
                case 'date':
                    fieldHtml += `<input type="date" class="form-control" id="${fieldId}_value" name="category_fields[${index}][field_value]" value="${fieldValue}">`;
                    break;
            }
            
            fieldHtml += `</div>`;
            
            // Append to container
            $('#categoryFieldsContainer').append(fieldHtml);
        }
        
        // Load custom product fields (only fields with name and type)
        function loadCustomFields() {
            const productFields = <?php echo json_encode($productFields); ?>;
            
            // Filter out empty fields (fields without name or type)
            const validFields = (productFields || []).filter(function(field) {
                return field.field_name && field.field_name.trim() !== '' && 
                       field.field_type && field.field_type.trim() !== '';
            });
            
            if (validFields && validFields.length > 0) {
                $('#dynamicFieldsContainer').html('');
                
                validFields.forEach(function(field, index) {
                    const fieldId = `field_${index}`;
                    
                    const fieldHtml = `
                        <div class="dynamic-field" id="${fieldId}">
                            <button type="button" class="btn btn-danger dynamic-field-remove" data-field-id="${fieldId}" title="Kaldır">
                                <i class="ti ti-x"></i>
                            </button>
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="mb-3">
                                        <label for="${fieldId}_name" class="form-label">Alan Adı</label>
                                        <input type="text" class="form-control" id="${fieldId}_name" name="product_fields[${fieldId}][name]" value="${field.field_name || ''}" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="${fieldId}_type" class="form-label">Alan Türü</label>
                                        <select class="form-select field-type-select" id="${fieldId}_type" name="product_fields[${fieldId}][type]" data-field-id="${fieldId}" required>
                                            <option value="">Seçiniz</option>
                                            <option value="text" ${field.field_type === 'text' ? 'selected' : ''}>Metin</option>
                                            <option value="number" ${field.field_type === 'number' ? 'selected' : ''}>Sayı</option>
                                            <option value="select" ${field.field_type === 'select' ? 'selected' : ''}>Seçim</option>
                                            <option value="textarea" ${field.field_type === 'textarea' ? 'selected' : ''}>Metin Alanı</option>
                                            <option value="date" ${field.field_type === 'date' ? 'selected' : ''}>Tarih</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="${fieldId}_value" class="form-label">Değer</label>
                                        <input type="text" class="form-control" id="${fieldId}_value" name="product_fields[${fieldId}][value]" value="${field.field_value || ''}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('#dynamicFieldsContainer').append(fieldHtml);
                });
            }
        }
        
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
        
        // Initialize fields on page load
        $('#category_id').trigger('change');
        loadCustomFields();
        
        // Set initial field counter based on existing fields
        fieldCounter = $('.dynamic-field').length;
    });
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>