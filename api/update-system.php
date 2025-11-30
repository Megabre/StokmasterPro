<?php
/**
 * Megabre StokMaster Pro
 * System Update API
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Oturum açmanız gerekiyor.'], 401);
    exit;
}

// Check if request is POST
if (!isPost()) {
    jsonResponse(['success' => false, 'message' => 'Geçersiz istek.'], 405);
    exit;
}

try {
    // Get database instance
    $db = Database::getInstance();
    
    // Clear query cache
    $db->query("FLUSH QUERY CACHE");
    
    // Clear table cache
    $db->query("FLUSH TABLES");
    
    // Clear privileges
    $db->query("FLUSH PRIVILEGES");
    
    // Clear session data
    session_regenerate_id(true);
    
    // Clear PHP opcache if enabled
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // Clear temporary files
    $temp_dir = ROOT_PATH . 'temp/';
    if (is_dir($temp_dir)) {
        $files = glob($temp_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    // Clear cache directory
    $cache_dir = ROOT_PATH . 'cache/';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    // Return success response
    jsonResponse([
        'success' => true,
        'message' => 'Sistem başarıyla güncellendi. Sayfa yenileniyor...'
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('System Update Error: ' . $e->getMessage());
    
    // Return error response
    jsonResponse([
        'success' => false,
        'message' => 'Sistem güncellenirken bir hata oluştu: ' . $e->getMessage()
    ], 500);
} 