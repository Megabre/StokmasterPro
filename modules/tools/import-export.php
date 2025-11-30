<?php
/**
 * Megabre StokMaster Pro
 * Import/Export Tool
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

// Create necessary tables if they don't exist
$db->query("CREATE TABLE IF NOT EXISTS import_export_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('import', 'export') NOT NULL,
    file_type VARCHAR(10) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    details TEXT,
    created_at DATETIME NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->execute();

// Process actions
$message = '';
$status = '';
$importResult = null;

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOADS_PATH . 'import/')) {
    mkdir(UPLOADS_PATH . 'import/', 0755, true);
}

// Define template paths
define('TEMPLATE_PATH', dirname(__DIR__, 2) . '/templates/');

// Create templates directory if it doesn't exist
if (!file_exists(TEMPLATE_PATH)) {
    mkdir(TEMPLATE_PATH, 0755, true);
}

// Define template contents
$templates = [
    'products_template.csv' => "id,name,description,sku,barcode,category_id,unit,price,cost_price,tax_rate,min_stock,max_stock,status\n1,Örnek Ürün 1,Ürün açıklaması,SKU001,8680000000001,1,adet,100.00,80.00,18,10,100,active\n2,Örnek Ürün 2,İkinci ürün açıklaması,SKU002,8680000000002,1,adet,150.00,120.00,18,5,50,active",
    'products_template.xlsx' => "id,name,description,sku,barcode,category_id,unit,price,cost_price,tax_rate,min_stock,max_stock,status\n1,Örnek Ürün 1,Ürün açıklaması,SKU001,8680000000001,1,adet,100.00,80.00,18,10,100,active\n2,Örnek Ürün 2,İkinci ürün açıklaması,SKU002,8680000000002,1,adet,150.00,120.00,18,5,50,active",
    'customers_template.csv' => "id,name,surname,email,phone,address,city,country,tax_office,tax_number,status\n1,Ahmet,Yılmaz,ahmet@example.com,5551234567,Örnek Mahallesi No:1,İstanbul,Türkiye,Örnek Vergi Dairesi,1234567890,active\n2,Ayşe,Demir,ayse@example.com,5559876543,Test Sokak No:2,Ankara,Türkiye,Test Vergi Dairesi,9876543210,active",
    'customers_template.xlsx' => "id,name,surname,email,phone,address,city,country,tax_office,tax_number,status\n1,Ahmet,Yılmaz,ahmet@example.com,5551234567,Örnek Mahallesi No:1,İstanbul,Türkiye,Örnek Vergi Dairesi,1234567890,active\n2,Ayşe,Demir,ayse@example.com,5559876543,Test Sokak No:2,Ankara,Türkiye,Test Vergi Dairesi,9876543210,active",
    'stock_template.csv' => "id,product_id,quantity,type,date,notes\n1,1,100,in,2024-03-20,İlk stok girişi\n2,2,50,in,2024-03-20,İlk stok girişi",
    'stock_template.xlsx' => "id,product_id,quantity,type,date,notes\n1,1,100,in,2024-03-20,İlk stok girişi\n2,2,50,in,2024-03-20,İlk stok girişi",
    'categories_template.csv' => "id,name,description\n1,Elektronik,Elektronik ürünler kategorisi\n2,Giyim,Giyim ürünleri kategorisi",
    'categories_template.xlsx' => "id,name,description\n1,Elektronik,Elektronik ürünler kategorisi\n2,Giyim,Giyim ürünleri kategorisi",
    'orders_template.csv' => "id,customer_id,order_date,status,notes,total_amount,vat_rate,vat_amount,grand_total\n1,1,2024-03-20,pending,Sipariş notu,1000.00,18,180.00,1180.00\n2,2,2024-03-21,processing,İkinci sipariş,500.00,18,90.00,590.00",
    'orders_template.xlsx' => "id,customer_id,order_date,status,notes,total_amount,vat_rate,vat_amount,grand_total\n1,1,2024-03-20,pending,Sipariş notu,1000.00,18,180.00,1180.00\n2,2,2024-03-21,processing,İkinci sipariş,500.00,18,90.00,590.00",
    'transactions_template.csv' => "id,customer_id,type,amount,date,payment_method,reference_no,is_installment,installment_count,installment_number,notes,order_id\n1,1,payment,500.00,2024-03-20,cash,REF001,0,,,Ödeme notu,\n2,2,debt,250.00,2024-03-21,bank_transfer,REF002,1,3,1,Borç notu,1",
    'transactions_template.xlsx' => "id,customer_id,type,amount,date,payment_method,reference_no,is_installment,installment_count,installment_number,notes,order_id\n1,1,payment,500.00,2024-03-20,cash,REF001,0,,,Ödeme notu,\n2,2,debt,250.00,2024-03-21,bank_transfer,REF002,1,3,1,Borç notu,1"
];

// Create template files
foreach ($templates as $filename => $content) {
    $filepath = TEMPLATE_PATH . $filename;
    if (!file_exists($filepath)) {
        file_put_contents($filepath, $content);
    }
}

// Process import action
if (isset($_POST['import_data'])) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=tools&action=import-export');
    }
    
    $importType = isset($_POST['import_type']) ? $_POST['import_type'] : '';
    
    if (empty($importType)) {
        $message = t('import_export_select_type', 'Lütfen içe aktarılacak veri türünü seçin.');
        $status = 'error';
    } else if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $message = t('import_export_select_file', 'Lütfen geçerli bir dosya seçin.');
        $status = 'error';
    } else {
        // Get uploaded file
        $file = $_FILES['import_file'];
        $fileName = $file['name'];
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check file extension
        if ($fileExt != 'csv' && $fileExt != 'xlsx') {
            $message = t('import_export_invalid_format', 'Sadece CSV ve Excel (XLSX) dosyaları desteklenmektedir.');
            $status = 'error';
        } else {
            // Move file to uploads directory
            $newFileName = 'import_' . date('Ymd_His') . '.' . $fileExt;
            $filePath = UPLOADS_PATH . 'import/' . $newFileName;
            
            if (move_uploaded_file($fileTmp, $filePath)) {
                try {
                    // Process import based on type
                    switch ($importType) {
                        case 'products':
                            $importResult = importProducts($filePath, $fileExt);
                            break;
                            
                        case 'customers':
                            $importResult = importCustomers($filePath, $fileExt);
                            break;
                            
                        case 'stock':
                            $importResult = importStock($filePath, $fileExt);
                            break;
                            
                        case 'categories':
                            $importResult = importCategories($filePath, $fileExt);
                            break;
                            
                        case 'orders':
                            $importResult = importOrders($filePath, $fileExt);
                            break;
                            
                        case 'transactions':
                            $importResult = importTransactions($filePath, $fileExt);
                            break;
                            
                        default:
                            throw new Exception(t('import_export_unsupported_type', 'Desteklenmeyen içe aktarım türü.'));
                    }
                    
                    $message = $importResult['success'] . ' ' . t('import_export_success', 'kayıt başarıyla içe aktarıldı.');
                    if ($importResult['errors'] > 0) {
                        $message .= ' ' . $importResult['errors'] . ' ' . t('import_export_errors', 'kayıt hata nedeniyle atlandı.');
                    }
                    $status = 'success';
                    
                } catch (Exception $e) {
                    $message = t('import_export_import_error', 'İçe aktarma sırasında hata oluştu:') . ' ' . $e->getMessage();
                    $status = 'error';
                }
            } else {
                $message = t('import_export_upload_error', 'Dosya yüklenirken hata oluştu.');
                $status = 'error';
            }
        }
    }
}

// Process export action
if (isset($_POST['export_data'])) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=tools&action=import-export');
    }
    
    $exportType = isset($_POST['export_type']) ? $_POST['export_type'] : '';
    $exportFormat = isset($_POST['export_format']) ? $_POST['export_format'] : 'csv';
    
    if (empty($exportType)) {
        $message = t('import_export_select_export_type', 'Lütfen dışa aktarılacak veri türünü seçin.');
        $status = 'error';
    } else {
        try {
            // Generate export data
            switch ($exportType) {
                case 'products':
                    $data = getProductsForExport();
                    $filename = 'products_export_' . date('Ymd_His');
                    break;
                    
                case 'customers':
                    $data = getCustomersForExport();
                    $filename = 'customers_export_' . date('Ymd_His');
                    break;
                    
                case 'stock':
                    $data = getStockForExport();
                    $filename = 'stock_export_' . date('Ymd_His');
                    break;
                    
                case 'categories':
                    $data = getCategoriesForExport();
                    $filename = 'categories_export_' . date('Ymd_His');
                    break;
                    
                case 'orders':
                    $data = getOrdersForExport();
                    $filename = 'orders_export_' . date('Ymd_His');
                    break;
                    
                case 'transactions':
                    $data = getTransactionsForExport();
                    $filename = 'transactions_export_' . date('Ymd_His');
                    break;
                    
                default:
                    throw new Exception('Desteklenmeyen dışa aktarım türü.');
            }
            
            // Export based on format
            if ($exportFormat == 'csv') {
                exportAsCSV($data, $filename);
            } else {
                exportAsExcel($data, $filename);
            }
            
            exit; // Stop execution after starting download
            
        } catch (Exception $e) {
            $message = t('import_export_export_error', 'Dışa aktarma sırasında hata oluştu:') . ' ' . $e->getMessage();
            $status = 'error';
        }
    }
}

// Process download template action
if (isset($_GET['download_template'])) {
    $type = $_GET['download_template'];
    $fileType = $_GET['file_type'] ?? 'csv';
    
    $templateFile = TEMPLATE_PATH . $type . '_template.' . $fileType;
    
    if (file_exists($templateFile)) {
        header('Content-Description: File Transfer');
        header('Content-Type: ' . ($fileType === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
        header('Content-Disposition: attachment; filename="' . basename($templateFile) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($templateFile));
        readfile($templateFile);
        exit;
    } else {
        Session::setFlash('error', t('import_export_template_not_found', 'Şablon dosyası bulunamadı.'));
        redirect('index.php?module=tools&action=import-export');
    }
}

// Function to import products
function importProducts($filePath, $fileExt) {
    $db = Database::getInstance();
    $success = 0;
    $errors = 0;
    $errorMessages = [];
    
    // Read file data
    $data = readImportFile($filePath, $fileExt);
    
    if (empty($data)) {
        throw new Exception(t('import_export_no_data', 'İçe aktarılacak veri bulunamadı.'));
    }
    
    // Check required headers
    $requiredHeaders = ['name', 'category_id', 'price'];
    $headers = array_keys($data[0]);
    
    foreach ($requiredHeaders as $header) {
        if (!in_array($header, $headers)) {
            throw new Exception("Gerekli sütun bulunamadı: $header");
        }
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        foreach ($data as $row) {
            // Validate row data
            if (empty($row['name']) || empty($row['category_id']) || !isset($row['price'])) {
                $errors++;
                $errorMessages[] = "Eksik veri: {$row['name']} (Ürün adı, kategori ID veya fiyat boş olamaz)";
                continue;
            }
            
            // Check if category exists
            $db->query("SELECT id FROM categories WHERE id = :id");
            $db->bind(':id', $row['category_id']);
            if (!$db->single()) {
                $errors++;
                $errorMessages[] = "Geçersiz kategori ID: {$row['category_id']} - Ürün: {$row['name']}";
                continue;
            }
            
            // Format price
            $price = str_replace(',', '.', $row['price']);
            if (!is_numeric($price)) {
                $errors++;
                $errorMessages[] = "Geçersiz fiyat formatı: {$row['price']} - Ürün: {$row['name']}";
                continue;
            }
            
            // Check if product exists (by name)
            $db->query("SELECT id FROM products WHERE name = :name");
            $db->bind(':name', $row['name']);
            $existingProduct = $db->single();
            
            if ($existingProduct) {
                // Update existing product
                $db->query("UPDATE products SET 
                           category_id = :category_id,
                           price = :price,
                           sku = :sku,
                           barcode = :barcode,
                           description = :description,
                           min_stock_level = :min_stock_level,
                           updated_at = NOW()
                           WHERE id = :id");
                           
                $db->bind(':category_id', $row['category_id']);
                $db->bind(':price', $price);
                $db->bind(':sku', $row['sku'] ?? '');
                $db->bind(':barcode', $row['barcode'] ?? '');
                $db->bind(':description', $row['description'] ?? '');
                $db->bind(':min_stock_level', $row['min_stock_level'] ?? 0);
                $db->bind(':id', $existingProduct['id']);
                $db->execute();
                
                $productId = $existingProduct['id'];
            } else {
                // Insert new product
                $db->query("INSERT INTO products (category_id, name, price, sku, barcode, description, min_stock_level) 
                           VALUES (:category_id, :name, :price, :sku, :barcode, :description, :min_stock_level)");
                           
                $db->bind(':category_id', $row['category_id']);
                $db->bind(':name', $row['name']);
                $db->bind(':price', $price);
                $db->bind(':sku', $row['sku'] ?? '');
                $db->bind(':barcode', $row['barcode'] ?? '');
                $db->bind(':description', $row['description'] ?? '');
                $db->bind(':min_stock_level', $row['min_stock_level'] ?? 0);
                $db->execute();
                
                $productId = $db->lastInsertId();
            }
            
            // Add initial stock if provided
            if (isset($row['initial_stock']) && is_numeric($row['initial_stock']) && $row['initial_stock'] > 0) {
                $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes) 
                           VALUES (:product_id, 'in', :quantity, :unit, CURDATE(), 'İçe aktarma ile eklendi')");
                           
                $db->bind(':product_id', $productId);
                $db->bind(':quantity', $row['initial_stock']);
                $db->bind(':unit', $row['unit'] ?? 'piece');
                $db->execute();
            }
            
            $success++;
        }
        
        // Commit transaction
        $db->endTransaction();
        
        return [
            'success' => $success,
            'errors' => $errors,
            'error_messages' => $errorMessages
        ];
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->cancelTransaction();
        throw $e;
    }
}

// Function to import customers
function importCustomers($filePath, $fileExt) {
    $db = Database::getInstance();
    $success = 0;
    $errors = 0;
    $errorMessages = [];
    
    // Read file data
    $data = readImportFile($filePath, $fileExt);
    
    if (empty($data)) {
        throw new Exception(t('import_export_no_data', 'İçe aktarılacak veri bulunamadı.'));
    }
    
    // Check required headers
    $requiredHeaders = ['name', 'surname', 'phone'];
    $headers = array_keys($data[0]);
    
    foreach ($requiredHeaders as $header) {
        if (!in_array($header, $headers)) {
            throw new Exception("Gerekli sütun bulunamadı: $header");
        }
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        foreach ($data as $row) {
            // Validate row data
            if (empty($row['name']) || empty($row['surname']) || empty($row['phone'])) {
                $errors++;
                $errorMessages[] = "Eksik veri: {$row['name']} {$row['surname']} (Ad, soyad veya telefon boş olamaz)";
                continue;
            }
            
            // Check if customer exists (by phone)
            $db->query("SELECT id FROM customers WHERE phone = :phone");
            $db->bind(':phone', $row['phone']);
            $existingCustomer = $db->single();
            
            if ($existingCustomer) {
                // Update existing customer
                $db->query("UPDATE customers SET 
                           name = :name,
                           surname = :surname,
                           email = :email,
                           company = :company,
                           address = :address,
                           notes = :notes,
                           updated_at = NOW()
                           WHERE id = :id");
                           
                $db->bind(':name', $row['name']);
                $db->bind(':surname', $row['surname']);
                $db->bind(':email', $row['email'] ?? '');
                $db->bind(':company', $row['company'] ?? '');
                $db->bind(':address', $row['address'] ?? '');
                $db->bind(':notes', $row['notes'] ?? '');
                $db->bind(':id', $existingCustomer['id']);
                $db->execute();
            } else {
                // Insert new customer
                $db->query("INSERT INTO customers (name, surname, phone, email, company, address, notes) 
                           VALUES (:name, :surname, :phone, :email, :company, :address, :notes)");
                           
                $db->bind(':name', $row['name']);
                $db->bind(':surname', $row['surname']);
                $db->bind(':phone', $row['phone']);
                $db->bind(':email', $row['email'] ?? '');
                $db->bind(':company', $row['company'] ?? '');
                $db->bind(':address', $row['address'] ?? '');
                $db->bind(':notes', $row['notes'] ?? '');
                $db->execute();
            }
            
            $success++;
        }
        
        // Commit transaction
        $db->endTransaction();
        
        return [
            'success' => $success,
            'errors' => $errors,
            'error_messages' => $errorMessages
        ];
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->cancelTransaction();
        throw $e;
    }
}

// Function to import stock
function importStock($filePath, $fileExt) {
    $db = Database::getInstance();
    $success = 0;
    $errors = 0;
    $errorMessages = [];
    
    // Read file data
    $data = readImportFile($filePath, $fileExt);
    
    if (empty($data)) {
        throw new Exception(t('import_export_no_data', 'İçe aktarılacak veri bulunamadı.'));
    }
    
    // Check required headers
    $requiredHeaders = ['product_id', 'quantity', 'type', 'date'];
    $headers = array_keys($data[0]);
    
    foreach ($requiredHeaders as $header) {
        if (!in_array($header, $headers)) {
            throw new Exception("Gerekli sütun bulunamadı: $header");
        }
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        foreach ($data as $row) {
            // Validate row data
            if (empty($row['product_id']) || !isset($row['quantity']) || empty($row['type']) || empty($row['date'])) {
                $errors++;
                $errorMessages[] = "Eksik veri: Ürün ID {$row['product_id']} (Ürün ID, miktar, tür veya tarih boş olamaz)";
                continue;
            }
            
            // Check if product exists
            $db->query("SELECT id FROM products WHERE id = :id");
            $db->bind(':id', $row['product_id']);
            if (!$db->single()) {
                $errors++;
                $errorMessages[] = "Geçersiz ürün ID: {$row['product_id']}";
                continue;
            }
            
            // Validate quantity
            if (!is_numeric($row['quantity']) || floatval($row['quantity']) <= 0) {
                $errors++;
                $errorMessages[] = "Geçersiz miktar: {$row['quantity']} - Ürün ID: {$row['product_id']}";
                continue;
            }
            
            // Validate type
            if ($row['type'] != 'in' && $row['type'] != 'out') {
                $errors++;
                $errorMessages[] = "Geçersiz stok hareketi türü: {$row['type']} - Ürün ID: {$row['product_id']}";
                continue;
            }
            
            // Validate date
            if (!strtotime($row['date'])) {
                $errors++;
                $errorMessages[] = "Geçersiz tarih formatı: {$row['date']} - Ürün ID: {$row['product_id']}";
                continue;
            }
            
            // Insert stock movement
            $db->query("INSERT INTO stock_movements (product_id, type, quantity, unit, date, notes) 
                       VALUES (:product_id, :type, :quantity, :unit, :date, :notes)");
                       
            $db->bind(':product_id', $row['product_id']);
            $db->bind(':type', $row['type']);
            $db->bind(':quantity', $row['quantity']);
            $db->bind(':unit', $row['unit'] ?? 'piece');
            $db->bind(':date', date('Y-m-d', strtotime($row['date'])));
            $db->bind(':notes', $row['notes'] ?? 'İçe aktarma ile eklendi');
            $db->execute();
            
            $success++;
        }
        
        // Commit transaction
        $db->endTransaction();
        
        return [
            'success' => $success,
            'errors' => $errors,
            'error_messages' => $errorMessages
        ];
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->cancelTransaction();
        throw $e;
    }
}

// Function to import categories
function importCategories($filePath, $fileExt) {
    $db = Database::getInstance();
    $success = 0;
    $errors = 0;
    $errorMessages = [];
    
    // Read file data
    $data = readImportFile($filePath, $fileExt);
    
    if (empty($data)) {
        throw new Exception(t('import_export_no_data', 'İçe aktarılacak veri bulunamadı.'));
    }
    
    // Check required headers
    $requiredHeaders = ['name'];
    $headers = array_keys($data[0]);
    
    foreach ($requiredHeaders as $header) {
        if (!in_array($header, $headers)) {
            throw new Exception("Gerekli sütun bulunamadı: $header");
        }
    }
    
    // Check if id column exists (for re-importing exported data)
    $hasIdColumn = in_array('id', $headers);
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        foreach ($data as $row) {
            // Validate row data
            if (empty($row['name'])) {
                $errors++;
                $errorMessages[] = "Eksik veri: Kategori adı boş olamaz";
                continue;
            }
            
            $existingCategory = null;
            
            // If id is provided and exists in database, use it
            if ($hasIdColumn && !empty($row['id']) && is_numeric($row['id'])) {
                $db->query("SELECT id FROM categories WHERE id = :id");
                $db->bind(':id', $row['id']);
                $existingCategory = $db->single();
            }
            
            // If not found by id, try to find by name
            if (!$existingCategory) {
                $db->query("SELECT id FROM categories WHERE name = :name");
                $db->bind(':name', $row['name']);
                $existingCategory = $db->single();
            }
            
            if ($existingCategory) {
                // Update existing category
                $db->query("UPDATE categories SET 
                           name = :name,
                           description = :description,
                           updated_at = NOW()
                           WHERE id = :id");
                           
                $db->bind(':name', $row['name']);
                $db->bind(':description', $row['description'] ?? '');
                $db->bind(':id', $existingCategory['id']);
                $db->execute();
                
                $categoryId = $existingCategory['id'];
            } else {
                // Insert new category
                if ($hasIdColumn && !empty($row['id']) && is_numeric($row['id'])) {
                    // Try to insert with provided id (may fail if id already exists)
                    try {
                        $db->query("INSERT INTO categories (id, name, description) 
                                   VALUES (:id, :name, :description)");
                        
                        $db->bind(':id', $row['id']);
                        $db->bind(':name', $row['name']);
                        $db->bind(':description', $row['description'] ?? '');
                        $db->execute();
                        
                        $categoryId = $db->lastInsertId();
                    } catch (Exception $e) {
                        // If id insertion fails, insert without id
                        $db->query("INSERT INTO categories (name, description) 
                                   VALUES (:name, :description)");
                        
                        $db->bind(':name', $row['name']);
                        $db->bind(':description', $row['description'] ?? '');
                        $db->execute();
                        
                        $categoryId = $db->lastInsertId();
                    }
                } else {
                    // Insert without id
                    $db->query("INSERT INTO categories (name, description) 
                               VALUES (:name, :description)");
                    
                    $db->bind(':name', $row['name']);
                    $db->bind(':description', $row['description'] ?? '');
                    $db->execute();
                    
                    $categoryId = $db->lastInsertId();
                }
            }
            
            $success++;
        }
        
        // Commit transaction
        $db->endTransaction();
        
        return [
            'success' => $success,
            'errors' => $errors,
            'error_messages' => $errorMessages
        ];
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->cancelTransaction();
        throw $e;
    }
}

// Function to import orders
function importOrders($filePath, $fileExt) {
    $db = Database::getInstance();
    $success = 0;
    $errors = 0;
    $errorMessages = [];
    
    // Read file data
    $data = readImportFile($filePath, $fileExt);
    
    if (empty($data)) {
        throw new Exception(t('import_export_no_data', 'İçe aktarılacak veri bulunamadı.'));
    }
    
    // Check required headers
    $requiredHeaders = ['customer_id', 'order_date'];
    $headers = array_keys($data[0]);
    
    foreach ($requiredHeaders as $header) {
        if (!in_array($header, $headers)) {
            throw new Exception("Gerekli sütun bulunamadı: $header");
        }
    }
    
    // Check if id column exists (for re-importing exported data)
    $hasIdColumn = in_array('id', $headers);
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        foreach ($data as $row) {
            // Validate row data
            if (empty($row['customer_id']) || empty($row['order_date'])) {
                $errors++;
                $errorMessages[] = "Eksik veri: Müşteri ID veya sipariş tarihi boş olamaz";
                continue;
            }
            
            // Check if customer exists
            $db->query("SELECT id FROM customers WHERE id = :id");
            $db->bind(':id', $row['customer_id']);
            if (!$db->single()) {
                $errors++;
                $errorMessages[] = "Geçersiz müşteri ID: {$row['customer_id']}";
                continue;
            }
            
            // Validate date
            if (!strtotime($row['order_date'])) {
                $errors++;
                $errorMessages[] = "Geçersiz tarih formatı: {$row['order_date']}";
                continue;
            }
            
            // Format amounts
            $totalAmount = isset($row['total_amount']) ? str_replace(',', '.', $row['total_amount']) : 0;
            $vatRate = isset($row['vat_rate']) ? str_replace(',', '.', $row['vat_rate']) : null;
            $vatAmount = isset($row['vat_amount']) ? str_replace(',', '.', $row['vat_amount']) : null;
            $grandTotal = isset($row['grand_total']) ? str_replace(',', '.', $row['grand_total']) : $totalAmount;
            
            if (!is_numeric($totalAmount)) $totalAmount = 0;
            if ($vatRate !== null && !is_numeric($vatRate)) $vatRate = null;
            if ($vatAmount !== null && !is_numeric($vatAmount)) $vatAmount = null;
            if (!is_numeric($grandTotal)) $grandTotal = $totalAmount;
            
            // Validate status
            $status = isset($row['status']) ? $row['status'] : 'pending';
            if (!in_array($status, ['pending', 'processing', 'completed', 'cancelled'])) {
                $status = 'pending';
            }
            
            $existingOrder = null;
            
            // If id is provided and exists in database, use it
            if ($hasIdColumn && !empty($row['id']) && is_numeric($row['id'])) {
                $db->query("SELECT id FROM orders WHERE id = :id");
                $db->bind(':id', $row['id']);
                $existingOrder = $db->single();
            }
            
            if ($existingOrder) {
                // Update existing order
                $db->query("UPDATE orders SET 
                           customer_id = :customer_id,
                           order_date = :order_date,
                           status = :status,
                           notes = :notes,
                           total_amount = :total_amount,
                           vat_rate = :vat_rate,
                           vat_amount = :vat_amount,
                           grand_total = :grand_total,
                           updated_at = NOW()
                           WHERE id = :id");
                           
                $db->bind(':customer_id', $row['customer_id']);
                $db->bind(':order_date', date('Y-m-d', strtotime($row['order_date'])));
                $db->bind(':status', $status);
                $db->bind(':notes', $row['notes'] ?? '');
                $db->bind(':total_amount', $totalAmount);
                $db->bind(':vat_rate', $vatRate);
                $db->bind(':vat_amount', $vatAmount);
                $db->bind(':grand_total', $grandTotal);
                $db->bind(':id', $existingOrder['id']);
                $db->execute();
                
                $orderId = $existingOrder['id'];
            } else {
                // Insert new order
                if ($hasIdColumn && !empty($row['id']) && is_numeric($row['id'])) {
                    try {
                        $db->query("INSERT INTO orders (id, customer_id, order_date, status, notes, total_amount, vat_rate, vat_amount, grand_total) 
                                   VALUES (:id, :customer_id, :order_date, :status, :notes, :total_amount, :vat_rate, :vat_amount, :grand_total)");
                        
                        $db->bind(':id', $row['id']);
                        $db->bind(':customer_id', $row['customer_id']);
                        $db->bind(':order_date', date('Y-m-d', strtotime($row['order_date'])));
                        $db->bind(':status', $status);
                        $db->bind(':notes', $row['notes'] ?? '');
                        $db->bind(':total_amount', $totalAmount);
                        $db->bind(':vat_rate', $vatRate);
                        $db->bind(':vat_amount', $vatAmount);
                        $db->bind(':grand_total', $grandTotal);
                        $db->execute();
                        
                        $orderId = $db->lastInsertId();
                    } catch (Exception $e) {
                        // If id insertion fails, insert without id
                        $db->query("INSERT INTO orders (customer_id, order_date, status, notes, total_amount, vat_rate, vat_amount, grand_total) 
                                   VALUES (:customer_id, :order_date, :status, :notes, :total_amount, :vat_rate, :vat_amount, :grand_total)");
                        
                        $db->bind(':customer_id', $row['customer_id']);
                        $db->bind(':order_date', date('Y-m-d', strtotime($row['order_date'])));
                        $db->bind(':status', $status);
                        $db->bind(':notes', $row['notes'] ?? '');
                        $db->bind(':total_amount', $totalAmount);
                        $db->bind(':vat_rate', $vatRate);
                        $db->bind(':vat_amount', $vatAmount);
                        $db->bind(':grand_total', $grandTotal);
                        $db->execute();
                        
                        $orderId = $db->lastInsertId();
                    }
                } else {
                    // Insert without id
                    $db->query("INSERT INTO orders (customer_id, order_date, status, notes, total_amount, vat_rate, vat_amount, grand_total) 
                               VALUES (:customer_id, :order_date, :status, :notes, :total_amount, :vat_rate, :vat_amount, :grand_total)");
                    
                    $db->bind(':customer_id', $row['customer_id']);
                    $db->bind(':order_date', date('Y-m-d', strtotime($row['order_date'])));
                    $db->bind(':status', $status);
                    $db->bind(':notes', $row['notes'] ?? '');
                    $db->bind(':total_amount', $totalAmount);
                    $db->bind(':vat_rate', $vatRate);
                    $db->bind(':vat_amount', $vatAmount);
                    $db->bind(':grand_total', $grandTotal);
                    $db->execute();
                    
                    $orderId = $db->lastInsertId();
                }
            }
            
            $success++;
        }
        
        // Commit transaction
        $db->endTransaction();
        
        return [
            'success' => $success,
            'errors' => $errors,
            'error_messages' => $errorMessages
        ];
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->cancelTransaction();
        throw $e;
    }
}

// Function to import transactions
function importTransactions($filePath, $fileExt) {
    $db = Database::getInstance();
    $success = 0;
    $errors = 0;
    $errorMessages = [];
    
    // Read file data
    $data = readImportFile($filePath, $fileExt);
    
    if (empty($data)) {
        throw new Exception(t('import_export_no_data', 'İçe aktarılacak veri bulunamadı.'));
    }
    
    // Check required headers
    $requiredHeaders = ['customer_id', 'type', 'amount', 'date'];
    $headers = array_keys($data[0]);
    
    foreach ($requiredHeaders as $header) {
        if (!in_array($header, $headers)) {
            throw new Exception("Gerekli sütun bulunamadı: $header");
        }
    }
    
    // Check if id column exists (for re-importing exported data)
    $hasIdColumn = in_array('id', $headers);
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        foreach ($data as $row) {
            // Validate row data
            if (empty($row['customer_id']) || empty($row['type']) || !isset($row['amount']) || empty($row['date'])) {
                $errors++;
                $errorMessages[] = "Eksik veri: Müşteri ID, tür, tutar veya tarih boş olamaz";
                continue;
            }
            
            // Check if customer exists
            $db->query("SELECT id FROM customers WHERE id = :id");
            $db->bind(':id', $row['customer_id']);
            if (!$db->single()) {
                $errors++;
                $errorMessages[] = "Geçersiz müşteri ID: {$row['customer_id']}";
                continue;
            }
            
            // Validate type
            if (!in_array($row['type'], ['payment', 'debt'])) {
                $errors++;
                $errorMessages[] = "Geçersiz işlem türü: {$row['type']} (payment veya debt olmalı)";
                continue;
            }
            
            // Format amount
            $amount = str_replace(',', '.', $row['amount']);
            if (!is_numeric($amount)) {
                $errors++;
                $errorMessages[] = "Geçersiz tutar formatı: {$row['amount']}";
                continue;
            }
            
            // Validate date
            if (!strtotime($row['date'])) {
                $errors++;
                $errorMessages[] = "Geçersiz tarih formatı: {$row['date']}";
                continue;
            }
            
            // Validate payment_method if provided
            $paymentMethod = isset($row['payment_method']) ? $row['payment_method'] : null;
            if ($paymentMethod !== null && !in_array($paymentMethod, ['cash', 'check', 'promissory_note', 'credit_card', 'bank_transfer'])) {
                $paymentMethod = null;
            }
            
            $existingTransaction = null;
            
            // If id is provided and exists in database, use it
            if ($hasIdColumn && !empty($row['id']) && is_numeric($row['id'])) {
                $db->query("SELECT id FROM transactions WHERE id = :id");
                $db->bind(':id', $row['id']);
                $existingTransaction = $db->single();
            }
            
            if ($existingTransaction) {
                // Update existing transaction
                $db->query("UPDATE transactions SET 
                           customer_id = :customer_id,
                           type = :type,
                           amount = :amount,
                           date = :date,
                           payment_method = :payment_method,
                           reference_no = :reference_no,
                           is_installment = :is_installment,
                           installment_count = :installment_count,
                           installment_number = :installment_number,
                           notes = :notes,
                           order_id = :order_id,
                           updated_at = NOW()
                           WHERE id = :id");
                           
                $db->bind(':customer_id', $row['customer_id']);
                $db->bind(':type', $row['type']);
                $db->bind(':amount', $amount);
                $db->bind(':date', date('Y-m-d', strtotime($row['date'])));
                $db->bind(':payment_method', $paymentMethod);
                $db->bind(':reference_no', $row['reference_no'] ?? null);
                $db->bind(':is_installment', isset($row['is_installment']) ? (int)$row['is_installment'] : 0);
                $db->bind(':installment_count', isset($row['installment_count']) && is_numeric($row['installment_count']) ? (int)$row['installment_count'] : null);
                $db->bind(':installment_number', isset($row['installment_number']) && is_numeric($row['installment_number']) ? (int)$row['installment_number'] : null);
                $db->bind(':notes', $row['notes'] ?? null);
                $db->bind(':order_id', isset($row['order_id']) && is_numeric($row['order_id']) ? (int)$row['order_id'] : null);
                $db->bind(':id', $existingTransaction['id']);
                $db->execute();
                
                $transactionId = $existingTransaction['id'];
            } else {
                // Insert new transaction
                if ($hasIdColumn && !empty($row['id']) && is_numeric($row['id'])) {
                    try {
                        $db->query("INSERT INTO transactions (id, customer_id, type, amount, date, payment_method, reference_no, is_installment, installment_count, installment_number, notes, order_id) 
                                   VALUES (:id, :customer_id, :type, :amount, :date, :payment_method, :reference_no, :is_installment, :installment_count, :installment_number, :notes, :order_id)");
                        
                        $db->bind(':id', $row['id']);
                        $db->bind(':customer_id', $row['customer_id']);
                        $db->bind(':type', $row['type']);
                        $db->bind(':amount', $amount);
                        $db->bind(':date', date('Y-m-d', strtotime($row['date'])));
                        $db->bind(':payment_method', $paymentMethod);
                        $db->bind(':reference_no', $row['reference_no'] ?? null);
                        $db->bind(':is_installment', isset($row['is_installment']) ? (int)$row['is_installment'] : 0);
                        $db->bind(':installment_count', isset($row['installment_count']) && is_numeric($row['installment_count']) ? (int)$row['installment_count'] : null);
                        $db->bind(':installment_number', isset($row['installment_number']) && is_numeric($row['installment_number']) ? (int)$row['installment_number'] : null);
                        $db->bind(':notes', $row['notes'] ?? null);
                        $db->bind(':order_id', isset($row['order_id']) && is_numeric($row['order_id']) ? (int)$row['order_id'] : null);
                        $db->execute();
                        
                        $transactionId = $db->lastInsertId();
                    } catch (Exception $e) {
                        // If id insertion fails, insert without id
                        $db->query("INSERT INTO transactions (customer_id, type, amount, date, payment_method, reference_no, is_installment, installment_count, installment_number, notes, order_id) 
                                   VALUES (:customer_id, :type, :amount, :date, :payment_method, :reference_no, :is_installment, :installment_count, :installment_number, :notes, :order_id)");
                        
                        $db->bind(':customer_id', $row['customer_id']);
                        $db->bind(':type', $row['type']);
                        $db->bind(':amount', $amount);
                        $db->bind(':date', date('Y-m-d', strtotime($row['date'])));
                        $db->bind(':payment_method', $paymentMethod);
                        $db->bind(':reference_no', $row['reference_no'] ?? null);
                        $db->bind(':is_installment', isset($row['is_installment']) ? (int)$row['is_installment'] : 0);
                        $db->bind(':installment_count', isset($row['installment_count']) && is_numeric($row['installment_count']) ? (int)$row['installment_count'] : null);
                        $db->bind(':installment_number', isset($row['installment_number']) && is_numeric($row['installment_number']) ? (int)$row['installment_number'] : null);
                        $db->bind(':notes', $row['notes'] ?? null);
                        $db->bind(':order_id', isset($row['order_id']) && is_numeric($row['order_id']) ? (int)$row['order_id'] : null);
                        $db->execute();
                        
                        $transactionId = $db->lastInsertId();
                    }
                } else {
                    // Insert without id
                    $db->query("INSERT INTO transactions (customer_id, type, amount, date, payment_method, reference_no, is_installment, installment_count, installment_number, notes, order_id) 
                               VALUES (:customer_id, :type, :amount, :date, :payment_method, :reference_no, :is_installment, :installment_count, :installment_number, :notes, :order_id)");
                    
                    $db->bind(':customer_id', $row['customer_id']);
                    $db->bind(':type', $row['type']);
                    $db->bind(':amount', $amount);
                    $db->bind(':date', date('Y-m-d', strtotime($row['date'])));
                    $db->bind(':payment_method', $paymentMethod);
                    $db->bind(':reference_no', $row['reference_no'] ?? null);
                    $db->bind(':is_installment', isset($row['is_installment']) ? (int)$row['is_installment'] : 0);
                    $db->bind(':installment_count', isset($row['installment_count']) && is_numeric($row['installment_count']) ? (int)$row['installment_count'] : null);
                    $db->bind(':installment_number', isset($row['installment_number']) && is_numeric($row['installment_number']) ? (int)$row['installment_number'] : null);
                    $db->bind(':notes', $row['notes'] ?? null);
                    $db->bind(':order_id', isset($row['order_id']) && is_numeric($row['order_id']) ? (int)$row['order_id'] : null);
                    $db->execute();
                    
                    $transactionId = $db->lastInsertId();
                }
            }
            
            $success++;
        }
        
        // Commit transaction
        $db->endTransaction();
        
        return [
            'success' => $success,
            'errors' => $errors,
            'error_messages' => $errorMessages
        ];
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->cancelTransaction();
        throw $e;
    }
}

// Function to read import file (CSV or Excel)
function readImportFile($filePath, $fileExt) {
    $data = [];
    
    if ($fileExt == 'csv') {
        // Read CSV file
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 0, ",");
            
            // Normalize headers (lowercase, trim)
            $headers = array_map(function($header) {
                return strtolower(trim($header));
            }, $headers);
            
            while (($row = fgetcsv($handle, 0, ",")) !== FALSE) {
                $rowData = [];
                foreach ($headers as $i => $header) {
                    $rowData[$header] = isset($row[$i]) ? $row[$i] : '';
                }
                $data[] = $rowData;
            }
            fclose($handle);
        }
    } elseif ($fileExt == 'xlsx') {
        // Read Excel file
        require_once VENDOR_PATH . 'phpoffice/phpspreadsheet/src/PhpSpreadsheet/IOFactory.php';
        
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        if (count($rows) > 0) {
            $headers = $rows[0];
            
            // Normalize headers (lowercase, trim)
            $headers = array_map(function($header) {
                return strtolower(trim($header));
            }, $headers);
            
            for ($i = 1; $i < count($rows); $i++) {
                $rowData = [];
                foreach ($headers as $j => $header) {
                    $rowData[$header] = isset($rows[$i][$j]) ? $rows[$i][$j] : '';
                }
                $data[] = $rowData;
            }
        }
    }
    
    return $data;
}

// Function to get products for export
function getProductsForExport() {
    $db = Database::getInstance();
    
    $db->query("SELECT p.*, c.name as category_name FROM products p 
               JOIN categories c ON p.category_id = c.id 
               ORDER BY p.id ASC");
    
    $products = $db->resultSet();
    
    // Get stock levels
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
    
    return $products;
}

// Function to get customers for export
function getCustomersForExport() {
    $db = Database::getInstance();
    
    $db->query("SELECT * FROM customers ORDER BY id ASC");
    return $db->resultSet();
}

// Function to get stock movements for export
function getStockForExport() {
    $db = Database::getInstance();
    
    $db->query("SELECT sm.*, p.name as product_name 
               FROM stock_movements sm 
               JOIN products p ON sm.product_id = p.id 
               ORDER BY sm.date DESC, sm.id DESC");
    
    return $db->resultSet();
}

// Function to get categories for export
function getCategoriesForExport() {
    $db = Database::getInstance();
    
    $db->query("SELECT * FROM categories ORDER BY id ASC");
    return $db->resultSet();
}

// Function to get orders for export
function getOrdersForExport() {
    $db = Database::getInstance();
    
    $db->query("SELECT o.*, 
               c.name as customer_name, c.surname as customer_surname, 
               (SELECT SUM(oi.quantity * oi.price) FROM order_items oi WHERE oi.order_id = o.id) as total_amount 
               FROM orders o 
               JOIN customers c ON o.customer_id = c.id 
               ORDER BY o.order_date DESC, o.id DESC");
    
    return $db->resultSet();
}

// Function to get transactions for export
function getTransactionsForExport() {
    $db = Database::getInstance();
    
    $db->query("SELECT t.*, 
               c.name as customer_name, c.surname as customer_surname 
               FROM transactions t 
               LEFT JOIN customers c ON t.customer_id = c.id 
               ORDER BY t.date DESC, t.id DESC");
    
    return $db->resultSet();
}

// Function to export data as CSV
function exportAsCSV($data, $filename) {
    if (empty($data)) {
        throw new Exception('Dışa aktarılacak veri bulunamadı.');
    }
    
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Create a file pointer
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Get headers from first row
    $headers = array_keys($data[0]);
    
    // Output the column headings
    fputcsv($output, $headers);
    
    // Output each row of the data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

// Function to export data as Excel
function exportAsExcel($data, $filename) {
    if (empty($data)) {
        throw new Exception('Dışa aktarılacak veri bulunamadı.');
    }
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Create Excel content
    echo '<table border="1">';
    
    // Add headers
    echo '<tr>';
    foreach (array_keys($data[0]) as $header) {
        echo '<th>' . $header . '</th>';
    }
    echo '</tr>';
    
    // Add data
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $value) {
            echo '<td>' . $value . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('import_export_title', 'İçe/Dışa Aktarım'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=tools'); ?>"><?php echo t('tools_title', 'Araçlar'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('import_export_title', 'İçe/Dışa Aktarım'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Display Message -->
<?php if ($message): ?>
<div class="alert alert-<?php echo $status; ?> alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Import/Export Tabs -->
<div class="card">
    <div class="card-body">
        <ul class="nav nav-tabs nav-tabs-solid nav-justified" id="importExportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="import-tab" data-bs-toggle="tab" data-bs-target="#import" type="button" role="tab" aria-controls="import" aria-selected="true">
                    <i class="fas fa-file-import me-2"></i> <?php echo t('import_export_import', 'İçe Aktar'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button" role="tab" aria-controls="export" aria-selected="false">
                    <i class="fas fa-file-export me-2"></i> <?php echo t('import_export_export', 'Dışa Aktar'); ?>
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="importExportTabContent">
            <!-- Import Tab -->
            <div class="tab-pane fade show active" id="import" role="tabpanel" aria-labelledby="import-tab">
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><?php echo t('import_export_data_import', 'Veri İçe Aktarma'); ?></h5>
                            </div>
                            <div class="card-body">
                                <form action="<?php echo url('index.php?module=tools&action=import-export'); ?>" method="post" enctype="multipart/form-data">
                                    <?php echo csrfField(); ?>
                                    
                                    <div class="mb-3">
                                        <label for="import_type" class="form-label required"><?php echo t('import_export_import_type', 'İçe Aktarılacak Veri Türü'); ?></label>
                                        <select class="form-select" id="import_type" name="import_type" required>
                                            <option value=""><?php echo t('import_export_select', 'Seçiniz'); ?></option>
                                            <option value="products"><?php echo t('import_export_products', 'Ürünler'); ?></option>
                                            <option value="customers"><?php echo t('import_export_customers', 'Müşteriler'); ?></option>
                                            <option value="stock"><?php echo t('import_export_stock', 'Stok Hareketleri'); ?></option>
                                            <option value="categories"><?php echo t('import_export_categories', 'Kategoriler'); ?></option>
                                            <option value="orders"><?php echo t('import_export_orders', 'Siparişler'); ?></option>
                                            <option value="transactions"><?php echo t('import_export_transactions', 'Mali İşlemler'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="import_file" class="form-label required"><?php echo t('import_export_import_file', 'İçe Aktarılacak Dosya'); ?></label>
                                        <input type="file" class="form-control" id="import_file" name="import_file" required accept=".csv, .xlsx">
                                        <div class="form-text"><?php echo t('import_export_file_formats', 'Sadece CSV ve Excel (XLSX) dosyaları desteklenmektedir.'); ?></div>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong><?php echo t('import_export_warning', 'Uyarı:'); ?></strong> <?php echo t('import_export_warning_text', 'İçe aktarma sırasında var olan kayıtlar güncellenecektir. Lütfen önce veritabanı yedeği alın.'); ?>
                                    </div>
                                    
                                    <button type="submit" name="import_data" class="btn btn-primary">
                                        <i class="fas fa-file-import me-1"></i> <?php echo t('import_export_import_button', 'İçe Aktar'); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <?php if ($importResult): ?>
                        <div class="card border mt-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0"><?php echo t('import_export_import_results', 'İçe Aktarma Sonuçları'); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-<?php echo $importResult['errors'] > 0 ? 'warning' : 'success'; ?>">
                                    <strong><?php echo t('import_export_total_records', 'Toplam Kayıt:'); ?></strong> <?php echo $importResult['success'] + $importResult['errors']; ?><br>
                                    <strong><?php echo t('import_export_success', 'Başarılı:'); ?></strong> <?php echo $importResult['success']; ?><br>
                                    <strong><?php echo t('import_export_errors', 'Hata:'); ?></strong> <?php echo $importResult['errors']; ?>
                                </div>
                                
                                <?php if ($importResult['errors'] > 0 && !empty($importResult['error_messages'])): ?>
                                <div class="mt-3">
                                    <h6><?php echo t('import_export_error_details', 'Hata Detayları'); ?></h6>
                                    <ul class="mb-0">
                                        <?php foreach ($importResult['error_messages'] as $error): ?>
                                        <li><?php echo e($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0"><?php echo t('import_export_templates', 'İçe Aktarma Şablonları'); ?></h5>
                            </div>
                            <div class="card-body">
                                <p><?php echo t('import_export_templates_desc', 'İçe aktarma işlemi için aşağıdaki şablon dosyalarını kullanabilirsiniz:'); ?></p>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th><?php echo t('import_export_data_type', 'Veri Türü'); ?></th>
                                                <th><?php echo t('import_export_templates_label', 'Şablonlar'); ?></th>
                                                <th><?php echo t('import_export_required_fields', 'Zorunlu Alanlar'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php echo t('import_export_products', 'Ürünler'); ?></td>
                                                <td>
                                                    <a href="<?php echo url('assets/templates/products_template.xlsx'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-excel"></i> Excel
                                                    </a>
                                                    <a href="<?php echo url('assets/templates/products_template.csv'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-csv"></i> CSV
                                                    </a>
                                                </td>
                                                <td><?php echo t('import_export_required_fields_products', 'name, category_id, price'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_customers', 'Müşteriler'); ?></td>
                                                <td>
                                                    <a href="<?php echo url('assets/templates/customers_template.xlsx'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-excel"></i> Excel
                                                    </a>
                                                    <a href="<?php echo url('assets/templates/customers_template.csv'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-csv"></i> CSV
                                                    </a>
                                                </td>
                                                <td><?php echo t('import_export_required_fields_customers', 'name, surname, phone'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_stock', 'Stok Hareketleri'); ?></td>
                                                <td>
                                                    <a href="<?php echo url('assets/templates/stock_template.xlsx'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-excel"></i> Excel
                                                    </a>
                                                    <a href="<?php echo url('assets/templates/stock_template.csv'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-csv"></i> CSV
                                                    </a>
                                                </td>
                                                <td><?php echo t('import_export_required_fields_stock', 'product_id, quantity, type, date'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_categories', 'Kategoriler'); ?></td>
                                                <td>
                                                    <a href="<?php echo url('assets/templates/categories_template.xlsx'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-excel"></i> Excel
                                                    </a>
                                                    <a href="<?php echo url('assets/templates/categories_template.csv'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-csv"></i> CSV
                                                    </a>
                                                </td>
                                                <td><?php echo t('import_export_required_fields_categories', 'name'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_orders', 'Siparişler'); ?></td>
                                                <td>
                                                    <a href="<?php echo url('index.php?module=tools&action=import-export&download_template=orders&file_type=xlsx'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-excel"></i> Excel
                                                    </a>
                                                    <a href="<?php echo url('index.php?module=tools&action=import-export&download_template=orders&file_type=csv'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-csv"></i> CSV
                                                    </a>
                                                </td>
                                                <td><?php echo t('import_export_required_fields_orders', 'customer_id, order_date'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_transactions', 'Mali İşlemler'); ?></td>
                                                <td>
                                                    <a href="<?php echo url('index.php?module=tools&action=import-export&download_template=transactions&file_type=xlsx'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-excel"></i> Excel
                                                    </a>
                                                    <a href="<?php echo url('index.php?module=tools&action=import-export&download_template=transactions&file_type=csv'); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-csv"></i> CSV
                                                    </a>
                                                </td>
                                                <td><?php echo t('import_export_required_fields_transactions', 'customer_id, type, amount, date'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong><?php echo t('import_export_tip', 'İpucu:'); ?></strong> <?php echo t('import_export_tip_text', 'Mevcut verileri önce dışa aktarıp, ardından düzenleyerek tekrar içe aktarabilirsiniz.'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card border mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><?php echo t('import_export_rules', 'İçe Aktarma Kuralları'); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="importRulesAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingProducts">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProducts" aria-expanded="false" aria-controls="collapseProducts">
                                                <?php echo t('import_export_rules_products', 'Ürünler İçin Kurallar'); ?>
                                            </button>
                                        </h2>
                                        <div id="collapseProducts" class="accordion-collapse collapse" aria-labelledby="headingProducts" data-bs-parent="#importRulesAccordion">
                                            <div class="accordion-body">
                                                <ul>
                                                    <li><?php echo t('import_export_rules_products_1', 'Ürün adı ve kategori ID zorunludur.'); ?></li>
                                                    <li><?php echo t('import_export_rules_products_2', 'Kategori ID sistemde var olan bir kategori olmalıdır.'); ?></li>
                                                    <li><?php echo t('import_export_rules_products_3', 'Fiyat sayısal bir değer olmalıdır (ondalık ayırıcı olarak virgül veya nokta kullanılabilir).'); ?></li>
                                                    <li><?php echo t('import_export_rules_products_4', 'Aynı isme sahip ürün varsa, mevcut ürün güncellenecektir.'); ?></li>
                                                    <li><?php echo t('import_export_rules_products_5', 'İsteğe bağlı olarak initial_stock sütunu ile başlangıç stoku eklenebilir.'); ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingCustomers">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCustomers" aria-expanded="false" aria-controls="collapseCustomers">
                                                <?php echo t('import_export_rules_customers', 'Müşteriler İçin Kurallar'); ?>
                                            </button>
                                        </h2>
                                        <div id="collapseCustomers" class="accordion-collapse collapse" aria-labelledby="headingCustomers" data-bs-parent="#importRulesAccordion">
                                            <div class="accordion-body">
                                                <ul>
                                                    <li><?php echo t('import_export_rules_customers_1', 'Ad, soyad ve telefon zorunludur.'); ?></li>
                                                    <li><?php echo t('import_export_rules_customers_2', 'Aynı telefon numarasına sahip müşteri varsa, mevcut müşteri güncellenecektir.'); ?></li>
                                                    <li><?php echo t('import_export_rules_customers_3', 'E-posta, şirket, adres ve notlar isteğe bağlıdır.'); ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingStock">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStock" aria-expanded="false" aria-controls="collapseStock">
                                                <?php echo t('import_export_rules_stock', 'Stok Hareketleri İçin Kurallar'); ?>
                                            </button>
                                        </h2>
                                        <div id="collapseStock" class="accordion-collapse collapse" aria-labelledby="headingStock" data-bs-parent="#importRulesAccordion">
                                            <div class="accordion-body">
                                                <ul>
                                                    <li><?php echo t('import_export_rules_stock_1', 'Ürün ID, miktar, tür ve tarih zorunludur.'); ?></li>
                                                    <li><?php echo t('import_export_rules_stock_2', 'Ürün ID sistemde var olan bir ürün olmalıdır.'); ?></li>
                                                    <li><?php echo t('import_export_rules_stock_3', 'Tür in (giriş) veya out (çıkış) olmalıdır.'); ?></li>
                                                    <li><?php echo t('import_export_rules_stock_4', 'Miktar sıfırdan büyük bir sayı olmalıdır.'); ?></li>
                                                    <li><?php echo t('import_export_rules_stock_5', 'Tarih formatı YYYY-MM-DD olmalıdır.'); ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingCategories">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCategories" aria-expanded="false" aria-controls="collapseCategories">
                                                <?php echo t('import_export_rules_categories', 'Kategoriler İçin Kurallar'); ?>
                                            </button>
                                        </h2>
                                        <div id="collapseCategories" class="accordion-collapse collapse" aria-labelledby="headingCategories" data-bs-parent="#importRulesAccordion">
                                            <div class="accordion-body">
                                                <ul>
                                                    <li><?php echo t('import_export_rules_categories_1', 'Kategori adı zorunludur.'); ?></li>
                                                    <li><?php echo t('import_export_rules_categories_2', 'Aynı isme sahip kategori varsa, mevcut kategori güncellenecektir.'); ?></li>
                                                    <li><?php echo t('import_export_rules_categories_3', 'Açıklama isteğe bağlıdır.'); ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Export Tab -->
            <div class="tab-pane fade" id="export" role="tabpanel" aria-labelledby="export-tab">
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0"><?php echo t('import_export_data_export', 'Veri Dışa Aktarma'); ?></h5>
                            </div>
                            <div class="card-body">
                                <form action="<?php echo url('index.php?module=tools&action=import-export'); ?>" method="post">
                                    <?php echo csrfField(); ?>
                                    
                                    <div class="mb-3">
                                        <label for="export_type" class="form-label required"><?php echo t('import_export_export_type', 'Dışa Aktarılacak Veri Türü'); ?></label>
                                        <select class="form-select" id="export_type" name="export_type" required>
                                            <option value=""><?php echo t('import_export_select', 'Seçiniz'); ?></option>
                                            <option value="products"><?php echo t('import_export_products', 'Ürünler'); ?></option>
                                            <option value="customers"><?php echo t('import_export_customers', 'Müşteriler'); ?></option>
                                            <option value="stock"><?php echo t('import_export_stock', 'Stok Hareketleri'); ?></option>
                                            <option value="categories"><?php echo t('import_export_categories', 'Kategoriler'); ?></option>
                                            <option value="orders"><?php echo t('import_export_orders', 'Siparişler'); ?></option>
                                            <option value="transactions"><?php echo t('import_export_transactions', 'Mali İşlemler'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label required"><?php echo t('import_export_file_format', 'Dosya Formatı'); ?></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="export_format" id="format_csv" value="csv" checked>
                                            <label class="form-check-label" for="format_csv">
                                                <?php echo t('import_export_format_csv', 'CSV Dosyası (.csv)'); ?>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="export_format" id="format_xlsx" value="xlsx">
                                            <label class="form-check-label" for="format_xlsx">
                                                <?php echo t('import_export_format_xlsx', 'Excel Dosyası (.xlsx)'); ?>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="export_data" class="btn btn-success">
                                        <i class="fas fa-file-export me-1"></i> <?php echo t('import_export_export_button', 'Dışa Aktar'); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><?php echo t('import_export_export_info', 'Dışa Aktarma Bilgileri'); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th><?php echo t('import_export_data_type', 'Veri Türü'); ?></th>
                                                <th><?php echo t('import_export_export_data', 'Aktarılacak Veriler'); ?></th>
                                                <th><?php echo t('import_export_record_count', 'Kayıt Sayısı'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get record counts
                                            $db->query("SELECT COUNT(*) as count FROM products");
                                            $productCount = $db->single()['count'];
                                            
                                            $db->query("SELECT COUNT(*) as count FROM customers");
                                            $customerCount = $db->single()['count'];
                                            
                                            $db->query("SELECT COUNT(*) as count FROM stock_movements");
                                            $stockCount = $db->single()['count'];
                                            
                                            $db->query("SELECT COUNT(*) as count FROM categories");
                                            $categoryCount = $db->single()['count'];
                                            
                                            $db->query("SELECT COUNT(*) as count FROM orders");
                                            $orderCount = $db->single()['count'];
                                            
                                            $db->query("SELECT COUNT(*) as count FROM transactions");
                                            $transactionCount = $db->single()['count'];
                                            ?>
                                            <tr>
                                                <td><?php echo t('import_export_products', 'Ürünler'); ?></td>
                                                <td><?php echo t('import_export_products_export_desc', 'Tüm ürün bilgileri, stok seviyeleri ve kategori isimleri'); ?></td>
                                                <td><?php echo $productCount; ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_customers', 'Müşteriler'); ?></td>
                                                <td><?php echo t('import_export_customers_export_desc', 'Tüm müşteri bilgileri'); ?></td>
                                                <td><?php echo $customerCount; ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_stock', 'Stok Hareketleri'); ?></td>
                                                <td><?php echo t('import_export_stock_export_desc', 'Tüm stok hareketleri ve ürün isimleri'); ?></td>
                                                <td><?php echo $stockCount; ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_categories', 'Kategoriler'); ?></td>
                                                <td><?php echo t('import_export_categories_export_desc', 'Tüm kategori bilgileri'); ?></td>
                                                <td><?php echo $categoryCount; ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_orders', 'Siparişler'); ?></td>
                                                <td><?php echo t('import_export_orders_export_desc', 'Tüm sipariş bilgileri ve müşteri isimleri'); ?></td>
                                                <td><?php echo $orderCount; ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php echo t('import_export_transactions', 'Mali İşlemler'); ?></td>
                                                <td><?php echo t('import_export_transactions_export_desc', 'Tüm mali işlem bilgileri ve müşteri isimleri'); ?></td>
                                                <td><?php echo $transactionCount; ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong><?php echo t('import_export_note', 'Not:'); ?></strong> <?php echo t('import_export_note_text', 'Dışa aktarma işlemi sunucu yükünü artırabilir. Büyük veri setleri için işlem uzun sürebilir.'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card border mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><?php echo t('import_export_tips', 'Dışa Aktarma İpuçları'); ?></h5>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li><?php echo t('import_export_tip1', 'Düzenli olarak verilerinizi dışa aktararak yedekleme yapabilirsiniz.'); ?></li>
                                    <li><?php echo t('import_export_tip2', 'Excel formatı (XLSX) daha fazla veri ve biçimlendirme seçeneği sunar, ancak daha büyük dosya boyutuna sahiptir.'); ?></li>
                                    <li><?php echo t('import_export_tip3', 'CSV formatı basittir ve çoğu uygulama tarafından desteklenir.'); ?></li>
                                    <li><?php echo t('import_export_tip4', 'Dışa aktarılan verileri başka sistemlere aktarmak için kullanabilirsiniz.'); ?></li>
                                    <li><?php echo t('import_export_tip5', 'Aktarılan dosyalarda Türkçe karakterler doğru şekilde görüntülenecektir.'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Import/Export Logs -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title"><?php echo t('import_export_recent_logs', 'Son İçe/Dışa Aktarım İşlemleri'); ?></h5>
    </div>
    <div class="card-body">
        <?php
        // Get recent import/export logs
        $db->query("SELECT l.*, u.name as user_name, u.surname as user_surname 
                   FROM import_export_logs l 
                   JOIN users u ON l.user_id = u.id 
                   ORDER BY l.created_at DESC 
                   LIMIT 10");
        $logs = $db->resultSet();
        
        if (!empty($logs)):
        ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo t('import_export_log_date', 'Tarih'); ?></th>
                        <th><?php echo t('import_export_log_operation', 'İşlem Türü'); ?></th>
                        <th><?php echo t('import_export_log_data_type', 'Veri Türü'); ?></th>
                        <th><?php echo t('import_export_log_file', 'Dosya'); ?></th>
                        <th><?php echo t('import_export_record_count', 'Kayıt Sayısı'); ?></th>
                        <th><?php echo t('import_export_log_user', 'Kullanıcı'); ?></th>
                        <th><?php echo t('import_export_log_status', 'Durum'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo formatDateTime($log['created_at']); ?></td>
                        <td>
                            <?php if ($log['operation'] == 'import'): ?>
                            <span class="badge bg-primary"><?php echo t('import_export_log_import', 'İçe Aktarım'); ?></span>
                            <?php else: ?>
                            <span class="badge bg-success"><?php echo t('import_export_log_export', 'Dışa Aktarım'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo ucfirst(e($log['data_type'])); ?></td>
                        <td><?php echo e($log['file_name']); ?></td>
                        <td><?php echo $log['record_count']; ?></td>
                        <td><?php echo e($log['user_name'] . ' ' . $log['user_surname']); ?></td>
                        <td>
                            <?php if ($log['status'] == 'success'): ?>
                            <span class="badge bg-success"><?php echo t('import_export_log_success', 'Başarılı'); ?></span>
                            <?php else: ?>
                            <span class="badge bg-danger"><?php echo t('import_export_log_failed', 'Başarısız'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> <?php echo t('import_export_no_logs', 'Henüz hiç içe/dışa aktarım işlemi yapılmamış.'); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Apply active tab based on URL hash if exists
        const hash = window.location.hash;
        if (hash) {
            $('.nav-tabs a[href="' + hash + '"]').tab('show');
        }
        
        // Update URL hash when tab changes
        $('.nav-tabs a').on('shown.bs.tab', function(e) {
            history.pushState(null, null, e.target.getAttribute('href'));
        });
    });
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>