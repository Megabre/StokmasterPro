<?php
// Hata raporlamayı aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Megabre StokMaster Pro
 * Login Page
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Include configuration
require_once 'config/config.php';

// Include core files
require_once CORE_PATH . 'Database.php';
require_once CORE_PATH . 'Session.php';
require_once CORE_PATH . 'Authentication.php';
require_once CORE_PATH . 'Language.php';
require_once CORE_PATH . 'helpers.php';

// Start session
Session::start();

// Initialize authentication
$auth = new Authentication();

// Check if already logged in
if ($auth->isLoggedIn()) {
    redirect('index.php');
}

// Process login form
$error = '';
$username = '';

if (isPost()) {
    // Get form data
    $username = post('username');
    $password = post('password');
    
    // Validate form data
    if (empty($username) || empty($password)) {
        $error = 'Lütfen kullanıcı adı ve şifre giriniz.';
    } else {
        // Attempt to login
        if ($auth->login($username, $password)) {
            // Redirect to dashboard
            redirect('index.php');
        } else {
            $error = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}

// Set page title
$page_title = APP_NAME . ' - Giriş';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-logo img {
            max-width: 200px;
            height: auto;
        }
        .login-title {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
        }
        .login-form .form-control {
            height: 46px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .login-form .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .login-form .btn {
            height: 46px;
            border-radius: 4px;
        }
        .login-form .input-group-text {
            background-color: transparent;
            border-right: none;
        }
        .login-form .form-control {
            border-left: none;
        }
        .version-info {
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="<?php echo asset('img/logo.png'); ?>" alt="<?php echo APP_NAME; ?>">
        </div>
        
        <h4 class="login-title">Yönetim Paneli</h4>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="post" class="login-form">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" name="username" id="username" placeholder="Kullanıcı Adı" value="<?php echo e($username); ?>" required autofocus>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="password" id="password" placeholder="Şifre" required>
                </div>
            </div>
            
            <div class="mb-3">
                <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
            </div>
        </form>
        
        <div class="version-info">
            <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?><br>
            &copy; <?php echo date('Y'); ?> <a href="https://megabre.com" target="_blank">Megabre</a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>