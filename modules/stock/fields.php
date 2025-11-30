<?php
/**
 * Megabre StokMaster Pro
 * Stock Dynamic Fields Management
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Check user role
$currentUser = $auth->getCurrentUser();
    if (!in_array($currentUser['role'], ['admin', 'user'])) {
        Session::setFlash('error', t('no_permission', 'Bu sayfaya erişim yetkiniz yok.'));
        redirect('index.php?module=stock');
    }

// Initialize database connection
$db = Database::getInstance();

// Get all stock fields (system-wide fields only, stock_id IS NULL)
$db->query("SELECT * FROM stock_fields WHERE stock_id IS NULL ORDER BY field_order ASC, created_at ASC");
$fields = $db->resultSet();

// Count total fields
$totalFields = count($fields);

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
            <h3 class="page-title"><?php echo t('stock_fields_title', 'Stok Dinamik Alanları'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=stock'); ?>"><?php echo t('stock_title', 'Stok Yönetimi'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('stock_fields_breadcrumb', 'Dinamik Alanlar'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFieldModal">
                <i class="fas fa-plus"></i> <?php echo t('stock_fields_add_field', 'Alan Ekle'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Fields Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?php echo t('stock_fields_list', 'Alan Listesi'); ?></h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="fieldsTable">
                <thead>
                    <tr>
                        <th width="50"><?php echo t('stock_fields_order', 'Sıra'); ?></th>
                        <th><?php echo t('stock_fields_field_name', 'Alan Adı'); ?></th>
                        <th><?php echo t('stock_fields_field_type', 'Alan Türü'); ?></th>
                        <th width="100"><?php echo t('stock_fields_required', 'Zorunlu'); ?></th>
                        <th width="100"><?php echo t('stock_fields_status', 'Durum'); ?></th>
                        <th width="150"><?php echo t('categories_actions', 'İşlemler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fields)): ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <?php echo t('stock_fields_no_fields', 'Henüz dinamik alan tanımlanmamış.'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fields as $field): ?>
                            <tr data-id="<?php echo $field['id']; ?>">
                                <td class="text-center">
                                    <span class="badge bg-secondary">
                                        <?php echo $field['field_order'] ?? '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo e($field['field_name']); ?></strong>
                                    <?php if ($field['field_key']): ?>
                                        <br><small class="text-muted">Key: <?php echo $field['field_key']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $type_labels = [
                                        'text' => '<span class="badge bg-info">' . t('stock_fields_type_text', 'Metin') . '</span>',
                                        'number' => '<span class="badge bg-warning">' . t('stock_fields_type_number', 'Sayı') . '</span>',
                                        'select' => '<span class="badge bg-success">' . t('stock_fields_type_select', 'Seçim') . '</span>',
                                        'textarea' => '<span class="badge bg-primary">' . t('stock_fields_type_textarea', 'Metin Alanı') . '</span>',
                                        'date' => '<span class="badge bg-secondary">' . t('stock_fields_type_date', 'Tarih') . '</span>'
                                    ];
                                    echo $type_labels[$field['field_type']] ?? $field['field_type'];
                                    ?>
                                    <?php if ($field['field_type'] == 'select' && $field['field_options']): ?>
                                        <br><small class="text-muted">
                                            <?php echo t('stock_fields_options', 'Seçenekler:'); ?> <?php echo e($field['field_options']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($field['is_required']): ?>
                                        <span class="badge bg-danger"><?php echo t('stock_fields_yes', 'Evet'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo t('stock_fields_no', 'Hayır'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($field['is_active']): ?>
                                        <span class="badge bg-success"><?php echo t('stock_fields_active', 'Aktif'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?php echo t('stock_fields_inactive', 'Pasif'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info edit-field" 
                                            data-id="<?php echo $field['id']; ?>"
                                            data-name="<?php echo e($field['field_name']); ?>"
                                            data-type="<?php echo $field['field_type']; ?>"
                                            data-options="<?php echo e($field['field_options']); ?>"
                                            data-required="<?php echo $field['is_required']; ?>"
                                            data-active="<?php echo $field['is_active']; ?>"
                                            data-order="<?php echo $field['field_order']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-field" 
                                            data-id="<?php echo $field['id']; ?>" 
                                            data-name="<?php echo e($field['field_name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php if ($totalFields > 1): ?>
                                    <button class="btn btn-sm btn-secondary move-field" 
                                            data-id="<?php echo $field['id']; ?>" 
                                            data-direction="up"
                                            <?php echo $field['field_order'] == 1 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-arrow-up"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary move-field" 
                                            data-id="<?php echo $field['id']; ?>" 
                                            data-direction="down"
                                            <?php echo $field['field_order'] == $totalFields ? 'disabled' : ''; ?>>
                                        <i class="fas fa-arrow-down"></i>
                                    </button>
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

<!-- Tips Section -->
<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0"><i class="fas fa-info-circle"></i> <?php echo t('stock_fields_help_tips', 'Yardım & İpuçları'); ?></h5>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li><?php echo t('stock_fields_help_tip1', 'Dinamik alanlar stok hareketi eklerken ve düzenlerken görünecektir'); ?></li>
            <li><?php echo t('stock_fields_help_tip2', 'Seçim tipi için seçenekleri virgülle ayırarak yazın (Örn: Tedarikçi 1,Tedarikçi 2,Tedarikçi 3)'); ?></li>
            <li><?php echo t('stock_fields_help_tip3', 'Alan sıralamasını yukarı/aşağı okları ile değiştirebilirsiniz'); ?></li>
            <li><?php echo t('stock_fields_help_tip4', 'Maksimum 20 dinamik alan ekleyebilirsiniz'); ?></li>
            <li><?php echo t('stock_fields_help_tip5', 'Pasif alanlar formlarda görünmez ancak mevcut veriler korunur'); ?></li>
            <li><?php echo t('stock_fields_help_tip6', 'Örnek alanlar: Tedarikçi, Fatura No, Lot No, Son Kullanma Tarihi vb.'); ?></li>
        </ul>
    </div>
</div>

<!-- Add Field Modal -->
<div class="modal fade" id="addFieldModal" tabindex="-1" aria-labelledby="addFieldModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addFieldForm" action="<?php echo url('api/stock.php?action=add_field'); ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFieldModalLabel"><?php echo t('stock_fields_modal_add', 'Alan Ekle'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo t('ui_aria_close', 'Kapat'); ?>"></button>
                </div>
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    
                    <div class="mb-3">
                        <label for="field_name" class="form-label required"><?php echo t('stock_fields_field_name', 'Alan Adı'); ?></label>
                        <input type="text" class="form-control" id="field_name" name="field_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="field_type" class="form-label required"><?php echo t('stock_fields_field_type', 'Alan Türü'); ?></label>
                        <select class="form-select" id="field_type" name="field_type" required>
                            <option value=""><?php echo t('orders_select', 'Seçiniz'); ?></option>
                            <option value="text"><?php echo t('stock_fields_type_text', 'Metin'); ?></option>
                            <option value="number"><?php echo t('stock_fields_type_number', 'Sayı'); ?></option>
                            <option value="select"><?php echo t('stock_fields_type_select', 'Seçim'); ?></option>
                            <option value="textarea"><?php echo t('stock_fields_type_textarea', 'Metin Alanı'); ?></option>
                            <option value="date"><?php echo t('stock_fields_type_date', 'Tarih'); ?></option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="optionsGroup" style="display: none;">
                        <label for="field_options" class="form-label"><?php echo t('stock_fields_options_label', 'Seçenekler'); ?></label>
                        <input type="text" class="form-control" id="field_options" name="field_options" 
                               placeholder="<?php echo t('stock_fields_options_placeholder', 'Seçenek1,Seçenek2,Seçenek3'); ?>">
                        <small class="text-muted"><?php echo t('stock_fields_options_hint', 'Seçenekleri virgülle ayırarak yazın'); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_required" name="is_required" value="1">
                            <label class="form-check-label" for="is_required">
                                <?php echo t('stock_fields_required_field', 'Zorunlu alan'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">
                                <?php echo t('stock_fields_active', 'Aktif'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel', 'İptal'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo t('orders_save', 'Kaydet'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Field Modal -->
<div class="modal fade" id="editFieldModal" tabindex="-1" aria-labelledby="editFieldModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editFieldForm" action="<?php echo url('api/stock.php?action=update_field'); ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFieldModalLabel"><?php echo t('stock_fields_modal_edit', 'Alan Düzenle'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo t('ui_aria_close', 'Kapat'); ?>"></button>
                </div>
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" id="edit_field_id" name="field_id">
                    
                    <div class="mb-3">
                        <label for="edit_field_name" class="form-label required"><?php echo t('stock_fields_field_name', 'Alan Adı'); ?></label>
                        <input type="text" class="form-control" id="edit_field_name" name="field_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_field_type" class="form-label required"><?php echo t('stock_fields_field_type', 'Alan Türü'); ?></label>
                        <select class="form-select" id="edit_field_type" name="field_type" required>
                            <option value=""><?php echo t('orders_select', 'Seçiniz'); ?></option>
                            <option value="text"><?php echo t('stock_fields_type_text', 'Metin'); ?></option>
                            <option value="number"><?php echo t('stock_fields_type_number', 'Sayı'); ?></option>
                            <option value="select"><?php echo t('stock_fields_type_select', 'Seçim'); ?></option>
                            <option value="textarea"><?php echo t('stock_fields_type_textarea', 'Metin Alanı'); ?></option>
                            <option value="date"><?php echo t('stock_fields_type_date', 'Tarih'); ?></option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="editOptionsGroup" style="display: none;">
                        <label for="edit_field_options" class="form-label"><?php echo t('stock_fields_options_label', 'Seçenekler'); ?></label>
                        <input type="text" class="form-control" id="edit_field_options" name="field_options" 
                               placeholder="<?php echo t('stock_fields_options_placeholder', 'Seçenek1,Seçenek2,Seçenek3'); ?>">
                        <small class="text-muted"><?php echo t('stock_fields_options_hint', 'Seçenekleri virgülle ayırarak yazın'); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_required" name="is_required" value="1">
                            <label class="form-check-label" for="edit_is_required">
                                <?php echo t('stock_fields_required_field', 'Zorunlu alan'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1">
                            <label class="form-check-label" for="edit_is_active">
                                <?php echo t('stock_fields_active', 'Aktif'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel', 'İptal'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo t('stock_fields_update', 'Güncelle'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Field type change handler
    $('#field_type, #edit_field_type').on('change', function() {
        const container = $(this).attr('id') === 'field_type' ? '#optionsGroup' : '#editOptionsGroup';
        if ($(this).val() === 'select') {
            $(container).show();
        } else {
            $(container).hide();
        }
    });
    
    // Edit field button handler
    $('.edit-field').on('click', function() {
        const $btn = $(this);
        
        $('#edit_field_id').val($btn.data('id'));
        $('#edit_field_name').val($btn.data('name'));
        $('#edit_field_type').val($btn.data('type')).trigger('change');
        $('#edit_field_options').val($btn.data('options'));
        $('#edit_is_required').prop('checked', $btn.data('required') == 1);
        $('#edit_is_active').prop('checked', $btn.data('active') == 1);
        
        $('#editFieldModal').modal('show');
    });
    
    // Delete field button handler
    $(document).on('click', '.delete-field', function() {
        const fieldId = $(this).data('id');
        const fieldName = $(this).data('name');
        const button = $(this);
        
        if (confirm(`"${fieldName}"<?php echo t('stock_fields_delete_confirm', ' alanını silmek istediğinize emin misiniz?'); ?>`)) {
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: '<?php echo url('api/stock.php?action=delete_field'); ?>',
                type: 'POST',
                data: {
                    field_id: fieldId,
                    csrf_token: '<?php echo Session::getCsrfToken(); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        const alertHtml = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                            response.message + 
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                            '</div>';
                        $('.page-header').after(alertHtml);
                        
                        // Remove the row from table
                        button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if table is empty
                            if ($('#fieldsTable tbody tr').length === 0) {
                                $('#fieldsTable tbody').html('<tr><td colspan="7" class="text-center">Henüz dinamik alan tanımlanmamış.</td></tr>');
                            }
                        });
                        
                        // Reload page after 1 second to refresh the list
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        const alertHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                            (response.message || 'Alan silinirken bir hata oluştu') + 
                            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                            '</div>';
                        $('.page-header').after(alertHtml);
                        button.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'Alan silinirken bir hata oluştu';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    const alertHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                        errorMessage + 
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                        '</div>';
                    $('.page-header').after(alertHtml);
                    button.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                }
            });
        }
    });
    
    // Move field handler
    $(document).on('click', '.move-field', function() {
        const fieldId = $(this).data('id');
        const direction = $(this).data('direction');
        const button = $(this);
        
        // Get CSRF token from form
        const csrfToken = $('input[name="csrf_token"]').val() || $('#addFieldForm input[name="csrf_token"]').val();
        
        button.prop('disabled', true);
        
        $.ajax({
            url: '<?php echo url('api/stock.php?action=reorder_field'); ?>',
            type: 'POST',
            data: {
                field_id: fieldId,
                direction: direction,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Reload page to show updated order
                    window.location.reload();
                } else {
                    const alertHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                        (response.message || 'Alan sırası değiştirilirken bir hata oluştu') + 
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                        '</div>';
                    $('.page-header').after(alertHtml);
                    button.prop('disabled', false);
                }
            },
            error: function(xhr) {
                let errorMessage = 'Alan sırası değiştirilirken bir hata oluştu';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                const alertHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    errorMessage + 
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                    '</div>';
                $('.page-header').after(alertHtml);
                button.prop('disabled', false);
            }
        });
    });
    
    // Clear form when modal is closed
    $('#addFieldModal').on('hidden.bs.modal', function() {
        $('#addFieldForm')[0].reset();
        $('#optionsGroup').hide();
    });
    
    $('#editFieldModal').on('hidden.bs.modal', function() {
        $('#editFieldForm')[0].reset();
        $('#editOptionsGroup').hide();
    });
    
    // Add field form submit (AJAX)
    $('#addFieldForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const fieldName = form.find('[name="field_name"]').val();
        const fieldType = form.find('[name="field_type"]').val();
        
        // Validation
        if (!fieldName || !fieldType) {
            alert('<?php echo t('common_required_fields', 'Lütfen tüm zorunlu alanları doldurun.'); ?>');
            return false;
        }
        
        if (fieldType === 'select') {
            const options = form.find('[name="field_options"]').val();
            if (!options) {
                alert('<?php echo t('common_select_required', 'Seçim tipi için en az bir seçenek girmelisiniz.'); ?>');
                return false;
            }
        }
        
        // Submit via AJAX
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close modal
                    $('#addFieldModal').modal('hide');
                    // Reload page to show new field
                    location.reload();
                } else {
                    alert(response.message || '<?php echo t('common_error', 'Alan eklenirken bir hata oluştu'); ?>');
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = '<?php echo t('common_error', 'Alan eklenirken bir hata oluştu'); ?>';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {
                    // Use default error message
                }
                alert(errorMsg);
            }
        });
        
        return false;
    });
    
    // Edit field form submit (AJAX)
    $('#editFieldForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const fieldName = form.find('[name="field_name"]').val();
        const fieldType = form.find('[name="field_type"]').val();
        
        // Validation
        if (!fieldName || !fieldType) {
            alert('<?php echo t('common_required_fields', 'Lütfen tüm zorunlu alanları doldurun.'); ?>');
            return false;
        }
        
        if (fieldType === 'select') {
            const options = form.find('[name="field_options"]').val();
            if (!options) {
                alert('<?php echo t('common_select_required', 'Seçim tipi için en az bir seçenek girmelisiniz.'); ?>');
                return false;
            }
        }
        
        // Submit via AJAX
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close modal
                    $('#editFieldModal').modal('hide');
                    // Reload page to show updated field
                    location.reload();
                } else {
                    alert(response.message || '<?php echo t('common_error', 'Alan güncellenirken bir hata oluştu'); ?>');
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = '<?php echo t('common_error', 'Alan güncellenirken bir hata oluştu'); ?>';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {
                    // Use default error message
                }
                alert(errorMsg);
            }
        });
        
        return false;
    });
});
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>