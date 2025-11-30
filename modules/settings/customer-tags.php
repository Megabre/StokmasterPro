<?php
/**
 * Megabre StokMaster Pro
 * Customer Tags Management
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

// Get action
$action = isset($_GET['subaction']) ? $_GET['subaction'] : (isset($_GET['action']) && $_GET['action'] != 'customer-tags' ? $_GET['action'] : 'index');
$tagId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Process actions
switch ($action) {
    case 'add':
    case 'edit':
        // Add or Edit Tag
        $isEditing = ($action == 'edit' && $tagId > 0);
        $tag = null;
        
        if ($isEditing) {
            $db->query("SELECT * FROM customer_tags WHERE id = :id");
            $db->bind(':id', $tagId);
            $tag = $db->single();
            
            if (!$tag) {
                Session::setFlash('error', t('tags_not_found', 'Etiket bulunamadı.'));
                redirect('index.php?module=settings&action=customer-tags');
            }
        }
        
        $errors = [];
        
        if (isPost()) {
            if (!validateCsrf()) {
                redirect('index.php?module=settings&action=customer-tags');
            }
            
            $name = trim(post('name'));
            $color = post('color');
            $discountPercentage = floatval(post('discount_percentage'));
            $description = post('description');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Validation
            if (empty($name)) {
                $errors[] = t('tags_name_required', 'Etiket adı gereklidir.');
            }
            
            if (empty($color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $errors[] = t('tags_color_invalid', 'Geçerli bir renk kodu giriniz (örn: #dc3545).');
            }
            
            if ($discountPercentage < 0 || $discountPercentage > 100) {
                $errors[] = t('tags_discount_invalid', 'İndirim yüzdesi 0-100 arasında olmalıdır.');
            }
            
            // Check if name already exists (for new tag)
            if (!$isEditing) {
                $db->query("SELECT id FROM customer_tags WHERE name = :name");
                $db->bind(':name', $name);
                if ($db->single()) {
                    $errors[] = t('tags_name_exists', 'Bu etiket adı zaten kullanılıyor.');
                }
            } else {
                // Check if name exists for another tag
                $db->query("SELECT id FROM customer_tags WHERE name = :name AND id != :id");
                $db->bind(':name', $name);
                $db->bind(':id', $tagId);
                if ($db->single()) {
                    $errors[] = t('tags_name_exists', 'Bu etiket adı zaten kullanılıyor.');
                }
            }
            
            if (empty($errors)) {
                $db->beginTransaction();
                
                try {
                    if ($isEditing) {
                        // Get old tag data
                        $db->query("SELECT * FROM customer_tags WHERE id = :id");
                        $db->bind(':id', $tagId);
                        $oldTag = $db->single();
                        
                        // Prepare old data for logging
                        $oldData = [
                            'name' => $oldTag['name'],
                            'color' => $oldTag['color'] ?? '',
                            'discount_percentage' => $oldTag['discount_percentage'] ?? 0,
                            'description' => $oldTag['description'] ?? '',
                            'is_active' => $oldTag['is_active']
                        ];
                        
                        // Update tag
                        $db->query("UPDATE customer_tags SET 
                                   name = :name, color = :color, discount_percentage = :discount_percentage,
                                   description = :description, is_active = :is_active, updated_at = NOW()
                                   WHERE id = :id");
                        $db->bind(':name', $name);
                        $db->bind(':color', $color);
                        $db->bind(':discount_percentage', $discountPercentage);
                        $db->bind(':description', $description);
                        $db->bind(':is_active', $isActive);
                        $db->bind(':id', $tagId);
                        $db->execute();
                        
                        // Prepare new data for logging
                        $newData = [
                            'name' => $name,
                            'color' => $color,
                            'discount_percentage' => $discountPercentage,
                            'description' => $description,
                            'is_active' => $isActive
                        ];
                        
                        // Log activity
                        logActivity('update_customer_tag', 'customer_tag', $tagId, $oldData, $newData, "Müşteri etiketi güncellendi: {$name}");
                        
                        Session::setFlash('success', t('tags_update_success', 'Etiket başarıyla güncellendi.'));
                    } else {
                        // Insert tag
                        $db->query("INSERT INTO customer_tags (name, color, discount_percentage, description, is_active) 
                                   VALUES (:name, :color, :discount_percentage, :description, :is_active)");
                        $db->bind(':name', $name);
                        $db->bind(':color', $color);
                        $db->bind(':discount_percentage', $discountPercentage);
                        $db->bind(':description', $description);
                        $db->bind(':is_active', $isActive);
                        $db->execute();
                        
                        $newTagId = $db->lastInsertId();
                        
                        // Log activity
                        logActivity('add_customer_tag', 'customer_tag', $newTagId, null, [
                            'name' => $name,
                            'color' => $color,
                            'discount_percentage' => $discountPercentage,
                            'is_active' => $isActive
                        ], "Yeni müşteri etiketi eklendi: {$name}");
                        
                        Session::setFlash('success', t('tags_add_success', 'Etiket başarıyla eklendi.'));
                    }
                    
                    $db->endTransaction();
                    redirect('index.php?module=settings&action=customer-tags');
                    
                } catch (PDOException $e) {
                    $db->cancelTransaction();
                    $errors[] = t('tags_error', 'İşlem sırasında bir hata oluştu:') . ' ' . $e->getMessage();
                }
            }
        }
        
        include_once INCLUDES_PATH . 'header.php';
        ?>
        
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title"><?php echo $isEditing ? t('tags_edit', 'Etiket Düzenle') : t('tags_add', 'Etiket Ekle'); ?></h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings&action=customer-tags'); ?>"><?php echo t('tags_title', 'Müşteri Etiketleri'); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $isEditing ? t('tags_edit', 'Düzenle') : t('tags_add', 'Ekle'); ?></li>
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
                        <h5 class="card-title"><?php echo $isEditing ? t('tags_edit_info', 'Etiket Bilgilerini Düzenle') : t('tags_add_info', 'Yeni Etiket Ekle'); ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php echo csrfField(); ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label required"><?php echo t('tags_name', 'Etiket Adı'); ?></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo e($tag['name'] ?? ''); ?>" required>
                                    <small class="text-muted"><?php echo t('tags_name_example', 'Örnek: Kırmızı, VIP, Özel Müşteri'); ?></small>
                                </div>
                                <div class="col-md-6">
                                    <label for="color" class="form-label required"><?php echo t('tags_color', 'Renk'); ?></label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" id="color" name="color" 
                                               value="<?php echo e($tag['color'] ?? '#dc3545'); ?>" required>
                                        <input type="text" class="form-control" id="color_text" 
                                               value="<?php echo e($tag['color'] ?? '#dc3545'); ?>" 
                                               pattern="^#[0-9A-Fa-f]{6}$" placeholder="#dc3545">
                                    </div>
                                    <small class="text-muted"><?php echo t('tags_color_desc', 'Etiketin görüntüleneceği renk'); ?></small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="discount_percentage" class="form-label"><?php echo t('tags_discount', 'İndirim Yüzdesi'); ?></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" 
                                               value="<?php echo e($tag['discount_percentage'] ?? 0); ?>" 
                                               min="0" max="100" step="0.01">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted"><?php echo t('tags_discount_desc', 'Bu etikete sahip müşterilere otomatik uygulanacak indirim yüzdesi'); ?></small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?php echo t('tags_status', 'Durum'); ?></label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               <?php echo ($tag['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active"><?php echo t('tags_active', 'Aktif'); ?></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label"><?php echo t('tags_description', 'Açıklama'); ?></label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo e($tag['description'] ?? ''); ?></textarea>
                                <small class="text-muted"><?php echo t('tags_description_desc', 'Etiket hakkında açıklama'); ?></small>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <a href="<?php echo url('index.php?module=settings&action=customer-tags'); ?>" class="btn btn-secondary">
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
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const colorInput = document.getElementById('color');
            const colorText = document.getElementById('color_text');
            
            colorInput.addEventListener('input', function() {
                colorText.value = this.value;
            });
            
            colorText.addEventListener('input', function() {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    colorInput.value = this.value;
                }
            });
        });
        </script>
        
        <?php
        include_once INCLUDES_PATH . 'footer.php';
        break;
        
    case 'delete':
        // Delete tag
        if ($tagId > 0) {
            $db->beginTransaction();
            
            try {
                // Check if tag is used
                $db->query("SELECT COUNT(*) as count FROM customer_tag_relations WHERE tag_id = :id");
                $db->bind(':id', $tagId);
                $usage = $db->single();
                
                if ($usage['count'] > 0) {
                    Session::setFlash('error', t('tags_cannot_delete_used', 'Bu etiket kullanıldığı için silinemez. Önce müşterilerden kaldırın.'));
                    redirect('index.php?module=settings&action=customer-tags');
                }
                
                // Get tag data before deletion
                $db->query("SELECT * FROM customer_tags WHERE id = :id");
                $db->bind(':id', $tagId);
                $tag = $db->single();
                
                // Log activity before deletion
                logActivity('delete_customer_tag', 'customer_tag', $tagId, [
                    'name' => $tag['name'],
                    'color' => $tag['color'] ?? '',
                    'discount_percentage' => $tag['discount_percentage'] ?? 0
                ], null, "Müşteri etiketi silindi: {$tag['name']}");
                
                // Delete tag
                $db->query("DELETE FROM customer_tags WHERE id = :id");
                $db->bind(':id', $tagId);
                $db->execute();
                
                $db->endTransaction();
                Session::setFlash('success', t('tags_delete_success', 'Etiket başarıyla silindi.'));
            } catch (PDOException $e) {
                $db->cancelTransaction();
                Session::setFlash('error', t('tags_delete_error', 'Etiket silinirken bir hata oluştu.'));
            }
        }
        
        redirect('index.php?module=settings&action=customer-tags');
        break;
        
    default:
        // List tags
        $db->query("SELECT ct.*, 
                   (SELECT COUNT(*) FROM customer_tag_relations WHERE tag_id = ct.id) as usage_count
                   FROM customer_tags ct 
                   ORDER BY ct.name ASC");
        $tags = $db->resultSet();
        
        include_once INCLUDES_PATH . 'header.php';
        ?>
        
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title"><?php echo t('tags_title', 'Müşteri Etiketleri'); ?></h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=settings'); ?>"><?php echo t('settings_title', 'Ayarlar'); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo t('tags_title', 'Müşteri Etiketleri'); ?></li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="<?php echo url('index.php?module=settings&action=customer-tags&subaction=add'); ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo t('tags_add', 'Etiket Ekle'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><?php echo t('tags_list', 'Etiket Listesi'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <strong><?php echo t('tags_info_title', 'Etiket Sistemi Nasıl Çalışır?'); ?></strong>
                            <ul class="mb-0 mt-2">
                                <li><?php echo t('tags_info_1', 'Müşterilere etiket atayarak otomatik indirim uygulayabilirsiniz'); ?></li>
                                <li><?php echo t('tags_info_2', 'Bir müşteriye birden fazla etiket atanabilir'); ?></li>
                                <li><?php echo t('tags_info_3', 'Sipariş oluşturulurken en yüksek indirim yüzdesine sahip etiket otomatik uygulanır'); ?></li>
                                <li><?php echo t('tags_info_4', 'Etiketler müşteri düzenleme sayfasından atanır'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><?php echo t('tags_name', 'Etiket Adı'); ?></th>
                                        <th><?php echo t('tags_color', 'Renk'); ?></th>
                                        <th><?php echo t('tags_discount', 'İndirim'); ?></th>
                                        <th><?php echo t('tags_description', 'Açıklama'); ?></th>
                                        <th><?php echo t('tags_usage', 'Kullanım'); ?></th>
                                        <th><?php echo t('tags_status', 'Durum'); ?></th>
                                        <th><?php echo t('actions', 'İşlemler'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tags)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center"><?php echo t('tags_no_tags', 'Henüz etiket eklenmemiş.'); ?></td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($tags as $tag): ?>
                                    <tr>
                                        <td>
                                            <span class="badge" style="background-color: <?php echo e($tag['color']); ?>; color: white; padding: 8px 12px;">
                                                <?php echo e($tag['name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="width: 30px; height: 30px; background-color: <?php echo e($tag['color']); ?>; border: 1px solid #ddd; border-radius: 4px;"></div>
                                        </td>
                                        <td>
                                            <?php if ($tag['discount_percentage'] > 0): ?>
                                            <strong class="text-success">%<?php echo number_format($tag['discount_percentage'], 2); ?></strong>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($tag['description'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $tag['usage_count']; ?> <?php echo t('tags_customers', 'müşteri'); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($tag['is_active']): ?>
                                            <span class="badge bg-success"><?php echo t('tags_active', 'Aktif'); ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo t('tags_inactive', 'Pasif'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo url('index.php?module=settings&action=customer-tags&subaction=edit&id=' . $tag['id']); ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($tag['usage_count'] == 0): ?>
                                            <a href="<?php echo url('index.php?module=settings&action=customer-tags&subaction=delete&id=' . $tag['id']); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('<?php echo t('tags_delete_confirm', 'Bu etiketi silmek istediğinizden emin misiniz?'); ?>');">
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
