<?php
/**
 * Megabre StokMaster Pro
 * Session Class
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

class Session {
    /**
     * Start session
     */
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_name(SESSION_PREFIX . 'SESSION');
            session_start([
                'cookie_lifetime' => SESSION_LIFETIME,
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'use_strict_mode' => true
            ]);
        }
    }
    
    /**
     * Set session data
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[SESSION_PREFIX . $key] = $value;
    }
    
    /**
     * Get session data
     */
    public static function get($key, $default = null) {
        self::start();
        return isset($_SESSION[SESSION_PREFIX . $key]) ? $_SESSION[SESSION_PREFIX . $key] : $default;
    }
    
    /**
     * Delete session data
     */
    public static function delete($key) {
        self::start();
        if (isset($_SESSION[SESSION_PREFIX . $key])) {
            unset($_SESSION[SESSION_PREFIX . $key]);
            return true;
        }
        return false;
    }
    
    /**
     * Check if session data exists
     */
    public static function exists($key) {
        self::start();
        return isset($_SESSION[SESSION_PREFIX . $key]);
    }
    
    /**
     * Flash message (set once, display once)
     */
    public static function setFlash($key, $message, $type = 'info') {
        self::start();
        $_SESSION[SESSION_PREFIX . 'flash'][$key] = [
            'message' => $message,
            'type' => $type
        ];
    }
    
    /**
     * Get flash message
     */
    public static function getFlash($key) {
        self::start();
        if (isset($_SESSION[SESSION_PREFIX . 'flash'][$key])) {
            $flash = $_SESSION[SESSION_PREFIX . 'flash'][$key];
            unset($_SESSION[SESSION_PREFIX . 'flash'][$key]);
            return $flash;
        }
        return null;
    }
    
    /**
     * Check if flash message exists
     */
    public static function hasFlash($key) {
        self::start();
        return isset($_SESSION[SESSION_PREFIX . 'flash'][$key]);
    }
    
    /**
     * Get all flash messages
     */
    public static function getAllFlash() {
        self::start();
        $flash = isset($_SESSION[SESSION_PREFIX . 'flash']) ? $_SESSION[SESSION_PREFIX . 'flash'] : [];
        unset($_SESSION[SESSION_PREFIX . 'flash']);
        return $flash;
    }
    
    /**
     * Set CSRF token
     */
    public static function setCsrfToken() {
        self::start();
        $token = bin2hex(random_bytes(32));
        $_SESSION[SESSION_PREFIX . 'csrf_token'] = $token;
        return $token;
    }
    
    /**
     * Get CSRF token
     */
    public static function getCsrfToken() {
        self::start();
        if (!isset($_SESSION[SESSION_PREFIX . 'csrf_token'])) {
            return self::setCsrfToken();
        }
        return $_SESSION[SESSION_PREFIX . 'csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCsrfToken($token) {
        self::start();
        if (!isset($_SESSION[SESSION_PREFIX . 'csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION[SESSION_PREFIX . 'csrf_token'], $token);
    }
    
    /**
     * Destroy session
     */
    public static function destroy() {
        self::start();
        session_unset();
        session_destroy();
        return true;
    }
    
    /**
     * Regenerate session ID
     */
    public static function regenerate() {
        self::start();
        return session_regenerate_id(true);
    }
}