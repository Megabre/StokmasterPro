<?php
/**
 * Megabre StokMaster Pro
 * Helper Functions
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

/**
 * Redirect to page
 */
function redirect($page) {
    header('Location: ' . BASE_URL . $page);
    exit;
}

/**
 * Get complete URL
 */
function url($path = '') {
    return BASE_URL . $path;
}

/**
 * Get asset URL
 */
function asset($path) {
    return BASE_URL . 'assets/' . $path;
}

/**
 * Format price
 */
function formatPrice($price, $decimals = 2) {
    return number_format($price, $decimals, ',', '.');
}

/**
 * Format date
 */
function formatDate($date, $format = 'd.m.Y') {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format datetime
 */
function formatDateTime($date, $format = 'd.m.Y H:i') {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Escape HTML
 */
function e($string) {
    if ($string === null || $string === '') {
        return '';
    }
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Translation helper function
 */
function t($key, $default = null) {
    if (isset($GLOBALS['L'][$key])) {
        return $GLOBALS['L'][$key];
    }
    return $default !== null ? $default : $key;
}

/**
 * Generate slug
 */
function slug($string) {
    $string = mb_strtolower($string, 'UTF-8');
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

/**
 * Generate slugify (alias for slug with Turkish character support)
 */
function slugify($string) {
    // Turkish character mapping
    $turkish = ['ş', 'Ş', 'ı', 'İ', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'];
    $english = ['s', 'S', 'i', 'I', 'g', 'G', 'u', 'U', 'o', 'O', 'c', 'C'];
    $string = str_replace($turkish, $english, $string);
    
    // Convert to lowercase
    $string = mb_strtolower($string, 'UTF-8');
    
    // Remove special characters, keep only alphanumeric and spaces/hyphens
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    
    // Replace spaces and multiple hyphens with single hyphen
    $string = preg_replace('/[\s-]+/', '-', $string);
    
    // Trim hyphens from start and end
    $string = trim($string, '-');
    
    return $string;
}

/**
 * Get current page URL
 */
function currentUrl() {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}

/**
 * Check if string starts with
 */
function startsWith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Check if string ends with
 */
function endsWith($haystack, $needle) {
    return substr($haystack, -strlen($needle)) === $needle;
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Generate random string
 */
function randomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Return JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get request method
 */
function requestMethod() {
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

/**
 * Check if request is POST
 */
function isPost() {
    return requestMethod() === 'POST';
}

/**
 * Check if request is GET
 */
function isGet() {
    return requestMethod() === 'GET';
}

/**
 * Get posted data
 */
function post($key = null, $default = null) {
    if ($key === null) {
        return $_POST;
    }
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/**
 * Get query data
 */
function get($key = null, $default = null) {
    if ($key === null) {
        return $_GET;
    }
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

/**
 * Limit string length
 */
function limitString($string, $limit = 100, $suffix = '...') {
    if (mb_strlen($string, 'UTF-8') <= $limit) {
        return $string;
    }
    return mb_substr($string, 0, $limit, 'UTF-8') . $suffix;
}

/**
 * Debug function
 */
function debug($data, $exit = true) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    if ($exit) exit;
}

/**
 * Get memory usage
 */
function getMemoryUsage() {
    $size = memory_get_usage(true);
    $unit = array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024, ($i=floor(log($size, 1024)))), 2).' '.$unit[$i];
}

/**
 * Get execution time
 */
function getExecutionTime() {
    return round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4);
}

/**
 * Check if environment is development
 */
function isDevelopment() {
    return in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']);
}

/**
 * Create backup of database
 */
function createDatabaseBackup() {
    $backup_dir = BACKUP_PATH;
    $backup_file = $backup_dir . date('Y-m-d-H-i-s') . '.sql';
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $command = sprintf(
        'mysqldump --user=%s --password=%s --host=%s %s > %s',
        DB_USER,
        DB_PASS,
        DB_HOST,
        DB_NAME,
        $backup_file
    );
    
    exec($command, $output, $return_val);
    
    if ($return_val === 0) {
        return basename($backup_file);
    }
    
    return false;
}

/**
 * Generate CSRF input field
 */
function csrfField() {
    $token = Session::getCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validate CSRF token
 */
function validateCsrf() {
    $token = post('csrf_token');
    
    if (!$token || !Session::validateCsrfToken($token)) {
        Session::setFlash('error', 'CSRF doğrulama hatası. Lütfen tekrar deneyin.');
        return false;
    }
    
    return true;
}

/**
 * Get flash messages HTML
 */
function getFlashMessages() {
    $messages = Session::getAllFlash();
    $html = '';
    
    if (!empty($messages)) {
        foreach ($messages as $key => $flash) {
            $class = '';
            switch ($flash['type']) {
                case 'success':
                    $class = 'alert-success';
                    break;
                case 'error':
                    $class = 'alert-danger';
                    break;
                case 'warning':
                    $class = 'alert-warning';
                    break;
                default:
                    $class = 'alert-info';
                    break;
            }
            
            $html .= '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
            $html .= $flash['message'];
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $html .= '</div>';
        }
    }
    
    return $html;
}

/**
 * Is current route active
 */
function isActiveRoute($route) {
    $current_page = basename($_SERVER['SCRIPT_NAME']);
    return $current_page == $route || startsWith($current_page, $route);
}

/**
 * Get active class if route is active
 */
function activeClass($route, $class = 'active') {
    return isActiveRoute($route) ? $class : '';
}

/**
 * Check permissions
 */
function hasPermission($permission) {
    // TODO: Implement permission check
    return true;
}

/**
 * Get browser info
 */
function getBrowser() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $browser = 'Unknown';
    
    if (preg_match('/MSIE/i', $user_agent) && !preg_match('/Opera/i', $user_agent)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $browser = 'Mozilla Firefox';
    } elseif (preg_match('/Chrome/i', $user_agent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Safari/i', $user_agent)) {
        $browser = 'Apple Safari';
    } elseif (preg_match('/Opera/i', $user_agent)) {
        $browser = 'Opera';
    } elseif (preg_match('/Netscape/i', $user_agent)) {
        $browser = 'Netscape';
    }
    
    return $browser;
}

/**
 * Get PHP and MySQL versions
 */
function getSystemVersions() {
    $db = Database::getInstance();
    $db->query("SELECT VERSION() as mysql_version");
    $result = $db->single();
    
    return [
        'app' => defined('APP_VERSION') ? APP_VERSION : '1.1.3',
        'php' => phpversion(),
        'mysql' => $result['mysql_version'] ?? 'Unknown'
    ];
}

/**
 * Convert bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Generate pagination links
 */
function pagination($total_items, $items_per_page, $current_page, $url = '') {
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current_page - 1) . '">&laquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=1">1</a></li>';
        if ($start_page > 2) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $total_pages . '">' . $total_pages . '</a></li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current_page + 1) . '">&raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">&raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Format file size
 */
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

/**
 * Format phone number
 * 
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function formatPhone($phone) {
    if (empty($phone)) {
        return '';
    }
    
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format based on length
    if (strlen($phone) == 10) {
        return substr($phone, 0, 3) . ' ' . substr($phone, 3, 3) . ' ' . substr($phone, 6, 4);
    } elseif (strlen($phone) == 11) {
        return substr($phone, 0, 3) . ' ' . substr($phone, 3, 3) . ' ' . substr($phone, 6, 4);
    }
    
    return $phone;
}

/**
 * Convert Font Awesome icon to Tabler icon
 * 
 * @param string $faIcon Font Awesome icon class (e.g., 'fas fa-home')
 * @return string Tabler icon class (e.g., 'ti ti-home')
 */
function tablerIcon($faIcon) {
    $iconMap = [
        'fas fa-home' => 'ti ti-home',
        'fas fa-plus' => 'ti ti-plus',
        'fas fa-edit' => 'ti ti-edit',
        'fas fa-trash' => 'ti ti-trash',
        'fas fa-eye' => 'ti ti-eye',
        'fas fa-filter' => 'ti ti-filter',
        'fas fa-search' => 'ti ti-search',
        'fas fa-sync' => 'ti ti-refresh',
        'fas fa-sync-alt' => 'ti ti-refresh',
        'fas fa-arrow-right' => 'ti ti-arrow-right',
        'fas fa-arrow-left' => 'ti ti-arrow-left',
        'fas fa-save' => 'ti ti-device-floppy',
        'fas fa-times' => 'ti ti-x',
        'fas fa-check' => 'ti ti-check',
        'fas fa-user' => 'ti ti-user',
        'fas fa-users' => 'ti ti-users',
        'fas fa-box' => 'ti ti-package',
        'fas fa-tags' => 'ti ti-tags',
        'fas fa-warehouse' => 'ti ti-warehouse',
        'fas fa-shopping-cart' => 'ti ti-shopping-cart',
        'fas fa-money-bill-wave' => 'ti ti-currency-dollar',
        'fas fa-tools' => 'ti ti-tool',
        'fas fa-cog' => 'ti ti-settings',
        'fas fa-chart-bar' => 'ti ti-chart-bar',
        'fas fa-calculator' => 'ti ti-calculator',
        'fas fa-broom' => 'ti ti-broom',
        'fas fa-database' => 'ti ti-database',
        'fas fa-file-export' => 'ti ti-file-export',
        'fas fa-tachometer-alt' => 'ti ti-dashboard',
        'fas fa-bell' => 'ti ti-bell',
        'fas fa-exclamation-circle' => 'ti ti-alert-circle',
        'fas fa-clock' => 'ti ti-clock',
        'fas fa-cogs' => 'ti ti-settings',
        'fas fa-check-circle' => 'ti ti-circle-check',
        'fas fa-times-circle' => 'ti ti-circle-x',
        'fas fa-list' => 'ti ti-list',
        'fas fa-sliders-h' => 'ti ti-adjustments',
        'fas fa-plus-circle' => 'ti ti-circle-plus',
        'fas fa-minus-circle' => 'ti ti-circle-minus',
        'fas fa-file-invoice-dollar' => 'ti ti-file-invoice',
        'fas fa-key' => 'ti ti-key',
        'fas fa-sign-out-alt' => 'ti ti-logout',
        'fas fa-user-circle' => 'ti ti-user-circle',
        'fas fa-language' => 'ti ti-language',
        'fas fa-question-circle' => 'ti ti-help',
        'fas fa-spinner' => 'ti ti-loader-2',
        'fas fa-ellipsis-v' => 'ti ti-dots-vertical',
        'fas fa-barcode' => 'ti ti-barcode',
        'fas fa-tag' => 'ti ti-tag',
        'fas fa-box-open' => 'ti ti-package-export',
        'fas fa-cart-plus' => 'ti ti-shopping-cart-plus',
        'fas fa-dolly-flatbed' => 'ti ti-truck',
        'fas fa-user-plus' => 'ti ti-user-plus',
        'fas fa-money-check-alt' => 'ti ti-credit-card',
        'fas fa-chart-line' => 'ti ti-chart-line',
        'fas fa-cogs' => 'ti ti-settings',
        'fas fa-id-card' => 'ti ti-id',
        'fas fa-dollar-sign' => 'ti ti-currency-dollar',
        'fas fa-users-cog' => 'ti ti-users-group',
        'fas fa-boxes' => 'ti ti-packages',
    ];
    
    // Remove 'fa-' prefix if exists and normalize
    $faIcon = str_replace(['fa-', 'fas ', 'far ', 'fab '], '', $faIcon);
    $faIcon = trim($faIcon);
    
    // Check if we have a mapping
    $key = 'fas fa-' . $faIcon;
    if (isset($iconMap[$key])) {
        return $iconMap[$key];
    }
    
    // Default: convert to Tabler format
    return 'ti ti-' . str_replace('fa-', '', $faIcon);
}

/**
 * Log activity to activity_logs table with detailed change tracking
 * 
 * @param string $action Action name (e.g., 'update_product', 'update_customer', 'update_order')
 * @param string $entityType Entity type (e.g., 'product', 'customer', 'order')
 * @param int $entityId Entity ID
 * @param array $oldData Old data array (e.g., ['name' => 'Old Name', 'price' => 100])
 * @param array $newData New data array (e.g., ['name' => 'New Name', 'price' => 200])
 * @param string|null $details Optional additional details
 * @param int|null $userId Optional user ID (defaults to current logged in user)
 * @return bool Success status
 */
function logActivity($action, $entityType = null, $entityId = null, $oldData = null, $newData = null, $details = null, $userId = null) {
    try {
        $db = Database::getInstance();
        
        // Get user ID if not provided
        if ($userId === null) {
            if (isset($GLOBALS['auth']) && $GLOBALS['auth']->isLoggedIn()) {
                $currentUser = $GLOBALS['auth']->getCurrentUser();
                $userId = $currentUser['id'] ?? null;
            }
        }
        
        // Get IP address and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Build details JSON with change tracking
        $changeDetails = [];
        if ($oldData && $newData && is_array($oldData) && is_array($newData)) {
            // Compare old and new data to find changes
            $changes = [];
            foreach ($newData as $key => $newValue) {
                $oldValue = $oldData[$key] ?? null;
                
                // Compare values (handle different types)
                if ($oldValue != $newValue) {
                    $changes[] = [
                        'field' => $key,
                        'old_value' => $oldValue,
                        'new_value' => $newValue
                    ];
                }
            }
            
            if (!empty($changes)) {
                $changeDetails = [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'changes' => $changes
                ];
            }
        }
        
        // Combine with additional details
        $finalDetails = $details;
        if (!empty($changeDetails)) {
            $changeDetailsJson = json_encode($changeDetails, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($finalDetails) {
                $finalDetails = $finalDetails . "\n\n" . $changeDetailsJson;
            } else {
                $finalDetails = $changeDetailsJson;
            }
        }
        
        // Insert into activity_logs table
        $db->query("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                   VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW())");
        $db->bind(':user_id', $userId);
        $db->bind(':action', $action);
        $db->bind(':details', $finalDetails);
        $db->bind(':ip_address', $ipAddress);
        $db->bind(':user_agent', $userAgent);
        
        return $db->execute();
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log('Activity log error: ' . $e->getMessage());
        return false;
    }
}