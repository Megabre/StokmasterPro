<?php
/**
 * Megabre StokMaster Pro
 * System Settings
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

// Get current settings
$db->query("SELECT * FROM settings");
$settingsResult = $db->resultSet();

$settings = [];
foreach ($settingsResult as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Define languages path if not defined
if (!defined('LANGUAGES_PATH')) {
    define('LANGUAGES_PATH', ROOT_PATH . 'languages/');
}

// Get available languages
$languages = [];
if (is_dir(LANGUAGES_PATH)) {
    foreach (scandir(LANGUAGES_PATH) as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'php') {
            $code = pathinfo($file, PATHINFO_FILENAME);
            $languages[$code] = ucfirst($code);
        }
    }
}

// Process form submission
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=settings&action=system');
    }
    
    // Get form data
    $siteName = post('site_name');
    $companyName = post('company_name');
    $companyAddress = post('company_address');
    $companyPhone = post('company_phone');
    $companyEmail = post('company_email');
    $companyTaxId = post('company_tax_id');
    $defaultCurrency = post('default_currency');
    $dateFormat = post('date_format');
    $timezone = post('timezone');
    $maxUploadSize = post('max_upload_size');
    $activityLogRetentionDays = post('activity_log_retention_days', 30);
    $logo = null;
    
    // Validate form data
    $errors = [];
    
    if (empty($siteName)) {
        $errors[] = t('settings_system_site_name_required', 'Site adı gereklidir.');
    }
    
    if (empty($companyName)) {
        $errors[] = t('settings_system_company_name_required', 'Firma adı gereklidir.');
    }
    
    if (empty($timezone)) {
        $errors[] = t('settings_system_timezone_required', 'Zaman dilimi gereklidir.');
    }
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 1 * 1024 * 1024; // 1MB
        
        if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
            $errors[] = t('settings_system_logo_format_error', 'Logo sadece JPEG, PNG veya GIF formatında olabilir.');
        } elseif ($_FILES['logo']['size'] > $maxSize) {
            $errors[] = t('settings_system_logo_size_error', 'Logo boyutu maksimum 1MB olabilir.');
        } else {
            $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $logo = 'logo_' . time() . '.' . $extension;
            $uploadPath = UPLOADS_PATH . 'company/';
            
            // Create directory if not exists
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }
            
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath . $logo)) {
                $errors[] = t('settings_system_logo_upload_error', 'Logo yüklenirken bir hata oluştu.');
                $logo = null;
            }
        }
    }
    
    if (empty($errors)) {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Update settings
            $settingsToUpdate = [
                'site_name' => $siteName,
                'company_name' => $companyName,
                'company_address' => $companyAddress,
                'company_phone' => $companyPhone,
                'company_email' => $companyEmail,
                'company_tax_id' => $companyTaxId,
                'default_currency' => $defaultCurrency,
                'date_format' => $dateFormat,
                'timezone' => $timezone,
                'max_upload_size' => $maxUploadSize,
                'activity_log_retention_days' => $activityLogRetentionDays,
                'last_update' => date('Y-m-d H:i:s')
            ];
            
            // Add logo if uploaded
            if ($logo) {
                $settingsToUpdate['company_logo'] = $logo;
                
                // Delete old logo if exists
                if (!empty($settings['company_logo'])) {
                    $oldLogo = UPLOADS_PATH . 'company/' . $settings['company_logo'];
                    if (file_exists($oldLogo)) {
                        unlink($oldLogo);
                    }
                }
            }
            
            // Track settings changes for logging
            $settingsChanges = [];
            
            // Update settings in database
            foreach ($settingsToUpdate as $key => $value) {
                try {
                    // Önce mevcut ayarı kontrol et
                    $db->query("SELECT id, setting_value FROM settings WHERE setting_key = :key");
                    $db->bind(':key', $key);
                    $result = $db->single();
                    
                    $oldValue = $result ? $result['setting_value'] : null;
                    
                    if ($result) {
                        // Ayar varsa güncelle
                        $db->query("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
                        $db->bind(':key', $key);
                        $db->bind(':value', $value);
                    } else {
                        // Ayar yoksa ekle
                        $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)");
                        $db->bind(':key', $key);
                        $db->bind(':value', $value);
                    }
                    $db->execute();
                    
                    // Track changes
                    if ($oldValue != $value) {
                        $settingsChanges[$key] = [
                            'old_value' => $oldValue,
                            'new_value' => $value
                        ];
                    }
                } catch (Exception $e) {
                    throw new Exception(t('settings_system_update_error', 'Ayar güncellenirken hata: ') . $e->getMessage());
                }
            }
            
            // Log activity if there are changes
            if (!empty($settingsChanges)) {
                logActivity('update_system_settings', 'settings', 0, 
                    array_map(function($change) { return $change['old_value']; }, $settingsChanges),
                    array_map(function($change) { return $change['new_value']; }, $settingsChanges),
                    "Sistem ayarları güncellendi"
                );
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Set success message
            Session::setFlash('success', t('settings_system_update_success', 'Sistem ayarları başarıyla güncellendi.'));
            
            // Redirect to refresh settings
            redirect('index.php?module=settings&action=system');
            
        } catch (Exception $e) {
            // Rollback transaction
            $db->cancelTransaction();
            
            // Delete uploaded logo if exists
            if ($logo && file_exists(UPLOADS_PATH . 'company/' . $logo)) {
                unlink(UPLOADS_PATH . 'company/' . $logo);
            }
            
            $errors[] = t('settings_system_general_error', 'Ayarlar güncellenirken bir hata oluştu: ') . $e->getMessage();
        }
    }
}

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
            <h3 class="page-title"><?php echo t('settings_system_title', 'Sistem Ayarları'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('settings_system_title', 'Sistem Ayarları'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Settings Form -->
<form action="<?php echo url('index.php?module=settings&action=system'); ?>" method="post" enctype="multipart/form-data">
    <?php echo csrfField(); ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- General Settings -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('settings_system_general', 'Genel Ayarlar'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_name" class="form-label required"><?php echo t('settings_system_site_name', 'Site Adı'); ?></label>
                                <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo e($settings['site_name'] ?? 'Megabre StokMaster Pro'); ?>" required>
                                <small class="text-muted"><?php echo t('settings_system_site_name_desc', 'Tarayıcı başlığında görünecek site adı'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="timezone" class="form-label required"><?php echo t('settings_timezone', 'Zaman Dilimi'); ?></label>
                                <select class="form-select" id="timezone" name="timezone" required>
                                    <?php
                                    $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
                                    $currentTimezone = $settings['timezone'] ?? 'Europe/Istanbul';
                                    foreach ($timezones as $tz): 
                                    ?>
                                    <option value="<?php echo $tz; ?>" <?php echo $currentTimezone == $tz ? 'selected' : ''; ?>><?php echo $tz; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_format" class="form-label required"><?php echo t('settings_system_date_format', 'Tarih Formatı'); ?></label>
                                <select class="form-select" id="date_format" name="date_format" required>
                                    <?php
                                    $now = new DateTime();
                                    $formats = [
                                        'd.m.Y' => $now->format('d.m.Y'),
                                        'd/m/Y' => $now->format('d/m/Y'),
                                        'Y-m-d' => $now->format('Y-m-d'),
                                        'd.m.Y H:i' => $now->format('d.m.Y H:i'),
                                        'd/m/Y H:i' => $now->format('d/m/Y H:i'),
                                        'Y-m-d H:i' => $now->format('Y-m-d H:i')
                                    ];
                                    $currentFormat = $settings['date_format'] ?? 'd.m.Y';
                                    
                                    foreach ($formats as $format => $example): 
                                    ?>
                                    <option value="<?php echo $format; ?>" <?php echo $currentFormat == $format ? 'selected' : ''; ?>><?php echo $example; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="default_currency" class="form-label required"><?php echo t('settings_system_currency', 'Para Birimi'); ?></label>
                                <select class="form-select" id="default_currency" name="default_currency" required>
                                    <option value="TRY" <?php echo ($settings['default_currency'] ?? 'TRY') == 'TRY' ? 'selected' : ''; ?>><?php echo t('settings_system_currency_try', 'Türk Lirası (₺)'); ?></option>
                                    <option value="USD" <?php echo ($settings['default_currency'] ?? 'TRY') == 'USD' ? 'selected' : ''; ?>><?php echo t('settings_system_currency_usd', 'Amerikan Doları ($)'); ?></option>
                                    <option value="EUR" <?php echo ($settings['default_currency'] ?? 'TRY') == 'EUR' ? 'selected' : ''; ?>><?php echo t('settings_system_currency_eur', 'Euro (€)'); ?></option>
                                    <option value="GBP" <?php echo ($settings['default_currency'] ?? 'TRY') == 'GBP' ? 'selected' : ''; ?>><?php echo t('settings_system_currency_gbp', 'İngiliz Sterlini (£)'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_upload_size" class="form-label required"><?php echo t('settings_max_upload_size', 'Maksimum Yükleme Boyutu (KB)'); ?></label>
                                <input type="number" class="form-control" id="max_upload_size" name="max_upload_size" value="<?php echo e($settings['max_upload_size'] ?? '5000'); ?>" required>
                                <small class="text-muted"><?php echo t('settings_system_max_upload_desc', 'Yüklenebilecek maksimum dosya boyutu (KB)'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="activity_log_retention_days" class="form-label required"><?php echo t('settings_activity_log_retention', 'Son İşlemler Tutma Süresi (Gün)'); ?></label>
                                <select class="form-select" id="activity_log_retention_days" name="activity_log_retention_days" required>
                                    <option value="5" <?php echo ($settings['activity_log_retention_days'] ?? 30) == 5 ? 'selected' : ''; ?>>5 <?php echo t('days', 'Gün'); ?></option>
                                    <option value="10" <?php echo ($settings['activity_log_retention_days'] ?? 30) == 10 ? 'selected' : ''; ?>>10 <?php echo t('days', 'Gün'); ?></option>
                                    <option value="15" <?php echo ($settings['activity_log_retention_days'] ?? 30) == 15 ? 'selected' : ''; ?>>15 <?php echo t('days', 'Gün'); ?></option>
                                    <option value="30" <?php echo ($settings['activity_log_retention_days'] ?? 30) == 30 ? 'selected' : ''; ?>>30 <?php echo t('days', 'Gün'); ?></option>
                                    <option value="60" <?php echo ($settings['activity_log_retention_days'] ?? 30) == 60 ? 'selected' : ''; ?>>60 <?php echo t('days', 'Gün'); ?></option>
                                    <option value="90" <?php echo ($settings['activity_log_retention_days'] ?? 30) == 90 ? 'selected' : ''; ?>>90 <?php echo t('days', 'Gün'); ?></option>
                                </select>
                                <small class="text-muted"><?php echo t('settings_activity_log_retention_desc', 'Son işlemler modülünde kaç günlük işlemlerin tutulacağını belirler'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Company Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('settings_system_company_info', 'Firma Bilgileri'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_name" class="form-label required"><?php echo t('settings_system_company_name', 'Firma Adı'); ?></label>
                                <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo e($settings['company_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_tax_id" class="form-label"><?php echo t('settings_system_tax_id', 'Vergi Numarası'); ?></label>
                                <input type="text" class="form-control" id="company_tax_id" name="company_tax_id" value="<?php echo e($settings['company_tax_id'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_phone" class="form-label"><?php echo t('phone', 'Telefon'); ?></label>
                                <input type="text" class="form-control" id="company_phone" name="company_phone" value="<?php echo e($settings['company_phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_email" class="form-label"><?php echo t('email', 'E-posta'); ?></label>
                                <input type="email" class="form-control" id="company_email" name="company_email" value="<?php echo e($settings['company_email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="company_address" class="form-label"><?php echo t('address', 'Adres'); ?></label>
                        <textarea class="form-control" id="company_address" name="company_address" rows="3"><?php echo e($settings['company_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Logo Upload -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('settings_system_company_logo', 'Firma Logosu'); ?></h5>
                </div>
                <div class="card-body text-center">
                    <?php if (isset($settings['company_logo']) && !empty($settings['company_logo'])): ?>
                    <div class="mb-3">
                        <img src="<?php echo url('uploads/company/' . $settings['company_logo']); ?>" alt="Logo" class="img-fluid mb-3" style="max-height: 150px;">
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <div class="no-logo-placeholder p-4 bg-light text-center rounded mb-3">
                            <i class="fas fa-image fa-5x text-muted"></i>
                            <p class="mt-2 text-muted"><?php echo t('settings_system_logo_not_uploaded', 'Logo yüklenmemiş'); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="logo" class="form-label"><?php echo t('settings_system_upload_logo', 'Yeni Logo Yükle'); ?></label>
                        <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg,image/png,image/gif">
                        <small class="text-muted"><?php echo t('settings_system_logo_requirements', 'Maksimum boyut: 1MB. Önerilen: 250x100 piksel'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><?php echo t('settings_system_info', 'Sistem Bilgileri'); ?></h5>
                </div>
                <div class="card-body">
                    <?php
                    $versions = getSystemVersions();
                    ?>
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <th width="40%"><?php echo t('settings_version', 'Versiyon'); ?></th>
                                <td><span class="badge bg-primary">v<?php echo $versions['app']; ?></span></td>
                            </tr>
                            <tr>
                                <th><?php echo t('settings_php_version', 'PHP Versiyonu'); ?></th>
                                <td><?php echo $versions['php']; ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('settings_mysql_version', 'MySQL Versiyonu'); ?></th>
                                <td><?php echo $versions['mysql']; ?></td>
                            </tr>
                            <tr>
                                <th><?php echo t('settings_app_name', 'Uygulama Adı'); ?></th>
                                <td><?php echo defined('APP_NAME') ? APP_NAME : 'StokMaster Pro'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy"></i> <?php echo t('settings_system_save', 'Ayarları Kaydet'); ?>
                        </button>
                        <a href="<?php echo url('index.php?module=settings'); ?>" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> <?php echo t('settings_system_back', 'Ayarlara Dön'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>