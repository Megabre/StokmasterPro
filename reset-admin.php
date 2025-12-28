<?php
/**
 * Megabre StokMaster Pro
 * Admin Password Reset Script
 * 
 * GÜVENLİK UYARISI: Bu dosyayı kullandıktan sonra MUTLAKA SİLİN!
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Hata raporlamayı aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Config dosyalarını dahil et
require_once 'config/config.php';
require_once CORE_PATH . 'Database.php';

// İşlem tamamlandı durumu
$success = false;
$error = '';
$users = [];

// Veritabanı bağlantısını yap
try {
    $db = Database::getInstance();
    
    // Tüm kullanıcıları al
    $db->query("SELECT id, username, email, name, surname, role, status FROM users ORDER BY id ASC");
    $users = $db->resultSet();
    
} catch (Exception $e) {
    $error = "Veritabanı bağlantı hatası: " . $e->getMessage();
}

// Form gönderilmişse işle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    // Doğrulama
    if ($userId <= 0) {
        $error = "Lütfen bir kullanıcı seçin.";
    } elseif (empty($newPassword)) {
        $error = "Yeni şifre boş olamaz.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Şifre en az 6 karakter olmalıdır.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Şifreler eşleşmiyor.";
    } else {
        // Şifreyi hashle
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Kullanıcının şifresini güncelle
        $db->query("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
        $db->bind(':password', $hashedPassword);
        $db->bind(':id', $userId);
        
        if ($db->execute()) {
            // İşlem logla
            $db->query("INSERT INTO user_activity (user_id, activity, details, ip_address, user_agent, created_at) 
                        VALUES (:user_id, 'password_reset', :details, :ip_address, :user_agent, NOW())");
            $db->bind(':user_id', $userId);
            $db->bind(':details', 'Password reset via reset-admin-password.php script');
            $db->bind(':ip_address', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
            $db->execute();
            
            $success = true;
        } else {
            $error = "Şifre güncellenirken bir hata oluştu.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StokMaster Pro - Şifre Sıfırlama</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .reset-container {
            max-width: 550px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(255, 255, 255, 0.1);
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-header .icon-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.4);
        }
        
        .reset-header .icon-wrapper i {
            font-size: 2rem;
            color: white;
        }
        
        .reset-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 0.5rem;
        }
        
        .reset-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fff3cd, #ffeeba);
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .warning-box i {
            color: #856404;
            font-size: 1.25rem;
            margin-top: 2px;
        }
        
        .warning-box .content {
            flex: 1;
        }
        
        .warning-box .content strong {
            color: #856404;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .warning-box .content p {
            color: #856404;
            font-size: 0.875rem;
            margin: 0;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0f3460;
            box-shadow: 0 0 0 4px rgba(15, 52, 96, 0.1);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        
        .form-control.input-with-icon {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .btn-reset {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
            border-radius: 10px;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
            background: linear-gradient(135deg, #c0392b, #a93226);
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .btn-back {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            color: #333;
            width: 100%;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-back:hover {
            background: #e9ecef;
            color: #333;
        }
        
        .success-container {
            text-align: center;
        }
        
        .success-container .icon-wrapper {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .success-container .icon-wrapper i {
            font-size: 3rem;
            color: white;
        }
        
        .success-container h2 {
            color: #27ae60;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .success-container p {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 2rem;
        }
        
        .delete-warning {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border: 1px solid #f5c6cb;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .delete-warning i {
            color: #721c24;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .delete-warning strong {
            color: #721c24;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .delete-warning p {
            color: #721c24;
            font-size: 0.875rem;
            margin: 0;
        }
        
        .user-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .user-badge.admin { background: #e74c3c; color: white; }
        .user-badge.manager { background: #3498db; color: white; }
        .user-badge.accountant { background: #9b59b6; color: white; }
        .user-badge.staff { background: #2ecc71; color: white; }
        .user-badge.viewer { background: #95a5a6; color: white; }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.inactive { background: #f8d7da; color: #721c24; }
        
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .footer-text a {
            color: #0f3460;
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if ($success): ?>
            <!-- Başarı Durumu -->
            <div class="success-container">
                <div class="icon-wrapper">
                    <i class="fas fa-check"></i>
                </div>
                <h2>Şifre Başarıyla Sıfırlandı!</h2>
                <p>Kullanıcı şifresi başarıyla güncellendi. Artık yeni şifresiyle giriş yapabilir.</p>
                
                <div class="delete-warning">
                    <i class="fas fa-skull-crossbones d-block"></i>
                    <strong>ÖNEMLİ GÜVENLİK UYARISI!</strong>
                    <p>Bu dosyayı sunucunuzdan hemen silin! Bu script güvenlik açığı oluşturabilir.</p>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="login.php" class="btn-reset text-decoration-none">
                        <i class="fas fa-sign-in-alt me-2"></i>Giriş Sayfasına Git
                    </a>
                    <a href="reset-admin-password.php" class="btn-back">
                        <i class="fas fa-redo me-2"></i>Başka Bir Şifre Sıfırla
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Form -->
            <div class="reset-header">
                <div class="icon-wrapper">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Şifre Sıfırlama</h1>
                <p>Kullanıcı şifresini sıfırlamak için aşağıdaki formu doldurun</p>
            </div>
            
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="content">
                    <strong>Güvenlik Uyarısı</strong>
                    <p>Bu script sadece acil durumlarda kullanılmalıdır. İşlem tamamlandıktan sonra bu dosyayı sunucudan silin.</p>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="user_id" class="form-label">
                        <i class="fas fa-user me-1"></i>Kullanıcı Seçin
                    </label>
                    <select name="user_id" id="user_id" class="form-select" required>
                        <option value="">-- Kullanıcı Seçin --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['username']); ?> 
                                (<?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?>) 
                                - <?php echo strtoupper($user['role']); ?>
                                <?php echo $user['status'] ? '' : ' [PASİF]'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">
                        <i class="fas fa-lock me-1"></i>Yeni Şifre
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="password" class="form-control input-with-icon" id="new_password" name="new_password" 
                               placeholder="En az 6 karakter" minlength="6" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock me-1"></i>Şifre Tekrar
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-redo"></i></span>
                        <input type="password" class="form-control input-with-icon" id="confirm_password" name="confirm_password" 
                               placeholder="Şifreyi tekrar girin" minlength="6" required>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn-reset">
                        <i class="fas fa-sync-alt me-2"></i>Şifreyi Sıfırla
                    </button>
                    <a href="login.php" class="btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Giriş Sayfasına Dön
                    </a>
                </div>
            </form>
        <?php endif; ?>
        
        <div class="footer-text">
            <a href="https://megabre.com" target="_blank">Megabre StokMaster Pro</a> &copy; <?php echo date('Y'); ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Şifre eşleşme kontrolü
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
