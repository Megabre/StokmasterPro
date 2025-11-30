<?php
/**
 * Megabre StokMaster Pro
 * Profile Index
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Initialize database connection
$db = Database::getInstance();

// Create user_activity table if not exists
$db->query("CREATE TABLE IF NOT EXISTS user_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->execute();

// Check and update users table structure
$db->query("SHOW COLUMNS FROM users LIKE 'name'");
if (!$db->single()) {
    $db->query("ALTER TABLE users ADD COLUMN name VARCHAR(50) AFTER username");
    $db->execute();
}

$db->query("SHOW COLUMNS FROM users LIKE 'surname'");
if (!$db->single()) {
    $db->query("ALTER TABLE users ADD COLUMN surname VARCHAR(50) AFTER name");
    $db->execute();
}

// Update existing users with default values if needed
$db->query("UPDATE users SET name = username, surname = '' WHERE name IS NULL OR surname IS NULL");
$db->execute();

// Get user data
$db->query("SELECT u.*, CONCAT(u.name, ' ', u.surname) as full_name FROM users u WHERE u.id = :id");
$db->bind(':id', $auth->getCurrentUser()['id']);
$user = $db->single();

if (!$user) {
    Session::setFlash('error', t('profile_not_found', 'Kullanıcı bulunamadı.'));
    redirect('index.php');
}

// Get user activity
$db->query("SELECT * FROM user_activity WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
$db->bind(':user_id', $auth->getCurrentUser()['id']);
$activities = $db->resultSet();

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
            <h3 class="page-title"><?php echo t('profile_title', 'Profil'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('profile_my_profile', 'Profilim'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="<?php echo url('index.php?module=profile&action=change-password'); ?>" class="btn btn-warning">
                <i class="fas fa-key"></i> <?php echo t('profile_change_password', 'Şifre Değiştir'); ?>
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <!-- User Profile Card -->
        <div class="card">
            <div class="card-body text-center">
                <?php if (!empty($user['profile_image'])): ?>
                <div class="mb-4">
                    <img src="<?php echo url('uploads/profile/' . $user['profile_image']); ?>" alt="<?php echo e($user['full_name']); ?>" class="img-fluid rounded-circle" style="max-width: 150px;">
                </div>
                <?php else: ?>
                <div class="mb-4">
                    <div class="profile-avatar">
                        <i class="fas fa-user-circle fa-7x text-muted"></i>
                    </div>
                </div>
                <?php endif; ?>
                
                <h4 class="mb-1"><?php echo e($user['full_name']); ?></h4>
                <p class="text-muted mb-2"><?php echo e($user['username']); ?></p>
                
                <div class="mb-3">
                    <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                        <?php echo $user['status'] == 'active' ? t('active', 'Aktif') : t('inactive', 'Pasif'); ?>
                    </span>
                    
                    <span class="badge bg-primary ms-2">
                        <?php 
                        $roles = [
                            'admin' => t('users_admin', 'Yönetici'),
                            'manager' => t('users_manager', 'Müdür'),
                            'accountant' => t('users_accountant', 'Muhasebeci'),
                            'staff' => t('users_staff', 'Personel'),
                            'viewer' => t('users_viewer', 'İzleyici')
                        ];
                        echo $roles[$user['role']] ?? $user['role'];
                        ?>
                    </span>
                </div>
                
                <hr>
                
                <div class="user-details text-start">
                    <div class="mb-2">
                        <i class="fas fa-envelope me-2"></i> <?php echo e($user['email']); ?>
                    </div>
                    
                    <div class="mb-2">
                        <i class="fas fa-clock me-2"></i> <?php echo t('profile_last_login', 'Son Giriş'); ?>: 
                        <?php 
                        $db->query("SELECT created_at FROM user_activity WHERE user_id = :user_id AND activity = 'login' ORDER BY created_at DESC LIMIT 1");
                        $db->bind(':user_id', $auth->getCurrentUser()['id']);
                        $lastLogin = $db->single();
                        echo $lastLogin ? date('d.m.Y H:i', strtotime($lastLogin['created_at'])) : t('profile_unknown', 'Bilinmiyor');
                        ?>
                    </div>
                    
                    <div class="mb-2">
                        <i class="fas fa-user-plus me-2"></i> <?php echo t('profile_registration_date', 'Kayıt Tarihi'); ?>: 
                        <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit Profile Card -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('profile_edit_profile', 'Profil Düzenle'); ?></h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('modules/profile/api.php?action=update'); ?>" method="post" enctype="multipart/form-data" id="profileForm">
                    <?php echo csrfField(); ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label required"><?php echo t('name', 'Ad'); ?></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo e($user['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="surname" class="form-label required"><?php echo t('surname', 'Soyad'); ?></label>
                        <input type="text" class="form-control" id="surname" name="surname" value="<?php echo e($user['surname'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label required"><?php echo t('email', 'E-posta'); ?></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo e($user['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="profile_image" class="form-label"><?php echo t('profile_image', 'Profil Resmi'); ?></label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif">
                        <small class="text-muted"><?php echo t('profile_image_max_size', 'Maksimum 500KB, JPG, PNG veya GIF'); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="language" class="form-label"><?php echo t('profile_language_default', 'Varsayılan Dil'); ?></label>
                        <select class="form-select" id="language" name="language" required>
                            <?php 
                            $language = Language::getInstance();
                            $available_langs = $language->getAvailableLanguages();
                            $current_lang = !empty($user['language']) ? $user['language'] : $language->getCurrentLanguage();
                            foreach ($available_langs as $lang_code => $lang_info): 
                            ?>
                            <option value="<?php echo $lang_code; ?>" <?php echo $current_lang == $lang_code ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lang_info['native_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><?php echo t('profile_language_note', 'Profilinizde seçtiğiniz dil, oturum açtığınızda varsayılan olarak yüklenecektir.'); ?></small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo t('profile_update', 'Profili Güncelle'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Dashboard Stats -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('profile_activity_summary', 'Aktivite Özeti'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    // Get user stats
                    $db->query("SELECT COUNT(*) as count FROM login_logs WHERE user_id = :user_id AND status = 'success'");
                    $db->bind(':user_id', $auth->getCurrentUser()['id']);
                    $loginCount = $db->single()['count'] ?? 0;
                    
                    $db->query("SELECT COUNT(*) as count FROM orders WHERE created_by = :username");
                    $db->bind(':username', $auth->getCurrentUser()['username']);
                    $orderCount = $db->single()['count'] ?? 0;
                    
                    $db->query("SELECT COUNT(*) as count FROM stock_movements WHERE created_by = :username");
                    $db->bind(':username', $auth->getCurrentUser()['username']);
                    $stockCount = $db->single()['count'] ?? 0;
                    
                    $db->query("SELECT COUNT(*) as count FROM customers WHERE created_by = :username");
                    $db->bind(':username', $auth->getCurrentUser()['username']);
                    $customerCount = $db->single()['count'] ?? 0;
                    ?>
                    
                    <div class="col-md-3">
                        <div class="stats-info">
                            <h6><?php echo t('profile_logins', 'Girişler'); ?></h6>
                            <h4><?php echo $loginCount; ?></h4>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stats-info">
                            <h6><?php echo t('orders_title', 'Siparişler'); ?></h6>
                            <h4><?php echo $orderCount; ?></h4>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stats-info">
                            <h6><?php echo t('profile_stock_operations', 'Stok İşlemleri'); ?></h6>
                            <h4><?php echo $stockCount; ?></h4>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="stats-info">
                            <h6><?php echo t('customers_title', 'Müşteriler'); ?></h6>
                            <h4><?php echo $customerCount; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('profile_recent_activities', 'Son Aktiviteler'); ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($activities)): ?>
                <div class="activity-timeline">
                    <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?php 
                            $icon = 'fas fa-check-circle';
                            $color = 'primary';
                            
                            switch ($activity['activity']) {
                                case 'login':
                                    $icon = 'fas fa-sign-in-alt';
                                    $color = 'success';
                                    break;
                                case 'logout':
                                    $icon = 'fas fa-sign-out-alt';
                                    $color = 'warning';
                                    break;
                                case 'create_order':
                                    $icon = 'fas fa-shopping-cart';
                                    $color = 'info';
                                    break;
                                case 'update_profile':
                                    $icon = 'fas fa-user-edit';
                                    $color = 'primary';
                                    break;
                                case 'change_password':
                                    $icon = 'fas fa-key';
                                    $color = 'danger';
                                    break;
                                case 'add_product':
                                    $icon = 'fas fa-box';
                                    $color = 'success';
                                    break;
                                case 'add_customer':
                                    $icon = 'fas fa-user-plus';
                                    $color = 'info';
                                    break;
                                case 'stock_movement':
                                    $icon = 'fas fa-dolly';
                                    $color = 'warning';
                                    break;
                            }
                            ?>
                            <i class="<?php echo $icon; ?> text-<?php echo $color; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-date">
                                <?php echo date('d.m.Y H:i', strtotime($activity['created_at'])); ?>
                            </div>
                            <p class="mb-0">
                                <?php 
                                $activityText = '';
                                switch ($activity['activity']) {
                                    case 'login':
                                        $activityText = t('activity_login', 'Sisteme giriş yapıldı');
                                        break;
                                    case 'logout':
                                        $activityText = t('activity_logout', 'Sistemden çıkış yapıldı');
                                        break;
                                    case 'create_order':
                                        $activityText = t('activity_create_order', 'Yeni sipariş oluşturuldu');
                                        break;
                                    case 'update_profile':
                                        $activityText = t('activity_update_profile', 'Profil güncellendi');
                                        break;
                                    case 'change_password':
                                        $activityText = t('activity_change_password', 'Şifre değiştirildi');
                                        break;
                                    case 'add_product':
                                        $activityText = t('activity_add_product', 'Yeni ürün eklendi');
                                        break;
                                    case 'add_customer':
                                        $activityText = t('activity_add_customer', 'Yeni müşteri eklendi');
                                        break;
                                    case 'stock_movement':
                                        $activityText = t('activity_stock_movement', 'Stok hareketi gerçekleştirildi');
                                        break;
                                    default:
                                        $activityText = $activity['activity'];
                                }
                                
                                echo $activityText;
                                
                                if (!empty($activity['details'])) {
                                    echo ': ' . $activity['details'];
                                }
                                ?>
                            </p>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-globe me-1"></i> <?php echo e($activity['ip_address']); ?>
                                <i class="fas fa-desktop ms-3 me-1"></i> <?php echo e($activity['user_agent']); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> <?php echo t('profile_no_activity', 'Henüz aktivite kaydı bulunmamaktadır.'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Permissions -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('profile_permissions', 'İzinler ve Yetkiler'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle"></i> <?php echo t('profile_access_info', 'Aşağıda mevcut kullanıcı rolünüz için tanımlanmış erişim izinleri listelenmektedir.'); ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-2"><?php echo t('profile_module_access', 'Modül Erişimi'); ?></h6>
                            
                            <?php 
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
                            
                            foreach ($modulePermissions as $module => $roles): 
                                $hasAccess = in_array($user['role'], $roles);
                            ?>
                            <div class="permission-item">
                                <i class="fas fa-<?php echo $hasAccess ? 'check text-success' : 'times text-danger'; ?> me-2"></i>
                                <?php echo ucfirst($module); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-2"><?php echo t('profile_action_permissions', 'İşlem İzinleri'); ?></h6>
                            
                            <?php 
                            $actionPermissions = [
                                t('profile_permission_product_add_edit', 'Ürün Ekleme/Düzenleme') => ['admin', 'manager'],
                                t('profile_permission_product_delete', 'Ürün Silme') => ['admin'],
                                t('profile_permission_stock_add', 'Stok Ekleme') => ['admin', 'manager', 'staff'],
                                t('profile_permission_order_create', 'Sipariş Oluşturma') => ['admin', 'manager', 'accountant', 'staff'],
                                t('profile_permission_order_cancel', 'Sipariş İptal') => ['admin', 'manager', 'accountant'],
                                t('profile_permission_customer_add_edit', 'Müşteri Ekleme/Düzenleme') => ['admin', 'manager', 'staff'],
                                t('profile_permission_customer_delete', 'Müşteri Silme') => ['admin', 'manager'],
                                t('profile_permission_transaction_add', 'Mali İşlem Ekleme') => ['admin', 'manager', 'accountant'],
                                t('profile_permission_reports_access', 'Raporlara Erişim') => ['admin', 'manager', 'accountant', 'viewer'],
                                t('profile_permission_user_management', 'Kullanıcı Yönetimi') => ['admin'],
                                t('profile_permission_system_settings', 'Sistem Ayarları') => ['admin']
                            ];
                            
                            foreach ($actionPermissions as $action => $roles): 
                                $hasAccess = in_array($user['role'], $roles);
                            ?>
                            <div class="permission-item">
                                <i class="fas fa-<?php echo $hasAccess ? 'check text-success' : 'times text-danger'; ?> me-2"></i>
                                <?php echo $action; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.activity-timeline {
    position: relative;
    padding-left: 30px;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 14px;
    top: 5px;
    bottom: 5px;
    width: 2px;
    background: #e9ecef;
}

.activity-item {
    position: relative;
    padding-bottom: 20px;
    padding-left: 10px;
}

.activity-icon {
    position: absolute;
    left: -30px;
    width: 30px;
    height: 30px;
    background: #fff;
    border-radius: 50%;
    text-align: center;
    line-height: 30px;
}

.activity-content {
    background-color: #f8f9fa;
    border-radius: 4px;
    padding: 12px 15px;
}

.activity-date {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 5px;
}

.permission-item {
    margin-bottom: 8px;
}

.stats-info {
    text-align: center;
    padding: 10px;
    border-radius: 4px;
    background-color: #f8f9fa;
    margin-bottom: 10px;
}

.stats-info h6 {
    color: #6c757d;
    margin-bottom: 5px;
}

.stats-info h4 {
    margin-bottom: 0;
}
</style>

<script>
$(document).ready(function() {
    // Profile form submission
    $('#profileForm').on('submit', function(e) {
        e.preventDefault();
        
        console.log('Profile form submitted');
        console.log('Form action:', $(this).attr('action'));
        console.log('Form data:', $(this).serialize());
        
        // Create form data
        const formData = new FormData(this);
        
        // Log FormData contents
        console.log('FormData contents:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ':', value);
        }
        
        // Submit form via AJAX
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response) {
                console.log('AJAX success response:', response);
                if (response.success) {
                    // Show success message
                    alert('<?php echo t('profile_update_success_message', 'Profil başarıyla güncellendi.'); ?>');
                    
                    // Reload page to apply language changes
                    window.location.reload();
                } else {
                    // Show error message
                    console.error('Response error:', response);
                    alert('<?php echo t('profile_update_error_message', 'Hata:'); ?> ' + (response.message || '<?php echo t('profile_update_error_unknown', 'Bilinmeyen bir hata oluştu'); ?>'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {
                    status: status,
                    error: error,
                    statusCode: xhr.status,
                    responseText: xhr.responseText
                });
                
                let errorMessage = '<?php echo t('profile_update_error_process', 'İşlem sırasında bir hata oluştu.'); ?>';
                
                // Try to parse error response
                if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMessage = errorResponse.message;
                        }
                    } catch (e) {
                        // If not JSON, use default message
                        console.error('Failed to parse error response:', e);
                    }
                }
                
                alert(errorMessage);
                console.error('Profile update error:', xhr.responseText);
            }
        });
    });
});
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>