<?php
/**
 * Megabre StokMaster Pro
 * Orders API
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
        $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
        
        // Build query
        $query = "SELECT o.*, c.name as customer_name, c.surname as customer_surname, c.phone as customer_phone,
                  (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
                  DATE_FORMAT(o.order_date, '%d.%m.%Y') as formatted_date
                  FROM orders o 
                  JOIN customers c ON o.customer_id = c.id";
        
        // Add WHERE conditions
        $where = [];
        $params = [];
        
        if ($customerId > 0) {
            $where[] = "o.customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        if (!empty($status)) {
            $where[] = "o.status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($dateFrom)) {
            $where[] = "o.order_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $where[] = "o.order_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        // Add ORDER BY
        $query .= " ORDER BY o.order_date DESC, o.id DESC";
        
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
        
        // Get orders
        $orders = $db->resultSet();
        
        jsonResponse(['success' => true, 'orders' => $orders]);
        break;
        
    case 'get':
        // Get single order
        $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($orderId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz sipariş ID\'si'], 400);
        }
        
        $db->query("SELECT o.*, c.name as customer_name, c.surname as customer_surname, 
                    c.phone as customer_phone, c.email as customer_email, c.company as customer_company,
                    c.address as customer_address
                    FROM orders o 
                    JOIN customers c ON o.customer_id = c.id 
                    WHERE o.id = :id");
        $db->bind(':id', $orderId);
        $order = $db->single();
        
        if (!$order) {
            jsonResponse(['success' => false, 'message' => 'Sipariş bulunamadı'], 404);
        }
        
        // Get order items
        $db->query("SELECT oi.*, p.name as product_name, p.sku as product_sku, p.barcode as product_barcode
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = :order_id");
        $db->bind(':order_id', $orderId);
        $order['items'] = $db->resultSet();
        
        // Get related transactions
        $db->query("SELECT * FROM transactions 
                    WHERE reference_type = 'order' AND reference_id = :order_id");
        $db->bind(':order_id', $orderId);
        $order['transactions'] = $db->resultSet();
        
        jsonResponse(['success' => true, 'order' => $order]);
        break;
        
    case 'create':
        // Create new order
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        // Get form data
        $customerId = post('customer_id');
        $orderDate = post('order_date');
        $orderNote = post('order_note');
        $items = post('items');
        $useBalance = post('use_balance') ? true : false;
        $addVat = post('add_vat') ? true : false;
        $vatRate = post('vat_rate') ? floatval(post('vat_rate')) : 18;
        
        // Validate data
        $errors = [];
        
        if (empty($customerId) || $customerId <= 0) {
            $errors[] = 'Müşteri seçimi gereklidir.';
        }
        
        if (empty($orderDate)) {
            $errors[] = 'Sipariş tarihi gereklidir.';
        }
        
        if (empty($items) || !is_array($items)) {
            $errors[] = 'En az bir ürün eklemelisiniz.';
        }
        
        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
        }
        
        // Validate items and calculate totals
        $validItems = [];
        $subtotal = 0;
        
        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
                continue;
            }
            
            // Get product info
            $db->query("
                SELECT p.*, 
                       COALESCE(SUM(CASE WHEN sm.type = 'in' THEN sm.quantity WHEN sm.type = 'out' THEN -sm.quantity ELSE sm.quantity END), 0) as stock_level
                FROM products p
                LEFT JOIN stock_movements sm ON p.id = sm.product_id
                WHERE p.id = :id
                GROUP BY p.id
            ");
            $db->bind(':id', $item['product_id']);
            $product = $db->single();
            
            if (!$product) {
                $errors[] = 'Ürün bulunamadı: ID ' . $item['product_id'];
                continue;
            }
            
            // Check stock
            if ($product['stock_level'] < $item['quantity']) {
                $errors[] = $product['name'] . ' için yetersiz stok!';
                continue;
            }
            
            // Calculate item total
            $unitPrice = !empty($item['unit_price']) ? floatval($item['unit_price']) : $product['price'];
            $itemTotal = $item['quantity'] * $unitPrice;
            $subtotal += $itemTotal;
            
            $validItems[] = [
                'product_id' => $product['id'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'total' => $itemTotal
            ];
        }
        
        if (empty($validItems)) {
            jsonResponse(['success' => false, 'message' => 'Geçerli ürün bulunamadı'], 400);
        }
        
        // Calculate totals
        $vatAmount = $addVat ? ($subtotal * $vatRate / 100) : 0;
        $totalAmount = $subtotal + $vatAmount;
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert order
            $db->query("INSERT INTO orders (customer_id, order_date, status, subtotal, vat_rate, vat_amount, total_amount, notes) 
                       VALUES (:customer_id, :order_date, 'pending', :subtotal, :vat_rate, :vat_amount, :total_amount, :notes)");
            $db->bind(':customer_id', $customerId);
            $db->bind(':order_date', $orderDate);
            $db->bind(':subtotal', $subtotal);
            $db->bind(':vat_rate', $addVat ? $vatRate : 0);
            $db->bind(':vat_amount', $vatAmount);
            $db->bind(':total_amount', $totalAmount);
            $db->bind(':notes', $orderNote);
            $db->execute();
            
            $orderId = $db->lastInsertId();
            
            // Insert order items and update stock
            foreach ($validItems as $item) {
                // Insert order item
                $db->query("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total) 
                           VALUES (:order_id, :product_id, :quantity, :unit_price, :total)");
                $db->bind(':order_id', $orderId);
                $db->bind(':product_id', $item['product_id']);
                $db->bind(':quantity', $item['quantity']);
                $db->bind(':unit_price', $item['unit_price']);
                $db->bind(':total', $item['total']);
                $db->execute();
                
                // Add stock out movement
                $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes) 
                           VALUES (:product_id, 'out', :quantity, 'piece', :date, :notes)");
                $db->bind(':product_id', $item['product_id']);
                $db->bind(':quantity', $item['quantity']);
                $db->bind(':date', $orderDate);
                $db->bind(':notes', 'Sipariş #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                $db->execute();
            }
            
            // Handle balance and create transaction
            if ($useBalance) {
                // Get customer balance
                $db->query("
                    SELECT COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END), 0) as balance
                    FROM transactions 
                    WHERE customer_id = :customer_id
                ");
                $db->bind(':customer_id', $customerId);
                $balanceResult = $db->single();
                $customerBalance = $balanceResult['balance'];
                
                $balanceUsed = min($customerBalance, $totalAmount);
                $remainingAmount = $totalAmount - $balanceUsed;
                
                if ($balanceUsed > 0) {
                    // Payment from balance
                    $db->query("INSERT INTO transactions (customer_id, type, amount, date, reference_type, reference_id, notes) 
                               VALUES (:customer_id, 'payment', :amount, :date, 'order', :order_id, :notes)");
                    $db->bind(':customer_id', $customerId);
                    $db->bind(':amount', $balanceUsed);
                    $db->bind(':date', $orderDate);
                    $db->bind(':order_id', $orderId);
                    $db->bind(':notes', 'Sipariş ödemesi - Bakiyeden');
                    $db->execute();
                }
                
                if ($remainingAmount > 0) {
                    // Debt for remaining amount
                    $db->query("INSERT INTO transactions (customer_id, type, amount, date, reference_type, reference_id, notes) 
                               VALUES (:customer_id, 'debt', :amount, :date, 'order', :order_id, :notes)");
                    $db->bind(':customer_id', $customerId);
                    $db->bind(':amount', $remainingAmount);
                    $db->bind(':date', $orderDate);
                    $db->bind(':order_id', $orderId);
                    $db->bind(':notes', 'Sipariş borcu: #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                    $db->execute();
                }
            } else {
                // Full debt
                $db->query("INSERT INTO transactions (customer_id, type, amount, date, reference_type, reference_id, notes) 
                           VALUES (:customer_id, 'debt', :amount, :date, 'order', :order_id, :notes)");
                $db->bind(':customer_id', $customerId);
                $db->bind(':amount', $totalAmount);
                $db->bind(':date', $orderDate);
                $db->bind(':order_id', $orderId);
                $db->bind(':notes', 'Sipariş borcu: #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                $db->execute();
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Sipariş başarıyla oluşturuldu', 'order_id' => $orderId]);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Sipariş oluşturulurken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'update_status':
        // Validate request
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        // Get parameters
        $orderId = post('order_id');
        $status = post('status');
        
        // Validate parameters
        if (empty($orderId) || empty($status)) {
            jsonResponse(['success' => false, 'message' => 'Eksik bilgi'], 400);
        }
        
        // Validate status
        $validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz sipariş durumu'], 400);
        }
        
        // Get order
        $db->query("SELECT * FROM orders WHERE id = :id");
        $db->bind(':id', $orderId);
        $order = $db->single();
        
        if (!$order) {
            jsonResponse(['success' => false, 'message' => 'Sipariş bulunamadı'], 404);
        }
        
        // Check if status can be changed
        $currentStatus = $order['status'];
        $canChange = false;
        
        switch ($currentStatus) {
            case 'pending':
                $canChange = in_array($status, ['processing', 'cancelled']);
                break;
            case 'processing':
                $canChange = in_array($status, ['completed', 'cancelled']);
                break;
            case 'completed':
            case 'cancelled':
                $canChange = false;
                break;
        }
        
        if (!$canChange || $currentStatus == $status) {
            jsonResponse(['success' => false, 'message' => 'Bu durum değişikliği yapılamaz'], 400);
        }
        
        try {
            // Get inventory settings
            $db->query("SELECT setting_value FROM settings WHERE setting_key = :key");
            $db->bind(':key', 'inventory_settings');
            $settingsResult = $db->single();
            
            $inventorySettings = [];
            $restoreStockOnCancel = true; // Default: restore stock
            
            if ($settingsResult) {
                $inventorySettings = json_decode($settingsResult['setting_value'], true);
                $restoreStockOnCancel = isset($inventorySettings['order_cancel_stock']) && $inventorySettings['order_cancel_stock'] == 1;
            }
            
            // Begin transaction
            $db->beginTransaction();
            
            // Update order status
            $db->query("UPDATE orders SET 
                        status = :status,
                        updated_at = NOW()
                        WHERE id = :id");
            $db->bind(':status', $status);
            $db->bind(':id', $orderId);
            $db->execute();
            
            // If cancelling order, restore stock (only if setting is enabled)
            if ($status == 'cancelled' && $restoreStockOnCancel) {
                // Get order items
                $db->query("SELECT * FROM order_items WHERE order_id = :order_id");
                $db->bind(':order_id', $orderId);
                $orderItems = $db->resultSet();
                
                // Create stock in movements for each item
                foreach ($orderItems as $item) {
                    // Get unit from the original stock out movement for this order
                    $db->query("SELECT unit FROM stock_movements 
                               WHERE product_id = :product_id 
                               AND type = 'out' 
                               AND notes = :notes 
                               ORDER BY id DESC 
                               LIMIT 1");
                    $db->bind(':product_id', $item['product_id']);
                    $db->bind(':notes', 'Sipariş #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                    $stockMovement = $db->single();
                    
                    // Use unit from stock movement if available, otherwise default to 'piece'
                    $unit = !empty($stockMovement['unit']) ? $stockMovement['unit'] : 'piece';
                    
                    // Check if stock movement already exists for this cancellation
                    $db->query("SELECT COUNT(*) as count FROM stock_movements 
                               WHERE product_id = :product_id 
                               AND type = 'in' 
                               AND notes = :notes");
                    $db->bind(':product_id', $item['product_id']);
                    $db->bind(':notes', 'İptal edilen sipariş: #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                    $existingCheck = $db->single();
                    
                    // Only add if not already restored
                    if ($existingCheck['count'] == 0) {
                        $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes) 
                                   VALUES (:product_id, 'in', :quantity, :unit, CURDATE(), :notes)");
                        $db->bind(':product_id', $item['product_id']);
                        $db->bind(':quantity', $item['quantity']);
                        $db->bind(':unit', $unit);
                        $db->bind(':notes', 'İptal edilen sipariş: #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
                        $db->execute();
                    }
                }
                
                // Update transaction status
                $db->query("UPDATE transactions SET 
                            notes = CONCAT(notes, ' (İptal edildi)')
                            WHERE reference_type = 'order' AND reference_id = :order_id");
                $db->bind(':order_id', $orderId);
                $db->execute();
            }
            
            // Add status change to order history
            $db->query("INSERT INTO order_history (order_id, status, note, created_at) 
                       VALUES (:order_id, :status, :note, NOW())");
            $db->bind(':order_id', $orderId);
            $db->bind(':status', $status);
            $db->bind(':note', 'Durum güncellendi');
            $db->execute();
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Sipariş durumu güncellendi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Durum güncellenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'delete':
        // Delete order
        $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($orderId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz sipariş ID\'si'], 400);
        }
        
        // Get order
        $db->query("SELECT * FROM orders WHERE id = :id");
        $db->bind(':id', $orderId);
        $order = $db->single();
        
        if (!$order) {
            jsonResponse(['success' => false, 'message' => 'Sipariş bulunamadı'], 404);
        }
        
        // Check if order can be deleted (only cancelled orders)
        if ($order['status'] != 'cancelled') {
            jsonResponse(['success' => false, 'message' => 'Sadece iptal edilen siparişler silinebilir'], 400);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Delete order items
            $db->query("DELETE FROM order_items WHERE order_id = :order_id");
            $db->bind(':order_id', $orderId);
            $db->execute();
            
            // Delete related transactions
            $db->query("DELETE FROM transactions 
                       WHERE reference_type = 'order' AND reference_id = :order_id");
            $db->bind(':order_id', $orderId);
            $db->execute();
            
            // Delete related stock movements
            $db->query("DELETE FROM stock_movements 
                       WHERE notes = :notes");
            $db->bind(':notes', 'Sipariş #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
            $db->execute();
            
            // Also delete cancellation stock movements
            $db->query("DELETE FROM stock_movements 
                       WHERE notes = :notes");
            $db->bind(':notes', 'İptal edilen sipariş: #' . str_pad($orderId, 6, '0', STR_PAD_LEFT));
            $db->execute();
            
            // Delete order
            $db->query("DELETE FROM orders WHERE id = :id");
            $db->bind(':id', $orderId);
            $db->execute();
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Sipariş başarıyla silindi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Sipariş silinirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'get_customer_orders':
        // Get orders for a specific customer
        $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        if ($customerId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz müşteri ID\'si'], 400);
        }
        
        $db->query("SELECT o.*, 
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
                    DATE_FORMAT(o.order_date, '%d.%m.%Y') as formatted_date
                    FROM orders o 
                    WHERE o.customer_id = :customer_id
                    ORDER BY o.order_date DESC, o.id DESC
                    LIMIT :limit");
        $db->bind(':customer_id', $customerId);
        $db->bind(':limit', $limit);
        $orders = $db->resultSet();
        
        jsonResponse(['success' => true, 'orders' => $orders]);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz eylem'], 400);
        break;
}