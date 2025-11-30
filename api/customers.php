<?php
/**
 * Megabre StokMaster Pro
 * Customers API
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
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
        
        // Build query
        $query = "SELECT c.* FROM customers c";
        
        // Add WHERE conditions
        $where = [];
        $params = [];
        
        if (!empty($search)) {
            $where[] = "(c.name LIKE :search OR c.surname LIKE :search OR c.phone LIKE :search OR c.email LIKE :search OR c.company LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        // Add ORDER BY
        $query .= " ORDER BY c.created_at DESC";
        
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
        
        // Get customers
        $customers = $db->resultSet();
        
        // Get balance info for customers
        if (!empty($customers)) {
            foreach ($customers as &$customer) {
                // Get balance
                $db->query("
                    SELECT 
                        COALESCE(SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END), 0) as total_debt,
                        COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) as total_payment
                    FROM transactions 
                    WHERE customer_id = :customer_id
                ");
                $db->bind(':customer_id', $customer['id']);
                $balance = $db->single();
                
                $customer['total_debt'] = $balance['total_debt'];
                $customer['total_payment'] = $balance['total_payment'];
                $customer['net_balance'] = $balance['total_payment'] - $balance['total_debt'];
                $customer['financial_status'] = $customer['net_balance'] >= 0 ? 'creditor' : 'debtor';
            }
        }
        
        jsonResponse(['success' => true, 'customers' => $customers]);
        break;
        
    case 'get':
        // Get single customer
        $customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($customerId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz müşteri ID\'si'], 400);
        }
        
        $db->query("SELECT * FROM customers WHERE id = :id");
        $db->bind(':id', $customerId);
        $customer = $db->single();
        
        if (!$customer) {
            jsonResponse(['success' => false, 'message' => 'Müşteri bulunamadı'], 404);
        }
        
        // Get customer field values
        $db->query("
            SELECT cfv.*, cf.field_name, cf.field_type 
            FROM customer_field_values cfv
            JOIN customer_fields cf ON cfv.field_id = cf.id
            WHERE cfv.customer_id = :customer_id
        ");
        $db->bind(':customer_id', $customerId);
        $fieldValues = $db->resultSet();
        
        $customer['fields'] = $fieldValues;
        
        // Get balance info
        $db->query("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END), 0) as total_debt,
                COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) as total_payment
            FROM transactions 
            WHERE customer_id = :customer_id
        ");
        $db->bind(':customer_id', $customerId);
        $balance = $db->single();
        
        $customer['total_debt'] = $balance['total_debt'];
        $customer['total_payment'] = $balance['total_payment'];
        $customer['net_balance'] = $balance['total_payment'] - $balance['total_debt'];
        $customer['financial_status'] = $customer['net_balance'] >= 0 ? 'creditor' : 'debtor';
        
        // Get order summary
        $db->query("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue
            FROM orders 
            WHERE customer_id = :customer_id
        ");
        $db->bind(':customer_id', $customerId);
        $orderSummary = $db->single();
        
        $customer['order_summary'] = $orderSummary;
        
        jsonResponse(['success' => true, 'customer' => $customer]);
        break;
        
    case 'create':
        // Create new customer
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        // Get form data
        $name = post('name');
        $surname = post('surname');
        $phone = post('phone');
        $email = post('email');
        $company = post('company');
        $address = post('address');
        $notes = post('notes');
        $dynamicFields = post('dynamic_fields');
        
        // Validate data
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Ad gereklidir.';
        }
        
        if (empty($surname)) {
            $errors[] = 'Soyad gereklidir.';
        }
        
        if (empty($phone)) {
            $errors[] = 'Telefon gereklidir.';
        }
        
        // Validate phone format
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Check email format if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçersiz e-posta formatı.';
        }
        
        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert customer
            $db->query("INSERT INTO customers (name, surname, phone, email, company, address, notes) 
                       VALUES (:name, :surname, :phone, :email, :company, :address, :notes)");
            $db->bind(':name', $name);
            $db->bind(':surname', $surname);
            $db->bind(':phone', $phone);
            $db->bind(':email', $email);
            $db->bind(':company', $company);
            $db->bind(':address', $address);
            $db->bind(':notes', $notes);
            $db->execute();
            
            $customerId = $db->lastInsertId();
            
            // Insert dynamic field values
            if ($dynamicFields && is_array($dynamicFields)) {
                foreach ($dynamicFields as $fieldId => $fieldValue) {
                    if (!empty($fieldValue)) {
                        $db->query("INSERT INTO customer_field_values (customer_id, field_id, field_value) 
                                   VALUES (:customer_id, :field_id, :field_value)");
                        $db->bind(':customer_id', $customerId);
                        $db->bind(':field_id', $fieldId);
                        $db->bind(':field_value', $fieldValue);
                        $db->execute();
                    }
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Müşteri başarıyla eklendi', 'customer_id' => $customerId]);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Müşteri eklenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'update':
        // Update customer
        if (!isPost()) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        $customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($customerId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz müşteri ID\'si'], 400);
        }
        
        // Check if customer exists
        $db->query("SELECT * FROM customers WHERE id = :id");
        $db->bind(':id', $customerId);
        $customer = $db->single();
        
        if (!$customer) {
            jsonResponse(['success' => false, 'message' => 'Müşteri bulunamadı'], 404);
        }
        
        // Get form data
        $name = post('name');
        $surname = post('surname');
        $phone = post('phone');
        $email = post('email');
        $company = post('company');
        $address = post('address');
        $notes = post('notes');
        $dynamicFields = post('dynamic_fields');
        
        // Validate data
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Ad gereklidir.';
        }
        
        if (empty($surname)) {
            $errors[] = 'Soyad gereklidir.';
        }
        
        if (empty($phone)) {
            $errors[] = 'Telefon gereklidir.';
        }
        
        // Validate phone format
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Check email format if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçersiz e-posta formatı.';
        }
        
        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Update customer
            $db->query("UPDATE customers SET 
                        name = :name, 
                        surname = :surname, 
                        phone = :phone, 
                        email = :email, 
                        company = :company, 
                        address = :address, 
                        notes = :notes,
                        updated_at = NOW() 
                        WHERE id = :id");
            $db->bind(':name', $name);
            $db->bind(':surname', $surname);
            $db->bind(':phone', $phone);
            $db->bind(':email', $email);
            $db->bind(':company', $company);
            $db->bind(':address', $address);
            $db->bind(':notes', $notes);
            $db->bind(':id', $customerId);
            $db->execute();
            
            // Delete existing field values
            $db->query("DELETE FROM customer_field_values WHERE customer_id = :customer_id");
            $db->bind(':customer_id', $customerId);
            $db->execute();
            
            // Insert new field values
            if ($dynamicFields && is_array($dynamicFields)) {
                foreach ($dynamicFields as $fieldId => $fieldValue) {
                    if (!empty($fieldValue)) {
                        $db->query("INSERT INTO customer_field_values (customer_id, field_id, field_value) 
                                   VALUES (:customer_id, :field_id, :field_value)");
                        $db->bind(':customer_id', $customerId);
                        $db->bind(':field_id', $fieldId);
                        $db->bind(':field_value', $fieldValue);
                        $db->execute();
                    }
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Müşteri başarıyla güncellendi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Müşteri güncellenirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'delete':
        // Delete customer
        $customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($customerId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Geçersiz müşteri ID\'si'], 400);
        }
        
        // Check permissions
        if (!$auth->isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Bu işlem için giriş yapmalısınız'], 403);
        }
        
        // Check if customer exists
        $db->query("SELECT * FROM customers WHERE id = :id");
        $db->bind(':id', $customerId);
        $customer = $db->single();
        
        if (!$customer) {
            jsonResponse(['success' => false, 'message' => 'Müşteri bulunamadı'], 404);
        }
        
        // Check if customer has orders or transactions
        $db->query("SELECT COUNT(*) as count FROM orders WHERE customer_id = :customer_id");
        $db->bind(':customer_id', $customerId);
        $ordersCount = $db->single()['count'];
        
        $db->query("SELECT COUNT(*) as count FROM transactions WHERE customer_id = :customer_id");
        $db->bind(':customer_id', $customerId);
        $transactionsCount = $db->single()['count'];
        
        if ($ordersCount > 0 || $transactionsCount > 0) {
            jsonResponse([
                'success' => false, 
                'message' => 'Bu müşterinin siparişleri veya mali işlemleri bulunduğu için silinemez.',
                'orders_count' => $ordersCount,
                'transactions_count' => $transactionsCount
            ], 400);
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Delete customer field values
            $db->query("DELETE FROM customer_field_values WHERE customer_id = :customer_id");
            $db->bind(':customer_id', $customerId);
            $db->execute();
            
            // Delete customer
            $db->query("DELETE FROM customers WHERE id = :id");
            $db->bind(':id', $customerId);
            $db->execute();
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Müşteri başarıyla silindi']);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            
            jsonResponse(['success' => false, 'message' => 'Müşteri silinirken bir hata oluştu: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'get_balance':
        // Check if customer ID is provided
        if (!isset($_GET['customer_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Müşteri ID\'si gereklidir.'
            ]);
            exit;
        }
        
        $customerId = (int)$_GET['customer_id'];
        
        try {
            // Get total payments
            $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                        WHERE customer_id = :customer_id AND type = 'payment'");
            $db->bind(':customer_id', $customerId);
            $payments = $db->single()['total'];
            
            // Get total debts
            $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                        WHERE customer_id = :customer_id AND type = 'debt'");
            $db->bind(':customer_id', $customerId);
            $debts = $db->single()['total'];
            
            // Calculate balance
            $balance = $payments - $debts;
            
            echo json_encode([
                'success' => true,
                'balance' => $balance
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Müşteri bakiyesi hesaplanırken bir hata oluştu: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'search':
        // Search customers
        $term = isset($_GET['term']) ? $_GET['term'] : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        if (empty($term)) {
            jsonResponse(['success' => false, 'message' => 'Arama terimi gereklidir'], 400);
        }
        
        // Search customers
        $db->query("SELECT id, name, surname, phone, email, company 
                   FROM customers 
                   WHERE name LIKE :term OR surname LIKE :term OR phone LIKE :term OR email LIKE :term OR company LIKE :term 
                   ORDER BY name ASC, surname ASC 
                   LIMIT :limit");
        $db->bind(':term', "%$term%");
        $db->bind(':limit', $limit);
        $customers = $db->resultSet();
        
        // Format response
        $results = [];
        foreach ($customers as $customer) {
            $label = $customer['name'] . ' ' . $customer['surname'];
            if (!empty($customer['company'])) {
                $label .= ' (' . $customer['company'] . ')';
            }
            
            $results[] = [
                'id' => $customer['id'],
                'value' => $customer['id'],
                'label' => $label,
                'name' => $customer['name'],
                'surname' => $customer['surname'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
                'company' => $customer['company']
            ];
        }
        
        jsonResponse(['success' => true, 'customers' => $results]);
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
        
        // Check field count (system-wide fields only, customer_id = 0 or NULL)
        $db->query("SELECT COUNT(*) as count FROM customer_fields WHERE customer_id = 0 OR customer_id IS NULL");
        $fieldCount = $db->single()['count'];
        
        if ($fieldCount >= 20) {
            jsonResponse(['success' => false, 'message' => 'Maksimum 20 dinamik alan ekleyebilirsiniz'], 400);
        }
        
        // Generate field key
        $fieldKey = slugify($fieldName);
        
        // Check and add missing columns if needed
        try {
            // Check if field_key column exists
            $db->query("SHOW COLUMNS FROM customer_fields LIKE 'field_key'");
            $result = $db->single();
            if (!$result) {
                // Add missing columns
                $db->query("ALTER TABLE customer_fields ADD COLUMN field_key VARCHAR(100) NULL AFTER id");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist or table structure issue
        }
        
        try {
            // Check if field_options column exists
            $db->query("SHOW COLUMNS FROM customer_fields LIKE 'field_options'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE customer_fields ADD COLUMN field_options TEXT NULL AFTER field_type");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        try {
            // Check if is_required column exists
            $db->query("SHOW COLUMNS FROM customer_fields LIKE 'is_required'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE customer_fields ADD COLUMN is_required TINYINT(1) NOT NULL DEFAULT 0 AFTER field_options");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        try {
            // Check if is_active column exists
            $db->query("SHOW COLUMNS FROM customer_fields LIKE 'is_active'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE customer_fields ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_required");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        try {
            // Check if field_order column exists
            $db->query("SHOW COLUMNS FROM customer_fields LIKE 'field_order'");
            $result = $db->single();
            if (!$result) {
                $db->query("ALTER TABLE customer_fields ADD COLUMN field_order INT NOT NULL DEFAULT 0 AFTER is_active");
                $db->execute();
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
        
        // Get next order (only if field_order column exists)
        try {
            $db->query("SELECT MAX(field_order) as max_order FROM customer_fields WHERE customer_id = 0 OR customer_id IS NULL");
            $maxOrder = $db->single()['max_order'] ?? 0;
            $fieldOrder = $maxOrder + 1;
        } catch (PDOException $e) {
            // If query fails, use count as order
            $db->query("SELECT COUNT(*) as count FROM customer_fields WHERE customer_id = 0 OR customer_id IS NULL");
            $count = $db->single()['count'] ?? 0;
            $fieldOrder = $count + 1;
        }
        
        try {
            // Check if customer_id column allows NULL
            $db->query("SHOW COLUMNS FROM customer_fields WHERE Field = 'customer_id'");
            $customerIdColumn = $db->single();
            $customerIdValue = null;
            
            if ($customerIdColumn && $customerIdColumn['Null'] === 'YES') {
                // Column allows NULL, use NULL for system-wide fields
                $customerIdValue = null;
            } else {
                // Column doesn't allow NULL, use 0
                $customerIdValue = 0;
            }
            
            // Insert field (system-wide field, customer_id = NULL or 0)
            if ($customerIdValue === null) {
                $db->query("INSERT INTO customer_fields (customer_id, field_name, field_key, field_type, field_options, is_required, is_active, field_order) 
                           VALUES (NULL, :field_name, :field_key, :field_type, :field_options, :is_required, :is_active, :field_order)");
            } else {
                $db->query("INSERT INTO customer_fields (customer_id, field_name, field_key, field_type, field_options, is_required, is_active, field_order) 
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
            error_log('Customer field add error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Alan eklenirken bir hata oluştu: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            // Log error for debugging
            error_log('Customer field add error: ' . $e->getMessage());
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
            $db->query("UPDATE customer_fields SET 
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
            // Delete field values
            $db->query("DELETE FROM customer_field_values WHERE field_id = :field_id");
            $db->bind(':field_id', $fieldId);
            $db->execute();
            
            // Delete field
            $db->query("DELETE FROM customer_fields WHERE id = :id");
            $db->bind(':id', $fieldId);
            $db->execute();
            
            // Reorder remaining fields (system-wide fields only)
            $db->query("SET @order = 0");
            $db->execute();
            
            $db->query("UPDATE customer_fields SET field_order = (@order := @order + 1) WHERE customer_id = 0 OR customer_id IS NULL ORDER BY field_order");
            $db->execute();
            
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => 'Alan başarıyla silindi']);
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            jsonResponse(['success' => false, 'message' => 'Alan silinirken bir hata oluştu'], 500);
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
        $db->query("SELECT * FROM customer_fields WHERE id = :id");
        $db->bind(':id', $fieldId);
        $currentField = $db->single();
        
        if (!$currentField) {
            jsonResponse(['success' => false, 'message' => 'Alan bulunamadı'], 404);
        }
        
        $currentOrder = $currentField['field_order'];
        $newOrder = $direction === 'up' ? $currentOrder - 1 : $currentOrder + 1;
        
        // Get swap field (system-wide fields only)
        $db->query("SELECT * FROM customer_fields WHERE (customer_id = 0 OR customer_id IS NULL) AND field_order = :order");
        $db->bind(':order', $newOrder);
        $swapField = $db->single();
        
        if ($swapField) {
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Update current field
                $db->query("UPDATE customer_fields SET field_order = :order WHERE id = :id");
                $db->bind(':order', $newOrder);
                $db->bind(':id', $currentField['id']);
                $db->execute();
                
                // Update swap field
                $db->query("UPDATE customer_fields SET field_order = :order WHERE id = :id");
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