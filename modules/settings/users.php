<?php
/**
 * Megabre StokMaster Pro
 * User Management
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Check if user has admin access
if (!$auth->hasAccess('admin')) {
    Session::setFlash('error', t('access_denied', 'Bu sayfaya erişim izniniz yok.'));
    redirect('index.php?module=settings');
}

// Initialize database connection
$db = Database::getInstance();

// Add profile_image column to users table if not exists
$db->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
$profileImageExists = $db->single();
if (!$profileImageExists) {
    $db->query("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER email");
    $db->execute();
}

// Create roles table if not exists
$db->query("CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->execute();

// Insert default roles if not exists
$db->query("INSERT IGNORE INTO roles (name, description) VALUES 
    ('admin', '" . t('settings_users_role_admin', 'Yönetici (Tam Yetki)') . "'),
    ('manager', '" . t('settings_users_role_manager', 'Müdür (Düzenleme Yetkisi)') . "'),
    ('accountant', '" . t('settings_users_role_accountant', 'Muhasebeci (Mali Yetki)') . "'),
    ('staff', '" . t('settings_users_role_staff', 'Personel (Sınırlı Yetki)') . "'),
    ('viewer', '" . t('settings_users_role_viewer', 'İzleyici (Sadece Görüntüleme)') . "')");
$db->execute();

// Get subaction
$subaction = isset($_GET['subaction']) ? $_GET['subaction'] : '';

// Process subactions
switch ($subaction) {
    case 'add':
        // Add user form
        
        // Process form submission
        if (isPost()) {
            // Validate CSRF token
            if (!validateCsrf()) {
                redirect('index.php?module=settings&action=users');
            }
            
            // Get form data
            $username = post('username');
            $name = post('name');
            $surname = post('surname');
            $email = post('email');
            $password = post('password');
            $passwordConfirm = post('password_confirm');
            $role = post('role');
            $status = post('status');
            
            // Validate form data
            $errors = [];
            
            if (empty($username)) {
                $errors[] = t('settings_users_username_required', 'Kullanıcı adı gereklidir.');
            } elseif (strlen($username) < 3 || strlen($username) > 50) {
                $errors[] = t('settings_users_username_length', 'Kullanıcı adı 3-50 karakter arasında olmalıdır.');
            } else {
                // Check if username exists
                $db->query("SELECT COUNT(*) as count FROM users WHERE username = :username");
                $db->bind(':username', $username);
                $result = $db->single();
                
                if ($result['count'] > 0) {
                    $errors[] = t('settings_users_username_exists', 'Bu kullanıcı adı zaten kullanılmaktadır.');
                }
            }
            
            if (empty($name)) {
                $errors[] = t('settings_users_name_required', 'Ad gereklidir.');
            }
            
            if (empty($surname)) {
                $errors[] = t('settings_users_surname_required', 'Soyad gereklidir.');
            }
            
            if (empty($email)) {
                $errors[] = t('settings_users_email_required', 'E-posta adresi gereklidir.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = t('settings_users_email_invalid', 'Geçerli bir e-posta adresi giriniz.');
            } else {
                // Check if email exists
                $db->query("SELECT COUNT(*) as count FROM users WHERE email = :email");
                $db->bind(':email', $email);
                $result = $db->single();
                
                if ($result['count'] > 0) {
                    $errors[] = t('settings_users_email_exists', 'Bu e-posta adresi zaten kullanılmaktadır.');
                }
            }
            
            if (empty($password)) {
                $errors[] = t('settings_users_password_required', 'Şifre gereklidir.');
            } elseif (strlen($password) < 6) {
                $errors[] = t('settings_users_password_min', 'Şifre en az 6 karakter olmalıdır.');
            } elseif ($password !== $passwordConfirm) {
                $errors[] = t('settings_users_password_mismatch', 'Şifreler eşleşmiyor.');
            }
            
            if (empty($role)) {
                $errors[] = t('settings_users_role_required', 'Kullanıcı rolü gereklidir.');
            }
            
            // Handle profile image upload
            $profileImage = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 500 * 1024; // 500KB
                
                if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
                    $errors[] = t('settings_users_profile_image_format', 'Profil resmi sadece JPEG, PNG veya GIF formatında olabilir.');
                } elseif ($_FILES['profile_image']['size'] > $maxSize) {
                    $errors[] = t('settings_users_profile_image_size', 'Profil resmi boyutu maksimum 500KB olabilir.');
                } else {
                    $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $profileImage = 'profile_' . time() . '.' . $extension;
                    $uploadPath = UPLOADS_PATH . 'profile/';
                    
                    // Create directory if not exists
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    
                    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath . $profileImage)) {
                        $errors[] = t('settings_users_profile_image_upload_error', 'Profil resmi yüklenirken bir hata oluştu.');
                        $profileImage = null;
                    }
                }
            }
            
            if (empty($errors)) {
                try {
                    // Begin transaction
                    $db->beginTransaction();
                    
                    // Hash password
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user
                    $db->query("INSERT INTO users (username, password, name, surname, email, role, status, profile_image, created_at) 
                               VALUES (:username, :password, :name, :surname, :email, :role, :status, :profile_image, NOW())");
                    $db->bind(':username', $username);
                    $db->bind(':password', $passwordHash);
                    $db->bind(':name', $name);
                    $db->bind(':surname', $surname);
                    $db->bind(':email', $email);
                    $db->bind(':role', $role);
                    $db->bind(':status', $status);
                    $db->bind(':profile_image', $profileImage);
                    $db->execute();
                    
                    $newUserId = $db->lastInsertId();
                    
                    // Log activity
                    logActivity('add_user', 'user', $newUserId, null, [
                        'username' => $username,
                        'name' => $name,
                        'surname' => $surname,
                        'email' => $email,
                        'role' => $role,
                        'status' => $status
                    ], "Yeni kullanıcı eklendi: {$username}");
                    
                    // Commit transaction
                    $db->endTransaction();
                    
                    // Set success message
                    Session::setFlash('success', t('settings_users_add_success', 'Kullanıcı başarıyla eklendi.'));
                    
                    // Redirect to users list
                    redirect('index.php?module=settings&action=users');
                    
                } catch (Exception $e) {
                    // Rollback transaction
                    $db->cancelTransaction();
                    
                    // Delete uploaded image if exists
                    if ($profileImage && file_exists(UPLOADS_PATH . 'profile/' . $profileImage)) {
                        unlink(UPLOADS_PATH . 'profile/' . $profileImage);
                    }
                    
                    $errors[] = t('settings_users_add_error', 'Kullanıcı eklenirken bir hata oluştu: ') . $e->getMessage();
                }
            }
        }
        
        // Include header
        include_once INCLUDES_PATH . 'header.php';
        
        // Display errors
        if (!empty($errors)) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">';
            foreach ($errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('settings_users_add_title', 'Yeni Kullanıcı Ekle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings&action=users'); ?>"><?php echo t('settings_users_title', 'Kullanıcı Yönetimi'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('settings_users_add_title', 'Yeni Kullanıcı Ekle'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Add User Form -->
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('settings_users_user_info', 'Kullanıcı Bilgileri'); ?></h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=settings&action=users&subaction=add'); ?>" method="post" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label required"><?php echo t('settings_users_username', 'Kullanıcı Adı'); ?></label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo post('username', ''); ?>" required>
                                <small class="text-muted"><?php echo t('settings_users_username_desc', 'Giriş için kullanılacak benzersiz kullanıcı adı'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label required"><?php echo t('settings_users_name', 'Ad'); ?></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo post('name', ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="surname" class="form-label required"><?php echo t('settings_users_surname', 'Soyad'); ?></label>
                                <input type="text" class="form-control" id="surname" name="surname" value="<?php echo post('surname', ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label required"><?php echo t('email', 'E-posta'); ?></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo post('email', ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label required"><?php echo t('settings_users_role', 'Kullanıcı Rolü'); ?></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value=""><?php echo t('select', 'Seçiniz'); ?></option>
                                    <option value="admin" <?php echo post('role') == 'admin' ? 'selected' : ''; ?>><?php echo t('settings_users_role_admin', 'Yönetici (Tam Yetki)'); ?></option>
                                    <option value="manager" <?php echo post('role') == 'manager' ? 'selected' : ''; ?>><?php echo t('settings_users_role_manager', 'Müdür (Düzenleme Yetkisi)'); ?></option>
                                    <option value="accountant" <?php echo post('role') == 'accountant' ? 'selected' : ''; ?>><?php echo t('settings_users_role_accountant', 'Muhasebeci (Mali Yetki)'); ?></option>
                                    <option value="staff" <?php echo post('role') == 'staff' ? 'selected' : ''; ?>><?php echo t('settings_users_role_staff', 'Personel (Sınırlı Yetki)'); ?></option>
                                    <option value="viewer" <?php echo post('role') == 'viewer' ? 'selected' : ''; ?>><?php echo t('settings_users_role_viewer', 'İzleyici (Sadece Görüntüleme)'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label required"><?php echo t('settings_users_password', 'Şifre'); ?></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted"><?php echo t('settings_users_password_min_desc', 'En az 6 karakter'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label required"><?php echo t('settings_users_password_confirm', 'Şifre Tekrar'); ?></label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label required"><?php echo t('settings_users_status', 'Durum'); ?></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="1" <?php echo post('status', '1') === '1' ? 'selected' : ''; ?>><?php echo t('settings_users_status_active', 'Aktif'); ?></option>
                                    <option value="0" <?php echo post('status') === '0' ? 'selected' : ''; ?>><?php echo t('settings_users_status_inactive', 'Pasif'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="profile_image" class="form-label"><?php echo t('settings_users_profile_image', 'Profil Resmi'); ?></label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif">
                                <small class="text-muted"><?php echo t('settings_users_profile_image_desc', 'Maksimum 500KB, JPG, PNG veya GIF'); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo t('settings_users_save', 'Kullanıcıyı Kaydet'); ?>
                        </button>
                        <a href="<?php echo url('index.php?module=settings&action=users'); ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-arrow-left"></i> <?php echo t('settings_users_cancel', 'İptal'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
        
    case 'edit':
        // Edit user form
        
        // Get user ID
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($userId <= 0) {
            Session::setFlash('error', t('settings_users_invalid_id', 'Geçersiz kullanıcı ID\'si.'));
            redirect('index.php?module=settings&action=users');
        }
        
        // Get user data
        $db->query("SELECT * FROM users WHERE id = :id");
        $db->bind(':id', $userId);
        $user = $db->single();
        
        if (!$user) {
            Session::setFlash('error', t('settings_users_not_found', 'Kullanıcı bulunamadı.'));
            redirect('index.php?module=settings&action=users');
        }
        
        // Process form submission
        if (isPost()) {
            // Validate CSRF token
            if (!validateCsrf()) {
                redirect('index.php?module=settings&action=users');
            }
            
            // Get form data
            $name = post('name');
            $surname = post('surname');
            $email = post('email');
            $role = post('role');
            $status = post('status');
            $password = post('password');
            $passwordConfirm = post('password_confirm');
            
            // Validate form data
            $errors = [];
            
            if (empty($name)) {
                $errors[] = t('settings_users_name_required', 'Ad gereklidir.');
            }
            
            if (empty($surname)) {
                $errors[] = t('settings_users_surname_required', 'Soyad gereklidir.');
            }
            
            if (empty($email)) {
                $errors[] = t('settings_users_email_required', 'E-posta adresi gereklidir.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = t('settings_users_email_invalid', 'Geçerli bir e-posta adresi giriniz.');
            } else {
                // Check if email exists for other users
                $db->query("SELECT COUNT(*) as count FROM users WHERE email = :email AND id != :id");
                $db->bind(':email', $email);
                $db->bind(':id', $userId);
                $result = $db->single();
                
                if ($result['count'] > 0) {
                    $errors[] = t('settings_users_email_exists_other', 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılmaktadır.');
                }
            }
            
            // Check role update permission
            if ($role !== $user['role'] && !$auth->canUpdateRole($userId)) {
                $errors[] = t('settings_users_role_update_denied', 'Bu kullanıcının rolünü değiştirme yetkiniz yok.');
            }
            
            // Check status change permission
            if ($status != $user['status'] && !$auth->canChangeUserStatus($userId)) {
                $errors[] = t('settings_users_status_change_denied', 'Bu kullanıcının durumunu değiştirme yetkiniz yok.');
            }
            
            // Check password if provided
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $errors[] = t('settings_users_password_min', 'Şifre en az 6 karakter olmalıdır.');
                } elseif ($password !== $passwordConfirm) {
                    $errors[] = t('settings_users_password_mismatch', 'Şifreler eşleşmiyor.');
                }
            }
            
            // Handle profile image upload
            $profileImage = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 500 * 1024; // 500KB
                
                if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
                    $errors[] = t('settings_users_profile_image_format', 'Profil resmi sadece JPEG, PNG veya GIF formatında olabilir.');
                } elseif ($_FILES['profile_image']['size'] > $maxSize) {
                    $errors[] = t('settings_users_profile_image_size', 'Profil resmi boyutu maksimum 500KB olabilir.');
                } else {
                    $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $profileImage = 'profile_' . time() . '.' . $extension;
                    $uploadPath = UPLOADS_PATH . 'profile/';
                    
                    // Create directory if not exists
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    
                    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath . $profileImage)) {
                        $errors[] = t('settings_users_profile_image_upload_error', 'Profil resmi yüklenirken bir hata oluştu.');
                        $profileImage = null;
                    }
                }
            }
            
            if (empty($errors)) {
                try {
                    // Begin transaction
                    $db->beginTransaction();
                    
                    // Update user
                    $query = "UPDATE users SET 
                              name = :name,
                              surname = :surname, 
                              email = :email, 
                              status = :status";
                    
                    // Add role to query if changed and allowed
                    if ($role !== $user['role'] && $auth->canUpdateRole($userId)) {
                        $query .= ", role = :role";
                    }
                    
                    // Add password to query if provided
                    if (!empty($password)) {
                        $query .= ", password = :password";
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
                    $db->bind(':status', $status);
                    
                    if ($role !== $user['role'] && $auth->canUpdateRole($userId)) {
                        $db->bind(':role', $role);
                    }
                    
                    if (!empty($password)) {
                        $db->bind(':password', password_hash($password, PASSWORD_DEFAULT));
                    }
                    
                    if ($profileImage) {
                        $db->bind(':profile_image', $profileImage);
                    }
                    
                    $db->bind(':id', $userId);
                    $db->execute();
                    
                    // Commit transaction
                    $db->endTransaction();
                    
                    // Set success message
                    Session::setFlash('success', t('settings_users_update_success', 'Kullanıcı başarıyla güncellendi.'));
                    
                    // Redirect to users list
                    redirect('index.php?module=settings&action=users');
                    
                } catch (Exception $e) {
                    // Rollback transaction
                    $db->cancelTransaction();
                    
                    // Delete uploaded image if exists
                    if ($profileImage && file_exists(UPLOADS_PATH . 'profile/' . $profileImage)) {
                        unlink(UPLOADS_PATH . 'profile/' . $profileImage);
                    }
                    
                    $errors[] = t('settings_users_update_error', 'Kullanıcı güncellenirken bir hata oluştu: ') . $e->getMessage();
                }
            }
        }
        
        // Include header
        include_once INCLUDES_PATH . 'header.php';
        
        // Display errors
        if (!empty($errors)) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">';
            foreach ($errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('settings_users_edit_title', 'Kullanıcı Düzenle'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings&action=users'); ?>"><?php echo t('settings_users_list_title', 'Kullanıcı Yönetimi'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('settings_users_edit_title', 'Kullanıcı Düzenle'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Edit User Form -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('settings_users_user_info', 'Kullanıcı Bilgileri'); ?></h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=settings&action=users&subaction=edit&id=' . $userId); ?>" method="post" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label"><?php echo t('settings_users_username', 'Kullanıcı Adı'); ?></label>
                                <input type="text" class="form-control" id="username" value="<?php echo e($user['username']); ?>" readonly disabled>
                                <small class="text-muted"><?php echo t('settings_users_username_readonly', 'Kullanıcı adı değiştirilemez'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label required"><?php echo t('settings_users_name', 'Ad'); ?></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($user['name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="surname" class="form-label required"><?php echo t('settings_users_surname', 'Soyad'); ?></label>
                                <input type="text" class="form-control" id="surname" name="surname" value="<?php echo e($user['surname'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label required"><?php echo t('email', 'E-posta'); ?></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo e($user['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label required"><?php echo t('settings_users_role', 'Kullanıcı Rolü'); ?></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>><?php echo t('settings_users_role_admin', 'Yönetici (Tam Yetki)'); ?></option>
                                    <option value="manager" <?php echo $user['role'] == 'manager' ? 'selected' : ''; ?>><?php echo t('settings_users_role_manager', 'Müdür (Düzenleme Yetkisi)'); ?></option>
                                    <option value="accountant" <?php echo $user['role'] == 'accountant' ? 'selected' : ''; ?>><?php echo t('settings_users_role_accountant', 'Muhasebeci (Mali Yetki)'); ?></option>
                                    <option value="staff" <?php echo $user['role'] == 'staff' ? 'selected' : ''; ?>><?php echo t('settings_users_role_staff', 'Personel (Sınırlı Yetki)'); ?></option>
                                    <option value="viewer" <?php echo $user['role'] == 'viewer' ? 'selected' : ''; ?>><?php echo t('settings_users_role_viewer', 'İzleyici (Sadece Görüntüleme)'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label"><?php echo t('settings_users_password_new', 'Yeni Şifre'); ?> <small>(<?php echo t('settings_users_password_new_desc', 'Boş bırakırsanız değişmez'); ?>)</small></label>
                                <input type="password" class="form-control" id="password" name="password">
                                <small class="text-muted"><?php echo t('settings_users_password_min_desc', 'En az 6 karakter'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label"><?php echo t('settings_users_password_confirm', 'Şifre Tekrar'); ?></label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label required"><?php echo t('settings_users_status', 'Durum'); ?></label>
                                <select class="form-select" id="status" name="status" required <?php echo !$auth->canChangeUserStatus($userId) ? 'disabled' : ''; ?>>
                                    <option value="1" <?php echo $user['status'] == 1 ? 'selected' : ''; ?>><?php echo t('settings_users_status_active', 'Aktif'); ?></option>
                                    <option value="0" <?php echo $user['status'] == 0 ? 'selected' : ''; ?>><?php echo t('settings_users_status_inactive', 'Pasif'); ?></option>
                                </select>
                                <?php if (!$auth->canChangeUserStatus($userId)): ?>
                                <small class="text-muted"><?php echo t('settings_users_status_change_self_denied', 'Kendi durumunuzu değiştiremezsiniz.'); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="profile_image" class="form-label"><?php echo t('settings_users_profile_image', 'Profil Resmi'); ?></label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif">
                                <small class="text-muted"><?php echo t('settings_users_profile_image_desc', 'Maksimum 500KB, JPG, PNG veya GIF'); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo t('settings_users_save_changes', 'Değişiklikleri Kaydet'); ?>
                        </button>
                        <a href="<?php echo url('index.php?module=settings&action=users'); ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-arrow-left"></i> <?php echo t('settings_users_cancel', 'İptal'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('settings_users_user_profile', 'Kullanıcı Profili'); ?></h5>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($user['profile_image'])): ?>
                <div class="mb-3">
                    <img src="<?php echo url('uploads/profile/' . $user['profile_image']); ?>" alt="<?php echo e($user['name'] . ' ' . $user['surname']); ?>" class="img-fluid rounded-circle" style="max-width: 150px;">
                </div>
                <?php else: ?>
                <div class="mb-3">
                    <div class="avatar-placeholder">
                        <i class="fas fa-user-circle fa-7x text-muted"></i>
                    </div>
                </div>
                <?php endif; ?>
                
                <h5><?php echo e($user['name'] . ' ' . $user['surname']); ?></h5>
                <p class="text-muted mb-1"><?php echo e($user['username']); ?></p>
                <p class="text-muted mb-1"><?php echo e($user['email']); ?></p>
                
                <div class="mt-3">
                    <span class="badge bg-<?php echo $user['status'] == 1 ? 'success' : 'danger'; ?>">
                        <?php echo $user['status'] == 1 ? t('settings_users_status_active', 'Aktif') : t('settings_users_status_inactive', 'Pasif'); ?>
                    </span>
                    
                    <span class="badge bg-primary ms-2">
                        <?php 
                        $roles = [
                            'admin' => t('settings_users_role_name_admin', 'Yönetici'),
                            'manager' => t('settings_users_role_name_manager', 'Müdür'),
                            'accountant' => t('settings_users_role_name_accountant', 'Muhasebeci'),
                            'staff' => t('settings_users_role_name_staff', 'Personel'),
                            'viewer' => t('settings_users_role_name_viewer', 'İzleyici')
                        ];
                        echo $roles[$user['role']] ?? t('settings_users_role_name_default', 'Kullanıcı');
                        ?>
                    </span>
                </div>
            </div>
            <div class="card-footer text-muted">
                <div class="mb-1"><small><?php echo t('settings_users_created_at', 'Oluşturma:'); ?> <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></small></div>
                <?php if (!empty($user['updated_at'])): ?>
                <div><small><?php echo t('settings_users_updated_at', 'Son Güncelleme:'); ?> <?php echo date('d.m.Y H:i', strtotime($user['updated_at'])); ?></small></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('settings_users_quick_actions', 'Hızlı İşlemler'); ?></h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo url('index.php?module=settings&action=users&subaction=reset_password&id=' . $userId); ?>" class="btn btn-warning">
                        <i class="fas fa-key"></i> <?php echo t('settings_users_reset_password', 'Şifre Sıfırlama'); ?>
                    </a>
                    
                    <?php if ($user['id'] != $auth->getCurrentUser()['id']): // Don't allow deleting yourself ?>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                        <i class="fas fa-trash"></i> <?php echo t('settings_users_delete_user', 'Kullanıcıyı Sil'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($user['id'] != $auth->getCurrentUser()['id']): // Don't allow deleting yourself ?>
<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteUserModalLabel"><?php echo t('settings_users_delete_user', 'Kullanıcıyı Sil'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><?php echo t('settings_users_delete_confirm', 'Bu kullanıcıyı silmek istediğinize emin misiniz?'); ?></p>
                <p><strong><?php echo e($user['username']); ?> (<?php echo e($user['name'] . ' ' . $user['surname']); ?>)</strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo t('settings_users_delete_warning', 'Bu işlem geri alınamaz!'); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('settings_users_cancel', 'İptal'); ?></button>
                <a href="<?php echo url('index.php?module=settings&action=users&subaction=delete&id=' . $userId); ?>" class="btn btn-danger">
                    <i class="fas fa-trash"></i> <?php echo t('settings_users_delete_user', 'Kullanıcıyı Sil'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
        
    case 'delete':
        // Delete user
        
        // Get user ID
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($userId <= 0) {
            Session::setFlash('error', t('settings_users_invalid_id', 'Geçersiz kullanıcı ID\'si.'));
            redirect('index.php?module=settings&action=users');
        }
        
        // Get user data
        $db->query("SELECT * FROM users WHERE id = :id");
        $db->bind(':id', $userId);
        $user = $db->single();
        
        if (!$user) {
            Session::setFlash('error', t('settings_users_not_found', 'Kullanıcı bulunamadı.'));
            redirect('index.php?module=settings&action=users');
        }
        
        // Don't allow deleting yourself
        if ($user['id'] == $auth->getCurrentUser()['id']) {
            Session::setFlash('error', t('settings_users_delete_self_denied', 'Kendinizi silemezsiniz.'));
            redirect('index.php?module=settings&action=users');
        }
        
        // Don't allow deleting the last admin
        if ($user['role'] == 'admin') {
            $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND id != :id");
            $db->bind(':id', $userId);
            $adminCount = $db->single()['count'];
            
            if ($adminCount == 0) {
                Session::setFlash('error', t('settings_users_delete_last_admin', 'Son yönetici kullanıcısını silemezsiniz.'));
                redirect('index.php?module=settings&action=users');
            }
        }
        
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Log activity before deletion
            logActivity('delete_user', 'user', $userId, [
                'username' => $user['username'],
                'name' => $user['name'],
                'surname' => $user['surname'],
                'email' => $user['email'],
                'role' => $user['role']
            ], null, "Kullanıcı silindi: {$user['username']}");
            
            // Delete user
            $db->query("DELETE FROM users WHERE id = :id");
            $db->bind(':id', $userId);
            $db->execute();
            
            // Delete profile image if exists
            if (!empty($user['profile_image'])) {
                $profileImage = UPLOADS_PATH . 'profile/' . $user['profile_image'];
                if (file_exists($profileImage)) {
                    unlink($profileImage);
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', t('settings_users_delete_success', 'Kullanıcı başarıyla silindi.'));
            
        } catch (Exception $e) {
            // Rollback transaction
            $db->cancelTransaction();
            
            // Set error message
            Session::setFlash('error', t('settings_users_delete_error', 'Kullanıcı silinirken bir hata oluştu: ') . $e->getMessage());
        }
        
        // Redirect to users list
        redirect('index.php?module=settings&action=users');
        break;
        
    case 'reset_password':
        // Reset user password
        
        // Get user ID
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($userId <= 0) {
            Session::setFlash('error', t('settings_users_invalid_id', 'Geçersiz kullanıcı ID\'si.'));
            redirect('index.php?module=settings&action=users');
        }
        
        // Get user data
        $db->query("SELECT * FROM users WHERE id = :id");
        $db->bind(':id', $userId);
        $user = $db->single();
        
        if (!$user) {
            Session::setFlash('error', t('settings_users_not_found', 'Kullanıcı bulunamadı.'));
            redirect('index.php?module=settings&action=users');
        }
        
        // Process form submission
        if (isPost()) {
            // Validate CSRF token
            if (!validateCsrf()) {
                redirect('index.php?module=settings&action=users');
            }
            
            // Get form data
            $password = post('password');
            $passwordConfirm = post('password_confirm');
            
            // Validate form data
            $errors = [];
            
            if (empty($password)) {
                $errors[] = t('settings_users_password_required', 'Şifre gereklidir.');
            } elseif (strlen($password) < 6) {
                $errors[] = t('settings_users_password_min', 'Şifre en az 6 karakter olmalıdır.');
            } elseif ($password !== $passwordConfirm) {
                $errors[] = t('settings_users_password_mismatch', 'Şifreler eşleşmiyor.');
            }
            
            // Check password reset permission
            if (!$auth->canResetPassword($userId)) {
                $errors[] = t('settings_users_reset_password_denied', 'Bu kullanıcının şifresini sıfırlama yetkiniz yok.');
            }
            
            if (empty($errors)) {
                try {
                    // Reset password using auth class
                    if ($auth->resetUserPassword($userId, $password)) {
                        // Set success message
                        Session::setFlash('success', t('settings_users_reset_password_success', 'Kullanıcı şifresi başarıyla sıfırlandı.'));
                        
                        // Redirect to users list
                        redirect('index.php?module=settings&action=users');
                    } else {
                        $errors[] = t('settings_users_reset_password_no_permission', 'Şifre sıfırlama yetkisi yok.');
                    }
                } catch (Exception $e) {
                    $errors[] = t('settings_users_reset_password_error', 'Şifre sıfırlanırken bir hata oluştu:') . ' ' . $e->getMessage();
                }
            }
        }
        
        // Include header
        include_once INCLUDES_PATH . 'header.php';
        
        // Display errors
        if (!empty($errors)) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">';
            foreach ($errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('settings_users_reset_password_title', 'Şifre Sıfırlama'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings&action=users'); ?>"><?php echo t('settings_users_list_title', 'Kullanıcı Yönetimi'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('settings_users_reset_password_title', 'Şifre Sıfırlama'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Reset Password Form -->
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('settings_users_reset_password_title_full', 'Şifre Sıfırlama:'); ?> <?php echo e($user['username']); ?> (<?php echo e($user['name'] . ' ' . $user['surname']); ?>)</h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=settings&action=users&subaction=reset_password&id=' . $userId); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label required"><?php echo t('settings_users_password_new', 'Yeni Şifre'); ?></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="text-muted"><?php echo t('settings_users_password_min_desc', 'En az 6 karakter'); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label required"><?php echo t('settings_users_password_confirm', 'Şifre Tekrar'); ?></label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <?php echo t('settings_users_reset_password_desc', 'Şifre sıfırlandıktan sonra kullanıcı yeni şifre ile giriş yapabilecektir.'); ?>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> <?php echo t('settings_users_reset_password_button', 'Şifreyi Sıfırla'); ?>
                        </button>
                        <a href="<?php echo url('index.php?module=settings&action=users'); ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-arrow-left"></i> <?php echo t('settings_users_cancel', 'İptal'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
        
    default:
        // Show user list
        
        // Get users with roles
        $db->query("SELECT DISTINCT u.*, r.name as role_name, CONCAT(u.name, ' ', u.surname) as full_name 
                    FROM users u 
                    LEFT JOIN roles r ON u.role = r.name 
                    ORDER BY u.id DESC");
        $users = $db->resultSet();
        
        // Include header
        include_once INCLUDES_PATH . 'header.php';
        
        // Show success/error messages
        if (Session::hasFlash('success')) {
            $flash = Session::getFlash('success');
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    ' . $flash['message'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        
        if (Session::hasFlash('error')) {
            $flash = Session::getFlash('error');
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ' . $flash['message'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('settings_users_list_title', 'Kullanıcı Yönetimi'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('settings_users_list_title', 'Kullanıcı Yönetimi'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="<?php echo url('index.php?module=settings&action=users&subaction=add'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> <?php echo t('settings_users_add_new', 'Yeni Kullanıcı'); ?>
            </a>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped datatable">
                <thead>
                    <tr>
                        <th width="80"><?php echo t('settings_users_table_id', 'ID'); ?></th>
                        <th width="60"><?php echo t('settings_users_table_image', 'Resim'); ?></th>
                        <th><?php echo t('settings_users_table_username', 'Kullanıcı Adı'); ?></th>
                        <th><?php echo t('settings_users_table_fullname', 'Ad Soyad'); ?></th>
                        <th><?php echo t('settings_users_table_email', 'E-posta'); ?></th>
                        <th><?php echo t('settings_users_table_role', 'Rol'); ?></th>
                        <th><?php echo t('settings_users_table_status', 'Durum'); ?></th>
                        <th><?php echo t('settings_users_table_created', 'Oluşturma'); ?></th>
                        <th width="150"><?php echo t('settings_users_table_actions', 'İşlemler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo url('uploads/profile/' . $user['profile_image']); ?>" alt="<?php echo e($user['name'] . ' ' . $user['surname']); ?>" class="rounded-circle" width="40" height="40">
                            <?php else: ?>
                            <div class="avatar-placeholder rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-user text-muted"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($user['username']); ?></td>
                        <td><?php echo e($user['name'] . ' ' . $user['surname']); ?></td>
                        <td><?php echo e($user['email']); ?></td>
                        <td>
                            <?php 
                            $roleBadge = 'primary';
                            $roleName = t('settings_users_role_name_default', 'Kullanıcı');
                            
                            switch ($user['role']) {
                                case 'admin':
                                    $roleBadge = 'danger';
                                    $roleName = t('settings_users_role_name_admin', 'Yönetici');
                                    break;
                                case 'manager':
                                    $roleBadge = 'warning';
                                    $roleName = t('settings_users_role_name_manager', 'Müdür');
                                    break;
                                case 'accountant':
                                    $roleBadge = 'info';
                                    $roleName = t('settings_users_role_name_accountant', 'Muhasebeci');
                                    break;
                                case 'staff':
                                    $roleBadge = 'success';
                                    $roleName = t('settings_users_role_name_staff', 'Personel');
                                    break;
                                case 'viewer':
                                    $roleBadge = 'secondary';
                                    $roleName = t('settings_users_role_name_viewer', 'İzleyici');
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $roleBadge; ?>"><?php echo $roleName; ?></span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $user['status'] == 1 ? 'success' : 'danger'; ?>">
                                <?php echo $user['status'] == 1 ? t('settings_users_status_active', 'Aktif') : t('settings_users_status_inactive', 'Pasif'); ?>
                            </span>
                        </td>
                        <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="<?php echo url('index.php?module=settings&action=users&subaction=edit&id=' . $user['id']); ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="<?php echo t('settings_users_edit_tooltip', 'Düzenle'); ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <a href="<?php echo url('index.php?module=settings&action=users&subaction=reset_password&id=' . $user['id']); ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="<?php echo t('settings_users_reset_password_tooltip', 'Şifre Sıfırla'); ?>">
                                    <i class="fas fa-key"></i>
                                </a>
                                
                                <?php if ($user['id'] != $auth->getCurrentUser()['id']): // Don't allow deleting yourself ?>
                                <a href="<?php echo url('index.php?module=settings&action=users&subaction=delete&id=' . $user['id']); ?>" class="btn btn-sm btn-danger delete-confirm" data-bs-toggle="tooltip" title="<?php echo t('settings_users_delete_tooltip', 'Sil'); ?>" data-user-name="<?php echo e($user['username']); ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-danger" disabled data-bs-toggle="tooltip" title="<?php echo t('settings_users_delete_self_tooltip', 'Kendinizi Silemezsiniz'); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Role Information -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title"><?php echo t('settings_users_roles_title', 'Kullanıcı Rolleri ve Yetkileri'); ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-4">
                    <h6 class="text-danger"><?php echo t('settings_users_role_admin_title', 'Yönetici (Admin)'); ?></h6>
                    <p class="text-muted mb-2"><?php echo t('settings_users_role_admin_desc', 'Tam yetkili kullanıcı, tüm sistem özelliklerine erişebilir.'); ?></p>
                    <ul class="small">
                        <li><?php echo t('settings_users_role_admin_feature1', 'Tüm modüllere tam erişim'); ?></li>
                        <li><?php echo t('settings_users_role_admin_feature2', 'Kullanıcı yönetimi'); ?></li>
                        <li><?php echo t('settings_users_role_admin_feature3', 'Sistem ayarları'); ?></li>
                        <li><?php echo t('settings_users_role_admin_feature4', 'Tüm verileri düzenleme, silme'); ?></li>
                    </ul>
                </div>
                
                <div class="mb-4">
                    <h6 class="text-warning"><?php echo t('settings_users_role_manager_title', 'Müdür (Manager)'); ?></h6>
                    <p class="text-muted mb-2"><?php echo t('settings_users_role_manager_desc', 'Genel işlemler ve yönetim için geniş yetkiler.'); ?></p>
                    <ul class="small">
                        <li><?php echo t('settings_users_role_manager_feature1', 'Ürün, kategori, stok, müşteri ve sipariş yönetimi'); ?></li>
                        <li><?php echo t('settings_users_role_manager_feature2', 'Mali işlemlere erişim'); ?></li>
                        <li><?php echo t('settings_users_role_manager_feature3', 'Raporlara erişim'); ?></li>
                        <li><?php echo t('settings_users_role_manager_feature4', 'Kullanıcı yönetimi hariç'); ?></li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-4">
                    <h6 class="text-info"><?php echo t('settings_users_role_accountant_title', 'Muhasebeci (Accountant)'); ?></h6>
                    <p class="text-muted mb-2"><?php echo t('settings_users_role_accountant_desc', 'Mali işlemler ve raporlar için yetkilendirilmiş.'); ?></p>
                    <ul class="small">
                        <li><?php echo t('settings_users_role_accountant_feature1', 'Mali işlemlere tam erişim'); ?></li>
                        <li><?php echo t('settings_users_role_accountant_feature2', 'Müşteri ve sipariş yönetimi'); ?></li>
                        <li><?php echo t('settings_users_role_accountant_feature3', 'Raporlara erişim'); ?></li>
                        <li><?php echo t('settings_users_role_accountant_feature4', 'Ürün/Kategori düzenleme yetkisi yok'); ?></li>
                    </ul>
                </div>
                
                <div class="mb-4">
                    <h6 class="text-success"><?php echo t('settings_users_role_staff_title', 'Personel (Staff)'); ?></h6>
                    <p class="text-muted mb-2"><?php echo t('settings_users_role_staff_desc', 'Günlük işlemler için sınırlı yetkiler.'); ?></p>
                    <ul class="small">
                        <li><?php echo t('settings_users_role_staff_feature1', 'Sipariş oluşturma ve düzenleme'); ?></li>
                        <li><?php echo t('settings_users_role_staff_feature2', 'Stok hareketleri'); ?></li>
                        <li><?php echo t('settings_users_role_staff_feature3', 'Müşteri ekleme/düzenleme'); ?></li>
                        <li><?php echo t('settings_users_role_staff_feature4', 'Mali işlem ve ayarlara erişim yok'); ?></li>
                    </ul>
                </div>
                
                <div class="mb-4">
                    <h6 class="text-secondary"><?php echo t('settings_users_role_viewer_title', 'İzleyici (Viewer)'); ?></h6>
                    <p class="text-muted mb-2"><?php echo t('settings_users_role_viewer_desc', 'Sadece görüntüleme yetkisi.'); ?></p>
                    <ul class="small">
                        <li><?php echo t('settings_users_role_viewer_feature1', 'Tüm verileri görüntüleme'); ?></li>
                        <li><?php echo t('settings_users_role_viewer_feature2', 'Hiçbir şeyi düzenleyemez veya silemez'); ?></li>
                        <li><?php echo t('settings_users_role_viewer_feature3', 'Raporlara erişim'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Destroy existing DataTable instance if exists
        if ($.fn.DataTable.isDataTable('.datatable')) {
            $('.datatable').DataTable().destroy();
        }
        
        // Initialize DataTable
        $('.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
            }
        });
        
        // Delete confirmation
        $('.delete-confirm').on('click', function(e) {
            e.preventDefault();
            
            const userName = $(this).data('user-name');
            const href = $(this).attr('href');
            const confirmText = '<?php echo addslashes(t('settings_users_delete_confirm_js', ' silinecek. Bu işlem geri alınamaz! Devam etmek istiyor musunuz?')); ?>';
            
            if (confirm('Kullanıcı "' + userName + '"' + confirmText)) {
                window.location.href = href;
            }
        });
    });
</script>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
}
?>