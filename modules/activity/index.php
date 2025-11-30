<?php
/**
 * Megabre StokMaster Pro
 * Activity Logs - Son İşlemler
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

// Get retention days from settings (default: 30 days)
$retentionDays = 30;
try {
    $db->query("SELECT setting_value FROM settings WHERE setting_key = 'activity_log_retention_days'");
    $setting = $db->single();
    if ($setting && isset($setting['setting_value'])) {
        $retentionDays = (int)$setting['setting_value'];
    }
} catch (Exception $e) {
    // Setting not found, use default
}

// Calculate date threshold
$dateThreshold = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

// Get filter parameters
$filterUser = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$filterAction = isset($_GET['action']) ? $_GET['action'] : '';
$filterDate = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$query = "SELECT al.*, u.name, u.surname, u.username,
          CONCAT(u.name, ' ', u.surname) as full_name
          FROM activity_logs al
          LEFT JOIN users u ON al.user_id = u.id
          WHERE al.created_at >= :date_threshold";

$params = [':date_threshold' => $dateThreshold];

if ($filterUser > 0) {
    $query .= " AND al.user_id = :user_id";
    $params[':user_id'] = $filterUser;
}

if (!empty($filterAction)) {
    $query .= " AND al.action LIKE :action";
    $params[':action'] = '%' . $filterAction . '%';
}

if (!empty($filterDate)) {
    $query .= " AND DATE(al.created_at) = :filter_date";
    $params[':filter_date'] = $filterDate;
}

$query .= " ORDER BY al.created_at DESC LIMIT 500";

$db->query($query);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$activities = $db->resultSet();

// Get all users for filter
$db->query("SELECT id, name, surname, username FROM users ORDER BY name ASC, surname ASC");
$allUsers = $db->resultSet();

// Get unique actions for filter
$db->query("SELECT DISTINCT action FROM activity_logs WHERE created_at >= :date_threshold ORDER BY action ASC");
$db->bind(':date_threshold', $dateThreshold);
$allActions = $db->resultSet();

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle"><?php echo t('home', 'Ana Sayfa'); ?></div>
                <h2 class="page-title"><?php echo t('activity_title', 'Son İşlemler'); ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="page-body">
    <div class="container-xl">
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="<?php echo url('index.php?module=activity'); ?>" class="row g-3">
                    <input type="hidden" name="module" value="activity">
                    
                    <div class="col-md-3">
                        <label class="form-label"><?php echo t('activity_filter_user', 'Kullanıcı'); ?></label>
                        <select name="user_id" class="form-select">
                            <option value=""><?php echo t('activity_all_users', 'Tüm Kullanıcılar'); ?></option>
                            <?php foreach ($allUsers as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo e($user['name'] . ' ' . $user['surname'] . ' (' . $user['username'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label"><?php echo t('activity_filter_action', 'İşlem Türü'); ?></label>
                        <select name="action" class="form-select">
                            <option value=""><?php echo t('activity_all_actions', 'Tüm İşlemler'); ?></option>
                            <?php foreach ($allActions as $action): ?>
                            <option value="<?php echo e($action['action']); ?>" <?php echo $filterAction == $action['action'] ? 'selected' : ''; ?>>
                                <?php echo e($action['action']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label"><?php echo t('activity_filter_date', 'Tarih'); ?></label>
                        <input type="date" name="date" class="form-control" value="<?php echo e($filterDate); ?>">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-filter"></i> <?php echo t('filter', 'Filtrele'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo t('activity_timeline', 'İşlem Zaman Tüneli'); ?></h3>
                <div class="card-actions">
                    <span class="badge bg-info"><?php echo count($activities); ?> <?php echo t('activity_items', 'işlem'); ?></span>
                    <span class="badge bg-secondary ms-2"><?php echo t('activity_retention', 'Son'); ?> <?php echo $retentionDays; ?> <?php echo t('activity_days', 'gün'); ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($activities)): ?>
                <div class="text-center py-5">
                    <i class="ti ti-clock-hour-4" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="text-muted mt-3"><?php echo t('activity_no_activities', 'Henüz işlem kaydı bulunmuyor.'); ?></p>
                </div>
                <?php else: ?>
                <div class="activity-timeline">
                    <?php
                    $currentDate = '';
                    foreach ($activities as $activity):
                        $activityDate = date('d.m.Y', strtotime($activity['created_at']));
                        $activityTime = date('H:i', strtotime($activity['created_at']));
                        
                        // Show date separator if date changed
                        if ($currentDate != $activityDate):
                            if ($currentDate != ''):
                                echo '</div>'; // Close previous day group
                            endif;
                            $currentDate = $activityDate;
                            echo '<div class="timeline-day-group mb-4">';
                            echo '<div class="timeline-day-header mb-3">';
                            echo '<h4 class="mb-0">' . $activityDate . '</h4>';
                            echo '</div>';
                        endif;
                        
                        // Get activity icon and color
                        $icon = 'ti-circle';
                        $color = 'primary';
                        $actionText = $activity['action'];
                        
                        // Parse action to get readable text
                        if (strpos($activity['action'], 'create') !== false || strpos($activity['action'], 'add') !== false) {
                            $icon = 'ti-plus';
                            $color = 'success';
                        } elseif (strpos($activity['action'], 'update') !== false || strpos($activity['action'], 'edit') !== false) {
                            $icon = 'ti-edit';
                            $color = 'info';
                        } elseif (strpos($activity['action'], 'delete') !== false || strpos($activity['action'], 'remove') !== false) {
                            $icon = 'ti-trash';
                            $color = 'danger';
                        } elseif (strpos($activity['action'], 'login') !== false) {
                            $icon = 'ti-login';
                            $color = 'success';
                        } elseif (strpos($activity['action'], 'logout') !== false) {
                            $icon = 'ti-logout';
                            $color = 'warning';
                        } elseif (strpos($activity['action'], 'view') !== false || strpos($activity['action'], 'show') !== false) {
                            $icon = 'ti-eye';
                            $color = 'secondary';
                        }
                        
                        // Format action text for user-friendly display
                        $actionText = str_replace('_', ' ', $activity['action']);
                        $actionText = ucwords($actionText);
                        
                        // Turkish translations for common actions
                        $actionTranslations = [
                            'create order' => t('activity_create_order', 'Sipariş Oluşturuldu'),
                            'add product' => t('activity_add_product', 'Ürün Eklendi'),
                            'update product' => t('activity_update_product', 'Ürün Güncellendi'),
                            'delete product' => t('activity_delete_product', 'Ürün Silindi'),
                            'add customer' => t('activity_add_customer', 'Müşteri Eklendi'),
                            'update customer' => t('activity_update_customer', 'Müşteri Güncellendi'),
                            'delete customer' => t('activity_delete_customer', 'Müşteri Silindi'),
                            'add stock' => t('activity_add_stock', 'Stok Eklendi'),
                            'update stock' => t('activity_update_stock', 'Stok Güncellendi'),
                            'add payment' => t('activity_add_payment', 'Ödeme Eklendi'),
                            'add debt' => t('activity_add_debt', 'Borç Eklendi'),
                            'login' => t('activity_login', 'Giriş Yapıldı'),
                            'logout' => t('activity_logout', 'Çıkış Yapıldı'),
                            'update profile' => t('activity_update_profile', 'Profil Güncellendi'),
                            'change password' => t('activity_change_password', 'Şifre Değiştirildi'),
                        ];
                        
                        $actionKey = strtolower($actionText);
                        if (isset($actionTranslations[$actionKey])) {
                            $actionText = $actionTranslations[$actionKey];
                        }
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-item-marker">
                            <div class="timeline-item-marker-icon bg-<?php echo $color; ?>">
                                <i class="ti <?php echo $icon; ?>"></i>
                            </div>
                        </div>
                        <div class="timeline-item-content">
                            <div class="timeline-item-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo e($activity['full_name'] ?: $activity['username'] ?: t('activity_system', 'Sistem')); ?></strong>
                                        <span class="text-muted ms-2"><?php echo $actionText; ?></span>
                                    </div>
                                    <span class="text-muted small"><?php echo $activityTime; ?></span>
                                </div>
                            </div>
                            <?php 
                            // Parse details to show changes
                            $detailsText = $activity['details'] ?? '';
                            $changes = null;
                            
                            // Try to parse JSON from details
                            if (!empty($detailsText)) {
                                // Check if details contains JSON
                                $jsonStart = strpos($detailsText, '{');
                                if ($jsonStart !== false) {
                                    $jsonPart = substr($detailsText, $jsonStart);
                                    $parsedJson = json_decode($jsonPart, true);
                                    if ($parsedJson && isset($parsedJson['changes'])) {
                                        $changes = $parsedJson['changes'];
                                        // Remove JSON part from details text
                                        $detailsText = trim(substr($detailsText, 0, $jsonStart));
                                    }
                                }
                            }
                            ?>
                            
                            <?php if (!empty($detailsText) || !empty($changes)): ?>
                            <div class="timeline-item-body mt-2">
                                <?php if (!empty($detailsText)): ?>
                                <p class="mb-2 text-muted small"><?php echo nl2br(e($detailsText)); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($changes) && is_array($changes)): ?>
                                <div class="changes-list">
                                    <?php foreach ($changes as $change): ?>
                                    <?php 
                                    $fieldName = $change['field'] ?? '';
                                    $oldValue = $change['old_value'] ?? '';
                                    $newValue = $change['new_value'] ?? '';
                                    
                                    // Format field names for display
                                    $fieldLabels = [
                                        'name' => t('activity_field_name', 'Ad'),
                                        'price' => t('activity_field_price', 'Fiyat'),
                                        'sku' => t('activity_field_sku', 'SKU'),
                                        'barcode' => t('activity_field_barcode', 'Barkod'),
                                        'description' => t('activity_field_description', 'Açıklama'),
                                        'min_stock_level' => t('activity_field_min_stock', 'Min. Stok'),
                                        'category_id' => t('activity_field_category', 'Kategori'),
                                        'category_name' => t('activity_field_category_name', 'Kategori Adı'),
                                        'first_name' => t('activity_field_first_name', 'Ad'),
                                        'last_name' => t('activity_field_last_name', 'Soyad'),
                                        'phone' => t('activity_field_phone', 'Telefon'),
                                        'email' => t('activity_field_email', 'E-posta'),
                                        'company' => t('activity_field_company', 'Şirket'),
                                        'address' => t('activity_field_address', 'Adres'),
                                        'notes' => t('activity_field_notes', 'Notlar'),
                                        'amount' => t('activity_field_amount', 'Tutar'),
                                        'payment_method' => t('activity_field_payment_method', 'Ödeme Yöntemi'),
                                        'reference_no' => t('activity_field_reference_no', 'Referans No'),
                                        'order_date' => t('activity_field_order_date', 'Sipariş Tarihi'),
                                        'status' => t('activity_field_status', 'Durum'),
                                        'total_amount' => t('activity_field_total_amount', 'Toplam Tutar'),
                                        'grand_total' => t('activity_field_grand_total', 'Genel Toplam'),
                                        'vat_rate' => t('activity_field_vat_rate', 'KDV Oranı'),
                                        'vat_amount' => t('activity_field_vat_amount', 'KDV Tutarı'),
                                        // Stock movement fields
                                        'product_id' => t('activity_field_product', 'Ürün'),
                                        'type' => t('activity_field_stock_type', 'Hareket Tipi'),
                                        'quantity' => t('activity_field_quantity', 'Miktar'),
                                        'unit' => t('activity_field_unit', 'Birim'),
                                        'date' => t('activity_field_date', 'Tarih'),
                                    ];
                                    
                                    $fieldLabel = $fieldLabels[$fieldName] ?? ucfirst(str_replace('_', ' ', $fieldName));
                                    
                                    // Format values
                                    if ($fieldName == 'price' || $fieldName == 'amount') {
                                        $oldValue = $oldValue !== '' && $oldValue !== null ? formatPrice($oldValue) . ' ₺' : '-';
                                        $newValue = $newValue !== '' && $newValue !== null ? formatPrice($newValue) . ' ₺' : '-';
                                    } elseif ($fieldName == 'type') {
                                        // Stock movement type labels
                                        $typeLabels = [
                                            'in' => t('stock_type_in', 'Giriş'),
                                            'out' => t('stock_type_out', 'Çıkış'),
                                            'adjustment' => t('stock_type_adjustment', 'Düzeltme')
                                        ];
                                        $oldValue = isset($typeLabels[$oldValue]) ? $typeLabels[$oldValue] : ($oldValue !== '' && $oldValue !== null ? e($oldValue) : '-');
                                        $newValue = isset($typeLabels[$newValue]) ? $typeLabels[$newValue] : ($newValue !== '' && $newValue !== null ? e($newValue) : '-');
                                    } elseif ($fieldName == 'product_id') {
                                        // Get product names
                                        try {
                                            if ($oldValue) {
                                                $db->query("SELECT name FROM products WHERE id = :id");
                                                $db->bind(':id', $oldValue);
                                                $oldProd = $db->single();
                                                $oldValue = $oldProd ? $oldProd['name'] : ($oldValue !== '' && $oldValue !== null ? $oldValue : '-');
                                            } else {
                                                $oldValue = '-';
                                            }
                                            if ($newValue) {
                                                $db->query("SELECT name FROM products WHERE id = :id");
                                                $db->bind(':id', $newValue);
                                                $newProd = $db->single();
                                                $newValue = $newProd ? $newProd['name'] : ($newValue !== '' && $newValue !== null ? $newValue : '-');
                                            } else {
                                                $newValue = '-';
                                            }
                                        } catch (Exception $e) {
                                            // Ignore, use IDs
                                            $oldValue = $oldValue !== '' && $oldValue !== null ? e($oldValue) : '-';
                                            $newValue = $newValue !== '' && $newValue !== null ? e($newValue) : '-';
                                        }
                                    } elseif ($fieldName == 'quantity') {
                                        // Format quantity with unit if available
                                        $oldValue = $oldValue !== '' && $oldValue !== null ? number_format($oldValue, 2) : '-';
                                        $newValue = $newValue !== '' && $newValue !== null ? number_format($newValue, 2) : '-';
                                    } elseif ($fieldName == 'date') {
                                        // Format dates
                                        if ($oldValue && $oldValue != '') {
                                            try {
                                                $oldValue = date('d.m.Y', strtotime($oldValue));
                                            } catch (Exception $e) {
                                                $oldValue = e($oldValue);
                                            }
                                        } else {
                                            $oldValue = '-';
                                        }
                                        if ($newValue && $newValue != '') {
                                            try {
                                                $newValue = date('d.m.Y', strtotime($newValue));
                                            } catch (Exception $e) {
                                                $newValue = e($newValue);
                                            }
                                        } else {
                                            $newValue = '-';
                                        }
                                    } elseif ($fieldName == 'category_id') {
                                        // Get category names
                                        try {
                                            if ($oldValue) {
                                                $db->query("SELECT name FROM categories WHERE id = :id");
                                                $db->bind(':id', $oldValue);
                                                $oldCat = $db->single();
                                                $oldValue = $oldCat ? $oldCat['name'] : ($oldValue !== '' && $oldValue !== null ? $oldValue : '-');
                                            } else {
                                                $oldValue = '-';
                                            }
                                            if ($newValue) {
                                                $db->query("SELECT name FROM categories WHERE id = :id");
                                                $db->bind(':id', $newValue);
                                                $newCat = $db->single();
                                                $newValue = $newCat ? $newCat['name'] : ($newValue !== '' && $newValue !== null ? $newValue : '-');
                                            } else {
                                                $newValue = '-';
                                            }
                                        } catch (Exception $e) {
                                            // Ignore, use IDs
                                            $oldValue = $oldValue !== '' && $oldValue !== null ? e($oldValue) : '-';
                                            $newValue = $newValue !== '' && $newValue !== null ? e($newValue) : '-';
                                        }
                                    } elseif ($fieldName == 'category_name') {
                                        // Category name is already formatted
                                        $oldValue = $oldValue !== '' && $oldValue !== null ? e($oldValue) : '-';
                                        $newValue = $newValue !== '' && $newValue !== null ? e($newValue) : '-';
                                    } else {
                                        $oldValue = $oldValue !== '' && $oldValue !== null ? e($oldValue) : '-';
                                        $newValue = $newValue !== '' && $newValue !== null ? e($newValue) : '-';
                                    }
                                    ?>
                                    <div class="change-item mb-2 p-2 bg-light rounded">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-grow-1">
                                                <strong class="text-primary"><?php echo $fieldLabel; ?>:</strong>
                                                <div class="mt-1">
                                                    <span class="badge bg-danger"><?php echo t('activity_old_value', 'Eski'); ?>: <?php echo $oldValue; ?></span>
                                                    <i class="ti ti-arrow-right mx-2 text-muted"></i>
                                                    <span class="badge bg-success"><?php echo t('activity_new_value', 'Yeni'); ?>: <?php echo $newValue; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($activity['ip_address'])): ?>
                            <div class="timeline-item-footer mt-2">
                                <small class="text-muted">
                                    <i class="ti ti-world"></i> <?php echo e($activity['ip_address']); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($currentDate != ''): ?>
                    </div> <!-- Close last day group -->
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.activity-timeline {
    position: relative;
    padding-left: 2rem;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}

.timeline-day-group {
    position: relative;
}

.timeline-day-header {
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 10;
    padding: 0.5rem 0;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 1rem;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
    padding-left: 2rem;
}

.timeline-item-marker {
    position: absolute;
    left: -1.25rem;
    top: 0.25rem;
    z-index: 1;
}

.timeline-item-marker-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.875rem;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #e5e7eb;
}

.timeline-item-content {
    background: #f8f9fa;
    border-radius: 0.5rem;
    padding: 1rem;
    border-left: 3px solid #e5e7eb;
}

.timeline-item-header {
    font-size: 0.95rem;
}

.timeline-item-body {
    font-size: 0.875rem;
}

.timeline-item-footer {
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .activity-timeline {
        padding-left: 1.5rem;
    }
    
    .timeline-item {
        padding-left: 1.5rem;
    }
    
    .timeline-item-marker {
        left: -1rem;
    }
    
    .timeline-item-marker-icon {
        width: 1.5rem;
        height: 1.5rem;
        font-size: 0.75rem;
    }
}
</style>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>

