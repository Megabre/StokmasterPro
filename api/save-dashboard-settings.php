<?php
/**
 * Megabre StokMaster Pro
 * Save Dashboard Settings API
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Include required files
require_once __DIR__ . '/../config/config.php';
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
    // Get POST data
    $settings = json_decode(file_get_contents('php://input'), true);
    
    if (!$settings) {
        throw new Exception('Geçersiz ayar verisi.');
    }
    
    // Save settings to JSON file
    $settingsFile = ROOT_PATH . '/data/dashboard_settings.json';
    if (file_put_contents($settingsFile, json_encode($settings))) {
        jsonResponse([
            'success' => true,
            'message' => 'Ayarlar başarıyla kaydedildi.'
        ]);
    } else {
        throw new Exception('Ayarlar kaydedilirken bir hata oluştu.');
    }
    
} catch (Exception $e) {
    // Log error
    error_log('Dashboard Settings Error: ' . $e->getMessage());
    
    // Return error response
    jsonResponse([
        'success' => false,
        'message' => 'Ayarlar kaydedilirken bir hata oluştu: ' . $e->getMessage()
    ], 500);
} 