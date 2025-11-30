<?php
/**
 * Megabre StokMaster Pro
 * Products API
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
        // Get filter parameters
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $stockStatus = isset($_GET['stock_status']) ? $_GET['stock_status'] : 'all';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
        
        // Build query
        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  JOIN categories c ON p.category_id = c.id";
        
        // Add WHERE conditions
        $where = [];
        $params = [];
        
        if ($categoryId > 0) {
            $where[] = "p.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }
        
        if (!empty($search)) {
            $where[] = "(p.name LIKE :search OR p.sku LIKE :search OR p.barcode LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        // Add ORDER BY
        $query .= " ORDER BY p.name ASC";
        
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
        
        // Get products
        $products = $db->resultSet();
        
        // Get stock levels for products
        if (!empty($products)) {
            $productIds = array_column($products, 'id');
            $productIdsStr = implode(',', $productIds);
            
            $db->query("SELECT product_id, SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level 
                        FROM stock_movements 
                        WHERE product_id IN ($productIdsStr) 
                        GROUP BY product_id");
            
            $stockLevels = $db->resultSet();
            $stockMap = [];
            
            foreach ($stockLevels as $stock) {
                $stockMap[$stock['product_id']] = $stock['stock_level'];
            }
            
            // Add stock level to products
            foreach ($products as &$product) {
                $stockLevel = isset($stockMap[$product['id']]) ? $stockMap[$product['id']] : 0;
                $product['stock_level'] = $stockLevel;
                
                // Add stock status
                if ($stockLevel <= 0) {
                    $product['stock_status'] = 'out_of_stock';
                } elseif ($stockLevel <= $product['min_stock_level']) {
                    $product['stock_status'] = 'low_stock';
                } else {
                    $product['stock_status'] = 'in_stock';
                }
            }
            
            // Filter by stock status if specified
            if ($stockStatus !== 'all') {
                $products = array_filter($products, function($product) use ($stockStatus) {
                    return $product['stock_status'] === $stockStatus;
                });
                
                // Re-index array
                $products = array_values($products);
            }
        }
        
        jsonResponse(['success' => true, 'products' => $products]);
        break;
        
    case 'get':
        // Get single product
        $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($productId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz ürün ID\'si'], 400);
        }
        
        $db->query("SELECT p.*, c.name as category_name 
                    FROM products p 
                    JOIN categories c ON p.category_id = c.id 
                    WHERE p.id = :id");
        $db->bind(':id', $productId);
        $product = $db->single();
        
        if (!$product) {
            jsonResponse(['success' => false, 'message' => 'Ürün bulunamadı'], 404);
        }
        
        // Get product dynamic fields
        $fields = $dynamicFields->getProductFields($productId);
        
        // Get current stock level
        $db->query("SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level 
                   FROM stock_movements 
                   WHERE product_id = :product_id");
        $db->bind(':product_id', $productId);
        $stockResult = $db->single();
        
        $product['stock_level'] = $stockResult ? $stockResult['stock_level'] : 0;
        $product['fields'] = $fields;
        
        jsonResponse(['success' => true, 'product' => $product]);
        break;
        
    case 'create':
        // Create new product
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
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
        
        // Validate data
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
        
        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
        }
        
        // Format price
        $price = floatval(str_replace(',', '.', $price));
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert product
            $db->query("INSERT INTO products (category_id, name, price, sku, barcode, description, min_stock_level) 
                       VALUES (:category_id, :name, :price, :sku, :barcode, :description, :min_stock_level)");
            $db->bind(':category_id', $categoryId);
            $db->bind(':name', $name);
            $db->bind(':price', $price);
            $db->bind(':sku', $sku);
            $db->bind(':barcode', $barcode);
            $db->bind(':description', $description);
            $db->bind(':min_stock_level', $minStockLevel);
            $db->execute();
            
            $productId = $db->lastInsertId();
            
            // Insert category fields
            if ($categoryFields && is_array($categoryFields)) {
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
            
            // Add initial stock if specified
            if ($initialStock && $initialStockQuantity > 0) {
                $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes) 
                           VALUES (:product_id, 'in', :quantity, :unit, CURDATE(), :notes)");
                $db->bind(':product_id', $productId);
                $db->bind(':quantity', $initialStockQuantity);
                $db->bind(':unit', $initialStockUnit);
                $db->bind(':notes', $initialStockNote ?: 'İlk stok girişi');
                $db->execute();
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Ürün başarıyla eklendi', 'product_id' => $productId]);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Ürün eklenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'update':
        // Update product
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($productId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz ürün ID\'si'], 400);
        }
        
        // Check if product exists
        $db->query("SELECT * FROM products WHERE id = :id");
        $db->bind(':id', $productId);
        $product = $db->single();
        
        if (!$product) {
            jsonResponse(['success' => false, 'message' => 'Ürün bulunamadı'], 404);
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
        $productFields = post('product_fields');
        
        // Validate data
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
        
        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
        }
        
        // Format price
        $price = floatval(str_replace(',', '.', $price));
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Update product
            $db->query("UPDATE products SET 
                        category_id = :category_id, 
                        name = :name, 
                        price = :price, 
                        sku = :sku, 
                        barcode = :barcode, 
                        description = :description, 
                        min_stock_level = :min_stock_level, 
                        updated_at = NOW() 
                        WHERE id = :id");
            $db->bind(':category_id', $categoryId);
            $db->bind(':name', $name);
            $db->bind(':price', $price);
            $db->bind(':sku', $sku);
            $db->bind(':barcode', $barcode);
            $db->bind(':description', $description);
            $db->bind(':min_stock_level', $minStockLevel);
            $db->bind(':id', $productId);
            $db->execute();
            
            // Delete existing fields
            $dynamicFields->deleteProductFields($productId);
            
            // Insert category fields
            if ($categoryFields && is_array($categoryFields)) {
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
            
            // Insert product fields
            if ($productFields && is_array($productFields)) {
                foreach ($productFields as $fieldId => $field) {
                    $fieldName = $field['name'] ?? '';
                    $fieldType = $field['type'] ?? '';
                    $fieldValue = $field['value'] ?? '';
                    
                    if (!empty($fieldName) && !empty($fieldType)) {
                        $dynamicFields->createProductField($productId, $fieldName, $fieldType, $fieldValue);
                    }
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Ürün başarıyla güncellendi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Ürün güncellenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'delete':
        // Delete product
        if (!isPost() && !isset($_POST['_method']) && $_POST['_method'] !== 'DELETE') {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($productId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz ürün ID\'si'], 400);
        }
        
        // Check if product exists
        $db->query("SELECT * FROM products WHERE id = :id");
        $db->bind(':id', $productId);
        $product = $db->single();
        
        if (!$product) {
            jsonResponse(['success' => false, 'message' => 'Ürün bulunamadı'], 404);
        }
        
        // Check if product has stock movements
        $db->query("SELECT COUNT(*) as count FROM stock_movements WHERE product_id = :product_id");
        $db->bind(':product_id', $productId);
        $stockMovementsCount = $db->single()['count'];
        
        // Check if product is used in orders
        $db->query("SELECT COUNT(*) as count FROM order_items WHERE product_id = :product_id");
        $db->bind(':product_id', $productId);
        $orderItemsCount = $db->single()['count'];
        
        // Check force delete option
        $forceDelete = isset($_POST['force_delete']) && $_POST['force_delete'] == 1;
        
        // Check if product can be deleted
        if (!$forceDelete && ($stockMovementsCount > 0 || $orderItemsCount > 0)) {
            jsonResponse([
                'success' => false, 
                'message' => 'Bu ürün stok hareketleri veya siparişlerde kullanıldığı için silinemez.',
                'stock_movements_count' => $stockMovementsCount,
                'order_items_count' => $orderItemsCount
            ], 400);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Delete product field values if table exists
            try {
                $db->query("DELETE FROM product_field_values WHERE product_id = :product_id");
                $db->bind(':product_id', $productId);
                $db->execute();
            } catch (PDOException $e) {
                // Table doesn't exist or column doesn't exist, skip
            }
            
            // If force delete, delete related records
            if ($forceDelete) {
                // Delete stock movements
                $db->query("DELETE FROM stock_movements WHERE product_id = :product_id");
                $db->bind(':product_id', $productId);
                $db->execute();
                
                // Delete order items if table exists
                try {
                    $db->query("DELETE FROM order_items WHERE product_id = :product_id");
                    $db->bind(':product_id', $productId);
                    $db->execute();
                } catch (PDOException $e) {
                    // Table doesn't exist or column doesn't exist, skip
                }
            }
            
            // Delete product
            $db->query("DELETE FROM products WHERE id = :id");
            $db->bind(':id', $productId);
            $db->execute();
            
            // Delete product image if exists
            if (!empty($product['image']) && file_exists(UPLOADS_PATH . 'products/' . $product['image'])) {
                unlink(UPLOADS_PATH . 'products/' . $product['image']);
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Ürün başarıyla silindi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Ürün silinirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'search':
        // Search products
        $term = isset($_GET['term']) ? $_GET['term'] : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        if (empty($term)) {
            jsonResponse(['success' => false, 'message' => 'Arama terimi gereklidir'], 400);
        }
        
        // Search products by name, sku, barcode
        $db->query("SELECT p.id, p.name, p.sku, p.barcode, p.price, p.image, c.name as category_name 
                   FROM products p 
                   JOIN categories c ON p.category_id = c.id 
                   WHERE p.name LIKE :term OR p.sku LIKE :term OR p.barcode LIKE :term 
                   ORDER BY p.name ASC 
                   LIMIT :limit");
        $db->bind(':term', "%$term%");
        $db->bind(':limit', $limit);
        $products = $db->resultSet();
        
        // Get stock levels for products
        if (!empty($products)) {
            $productIds = array_column($products, 'id');
            $productIdsStr = implode(',', $productIds);
            
            $db->query("SELECT product_id, SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level 
                        FROM stock_movements 
                        WHERE product_id IN ($productIdsStr) 
                        GROUP BY product_id");
            
            $stockLevels = $db->resultSet();
            $stockMap = [];
            
            foreach ($stockLevels as $stock) {
                $stockMap[$stock['product_id']] = $stock['stock_level'];
            }
            
            // Add stock level to products
            foreach ($products as &$product) {
                $product['stock_level'] = isset($stockMap[$product['id']]) ? $stockMap[$product['id']] : 0;
            }
        }
        
        jsonResponse(['success' => true, 'products' => $products]);
        break;
        
    case 'get_stock_level':
        // Get product stock level
        $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
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
        
        // Get stock level
        $db->query("SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level 
                   FROM stock_movements 
                   WHERE product_id = :product_id");
        $db->bind(':product_id', $productId);
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
        // Bulk delete products
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $productIds = isset($_POST['ids']) ? $_POST['ids'] : [];
        $forceDelete = isset($_POST['force_delete']) && $_POST['force_delete'] == '1';
        
        if (empty($productIds) || !is_array($productIds)) {
            jsonResponse(['success' => false, 'message' => 'Geçerli ürün ID\'leri gönderilmelidir'], 400);
        }
        
        // Validate and sanitize IDs
        $productIds = array_map('intval', $productIds);
        $productIds = array_filter($productIds, function($id) { return $id > 0; });
        
        if (empty($productIds)) {
            jsonResponse(['success' => false, 'message' => 'Geçerli ürün ID\'si bulunamadı'], 400);
        }
        
        $deletedCount = 0;
        $failedCount = 0;
        $errors = [];
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            foreach ($productIds as $productId) {
                try {
                    // Check if product exists
                    $db->query("SELECT * FROM products WHERE id = :id");
                    $db->bind(':id', $productId);
                    $product = $db->single();
                    
                    if (!$product) {
                        $failedCount++;
                        $errors[] = "Ürün ID $productId bulunamadı";
                        continue;
                    }
                    
                    // Delete product field values if table exists
                    try {
                        $db->query("DELETE FROM product_field_values WHERE product_id = :product_id");
                        $db->bind(':product_id', $productId);
                        $db->execute();
                    } catch (PDOException $e) {
                        // Table doesn't exist, skip
                    }
                    
                    // Delete order items if table exists
                    try {
                        $db->query("DELETE FROM order_items WHERE product_id = :product_id");
                        $db->bind(':product_id', $productId);
                        $db->execute();
                    } catch (PDOException $e) {
                        // Table doesn't exist, skip
                    }
                    
                    // Delete stock movements (always delete for bulk operation)
                    $db->query("DELETE FROM stock_movements WHERE product_id = :product_id");
                    $db->bind(':product_id', $productId);
                    $db->execute();
                    
                    // Delete product image if exists
                    if (!empty($product['image']) && file_exists(UPLOADS_PATH . 'products/' . $product['image'])) {
                        @unlink(UPLOADS_PATH . 'products/' . $product['image']);
                    }
                    
                    // Delete product
                    $db->query("DELETE FROM products WHERE id = :id");
                    $db->bind(':id', $productId);
                    $db->execute();
                    
                    $deletedCount++;
                    
                } catch (PDOException $e) {
                    $failedCount++;
                    $errors[] = "Ürün ID $productId silinirken hata: " . $e->getMessage();
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            $message = "$deletedCount ürün başarıyla silindi.";
            if ($failedCount > 0) {
                $message .= " $failedCount ürün silinirken hata oluştu.";
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
        
    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz işlem'], 400);
        break;
}