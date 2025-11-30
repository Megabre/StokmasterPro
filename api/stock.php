<?php
/**
 * Megabre StokMaster Pro
 * Stock API
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Include configuration
require_once '../config/config.php';

// Include core files
require_once CORE_PATH . 'Database.php';
require_once CORE_PATH . 'Session.php';
require_once CORE_PATH . 'Authentication.php';
require_once CORE_PATH . 'Language.php';
require_once CORE_PATH . 'helpers.php';

// Initialize authentication
$auth = new Authentication();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Yetkisiz erişim'], 401);
}

// Initialize database connection
$db = Database::getInstance();

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Process action
switch ($action) {
    case 'get_all':
        // Get filter parameters
        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
        
        // Build query
        $query = "SELECT sm.*, p.name as product_name, p.sku, p.barcode,
                  DATE_FORMAT(sm.date, '%d.%m.%Y') as formatted_date
                  FROM stock_movements sm 
                  JOIN products p ON sm.product_id = p.id";
        
        // Add WHERE conditions
        $where = [];
        $params = [];
        
        if ($productId > 0) {
            $where[] = "sm.product_id = :product_id";
            $params[':product_id'] = $productId;
        }
        
        if (!empty($type)) {
            $where[] = "sm.type = :type";
            $params[':type'] = $type;
        }
        
        if (!empty($dateFrom)) {
            $where[] = "sm.date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $where[] = "sm.date <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        // Add ORDER BY
        $query .= " ORDER BY sm.date DESC, sm.id DESC";
        
        // Add LIMIT if specified
        if ($limit > 0) {
            $query .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        
        // Execute query
        $db->query($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $db->bind($key, $value);
        }
        
        // Get movements
        $movements = $db->resultSet();
        
        jsonResponse(['success' => true, 'movements' => $movements]);
        break;
        
    case 'get':
        // Get single stock movement
        $movementId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($movementId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz hareket ID\'si'], 400);
        }
        
        $db->query("SELECT sm.*, p.name as product_name 
                    FROM stock_movements sm 
                    JOIN products p ON sm.product_id = p.id 
                    WHERE sm.id = :id");
        $db->bind(':id', $movementId);
        $movement = $db->single();
        
        if (!$movement) {
            jsonResponse(['success' => false, 'message' => 'Stok hareketi bulunamadı'], 404);
        }
        
        // Get field values
        $db->query("SELECT * FROM stock_field_values WHERE movement_id = :movement_id");
        $db->bind(':movement_id', $movementId);
        $fieldValues = $db->resultSet();
        
        $movement['field_values'] = $fieldValues;
        
        jsonResponse(['success' => true, 'movement' => $movement]);
        break;
        
    case 'create':
        // Create new stock movement
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        // Get form data
        $productId = post('product_id');
        $type = post('type');
        $date = post('date');
        $quantity = post('quantity');
        $unit = post('unit');
        $notes = post('notes');
        $dynamicFields = post('dynamic_fields');
        
        // Validate data
        $errors = [];
        
        if (empty($productId) || $productId <= 0) {
            $errors[] = 'Ürün seçimi gereklidir.';
        }
        
        if (empty($type) || !in_array($type, ['in', 'out', 'adjustment'])) {
            $errors[] = 'Geçersiz hareket tipi.';
        }
        
        if (empty($date)) {
            $errors[] = 'Tarih gereklidir.';
        }
        
        if (empty($quantity) || !is_numeric($quantity) || floatval($quantity) <= 0) {
            $errors[] = 'Miktar geçerli bir sayı olmalıdır.';
        }
        
        if (empty($unit)) {
            $errors[] = 'Birim seçimi gereklidir.';
        }
        
        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
        }
        
        // Check stock level for out movements
        if ($type == 'out') {
            $db->query("SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN quantity WHEN type = 'out' THEN -quantity ELSE quantity END), 0) as current_stock 
                       FROM stock_movements 
                       WHERE product_id = :product_id");
            $db->bind(':product_id', $productId);
            $stockResult = $db->single();
            $currentStock = $stockResult['current_stock'];
            
            if ($currentStock < floatval($quantity)) {
                jsonResponse(['success' => false, 'message' => 'Yetersiz stok! Mevcut stok: ' . number_format($currentStock, 2)], 400);
            }
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert stock movement
            $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes) 
                       VALUES (:product_id, :type, :quantity, :unit, :date, :notes)");
            $db->bind(':product_id', $productId);
            $db->bind(':type', $type);
            $db->bind(':quantity', $quantity);
            $db->bind(':unit', $unit);
            $db->bind(':date', $date);
            $db->bind(':notes', $notes);
            $db->execute();
            
            $movementId = $db->lastInsertId();
            
            // Insert dynamic field values
            if (!empty($dynamicFields)) {
                foreach ($dynamicFields as $fieldId => $fieldValue) {
                    if (!empty($fieldValue)) {
                        $db->query("INSERT INTO stock_field_values (movement_id, field_id, field_value) 
                                   VALUES (:movement_id, :field_id, :field_value)");
                        $db->bind(':movement_id', $movementId);
                        $db->bind(':field_id', $fieldId);
                        $db->bind(':field_value', $fieldValue);
                        $db->execute();
                    }
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Stok hareketi başarıyla eklendi', 'movement_id' => $movementId]);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Stok hareketi eklenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'update':
        // Update stock movement
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $movementId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($movementId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz hareket ID\'si'], 400);
        }
        
        // Check if movement exists
        $db->query("SELECT * FROM stock_movements WHERE id = :id");
        $db->bind(':id', $movementId);
        $movement = $db->single();
        
        if (!$movement) {
            jsonResponse(['success' => false, 'message' => 'Stok hareketi bulunamadı'], 404);
        }
        
        // Get form data
        $productId = post('product_id');
        $type = post('type');
        $date = post('date');
        $quantity = post('quantity');
        $unit = post('unit');
        $notes = post('notes');
        $dynamicFields = post('dynamic_fields');
        
        // Validate data
        $errors = [];
        
        if (empty($productId) || $productId <= 0) {
            $errors[] = 'Ürün seçimi gereklidir.';
        }
        
        if (empty($type) || !in_array($type, ['in', 'out', 'adjustment'])) {
            $errors[] = 'Geçersiz hareket tipi.';
        }
        
        if (empty($date)) {
            $errors[] = 'Tarih gereklidir.';
        }
        
        if (empty($quantity) || !is_numeric($quantity) || floatval($quantity) <= 0) {
            $errors[] = 'Miktar geçerli bir sayı olmalıdır.';
        }
        
        if (empty($unit)) {
            $errors[] = 'Birim seçimi gereklidir.';
        }
        
        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
        }
        
        // Check stock level for out movements (excluding current movement)
        if ($type == 'out') {
            $db->query("SELECT COALESCE(SUM(CASE 
                            WHEN type = 'in' THEN quantity 
                            WHEN type = 'out' THEN -quantity 
                            ELSE quantity 
                        END), 0) as current_stock 
                       FROM stock_movements 
                       WHERE product_id = :product_id AND id != :movement_id");
            $db->bind(':product_id', $productId);
            $db->bind(':movement_id', $movementId);
            $stockResult = $db->single();
            $currentStock = $stockResult['current_stock'];
            
            if ($currentStock < floatval($quantity)) {
                jsonResponse(['success' => false, 'message' => 'Yetersiz stok! Mevcut stok: ' . number_format($currentStock, 2)], 400);
            }
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Update stock movement
            $db->query("UPDATE stock_movements SET 
                        product_id = :product_id, 
                        type = :type, 
                        quantity = :quantity, 
                        unit = :unit, 
                        date = :date, 
                        notes = :notes,
                        updated_at = NOW()
                        WHERE id = :id");
            $db->bind(':product_id', $productId);
            $db->bind(':type', $type);
            $db->bind(':quantity', $quantity);
            $db->bind(':unit', $unit);
            $db->bind(':date', $date);
            $db->bind(':notes', $notes);
            $db->bind(':id', $movementId);
            $db->execute();
            
            // Delete existing field values
            $db->query("DELETE FROM stock_field_values WHERE movement_id = :movement_id");
            $db->bind(':movement_id', $movementId);
            $db->execute();
            
            // Insert new field values
            if (!empty($dynamicFields)) {
                foreach ($dynamicFields as $fieldId => $fieldValue) {
                    if (!empty($fieldValue)) {
                        $db->query("INSERT INTO stock_field_values (movement_id, field_id, field_value) 
                                   VALUES (:movement_id, :field_id, :field_value)");
                        $db->bind(':movement_id', $movementId);
                        $db->bind(':field_id', $fieldId);
                        $db->bind(':field_value', $fieldValue);
                        $db->execute();
                    }
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Stok hareketi başarıyla güncellendi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Stok hareketi güncellenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'delete':
        // Delete stock movement
        $movementId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($movementId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz hareket ID\'si'], 400);
        }
        
        // Check if movement exists
        $db->query("SELECT * FROM stock_movements WHERE id = :id");
        $db->bind(':id', $movementId);
        $movement = $db->single();
        
        if (!$movement) {
            jsonResponse(['success' => false, 'message' => 'Stok hareketi bulunamadı'], 404);
        }
        
        // Calculate stock after deletion
        $db->query("SELECT COALESCE(SUM(CASE 
                        WHEN type = 'in' THEN quantity 
                        WHEN type = 'out' THEN -quantity 
                        ELSE quantity 
                    END), 0) as current_stock 
                   FROM stock_movements 
                   WHERE product_id = :product_id");
        $db->bind(':product_id', $movement['product_id']);
        $stockResult = $db->single();
        $currentStock = $stockResult['current_stock'];
        
        // Calculate what stock would be after deletion
        $stockAfterDeletion = $currentStock;
        if ($movement['type'] == 'in') {
            $stockAfterDeletion -= $movement['quantity'];
        } elseif ($movement['type'] == 'out') {
            $stockAfterDeletion += $movement['quantity'];
        } else { // adjustment
            $stockAfterDeletion -= $movement['quantity'];
        }
        
        // Check if deletion would result in negative stock
        if ($stockAfterDeletion < 0) {
            jsonResponse(['success' => false, 'message' => 'Bu hareket silinemez! Silme işlemi negatif stoğa neden olacaktır.'], 400);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Delete field values (if table exists)
            try {
                $db->query("DELETE FROM stock_field_values WHERE movement_id = :movement_id");
                $db->bind(':movement_id', $movementId);
                $db->execute();
            } catch (PDOException $e) {
                // Table doesn't exist or column doesn't exist, skip
            }
            
            // Delete movement
            $db->query("DELETE FROM stock_movements WHERE id = :id");
            $db->bind(':id', $movementId);
            $db->execute();
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Stok hareketi başarıyla silindi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Stok hareketi silinirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'get_stock_level':
        // Get product stock level
        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        $excludeMovementId = isset($_GET['exclude_movement_id']) ? (int)$_GET['exclude_movement_id'] : 0;
        
        if ($productId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz ürün ID\'si'], 400);
        }
        
        // Get product
        $db->query("SELECT * FROM products WHERE id = :id");
        $db->bind(':id', $productId);
        $product = $db->single();
        
        if (!$product) {
            jsonResponse(['success' => false, 'message' => 'Ürün bulunamadı'], 404);
        }
        
        // Build query
        $query = "SELECT COALESCE(SUM(CASE 
                    WHEN type = 'in' THEN quantity 
                    WHEN type = 'out' THEN -quantity 
                    ELSE quantity 
                  END), 0) as stock_level 
                  FROM stock_movements 
                  WHERE product_id = :product_id";
        
        $params = [':product_id' => $productId];
        
        if ($excludeMovementId > 0) {
            $query .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeMovementId;
        }
        
        $db->query($query);
        foreach ($params as $key => $value) {
            $db->bind($key, $value);
        }
        
        $stockResult = $db->single();
        $stockLevel = $stockResult ? $stockResult['stock_level'] : 0;
        
        // Determine stock status
        $stockStatus = 'out_of_stock';
        if ($stockLevel > 0) {
            if ($stockLevel <= $product['min_stock_level']) {
                $stockStatus = 'low_stock';
            } else {
                $stockStatus = 'in_stock';
            }
        }
        
        jsonResponse([
            'success' => true, 
            'product_id' => $productId,
            'stock_level' => $stockLevel,
            'min_stock_level' => $product['min_stock_level'],
            'stock_status' => $stockStatus
        ]);
        break;
        
    case 'bulk-delete':
        // Bulk delete stock movements
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $movementIds = isset($_POST['ids']) ? $_POST['ids'] : [];
        
        if (empty($movementIds) || !is_array($movementIds)) {
            jsonResponse(['success' => false, 'message' => 'Geçerli hareket ID\'leri gönderilmelidir'], 400);
        }
        
        // Validate and sanitize IDs
        $movementIds = array_map('intval', $movementIds);
        $movementIds = array_filter($movementIds, function($id) { return $id > 0; });
        
        if (empty($movementIds)) {
            jsonResponse(['success' => false, 'message' => 'Geçerli hareket ID\'si bulunamadı'], 400);
        }
        
        $deletedCount = 0;
        $failedCount = 0;
        $errors = [];
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            foreach ($movementIds as $movementId) {
                try {
                    // Check if movement exists
                    $db->query("SELECT * FROM stock_movements WHERE id = :id");
                    $db->bind(':id', $movementId);
                    $movement = $db->single();
                    
                    if (!$movement) {
                        $failedCount++;
                        $errors[] = "Hareket ID $movementId bulunamadı";
                        continue;
                    }
                    
                    // Calculate stock after deletion
                    $db->query("SELECT COALESCE(SUM(CASE 
                                WHEN type = 'in' THEN quantity 
                                WHEN type = 'out' THEN -quantity 
                                ELSE quantity 
                            END), 0) as current_stock 
                           FROM stock_movements 
                           WHERE product_id = :product_id AND id != :exclude_id");
                    $db->bind(':product_id', $movement['product_id']);
                    $db->bind(':exclude_id', $movementId);
                    $stockResult = $db->single();
                    $stockAfterDeletion = $stockResult ? $stockResult['current_stock'] : 0;
                    
                    // Check if deletion would result in negative stock
                    if ($stockAfterDeletion < 0) {
                        $failedCount++;
                        $errors[] = "Hareket ID $movementId silinirse stok negatif olacak";
                        continue;
                    }
                    
                    // Delete stock field values
                    try {
                        $db->query("DELETE FROM stock_field_values WHERE movement_id = :movement_id");
                        $db->bind(':movement_id', $movementId);
                        $db->execute();
                    } catch (PDOException $e) {
                        // Table doesn't exist, skip
                    }
                    
                    // Delete movement
                    $db->query("DELETE FROM stock_movements WHERE id = :id");
                    $db->bind(':id', $movementId);
                    $db->execute();
                    
                    $deletedCount++;
                    
                } catch (PDOException $e) {
                    $failedCount++;
                    $errors[] = "Hareket ID $movementId silinirken hata: " . $e->getMessage();
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            $message = "$deletedCount stok hareketi başarıyla silindi.";
            if ($failedCount > 0) {
                $message .= " $failedCount hareket silinirken hata oluştu.";
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
        
    case 'add_field':
        // Add dynamic field
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
        
        // Check field count (system-wide fields only, stock_id IS NULL)
        $db->query("SELECT COUNT(*) as count FROM stock_fields WHERE stock_id IS NULL");
        $fieldCount = $db->single()['count'];
        
        if ($fieldCount >= 20) {
            jsonResponse(['success' => false, 'message' => 'Maksimum 20 dinamik alan ekleyebilirsiniz'], 400);
        }
        
        // Generate field key
        $fieldKey = slugify($fieldName);
        
        // Get next order (system-wide fields only, stock_id IS NULL)
        try {
            $db->query("SELECT MAX(field_order) as max_order FROM stock_fields WHERE stock_id IS NULL");
            $maxOrder = $db->single()['max_order'] ?? 0;
            $fieldOrder = $maxOrder + 1;
        } catch (PDOException $e) {
            // If query fails, use count as order
            $db->query("SELECT COUNT(*) as count FROM stock_fields WHERE stock_id IS NULL");
            $count = $db->single()['count'] ?? 0;
            $fieldOrder = $count + 1;
        }
        
        // Check and add missing columns if needed
        try {
            // Check if field_key column exists
            $db->query("SHOW COLUMNS FROM stock_fields LIKE 'field_key'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE stock_fields ADD COLUMN field_key VARCHAR(100) NULL AFTER id");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        try {
            // Check if field_options column exists
            $db->query("SHOW COLUMNS FROM stock_fields LIKE 'field_options'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE stock_fields ADD COLUMN field_options TEXT NULL AFTER field_type");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        try {
            // Check if is_required column exists
            $db->query("SHOW COLUMNS FROM stock_fields LIKE 'is_required'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE stock_fields ADD COLUMN is_required TINYINT(1) NOT NULL DEFAULT 0 AFTER field_options");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        try {
            // Insert field (system-wide field, stock_id = NULL)
            $db->query("INSERT INTO stock_fields (stock_id, field_name, field_key, field_type, field_options, is_required, is_active, field_order) 
                       VALUES (NULL, :field_name, :field_key, :field_type, :field_options, :is_required, :is_active, :field_order)");
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
            error_log('Stock field add error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Alan eklenirken bir hata oluştu: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            // Log error for debugging
            error_log('Stock field add error: ' . $e->getMessage());
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
            $db->query("UPDATE stock_fields SET 
                       field_name = :field_name,
                       field_type = :field_type,
                       field_options = :field_options,
                       is_required = :is_required,
                       is_active = :is_active
                       WHERE id = :id");
            $db->bind(':field_name', $fieldName);
            $db->bind(':field_type', $fieldType);
            $db->bind(':field_options', $fieldOptions);
            $db->bind(':is_required', $isRequired);
            $db->bind(':is_active', $isActive);
            $db->bind(':id', $fieldId);
            $db->execute();
            
            jsonResponse(['success' => true, 'message' => 'Alan başarıyla güncellendi']);
            
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Alan güncellenirken bir hata oluştu'], 500);
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
            // Delete field values (if table exists)
            try {
                $db->query("DELETE FROM stock_field_values WHERE field_id = :field_id");
                $db->bind(':field_id', $fieldId);
                $db->execute();
            } catch (PDOException $e) {
                // Table might not exist, that's okay - continue
                error_log('stock_field_values table might not exist: ' . $e->getMessage());
            }
            
            // Delete field
            $db->query("DELETE FROM stock_fields WHERE id = :id");
            $db->bind(':id', $fieldId);
            $db->execute();
            
            // Reorder remaining fields
            try {
                $db->query("SET @order = 0");
                $db->execute();
                
                $db->query("UPDATE stock_fields SET field_order = (@order := @order + 1) ORDER BY field_order");
                $db->execute();
            } catch (PDOException $e) {
                // Reorder might fail, but field is deleted - log and continue
                error_log('Field reorder error: ' . $e->getMessage());
            }
            
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Alan başarıyla silindi']);
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            error_log('Stock field delete error: ' . $e->getMessage());
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
        $db->query("SELECT * FROM stock_fields WHERE id = :id");
        $db->bind(':id', $fieldId);
        $currentField = $db->single();
        
        if (!$currentField) {
            jsonResponse(['success' => false, 'message' => 'Alan bulunamadı'], 404);
        }
        
        $currentOrder = $currentField['field_order'];
        $newOrder = $direction === 'up' ? $currentOrder - 1 : $currentOrder + 1;
        
        // Get swap field
        $db->query("SELECT * FROM stock_fields WHERE field_order = :order");
        $db->bind(':order', $newOrder);
        $swapField = $db->single();
        
        if ($swapField) {
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Update current field
                $db->query("UPDATE stock_fields SET field_order = :order WHERE id = :id");
                $db->bind(':order', $newOrder);
                $db->bind(':id', $currentField['id']);
                $db->execute();
                
                // Update swap field
                $db->query("UPDATE stock_fields SET field_order = :order WHERE id = :id");
                $db->bind(':order', $currentOrder);
                $db->bind(':id', $swapField['id']);
                $db->execute();
                
                $db->endTransaction();
                
                jsonResponse(['success' => true, 'message' => 'Alan sırası güncellendi']);
                
            } catch (Exception $e) {
                $db->cancelTransaction();
                jsonResponse(['success' => false, 'message' => 'Sıralama güncellenirken bir hata oluştu'], 500);
            }
        } else {
            jsonResponse(['success' => false, 'message' => 'Sıralama değiştirilemedi'], 400);
        }
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz eylem'], 400);
        break;
}