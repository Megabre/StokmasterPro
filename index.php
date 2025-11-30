<?php
/**
 * Megabre StokMaster Pro
 * Index Page - Main Router
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Include configuration
require_once 'config/config.php';

// Include core files
require_once CORE_PATH . 'Database.php';
require_once CORE_PATH . 'Session.php';
require_once CORE_PATH . 'Authentication.php';
require_once CORE_PATH . 'Language.php';
require_once CORE_PATH . 'Cache.php';
require_once CORE_PATH . 'DynamicFields.php';
require_once CORE_PATH . 'helpers.php';

// Initialize authentication
$auth = new Authentication();

// Check if logged in, redirect to login if not
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Handle language change
if (isset($_GET['language'])) {
    $lang_code = $_GET['language'];
    $language = Language::getInstance();
    if ($language->setLanguage($lang_code)) {
        // Build clean URL without language parameter
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        parse_str($queryString, $params);
        unset($params['language']);
        
        $cleanQuery = http_build_query($params);
        $scriptName = basename($_SERVER['SCRIPT_NAME']);
        
        // Build relative path
        $redirectUrl = $scriptName;
        if (!empty($cleanQuery)) {
            $redirectUrl .= '?' . $cleanQuery;
        }
        
        redirect($redirectUrl);
    }
}

// Get current user
$current_user = $auth->getCurrentUser();

// Check if language column exists in users table
$db = Database::getInstance();
try {
    $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'language'");
    $langColumnExists = $db->single();
    if (!$langColumnExists) {
        // Add language column
        $db->query("ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT 'tr' AFTER email");
        $db->execute();
        
        // Reload user data to include language column
        $db->query("SELECT * FROM users WHERE id = :id");
        $db->bind(':id', $current_user['id']);
        $current_user = $db->single();
        unset($current_user['password']);
        Session::set('user', $current_user);
    }
} catch (Exception $e) {
    // If error, try alternative method
    try {
        $db->query("ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT 'tr' AFTER email");
        $db->execute();
        
        // Reload user data to include language column
        $db->query("SELECT * FROM users WHERE id = :id");
        $db->bind(':id', $current_user['id']);
        $current_user = $db->single();
        unset($current_user['password']);
        Session::set('user', $current_user);
    } catch (Exception $e2) {
        // Column might already exist, ignore error
        error_log('Language column addition: ' . $e2->getMessage());
    }
}

// Initialize language - prioritize user preference, then session, then default
$language = Language::getInstance();

// Load user language preference if exists
if (!empty($current_user['language'])) {
    $language->setLanguage($current_user['language']);
} elseif (Session::exists('language')) {
    // Use session language if user preference not set
    $language->setLanguage(Session::get('language'));
}

// Make $L, language and auth available globally
$GLOBALS['L'] = $language->getAll();
$GLOBALS['language'] = $language;
$GLOBALS['auth'] = $auth;

// Get settings
$db = Database::getInstance();
$db->query("SELECT * FROM settings");
$settingsResult = $db->resultSet();

$settings = [];
foreach ($settingsResult as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get module and action from URL
$module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : 'index';

// Handle API requests
if ($module === 'api') {
    header('Content-Type: application/json');
    
    $apiFile = API_PATH . $action . '.php';
    if (file_exists($apiFile)) {
        // Set subaction if provided
        if (isset($_GET['subaction'])) {
            $_GET['action'] = $_GET['subaction'];
        }
        require_once $apiFile;
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'API endpoint not found']);
        exit;
    }
}

// Validate module
$allowed_modules = [
    'dashboard',
    'products',
    'categories',
    'customers',
    'stock',
    'orders',
    'transactions',
    'tools',
    'settings',
    'profile',
    'activity'
];

if (!in_array($module, $allowed_modules)) {
    $module = 'dashboard';
}

// Build module file path
$module_file = MODULES_PATH . $module . '/' . $action . '.php';

// Check if module file exists
if (file_exists($module_file)) {
    require_once $module_file;
} else {
    // If action file doesn't exist, try index.php
    $module_index = MODULES_PATH . $module . '/index.php';
    if (file_exists($module_index)) {
        require_once $module_index;
    } else {
        // If module doesn't exist, redirect to dashboard
        redirect('index.php?module=dashboard');
    }
}