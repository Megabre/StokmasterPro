<?php
/**
 * Megabre StokMaster Pro
 * Database Optimization Tool
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

// Initialize database connection
$db = Database::getInstance();

// Get all tables
$db->query("SHOW TABLES");
$tables = $db->resultSet();
$tableNames = [];
foreach ($tables as $table) {
    $tableNames[] = array_values($table)[0];
}

// Get table status information
$tableStatuses = [];
foreach ($tableNames as $tableName) {
    // Sanitize table name for security
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $db->query("SHOW TABLE STATUS LIKE '$tableName'");
    $status = $db->single();
    if ($status) {
        $tableStatuses[$tableName] = $status;
    }
}

// Calculate total database size
$totalSize = 0;
$totalRows = 0;
$totalDataLength = 0;
$totalIndexLength = 0;
$totalDataFree = 0;

foreach ($tableStatuses as $status) {
    $totalSize += ($status['Data_length'] ?? 0) + ($status['Index_length'] ?? 0);
    $totalRows += $status['Rows'] ?? 0;
    $totalDataLength += $status['Data_length'] ?? 0;
    $totalIndexLength += $status['Index_length'] ?? 0;
    $totalDataFree += $status['Data_free'] ?? 0;
}

$totalSizeMB = round($totalSize / 1024 / 1024, 2);
$totalDataLengthMB = round($totalDataLength / 1024 / 1024, 2);
$totalIndexLengthMB = round($totalIndexLength / 1024 / 1024, 2);
$totalDataFreeMB = round($totalDataFree / 1024 / 1024, 2);
$fragmentationPercent = $totalSize > 0 ? round(($totalDataFree / $totalSize) * 100, 2) : 0;

// Process optimization actions
$message = '';
$status = '';
$optimizationResults = [];

if (isset($_POST['optimize_action'])) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=tools&action=optimize');
    }
    
    $action = $_POST['optimize_action'];
    $selectedTables = isset($_POST['tables']) ? $_POST['tables'] : [];
    
    if (empty($selectedTables) && $action != 'all') {
        $message = t('optimize_no_tables_selected', 'Lütfen optimize edilecek tabloları seçin.');
        $status = 'warning';
    } else {
        $tablesToProcess = ($action == 'all') ? $tableNames : $selectedTables;
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($tablesToProcess as $tableName) {
            // Sanitize table name
            $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            
            if (!in_array($tableName, $tableNames)) {
                $optimizationResults[$tableName] = [
                    'status' => 'error',
                    'message' => t('optimize_table_not_found', 'Tablo bulunamadı')
                ];
                $errorCount++;
                continue;
            }
            
            try {
                switch ($action) {
                    case 'optimize':
                        $db->query("OPTIMIZE TABLE `$tableName`");
                        $result = $db->resultSet();
                        $optimizationResults[$tableName] = [
                            'status' => 'success',
                            'message' => t('optimize_table_optimized', 'Tablo optimize edildi'),
                            'details' => $result
                        ];
                        $successCount++;
                        break;
                        
                    case 'analyze':
                        $db->query("ANALYZE TABLE `$tableName`");
                        $result = $db->resultSet();
                        $optimizationResults[$tableName] = [
                            'status' => 'success',
                            'message' => t('optimize_table_analyzed', 'Tablo analiz edildi'),
                            'details' => $result
                        ];
                        $successCount++;
                        break;
                        
                    case 'check':
                        $db->query("CHECK TABLE `$tableName`");
                        $result = $db->resultSet();
                        $optimizationResults[$tableName] = [
                            'status' => 'success',
                            'message' => t('optimize_table_checked', 'Tablo kontrol edildi'),
                            'details' => $result
                        ];
                        $successCount++;
                        break;
                        
                    case 'repair':
                        $db->query("REPAIR TABLE `$tableName`");
                        $result = $db->resultSet();
                        $optimizationResults[$tableName] = [
                            'status' => 'success',
                            'message' => t('optimize_table_repaired', 'Tablo onarıldı'),
                            'details' => $result
                        ];
                        $successCount++;
                        break;
                        
                    case 'all':
                        // Optimize all tables
                        $db->query("OPTIMIZE TABLE `$tableName`");
                        $result = $db->resultSet();
                        $optimizationResults[$tableName] = [
                            'status' => 'success',
                            'message' => t('optimize_table_optimized', 'Tablo optimize edildi'),
                            'details' => $result
                        ];
                        $successCount++;
                        break;
                }
            } catch (Exception $e) {
                $optimizationResults[$tableName] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }
        
        // Refresh table statuses after optimization
        $tableStatuses = [];
        foreach ($tableNames as $tableName) {
            // Sanitize table name for security
            $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            $db->query("SHOW TABLE STATUS LIKE '$tableName'");
            $status = $db->single();
            if ($status) {
                $tableStatuses[$tableName] = $status;
            }
        }
        
        if ($action == 'all') {
            $message = t('optimize_all_success', 'Tüm tablolar başarıyla optimize edildi.');
        } else {
            $message = sprintf(
                t('optimize_action_completed', '%d tablo başarıyla işlendi, %d tabloda hata oluştu.'),
                $successCount,
                $errorCount
            );
        }
        $status = ($errorCount == 0) ? 'success' : 'warning';
    }
}

// Process flush tables
if (isset($_POST['flush_tables'])) {
    if (!validateCsrf()) {
        redirect('index.php?module=tools&action=optimize');
    }
    
    try {
        // FLUSH TABLES - Clears table cache
        $db->query("FLUSH TABLES");
        $db->execute();
        
        // Try to flush query cache (only if available - MySQL 5.7 and earlier)
        // MySQL 8.0+ removed query cache, so we'll try-catch this
        try {
            $db->query("RESET QUERY CACHE");
            $db->execute();
        } catch (Exception $e) {
            // Query cache not available (MySQL 8.0+), ignore this error
            // FLUSH TABLES is sufficient for cache clearing
        }
        
        $message = t('optimize_flush_success', 'Tablo cache\'i başarıyla temizlendi.');
        $status = 'success';
    } catch (Exception $e) {
        $message = t('optimize_flush_error', 'Cache temizlenirken hata oluştu: ') . $e->getMessage();
        $status = 'error';
    }
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('optimize_title', 'Veritabanı Optimizasyonu'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=tools'); ?>"><?php echo t('tools_title', 'Araçlar'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('optimize_title', 'Veritabanı Optimizasyonu'); ?></li>
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

<!-- Database Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary"><?php echo $totalSizeMB; ?> MB</h3>
                <p class="text-muted mb-0"><?php echo t('optimize_total_size', 'Toplam Boyut'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-info"><?php echo count($tableNames); ?></h3>
                <p class="text-muted mb-0"><?php echo t('optimize_table_count', 'Tablo Sayısı'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success"><?php echo number_format($totalRows); ?></h3>
                <p class="text-muted mb-0"><?php echo t('optimize_total_rows', 'Toplam Kayıt'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="<?php echo $fragmentationPercent > 10 ? 'text-danger' : 'text-success'; ?>"><?php echo $fragmentationPercent; ?>%</h3>
                <p class="text-muted mb-0"><?php echo t('optimize_fragmentation', 'Fragmentasyon'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Optimization Actions -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('optimize_quick_actions', 'Hızlı İşlemler'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <form method="post" onsubmit="return confirm('<?php echo t('optimize_confirm_all', 'Tüm tabloları optimize etmek istediğinize emin misiniz? Bu işlem biraz zaman alabilir.'); ?>');">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="optimize_action" value="all">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ti ti-wand me-2"></i>
                                <?php echo t('optimize_all_tables', 'Tüm Tabloları Optimize Et'); ?>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-3 mb-3">
                        <form method="post" onsubmit="return confirm('<?php echo t('optimize_confirm_flush', 'Tablo cache\'ini temizlemek istediğinize emin misiniz?'); ?>');">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="flush_tables" value="1">
                            <button type="submit" class="btn btn-info w-100">
                                <i class="ti ti-refresh me-2"></i>
                                <?php echo t('optimize_flush_cache', 'Cache Temizle'); ?>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button type="button" class="btn btn-success w-100" onclick="analyzeAllTables()">
                            <i class="ti ti-chart-bar me-2"></i>
                            <?php echo t('optimize_analyze_all', 'Tümünü Analiz Et'); ?>
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button type="button" class="btn btn-warning w-100" onclick="checkAllTables()">
                            <i class="ti ti-check me-2"></i>
                            <?php echo t('optimize_check_all', 'Tümünü Kontrol Et'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Table List -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('optimize_table_list', 'Tablo Listesi ve Durum'); ?></h5>
            </div>
            <div class="card-body">
                <form method="post" id="optimizeForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="optimize_action" id="optimizeAction" value="">
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllTables()">
                            <i class="ti ti-checkbox me-1"></i> <?php echo t('optimize_select_all', 'Tümünü Seç'); ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAllTables()">
                            <i class="ti ti-square me-1"></i> <?php echo t('optimize_deselect_all', 'Tümünü Kaldır'); ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="optimizeSelected()">
                            <i class="ti ti-wand me-1"></i> <?php echo t('optimize_selected', 'Seçilenleri Optimize Et'); ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="analyzeSelected()">
                            <i class="ti ti-chart-bar me-1"></i> <?php echo t('optimize_analyze_selected', 'Seçilenleri Analiz Et'); ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" onclick="checkSelected()">
                            <i class="ti ti-check me-1"></i> <?php echo t('optimize_check_selected', 'Seçilenleri Kontrol Et'); ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="repairSelected()">
                            <i class="ti ti-tools me-1"></i> <?php echo t('optimize_repair_selected', 'Seçilenleri Onar'); ?>
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" onchange="toggleAllTables()">
                                    </th>
                                    <th><?php echo t('optimize_table_name', 'Tablo Adı'); ?></th>
                                    <th><?php echo t('optimize_rows', 'Kayıt Sayısı'); ?></th>
                                    <th><?php echo t('optimize_data_size', 'Veri Boyutu'); ?></th>
                                    <th><?php echo t('optimize_index_size', 'İndex Boyutu'); ?></th>
                                    <th><?php echo t('optimize_free_space', 'Boş Alan'); ?></th>
                                    <th><?php echo t('optimize_fragmentation', 'Fragmentasyon'); ?></th>
                                    <th><?php echo t('optimize_engine', 'Motor'); ?></th>
                                    <th><?php echo t('optimize_collation', 'Karakter Seti'); ?></th>
                                    <th><?php echo t('actions', 'İşlemler'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableStatuses as $tableName => $status): 
                                    $dataSize = round(($status['Data_length'] ?? 0) / 1024 / 1024, 2);
                                    $indexSize = round(($status['Index_length'] ?? 0) / 1024 / 1024, 2);
                                    $freeSpace = round(($status['Data_free'] ?? 0) / 1024 / 1024, 2);
                                    $totalTableSize = ($status['Data_length'] ?? 0) + ($status['Index_length'] ?? 0);
                                    $fragmentation = $totalTableSize > 0 ? round((($status['Data_free'] ?? 0) / $totalTableSize) * 100, 2) : 0;
                                    $rowCount = number_format($status['Rows'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="tables[]" value="<?php echo e($tableName); ?>" class="table-checkbox">
                                    </td>
                                    <td><strong><?php echo e($tableName); ?></strong></td>
                                    <td><?php echo $rowCount; ?></td>
                                    <td><?php echo $dataSize; ?> MB</td>
                                    <td><?php echo $indexSize; ?> MB</td>
                                    <td><?php echo $freeSpace; ?> MB</td>
                                    <td>
                                        <span class="badge bg-<?php echo $fragmentation > 10 ? 'danger' : ($fragmentation > 5 ? 'warning' : 'success'); ?>">
                                            <?php echo $fragmentation; ?>%
                                        </span>
                                    </td>
                                    <td><?php echo e($status['Engine'] ?? '-'); ?></td>
                                    <td><?php echo e($status['Collation'] ?? '-'); ?></td>
                                    <td>
                                        <div class="btn-list">
                                            <button type="button" class="btn btn-sm btn-primary" onclick="optimizeTable('<?php echo e($tableName); ?>')" title="<?php echo t('optimize_optimize', 'Optimize Et'); ?>">
                                                <i class="ti ti-wand"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-info" onclick="analyzeTable('<?php echo e($tableName); ?>')" title="<?php echo t('optimize_analyze', 'Analiz Et'); ?>">
                                                <i class="ti ti-chart-bar"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning" onclick="checkTable('<?php echo e($tableName); ?>')" title="<?php echo t('optimize_check', 'Kontrol Et'); ?>">
                                                <i class="ti ti-check"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Optimization Results -->
<?php if (!empty($optimizationResults)): ?>
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('optimize_results', 'Optimizasyon Sonuçları'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th><?php echo t('optimize_table_name', 'Tablo Adı'); ?></th>
                                <th><?php echo t('status', 'Durum'); ?></th>
                                <th><?php echo t('optimize_message', 'Mesaj'); ?></th>
                                <th><?php echo t('optimize_details', 'Detaylar'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($optimizationResults as $tableName => $result): ?>
                            <tr>
                                <td><strong><?php echo e($tableName); ?></strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $result['status'] == 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo $result['status'] == 'success' ? t('success', 'Başarılı') : t('error', 'Hata'); ?>
                                    </span>
                                </td>
                                <td><?php echo e($result['message']); ?></td>
                                <td>
                                    <?php if (isset($result['details']) && is_array($result['details'])): ?>
                                        <?php foreach ($result['details'] as $detail): ?>
                                            <?php if (isset($detail['Msg_text'])): ?>
                                                <small class="text-muted"><?php echo e($detail['Msg_text']); ?></small><br>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Information Card -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">
                    <i class="ti ti-info-circle me-2"></i>
                    <?php echo t('optimize_info_title', 'Optimizasyon Hakkında'); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><?php echo t('optimize_what_is', 'Optimizasyon Nedir?'); ?></h6>
                        <p><?php echo t('optimize_what_is_desc', 'Veritabanı optimizasyonu, tablolarınızdaki fragmentasyonu (parçalanmayı) azaltır ve performansı artırır. Özellikle binlerce kayıt içeren tablolarda önemlidir.'); ?></p>
                        
                        <h6><?php echo t('optimize_when', 'Ne Zaman Optimize Edilmeli?'); ?></h6>
                        <ul>
                            <li><?php echo t('optimize_when_1', 'Sistem yavaşladığında'); ?></li>
                            <li><?php echo t('optimize_when_2', 'Fragmentasyon %10\'dan fazla olduğunda'); ?></li>
                            <li><?php echo t('optimize_when_3', 'Çok sayıda silme/güncelleme işlemi yapıldığında'); ?></li>
                            <li><?php echo t('optimize_when_4', 'Düzenli bakım olarak (aylık/haftalık)'); ?></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><?php echo t('optimize_actions', 'İşlem Türleri'); ?></h6>
                        <ul>
                            <li><strong><?php echo t('optimize_action_optimize', 'OPTIMIZE TABLE:'); ?></strong> <?php echo t('optimize_action_optimize_desc', 'Tabloyu optimize eder, fragmentasyonu azaltır.'); ?></li>
                            <li><strong><?php echo t('optimize_action_analyze', 'ANALYZE TABLE:'); ?></strong> <?php echo t('optimize_action_analyze_desc', 'Tablo istatistiklerini günceller, sorgu performansını artırır.'); ?></li>
                            <li><strong><?php echo t('optimize_action_check', 'CHECK TABLE:'); ?></strong> <?php echo t('optimize_action_check_desc', 'Tablo bütünlüğünü kontrol eder.'); ?></li>
                            <li><strong><?php echo t('optimize_action_repair', 'REPAIR TABLE:'); ?></strong> <?php echo t('optimize_action_repair_desc', 'Bozuk tabloları onarır (sadece gerektiğinde kullanın).'); ?></li>
                        </ul>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="ti ti-alert-triangle me-2"></i>
                            <strong><?php echo t('optimize_warning', 'Uyarı:'); ?></strong>
                            <?php echo t('optimize_warning_text', 'Optimizasyon işlemi sırasında tablolar kilitlenebilir. Büyük tablolarda işlem uzun sürebilir.'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAllTables() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.table-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function selectAllTables() {
    document.getElementById('selectAll').checked = true;
    toggleAllTables();
}

function deselectAllTables() {
    document.getElementById('selectAll').checked = false;
    toggleAllTables();
}

function optimizeSelected() {
    if (!confirm('<?php echo t('optimize_confirm_selected', 'Seçilen tabloları optimize etmek istediğinize emin misiniz?'); ?>')) {
        return;
    }
    document.getElementById('optimizeAction').value = 'optimize';
    document.getElementById('optimizeForm').submit();
}

function analyzeSelected() {
    if (!confirm('<?php echo t('optimize_confirm_analyze', 'Seçilen tabloları analiz etmek istediğinize emin misiniz?'); ?>')) {
        return;
    }
    document.getElementById('optimizeAction').value = 'analyze';
    document.getElementById('optimizeForm').submit();
}

function checkSelected() {
    if (!confirm('<?php echo t('optimize_confirm_check', 'Seçilen tabloları kontrol etmek istediğinize emin misiniz?'); ?>')) {
        return;
    }
    document.getElementById('optimizeAction').value = 'check';
    document.getElementById('optimizeForm').submit();
}

function repairSelected() {
    if (!confirm('<?php echo t('optimize_confirm_repair', 'Seçilen tabloları onarmak istediğinize emin misiniz? Bu işlem sadece bozuk tablolar için kullanılmalıdır.'); ?>')) {
        return;
    }
    document.getElementById('optimizeAction').value = 'repair';
    document.getElementById('optimizeForm').submit();
}

function optimizeTable(tableName) {
    if (!confirm('<?php echo t('optimize_confirm_table', 'Bu tabloyu optimize etmek istediğinize emin misiniz?'); ?>')) {
        return;
    }
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = '<?php echo csrfField(); ?><input type="hidden" name="optimize_action" value="optimize"><input type="hidden" name="tables[]" value="' + tableName + '">';
    document.body.appendChild(form);
    form.submit();
}

function analyzeTable(tableName) {
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = '<?php echo csrfField(); ?><input type="hidden" name="optimize_action" value="analyze"><input type="hidden" name="tables[]" value="' + tableName + '">';
    document.body.appendChild(form);
    form.submit();
}

function checkTable(tableName) {
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = '<?php echo csrfField(); ?><input type="hidden" name="optimize_action" value="check"><input type="hidden" name="tables[]" value="' + tableName + '">';
    document.body.appendChild(form);
    form.submit();
}

function analyzeAllTables() {
    if (!confirm('<?php echo t('optimize_confirm_analyze_all', 'Tüm tabloları analiz etmek istediğinize emin misiniz?'); ?>')) {
        return;
    }
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = '<?php echo csrfField(); ?><input type="hidden" name="optimize_action" value="analyze">';
    <?php foreach ($tableNames as $tableName): ?>
    form.innerHTML += '<input type="hidden" name="tables[]" value="<?php echo e($tableName); ?>">';
    <?php endforeach; ?>
    document.body.appendChild(form);
    form.submit();
}

function checkAllTables() {
    if (!confirm('<?php echo t('optimize_confirm_check_all', 'Tüm tabloları kontrol etmek istediğinize emin misiniz?'); ?>')) {
        return;
    }
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = '<?php echo csrfField(); ?><input type="hidden" name="optimize_action" value="check">';
    <?php foreach ($tableNames as $tableName): ?>
    form.innerHTML += '<input type="hidden" name="tables[]" value="<?php echo e($tableName); ?>">';
    <?php endforeach; ?>
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>

