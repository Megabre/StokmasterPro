<?php
/**
 * Megabre StokMaster Pro
 * Settings Index
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
    redirect('index.php');
}

// Get page parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'index';

// Process actions
switch ($action) {
    case 'system':
        include_once MODULES_PATH . 'settings/system.php';
        break;
        
    case 'users':
        include_once MODULES_PATH . 'settings/users.php';
        break;
        
    case 'inventory':
        include_once MODULES_PATH . 'settings/inventory.php';
        break;
        
    case 'currencies':
        include_once MODULES_PATH . 'settings/currencies.php';
        break;
        
    case 'customer-tags':
        include_once MODULES_PATH . 'settings/customer-tags.php';
        break;
        
    default:
        // Default action: settings dashboard
        
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
            <h3 class="page-title"><?php echo t('settings_title', 'Ayarlar'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('settings_title', 'Ayarlar'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Settings Cards -->
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-cogs fa-3x text-primary mb-3"></i>
                <h4 class="card-title"><?php echo t('settings_system_title', 'Sistem Ayarları'); ?></h4>
                <p class="card-text"><?php echo t('settings_system_desc', 'Sistem ayarları, dil seçenekleri, önbellek, tema ve diğer genel ayarları yönetin.'); ?></p>
                <a href="<?php echo url('index.php?module=settings&action=system'); ?>" class="btn btn-primary mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('settings_go_to_system', 'Sistem Ayarlarına Git'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-users fa-3x text-success mb-3"></i>
                <h4 class="card-title"><?php echo t('settings_users_title', 'Kullanıcı Yönetimi'); ?></h4>
                <p class="card-text"><?php echo t('settings_users_desc', 'Kullanıcı hesaplarını, yetkileri ve rolleri yönetin, yeni kullanıcılar ekleyin.'); ?></p>
                <a href="<?php echo url('index.php?module=settings&action=users'); ?>" class="btn btn-success mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('settings_go_to_users', 'Kullanıcı Yönetimine Git'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-boxes fa-3x text-warning mb-3"></i>
                <h4 class="card-title"><?php echo t('settings_inventory_title', 'Envanter Ayarları'); ?></h4>
                <p class="card-text"><?php echo t('settings_inventory_desc', 'Stok uyarı seviyeleri, birimler, ürün numaralandırma ve envanter işlemleri ayarları.'); ?></p>
                <a href="<?php echo url('index.php?module=settings&action=inventory'); ?>" class="btn btn-warning mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('settings_go_to_inventory', 'Envanter Ayarlarına Git'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-3x text-info mb-3"></i>
                <h4 class="card-title"><?php echo t('settings_currencies_title', 'Para Birimleri'); ?></h4>
                <p class="card-text"><?php echo t('settings_currencies_desc', 'Para birimlerini yönetin, dönüşüm oranlarını güncelleyin ve varsayılan para birimini ayarlayın.'); ?></p>
                <a href="<?php echo url('index.php?module=settings&action=currencies'); ?>" class="btn btn-info mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('settings_go_to_currencies', 'Para Birimlerine Git'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-tags fa-3x text-danger mb-3"></i>
                <h4 class="card-title"><?php echo t('settings_customer_tags_title', 'Müşteri Etiketleri'); ?></h4>
                <p class="card-text"><?php echo t('settings_customer_tags_desc', 'Müşteri etiketlerini yönetin, indirim yüzdelerini ayarlayın ve müşterilere etiket atayın.'); ?></p>
                <a href="<?php echo url('index.php?module=settings&action=customer-tags'); ?>" class="btn btn-danger mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('settings_go_to_customer_tags', 'Müşteri Etiketlerine Git'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title"><?php echo t('settings_system_info', 'Sistem Bilgileri'); ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3"><?php echo t('settings_software_info', 'Yazılım Bilgileri'); ?></h6>
                <div class="mb-2">
                    <strong><?php echo t('settings_application', 'Uygulama:'); ?></strong> Megabre StokMaster Pro
                </div>
                <div class="mb-2">
                    <strong><?php echo t('settings_version', 'Versiyon:'); ?></strong> 1.0.0
                </div>
                <div class="mb-2">
                    <strong><?php echo t('settings_license', 'Lisans:'); ?></strong> 
                    <?php
                    // Get license information
                    $db = Database::getInstance();
                    $db->query("SELECT value FROM settings WHERE `key` = 'license_key'");
                    $licenseKey = $db->single()['value'] ?? '';
                    
                    $db->query("SELECT value FROM settings WHERE `key` = 'license_status'");
                    $licenseStatus = $db->single()['value'] ?? '';
                    
                    if (!empty($licenseKey) && $licenseStatus == 'active') {
                        echo '<span class="text-success">' . t('settings_license_active', 'Aktif') . '</span>';
                    } elseif (!empty($licenseKey)) {
                        echo '<span class="text-warning">' . t('settings_license_unverified', 'Doğrulanmamış') . '</span>';
                    } else {
                        echo '<span class="text-danger">' . t('settings_license_unlicensed', 'Lisanslanmamış') . '</span>';
                    }
                    ?>
                </div>
                <div class="mb-2">
                    <strong><?php echo t('settings_installation_date', 'Kurulum Tarihi:'); ?></strong> 
                    <?php
                    $db->query("SELECT value FROM settings WHERE `key` = 'installation_date'");
                    $installationDate = $db->single()['value'] ?? '';
                    
                    echo $installationDate ? date('d.m.Y H:i', strtotime($installationDate)) : t('settings_unknown', 'Bilinmiyor');
                    ?>
                </div>
                <div class="mb-2">
                    <strong><?php echo t('settings_last_update', 'Son Güncelleme:'); ?></strong> 
                    <?php
                    $db->query("SELECT value FROM settings WHERE `key` = 'last_update'");
                    $lastUpdate = $db->single()['value'] ?? '';
                    
                    echo $lastUpdate ? date('d.m.Y H:i', strtotime($lastUpdate)) : t('settings_no_update', 'Güncelleme yok');
                    ?>
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3"><?php echo t('settings_server_info', 'Sunucu Bilgileri'); ?></h6>
                <div class="mb-2">
                    <strong><?php echo t('settings_php_version', 'PHP Versiyonu:'); ?></strong> <?php echo phpversion(); ?>
                </div>
                <div class="mb-2">
                    <strong><?php echo t('settings_mysql_version', 'MySQL Versiyonu:'); ?></strong> 
                    <?php
                    $db->query("SELECT VERSION() as version");
                    $mysqlVersion = $db->single()['version'] ?? t('settings_unknown', 'Bilinmiyor');
                    echo $mysqlVersion;
                    ?>
                </div>
                <div class="mb-2">
                    <strong><?php echo t('settings_server', 'Sunucu:'); ?></strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?>
                </div>
                <div class="mb-2">
                    <strong><?php echo t('settings_os', 'İşletim Sistemi:'); ?></strong> <?php echo PHP_OS; ?>
                </div>
                <div class="mb-2">
                    <strong><?php echo t('settings_max_upload_size', 'Maksimum Yükleme Boyutu:'); ?></strong> <?php echo ini_get('upload_max_filesize'); ?>
                </div>
                <div class="mb-2">
                    <strong><?php echo t('settings_timezone', 'Zaman Dilimi:'); ?></strong> 
                    <?php
                    $db->query("SELECT value FROM settings WHERE `key` = 'timezone'");
                    $timezone = $db->single()['value'] ?? 'Europe/Istanbul';
                    echo $timezone;
                    ?>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3"><?php echo t('settings_module_status', 'Modül Durumu'); ?></h6>
                <?php
                // Get module status
                $db->query("SELECT * FROM settings WHERE `key` LIKE 'module_%'");
                $modules = $db->resultSet();
                
                $moduleStatus = [];
                foreach ($modules as $module) {
                    $moduleStatus[str_replace('module_', '', $module['key'])] = $module['value'];
                }
                ?>
                <div class="row">
                    <div class="col-6">
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['dashboard']) && $moduleStatus['dashboard'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('dashboard', 'Dashboard'); ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['products']) && $moduleStatus['products'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('products', 'Ürünler'); ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['customers']) && $moduleStatus['customers'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('customers', 'Müşteriler'); ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['orders']) && $moduleStatus['orders'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('orders', 'Siparişler'); ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['stock']) && $moduleStatus['stock'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('stock', 'Stok Yönetimi'); ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['transactions']) && $moduleStatus['transactions'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('transactions', 'Mali İşlemler'); ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['tools']) && $moduleStatus['tools'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('tools_title', 'Araçlar'); ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['reports']) && $moduleStatus['reports'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('reports_title', 'Raporlar'); ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['settings']) && $moduleStatus['settings'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('settings_title', 'Ayarlar'); ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-circle <?php echo isset($moduleStatus['dynamic_fields']) && $moduleStatus['dynamic_fields'] == 1 ? 'text-success' : 'text-danger'; ?> me-2"></i>
                            <strong><?php echo t('settings_dynamic_fields', 'Dinamik Alanlar'); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3"><?php echo t('settings_system_status', 'Sistem Durumu'); ?></h6>
                <?php
                // Get system status
                $db->query("SELECT 
                           ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as db_size 
                           FROM information_schema.TABLES 
                           WHERE table_schema = DATABASE()");
                $dbSize = $db->single()['db_size'];
                
                $cacheSize = 0;
                if (is_dir(CACHE_PATH)) {
                    $cacheSize = folderSize(CACHE_PATH) / (1024 * 1024);
                }
                
                $uploadsSize = 0;
                if (is_dir(UPLOADS_PATH)) {
                    $uploadsSize = folderSize(UPLOADS_PATH) / (1024 * 1024);
                }
                
                $logSize = 0;
                if (is_dir(LOGS_PATH)) {
                    $logSize = folderSize(LOGS_PATH) / (1024 * 1024);
                }
                
                // Get total storage size
                $totalStorage = $dbSize + $cacheSize + $uploadsSize + $logSize;
                
                // Get memory usage
                $memoryUsage = memory_get_usage() / (1024 * 1024);
                $memoryLimit = getMemoryLimitInMB();
                $memoryPercentage = ($memoryUsage / $memoryLimit) * 100;
                
                // Function to calculate folder size
                function folderSize($dir) {
                    $size = 0;
                    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
                        $size += is_file($each) ? filesize($each) : folderSize($each);
                    }
                    return $size;
                }
                
                // Function to get memory limit in MB
                function getMemoryLimitInMB() {
                    $memory_limit = ini_get('memory_limit');
                    $val = trim($memory_limit);
                    $last = strtolower($val[strlen($val)-1]);
                    
                    switch($last) {
                        case 'g':
                            $val *= 1024;
                            break;
                        case 'm':
                            $val = substr($val, 0, -1);
                            break;
                        case 'k':
                            $val = substr($val, 0, -1) / 1024;
                            break;
                    }
                    
                    return $val;
                }
                ?>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo t('settings_database_size', 'Veritabanı Boyutu'); ?> (<?php echo number_format($dbSize, 2); ?> MB)</label>
                    <div class="progress">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(($dbSize / 100), 100); ?>%" aria-valuenow="<?php echo $dbSize; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo t('settings_cache_size', 'Önbellek Boyutu'); ?> (<?php echo number_format($cacheSize, 2); ?> MB)</label>
                    <div class="progress">
                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo min(($cacheSize / 10), 100); ?>%" aria-valuenow="<?php echo $cacheSize; ?>" aria-valuemin="0" aria-valuemax="10"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo t('settings_uploads_size', 'Yükleme Dizini Boyutu'); ?> (<?php echo number_format($uploadsSize, 2); ?> MB)</label>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(($uploadsSize / 500), 100); ?>%" aria-valuenow="<?php echo $uploadsSize; ?>" aria-valuemin="0" aria-valuemax="500"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo t('settings_logs_size', 'Log Dosyaları Boyutu'); ?> (<?php echo number_format($logSize, 2); ?> MB)</label>
                    <div class="progress">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo min(($logSize / 50), 100); ?>%" aria-valuenow="<?php echo $logSize; ?>" aria-valuemin="0" aria-valuemax="50"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo t('settings_memory_usage', 'Bellek Kullanımı'); ?> (<?php echo number_format($memoryUsage, 2); ?> MB / <?php echo $memoryLimit; ?> MB)</label>
                    <div class="progress">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo min($memoryPercentage, 100); ?>%" aria-valuenow="<?php echo $memoryUsage; ?>" aria-valuemin="0" aria-valuemax="<?php echo $memoryLimit; ?>"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><?php echo t('settings_total_storage', 'Toplam Depolama'); ?> (<?php echo number_format($totalStorage, 2); ?> MB)</label>
                    <div class="progress">
                        <div class="progress-bar bg-dark" role="progressbar" style="width: <?php echo min(($totalStorage / 1000), 100); ?>%" aria-valuenow="<?php echo $totalStorage; ?>" aria-valuemin="0" aria-valuemax="1000"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
}
?>