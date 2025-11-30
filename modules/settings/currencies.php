<?php
/**
 * Megabre StokMaster Pro
 * Currency Management
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

// Get action - check both 'action' and second 'action' parameter
$action = isset($_GET['subaction']) ? $_GET['subaction'] : (isset($_GET['action']) && $_GET['action'] != 'currencies' ? $_GET['action'] : 'index');
$currencyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Process actions
switch ($action) {
    case 'add':
    case 'edit':
        // Add or Edit Currency
        $isEditing = ($action == 'edit' && $currencyId > 0);
        $currency = null;
        
        if ($isEditing) {
            $db->query("SELECT * FROM currencies WHERE id = :id");
            $db->bind(':id', $currencyId);
            $currency = $db->single();
            
            if (!$currency) {
                Session::setFlash('error', t('currencies_not_found', 'Para birimi bulunamadı.'));
                redirect('index.php?module=settings&action=currencies');
            }
        }
        
        $errors = [];
        
        if (isPost()) {
            if (!validateCsrf()) {
                redirect('index.php?module=settings&action=currencies');
            }
            
            $code = strtoupper(trim(post('code')));
            $name = post('name');
            $prefix = post('prefix');
            $suffix = post('suffix');
            $format = post('format');
            $baseRate = floatval(post('base_rate'));
            $decimalPlaces = intval(post('decimal_places'));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $isDefault = isset($_POST['is_default']) ? 1 : 0;
            
            // Validation
            if (empty($code) || strlen($code) < 1) {
                $errors[] = t('currencies_code_required', 'Para birimi kodu gereklidir.');
            }
            
            if (strlen($code) > 10) {
                $errors[] = t('currencies_code_too_long', 'Para birimi kodu en fazla 10 karakter olabilir.');
            }
            
            if (empty($name)) {
                $errors[] = t('currencies_name_required', 'Para birimi adı gereklidir.');
            }
            
            if ($baseRate <= 0) {
                $errors[] = t('currencies_rate_invalid', 'Dönüşüm oranı 0\'dan büyük olmalıdır.');
            }
            
            // Check if code already exists (for new currency)
            if (!$isEditing) {
                $db->query("SELECT id FROM currencies WHERE code = :code");
                $db->bind(':code', $code);
                if ($db->single()) {
                    $errors[] = t('currencies_code_exists', 'Bu para birimi kodu zaten kullanılıyor.');
                }
            } else {
                // Check if code exists for another currency
                $db->query("SELECT id FROM currencies WHERE code = :code AND id != :id");
                $db->bind(':code', $code);
                $db->bind(':id', $currencyId);
                if ($db->single()) {
                    $errors[] = t('currencies_code_exists', 'Bu para birimi kodu zaten kullanılıyor.');
                }
            }
            
            if (empty($errors)) {
                $db->beginTransaction();
                
                try {
                    // If setting as default, unset other defaults
                    if ($isDefault) {
                        $db->query("UPDATE currencies SET is_default = 0");
                        $db->execute();
                        
                        // Update settings
                        $db->query("UPDATE settings SET setting_value = :value WHERE setting_key = 'default_currency_id'");
                        $db->bind(':value', $isEditing ? $currencyId : 'NEW');
                        $db->execute();
                    }
                    
                    if ($isEditing) {
                        // Get old currency data
                        $db->query("SELECT * FROM currencies WHERE id = :id");
                        $db->bind(':id', $currencyId);
                        $oldCurrency = $db->single();
                        
                        // Prepare old data for logging
                        $oldData = [
                            'code' => $oldCurrency['code'],
                            'name' => $oldCurrency['name'],
                            'prefix' => $oldCurrency['prefix'] ?? '',
                            'suffix' => $oldCurrency['suffix'] ?? '',
                            'format' => $oldCurrency['format'] ?? '',
                            'base_rate' => $oldCurrency['base_rate'] ?? 1,
                            'decimal_places' => $oldCurrency['decimal_places'] ?? 2,
                            'is_active' => $oldCurrency['is_active'],
                            'is_default' => $oldCurrency['is_default']
                        ];
                        
                        // Update currency
                        $db->query("UPDATE currencies SET 
                                   code = :code, name = :name, prefix = :prefix, suffix = :suffix,
                                   format = :format, base_rate = :base_rate, decimal_places = :decimal_places,
                                   is_active = :is_active, is_default = :is_default, updated_at = NOW()
                                   WHERE id = :id");
                        $db->bind(':code', $code);
                        $db->bind(':name', $name);
                        $db->bind(':prefix', $prefix);
                        $db->bind(':suffix', $suffix);
                        $db->bind(':format', $format);
                        $db->bind(':base_rate', $baseRate);
                        $db->bind(':decimal_places', $decimalPlaces);
                        $db->bind(':is_active', $isActive);
                        $db->bind(':is_default', $isDefault);
                        $db->bind(':id', $currencyId);
                        $db->execute();
                        
                        // Prepare new data for logging
                        $newData = [
                            'code' => $code,
                            'name' => $name,
                            'prefix' => $prefix,
                            'suffix' => $suffix,
                            'format' => $format,
                            'base_rate' => $baseRate,
                            'decimal_places' => $decimalPlaces,
                            'is_active' => $isActive,
                            'is_default' => $isDefault
                        ];
                        
                        // Log activity
                        logActivity('update_currency', 'currency', $currencyId, $oldData, $newData, "Para birimi güncellendi: {$code}");
                        
                        Session::setFlash('success', t('currencies_update_success', 'Para birimi başarıyla güncellendi.'));
                    } else {
                        // Insert currency
                        $db->query("INSERT INTO currencies (code, name, prefix, suffix, format, base_rate, decimal_places, is_active, is_default) 
                                   VALUES (:code, :name, :prefix, :suffix, :format, :base_rate, :decimal_places, :is_active, :is_default)");
                        $db->bind(':code', $code);
                        $db->bind(':name', $name);
                        $db->bind(':prefix', $prefix);
                        $db->bind(':suffix', $suffix);
                        $db->bind(':format', $format);
                        $db->bind(':base_rate', $baseRate);
                        $db->bind(':decimal_places', $decimalPlaces);
                        $db->bind(':is_active', $isActive);
                        $db->bind(':is_default', $isDefault);
                        $db->execute();
                        
                        $newCurrencyId = $db->lastInsertId();
                        
                        // Log activity
                        logActivity('add_currency', 'currency', $newCurrencyId, null, [
                            'code' => $code,
                            'name' => $name,
                            'prefix' => $prefix,
                            'suffix' => $suffix,
                            'is_active' => $isActive,
                            'is_default' => $isDefault
                        ], "Yeni para birimi eklendi: {$code} - {$name}");
                        
                        if ($isDefault) {
                            $db->query("UPDATE settings SET setting_value = :value WHERE setting_key = 'default_currency_id'");
                            $db->bind(':value', $newCurrencyId);
                            $db->execute();
                        }
                        
                        Session::setFlash('success', t('currencies_add_success', 'Para birimi başarıyla eklendi.'));
                    }
                    
                    $db->endTransaction();
                    redirect('index.php?module=settings&action=currencies');
                    
                } catch (PDOException $e) {
                    $db->cancelTransaction();
                    $errors[] = t('currencies_error', 'İşlem sırasında bir hata oluştu:') . ' ' . $e->getMessage();
                }
            }
        }
        
        include_once INCLUDES_PATH . 'header.php';
        ?>
        
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title"><?php echo $isEditing ? t('currencies_edit', 'Para Birimi Düzenle') : t('currencies_add', 'Para Birimi Ekle'); ?></h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings&action=currencies'); ?>"><?php echo t('currencies_title', 'Para Birimleri'); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $isEditing ? t('currencies_edit', 'Düzenle') : t('currencies_add', 'Ekle'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><?php echo $isEditing ? t('currencies_edit_info', 'Para Birimi Bilgilerini Düzenle') : t('currencies_add_info', 'Yeni Para Birimi Ekle'); ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php echo csrfField(); ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="code" class="form-label required"><?php echo t('currencies_code', 'Para Birimi Kodu'); ?></label>
                                    <input type="text" class="form-control" id="code" name="code" 
                                           value="<?php echo e($currency['code'] ?? ''); ?>" 
                                           maxlength="10" required>
                                    <small class="text-muted"><?php echo t('currencies_code_example', 'Örnek: TRY, USD, EUR veya özel kodlarınız'); ?></small>
                                </div>
                                <div class="col-md-6">
                                    <label for="name" class="form-label required"><?php echo t('currencies_name', 'Para Birimi Adı'); ?></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo e($currency['name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="prefix" class="form-label"><?php echo t('currencies_prefix', 'Önek'); ?></label>
                                    <input type="text" class="form-control" id="prefix" name="prefix" 
                                           value="<?php echo e($currency['prefix'] ?? ''); ?>" maxlength="10">
                                    <small class="text-muted"><?php echo t('currencies_prefix_example', 'Örnek: ₺, $, €'); ?></small>
                                </div>
                                <div class="col-md-6">
                                    <label for="suffix" class="form-label"><?php echo t('currencies_suffix', 'Sonek'); ?></label>
                                    <input type="text" class="form-control" id="suffix" name="suffix" 
                                           value="<?php echo e($currency['suffix'] ?? ''); ?>" maxlength="10">
                                    <small class="text-muted"><?php echo t('currencies_suffix_example', 'Örnek: TL, USD, EUR'); ?></small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="format" class="form-label"><?php echo t('currencies_format', 'Biçim'); ?></label>
                                    <select class="form-select" id="format" name="format">
                                        <option value="1234.56" <?php echo ($currency['format'] ?? '') == '1234.56' ? 'selected' : ''; ?>>1234.56</option>
                                        <option value="1,234.56" <?php echo ($currency['format'] ?? '') == '1,234.56' ? 'selected' : ''; ?>>1,234.56</option>
                                        <option value="1.234,56" <?php echo ($currency['format'] ?? '') == '1.234,56' ? 'selected' : ''; ?>>1.234,56</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="decimal_places" class="form-label"><?php echo t('currencies_decimal_places', 'Ondalık Basamak'); ?></label>
                                    <input type="number" class="form-control" id="decimal_places" name="decimal_places" 
                                           value="<?php echo e($currency['decimal_places'] ?? 2); ?>" min="0" max="5">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="base_rate" class="form-label required"><?php echo t('currencies_base_rate', 'Baz Dönüşüm Oranı'); ?></label>
                                    <input type="number" class="form-control" id="base_rate" name="base_rate" 
                                           value="<?php echo e($currency['base_rate'] ?? '1.00000'); ?>" 
                                           step="0.00001" min="0.00001" required>
                                    <small class="text-muted"><?php echo t('currencies_base_rate_desc', 'TRY baz alınarak dönüşüm oranı (TRY = 1.00000)'); ?></small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               <?php echo ($currency['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active"><?php echo t('currencies_active', 'Aktif'); ?></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_default" name="is_default" 
                                               <?php echo ($currency['is_default'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_default"><?php echo t('currencies_default', 'Varsayılan Para Birimi'); ?></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <a href="<?php echo url('index.php?module=settings&action=currencies'); ?>" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> <?php echo t('cancel', 'İptal'); ?>
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?php echo t('save', 'Kaydet'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        include_once INCLUDES_PATH . 'footer.php';
        break;
        
    case 'delete':
        // Delete currency
        if ($currencyId > 0) {
            $db->beginTransaction();
            
            try {
                // Check if currency is default
                $db->query("SELECT is_default FROM currencies WHERE id = :id");
                $db->bind(':id', $currencyId);
                $currency = $db->single();
                
                if ($currency && $currency['is_default']) {
                    Session::setFlash('error', t('currencies_cannot_delete_default', 'Varsayılan para birimi silinemez.'));
                    redirect('index.php?module=settings&action=currencies');
                }
                
                // Log activity before deletion
                logActivity('delete_currency', 'currency', $currencyId, [
                    'code' => $currency['code'],
                    'name' => $currency['name'],
                    'prefix' => $currency['prefix'] ?? '',
                    'suffix' => $currency['suffix'] ?? ''
                ], null, "Para birimi silindi: {$currency['code']} - {$currency['name']}");
                
                // Delete currency
                $db->query("DELETE FROM currencies WHERE id = :id");
                $db->bind(':id', $currencyId);
                $db->execute();
                
                $db->endTransaction();
                Session::setFlash('success', t('currencies_delete_success', 'Para birimi başarıyla silindi.'));
            } catch (PDOException $e) {
                $db->cancelTransaction();
                Session::setFlash('error', t('currencies_delete_error', 'Para birimi silinirken bir hata oluştu.'));
            }
        }
        
        redirect('index.php?module=settings&action=currencies');
        break;
        
    default:
        // List currencies
        $db->query("SELECT * FROM currencies ORDER BY is_default DESC, code ASC");
        $currencies = $db->resultSet();
        
        // Get default currency ID
        $db->query("SELECT setting_value FROM settings WHERE setting_key = 'default_currency_id'");
        $defaultCurrencySetting = $db->single();
        $defaultCurrencyId = $defaultCurrencySetting ? (int)$defaultCurrencySetting['setting_value'] : 1;
        
        include_once INCLUDES_PATH . 'header.php';
        ?>
        
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title"><?php echo t('currencies_title', 'Para Birimleri'); ?></h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo t('currencies_title', 'Para Birimleri'); ?></li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="<?php echo url('index.php?module=settings&action=currencies&subaction=add'); ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo t('currencies_add', 'Para Birimi Ekle'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><?php echo t('currencies_list', 'Para Birimleri Listesi'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><?php echo t('currencies_code_short', 'Kod'); ?></th>
                                        <th><?php echo t('currencies_name_short', 'Ad'); ?></th>
                                        <th><?php echo t('currencies_prefix', 'Önek'); ?></th>
                                        <th><?php echo t('currencies_suffix', 'Sonek'); ?></th>
                                        <th><?php echo t('currencies_base_rate', 'Dönüşüm Oranı'); ?></th>
                                        <th><?php echo t('currencies_format', 'Biçim'); ?></th>
                                        <th><?php echo t('currencies_status', 'Durum'); ?></th>
                                        <th><?php echo t('actions', 'İşlemler'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($currencies)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center"><?php echo t('currencies_no_currencies', 'Henüz para birimi eklenmemiş.'); ?></td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($currencies as $currency): ?>
                                    <tr>
                                        <td><strong><?php echo e($currency['code']); ?></strong></td>
                                        <td><?php echo e($currency['name']); ?></td>
                                        <td><?php echo e($currency['prefix'] ?? '-'); ?></td>
                                        <td><?php echo e($currency['suffix'] ?? '-'); ?></td>
                                        <td><strong><?php echo number_format($currency['base_rate'], 5); ?></strong></td>
                                        <td><?php echo e($currency['format'] ?? '1234.56'); ?></td>
                                        <td>
                                            <?php if ($currency['is_default']): ?>
                                            <span class="badge bg-primary"><?php echo t('currencies_default', 'Varsayılan'); ?></span>
                                            <?php endif; ?>
                                            <?php if ($currency['is_active']): ?>
                                            <span class="badge bg-success"><?php echo t('currencies_active', 'Aktif'); ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo t('currencies_inactive', 'Pasif'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo url('index.php?module=settings&action=currencies&subaction=edit&id=' . $currency['id']); ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if (!$currency['is_default']): ?>
                                            <a href="<?php echo url('index.php?module=settings&action=currencies&subaction=delete&id=' . $currency['id']); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('<?php echo t('currencies_delete_confirm', 'Bu para birimini silmek istediğinizden emin misiniz?'); ?>');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        include_once INCLUDES_PATH . 'footer.php';
        break;
}
?>

