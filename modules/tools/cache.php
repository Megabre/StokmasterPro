<?php
/**
 * Megabre StokMaster Pro
 * Cache Tool
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Check if user has access to tools
if (!$auth->isAdmin()) {
    redirect('index.php');
}

// Initialize cache manager
require_once CORE_PATH . 'Cache.php';
$cache = Cache::getInstance();

// Initialize database connection
$db = Database::getInstance();

// Force cache instance reload to get latest settings
Cache::resetInstance();
$cache = Cache::getInstance();

// Get cache statistics
$cacheStats = $cache->getStats();

// Default cache statistics
$defaultStats = [
    'hit_rate' => 0,
    'total_queries' => 0,
    'cache_hits' => 0,
    'cache_misses' => 0,
    'load_reduction' => 0,
    'avg_query_time' => 0,
    'total_size' => 0,
    'file_count' => 0,
    'data_count' => 0,
    'data_size' => 0,
    'data_last_modified' => 0,
    'template_count' => 0,
    'template_size' => 0,
    'template_last_modified' => 0,
    'query_count' => 0,
    'query_size' => 0,
    'query_last_modified' => 0,
    'performance_data' => [
        'labels' => [],
        'hit_rates' => [],
        'load_reductions' => []
    ]
];

// Merge default stats with actual stats
$cacheStats = array_merge($defaultStats, $cacheStats);

// Ensure performance_data exists
if (!isset($cacheStats['performance_data'])) {
    $cacheStats['performance_data'] = $defaultStats['performance_data'];
}

// Process cache clear action
$message = '';
$status = '';

// Process clear cache request
if (isset($_POST['clear_cache'])) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=tools&action=cache');
    }
    
    $cacheType = isset($_POST['cache_type']) ? $_POST['cache_type'] : 'all';
    $cacheDir = CACHE_PATH;
    $result = false;
    
    switch ($cacheType) {
        case 'all':
            // Tüm önbellek dizinlerini temizle
            $result = array_map(function($dir) {
                if (is_dir($dir)) {
                    array_map('unlink', glob("$dir/*.*"));
                    return true;
                }
                return false;
            }, [
                $cacheDir . '/data',
                $cacheDir . '/templates',
                $cacheDir . '/queries'
            ]);
            $message = t('cache_clear_all_success', 'Tüm önbellek başarıyla temizlendi.');
            break;
            
        case 'data':
            // Veri önbelleğini temizle
            $dataDir = $cacheDir . '/data';
            if (is_dir($dataDir)) {
                array_map('unlink', glob("$dataDir/*.*"));
                $result = true;
            }
            $message = t('cache_clear_data_success', 'Veri önbelleği başarıyla temizlendi.');
            break;
            
        case 'template':
            // Şablon önbelleğini temizle
            $templateDir = $cacheDir . '/templates';
            if (is_dir($templateDir)) {
                array_map('unlink', glob("$templateDir/*.*"));
                $result = true;
            }
            $message = t('cache_clear_template_success', 'Şablon önbelleği başarıyla temizlendi.');
            break;
            
        case 'queries':
            // Sorgu önbelleğini temizle
            $queryDir = $cacheDir . '/queries';
            if (is_dir($queryDir)) {
                array_map('unlink', glob("$queryDir/*.*"));
                $result = true;
            }
            $message = t('cache_clear_queries_success', 'Sorgu önbelleği başarıyla temizlendi.');
            break;
            
        default:
            $result = false;
            $message = t('cache_invalid_type', 'Geçersiz önbellek türü.');
            break;
    }
    
    $status = $result ? 'success' : 'error';
    
    // Get updated cache statistics
    $cacheStats = $cache->getStats();
}

// Process cache settings
if (isset($_POST['save_settings'])) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=tools&action=cache');
    }
    
    $enableCache = isset($_POST['enable_cache']) ? 1 : 0;
    $cacheTTL = (int)$_POST['cache_ttl'];
    $cacheMethod = $_POST['cache_method'];
    
    // Save settings using Cache class methods
    $result1 = $cache->setEnabled($enableCache);
    $result2 = $cache->setTTL($cacheTTL);
    $result3 = $cache->setMethod($cacheMethod);
    
    // Reset cache instance to reload settings
    Cache::resetInstance();
    $cache = Cache::getInstance();
    
    $result = $result1 && $result2 && $result3;
    $message = $result ? t('cache_settings_saved', 'Önbellek ayarları başarıyla kaydedildi.') : t('cache_settings_error', 'Önbellek ayarları kaydedilirken bir hata oluştu.');
    $status = $result ? 'success' : 'error';
}

// Get current cache settings
$db->query("SELECT * FROM settings WHERE setting_key IN ('cache_enabled', 'cache_ttl', 'cache_method')");
$settingsRows = $db->resultSet();

// Default settings
$settings = [
    'cache_enabled' => '0',
    'cache_ttl' => '3600',
    'cache_method' => 'file'
];

// Override with database values
foreach ($settingsRows as $row) {
    if (isset($row['setting_key']) && isset($row['setting_value'])) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Debug: Test cache functionality
$cacheTestKey = 'test_cache_' . time();
$cacheTestData = ['test' => 'data', 'timestamp' => time()];
$cacheTestResult = false;
$cacheEnabledStatus = $cache->isEnabled();
$cachePathStatus = is_dir(CACHE_PATH) && is_writable(CACHE_PATH);

if ($cacheEnabledStatus) {
    // Try to set a test cache
    $cacheTestResult = $cache->set($cacheTestKey, $cacheTestData, 60);
    // Clean up test cache
    if ($cacheTestResult) {
        $cache->delete($cacheTestKey);
    }
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('cache_title', 'Önbellek Yönetimi'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=tools'); ?>"><?php echo t('tools_title', 'Araçlar'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('cache_title', 'Önbellek Yönetimi'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Display Message -->
<?php if ($message): ?>
<div class="alert alert-<?php echo $status; ?> alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Cache Management Card -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('cache_status', 'Önbellek Durumu'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center mb-3">
                            <h4 class="m-0"><?php echo formatSize(($cacheStats['data_size'] ?? 0) + ($cacheStats['template_size'] ?? 0) + ($cacheStats['query_size'] ?? 0)); ?></h4>
                            <p class="text-muted mb-0"><?php echo t('cache_total_size', 'Toplam Boyut'); ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center mb-3">
                            <h4 class="m-0"><?php echo ($cacheStats['data_count'] ?? 0) + ($cacheStats['template_count'] ?? 0) + ($cacheStats['query_count'] ?? 0); ?></h4>
                            <p class="text-muted mb-0"><?php echo t('cache_file_count', 'Dosya Sayısı'); ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center mb-3">
                            <h4 class="m-0"><?php echo $settings['cache_enabled'] ? '<span class="text-success">' . t('cache_status_active', 'Aktif') . '</span>' : '<span class="text-danger">' . t('cache_status_inactive', 'Devre Dışı') . '</span>'; ?></h4>
                            <p class="text-muted mb-0"><?php echo t('cache_status', 'Önbellek Durumu'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="border-bottom pb-2 mb-3"><?php echo t('cache_details', 'Önbellek Detayları'); ?></h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><?php echo t('cache_type', 'Önbellek Türü'); ?></th>
                                        <th><?php echo t('cache_file_count', 'Dosya Sayısı'); ?></th>
                                        <th><?php echo t('cache_total_size', 'Boyut'); ?></th>
                                        <th><?php echo t('cache_last_modified', 'Son Güncellenme'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo t('cache_data_cache', 'Veri Önbelleği'); ?></td>
                                        <td><?php echo $cacheStats['data']['count'] ?? 0; ?></td>
                                        <td><?php echo $cacheStats['data']['size_formatted'] ?? '0 B'; ?></td>
                                        <td><?php echo isset($cacheStats['data']['last_modified']) && $cacheStats['data']['last_modified'] ? date('d.m.Y H:i:s', $cacheStats['data']['last_modified']) : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo t('cache_template_cache', 'Şablon Önbelleği'); ?></td>
                                        <td><?php echo $cacheStats['templates']['count'] ?? 0; ?></td>
                                        <td><?php echo $cacheStats['templates']['size_formatted'] ?? '0 B'; ?></td>
                                        <td><?php echo isset($cacheStats['templates']['last_modified']) && $cacheStats['templates']['last_modified'] ? date('d.m.Y H:i:s', $cacheStats['templates']['last_modified']) : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><?php echo t('cache_query_cache', 'Sorgu Önbelleği'); ?></td>
                                        <td><?php echo $cacheStats['queries']['count'] ?? 0; ?></td>
                                        <td><?php echo $cacheStats['queries']['size_formatted'] ?? '0 B'; ?></td>
                                        <td><?php echo isset($cacheStats['queries']['last_modified']) && $cacheStats['queries']['last_modified'] ? date('d.m.Y H:i:s', $cacheStats['queries']['last_modified']) : '-'; ?></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td><?php echo t('cache_total', 'Toplam'); ?></td>
                                        <td><?php echo $cacheStats['total'] ?? 0; ?></td>
                                        <td><?php echo $cacheStats['size_formatted'] ?? '0 B'; ?></td>
                                        <td>-</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="border-bottom pb-2 mb-3"><?php echo t('cache_files', 'Önbellek Dosyaları'); ?></h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><?php echo t('cache_file_name', 'Dosya Adı'); ?></th>
                                        <th><?php echo t('cache_type', 'Tür'); ?></th>
                                        <th><?php echo t('cache_total_size', 'Boyut'); ?></th>
                                        <th><?php echo t('cache_last_modified', 'Son Güncellenme'); ?></th>
                                        <th><?php echo t('status', 'Durum'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($cacheStats['files'])): ?>
                                        <?php foreach ($cacheStats['files'] as $file): ?>
                                            <tr>
                                                <td><?php echo $file['name']; ?></td>
                                                <td><?php echo ucfirst($file['type']); ?></td>
                                                <td><?php echo $file['size_formatted']; ?></td>
                                                <td><?php echo date('d.m.Y H:i:s', $file['last_modified']); ?></td>
                                                <td>
                                                    <?php if ($file['expired']): ?>
                                                        <span class="badge bg-danger"><?php echo t('cache_expired', 'Süresi Dolmuş'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success"><?php echo t('cache_status_active', 'Aktif'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center"><?php echo t('cache_no_files', 'Önbellek dosyası bulunamadı.'); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <h5 class="border-bottom pb-2 mb-3"><?php echo t('cache_clearing', 'Önbellek Temizleme'); ?></h5>
                        <form action="<?php echo url('index.php?module=tools&action=cache'); ?>" method="post">
                            <?php echo csrfField(); ?>
                            <div class="mb-3">
                                <label class="form-label"><?php echo t('cache_clear_type', 'Temizlenecek Önbellek:'); ?></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cache_type" id="cache_all" value="all" checked>
                                    <label class="form-check-label" for="cache_all">
                                        <?php echo t('cache_clear_all', 'Tüm Önbellek'); ?>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cache_type" id="cache_data" value="data">
                                    <label class="form-check-label" for="cache_data">
                                        <?php echo t('cache_clear_data_only', 'Sadece Veri Önbelleği'); ?>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cache_type" id="cache_template" value="template">
                                    <label class="form-check-label" for="cache_template">
                                        <?php echo t('cache_clear_template_only', 'Sadece Şablon Önbelleği'); ?>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cache_type" id="cache_queries" value="queries">
                                    <label class="form-check-label" for="cache_queries">
                                        <?php echo t('cache_clear_queries_only', 'Sadece Sorgu Önbelleği'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong><?php echo t('cache_clear_warning', 'Uyarı:'); ?></strong> <?php echo t('cache_clear_warning_text', 'Önbellek temizleme işlemi geri alınamaz ve sistem performansını geçici olarak etkileyebilir.'); ?>
                            </div>
                            
                            <button type="submit" name="clear_cache" class="btn btn-danger">
                                <i class="fas fa-trash me-1"></i> <?php echo t('cache_clear_button', 'Önbelleği Temizle'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('cache_settings', 'Önbellek Ayarları'); ?></h5>
            </div>
            <div class="card-body">
                <form action="<?php echo url('index.php?module=tools&action=cache'); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="enable_cache" name="enable_cache" <?php echo $settings['cache_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enable_cache"><?php echo t('cache_enable', 'Önbellek Aktif'); ?></label>
                        <div class="form-text"><?php echo t('cache_enable_desc', 'Önbellek sistemini aktifleştir veya devre dışı bırak.'); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cache_ttl" class="form-label"><?php echo t('cache_ttl', 'Önbellek Süresi (saniye)'); ?></label>
                        <input type="number" class="form-control" id="cache_ttl" name="cache_ttl" value="<?php echo $settings['cache_ttl']; ?>" min="60" step="60">
                        <div class="form-text"><?php echo t('cache_ttl_desc', 'Önbellek dosyalarının saklanacağı süre (saniye). Minimum 60 saniye.'); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cache_method" class="form-label"><?php echo t('cache_method', 'Önbellek Metodu'); ?></label>
                        <select class="form-select" id="cache_method" name="cache_method">
                            <option value="file" <?php echo $settings['cache_method'] == 'file' ? 'selected' : ''; ?>><?php echo t('cache_method_file', 'Dosya (File)'); ?></option>
                            <option value="apc" <?php echo $settings['cache_method'] == 'apc' ? 'selected' : ''; ?>><?php echo t('cache_method_apc', 'APC'); ?></option>
                            <option value="memcached" <?php echo $settings['cache_method'] == 'memcached' ? 'selected' : ''; ?>><?php echo t('cache_method_memcached', 'Memcached'); ?></option>
                            <option value="redis" <?php echo $settings['cache_method'] == 'redis' ? 'selected' : ''; ?>><?php echo t('cache_method_redis', 'Redis'); ?></option>
                        </select>
                        <div class="form-text"><?php echo t('cache_method_desc', 'Önbellek verilerinin nasıl saklanacağını belirler. Sadece sunucuda yüklü olan seçenekler çalışır.'); ?></div>
                    </div>
                    
                    <button type="submit" name="save_settings" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> <?php echo t('cache_save_settings', 'Ayarları Kaydet'); ?>
                    </button>
                </form>
                
                <!-- Debug Information -->
                <div class="mt-4">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0"><?php echo t('cache_status_info', 'Önbellek Durum Bilgisi'); ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong><?php echo t('cache_enabled', 'Önbellek Aktif:'); ?></strong> 
                                <span class="badge bg-<?php echo $cacheEnabledStatus ? 'success' : 'danger'; ?>">
                                    <?php echo $cacheEnabledStatus ? t('cache_enabled_yes', 'Evet') : t('cache_enabled_no', 'Hayır'); ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <strong><?php echo t('cache_path', 'Önbellek Yolu:'); ?></strong> 
                                <code><?php echo CACHE_PATH; ?></code>
                            </div>
                            <div class="mb-2">
                                <strong><?php echo t('cache_directory_exists', 'Dizin Mevcut:'); ?></strong> 
                                <span class="badge bg-<?php echo is_dir(CACHE_PATH) ? 'success' : 'danger'; ?>">
                                    <?php echo is_dir(CACHE_PATH) ? t('yes', 'Evet') : t('no', 'Hayır'); ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <strong><?php echo t('cache_writable', 'Yazılabilir:'); ?></strong> 
                                <span class="badge bg-<?php echo $cachePathStatus ? 'success' : 'danger'; ?>">
                                    <?php echo $cachePathStatus ? t('yes', 'Evet') : t('no', 'Hayır'); ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <strong><?php echo t('cache_test_write', 'Test Yazma:'); ?></strong> 
                                <span class="badge bg-<?php echo $cacheTestResult ? 'success' : 'danger'; ?>">
                                    <?php echo $cacheTestResult ? t('cache_test_success', 'Başarılı') : t('cache_test_failed', 'Başarısız'); ?>
                                </span>
                            </div>
                            <?php if (!$cacheEnabledStatus): ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong><?php echo t('cache_warning_inactive', 'Uyarı:'); ?></strong> <?php echo t('cache_warning_inactive_text', 'Önbellek sistemi devre dışı. Önbellek dosyaları oluşturulmayacak. Lütfen yukarıdaki "Önbellek Aktif" seçeneğini işaretleyip ayarları kaydedin.'); ?>
                            </div>
                            <?php elseif (!$cachePathStatus): ?>
                            <div class="alert alert-danger mt-3 mb-0">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong><?php echo t('cache_error_not_writable', 'Hata:'); ?></strong> <?php echo t('cache_error_not_writable_text', 'Önbellek dizini yazılabilir değil. Lütfen dizininin yazma izinlerini kontrol edin.'); ?>
                            </div>
                            <?php elseif (!$cacheTestResult): ?>
                            <div class="alert alert-danger mt-3 mb-0">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong><?php echo t('cache_error_write_failed', 'Hata:'); ?></strong> <?php echo t('cache_error_write_failed_text', 'Önbellek dosyası yazılamadı. Dizin izinlerini kontrol edin.'); ?>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success mt-3 mb-0">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong><?php echo t('cache_success', 'Başarılı:'); ?></strong> <?php echo t('cache_success_text', 'Önbellek sistemi çalışıyor. Dashboard sayfasını ziyaret ettiğinizde önbellek dosyaları oluşturulacaktır.'); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('cache_performance', 'Önbellek Performansı'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th><?php echo t('cache_hit_rate', 'Hit Oranı'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['hit_rate'] ?? 0, 2); ?>%</td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_total_queries', 'Toplam Sorgu'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['total_queries'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_hits', 'Cache Hit'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['cache_hits'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_misses', 'Cache Miss'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['cache_misses'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_load_reduction', 'Yük Azaltma'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['load_reduction'] ?? 0, 2); ?>%</td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_avg_query_time', 'Ortalama Sorgu Süresi'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['avg_query_time'] ?? 0, 4); ?> <?php echo t('calculators_seconds', 'saniye'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cache Performance Chart -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title"><?php echo t('cache_performance', 'Önbellek Performansı'); ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <canvas id="cachePerformanceChart" height="300"></canvas>
            </div>
            <div class="col-md-4">
                <h5 class="border-bottom pb-2 mb-3"><?php echo t('cache_performance_stats', 'Performans İstatistikleri'); ?></h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th><?php echo t('cache_hit_rate_label', 'Önbellek Hit Oranı'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['hit_rate'] ?? 0, 2); ?>%</td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_total_queries', 'Toplam Sorgular'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['total_queries'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_fetched_from_cache', 'Önbellekten Getirilen'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['cache_hits'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_fetched_from_db', 'Veritabanından Getirilen'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['cache_misses'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_estimated_load_reduction', 'Tahmini Yük Azaltma'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['load_reduction'] ?? 0, 2); ?>%</td>
                            </tr>
                            <tr>
                                <th><?php echo t('cache_avg_query_time', 'Ortalama Sorgu Süresi'); ?></th>
                                <td class="text-end"><?php echo number_format($cacheStats['avg_query_time'] ?? 0, 4); ?> <?php echo t('calculators_seconds', 'saniye'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Cache Performance Chart
        const ctx = document.getElementById('cachePerformanceChart').getContext('2d');
        const cachePerformanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($cacheStats['performance_data']['labels']); ?>,
                datasets: [
                    {
                        label: '<?php echo t('cache_hit_rate_chart', 'Hit Oranı (%)'); ?>',
                        data: <?php echo json_encode($cacheStats['performance_data']['hit_rates']); ?>,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: '<?php echo t('cache_load_reduction_chart', 'Yük Azaltma (%)'); ?>',
                        data: <?php echo json_encode($cacheStats['performance_data']['load_reductions']); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: '<?php echo t('cache_percentage', 'Yüzde (%)'); ?>'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '<?php echo t('cache_date', 'Tarih'); ?>'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    });
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>