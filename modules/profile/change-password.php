<?php
/**
 * Megabre StokMaster Pro
 * Change Password
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

// Get user data
$db->query("SELECT * FROM users WHERE id = :id");
$db->bind(':id', $auth->getCurrentUser()['id']);
$user = $db->single();

if (!$user) {
    Session::setFlash('error', t('profile_not_found', 'Kullanıcı bulunamadı.'));
    redirect('index.php');
}

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=profile&action=change-password');
    }
    
    // Get form data
    $currentPassword = post('current_password');
    $newPassword = post('new_password');
    $confirmPassword = post('confirm_password');
    
    // Validate form data
    $errors = [];
    
    if (empty($currentPassword)) {
        $errors[] = t('profile_current_password_required', 'Mevcut şifre gereklidir.');
    } else {
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            $errors[] = t('profile_current_password_wrong', 'Mevcut şifre yanlış.');
        }
    }
    
    if (empty($newPassword)) {
        $errors[] = t('profile_new_password_required', 'Yeni şifre gereklidir.');
    } elseif (strlen($newPassword) < 6) {
        $errors[] = t('profile_new_password_min', 'Yeni şifre en az 6 karakter olmalıdır.');
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = t('profile_password_mismatch', 'Şifreler eşleşmiyor.');
    }
    
    if (empty($errors)) {
        try {
            // Reset password using auth class
            if ($auth->resetUserPassword($auth->getCurrentUser()['id'], $newPassword)) {
                // Log activity
                logActivity('change_password', 'user', $auth->getCurrentUser()['id'], null, null, "Kullanıcı şifresi değiştirildi");
                
                // Set success message
                Session::setFlash('success', t('profile_password_change_success', 'Şifreniz başarıyla değiştirildi.'));
                
                // Redirect to profile
                redirect('index.php?module=profile');
            } else {
                $errors[] = t('profile_password_change_failed', 'Şifre değiştirme işlemi başarısız oldu.');
            }
        } catch (Exception $e) {
            $errors[] = t('profile_password_change_error', 'Şifre değiştirilirken bir hata oluştu: ') . $e->getMessage();
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
            <h3 class="page-title"><?php echo t('profile_change_password_title', 'Şifre Değiştir'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=profile'); ?>"><?php echo t('profile_my_profile', 'Profilim'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('profile_change_password_title', 'Şifre Değiştir'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Change Password Form -->
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('profile_change_password_form_title', 'Şifre Değiştirme Formu'); ?></h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=profile&action=change-password'); ?>" method="post" id="passwordForm">
                    <?php echo csrfField(); ?>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label required"><?php echo t('profile_current_password', 'Mevcut Şifre'); ?></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label required"><?php echo t('profile_new_password', 'Yeni Şifre'); ?></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="text-muted"><?php echo t('settings_users_password_min_desc', 'En az 6 karakter'); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label required"><?php echo t('profile_new_password_confirm', 'Yeni Şifre Tekrar'); ?></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo t('profile_password_change_warning', 'Şifrenizi değiştirdikten sonra sistemden çıkış yapılacak ve yeni şifrenizle tekrar giriş yapmanız gerekecektir.'); ?>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> <?php echo t('profile_password_change_button', 'Şifreyi Değiştir'); ?>
                        </button>
                        <a href="<?php echo url('index.php?module=profile'); ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-arrow-left"></i> <?php echo t('cancel', 'İptal'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Password Tips -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('profile_password_tips_title', 'Güçlü Şifre İpuçları'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <h6><?php echo t('profile_password_tips_heading', 'Güvenli Şifre Oluşturma İpuçları'); ?></h6>
                    <ul class="mb-0">
                        <li><?php echo t('profile_password_tip1', 'En az 8 karakter uzunluğunda olmalı'); ?></li>
                        <li><?php echo t('profile_password_tip2', 'Büyük ve küçük harfler içermeli'); ?></li>
                        <li><?php echo t('profile_password_tip3', 'Rakamlar içermeli'); ?></li>
                        <li><?php echo t('profile_password_tip4', 'Özel karakterler içermeli (!@#$%^&*)'); ?></li>
                        <li><?php echo t('profile_password_tip5', 'Tahmin edilebilir bilgiler (doğum tarihi, isim vb.) kullanmayın'); ?></li>
                        <li><?php echo t('profile_password_tip6', 'Farklı sistemlerde aynı şifreyi kullanmayın'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Password form validation
    $('#passwordForm').on('submit', function(e) {
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('<?php echo t('profile_password_mismatch_js', 'Şifreler eşleşmiyor!'); ?>');
        }
    });
    
    // Password strength indicator
    $('#new_password').on('input', function() {
        const password = $(this).val();
        const strength = checkPasswordStrength(password);
        
        // Update visual indicator if needed
    });
    
    // Function to check password strength
    function checkPasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 6) strength += 1;
        if (password.length >= 8) strength += 1;
        if (/[A-Z]/.test(password)) strength += 1;
        if (/[0-9]/.test(password)) strength += 1;
        if (/[^A-Za-z0-9]/.test(password)) strength += 1;
        
        return strength;
    }
});
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>