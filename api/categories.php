<?php
/**
 * Megabre StokMaster Pro
 * Categories API
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Include configuration
require_once '../config/config.php';

// Debug log
error_log("API Request: " . $_SERVER['REQUEST_URI']);
error_log("Script Path: " . __FILE__);
error_log("Document Root: " . $_SERVER['DOCUMENT_ROOT']);

// Include core files
require_once CORE_PATH . 'Database.php';
require_once CORE_PATH . 'Session.php';
require_once CORE_PATH . 'Authentication.php';
require_once CORE_PATH . 'Language.php';
require_once CORE_PATH . 'DynamicFields.php';
require_once CORE_PATH . 'helpers.php';

// Initialize authentication
$auth = new Authentication();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Yetkisiz erişim'], 401);
}

// Initialize database connection
$db = Database::getInstance();

// Initialize dynamic fields class
$dynamicFields = new DynamicFields();

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Process action
switch ($action) {
    case 'get_all':
        // Get all categories
        $db->query("SELECT * FROM categories ORDER BY name ASC");
        $categories = $db->resultSet();
        
        jsonResponse(['success' => true, 'categories' => $categories]);
        break;
        
    case 'get':
        // Get single category
        $categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($categoryId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz kategori ID\'si'], 400);
        }
        
        $db->query("SELECT * FROM categories WHERE id = :id");
        $db->bind(':id', $categoryId);
        $category = $db->single();
        
        if (!$category) {
            jsonResponse(['success' => false, 'message' => 'Kategori bulunamadı'], 404);
        }
        
        jsonResponse(['success' => true, 'category' => $category]);
        break;
        
    case 'create':
        // Create new category
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $name = post('name');
        $description = post('description');
        $fields = post('fields');
        
        // Validate data
        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Kategori adı gereklidir'], 400);
        }
        
        // Check if category name already exists
        $db->query("SELECT id FROM categories WHERE name = :name");
        $db->bind(':name', $name);
        $existingCategory = $db->single();
        
        if ($existingCategory) {
            jsonResponse(['success' => false, 'message' => 'Bu isimde bir kategori zaten mevcut'], 400);
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
            
            // Insert dynamic fields if any
            if ($fields && is_array($fields)) {
                foreach ($fields as $field) {
                    $fieldName = $field['name'] ?? '';
                    $fieldType = $field['type'] ?? '';
                    $fieldOptions = isset($field['options']) ? $dynamicFields->parseFieldOptions($field['options']) : null;
                    
                    if (!empty($fieldName) && !empty($fieldType)) {
                        $dynamicFields->createCategoryField($categoryId, $fieldName, $fieldType, $fieldOptions);
                    }
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Kategori başarıyla eklendi', 'category_id' => $categoryId]);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Kategori eklenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'update':
        // Update category
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($categoryId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz kategori ID\'si'], 400);
        }
        
        $name = post('name');
        $description = post('description');
        
        // Validate data
        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Kategori adı gereklidir'], 400);
        }
        
        // Check if category exists
        $db->query("SELECT * FROM categories WHERE id = :id");
        $db->bind(':id', $categoryId);
        $category = $db->single();
        
        if (!$category) {
            jsonResponse(['success' => false, 'message' => 'Kategori bulunamadı'], 404);
        }
        
        // Check if category name already exists (excluding current category)
        $db->query("SELECT id FROM categories WHERE name = :name AND id != :id");
        $db->bind(':name', $name);
        $db->bind(':id', $categoryId);
        $existingCategory = $db->single();
        
        if ($existingCategory) {
            jsonResponse(['success' => false, 'message' => 'Bu isimde bir kategori zaten mevcut'], 400);
        }
        
        // Update category
        $db->query("UPDATE categories SET name = :name, description = :description, updated_at = NOW() WHERE id = :id");
        $db->bind(':name', $name);
        $db->bind(':description', $description);
        $db->bind(':id', $categoryId);
        
        if ($db->execute()) {
            jsonResponse(['success' => true, 'message' => 'Kategori başarıyla güncellendi']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Kategori güncellenirken bir hata oluştu'], 500);
        }
        break;
        
    case 'delete':
        // Delete category
        // Allow POST requests (frontend sends POST with _method: DELETE)
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($categoryId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz kategori ID\'si'], 400);
        }
        
        // Check if category exists
        $db->query("SELECT * FROM categories WHERE id = :id");
        $db->bind(':id', $categoryId);
        $category = $db->single();
        
        if (!$category) {
            jsonResponse(['success' => false, 'message' => 'Kategori bulunamadı'], 404);
        }
        
        // Check if category has products
        $db->query("SELECT COUNT(*) as count FROM products WHERE category_id = :category_id");
        $db->bind(':category_id', $categoryId);
        $productCount = $db->single()['count'];
        
        if ($productCount > 0) {
            jsonResponse(['success' => false, 'message' => 'Bu kategoriye ait ' . $productCount . ' ürün bulunmaktadır. Lütfen önce ürünleri başka bir kategoriye taşıyın veya silin.'], 400);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Delete category fields first (if table exists)
            try {
                $db->query("DELETE FROM category_fields WHERE category_id = :category_id");
                $db->bind(':category_id', $categoryId);
                $db->execute();
            } catch (PDOException $e) {
                // Table doesn't exist or column doesn't exist, skip
            }
            
            // Delete category
            $db->query("DELETE FROM categories WHERE id = :id");
            $db->bind(':id', $categoryId);
            $db->execute();
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Kategori başarıyla silindi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Kategori silinirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'bulk-delete':
        // Bulk delete categories
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $categoryIds = isset($_POST['ids']) ? $_POST['ids'] : [];
        $forceDelete = isset($_POST['force_delete']) && $_POST['force_delete'] == '1';
        
        if (empty($categoryIds) || !is_array($categoryIds)) {
            jsonResponse(['success' => false, 'message' => 'Geçerli kategori ID\'leri gönderilmelidir'], 400);
        }
        
        // Validate and sanitize IDs
        $categoryIds = array_map('intval', $categoryIds);
        $categoryIds = array_filter($categoryIds, function($id) { return $id > 0; });
        
        if (empty($categoryIds)) {
            jsonResponse(['success' => false, 'message' => 'Geçerli kategori ID\'si bulunamadı'], 400);
        }
        
        $deletedCount = 0;
        $failedCount = 0;
        $errors = [];
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            foreach ($categoryIds as $categoryId) {
                try {
                    // Check if category exists
                    $db->query("SELECT * FROM categories WHERE id = :id");
                    $db->bind(':id', $categoryId);
                    $category = $db->single();
                    
                    if (!$category) {
                        $failedCount++;
                        $errors[] = "Kategori ID $categoryId bulunamadı";
                        continue;
                    }
                    
                    // Check if category has products
                    $db->query("SELECT COUNT(*) as count FROM products WHERE category_id = :category_id");
                    $db->bind(':category_id', $categoryId);
                    $productCount = $db->single()['count'];
                    
                    if ($productCount > 0 && !$forceDelete) {
                        $failedCount++;
                        $errors[] = "Kategori ID $categoryId içinde $productCount ürün bulunmaktadır";
                        continue;
                    }
                    
                    // Delete products if force delete and category has products
                    if ($productCount > 0 && $forceDelete) {
                        // Get product IDs
                        $db->query("SELECT id FROM products WHERE category_id = :category_id");
                        $db->bind(':category_id', $categoryId);
                        $products = $db->resultSet();
                        $productIds = array_column($products, 'id');
                        
                        if (!empty($productIds)) {
                            foreach ($productIds as $productId) {
                                // Delete product field values
                                try {
                                    $db->query("DELETE FROM product_field_values WHERE product_id = :product_id");
                                    $db->bind(':product_id', $productId);
                                    $db->execute();
                                } catch (PDOException $e) {
                                    // Table doesn't exist, skip
                                }
                                
                                // Delete order items
                                try {
                                    $db->query("DELETE FROM order_items WHERE product_id = :product_id");
                                    $db->bind(':product_id', $productId);
                                    $db->execute();
                                } catch (PDOException $e) {
                                    // Table doesn't exist, skip
                                }
                                
                                // Delete stock movements
                                $db->query("DELETE FROM stock_movements WHERE product_id = :product_id");
                                $db->bind(':product_id', $productId);
                                $db->execute();
                            }
                            
                            // Delete products
                            $productIdsStr = implode(',', $productIds);
                            $db->query("DELETE FROM products WHERE id IN ($productIdsStr)");
                            $db->execute();
                        }
                    }
                    
                    // Delete category fields
                    try {
                        $db->query("DELETE FROM category_fields WHERE category_id = :category_id");
                        $db->bind(':category_id', $categoryId);
                        $db->execute();
                    } catch (PDOException $e) {
                        // Table doesn't exist, skip
                    }
                    
                    // Delete category
                    $db->query("DELETE FROM categories WHERE id = :id");
                    $db->bind(':id', $categoryId);
                    $db->execute();
                    
                    $deletedCount++;
                    
                } catch (PDOException $e) {
                    $failedCount++;
                    $errors[] = "Kategori ID $categoryId silinirken hata: " . $e->getMessage();
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            $message = "$deletedCount kategori başarıyla silindi.";
            if ($failedCount > 0) {
                $message .= " $failedCount kategori silinirken hata oluştu.";
            }
            
            jsonResponse([
                'success' => $deletedCount > 0,
                'message' => $message,
                'deleted_count' => $deletedCount,
                'failed_count' => $failedCount,
                'errors' => $errors
            ]);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Toplu silme işlemi sırasında bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'get_fields':
        // Get category fields
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        
        // Debug log
        error_log("Category ID: " . $categoryId);
        error_log("Request URI: " . $_SERVER['REQUEST_URI']);
        error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
        error_log("GET params: " . print_r($_GET, true));
        
        if ($categoryId <= 0) {
            error_log("Invalid category ID");
            jsonResponse(['success' => false, 'message' => 'Geçersiz kategori ID\'si'], 400);
        }
        
        // Check if category exists
        $db->query("SELECT * FROM categories WHERE id = :id");
        $db->bind(':id', $categoryId);
        $category = $db->single();
        
        // Debug log
        error_log("Category query result: " . print_r($category, true));
        
        if (!$category) {
            error_log("Category not found");
            jsonResponse(['success' => false, 'message' => 'Kategori bulunamadı'], 404);
        }
        
        // Get fields
        $fields = $dynamicFields->getCategoryFields($categoryId);
        
        // Debug log
        error_log("Fields query result: " . print_r($fields, true));
        
        jsonResponse(['success' => true, 'fields' => $fields]);
        break;
        
    case 'update_fields':
        // Update category fields
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        
        if ($categoryId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz kategori ID\'si'], 400);
        }
        
        // Check if category exists
        $db->query("SELECT * FROM categories WHERE id = :id");
        $db->bind(':id', $categoryId);
        $category = $db->single();
        
        if (!$category) {
            jsonResponse(['success' => false, 'message' => 'Kategori bulunamadı'], 404);
        }
        
        $formFields = post('fields');
        
        // Validate data
        if (empty($formFields) || !is_array($formFields)) {
            jsonResponse(['success' => false, 'message' => 'En az bir dinamik alan tanımlamalısınız'], 400);
        }
        
        // Check for duplicate field names
        $fieldNames = [];
        foreach ($formFields as $fieldId => $field) {
            $fieldName = $field['name'] ?? '';
            
            if (empty($fieldName)) {
                jsonResponse(['success' => false, 'message' => 'Alan adı boş olamaz'], 400);
            }
            
            if (in_array($fieldName, $fieldNames)) {
                jsonResponse(['success' => false, 'message' => '"' . $fieldName . '" alanı zaten eklenmiş. Alan adları benzersiz olmalıdır'], 400);
            }
            
            $fieldNames[] = $fieldName;
        }
        
        // Get existing fields
        $existingFields = $dynamicFields->getCategoryFields($categoryId);
        $existingFieldIds = array_map(function($field) {
            return $field['id'];
        }, $existingFields);
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Track processed fields
            $processedFieldIds = [];
            
            // Process each field
            foreach ($formFields as $fieldId => $field) {
                $fieldName = $field['name'];
                $fieldType = $field['type'];
                $fieldOptions = isset($field['options']) ? $dynamicFields->parseFieldOptions($field['options']) : null;
                
                if (isset($field['id']) && in_array($field['id'], $existingFieldIds)) {
                    // Update existing field
                    $dynamicFields->updateCategoryField($field['id'], $fieldName, $fieldType, $fieldOptions);
                    $processedFieldIds[] = $field['id'];
                } else {
                    // Create new field
                    $dynamicFields->createCategoryField($categoryId, $fieldName, $fieldType, $fieldOptions);
                }
            }
            
            // Delete fields that weren't processed (removed from form)
            foreach ($existingFieldIds as $existingFieldId) {
                if (!in_array($existingFieldId, $processedFieldIds)) {
                    $dynamicFields->deleteCategoryField($existingFieldId);
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Kategori dinamik alanları başarıyla güncellendi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Dinamik alanlar güncellenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'add_field':
        // Add dynamic field (system-wide fields, category_id = 0)
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        // Check permissions - allow admin and user roles
        if (!$auth->hasRole(['admin', 'user'])) {
            jsonResponse(['success' => false, 'message' => 'Bu işlem için yetkiniz yok'], 403);
        }
        
        // Get form data
        $fieldName = post('field_name');
        $fieldType = post('field_type');
        $fieldOptions = post('field_options');
        $isRequired = post('is_required') ? 1 : 0;
        $isActive = post('is_active') ? 1 : 0;
        
        // Validate
        if (empty($fieldName) || empty($fieldType)) {
            jsonResponse(['success' => false, 'message' => 'Alan adı ve türü zorunludur'], 400);
        }
        
        // Check field count (system-wide fields only, category_id = 0 or NULL)
        $db->query("SELECT COUNT(*) as count FROM category_fields WHERE category_id = 0 OR category_id IS NULL");
        $fieldCount = $db->single()['count'];
        
        if ($fieldCount >= 20) {
            jsonResponse(['success' => false, 'message' => 'Maksimum 20 dinamik alan ekleyebilirsiniz'], 400);
        }
        
        // Generate field key
        $fieldKey = slugify($fieldName);
        
        // Check and add missing columns if needed
        try {
            // Check if field_key column exists
            $db->query("SHOW COLUMNS FROM category_fields LIKE 'field_key'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE category_fields ADD COLUMN field_key VARCHAR(100) NULL AFTER id");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        try {
            // Check if is_required column exists
            $db->query("SHOW COLUMNS FROM category_fields LIKE 'is_required'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE category_fields ADD COLUMN is_required TINYINT(1) NOT NULL DEFAULT 0 AFTER field_options");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        try {
            // Check if is_active column exists
            $db->query("SHOW COLUMNS FROM category_fields LIKE 'is_active'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE category_fields ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_required");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        try {
            // Check if field_order column exists
            $db->query("SHOW COLUMNS FROM category_fields LIKE 'field_order'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE category_fields ADD COLUMN field_order INT NOT NULL DEFAULT 0 AFTER is_active");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        // Get next order (only if field_order column exists)
        try {
            $db->query("SELECT MAX(field_order) as max_order FROM category_fields WHERE category_id = 0 OR category_id IS NULL");
            $maxOrder = $db->single()['max_order'] ?? 0;
            $fieldOrder = $maxOrder + 1;
        } catch (PDOException $e) {
            // If query fails, use count as order
            $db->query("SELECT COUNT(*) as count FROM category_fields WHERE category_id = 0 OR category_id IS NULL");
            $count = $db->single()['count'] ?? 0;
            $fieldOrder = $count + 1;
        }
        
        try {
            // Check if category_id column allows NULL
            $db->query("SHOW COLUMNS FROM category_fields WHERE Field = 'category_id'");
            $categoryIdColumn = $db->single();
            $categoryIdValue = 0;
            
            if ($categoryIdColumn && $categoryIdColumn['Null'] === 'YES') {
                // Column allows NULL, use NULL for system-wide fields
                $categoryIdValue = null;
            } else {
                // Column doesn't allow NULL, use 0
                $categoryIdValue = 0;
            }
            
            // Insert field (system-wide field, category_id = NULL or 0)
            if ($categoryIdValue === null) {
                $db->query("INSERT INTO category_fields (category_id, field_name, field_key, field_type, field_options, is_required, is_active, field_order) 
                           VALUES (NULL, :field_name, :field_key, :field_type, :field_options, :is_required, :is_active, :field_order)");
            } else {
                $db->query("INSERT INTO category_fields (category_id, field_name, field_key, field_type, field_options, is_required, is_active, field_order) 
                           VALUES (0, :field_name, :field_key, :field_type, :field_options, :is_required, :is_active, :field_order)");
            }
            $db->bind(':field_name', $fieldName);
            $db->bind(':field_key', $fieldKey);
            $db->bind(':field_type', $fieldType);
            $db->bind(':field_options', $fieldOptions ?: null);
            $db->bind(':is_required', $isRequired);
            $db->bind(':is_active', $isActive);
            $db->bind(':field_order', $fieldOrder);
            $db->execute();
            
            jsonResponse(['success' => true, 'message' => 'Alan başarıyla eklendi']);
            
        } catch (PDOException $e) {
            // Log error for debugging
            error_log('Category field add error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Alan eklenirken bir hata oluştu: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            // Log error for debugging
            error_log('Category field add error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Alan eklenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'update_field':
        // Update dynamic field
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        // Check permissions - allow admin and user roles
        if (!$auth->hasRole(['admin', 'user'])) {
            jsonResponse(['success' => false, 'message' => 'Bu işlem için yetkiniz yok'], 403);
        }
        
        // Get form data
        $fieldId = post('field_id');
        $fieldName = post('field_name');
        $fieldType = post('field_type');
        $fieldOptions = post('field_options');
        $isRequired = post('is_required') ? 1 : 0;
        $isActive = post('is_active') ? 1 : 0;
        
        // Validate
        if (empty($fieldId) || empty($fieldName) || empty($fieldType)) {
            jsonResponse(['success' => false, 'message' => 'Eksik bilgi'], 400);
        }
        
        try {
            // Update field
            $db->query("UPDATE category_fields SET 
                       field_name = :field_name,
                       field_type = :field_type,
                       field_options = :field_options,
                       is_required = :is_required,
                       is_active = :is_active
                       WHERE id = :id");
            $db->bind(':field_name', $fieldName);
            $db->bind(':field_type', $fieldType);
            $db->bind(':field_options', $fieldOptions ?: null);
            $db->bind(':is_required', $isRequired);
            $db->bind(':is_active', $isActive);
            $db->bind(':id', $fieldId);
            $db->execute();
            
            jsonResponse(['success' => true, 'message' => 'Alan başarıyla güncellendi']);
            
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Alan güncellenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'delete_field':
        // Delete dynamic field
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        // Check permissions - allow admin and user roles
        if (!$auth->hasRole(['admin', 'user'])) {
            jsonResponse(['success' => false, 'message' => 'Bu işlem için yetkiniz yok'], 403);
        }
        
        // Get field ID
        $fieldId = post('field_id');
        
        if (empty($fieldId)) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz alan ID'], 400);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Get category_id before deleting
            $db->query("SELECT category_id FROM category_fields WHERE id = :id");
            $db->bind(':id', $fieldId);
            $field = $db->single();
            
            if (!$field) {
                jsonResponse(['success' => false, 'message' => 'Alan bulunamadı'], 404);
            }
            
            $categoryId = $field['category_id'];
            
            // Delete field
            $db->query("DELETE FROM category_fields WHERE id = :id");
            $db->bind(':id', $fieldId);
            $db->execute();
            
            // Reorder remaining fields (same category)
            $db->query("SET @order = 0");
            $db->execute();
            
            if ($categoryId && $categoryId > 0) {
                $db->query("UPDATE category_fields SET field_order = (@order := @order + 1) WHERE category_id = :category_id ORDER BY field_order");
                $db->bind(':category_id', $categoryId);
            } else {
                $db->query("UPDATE category_fields SET field_order = (@order := @order + 1) WHERE category_id = 0 OR category_id IS NULL ORDER BY field_order");
            }
            $db->execute();
            
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Alan başarıyla silindi']);
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            jsonResponse(['success' => false, 'message' => 'Alan silinirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'reorder_field':
        // Reorder dynamic field
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        // Check permissions - allow admin and user roles
        if (!$auth->hasRole(['admin', 'user'])) {
            jsonResponse(['success' => false, 'message' => 'Bu işlem için yetkiniz yok'], 403);
        }
        
        // Get data
        $fieldId = post('field_id');
        $direction = post('direction');
        
        if (empty($fieldId) || empty($direction)) {
            jsonResponse(['success' => false, 'message' => 'Eksik bilgi'], 400);
        }
        
        // Get current field
        $db->query("SELECT * FROM category_fields WHERE id = :id");
        $db->bind(':id', $fieldId);
        $currentField = $db->single();
        
        if (!$currentField) {
            jsonResponse(['success' => false, 'message' => 'Alan bulunamadı'], 404);
        }
        
        $categoryId = $currentField['category_id'];
        $currentOrder = $currentField['field_order'] ?? 0;
        $newOrder = $direction === 'up' ? $currentOrder - 1 : $currentOrder + 1;
        
        // Get swap field (same category)
        if ($categoryId && $categoryId > 0) {
            $db->query("SELECT * FROM category_fields WHERE category_id = :category_id AND field_order = :order");
            $db->bind(':category_id', $categoryId);
            $db->bind(':order', $newOrder);
        } else {
            $db->query("SELECT * FROM category_fields WHERE (category_id = 0 OR category_id IS NULL) AND field_order = :order");
            $db->bind(':order', $newOrder);
        }
        $swapField = $db->single();
        
        if ($swapField) {
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Update current field
                $db->query("UPDATE category_fields SET field_order = :order WHERE id = :id");
                $db->bind(':order', $newOrder);
                $db->bind(':id', $currentField['id']);
                $db->execute();
                
                // Update swap field
                $db->query("UPDATE category_fields SET field_order = :order WHERE id = :id");
                $db->bind(':order', $currentOrder);
                $db->bind(':id', $swapField['id']);
                $db->execute();
                
                $db->endTransaction();
                
                jsonResponse(['success' => true, 'message' => 'Alan sırası başarıyla değiştirildi']);
                
            } catch (Exception $e) {
                $db->cancelTransaction();
                jsonResponse(['success' => false, 'message' => 'Alan sırası değiştirilirken bir hata oluştu: ' . $e->getMessage()], 500);
            }
        } else {
            jsonResponse(['success' => false, 'message' => 'Alan sırası değiştirilemedi'], 400);
        }
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz eylem'], 400);
        break;
}