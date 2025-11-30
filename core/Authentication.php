<?php
/**
 * Megabre StokMaster Pro
 * Authentication Class
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

class Authentication {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        // Get user by username
        $this->db->query("SELECT * FROM users WHERE username = :username AND status = 1");
        $this->db->bind(':username', $username);
        $user = $this->db->single();
        
        // Verify user exists and password is correct
        if ($user && password_verify($password, $user['password'])) {
            $this->logLoginAttempt($user['id'], true);
            $this->updateLastLogin($user['id']);
            
            // Set session
            unset($user['password']); // Don't store password in session
            
            // Load user language preference if exists
            if (!empty($user['language'])) {
                Session::set('language', $user['language']);
            } else {
                // Set default language if not set
                Session::set('language', 'tr');
            }
            
            Session::set('user', $user);
            Session::set('logged_in', true);
            Session::regenerate(); // Security: regenerate session ID
            
            return true;
        }
        
        // Log failed attempt
        $this->logLoginAttempt(null, false);
        return false;
    }
    
    /**
     * Log out user
     */
    public function logout() {
        Session::delete('user');
        Session::delete('logged_in');
        Session::destroy();
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return Session::exists('logged_in') && Session::get('logged_in') === true;
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        return $this->isLoggedIn() ? Session::get('user') : null;
    }
    
    /**
     * Get current user ID
     */
    public function getUserId() {
        $user = $this->getCurrentUser();
        return $user ? $user['id'] : null;
    }
    
    /**
     * Check if current user has specific role
     */
    public function hasRole($role) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        if (is_array($role)) {
            return in_array($user['role'], $role);
        }
        
        return $user['role'] === $role;
    }
    
    /**
     * Check if current user is admin
     */
    public function isAdmin() {
        return $this->hasRole('admin');
    }
    
    /**
     * Update user's last login time
     */
    private function updateLastLogin($userId) {
        $this->db->query("UPDATE users SET last_login = NOW() WHERE id = :id");
        $this->db->bind(':id', $userId);
        return $this->db->execute();
    }
    
    /**
     * Log login attempt
     */
    private function logLoginAttempt($userId, $success) {
        $this->db->query("INSERT INTO login_logs (user_id, ip_address, user_agent, status) VALUES (:user_id, :ip_address, :user_agent, :status)");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
        $this->db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
        $this->db->bind(':status', $success ? 'success' : 'failed');
        return $this->db->execute();
    }
    
    /**
     * Hash password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        // Get user
        $this->db->query("SELECT * FROM users WHERE id = :id");
        $this->db->bind(':id', $userId);
        $user = $this->db->single();
        
        // Verify current password
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return false;
        }
        
        // Update password
        $this->db->query("UPDATE users SET password = :password WHERE id = :id");
        $this->db->bind(':password', $this->hashPassword($newPassword));
        $this->db->bind(':id', $userId);
        
        return $this->db->execute();
    }
    
    /**
     * Create new user
     */
    public function createUser($username, $password, $email, $name, $role = 'user') {
        // Check if username or email already exists
        $this->db->query("SELECT id FROM users WHERE username = :username OR email = :email");
        $this->db->bind(':username', $username);
        $this->db->bind(':email', $email);
        $existingUser = $this->db->single();
        
        if ($existingUser) {
            return false;
        }
        
        // Create user
        $this->db->query("INSERT INTO users (username, password, email, name, role) VALUES (:username, :password, :email, :name, :role)");
        $this->db->bind(':username', $username);
        $this->db->bind(':password', $this->hashPassword($password));
        $this->db->bind(':email', $email);
        $this->db->bind(':name', $name);
        $this->db->bind(':role', $role);
        
        return $this->db->execute() ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update user
     */
    public function updateUser($userId, $email, $name, $role = null) {
        $query = "UPDATE users SET email = :email, name = :name";
        
        // Only update role if provided (admin only)
        if ($role !== null) {
            $query .= ", role = :role";
        }
        
        $query .= " WHERE id = :id";
        
        $this->db->query($query);
        $this->db->bind(':email', $email);
        $this->db->bind(':name', $name);
        
        if ($role !== null) {
            $this->db->bind(':role', $role);
        }
        
        $this->db->bind(':id', $userId);
        
        return $this->db->execute();
    }
    
    /**
     * Get all users
     */
    public function getAllUsers() {
        $this->db->query("SELECT id, username, email, name, role, last_login, status, created_at FROM users ORDER BY id ASC");
        return $this->db->resultSet();
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $this->db->query("SELECT id, username, email, name, role, last_login, status, created_at FROM users WHERE id = :id");
        $this->db->bind(':id', $userId);
        return $this->db->single();
    }
    
    /**
     * Delete user
     */
    public function deleteUser($userId) {
        // Don't allow deleting if there's only one admin
        if ($this->isLastAdmin($userId)) {
            return false;
        }
        
        $this->db->query("DELETE FROM users WHERE id = :id AND role != 'admin'");
        $this->db->bind(':id', $userId);
        return $this->db->execute() && $this->db->rowCount() > 0;
    }
    
    /**
     * Check if user is the last admin
     */
    private function isLastAdmin($userId) {
        $this->db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $result = $this->db->single();
        
        if ($result['count'] <= 1) {
            $this->db->query("SELECT role FROM users WHERE id = :id");
            $this->db->bind(':id', $userId);
            $user = $this->db->single();
            
            return $user && $user['role'] === 'admin';
        }
        
        return false;
    }
    
    /**
     * Change user status (activate/deactivate)
     */
    public function changeUserStatus($userId, $status) {
        // Don't allow deactivating if there's only one admin
        if (!$status && $this->isLastAdmin($userId)) {
            return false;
        }
        
        $this->db->query("UPDATE users SET status = :status WHERE id = :id");
        $this->db->bind(':status', $status ? 1 : 0);
        $this->db->bind(':id', $userId);
        
        return $this->db->execute();
    }
    
    public function hasAccess($module, $action = null) {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $user = $this->getCurrentUser();
        
        // Admin her şeye erişebilir
        if ($user['role'] === 'admin') {
            return true;
        }

        // Modül bazlı yetkilendirme
        $modulePermissions = [
            'dashboard' => ['admin', 'manager', 'accountant', 'staff', 'viewer'],
            'products' => ['admin', 'manager', 'staff'],
            'categories' => ['admin', 'manager'],
            'customers' => ['admin', 'manager', 'accountant', 'staff'],
            'stock' => ['admin', 'manager', 'staff'],
            'orders' => ['admin', 'manager', 'accountant', 'staff'],
            'transactions' => ['admin', 'manager', 'accountant'],
            'reports' => ['admin', 'manager', 'accountant', 'viewer'],
            'tools' => ['admin', 'manager'],
            'settings' => ['admin']
        ];

        // Modül erişim kontrolü
        if (!isset($modulePermissions[$module]) || !in_array($user['role'], $modulePermissions[$module])) {
            return false;
        }

        // Eğer action belirtilmişse, action bazlı yetkilendirme kontrolü
        if ($action !== null) {
            $actionPermissions = [
                'add' => ['admin', 'manager'],
                'edit' => ['admin', 'manager'],
                'delete' => ['admin'],
                'view' => ['admin', 'manager', 'accountant', 'staff', 'viewer']
            ];

            if (!isset($actionPermissions[$action]) || !in_array($user['role'], $actionPermissions[$action])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user can update role
     */
    public function canUpdateRole($targetUserId) {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return false;
        }
        
        // Admin can update any role
        if ($currentUser['role'] === 'admin') {
            // But can't update their own role
            if ($currentUser['id'] == $targetUserId) {
                return false;
            }
            
            // Check if this is the last admin
            if ($this->isLastAdmin($targetUserId)) {
                return false;
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Check if user can reset password
     */
    public function canResetPassword($targetUserId) {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return false;
        }
        
        // Admin can reset any password
        if ($currentUser['role'] === 'admin') {
            return true;
        }
        
        // Users can only reset their own password
        return $currentUser['id'] == $targetUserId;
    }

    /**
     * Update user role with security checks
     */
    public function updateUserRole($userId, $newRole) {
        if (!$this->canUpdateRole($userId)) {
            return false;
        }
        
        $this->db->query("UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id");
        $this->db->bind(':role', $newRole);
        $this->db->bind(':id', $userId);
        
        return $this->db->execute();
    }

    /**
     * Reset user password with security checks
     */
    public function resetUserPassword($userId, $newPassword) {
        if (!$this->canResetPassword($userId)) {
            return false;
        }
        
        $this->db->query("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
        $this->db->bind(':password', $this->hashPassword($newPassword));
        $this->db->bind(':id', $userId);
        
        if ($this->db->execute()) {
            // Log password reset
            $this->logPasswordReset($userId);
            
            // If user reset their own password, regenerate session
            if ($userId == $this->getCurrentUser()['id']) {
                $this->regenerateSession();
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Log password reset
     */
    private function logPasswordReset($userId) {
        $this->db->query("INSERT INTO user_activity (user_id, activity, details, ip_address, user_agent) 
                          VALUES (:user_id, 'reset_password', :details, :ip_address, :user_agent)");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':details', 'Password reset by ' . $this->getCurrentUser()['username']);
        $this->db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
        $this->db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
        return $this->db->execute();
    }

    /**
     * Regenerate session for security
     */
    private function regenerateSession() {
        Session::regenerate();
        $user = $this->getCurrentUser();
        if ($user) {
            Session::set('user', $user);
            Session::set('logged_in', true);
        }
    }

    /**
     * Check if user can change another user's status
     */
    public function canChangeUserStatus($targetUserId) {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return false;
        }
        
        // Admin can change any user's status except their own
        if ($currentUser['role'] === 'admin') {
            // Don't allow changing own status
            if ($currentUser['id'] == $targetUserId) {
                return false;
            }
            
            // Don't allow deactivating the last admin
            if ($this->isLastAdmin($targetUserId)) {
                return false;
            }
            
            return true;
        }
        
        return false;
    }
}