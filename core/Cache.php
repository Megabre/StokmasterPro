<?php
/**
 * Megabre StokMaster Pro
 * Cache Class
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

class Cache {
    private static $instance = null;
    private $cache_path;
    private $enabled;
    private $lifetime;
    private $method;
    private $ttl;
    private $db;
    
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
     * Reset singleton instance
     */
    public static function resetInstance() {
        self::$instance = null;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->cache_path = CACHE_PATH;
        $this->db = Database::getInstance();
        
        // Load settings from database
        $this->loadSettings();
        
        // Create cache directory if it doesn't exist
        if (!file_exists($this->cache_path)) {
            mkdir($this->cache_path, 0777, true);
        }
        
        // Create subdirectories if they don't exist
        $subdirs = ['data', 'templates', 'queries'];
        foreach ($subdirs as $dir) {
            $path = $this->cache_path . '/' . $dir;
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
        }
    }
    
    /**
     * Load settings from database
     */
    private function loadSettings() {
        $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('cache_enabled', 'cache_ttl', 'cache_method')");
        $settings = $this->db->resultSet();
        
        // Default values
        $this->enabled = false;
        $this->ttl = 3600;
        $this->method = 'file';
        
        // Override with database values
        foreach ($settings as $setting) {
            switch ($setting['setting_key']) {
                case 'cache_enabled':
                    $this->enabled = (bool)$setting['setting_value'];
                    break;
                case 'cache_ttl':
                    $this->ttl = (int)$setting['setting_value'];
                    break;
                case 'cache_method':
                    $this->method = $setting['setting_value'];
                    break;
            }
        }
        
        $this->lifetime = $this->ttl;
    }
    
    /**
     * Get cache key
     */
    private function getKey($key) {
        return md5($key);
    }
    
    /**
     * Get cache file path
     */
    private function getFilePath($key, $type = 'data') {
        $hashed_key = $this->getKey($key);
        
        // Determine subdirectory based on key prefix or type
        $subdir = 'data';
        if (strpos($key, 'template:') === 0 || strpos($key, 'templates:') === 0) {
            $subdir = 'templates';
        } elseif (strpos($key, 'query:') === 0 || strpos($key, 'queries:') === 0) {
            $subdir = 'queries';
        } elseif ($type !== 'data') {
            $subdir = $type;
        }
        
        $subdir_path = $this->cache_path . '/' . $subdir;
        if (!file_exists($subdir_path)) {
            mkdir($subdir_path, 0777, true);
        }
        
        return $subdir_path . '/' . $hashed_key . '.cache';
    }
    
    /**
     * Check if cache exists and is valid
     */
    public function exists($key, $type = 'data') {
        if (!$this->enabled) {
            return false;
        }
        
        $file = $this->getFilePath($key, $type);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = $this->read($key, $type);
        
        if (!$data) {
            return false;
        }
        
        return $data['expiry'] > time();
    }
    
    /**
     * Set cache
     */
    public function set($key, $data, $lifetime = null, $type = 'data') {
        if (!$this->enabled) {
            return false;
        }
        
        $lifetime = $lifetime !== null ? $lifetime : $this->lifetime;
        
        $cache_data = [
            'data' => $data,
            'expiry' => time() + $lifetime,
            'type' => $type
        ];
        
        $file = $this->getFilePath($key, $type);
        $content = serialize($cache_data);
        
        return file_put_contents($file, $content) !== false;
    }
    
    /**
     * Get cache
     */
    public function get($key, $default = null, $type = 'data') {
        if (!$this->enabled || !$this->exists($key, $type)) {
            return $default;
        }
        
        $data = $this->read($key, $type);
        
        return $data ? $data['data'] : $default;
    }
    
    /**
     * Read cache file
     */
    private function read($key, $type = 'data') {
        $file = $this->getFilePath($key, $type);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $content = file_get_contents($file);
        
        if (!$content) {
            return false;
        }
        
        $data = @unserialize($content);
        
        if (!$data) {
            return false;
        }
        
        if ($data['expiry'] < time()) {
            $this->delete($key, $type);
            return false;
        }
        
        return $data;
    }
    
    /**
     * Delete cache
     */
    public function delete($key, $type = 'data') {
        $file = $this->getFilePath($key, $type);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return false;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $subdirs = ['data', 'templates', 'queries'];
        $cleared = 0;
        
        foreach ($subdirs as $dir) {
            $path = $this->cache_path . '/' . $dir;
            if (is_dir($path)) {
                $files = glob($path . '/*.cache');
                if ($files) {
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            unlink($file);
                            $cleared++;
                        }
                    }
                }
            }
        }
        
        // Also clear root cache files (for backward compatibility)
        $rootFiles = glob($this->cache_path . '*.cache');
        if ($rootFiles) {
            foreach ($rootFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                    $cleared++;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Clear expired cache
     */
    public function clearExpired() {
        $subdirs = ['data', 'templates', 'queries'];
        $cleared = 0;
        
        foreach ($subdirs as $dir) {
            $path = $this->cache_path . '/' . $dir;
            if (is_dir($path)) {
                $files = glob($path . '/*.cache');
                if ($files) {
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            $content = file_get_contents($file);
                            
                            if ($content) {
                                $data = @unserialize($content);
                                
                                if ($data && isset($data['expiry']) && $data['expiry'] < time()) {
                                    unlink($file);
                                    $cleared++;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Also clear expired root cache files (for backward compatibility)
        $rootFiles = glob($this->cache_path . '*.cache');
        if ($rootFiles) {
            foreach ($rootFiles as $file) {
                if (file_exists($file)) {
                    $content = file_get_contents($file);
                    
                    if ($content) {
                        $data = @unserialize($content);
                        
                        if ($data && isset($data['expiry']) && $data['expiry'] < time()) {
                            unlink($file);
                            $cleared++;
                        }
                    }
                }
            }
        }
        
        return $cleared;
    }
    
    /**
     * Forget (delete) a specific cache key
     */
    public function forget($key, $type = 'data') {
        return $this->delete($key, $type);
    }
    
    /**
     * Get cache statistics with detailed file information
     */
    public function getStats() {
        $stats = [
            'total' => 0,
            'active' => 0,
            'expired' => 0,
            'size' => 0,
            'size_formatted' => '0 B',
            'files' => [],
            'data' => [
                'count' => 0,
                'size' => 0,
                'size_formatted' => '0 B'
            ],
            'templates' => [
                'count' => 0,
                'size' => 0,
                'size_formatted' => '0 B'
            ],
            'queries' => [
                'count' => 0,
                'size' => 0,
                'size_formatted' => '0 B'
            ]
        ];
        
        $subdirs = ['data', 'templates', 'queries'];
        foreach ($subdirs as $dir) {
            $path = $this->cache_path . '/' . $dir;
            if (is_dir($path)) {
                $files = glob($path . '/*.cache');
                if ($files) {
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            $size = filesize($file);
                            $content = file_get_contents($file);
                            $data = @unserialize($content);
                            $isExpired = $data && isset($data['expiry']) && $data['expiry'] < time();
                            
                            $stats['total']++;
                            $stats['size'] += $size;
                            
                            if ($isExpired) {
                                $stats['expired']++;
                            } else {
                                $stats['active']++;
                            }
                            
                            $stats[$dir]['count']++;
                            $stats[$dir]['size'] += $size;
                            
                            $stats['files'][] = [
                                'name' => basename($file),
                                'path' => $file,
                                'size' => $size,
                                'size_formatted' => formatBytes($size),
                                'type' => $dir,
                                'expired' => $isExpired,
                                'last_modified' => filemtime($file)
                            ];
                        }
                    }
                }
            }
        }
        
        // Format sizes
        $stats['size_formatted'] = formatBytes($stats['size']);
        $stats['data']['size_formatted'] = formatBytes($stats['data']['size']);
        $stats['templates']['size_formatted'] = formatBytes($stats['templates']['size']);
        $stats['queries']['size_formatted'] = formatBytes($stats['queries']['size']);
        
        return $stats;
    }
    
    /**
     * Remember (get from cache or execute callback and store)
     */
    public function remember($key, $callback, $lifetime = null, $type = 'data') {
        if ($this->exists($key, $type)) {
            return $this->get($key, null, $type);
        }
        
        $data = $callback();
        $this->set($key, $data, $lifetime, $type);
        
        return $data;
    }
    
    /**
     * Check if cache is enabled
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * Enable cache
     */
    public function enable() {
        $this->enabled = true;
    }
    
    /**
     * Disable cache
     */
    public function disable() {
        $this->enabled = false;
    }
    
    /**
     * Set cache lifetime
     */
    public function setLifetime($seconds) {
        $this->lifetime = $seconds;
    }
    
    /**
     * Get cache lifetime
     */
    public function getLifetime() {
        return $this->lifetime;
    }
    
    public function setMethod($method) {
        if (!in_array($method, ['file', 'apc', 'memcached', 'redis'])) {
            return false;
        }
        
        // Mevcut önbelleği temizle
        $this->clear();
        
        // Yeni metodu ayarla
        $this->method = $method;
        
        // Veritabanında INSERT veya UPDATE yap
        $this->db->query("SELECT id FROM settings WHERE setting_key = 'cache_method'");
        $exists = $this->db->single();
        
        if ($exists) {
            $this->db->query("UPDATE settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = 'cache_method'");
        } else {
            $this->db->query("INSERT INTO settings (setting_key, setting_value, setting_description, created_at, updated_at) VALUES ('cache_method', :value, 'Önbellek saklama metodu', NOW(), NOW())");
        }
        $this->db->bind(':value', $method);
        return $this->db->execute();
    }
    
    public function setTTL($ttl) {
        if ($ttl < 60) {
            return false;
        }
        
        $this->ttl = $ttl;
        $this->lifetime = $ttl;
        
        // Veritabanında INSERT veya UPDATE yap
        $this->db->query("SELECT id FROM settings WHERE setting_key = 'cache_ttl'");
        $exists = $this->db->single();
        
        if ($exists) {
            $this->db->query("UPDATE settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = 'cache_ttl'");
        } else {
            $this->db->query("INSERT INTO settings (setting_key, setting_value, setting_description, created_at, updated_at) VALUES ('cache_ttl', :value, 'Önbellek saklama süresi (saniye)', NOW(), NOW())");
        }
        $this->db->bind(':value', $ttl);
        return $this->db->execute();
    }
    
    public function setEnabled($enabled) {
        $this->enabled = (bool)$enabled;
        
        // Veritabanında INSERT veya UPDATE yap
        $this->db->query("SELECT id FROM settings WHERE setting_key = 'cache_enabled'");
        $exists = $this->db->single();
        
        if ($exists) {
            $this->db->query("UPDATE settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = 'cache_enabled'");
        } else {
            $this->db->query("INSERT INTO settings (setting_key, setting_value, setting_description, created_at, updated_at) VALUES ('cache_enabled', :value, 'Cache sistemi aktif mi?', NOW(), NOW())");
        }
        $this->db->bind(':value', $enabled ? '1' : '0');
        $result = $this->db->execute();
        
        // Singleton instance'ı resetle ki yeni ayarlar yüklensin
        if ($result) {
            self::resetInstance();
        }
        
        return $result;
    }
}