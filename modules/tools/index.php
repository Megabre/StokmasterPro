<?php
/**
 * Megabre StokMaster Pro
 * Tools Index
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Get page parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'index';

// Process actions
switch ($action) {
    case 'reports':
        include_once MODULES_PATH . 'tools/reports.php';
        break;
        
    case 'calculators':
        include_once MODULES_PATH . 'tools/calculators.php';
        break;
        
    case 'cache':
        include_once MODULES_PATH . 'tools/cache.php';
        break;
        
    case 'backup':
        include_once MODULES_PATH . 'tools/backup.php';
        break;
        
    case 'import-export':
        include_once MODULES_PATH . 'tools/import-export.php';
        break;
        
    case 'optimize':
        include_once MODULES_PATH . 'tools/optimize.php';
        break;
        
    default:
        // Default action: tools dashboard
        
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
            <h3 class="page-title"><?php echo t('tools_title', 'Araçlar'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('tools_title', 'Araçlar'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Tools Cards -->
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                <h4 class="card-title"><?php echo t('tools_detailed_reports', 'Detaylı Raporlar'); ?></h4>
                <p class="card-text"><?php echo t('tools_detailed_reports_desc', 'Satışlar, stoklar, müşteriler ve mali durumunuz hakkında detaylı raporlar oluşturun.'); ?></p>
                <a href="<?php echo url('index.php?module=tools&action=reports'); ?>" class="btn btn-primary mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('tools_go_to_reports', 'Raporlara Git'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-calculator fa-3x text-success mb-3"></i>
                <h4 class="card-title"><?php echo t('tools_calculators', 'Hesaplama Araçları'); ?></h4>
                <p class="card-text"><?php echo t('tools_calculators_desc', 'Metreküp, metrekare, KDV, kâr marjı ve daha fazlası için hesaplama araçları.'); ?></p>
                <a href="<?php echo url('index.php?module=tools&action=calculators'); ?>" class="btn btn-success mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('tools_go_to_calculators', 'Hesaplayıcılara Git'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-broom fa-3x text-warning mb-3"></i>
                <h4 class="card-title"><?php echo t('tools_cache_clearing', 'Önbellek Temizleme'); ?></h4>
                <p class="card-text"><?php echo t('tools_cache_clearing_desc', 'Sistem performansını artırmak için önbelleği temizleyin ve sistem kaynaklarını optimize edin.'); ?></p>
                <a href="<?php echo url('index.php?module=tools&action=cache'); ?>" class="btn btn-warning mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('tools_clear_cache', 'Önbelleği Temizle'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-database fa-3x text-info mb-3"></i>
                <h4 class="card-title"><?php echo t('tools_system_backup', 'Sistem Yedekleme'); ?></h4>
                <p class="card-text"><?php echo t('tools_system_backup_desc', 'Veritabanınızı ve sistem verilerinizi yedekleyin, önceki yedekleri geri yükleyin.'); ?></p>
                <a href="<?php echo url('index.php?module=tools&action=backup'); ?>" class="btn btn-info mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('tools_backup_operations', 'Yedekleme İşlemleri'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-file-export fa-3x text-danger mb-3"></i>
                <h4 class="card-title"><?php echo t('tools_import_export', 'İçe/Dışa Aktarım'); ?></h4>
                <p class="card-text"><?php echo t('tools_import_export_desc', 'Ürünleri, müşterileri, stokları ve diğer verileri Excel formatında içe/dışa aktarın.'); ?></p>
                <a href="<?php echo url('index.php?module=tools&action=import-export'); ?>" class="btn btn-danger mt-3">
                    <i class="fas fa-arrow-right"></i> <?php echo t('tools_import_export', 'İçe/Dışa Aktarım'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-database fa-3x text-purple mb-3" style="color: #9b59b6;"></i>
                <h4 class="card-title"><?php echo t('tools_database_optimization', 'Veritabanı Optimizasyonu'); ?></h4>
                <p class="card-text"><?php echo t('tools_database_optimization_desc', 'Veritabanı tablolarını optimize edin, fragmentasyonu azaltın ve performansı artırın.'); ?></p>
                <a href="<?php echo url('index.php?module=tools&action=optimize'); ?>" class="btn mt-3" style="background-color: #9b59b6; color: white;">
                    <i class="fas fa-arrow-right"></i> <?php echo t('tools_optimize_database', 'Veritabanını Optimize Et'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-cogs fa-3x text-secondary mb-3"></i>
                <h4 class="card-title"><?php echo t('tools_system_info', 'Sistem Bilgileri'); ?></h4>
                <p class="card-text"><?php echo t('tools_system_info_desc', 'Sistem durumu, performans istatistikleri ve teknik bilgileri görüntüleyin.'); ?></p>
                <button type="button" class="btn btn-secondary mt-3" data-bs-toggle="modal" data-bs-target="#systemInfoModal">
                    <i class="fas fa-arrow-right"></i> <?php echo t('tools_show_system_info', 'Sistem Bilgilerini Göster'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- System Info Modal -->
<div class="modal fade" id="systemInfoModal" tabindex="-1" aria-labelledby="systemInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="systemInfoModalLabel"><?php echo t('tools_system_info_modal_title', 'Sistem Bilgileri'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3"><?php echo t('tools_system_info_section', 'Sistem Bilgileri'); ?></h6>
                        <div class="mb-2">
                            <strong><?php echo t('tools_app_name', 'Uygulama Adı:'); ?></strong> Megabre StokMaster Pro
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_version', 'Versiyon:'); ?></strong> 1.0.0
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_php_version', 'PHP Versiyonu:'); ?></strong> <?php echo phpversion(); ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_server', 'Sunucu:'); ?></strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_os', 'İşletim Sistemi:'); ?></strong> <?php echo PHP_OS; ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_date_time', 'Tarih/Saat:'); ?></strong> <?php echo date('d.m.Y H:i:s'); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3"><?php echo t('tools_database_info', 'Veritabanı Bilgileri'); ?></h6>
                        <?php
                        $db = Database::getInstance();
                        
                        // Get database size
                        $db->query("SELECT 
                                   ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size 
                                   FROM information_schema.TABLES 
                                   WHERE table_schema = DATABASE()");
                        $dbSize = $db->single()['size'];
                        
                        // Get table count
                        $db->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE table_schema = DATABASE()");
                        $tableCount = $db->single()['count'];
                        
                        // Get record counts
                        $db->query("SELECT COUNT(*) as count FROM products");
                        $productCount = $db->single()['count'];
                        
                        $db->query("SELECT COUNT(*) as count FROM customers");
                        $customerCount = $db->single()['count'];
                        
                        $db->query("SELECT COUNT(*) as count FROM orders");
                        $orderCount = $db->single()['count'];
                        
                        $db->query("SELECT COUNT(*) as count FROM transactions");
                        $transactionCount = $db->single()['count'];
                        
                        $db->query("SELECT COUNT(*) as count FROM stock_movements");
                        $stockCount = $db->single()['count'];
                        ?>
                        <div class="mb-2">
                            <strong><?php echo t('tools_database_size', 'Veritabanı Boyutu:'); ?></strong> <?php echo $dbSize; ?> MB
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_table_count', 'Tablo Sayısı:'); ?></strong> <?php echo $tableCount; ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_product_count', 'Ürün Sayısı:'); ?></strong> <?php echo $productCount; ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_customer_count', 'Müşteri Sayısı:'); ?></strong> <?php echo $customerCount; ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_order_count', 'Sipariş Sayısı:'); ?></strong> <?php echo $orderCount; ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_transaction_count', 'Mali İşlem Sayısı:'); ?></strong> <?php echo $transactionCount; ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_stock_movement_count', 'Stok Hareketi Sayısı:'); ?></strong> <?php echo $stockCount; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <h6 class="border-bottom pb-2 mb-3"><?php echo t('tools_php_modules', 'PHP Modülleri'); ?></h6>
                        <div class="row">
                            <?php
                            $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo', 'gd', 'zip', 'curl'];
                            $loadedExtensions = get_loaded_extensions();
                            
                            foreach ($requiredExtensions as $ext):
                                $isLoaded = in_array($ext, $loadedExtensions);
                            ?>
                            <div class="col-md-3 mb-2">
                                <span class="<?php echo $isLoaded ? 'text-success' : 'text-danger'; ?>">
                                    <i class="fas <?php echo $isLoaded ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo $ext; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <h6 class="border-bottom pb-2 mb-3"><?php echo t('tools_performance_info', 'Performans Bilgileri'); ?></h6>
                        <div class="mb-2">
                            <strong><?php echo t('tools_memory_usage', 'Bellek Kullanımı:'); ?></strong> <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_max_memory_limit', 'Maksimum Bellek Sınırı:'); ?></strong> <?php echo ini_get('memory_limit'); ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_max_upload_size', 'Maksimum Yükleme Boyutu:'); ?></strong> <?php echo ini_get('upload_max_filesize'); ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_max_post_size', 'Maksimum POST Boyutu:'); ?></strong> <?php echo ini_get('post_max_size'); ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo t('tools_max_execution_time', 'Maksimum Çalışma Süresi:'); ?></strong> <?php echo ini_get('max_execution_time'); ?> <?php echo t('tools_seconds', 'saniye'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('tools_close', 'Kapat'); ?></button>
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