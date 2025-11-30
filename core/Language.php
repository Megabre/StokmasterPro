<?php
/**
 * Megabre StokMaster Pro
 * Language Class - File Based
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

class Language {
    private static $instance = null;
    private $translations = [];
    private $language = 'tr'; // Default language
    private $loaded = false;
    private static $L = []; // Global translations array
    
    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Priority: User preference > Session > Default
        // Try to get from user profile first
        if (isset($GLOBALS['auth']) && $GLOBALS['auth']->isLoggedIn()) {
            $user = $GLOBALS['auth']->getCurrentUser();
            if (!empty($user['language'])) {
                $this->language = $user['language'];
                Session::set('language', $this->language);
            } elseif (Session::exists('language')) {
                $this->language = Session::get('language');
            } else {
                // Default language
                $this->language = 'tr';
                Session::set('language', $this->language);
            }
        } elseif (Session::exists('language')) {
            $this->language = Session::get('language');
        } else {
            // Default language
            $this->language = 'tr';
            Session::set('language', $this->language);
        }
        
        $this->load();
    }
    
    /**
     * Load translations from file
     */
    public function load() {
        if ($this->loaded) {
            return;
        }
        
        $lang_file = ROOT_PATH . '/lang/' . $this->language . '.php';
        
        if (file_exists($lang_file)) {
            $translations = include $lang_file;
            if (is_array($translations)) {
                $this->translations = $translations;
                self::$L = $translations; // Set global $L array
            }
        } else {
            // Fallback to Turkish if language file doesn't exist
            $fallback_file = ROOT_PATH . '/lang/tr.php';
            if (file_exists($fallback_file)) {
                $translations = include $fallback_file;
                if (is_array($translations)) {
                    $this->translations = $translations;
                    self::$L = $translations;
                }
            }
        }
        
        $this->loaded = true;
    }
    
    /**
     * Get translation
     */
    public function get($key, $default = null) {
        $this->load();
        
        if (isset($this->translations[$key])) {
            return $this->translations[$key];
        }
        
        return $default !== null ? $default : $key;
    }
    
    /**
     * Get all translations
     */
    public function getAll() {
        $this->load();
        return $this->translations;
    }
    
    /**
     * Get current language code
     */
    public function getCurrentLanguage() {
        return $this->language;
    }
    
    /**
     * Set language
     */
    public function setLanguage($code) {
        $lang_file = ROOT_PATH . '/lang/' . $code . '.php';
        
        if (file_exists($lang_file)) {
            $this->language = $code;
            $this->loaded = false;
            Session::set('language', $code);
            $this->load();
            
            // Update user language preference if logged in
            if (isset($GLOBALS['auth']) && $GLOBALS['auth']->isLoggedIn()) {
                $db = Database::getInstance();
                $user = $GLOBALS['auth']->getCurrentUser();
                
                if ($user && isset($user['id'])) {
                    $user_id = $user['id'];
                    
                    try {
                        // Check if language column exists
                        $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'language'");
                        $langColumnCheck = $db->single();
                        
                        if (!empty($langColumnCheck)) {
                            // Column exists, update it
                            $db->query("UPDATE users SET language = :language WHERE id = :id");
                            $db->bind(':language', $code);
                            $db->bind(':id', $user_id);
                            $db->execute();
                            
                            // Update session user data
                            $user['language'] = $code;
                            Session::set('user', $user);
                        } else {
                            // Column doesn't exist, try to add it
                            try {
                                $db->query("ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT 'tr' AFTER email");
                                $db->execute();
                                
                                // Now update it
                                $db->query("UPDATE users SET language = :language WHERE id = :id");
                                $db->bind(':language', $code);
                                $db->bind(':id', $user_id);
                                $db->execute();
                                
                                // Update session user data
                                $user['language'] = $code;
                                Session::set('user', $user);
                            } catch (Exception $e) {
                                // Column might already exist or other error, ignore
                                error_log('Language column addition: ' . $e->getMessage());
                            }
                        }
                    } catch (Exception $e) {
                        // Error checking column, try direct update
                        try {
                            $db->query("UPDATE users SET language = :language WHERE id = :id");
                            $db->bind(':language', $code);
                            $db->bind(':id', $user_id);
                            $db->execute();
                            
                            // Update session user data
                            $user['language'] = $code;
                            Session::set('user', $user);
                        } catch (Exception $e2) {
                            error_log('Language update error: ' . $e2->getMessage());
                        }
                    }
                }
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get available languages
     */
    public function getAvailableLanguages() {
        $languages = [];
        $lang_dir = ROOT_PATH . '/lang/';
        
        if (is_dir($lang_dir)) {
            $files = glob($lang_dir . '*.php');
            foreach ($files as $file) {
                $code = basename($file, '.php');
                $lang_data = include $file;
                $languages[$code] = [
                    'code' => $code,
                    'name' => isset($lang_data['language_name']) ? $lang_data['language_name'] : $code,
                    'native_name' => isset($lang_data['language_native_name']) ? $lang_data['language_native_name'] : $code
                ];
            }
        }
        
        return $languages;
    }
    
    /**
     * Reload translations
     */
    public function reload() {
        $this->loaded = false;
        $this->load();
    }
}

/**
 * Global translation function
 * Usage: __('key') or $L['key']
 */
function __($key, $default = null) {
    $lang = Language::getInstance();
    return $lang->get($key, $default);
}

// Initialize global $L array
if (!isset($GLOBALS['L'])) {
    $GLOBALS['L'] = [];
}

// Load language on include
if (class_exists('Language')) {
    $lang = Language::getInstance();
    $GLOBALS['L'] = $lang->getAll();
}
