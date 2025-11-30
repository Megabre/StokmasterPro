<?php
/**
 * Megabre StokMaster Pro
 * Product Custom Fields
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Check if user has access
if (!$auth->hasAccess('products')) {
    Session::setFlash('error', 'Bu sayfaya erişim izniniz yok.');
    redirect('index.php');
}

// Initialize database connection
$db = Database::getInstance();

// Get subaction
$subaction = isset($_GET['subaction']) ? $_GET['subaction'] : '';

// Process subactions
switch ($subaction) {
    case 'add':
        // Add field form
        
        // Process form submission
        if (isPost()) {
            // Validate CSRF token
            if (!validateCsrf()) {
                redirect('index.php?module=products&action=fields');
            }
            
            // Get form data
            $name = post('name');
            $type = post('type');
            $label = post('label');
            $placeholder = post('placeholder');
            $required = post('required', 0);
            $options = post('options', '');
            $order = post('order', 0);
            $status = post('status', 1);
            
            // Validate form data
            $errors = [];
            
            if (empty($name)) {
                $errors[] = 'Alan adı gereklidir.';
            } elseif (!preg_match('/^[a-z0-9_]+$/', $name)) {
                $errors[] = 'Alan adı sadece küçük harf, rakam ve alt çizgi içerebilir.';
            } else {
                // Check if field name exists
                $db->query("SELECT COUNT(*) as count FROM product_fields WHERE name = :name");
                $db->bind(':name', $name);
                $result = $db->single();
                
                if ($result['count'] > 0) {
                    $errors[] = 'Bu alan adı zaten kullanılmaktadır.';
                }
            }
            
            if (empty($type)) {
                $errors[] = 'Alan tipi gereklidir.';
            }
            
            if (empty($label)) {
                $errors[] = 'Alan etiketi gereklidir.';
            }
            
            if ($type == 'select' || $type == 'radio' || $type == 'checkbox') {
                if (empty($options)) {
                    $errors[] = 'Seçenekler gereklidir.';
                }
            }
            
            if (empty($errors)) {
                try {
                    // Begin transaction
                    $db->beginTransaction();
                    
                    // Insert field
                    $db->query("INSERT INTO product_fields (name, type, label, placeholder, required, options, `order`, status, created_at) 
                               VALUES (:name, :type, :label, :placeholder, :required, :options, :order, :status, NOW())");
                    $db->bind(':name', $name);
                    $db->bind(':type', $type);
                    $db->bind(':label', $label);
                    $db->bind(':placeholder', $placeholder);
                    $db->bind(':required', $required);
                    $db->bind(':options', $options);
                    $db->bind(':order', $order);
                    $db->bind(':status', $status);
                    $db->execute();
                    
                    // Commit transaction before ALTER TABLE (DDL commands auto-commit)
                    $db->endTransaction();
                    
                    // Add field to products table (DDL commands don't work well in transactions)
                    try {
                        $db->query("ALTER TABLE products ADD COLUMN `" . $name . "` TEXT NULL");
                        $db->execute();
                    } catch (PDOException $alterError) {
                        // Column might already exist, try to delete the inserted field
                        try {
                            $db->query("DELETE FROM product_fields WHERE name = :name");
                            $db->bind(':name', $name);
                            $db->execute();
                        } catch (Exception $deleteError) {
                            // Ignore delete error
                        }
                        throw $alterError;
                    }
                    
                    // Set success message
                    Session::setFlash('success', 'Özel alan başarıyla eklendi.');
                    
                    // Redirect to fields list
                    redirect('index.php?module=products&action=fields');
                    
                } catch (Exception $e) {
                    // Try to rollback if transaction is still active
                    try {
                        if ($db->inTransaction()) {
                            $db->cancelTransaction();
                        }
                    } catch (Exception $rollbackError) {
                        // Ignore rollback error
                    }
                    
                    $errors[] = 'Özel alan eklenirken bir hata oluştu: ' . $e->getMessage();
                }
            }
        }
        
        // Include header
        include_once INCLUDES_PATH . 'header.php';
        
        // Display errors
        if (!empty($errors)) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">';
            foreach ($errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Yeni Özel Alan Ekle</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=products'); ?>">Ürünler</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=products&action=fields'); ?>">Özel Alanlar</a></li>
                <li class="breadcrumb-item active">Yeni Alan</li>
            </ul>
        </div>
    </div>
</div>

<!-- Add Field Form -->
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Alan Bilgileri</h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=products&action=fields&subaction=add'); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label required">Alan Adı</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo post('name', ''); ?>" required>
                                <small class="text-muted">Sadece küçük harf, rakam ve alt çizgi kullanın</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label required">Alan Tipi</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Seçiniz</option>
                                    <option value="text" <?php echo post('type') == 'text' ? 'selected' : ''; ?>>Metin</option>
                                    <option value="textarea" <?php echo post('type') == 'textarea' ? 'selected' : ''; ?>>Uzun Metin</option>
                                    <option value="number" <?php echo post('type') == 'number' ? 'selected' : ''; ?>>Sayı</option>
                                    <option value="select" <?php echo post('type') == 'select' ? 'selected' : ''; ?>>Seçim Kutusu</option>
                                    <option value="radio" <?php echo post('type') == 'radio' ? 'selected' : ''; ?>>Radyo Düğmesi</option>
                                    <option value="checkbox" <?php echo post('type') == 'checkbox' ? 'selected' : ''; ?>>Onay Kutusu</option>
                                    <option value="date" <?php echo post('type') == 'date' ? 'selected' : ''; ?>>Tarih</option>
                                    <option value="file" <?php echo post('type') == 'file' ? 'selected' : ''; ?>>Dosya</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="label" class="form-label required">Alan Etiketi</label>
                                <input type="text" class="form-control" id="label" name="label" value="<?php echo post('label', ''); ?>" required>
                                <small class="text-muted">Kullanıcıya gösterilecek etiket</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="placeholder" class="form-label">Placeholder</label>
                                <input type="text" class="form-control" id="placeholder" name="placeholder" value="<?php echo post('placeholder', ''); ?>">
                                <small class="text-muted">Alan içinde gösterilecek ipucu metni</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="options" class="form-label">Seçenekler</label>
                                <textarea class="form-control" id="options" name="options" rows="3"><?php echo post('options', ''); ?></textarea>
                                <small class="text-muted">Her satıra bir seçenek yazın (sadece seçim, radyo ve onay kutusu için)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="order" class="form-label">Sıralama</label>
                                <input type="number" class="form-control" id="order" name="order" value="<?php echo post('order', 0); ?>">
                                <small class="text-muted">Alanların görüntülenme sırası</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="required" name="required" value="1" <?php echo post('required') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="required">Zorunlu Alan</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="status" name="status" value="1" <?php echo post('status', 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Alanı Kaydet
                        </button>
                        <a href="<?php echo url('index.php?module=products&action=fields'); ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-arrow-left"></i> İptal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Show/hide options field based on field type
        $('#type').on('change', function() {
            var type = $(this).val();
            var optionsField = $('#options').closest('.mb-3');
            
            if (type == 'select' || type == 'radio' || type == 'checkbox') {
                optionsField.show();
            } else {
                optionsField.hide();
            }
        }).trigger('change');
    });
</script>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
        
    case 'edit':
        // Edit field form
        
        // Get field ID
        $fieldId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($fieldId <= 0) {
            Session::setFlash('error', 'Geçersiz alan ID\'si.');
            redirect('index.php?module=products&action=fields');
        }
        
        // Get field data
        $db->query("SELECT * FROM product_fields WHERE id = :id");
        $db->bind(':id', $fieldId);
        $field = $db->single();
        
        if (!$field) {
            Session::setFlash('error', 'Alan bulunamadı.');
            redirect('index.php?module=products&action=fields');
        }
        
        // Process form submission
        if (isPost()) {
            // Validate CSRF token
            if (!validateCsrf()) {
                redirect('index.php?module=products&action=fields');
            }
            
            // Get form data
            $name = post('name');
            $type = post('type');
            $label = post('label');
            $placeholder = post('placeholder');
            $required = post('required', 0);
            $options = post('options', '');
            $order = post('order', 0);
            $status = post('status', 1);
            
            // Validate form data
            $errors = [];
            
            if (empty($name)) {
                $errors[] = 'Alan adı gereklidir.';
            } elseif (!preg_match('/^[a-z0-9_]+$/', $name)) {
                $errors[] = 'Alan adı sadece küçük harf, rakam ve alt çizgi içerebilir.';
            } else {
                // Check if field name exists for other fields
                $db->query("SELECT COUNT(*) as count FROM product_fields WHERE name = :name AND id != :id");
                $db->bind(':name', $name);
                $db->bind(':id', $fieldId);
                $result = $db->single();
                
                if ($result['count'] > 0) {
                    $errors[] = 'Bu alan adı başka bir alan tarafından kullanılmaktadır.';
                }
            }
            
            if (empty($type)) {
                $errors[] = 'Alan tipi gereklidir.';
            }
            
            if (empty($label)) {
                $errors[] = 'Alan etiketi gereklidir.';
            }
            
            if ($type == 'select' || $type == 'radio' || $type == 'checkbox') {
                if (empty($options)) {
                    $errors[] = 'Seçenekler gereklidir.';
                }
            }
            
            if (empty($errors)) {
                try {
                    // Begin transaction
                    $db->beginTransaction();
                    
                    // Update field
                    $db->query("UPDATE product_fields SET 
                              name = :name, 
                              type = :type, 
                              label = :label, 
                              placeholder = :placeholder, 
                              required = :required, 
                              options = :options, 
                              `order` = :order, 
                              status = :status, 
                              updated_at = NOW() 
                              WHERE id = :id");
                    $db->bind(':name', $name);
                    $db->bind(':type', $type);
                    $db->bind(':label', $label);
                    $db->bind(':placeholder', $placeholder);
                    $db->bind(':required', $required);
                    $db->bind(':options', $options);
                    $db->bind(':order', $order);
                    $db->bind(':status', $status);
                    $db->bind(':id', $fieldId);
                    $db->execute();
                    
                    // Commit transaction before ALTER TABLE (DDL commands auto-commit)
                    $db->endTransaction();
                    
                    // Rename column if name changed (DDL commands don't work well in transactions)
                    if ($name != $field['name']) {
                        try {
                            $db->query("ALTER TABLE products CHANGE `" . $field['name'] . "` `" . $name . "` TEXT NULL");
                            $db->execute();
                        } catch (PDOException $alterError) {
                            // If column rename fails, revert the field name update
                            try {
                                $db->query("UPDATE product_fields SET name = :old_name WHERE id = :id");
                                $db->bind(':old_name', $field['name']);
                                $db->bind(':id', $fieldId);
                                $db->execute();
                            } catch (Exception $revertError) {
                                // Ignore revert error
                            }
                            throw $alterError;
                        }
                    }
                    
                    // Set success message
                    Session::setFlash('success', 'Özel alan başarıyla güncellendi.');
                    
                    // Redirect to fields list
                    redirect('index.php?module=products&action=fields');
                    
                } catch (Exception $e) {
                    // Try to rollback if transaction is still active
                    try {
                        if ($db->inTransaction()) {
                            $db->cancelTransaction();
                        }
                    } catch (Exception $rollbackError) {
                        // Ignore rollback error
                    }
                    
                    $errors[] = 'Özel alan güncellenirken bir hata oluştu: ' . $e->getMessage();
                }
            }
        }
        
        // Include header
        include_once INCLUDES_PATH . 'header.php';
        
        // Display errors
        if (!empty($errors)) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">';
            foreach ($errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Özel Alan Düzenle</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=products'); ?>">Ürünler</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=products&action=fields'); ?>">Özel Alanlar</a></li>
                <li class="breadcrumb-item active">Alan Düzenle</li>
            </ul>
        </div>
    </div>
</div>

<!-- Edit Field Form -->
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Alan Bilgileri</h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=products&action=fields&subaction=edit&id=' . $fieldId); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label required">Alan Adı</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e(post('name', $field['name'])); ?>" required>
                                <small class="text-muted">Sadece küçük harf, rakam ve alt çizgi kullanın</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label required">Alan Tipi</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Seçiniz</option>
                                    <option value="text" <?php echo $field['type'] == 'text' ? 'selected' : ''; ?>>Metin</option>
                                    <option value="textarea" <?php echo $field['type'] == 'textarea' ? 'selected' : ''; ?>>Uzun Metin</option>
                                    <option value="number" <?php echo $field['type'] == 'number' ? 'selected' : ''; ?>>Sayı</option>
                                    <option value="select" <?php echo $field['type'] == 'select' ? 'selected' : ''; ?>>Seçim Kutusu</option>
                                    <option value="radio" <?php echo $field['type'] == 'radio' ? 'selected' : ''; ?>>Radyo Düğmesi</option>
                                    <option value="checkbox" <?php echo $field['type'] == 'checkbox' ? 'selected' : ''; ?>>Onay Kutusu</option>
                                    <option value="date" <?php echo $field['type'] == 'date' ? 'selected' : ''; ?>>Tarih</option>
                                    <option value="file" <?php echo $field['type'] == 'file' ? 'selected' : ''; ?>>Dosya</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="label" class="form-label required">Alan Etiketi</label>
                                <input type="text" class="form-control" id="label" name="label" value="<?php echo e(post('label', $field['label'])); ?>" required>
                                <small class="text-muted">Kullanıcıya gösterilecek etiket</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="placeholder" class="form-label">Placeholder</label>
                                <input type="text" class="form-control" id="placeholder" name="placeholder" value="<?php echo e(post('placeholder', $field['placeholder'])); ?>">
                                <small class="text-muted">Alan içinde gösterilecek ipucu metni</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="options" class="form-label">Seçenekler</label>
                                <textarea class="form-control" id="options" name="options" rows="3"><?php echo e(post('options', $field['options'])); ?></textarea>
                                <small class="text-muted">Her satıra bir seçenek yazın (sadece seçim, radyo ve onay kutusu için)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="order" class="form-label">Sıralama</label>
                                <input type="number" class="form-control" id="order" name="order" value="<?php echo e(post('order', $field['order'])); ?>">
                                <small class="text-muted">Alanların görüntülenme sırası</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="required" name="required" value="1" <?php echo $field['required'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="required">Zorunlu Alan</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="status" name="status" value="1" <?php echo $field['status'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Değişiklikleri Kaydet
                        </button>
                        <a href="<?php echo url('index.php?module=products&action=fields'); ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-arrow-left"></i> İptal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Show/hide options field based on field type
        $('#type').on('change', function() {
            var type = $(this).val();
            var optionsField = $('#options').closest('.mb-3');
            
            if (type == 'select' || type == 'radio' || type == 'checkbox') {
                optionsField.show();
            } else {
                optionsField.hide();
            }
        }).trigger('change');
    });
</script>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
        
    case 'delete':
        // Delete field
        
        // Get field ID
        $fieldId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($fieldId <= 0) {
            Session::setFlash('error', 'Geçersiz alan ID\'si.');
            redirect('index.php?module=products&action=fields');
        }
        
        // Get field data
        $db->query("SELECT * FROM product_fields WHERE id = :id");
        $db->bind(':id', $fieldId);
        $field = $db->single();
        
        if (!$field) {
            Session::setFlash('error', 'Alan bulunamadı.');
            redirect('index.php?module=products&action=fields');
        }
        
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Delete field
            $db->query("DELETE FROM product_fields WHERE id = :id");
            $db->bind(':id', $fieldId);
            $db->execute();
            
            // Commit transaction before ALTER TABLE (DDL commands auto-commit)
            $db->endTransaction();
            
            // Drop column from products table (DDL commands don't work well in transactions)
            try {
                $db->query("ALTER TABLE products DROP COLUMN `" . $field['name'] . "`");
                $db->execute();
            } catch (PDOException $alterError) {
                // Column might not exist, that's okay - field is already deleted
                // Log the error but don't fail
                error_log('Column drop failed (might not exist): ' . $alterError->getMessage());
            }
            
            // Set success message
            Session::setFlash('success', 'Özel alan başarıyla silindi.');
            
        } catch (Exception $e) {
            // Try to rollback if transaction is still active
            try {
                if ($db->inTransaction()) {
                    $db->cancelTransaction();
                }
            } catch (Exception $rollbackError) {
                // Ignore rollback error
            }
            
            // Set error message
            Session::setFlash('error', 'Özel alan silinirken bir hata oluştu: ' . $e->getMessage());
        }
        
        // Redirect to fields list
        redirect('index.php?module=products&action=fields');
        break;
        
    default:
        // Show fields list
        
        // Get fields
        $db->query("SELECT * FROM product_fields ORDER BY `order` ASC, id DESC");
        $fields = $db->resultSet();
        
        // Include header
        include_once INCLUDES_PATH . 'header.php';
        
        // Show success/error messages
        if (Session::hasFlash('success')) {
            $flash = Session::getFlash('success');
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    ' . $flash['message'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        
        if (Session::hasFlash('error')) {
            $flash = Session::getFlash('error');
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ' . $flash['message'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Özel Alanlar</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=products'); ?>">Ürünler</a></li>
                <li class="breadcrumb-item active">Özel Alanlar</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="<?php echo url('index.php?module=products&action=fields&subaction=add'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Yeni Alan
            </a>
        </div>
    </div>
</div>

<!-- Fields Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped datatable">
                <thead>
                    <tr>
                        <th width="80">ID</th>
                        <th>Alan Adı</th>
                        <th>Etiket</th>
                        <th>Tip</th>
                        <th>Sıra</th>
                        <th>Zorunlu</th>
                        <th>Durum</th>
                        <th width="150">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $field): ?>
                    <tr>
                        <td><?php echo $field['id']; ?></td>
                        <td><?php echo e($field['name']); ?></td>
                        <td><?php echo e($field['label']); ?></td>
                        <td>
                            <?php 
                            $types = [
                                'text' => 'Metin',
                                'textarea' => 'Uzun Metin',
                                'number' => 'Sayı',
                                'select' => 'Seçim Kutusu',
                                'radio' => 'Radyo Düğmesi',
                                'checkbox' => 'Onay Kutusu',
                                'date' => 'Tarih',
                                'file' => 'Dosya'
                            ];
                            echo $types[$field['type']] ?? $field['type'];
                            ?>
                        </td>
                        <td><?php echo $field['order']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $field['required'] ? 'danger' : 'success'; ?>">
                                <?php echo $field['required'] ? 'Evet' : 'Hayır'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $field['status'] ? 'success' : 'danger'; ?>">
                                <?php echo $field['status'] ? 'Aktif' : 'Pasif'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="<?php echo url('index.php?module=products&action=fields&subaction=edit&id=' . $field['id']); ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <a href="<?php echo url('index.php?module=products&action=fields&subaction=delete&id=' . $field['id']); ?>" class="btn btn-sm btn-danger delete-confirm" data-bs-toggle="tooltip" title="Sil" data-field-name="<?php echo e($field['name']); ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        if (!$.fn.DataTable.isDataTable('.datatable')) {
            $('.datatable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
                }
            });
        }
        
        // Delete confirmation
        $('.delete-confirm').on('click', function(e) {
            e.preventDefault();
            
            const fieldName = $(this).data('field-name');
            const href = $(this).attr('href');
            
            if (confirm('Alan "' + fieldName + '" silinecek. Bu işlem geri alınamaz! Devam etmek istiyor musunuz?')) {
                window.location.href = href;
            }
        });
    });
</script>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
}
?> 