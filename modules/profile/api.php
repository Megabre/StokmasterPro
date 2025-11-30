<?php
/**
 * Megabre StokMaster Pro
 * Profile API
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Include configuration
require_once __DIR__ . '/../../config/config.php';

// Include core files
require_once CORE_PATH . 'Database.php';
require_once CORE_PATH . 'Session.php';
require_once CORE_PATH . 'Authentication.php';
require_once CORE_PATH . 'helpers.php';

// Start session
Session::start();

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

// Debug: Log request
error_log('Profile API Request - Action: ' . $action);
error_log('Profile API Request - Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Profile API Request - POST data: ' . print_r($_POST, true));

// Process action
switch ($action) {
    case 'update':
        // Update profile
        if (!isPost()) {
            error_log('Profile API - Invalid method: ' . $_SERVER['REQUEST_METHOD']);
            jsonResponse(['success' => false, 'message' => 'Geçersiz istek metodu'], 400);
        }
        
        // Validate CSRF token
        $csrfToken = post('csrf_token');
        error_log('Profile API - CSRF token from POST: ' . ($csrfToken ? 'exists' : 'missing'));
        error_log('Profile API - Session CSRF token: ' . (Session::getCsrfToken() ? 'exists' : 'missing'));
        
        if (!validateCsrf()) {
            error_log('Profile API - Invalid CSRF token');
            error_log('Profile API - POST CSRF: ' . ($csrfToken ?? 'N/A'));
            error_log('Profile API - Session CSRF: ' . (Session::getCsrfToken() ?? 'N/A'));
            jsonResponse(['success' => false, 'message' => 'Geçersiz CSRF token'], 400);
        }
        
        // Get user ID
        $userId = $auth->getUserId();
        
        // Get user data
        $db->query("SELECT * FROM users WHERE id = :id");
        $db->bind(':id', $userId);
        $user = $db->single();
        
        if (!$user) {
            jsonResponse(['success' => false, 'message' => 'Kullanıcı bulunamadı'], 404);
        }
        
        // Get form data
        $name = post('name');
        $surname = post('surname');
        $email = post('email');
        $userLanguage = post('language', 'tr');
        
        error_log('Profile API - Form data received:');
        error_log('  Name: ' . ($name ?? 'N/A'));
        error_log('  Surname: ' . ($surname ?? 'N/A'));
        error_log('  Email: ' . ($email ?? 'N/A'));
        error_log('  Language: ' . ($userLanguage ?? 'N/A'));
        
        // Initialize language for translations
        require_once CORE_PATH . 'Language.php';
        $lang = Language::getInstance();
        // Make translations available globally for t() function
        $GLOBALS['L'] = $lang->getAll();
        
        // Validate form data
        $errors = [];
        
        if (empty($name)) {
            $errors[] = t('validation_required', 'Bu alan zorunludur.') . ' (' . t('name', 'Ad') . ')';
        }
        
        if (empty($surname)) {
            $errors[] = t('validation_required', 'Bu alan zorunludur.') . ' (' . t('surname', 'Soyad') . ')';
        }
        
        if (empty($email)) {
            $errors[] = t('validation_required', 'Bu alan zorunludur.') . ' (' . t('email', 'E-posta') . ')';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('validation_email', 'Geçerli bir e-posta adresi giriniz.');
        } else {
            // Check if email exists for other users
            $db->query("SELECT COUNT(*) as count FROM users WHERE email = :email AND id != :id");
            $db->bind(':email', $email);
            $db->bind(':id', $userId);
            $result = $db->single();
            
            if ($result['count'] > 0) {
                $errors[] = t('validation_unique', 'Bu değer zaten kullanılmaktadır.') . ' (' . t('email', 'E-posta') . ')';
            }
        }
        
        // Handle profile image upload
        $profileImage = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 500 * 1024; // 500KB
            
            if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
                $errors[] = 'Profil resmi sadece JPEG, PNG veya GIF formatında olabilir.';
            } elseif ($_FILES['profile_image']['size'] > $maxSize) {
                $errors[] = 'Profil resmi boyutu maksimum 500KB olabilir.';
            } else {
                $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $profileImage = 'profile_' . time() . '.' . $extension;
                $uploadPath = UPLOADS_PATH . 'profile/';
                
                // Create directory if not exists
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
                
                if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath . $profileImage)) {
                    $errors[] = 'Profil resmi yüklenirken bir hata oluştu.';
                    $profileImage = null;
                }
            }
        }
        
        if (!empty($errors)) {
            jsonResponse(['success' => false, 'message' => implode('<br>', $errors)], 400);
        }
        
        // Check if language column exists, if not add it (before transaction)
        $languageColumnExists = false;
        try {
            // Check if column exists using INFORMATION_SCHEMA
            $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'language'");
            $langColumnCheck = $db->single();
            $languageColumnExists = !empty($langColumnCheck);
            
            // If column doesn't exist, try to add it
            if (!$languageColumnExists) {
                // Make sure we're not in a transaction before ALTER TABLE
                if ($db->inTransaction()) {
                    $db->cancelTransaction();
                }
                
                try {
                    $db->query("ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT 'tr' AFTER email");
                    $db->execute();
                    $languageColumnExists = true;
                } catch (PDOException $alterError) {
                    // Column might already exist from another request, check again
                    error_log('Language column addition error: ' . $alterError->getMessage());
                    
                    try {
                        $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'language'");
                        $langColumnCheck = $db->single();
                        $languageColumnExists = !empty($langColumnCheck);
                    } catch (Exception $e2) {
                        error_log('Language column check error: ' . $e2->getMessage());
                        $languageColumnExists = false;
                    }
                }
            }
        } catch (Exception $alterError) {
            error_log('Language column check general error: ' . $alterError->getMessage());
            // Assume column doesn't exist - continue without language update
            $languageColumnExists = false;
        }
        
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Update user
            $query = "UPDATE users SET 
                     name = :name,
                     surname = :surname, 
                     email = :email";
            
            // Add language to query only if column exists
            if ($languageColumnExists) {
                $query .= ", language = :user_language";
            }
            
            // Add profile image to query if uploaded
            if ($profileImage) {
                $query .= ", profile_image = :profile_image";
                
                // Delete old profile image if exists
                if (!empty($user['profile_image'])) {
                    $oldImage = UPLOADS_PATH . 'profile/' . $user['profile_image'];
                    if (file_exists($oldImage)) {
                        unlink($oldImage);
                    }
                }
            }
            
            $query .= ", updated_at = NOW() WHERE id = :id";
            
            $db->query($query);
            $db->bind(':name', $name);
            $db->bind(':surname', $surname);
            $db->bind(':email', $email);
            
            // Bind language only if column exists and was added to query
            if ($languageColumnExists) {
                $db->bind(':user_language', $userLanguage);
            }
            
            if ($profileImage) {
                $db->bind(':profile_image', $profileImage);
            }
            
            $db->bind(':id', $userId);
            
            // Execute query
            error_log('Profile API - Executing UPDATE query: ' . $query);
            error_log('Profile API - Binding values - name: ' . $name . ', surname: ' . $surname . ', email: ' . $email . ', language: ' . ($languageColumnExists ? $userLanguage : 'N/A') . ', id: ' . $userId);
            
            $result = $db->execute();
            
            error_log('Profile API - Query executed, result: ' . ($result ? 'success' : 'failed'));
            
            if (!$result) {
                throw new Exception(t('profile_update_error_process', 'İşlem sırasında bir hata oluştu.'));
            }
            
            // Update session language if changed
            if (Session::get('language') != $userLanguage) {
                Session::set('language', $userLanguage);
                // Reload language translations
                if (isset($GLOBALS['language'])) {
                    $GLOBALS['language']->setLanguage($userLanguage);
                    $GLOBALS['L'] = $GLOBALS['language']->getAll();
                }
            }
            
            // Update session user data with new language
            $user = $auth->getCurrentUser();
            $user['language'] = $userLanguage;
            Session::set('user', $user);
            
            // Log activity using logActivity helper
            try {
                logActivity('update_profile', 'user', $userId, [
                    'name' => $user['name'],
                    'surname' => $user['surname'],
                    'email' => $user['email']
                ], [
                    'name' => $name,
                    'surname' => $surname,
                    'email' => $email
                ], "Profil güncellendi: {$name} {$surname}");
            } catch (Exception $logError) {
                // Log error ignored, don't fail the update
            }
            
            // Commit transaction
            $db->endTransaction();
            
            jsonResponse(['success' => true, 'message' => t('profile_update_success_message', 'Profil başarıyla güncellendi.')]);
            
        } catch (PDOException $e) {
            // Rollback transaction if active
            if ($db->inTransaction()) {
                try {
                    $db->cancelTransaction();
                } catch (Exception $rollbackError) {
                    error_log('Transaction rollback error: ' . $rollbackError->getMessage());
                }
            }
            
            // Delete uploaded image if exists
            if (isset($profileImage) && $profileImage && file_exists(UPLOADS_PATH . 'profile/' . $profileImage)) {
                unlink(UPLOADS_PATH . 'profile/' . $profileImage);
            }
            
            error_log('Profile update PDO error: ' . $e->getMessage());
            error_log('Profile update error code: ' . $e->getCode());
            error_log('Profile update query: ' . (isset($query) ? $query : 'N/A'));
            error_log('Profile update user ID: ' . (isset($userId) ? $userId : 'N/A'));
            error_log('Profile update trace: ' . $e->getTraceAsString());
            
            jsonResponse(['success' => false, 'message' => t('profile_update_error_process', 'İşlem sırasında bir hata oluştu.') . ' ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            // Rollback transaction if active
            if ($db->inTransaction()) {
                try {
                    $db->cancelTransaction();
                } catch (Exception $rollbackError) {
                    error_log('Transaction rollback error: ' . $rollbackError->getMessage());
                }
            }
            
            // Delete uploaded image if exists
            if (isset($profileImage) && $profileImage && file_exists(UPLOADS_PATH . 'profile/' . $profileImage)) {
                unlink(UPLOADS_PATH . 'profile/' . $profileImage);
            }
            
            error_log('Profile update error: ' . $e->getMessage());
            error_log('Profile update query: ' . (isset($query) ? $query : 'N/A'));
            error_log('Profile update user ID: ' . (isset($userId) ? $userId : 'N/A'));
            error_log('Profile update trace: ' . $e->getTraceAsString());
            
            jsonResponse(['success' => false, 'message' => t('profile_update_error_process', 'İşlem sırasında bir hata oluştu.') . ' ' . $e->getMessage()], 500);
        }
        break;
        
    case 'get_activities':
        // Get user activities
        $userId = $auth->getUserId();
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $db->query("SELECT COUNT(*) as count FROM user_activity WHERE user_id = :user_id");
        $db->bind(':user_id', $userId);
        $totalCount = $db->single()['count'];
        
        // Get activities
        $db->query("SELECT * FROM user_activity WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        $db->bind(':user_id', $userId);
        $db->bind(':limit', $limit, PDO::PARAM_INT);
        $db->bind(':offset', $offset, PDO::PARAM_INT);
        $activities = $db->resultSet();
        
        // Format activities
        foreach ($activities as &$activity) {
            $activity['created_at_formatted'] = date('d.m.Y H:i', strtotime($activity['created_at']));
            
            switch ($activity['activity']) {
                case 'login':
                    $activity['activity_text'] = 'Sisteme giriş yapıldı';
                    $activity['icon'] = 'sign-in-alt';
                    $activity['color'] = 'success';
                    break;
                case 'logout':
                    $activity['activity_text'] = 'Sistemden çıkış yapıldı';
                    $activity['icon'] = 'sign-out-alt';
                    $activity['color'] = 'warning';
                    break;
                case 'update_profile':
                    $activity['activity_text'] = 'Profil güncellendi';
                    $activity['icon'] = 'user-edit';
                    $activity['color'] = 'primary';
                    break;
                case 'change_password':
                    $activity['activity_text'] = 'Şifre değiştirildi';
                    $activity['icon'] = 'key';
                    $activity['color'] = 'danger';
                    break;
                case 'create_order':
                    $activity['activity_text'] = 'Yeni sipariş oluşturuldu';
                    $activity['icon'] = 'shopping-cart';
                    $activity['color'] = 'info';
                    break;
                case 'add_product':
                    $activity['activity_text'] = 'Yeni ürün eklendi';
                    $activity['icon'] = 'box';
                    $activity['color'] = 'success';
                    break;
                case 'add_customer':
                    $activity['activity_text'] = 'Yeni müşteri eklendi';
                    $activity['icon'] = 'user-plus';
                    $activity['color'] = 'info';
                    break;
                case 'stock_movement':
                    $activity['activity_text'] = 'Stok hareketi gerçekleştirildi';
                    $activity['icon'] = 'dolly';
                    $activity['color'] = 'warning';
                    break;
                default:
                    $activity['activity_text'] = $activity['activity'];
                    $activity['icon'] = 'check-circle';
                    $activity['color'] = 'primary';
            }
            
            if (!empty($activity['details'])) {
                $activity['activity_text'] .= ': ' . $activity['details'];
            }
        }
        
        jsonResponse([
            'success' => true, 
            'activities' => $activities,
            'pagination' => [
                'total' => $totalCount,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => ceil($totalCount / $limit)
            ]
        ]);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Geçersiz eylem'], 400);
        break;
}