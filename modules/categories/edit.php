<?php
/**
 * Megabre StokMaster Pro
 * Edit Category
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

// Get category ID from URL
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($categoryId <= 0) {
    Session::setFlash('error', 'Geçersiz kategori ID\'si.');
    redirect('index.php?module=categories');
}

// Get category data
$db->query("SELECT * FROM categories WHERE id = :id");
$db->bind(':id', $categoryId);
$category = $db->single();

if (!$category) {
    Session::setFlash('error', 'Kategori bulunamadı.');
    redirect('index.php?module=categories');
}

// Get category-specific dynamic fields
$fields = $dynamicFields->getCategoryFields($categoryId);

// Get system-wide dynamic fields (category_id = 0 or NULL)
$db->query("SELECT * FROM category_fields WHERE (category_id = 0 OR category_id IS NULL) AND is_active = 1 ORDER BY field_order ASC, created_at ASC");
$systemWideFields = $db->resultSet();

// Get system-wide field values for this category
$systemFieldValues = [];
try {
    $db->query("SELECT field_id, field_value FROM category_field_values WHERE category_id = :category_id");
    $db->bind(':category_id', $categoryId);
    $fieldValues = $db->resultSet();
    foreach ($fieldValues as $fv) {
        $systemFieldValues[$fv['field_id']] = $fv['field_value'];
    }
} catch (PDOException $e) {
    // Table might not exist yet, that's okay
    $systemFieldValues = [];
}

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=categories');
    }
    
    // Get form data
    $name = post('name');
    $description = post('description');
    $systemFields = post('system_fields', []);
    
    // Validate form data
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Kategori adı gereklidir.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = 'Kategori adı 2-100 karakter arasında olmalıdır.';
    }
    
    // Check if category name already exists (excluding current category)
    $db->query("SELECT id FROM categories WHERE name = :name AND id != :id");
    $db->bind(':name', $name);
    $db->bind(':id', $categoryId);
    $existingCategory = $db->single();
    
    if ($existingCategory) {
        $errors[] = 'Bu isimde bir kategori zaten mevcut.';
    }
    
    if (empty($errors)) {
        // Ensure category_field_values table exists (before any operations - CREATE TABLE commits transaction)
        try {
            $db->query("SELECT 1 FROM category_field_values LIMIT 1");
            $db->execute();
        } catch (PDOException $e) {
            // Table doesn't exist, create it
            try {
                $db->query("CREATE TABLE IF NOT EXISTS category_field_values (
                    id INT NOT NULL AUTO_INCREMENT,
                    category_id INT NOT NULL,
                    field_id INT NOT NULL,
                    field_value TEXT,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY category_field_unique (category_id, field_id),
                    KEY category_id (category_id),
                    KEY field_id (field_id),
                    CONSTRAINT category_field_values_ibfk_1 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
                    CONSTRAINT category_field_values_ibfk_2 FOREIGN KEY (field_id) REFERENCES category_fields (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $db->execute();
            } catch (PDOException $createError) {
                // Ignore if table already exists or other error
            }
        }
        
        // Prepare old data for logging
        $oldData = [
            'name' => $category['name'],
            'description' => $category['description'] ?? ''
        ];
        
        // Prepare new data for logging
        $newData = [
            'name' => $name,
            'description' => $description ?? ''
        ];
        
        // Update category
        $db->query("UPDATE categories SET name = :name, description = :description, updated_at = NOW() WHERE id = :id");
        $db->bind(':name', $name);
        $db->bind(':description', $description);
        $db->bind(':id', $categoryId);
        
        if ($db->execute()) {
            // Log activity with detailed changes
            logActivity('update_category', 'category', $categoryId, $oldData, $newData, "Kategori #{$categoryId} güncellendi");
            // Save system-wide field values
            if ($systemFields && is_array($systemFields)) {
                foreach ($systemFields as $fieldId => $fieldValue) {
                    $db->query("INSERT INTO category_field_values (category_id, field_id, field_value) 
                               VALUES (:category_id, :field_id, :field_value)
                               ON DUPLICATE KEY UPDATE field_value = :field_value2");
                    $db->bind(':category_id', $categoryId);
                    $db->bind(':field_id', $fieldId);
                    $db->bind(':field_value', $fieldValue !== '' ? $fieldValue : null);
                    $db->bind(':field_value2', $fieldValue !== '' ? $fieldValue : null);
                    $db->execute();
                }
            }
            
            // Set success message
            Session::setFlash('success', 'Kategori başarıyla güncellendi.');
            
            // Redirect to categories list
            redirect('index.php?module=categories');
        } else {
            $errors[] = 'Kategori güncellenirken bir hata oluştu.';
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
            <h3 class="page-title">Kategori Düzenle</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=categories'); ?>">Kategoriler</a></li>
                <li class="breadcrumb-item active">Kategori Düzenle</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="<?php echo url('index.php?module=categories&action=fields&id=' . $categoryId); ?>" class="btn btn-primary">
                <i class="fas fa-sliders-h"></i> Dinamik Alanları Düzenle
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

<!-- Edit Category Form -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Kategori Bilgileri</h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=categories&action=edit&id=' . $categoryId); ?>" method="post" id="categoryForm">
                    <?php echo csrfField(); ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label required">Kategori Adı</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo e($category['name']); ?>" required>
                        <small class="form-text text-muted">Kategori adı benzersiz olmalıdır (2-100 karakter).</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($category['description']); ?></textarea>
                    </div>
                    
                    <!-- System-Wide Dynamic Fields -->
                    <?php if (!empty($systemWideFields)): ?>
                    <div class="mt-4 mb-4">
                        <h5 class="mb-3">
                            <i class="fas fa-globe text-info" title="Sistem geneli dinamik alanlar - Tüm kategoriler için geçerlidir"></i>
                            Sistem Geneli Dinamik Alanlar
                        </h5>
                        
                        <div class="row">
                            <?php 
                            foreach ($systemWideFields as $field): 
                                $fieldValue = post('system_fields.' . $field['id'], isset($systemFieldValues[$field['id']]) ? $systemFieldValues[$field['id']] : '');
                            ?>
                            <div class="col-md-6 mb-3">
                                <label for="system_field_<?php echo $field['id']; ?>" class="form-label">
                                    <?php echo e($field['field_name']); ?>
                                    <?php if ($field['is_required']): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php
                                $fieldOptions = [];
                                if ($field['field_options']) {
                                    try {
                                        $fieldOptions = json_decode($field['field_options'], true);
                                        if (!is_array($fieldOptions)) {
                                            $fieldOptions = [];
                                        }
                                    } catch (Exception $e) {
                                        $fieldOptions = [];
                                    }
                                }
                                
                                switch ($field['field_type']):
                                    case 'text':
                                        ?>
                                        <input type="text" 
                                               class="form-control" 
                                               id="system_field_<?php echo $field['id']; ?>" 
                                               name="system_fields[<?php echo $field['id']; ?>]" 
                                               value="<?php echo e(post('system_fields.' . $field['id'], $fieldValue)); ?>"
                                               <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                        <?php
                                        break;
                                    
                                    case 'number':
                                        ?>
                                        <input type="number" 
                                               class="form-control" 
                                               id="system_field_<?php echo $field['id']; ?>" 
                                               name="system_fields[<?php echo $field['id']; ?>]" 
                                               value="<?php echo e(post('system_fields.' . $field['id'], $fieldValue)); ?>"
                                               step="any"
                                               <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                        <?php
                                        break;
                                    
                                    case 'textarea':
                                        ?>
                                        <textarea class="form-control" 
                                                  id="system_field_<?php echo $field['id']; ?>" 
                                                  name="system_fields[<?php echo $field['id']; ?>]" 
                                                  rows="3"
                                                  <?php echo $field['is_required'] ? 'required' : ''; ?>><?php echo e(post('system_fields.' . $field['id'], $fieldValue)); ?></textarea>
                                        <?php
                                        break;
                                    
                                    case 'select':
                                        ?>
                                        <select class="form-select" 
                                                id="system_field_<?php echo $field['id']; ?>" 
                                                name="system_fields[<?php echo $field['id']; ?>]"
                                                <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                            <option value="">Seçiniz</option>
                                            <?php foreach ($fieldOptions as $option): ?>
                                            <option value="<?php echo e($option); ?>" <?php echo (post('system_fields.' . $field['id'], $fieldValue) == $option) ? 'selected' : ''; ?>>
                                                <?php echo e($option); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php
                                        break;
                                    
                                    case 'date':
                                        ?>
                                        <input type="date" 
                                               class="form-control" 
                                               id="system_field_<?php echo $field['id']; ?>" 
                                               name="system_fields[<?php echo $field['id']; ?>]" 
                                               value="<?php echo e(post('system_fields.' . $field['id'], $fieldValue)); ?>"
                                               <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                        <?php
                                        break;
                                endswitch;
                                ?>
                                
                                <input type="hidden" name="system_fields_info[<?php echo $field['id']; ?>][field_name]" value="<?php echo e($field['field_name']); ?>">
                                <input type="hidden" name="system_fields_info[<?php echo $field['id']; ?>][field_type]" value="<?php echo $field['field_type']; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Oluşturma Tarihi</label>
                        <p class="form-control-static"><?php echo formatDateTime($category['created_at']); ?></p>
                    </div>
                    
                    <?php if (!empty($category['updated_at']) && $category['updated_at'] != $category['created_at']): ?>
                    <div class="mb-3">
                        <label class="form-label">Son Güncelleme</label>
                        <p class="form-control-static"><?php echo formatDateTime($category['updated_at']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo url('index.php?module=categories'); ?>'">
                                    <i class="fas fa-arrow-left"></i> Geri Dön
                                </button>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Değişiklikleri Kaydet
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- System-Wide Dynamic Fields -->
        <?php if (!empty($systemWideFields)): ?>
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">Sistem Geneli Dinamik Alanlar</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Bu alanlar tüm kategoriler için geçerlidir.</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Alan Adı</th>
                                <th>Tür</th>
                                <th>Zorunlu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($systemWideFields as $field): ?>
                            <tr>
                                <td><?php echo e($field['field_name']); ?></td>
                                <td>
                                    <?php
                                    $type_labels = [
                                        'text' => '<span class="badge bg-info">Metin</span>',
                                        'number' => '<span class="badge bg-warning">Sayı</span>',
                                        'select' => '<span class="badge bg-success">Seçim</span>',
                                        'textarea' => '<span class="badge bg-primary">Metin Alanı</span>',
                                        'date' => '<span class="badge bg-secondary">Tarih</span>'
                                    ];
                                    echo $type_labels[$field['field_type']] ?? $field['field_type'];
                                    ?>
                                </td>
                                <td>
                                    <?php if ($field['is_required']): ?>
                                        <span class="badge bg-danger">Evet</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Hayır</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo url('index.php?module=categories&action=fields'); ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-cog"></i> Sistem Geneli Alanları Yönet
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Category-Specific Dynamic Fields -->
        <div class="card <?php echo !empty($systemWideFields) ? 'mt-4' : ''; ?>">
            <div class="card-header">
                <h5 class="card-title">Kategoriye Özel Dinamik Alanlar</h5>
            </div>
            <div class="card-body">
                <?php if (empty($fields)): ?>
                <div class="alert alert-info mb-0">
                    Bu kategori için henüz dinamik alan tanımlanmamış.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Alan Adı</th>
                                <th>Tür</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields as $field): ?>
                            <tr>
                                <td><?php echo e($field['field_name']); ?></td>
                                <td><?php echo $dynamicFields->getFieldTypeLabel($field['field_type']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="<?php echo url('index.php?module=categories&action=fields&id=' . $categoryId); ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i> Kategoriye Özel Alanları Düzenle
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Category Stats -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Kategori İstatistikleri</h5>
            </div>
            <div class="card-body">
                <?php
                // Get product count
                $db->query("SELECT COUNT(*) as count FROM products WHERE category_id = :category_id");
                $db->bind(':category_id', $categoryId);
                $productCount = $db->single()['count'];
                
                // Get field counts
                $categoryFieldCount = count($fields);
                $systemWideFieldCount = count($systemWideFields);
                $totalFieldCount = $categoryFieldCount + $systemWideFieldCount;
                ?>
                
                <div class="d-flex justify-content-between mb-2">
                    <div>Ürün Sayısı:</div>
                    <div><strong><?php echo $productCount; ?></strong></div>
                </div>
                
                <div class="d-flex justify-content-between mb-2">
                    <div>Kategoriye Özel Alan:</div>
                    <div><strong><?php echo $categoryFieldCount; ?></strong></div>
                </div>
                
                <div class="d-flex justify-content-between mb-2">
                    <div>Sistem Geneli Alan:</div>
                    <div><strong><?php echo $systemWideFieldCount; ?></strong></div>
                </div>
                
                <div class="d-flex justify-content-between mb-2 border-top pt-2">
                    <div><strong>Toplam Dinamik Alan:</strong></div>
                    <div><strong><?php echo $totalFieldCount; ?></strong></div>
                </div>
                
                <?php if ($productCount > 0): ?>
                <div class="mt-3">
                    <a href="<?php echo url('index.php?module=products&category_id=' . $categoryId); ?>" class="btn btn-sm btn-info w-100">
                        <i class="fas fa-box"></i> Bu Kategorideki Ürünleri Göster
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>