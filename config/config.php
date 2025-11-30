<?php
/**
 * Megabre StokMaster Pro
 * System Configuration
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Application paths
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes/');
define('MODULES_PATH', BASE_PATH . '/modules/');
define('ASSETS_PATH', BASE_PATH . '/assets/');
define('VENDOR_PATH', BASE_PATH . '/vendor/');

// Application settings
define('APP_NAME', 'StokMaster Pro');
define('APP_VERSION', '1.1.3');
define('APP_URL', 'http://localhost/stok');
define('APP_TIMEZONE', 'Europe/Istanbul');

// Base URL - Dinamik olarak tespit et
$base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url .= "://" . $_SERVER['HTTP_HOST'];
$base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
define('BASE_URL', $base_url);

// Path settings
define('ROOT_PATH', dirname(__DIR__));
define('CORE_PATH', ROOT_PATH . '/core/');
define('UPLOADS_PATH', ROOT_PATH . '/uploads/');
define('CACHE_PATH', ROOT_PATH . '/cache/');
define('BACKUP_PATH', ROOT_PATH . 'backup/');
define('DATA_PATH', ROOT_PATH . '/data/');
define('API_PATH', ROOT_PATH . '/api/');

// Create data directory if not exists
if (!file_exists(DATA_PATH)) {
    mkdir(DATA_PATH, 0777, true);
}

// Session settings
define('SESSION_PREFIX', 'MEGABRE_');
define('SESSION_LIFETIME', 86400); // 24 saat (saniye)

// Cache settings
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 saat (saniye)

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting (Production'da kapatılacak)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'database.php';

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}