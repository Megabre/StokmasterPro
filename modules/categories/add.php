<?php
/**
 * Megabre StokMaster Pro
 * Add Category
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

// Get system-wide dynamic fields (category_id = 0 or NULL)
$db->query("SELECT * FROM category_fields WHERE (category_id = 0 OR category_id IS NULL) AND is_active = 1 ORDER BY field_order ASC, created_at ASC");
$systemWideFields = $db->resultSet();

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=categories');
    }
    
    // Get form data
    $name = post('name');
    $description = post('description');
    $fields = post('fields');
    $systemFields = post('system_fields', []);
    
    // Validate form data
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Kategori adı gereklidir.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = 'Kategori adı 2-100 karakter arasında olmalıdır.';
    }
    
    // Check if category name already exists
    $db->query("SELECT id FROM categories WHERE name = :name");
    $db->bind(':name', $name);
    $existingCategory = $db->single();
    
    if ($existingCategory) {
        $errors[] = 'Bu isimde bir kategori zaten mevcut.';
    }
    
    if (empty($errors)) {
        // Ensure category_field_values table exists (before transaction - CREATE TABLE commits transaction)
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
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert category
            $db->query("INSERT INTO categories (name, description) VALUES (:name, :description)");
            $db->bind(':name', $name);
            $db->bind(':description', $description);
            $db->execute();
            
            $categoryId = $db->lastInsertId();
            
            // Log activity
            logActivity('add_category', 'category', $categoryId, null, [
                'name' => $name,
                'description' => $description ?? ''
            ], "Yeni kategori eklendi: {$name}");
            
            // Insert dynamic fields if any
            if ($fields && is_array($fields)) {
                foreach ($fields as $fieldKey => $field) {
                    $fieldName = $field['name'] ?? '';
                    $fieldType = $field['type'] ?? '';
                    $fieldOptions = isset($field['options']) ? $dynamicFields->parseFieldOptions($field['options']) : null;
                    $fieldValue = $field['value'] ?? '';
                    
                    if (!empty($fieldName) && !empty($fieldType)) {
                        $newFieldId = $dynamicFields->createCategoryField($categoryId, $fieldName, $fieldType, $fieldOptions);
                        
                        // Save field value if provided
                        if ($newFieldId && $fieldValue !== '' && $fieldValue !== null) {
                            try {
                                $db->query("INSERT INTO category_field_values (category_id, field_id, field_value) 
                                           VALUES (:category_id, :field_id, :field_value)");
                                $db->bind(':category_id', $categoryId);
                                $db->bind(':field_id', $newFieldId);
                                $db->bind(':field_value', $fieldValue);
                                $db->execute();
                            } catch (PDOException $e) {
                                // Ignore errors
                            }
                        }
                    }
                }
            }
            
            // Save system-wide field values
            if ($systemFields && is_array($systemFields)) {
                foreach ($systemFields as $fieldId => $fieldValue) {
                    if ($fieldValue !== '' && $fieldValue !== null) {
                        $db->query("INSERT INTO category_field_values (category_id, field_id, field_value) 
                                   VALUES (:category_id, :field_id, :field_value)
                                   ON DUPLICATE KEY UPDATE field_value = :field_value2");
                        $db->bind(':category_id', $categoryId);
                        $db->bind(':field_id', $fieldId);
                        $db->bind(':field_value', $fieldValue);
                        $db->bind(':field_value2', $fieldValue);
                        $db->execute();
                    }
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', 'Kategori başarıyla eklendi.');
            
            // Redirect to categories list
            redirect('index.php?module=categories');
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            $errors[] = 'Kategori eklenirken bir hata oluştu: ' . $e->getMessage();
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
            <h3 class="page-title"><?php echo t('categories_add_title', 'Kategori Ekle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=categories'); ?>"><?php echo t('categories_title', 'Kategoriler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('categories_add_title', 'Kategori Ekle'); ?></li>
            </ul>
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

<!-- Add Category Form -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('categories_category_info', 'Kategori Bilgileri'); ?></h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=categories&action=add'); ?>" method="post" id="categoryForm">
                    <?php echo csrfField(); ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label required"><?php echo t('categories_category_name', 'Kategori Adı'); ?></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo post('name', ''); ?>" required>
                                <small class="form-text text-muted"><?php echo t('categories_category_name_unique', 'Kategori adı benzersiz olmalıdır (2-100 karakter).'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="description" class="form-label"><?php echo t('categories_description', 'Açıklama'); ?></label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo post('description', ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System-Wide Dynamic Fields Section -->
                    <?php if (!empty($systemWideFields)): ?>
                    <div class="mt-4">
                        <h5>
                            <i class="fas fa-globe text-info" title="Sistem geneli dinamik alanlar - Tüm kategoriler için geçerlidir"></i>
                            <?php echo t('categories_system_fields_label', 'Sistem Geneli Dinamik Alanlar'); ?>
                        </h5>
                        
                        <div class="row">
                            <?php foreach ($systemWideFields as $index => $field): ?>
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
                                               value="<?php echo post('system_fields.' . $field['id'], ''); ?>"
                                               <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                        <?php
                                        break;
                                    
                                    case 'number':
                                        ?>
                                        <input type="number" 
                                               class="form-control" 
                                               id="system_field_<?php echo $field['id']; ?>" 
                                               name="system_fields[<?php echo $field['id']; ?>]" 
                                               value="<?php echo post('system_fields.' . $field['id'], ''); ?>"
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
                                                  <?php echo $field['is_required'] ? 'required' : ''; ?>><?php echo post('system_fields.' . $field['id'], ''); ?></textarea>
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
                                            <option value="<?php echo e($option); ?>" <?php echo post('system_fields.' . $field['id']) == $option ? 'selected' : ''; ?>>
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
                                               value="<?php echo post('system_fields.' . $field['id'], ''); ?>"
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
                    
                    <!-- Category-Specific Dynamic Fields Section -->
                    <div class="mt-4">
                        <h5><?php echo t('categories_dynamic_fields_label', 'Kategoriye Özel Dinamik Alanlar'); ?></h5>
                        <p class="text-muted"><?php echo t('categories_dynamic_fields_desc', 'Bu kategoriye özel alanlar tanımlayabilirsiniz. Örneğin "Boy", "Renk", "Malzeme" gibi özellikler ekleyebilirsiniz.'); ?></p>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-primary" id="addFieldBtn">
                                <i class="fas fa-plus"></i> <?php echo t('categories_add_field', 'Alan Ekle'); ?>
                            </button>
                            <span id="fieldCountWarning" class="text-danger ms-2" style="display: none;">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo t('categories_max_fields_warning', 'Maksimum 20 alan ekleyebilirsiniz.'); ?>
                            </span>
                        </div>
                        
                        <div id="dynamicFieldsContainer" class="dynamic-fields-container">
                            <!-- Dynamic fields will be added here -->
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo url('index.php?module=categories'); ?>'">
                                    <i class="fas fa-arrow-left"></i> <?php echo t('ui_go_back', 'Geri Dön'); ?>
                                </button>
                                <button type="reset" class="btn btn-warning">
                                    <i class="fas fa-eraser"></i> <?php echo t('categories_form_clear', 'Formu Temizle'); ?>
                                </button>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo t('categories_save_category', 'Kategoriyi Kaydet'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Help Section -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('categories_help_tips', 'Yardım & İpuçları'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><?php echo t('categories_creation_tips', 'Kategori Oluşturma İpuçları'); ?></h5>
                    <ul class="mb-0">
                        <li><strong><?php echo t('categories_creation_tip_name', 'Kategori Adı:'); ?></strong> <?php echo t('categories_creation_tip_name_desc', 'Benzersiz olmalıdır, 2-100 karakter arası olmalıdır.'); ?></li>
                        <li><strong><?php echo t('categories_creation_tip_description', 'Açıklama:'); ?></strong> <?php echo t('categories_creation_tip_description_desc', 'Kategorinin kullanım amacını veya özelliklerini belirtebilirsiniz.'); ?></li>
                        <li><strong><?php echo t('categories_creation_tip_fields', 'Dinamik Alanlar:'); ?></strong> <?php echo t('categories_creation_tip_fields_desc', 'Sistem geneli dinamik alanlar tüm kategoriler için geçerlidir. Ayrıca bu kategoriye özel özellikler de tanımlayabilirsiniz.'); ?></li>
                    </ul>
                    <p class="mb-0 mt-2"><strong><?php echo t('categories_creation_note', 'Not:'); ?></strong> <?php echo t('categories_creation_note_desc', 'Kategori oluşturulduktan sonra adı değiştirilebilir ancak dinamik alanlar düzenlenebilir olacaktır.'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>