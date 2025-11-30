<?php
/**
 * Megabre StokMaster Pro
 * Categories Index
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Initialize Cache and Database
$cache = Cache::getInstance();
$db = Database::getInstance();

// Get page parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'index';

// Process actions
switch ($action) {
    case 'add':
        include_once MODULES_PATH . 'categories/add.php';
        break;
        
    case 'edit':
        include_once MODULES_PATH . 'categories/edit.php';
        break;
        
    case 'delete':
        include_once MODULES_PATH . 'categories/delete.php';
        break;
        
    case 'fields':
        include_once MODULES_PATH . 'categories/fields.php';
        break;
        
    default:
        // Default action: list all categories
        
        // Get categories with product counts (cached)
        $categories = $cache->remember('categories_list_with_counts', function() use ($db) {
            $db->query("SELECT c.*, COUNT(p.id) as product_count 
                        FROM categories c 
                        LEFT JOIN products p ON c.id = p.category_id 
                        GROUP BY c.id 
                        ORDER BY c.name ASC");
            return $db->resultSet();
        }, 300); // Cache for 5 minutes
        
        // Get dynamic fields count for each category (cached)
        $categoryFields = $cache->remember('categories_fields_count', function() use ($db, $categories) {
            $fields = [];
            foreach ($categories as $category) {
                $db->query("SELECT COUNT(*) as field_count FROM category_fields WHERE category_id = :category_id");
                $db->bind(':category_id', $category['id']);
                $result = $db->single();
                $fields[$category['id']] = $result ? $result['field_count'] : 0;
            }
            return $fields;
        }, 300); // Cache for 5 minutes
        
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
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle"><?php echo t('home', 'Ana Sayfa'); ?></div>
                <h2 class="page-title"><?php echo t('categories_title', 'Kategoriler'); ?></h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <button type="button" class="btn btn-danger" id="bulkDeleteBtn" style="display: none;">
                        <i class="ti ti-trash"></i> <span id="selectedCount">0</span> Seçili Kategoriyi Sil
                    </button>
                    <button type="button" class="btn btn-primary" id="columnFilterBtn" data-bs-toggle="modal" data-bs-target="#columnFilterModal">
                <i class="ti ti-filter"></i> <?php echo t('categories_column_filter', 'Sütun Filtresi'); ?>
            </button>
            <a href="<?php echo url('index.php?module=categories&action=add'); ?>" class="btn btn-primary">
                <i class="ti ti-plus"></i> <?php echo t('categories_new_category', 'Yeni Kategori'); ?>
            </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="page-body">
    <div class="container-xl">
        <!-- Categories Table -->
        <div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable" id="categories-table" data-page-length="50">
                <thead>
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAll" title="Tümünü Seç/Kaldır">
                        </th>
                        <th width="60"><?php echo t('categories_id', 'ID'); ?></th>
                        <th><?php echo t('categories_category_name', 'Kategori Adı'); ?></th>
                        <th><?php echo t('categories_description', 'Açıklama'); ?></th>
                        <th><?php echo t('categories_product_count', 'Ürün Sayısı'); ?></th>
                        <th><?php echo t('categories_dynamic_field_count', 'Dinamik Alan Sayısı'); ?></th>
                        <th><?php echo t('categories_created_at', 'Oluşturma Tarihi'); ?></th>
                        <th width="150"><?php echo t('categories_actions', 'İşlemler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="category-checkbox" value="<?php echo $category['id']; ?>" data-category-name="<?php echo e($category['name']); ?>" data-product-count="<?php echo $category['product_count']; ?>">
                        </td>
                        <td><?php echo $category['id']; ?></td>
                        <td>
                            <a href="<?php echo url('index.php?module=categories&action=edit&id=' . $category['id']); ?>">
                                <?php echo e($category['name']); ?>
                            </a>
                        </td>
                        <td><?php echo limitString(e($category['description']), 50); ?></td>
                        <td><?php echo $category['product_count']; ?></td>
                        <td><?php echo $categoryFields[$category['id']]; ?></td>
                        <td><?php echo formatDateTime($category['created_at']); ?></td>
                        <td>
                            <div class="btn-list">
                                <a href="<?php echo url('index.php?module=categories&action=edit&id=' . $category['id']); ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="<?php echo t('categories_show_edit', 'Göster/Düzenle'); ?>">
                                    <i class="ti ti-edit"></i>
                                </a>
                                <a href="<?php echo url('index.php?module=categories&action=fields&id=' . $category['id']); ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="<?php echo t('categories_dynamic_fields', 'Dinamik Alanlar'); ?>">
                                    <i class="ti ti-adjustments"></i>
                                </a>
                                <a href="<?php echo url('api/categories.php?action=delete&id=' . $category['id']); ?>" class="btn btn-sm btn-danger delete-confirm" data-bs-toggle="tooltip" title="Sil" data-item-name="'<?php echo e($category['name']); ?>' kategorisini">
                                    <i class="ti ti-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Statistics -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('categories_statistics', 'Kategori İstatistikleri'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('categories_total_categories', 'Toplam Kategori'); ?></h6>
                            <h4><?php echo count($categories); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('categories_total_dynamic_fields', 'Toplam Dinamik Alan'); ?></h6>
                            <h4><?php echo array_sum($categoryFields); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('categories_help_tips', 'Yardım & İpuçları'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <strong><?php echo t('categories_tip', 'İpucu:'); ?></strong> <?php echo t('categories_tip_text', 'Ürünlerinizi daha iyi organize etmek için kategoriler oluşturun. Her kategori için özel dinamik alanlar tanımlayabilirsiniz.'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Column Filter Modal -->
<div class="modal fade" id="columnFilterModal" tabindex="-1" aria-labelledby="columnFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="columnFilterModalLabel"><?php echo t('categories_column_filter_title', 'Sütun Filtresi'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo t('ui_aria_close', 'Close'); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo t('categories_column_filter_desc', 'Tabloda görmek istediğiniz sütunları seçin:'); ?></p>
                <form id="columnFilterForm">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_id" checked disabled>
                        <label class="form-check-label" for="column_id">ID</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_name" checked>
                        <label class="form-check-label" for="column_name"><?php echo t('categories_category_name', 'Kategori Adı'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_description" checked>
                        <label class="form-check-label" for="column_description"><?php echo t('categories_description', 'Açıklama'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_product_count" checked>
                        <label class="form-check-label" for="column_product_count"><?php echo t('categories_product_count', 'Ürün Sayısı'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_fields_count" checked>
                        <label class="form-check-label" for="column_fields_count"><?php echo t('categories_dynamic_field_count', 'Dinamik Alan Sayısı'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_created_at" checked>
                        <label class="form-check-label" for="column_created_at"><?php echo t('categories_created_at', 'Oluşturma Tarihi'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_actions" checked disabled>
                        <label class="form-check-label" for="column_actions"><?php echo t('categories_actions', 'İşlemler'); ?></label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel', 'İptal'); ?></button>
                <button type="button" class="btn btn-primary" id="applyColumnFilter">
                    <i class="ti ti-check"></i> <?php echo t('categories_apply', 'Uygula'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Bulk delete functionality - Define function first
        function updateBulkDeleteButton() {
            const selected = $('.category-checkbox:checked').length;
            if (selected > 0) {
                $('#bulkDeleteBtn').show();
                $('#selectedCount').text(selected);
            } else {
                $('#bulkDeleteBtn').hide();
            }
        }
        
        // Check if table exists before initializing DataTable
        if ($('#categories-table').length === 0) {
            console.error('Categories table not found');
            updateBulkDeleteButton();
            return;
        }
        
        // Initialize DataTable
        try {
            // Destroy existing DataTable if it exists
            if ($.fn.DataTable.isDataTable('#categories-table')) {
                $('#categories-table').DataTable().destroy();
            }
            
            // Initialize DataTable
            const categoriesTable = $('#categories-table').DataTable({
                language: {
                    emptyTable: 'Tabloda veri bulunmuyor',
                    info: 'Toplam _TOTAL_ kayıttan _START_ - _END_ arası gösteriliyor',
                    infoEmpty: 'Toplam 0 kayıttan 0 - 0 arası gösteriliyor',
                    infoFiltered: '(_MAX_ kayıt içerisinden bulunan)',
                    lengthMenu: '_MENU_ kayıt göster',
                    loadingRecords: 'Yükleniyor...',
                    processing: 'İşleniyor...',
                    search: 'Ara:',
                    zeroRecords: 'Eşleşen kayıt bulunamadı',
                    paginate: {
                        first: 'İlk',
                        last: 'Son',
                        next: 'Sonraki',
                        previous: 'Önceki'
                    }
                },
                responsive: true,
                stateSave: true,
                stateSaveCallback: function(settings, data) {
                    try {
                        localStorage.setItem('DataTables_categories-table', JSON.stringify(data));
                    } catch (e) {
                        console.error('Error saving DataTable state:', e);
                    }
                },
                stateLoadCallback: function(settings) {
                    try {
                        const saved = localStorage.getItem('DataTables_categories-table');
                        return saved ? JSON.parse(saved) : null;
                    } catch (e) {
                        console.error('Error loading DataTable state:', e);
                        return null;
                    }
                },
                drawCallback: function() {
                    // Update bulk delete button visibility after table draw
                    updateBulkDeleteButton();
                }
            });
            
            // Load column visibility state from localStorage
            try {
                const columnVisibility = JSON.parse(localStorage.getItem('categoriesColumnVisibility')) || {};
                
                // Apply saved column visibility
                if (Object.keys(columnVisibility).length > 0) {
                    Object.keys(columnVisibility).forEach(key => {
                        try {
                            categoriesTable.column(key).visible(columnVisibility[key]);
                            $(`#column_${key}`).prop('checked', columnVisibility[key]);
                        } catch (e) {
                            console.error('Error applying column visibility:', e);
                        }
                    });
                }
            } catch (e) {
                console.error('Error loading column visibility:', e);
            }
            
            // Check bulk delete button visibility on page load
            updateBulkDeleteButton();
        
            
            // Apply column filter
            $('#applyColumnFilter').on('click', function() {
                const newColumnVisibility = {};
                
                // Update column visibility
                newColumnVisibility[1] = $('#column_name').is(':checked');
                newColumnVisibility[2] = $('#column_description').is(':checked');
                newColumnVisibility[3] = $('#column_product_count').is(':checked');
                newColumnVisibility[4] = $('#column_fields_count').is(':checked');
                newColumnVisibility[5] = $('#column_created_at').is(':checked');
                
                // Apply changes
                Object.keys(newColumnVisibility).forEach(key => {
                    try {
                        categoriesTable.column(key).visible(newColumnVisibility[key]);
                    } catch (e) {
                        console.error('Error applying column visibility:', e);
                    }
                });
                
                // Save to localStorage
                try {
                    localStorage.setItem('categoriesColumnVisibility', JSON.stringify(newColumnVisibility));
                } catch (e) {
                    console.error('Error saving column visibility:', e);
                }
                
                // Close modal
                $('#columnFilterModal').modal('hide');
            });
        } catch (e) {
            console.error('Error initializing DataTable:', e);
            // Fallback: Check bulk delete button visibility even if DataTable fails
            updateBulkDeleteButton();
        }
        
        // Delete confirmation
        $('.delete-confirm').on('click', function(e) {
            e.preventDefault();
            const itemName = $(this).data('item-name');
            const deleteUrl = $(this).attr('href');
            const $button = $(this);
            
            const confirmMsg = '<?php echo t('categories_delete_confirm', 'Bu işlem geri alınamaz. {item_name} silmek istediğinize emin misiniz?'); ?>'.replace('{item_name}', itemName);
            if (confirm(confirmMsg)) {
                // Show loading
                const originalHtml = $button.html();
                $button.prop('disabled', true).html('<i class="ti ti-loader-2 spinner"></i>');
                
                // Send DELETE request via AJAX POST
                $.ajax({
                    url: deleteUrl,
                    type: 'POST',
                    data: {
                        _method: 'DELETE'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.message || 'Kategori başarıyla silindi.');
                            location.reload();
                        } else {
                            alert('Hata: ' + (response.message || 'Bilinmeyen bir hata oluştu.'));
                            $button.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Sunucu hatası oluştu. Lütfen tekrar deneyin.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        alert('Hata: ' + errorMsg);
                        $button.prop('disabled', false).html(originalHtml);
                    }
                });
            }
        });
        
        // Select all checkbox
        $('#selectAll').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.category-checkbox').prop('checked', isChecked);
            updateBulkDeleteButton();
        });
        
        // Individual checkbox change
        $(document).on('change', '.category-checkbox', function() {
            const total = $('.category-checkbox').length;
            const checked = $('.category-checkbox:checked').length;
            $('#selectAll').prop('checked', total === checked);
            updateBulkDeleteButton();
        });
        
        // Bulk delete button click
        $('#bulkDeleteBtn').on('click', function() {
            const selectedIds = [];
            const selectedNames = [];
            const categoriesWithProducts = [];
            
            $('.category-checkbox:checked').each(function() {
                const id = $(this).val();
                const name = $(this).data('category-name');
                const productCount = parseInt($(this).data('product-count')) || 0;
                
                selectedIds.push(id);
                selectedNames.push(name);
                
                if (productCount > 0) {
                    categoriesWithProducts.push({ id: id, name: name, count: productCount });
                }
            });
            
            if (selectedIds.length === 0) {
                alert('Lütfen silmek istediğiniz kategorileri seçin.');
                return;
            }
            
            let confirmMessage = `Seçili ${selectedIds.length} kategoriyi silmek istediğinizden emin misiniz?\n\n`;
            
            if (categoriesWithProducts.length > 0) {
                confirmMessage += `UYARI: Aşağıdaki kategorilerde ürün bulunmaktadır:\n`;
                categoriesWithProducts.forEach(cat => {
                    confirmMessage += `- ${cat.name}: ${cat.count} ürün\n`;
                });
                confirmMessage += `\nBu kategoriler silindiğinde içlerindeki ürünler de silinecektir!\n\n`;
            }
            
            confirmMessage += `Silinecek kategoriler:\n${selectedNames.slice(0, 10).join(', ')}${selectedNames.length > 10 ? '...' : ''}\n\n`;
            confirmMessage += `Bu işlem geri alınamaz!`;
            
            if (confirm(confirmMessage)) {
                // Show loading
                $(this).prop('disabled', true).html('<i class="ti ti-loader-2 spinner"></i> Siliniyor...');
                
                // Send bulk delete request
                $.ajax({
                    url: '<?php echo url('api/categories.php?action=bulk-delete'); ?>',
                    type: 'POST',
                    data: {
                        ids: selectedIds,
                        force_delete: true // Otomatik olarak ürünleri de sil
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.message || `${selectedIds.length} kategori başarıyla silindi.`);
                            location.reload();
                        } else {
                            alert('Hata: ' + (response.message || 'Bilinmeyen bir hata oluştu.'));
                            $('#bulkDeleteBtn').prop('disabled', false).html('<i class="ti ti-trash"></i> <span id="selectedCount">' + selectedIds.length + '</span> Seçili Kategoriyi Sil');
                        }
                    },
                    error: function() {
                        alert('Sunucu hatası oluştu. Lütfen tekrar deneyin.');
                        $('#bulkDeleteBtn').prop('disabled', false).html('<i class="fas fa-trash"></i> <span id="selectedCount">' + selectedIds.length + '</span> Seçili Kategoriyi Sil');
                    }
                });
            }
        });
    });
</script>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
}
?>