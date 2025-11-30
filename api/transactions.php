<?php
/**
 * Megabre StokMaster Pro
 * Transactions API
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Başlangıç zamanını kaydet (performans ölçümü için)
$start_time = microtime(true);

// Temel yapılandırma dosyasını dahil et
$config_path = realpath(__DIR__ . '/../config/config.php');
if (!file_exists($config_path)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yapılandırma dosyası bulunamadı. Sistem yöneticinize başvurun.']);
    exit;
}
require_once $config_path;

// Hata raporlama ayarları
error_reporting(E_ALL);
ini_set('display_errors', 0); // Tarayıcıda hataları gösterme
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/api_errors.log');

// JSON içerik türünü ayarla
header('Content-Type: application/json');

// İstek detaylarını günlüğe kaydet
error_log("=== API İstek Detayları ===");
error_log("Tarih ve Saat: " . date('Y-m-d H:i:s'));
error_log("İstek URI: " . $_SERVER['REQUEST_URI']);
error_log("Betik Dosyası: " . $_SERVER['SCRIPT_FILENAME']);
error_log("Belge Kök Dizini: " . $_SERVER['DOCUMENT_ROOT']);
error_log("Geçerli Dizin: " . getcwd());
error_log("İşlem: " . (isset($_GET['action']) ? $_GET['action'] : 'none'));
error_log("GET Parametreleri: " . print_r($_GET, true));

// Gerekli dosyaları kontrol et
$requiredFiles = [
    CORE_PATH . 'Database.php' => 'Veritabanı sınıfı',
    CORE_PATH . 'Session.php' => 'Oturum sınıfı',
    CORE_PATH . 'Authentication.php' => 'Kimlik doğrulama sınıfı',
    CORE_PATH . 'Language.php' => 'Dil sınıfı',
    CORE_PATH . 'helpers.php' => 'Yardımcı fonksiyonlar'
];

foreach ($requiredFiles as $file => $description) {
    if (!file_exists($file)) {
        error_log("Gerekli dosya eksik: $file ($description)");
        echo json_encode(['success' => false, 'message' => "Sistem hatası: Gerekli dosya eksik ($description)"]);
        exit;
    }
}

// Çekirdek dosyaları dahil et
require_once CORE_PATH . 'Database.php';
require_once CORE_PATH . 'Session.php';
require_once CORE_PATH . 'Authentication.php';
require_once CORE_PATH . 'Language.php';
require_once CORE_PATH . 'Cache.php';
require_once CORE_PATH . 'DynamicFields.php';
require_once CORE_PATH . 'helpers.php';

// Kimlik doğrulama başlat
$auth = new Authentication();

// Kullanıcı giriş yapmış mı kontrol et
if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Yetkisiz erişim'], 401);
}

// Veritabanı bağlantısını başlat
$db = Database::getInstance();

// İşlemi al
$action = isset($_GET['action']) ? $_GET['action'] : '';
$subaction = isset($_GET['subaction']) ? $_GET['subaction'] : '';

// Yardımcı fonksiyonlar
/**
 * Ödeme yöntemi adını döndürür
 *
 * @param string $method Ödeme yöntemi kodu
 * @return string Ödeme yöntemi adı
 */
function getPaymentMethodName($method) {
    $methods = [
        'cash' => 'Nakit',
        'check' => 'Çek',
        'promissory' => 'Senet',
        'credit_card' => 'Kredi Kartı',
        'bank_transfer' => 'Havale / EFT'
    ];
    return isset($methods[$method]) ? $methods[$method] : $method;
}

/**
 * İşlem için eylem butonlarını oluşturur
 *
 * @param int $id İşlem ID
 * @return string HTML butonları
 */
function getTransactionActions($id) {
    return '<div class="btn-group">
        <a href="' . url('index.php?module=transactions&action=edit&id=' . $id) . '" class="btn btn-sm btn-primary">
            <i class="fas fa-edit"></i>
        </a>
        <button type="button" class="btn btn-sm btn-danger delete-transaction" data-id="' . $id . '">
            <i class="fas fa-trash"></i>
        </button>
    </div>';
}

/**
 * Müşteri bakiyesini hesaplar
 *
 * @param int $customerId Müşteri ID
 * @return array Bakiye bilgileri (total_payments, total_debts, balance)
 */
function getCustomerBalance($customerId) {
    $db = Database::getInstance();
    
    // Toplam ödemeleri al
    $db->query("SELECT COALESCE(SUM(amount), 0) as total_payments FROM transactions 
                WHERE customer_id = :customer_id AND type = 'payment'");
    $db->bind(':customer_id', $customerId);
    $totalPayments = $db->single()['total_payments'];
    
    // Toplam borçları al
    $db->query("SELECT COALESCE(SUM(amount), 0) as total_debts FROM transactions 
                WHERE customer_id = :customer_id AND type = 'debt'");
    $db->bind(':customer_id', $customerId);
    $totalDebts = $db->single()['total_debts'];
    
    // Bakiyeyi hesapla
    $balance = $totalPayments - $totalDebts;
    
    return [
        'total_payments' => $totalPayments,
        'total_debts' => $totalDebts,
        'balance' => $balance
    ];
}

// İşlemi işle
if ($action === 'transactions' || $action === '') {
    switch ($subaction) {
        case 'get_all':
            // DataTables parametrelerini al
            $draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
            $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
            $length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
            $search = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
            
            // Filtre parametrelerini al
            $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
            $transactionType = isset($_GET['type']) ? $_GET['type'] : '';
            $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
            $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
            
            // Sorgu oluştur
            $query = "SELECT t.*, c.first_name as customer_name, c.last_name as customer_surname, 
                      DATE_FORMAT(t.date, '%d.%m.%Y') as formatted_date 
                      FROM transactions t 
                      JOIN customers c ON t.customer_id = c.id
                      WHERE 1=1";
            
            $countQuery = "SELECT COUNT(*) as total FROM transactions t JOIN customers c ON t.customer_id = c.id WHERE 1=1";
            
            $params = [];
            
            // Arama koşulunu ekle
            if (!empty($search)) {
                $query .= " AND (t.id LIKE :search OR c.first_name LIKE :search OR c.last_name LIKE :search OR t.reference_no LIKE :search)";
                $countQuery .= " AND (t.id LIKE :search OR c.first_name LIKE :search OR c.last_name LIKE :search OR t.reference_no LIKE :search)";
                $params[':search'] = "%$search%";
            }
            
            // Müşteri filtresini ekle
            if ($customerId > 0) {
                $query .= " AND t.customer_id = :customer_id";
                $countQuery .= " AND t.customer_id = :customer_id";
                $params[':customer_id'] = $customerId;
            }
            
            // İşlem türü filtresini ekle
            if (!empty($transactionType)) {
                $query .= " AND t.type = :type";
                $countQuery .= " AND t.type = :type";
                $params[':type'] = $transactionType;
            }
            
            // Tarih aralığı filtresini ekle
            if (!empty($startDate)) {
                $query .= " AND t.date >= :start_date";
                $countQuery .= " AND t.date >= :start_date";
                $params[':start_date'] = $startDate;
            }
            
            if (!empty($endDate)) {
                $query .= " AND t.date <= :end_date";
                $countQuery .= " AND t.date <= :end_date";
                $params[':end_date'] = $endDate;
            }
            
            // Toplam kayıt sayısını al
            $db->query($countQuery);
            foreach ($params as $key => $value) {
                $db->bind($key, $value);
            }
            $totalRecords = $db->single()['total'];
            
            // Sıralama ve sayfalama ekle
            $query .= " ORDER BY t.id DESC LIMIT :start, :length";
            $params[':start'] = $start;
            $params[':length'] = $length;
            
            // Sorguyu çalıştır
            $db->query($query);
            foreach ($params as $key => $value) {
                $db->bind($key, $value);
            }
            
            // İşlemleri al
            $transactions = $db->resultSet();
            
            // DataTables için verileri biçimlendir
            $formattedData = [];
            foreach ($transactions as $transaction) {
                $formattedData[] = [
                    'id' => $transaction['id'],
                    'customer' => $transaction['customer_name'] . ' ' . $transaction['customer_surname'],
                    'type' => $transaction['type'] == 'payment' ? 'Ödeme' : 'Borç',
                    'amount' => formatPrice($transaction['amount']) . ' ₺',
                    'date' => $transaction['formatted_date'],
                    'payment_method' => getPaymentMethodName($transaction['payment_method']),
                    'reference_no' => $transaction['reference_no'],
                    'notes' => $transaction['notes'],
                    'actions' => getTransactionActions($transaction['id'])
                ];
            }
            
            // Yanıt hazırla
            $response = [
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $formattedData
            ];
            
            // Yanıt gönder
            echo json_encode($response);
            break;
            
        case 'get':
            // Tek bir işlemi al
            $transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($transactionId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Geçersiz işlem ID\'si'], 400);
            }
            
            // İşlem verilerini al
            $db->query("SELECT t.*, c.first_name as customer_name, c.last_name as customer_surname, 
                       c.phone as customer_phone, c.email as customer_email, c.company as customer_company,
                       DATE_FORMAT(t.date, '%d.%m.%Y') as formatted_date
                       FROM transactions t 
                       JOIN customers c ON t.customer_id = c.id 
                       WHERE t.id = :id");
            $db->bind(':id', $transactionId);
            $transaction = $db->single();
            
            if (!$transaction) {
                jsonResponse(['success' => false, 'message' => 'İşlem bulunamadı'], 404);
            }
            
            // Müşteri bakiyesini al
            $customerId = $transaction['customer_id'];
            $balance = getCustomerBalance($customerId);
            
            // Bakiye bilgilerini işleme ekle
            $transaction['customer_total_payment'] = $balance['total_payments'];
            $transaction['customer_total_debt'] = $balance['total_debts'];
            $transaction['customer_balance'] = $balance['balance'];
            
            jsonResponse(['success' => true, 'transaction' => $transaction]);
            break;
            
        case 'create':
            // Yeni işlem oluştur
            if (!isPost()) {
                jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
            }
            
            // Form verilerini al
            $customerId = post('customer_id');
            $type = post('type');
            $amount = post('amount');
            $transactionDate = post('transaction_date');
            $paymentMethod = post('payment_method');
            $referenceNo = post('reference_no');
            $isInstallment = isset($_POST['is_installment']) ? 1 : 0;
            $installmentCount = post('installment_count', 1);
            $notes = post('notes');
            
            // Verileri doğrula
            $errors = [];
            
            if (empty($customerId)) {
                $errors[] = 'Müşteri seçimi gereklidir.';
            }
            
            if (empty($type) || !in_array($type, ['payment', 'debt'])) {
                $errors[] = 'Geçersiz işlem türü.';
            }
            
            if (empty($amount) || !is_numeric(str_replace(',', '.', $amount)) || floatval(str_replace(',', '.', $amount)) <= 0) {
                $errors[] = 'Geçerli bir tutar giriniz.';
            }
            
            if (empty($transactionDate)) {
                $errors[] = 'İşlem tarihi gereklidir.';
            }
            
            if (empty($paymentMethod)) {
                $errors[] = 'Ödeme yöntemi gereklidir.';
            }
            
            if ($isInstallment && ($installmentCount < 2 || !is_numeric($installmentCount) || $installmentCount > 60)) {
                $errors[] = 'Geçerli bir taksit sayısı giriniz (2-60 arası).';
            }
            
            if (!empty($errors)) {
                jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
            }
            
            // Tutarı biçimlendir
            $amount = floatval(str_replace(',', '.', $amount));
            
            // İşlem başlat
            $db->beginTransaction();
            
            try {
                if ($isInstallment) {
                    // Taksit tutarını hesapla (2 ondalık basamağa yuvarla)
                    $installmentAmount = round($amount / $installmentCount, 2);
                    
                    // Son taksiti hesapla (yuvarlama farklarını hesaba katmak için)
                    $lastInstallmentAmount = $amount - ($installmentAmount * ($installmentCount - 1));
                    
                    // Taksit tarihlerini oluşturmak için işlem tarihini ayrıştır
                    $baseDate = new DateTime($transactionDate);
                    
                    // Taksitleri oluştur
                    for ($i = 0; $i < $installmentCount; $i++) {
                        // Taksit tarihini ayarla (geçerli ay + i)
                        $installmentDate = clone $baseDate;
                        $installmentDate->modify("+$i month");
                        
                        // Taksit tutarını ayarla (son taksit, yuvarlama nedeniyle farklı olabilir)
                        $currentAmount = ($i == $installmentCount - 1) ? $lastInstallmentAmount : $installmentAmount;
                        
                        // Taksit notunu oluştur
                        $installmentNote = $notes . " (Taksit " . ($i + 1) . "/" . $installmentCount . ")";
                        
                        // Taksiti ekle
                        $db->query("INSERT INTO transactions (
                                    customer_id, type, amount, date, payment_method, 
                                    reference_no, is_installment, installment_number, installment_count, notes
                                ) VALUES (
                                    :customer_id, :type, :amount, :date, :payment_method, 
                                    :reference_no, 1, :installment_number, :installment_count, :notes
                                )");
                        
                        $db->bind(':customer_id', $customerId);
                        $db->bind(':type', $type);
                        $db->bind(':amount', $currentAmount);
                        $db->bind(':date', $installmentDate->format('Y-m-d'));
                        $db->bind(':payment_method', $paymentMethod);
                        $db->bind(':reference_no', $referenceNo);
                        $db->bind(':installment_number', $i + 1);
                        $db->bind(':installment_count', $installmentCount);
                        $db->bind(':notes', $installmentNote);
                        $db->execute();
                    }
                    
                    // İlk taksit ID'sini al
                    $firstInstallmentId = $db->lastInsertId() - $installmentCount + 1;
                    
                    // İşlemi sonlandır
                    $db->endTransaction();
                    
                    jsonResponse([
                        'success' => true, 
                        'message' => $installmentCount . ' taksitli işlem başarıyla eklendi.', 
                        'transaction_id' => $firstInstallmentId,
                        'is_installment' => true,
                        'installment_count' => $installmentCount
                    ]);
                } else {
                    // Tek işlem ekle
                    $db->query("INSERT INTO transactions (
                                customer_id, type, amount, date, payment_method, 
                                reference_no, is_installment, installment_number, installment_count, notes
                            ) VALUES (
                                :customer_id, :type, :amount, :date, :payment_method, 
                                :reference_no, 0, 0, 0, :notes
                            )");
                    
                    $db->bind(':customer_id', $customerId);
                    $db->bind(':type', $type);
                    $db->bind(':amount', $amount);
                    $db->bind(':date', $transactionDate);
                    $db->bind(':payment_method', $paymentMethod);
                    $db->bind(':reference_no', $referenceNo);
                    $db->bind(':notes', $notes);
                    $db->execute();
                    
                    $transactionId = $db->lastInsertId();
                    
                    // İşlemi sonlandır
                    $db->endTransaction();
                    
                    jsonResponse([
                        'success' => true, 
                        'message' => 'İşlem başarıyla eklendi.', 
                        'transaction_id' => $transactionId,
                        'is_installment' => false
                    ]);
                }
                
            } catch (PDOException $e) {
                // Hata durumunda işlemi geri al
                $db->cancelTransaction();
                
                jsonResponse(['success' => false, 'message' => 'İşlem eklenirken bir hata oluştu: ' . $e->getMessage()], 500);
            }
            break;
            
        case 'update':
            // İşlemi güncelle
            if (!isPost()) {
                jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
            }
            
            $transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($transactionId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Geçersiz işlem ID\'si'], 400);
            }
            
            // İşlemin var olup olmadığını kontrol et
            $db->query("SELECT * FROM transactions WHERE id = :id");
            $db->bind(':id', $transactionId);
            $transaction = $db->single();
            
            if (!$transaction) {
                jsonResponse(['success' => false, 'message' => 'İşlem bulunamadı'], 404);
            }
            
            // Form verilerini al
            $customerId = post('customer_id');
            $amount = post('amount');
            $transactionDate = post('transaction_date');
            $paymentMethod = post('payment_method');
            $referenceNo = post('reference_no');
            $notes = post('notes');
            
            // Verileri doğrula
            $errors = [];
            
            if (empty($customerId)) {
                $errors[] = 'Müşteri seçimi gereklidir.';
            }
            
            if (empty($amount) || !is_numeric(str_replace(',', '.', $amount)) || floatval(str_replace(',', '.', $amount)) <= 0) {
                $errors[] = 'Geçerli bir tutar giriniz.';
            }
            
            if (empty($transactionDate)) {
                $errors[] = 'İşlem tarihi gereklidir.';
            }
            
            if (empty($paymentMethod)) {
                $errors[] = 'Ödeme yöntemi gereklidir.';
            }
            
            if (!empty($errors)) {
                jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
            }
            
            // Tutarı biçimlendir
            $amount = floatval(str_replace(',', '.', $amount));
            
            // İşlem başlat
            $db->beginTransaction();
            
            try {
                // İşlemi güncelle
                $db->query("UPDATE transactions SET 
                            customer_id = :customer_id, 
                            amount = :amount, 
                            date = :date, 
                            payment_method = :payment_method, 
                            reference_no = :reference_no, 
                            notes = :notes, 
                            updated_at = NOW() 
                            WHERE id = :id");
                
                $db->bind(':customer_id', $customerId);
                $db->bind(':amount', $amount);
                $db->bind(':date', $transactionDate);
                $db->bind(':payment_method', $paymentMethod);
                $db->bind(':reference_no', $referenceNo);
                $db->bind(':notes', $notes);
                $db->bind(':id', $transactionId);
                $db->execute();
                
                // İşlemi sonlandır
                $db->endTransaction();
                
                jsonResponse(['success' => true, 'message' => 'İşlem başarıyla güncellendi.']);
                
            } catch (PDOException $e) {
                // Hata durumunda işlemi geri al
                $db->cancelTransaction();
                
                jsonResponse(['success' => false, 'message' => 'İşlem güncellenirken bir hata oluştu: ' . $e->getMessage()], 500);
            }
            break;
            
        case 'delete':
            // İşlemi sil
            if (!isPost() && !isset($_POST['_method']) && $_POST['_method'] !== 'DELETE') {
                jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
            }
            
            $transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($transactionId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Geçersiz işlem ID\'si'], 400);
            }
            
            // İşlemin var olup olmadığını kontrol et
            $db->query("SELECT * FROM transactions WHERE id = :id");
            $db->bind(':id', $transactionId);
            $transaction = $db->single();
            
            if (!$transaction) {
                jsonResponse(['success' => false, 'message' => 'İşlem bulunamadı'], 404);
            }
            
            // İşlem başlat
            $db->beginTransaction();
            
            try {
                // İşlemi sil
                $db->query("DELETE FROM transactions WHERE id = :id");
                $db->bind(':id', $transactionId);
                $db->execute();
                
                // İşlemi sonlandır
                $db->endTransaction();
                
                jsonResponse(['success' => true, 'message' => 'İşlem başarıyla silindi.']);
                
            } catch (PDOException $e) {
                // Hata durumunda işlemi geri al
                $db->cancelTransaction();
                
                jsonResponse(['success' => false, 'message' => 'İşlem silinirken bir hata oluştu: ' . $e->getMessage()], 500);
            }
            break;
            
        case 'get_customer_balance':
            // Müşteri ID'sini al
            $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
            
            if ($customerId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Geçersiz müşteri ID\'si'], 400);
            }
            
            // Müşteri bakiyesini hesapla
            $balance = getCustomerBalance($customerId);
            
            // Yanıt gönder
            jsonResponse([
                'success' => true,
                'balance' => $balance
            ]);
            break;
            
        case 'get_customer_transactions':
            // Müşteri işlemlerini al
            $customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
            
            if ($customerId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Geçersiz müşteri ID\'si'], 400);
            }
            
            // Müşterinin var olup olmadığını kontrol et
            $db->query("SELECT * FROM customers WHERE id = :id");
            $db->bind(':id', $customerId);
            $customer = $db->single();
            
            if (!$customer) {
                jsonResponse(['success' => false, 'message' => 'Müşteri bulunamadı'], 404);
            }
            
            // Müşteri işlemlerini al
            $query = "SELECT t.*, DATE_FORMAT(t.date, '%d.%m.%Y') as formatted_date 
                      FROM transactions t 
                      WHERE t.customer_id = :customer_id 
                      ORDER BY t.date DESC, t.id DESC";
            
            if ($limit > 0) {
                $query .= " LIMIT :limit";
            }
            
            $db->query($query);
            $db->bind(':customer_id', $customerId);
            
            if ($limit > 0) {
                $db->bind(':limit', $limit);
            }
            
            $transactions = $db->resultSet();
            
            // Müşteri bakiyesini hesapla
            $balance = getCustomerBalance($customerId);
            
            // Yanıt gönder
            jsonResponse([
                'success' => true, 
                'transactions' => $transactions,
                'balance' => $balance
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Geçersiz alt işlem'], 400);
            break;
    }
} else {
    jsonResponse(['success' => false, 'message' => 'Geçersiz işlem'], 400);
}

// İşlem süresini hesapla
$execution_time = microtime(true) - $start_time;
error_log("İşlem süresi: " . round($execution_time * 1000, 2) . " ms");