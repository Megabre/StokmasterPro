<?php
/**
 * Megabre StokMaster Pro
 * Dış Giderler Listesi
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

// Get page parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$expenseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category = isset($_GET['category']) ? $_GET['category'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Default action: list all expenses
// Build query based on filters
$query = "SELECT e.*, 
          DATE_FORMAT(e.date, '%d.%m.%Y') as formatted_date,
          u.name as created_by_name
          FROM expenses e 
          LEFT JOIN users u ON e.created_by = u.id
          WHERE 1=1";

$params = [];

// Add category filter if specified
if (!empty($category)) {
    $query .= " AND e.category = :category";
    $params[':category'] = $category;
}

// Add date range filter if specified
if (!empty($startDate)) {
    $query .= " AND e.date >= :start_date";
    $params[':start_date'] = $startDate;
}

if (!empty($endDate)) {
    $query .= " AND e.date <= :end_date";
    $params[':end_date'] = $endDate;
}

$query .= " ORDER BY e.date DESC, e.id DESC";

// Execute query
$db->query($query);

// Bind parameters
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}

// Get expenses
$expenses = $db->resultSet();

// Get categories for filter dropdown
$db->query("SELECT DISTINCT category FROM expenses ORDER BY category ASC");
$categories = $db->resultSet();

// Calculate statistics
$totalExpenses = 0;
foreach ($expenses as $expense) {
    $totalExpenses += $expense['amount'];
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
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('expenses_title', 'Dış Giderler'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=transactions'); ?>"><?php echo t('transactions_title', 'Mali İşlemler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('expenses_title', 'Dış Giderler'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="<?php echo url('index.php?module=transactions&action=add-expense'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> <?php echo t('expenses_add', 'Gider Ekle'); ?>
            </a>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end" method="get" action="<?php echo url('index.php'); ?>">
            <input type="hidden" name="module" value="transactions">
            <input type="hidden" name="action" value="expenses">
            
            <div class="col-md-3">
                <label for="category" class="form-label"><?php echo t('expenses_category', 'Kategori'); ?></label>
                <select id="category" name="category" class="form-select">
                    <option value=""><?php echo t('transactions_filter_all', 'Tümü'); ?></option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo e($cat['category']); ?>" <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                        <?php echo e($cat['category']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="start_date" class="form-label"><?php echo t('transactions_from_date', 'Başlangıç Tarihi'); ?></label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
            </div>
            
            <div class="col-md-2">
                <label for="end_date" class="form-label"><?php echo t('transactions_to_date', 'Bitiş Tarihi'); ?></label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> <?php echo t('filter', 'Filtrele'); ?>
                </button>
            </div>
            
            <div class="col-md-1">
                <a href="<?php echo url('index.php?module=transactions&action=expenses'); ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-sync"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Expenses Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable" id="expenses-table" data-page-length="50">
                <thead>
                    <tr>
                        <th width="60"><?php echo t('transactions_column_id', 'ID'); ?></th>
                        <th><?php echo t('expenses_category', 'Kategori'); ?></th>
                        <th><?php echo t('expenses_description', 'Açıklama'); ?></th>
                        <th><?php echo t('expenses_supplier', 'Tedarikçi'); ?></th>
                        <th><?php echo t('transactions_amount', 'Tutar (₺)'); ?></th>
                        <th><?php echo t('transactions_date', 'Tarih'); ?></th>
                        <th><?php echo t('transactions_payment_method', 'Ödeme Yöntemi'); ?></th>
                        <th><?php echo t('transactions_reference_no', 'Referans No'); ?></th>
                        <th><?php echo t('expenses_created_by', 'Oluşturan'); ?></th>
                        <th width="120"><?php echo t('transactions_actions', 'İşlemler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><?php echo $expense['id']; ?></td>
                        <td><span class="badge bg-info"><?php echo e($expense['category']); ?></span></td>
                        <td>
                            <?php 
                            if (!empty($expense['description'])) {
                                $desc = e($expense['description']);
                                echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo !empty($expense['supplier']) ? e($expense['supplier']) : '-'; ?></td>
                        <td class="text-end fw-bold text-danger">
                            <?php echo formatPrice($expense['amount']); ?> ₺
                        </td>
                        <td><?php echo $expense['formatted_date']; ?></td>
                        <td>
                            <?php
                            $paymentMethods = [
                                'cash' => t('transactions_cash', 'Nakit'),
                                'check' => t('transactions_check', 'Çek'),
                                'promissory_note' => t('transactions_promissory', 'Senet'),
                                'credit_card' => t('transactions_credit_card', 'Kredi Kartı'),
                                'bank_transfer' => t('transactions_bank_transfer', 'Havale / EFT')
                            ];
                            echo isset($paymentMethods[$expense['payment_method']]) ? $paymentMethods[$expense['payment_method']] : $expense['payment_method'];
                            ?>
                        </td>
                        <td><?php echo !empty($expense['reference_no']) ? e($expense['reference_no']) : '-'; ?></td>
                        <td><?php echo !empty($expense['created_by_name']) ? e($expense['created_by_name']) : '-'; ?></td>
                        <td>
                            <div class="actions">
                                <a href="<?php echo url('index.php?module=transactions&action=edit-expense&id=' . $expense['id']); ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="<?php echo t('edit', 'Düzenle'); ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <a href="<?php echo url('index.php?module=transactions&action=delete-expense&id=' . $expense['id']); ?>" class="btn btn-sm btn-danger delete-expense" data-bs-toggle="tooltip" title="<?php echo t('delete', 'Sil'); ?>" data-id="<?php echo $expense['id']; ?>" data-category="<?php echo e($expense['category']); ?>" data-amount="<?php echo formatPrice($expense['amount']); ?>">
                                    <i class="fas fa-trash"></i>
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

<!-- Statistics -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('expenses_statistics', 'Gider İstatistikleri'); ?></h5>
            </div>
            <div class="card-body">
                <div class="stats-info">
                    <h6><?php echo t('expenses_total_expenses', 'Toplam Gider'); ?></h6>
                    <h4 class="text-danger"><?php echo formatPrice($totalExpenses); ?> ₺</h4>
                </div>
                <div class="stats-info mt-3">
                    <h6><?php echo t('expenses_total_count', 'Toplam Gider Sayısı'); ?></h6>
                    <h4><?php echo count($expenses); ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('expenses_help_tips', 'Yardım & İpuçları'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <h6><?php echo t('expenses_title', 'Dış Giderler'); ?></h6>
                    <ul class="mb-0">
                        <li><?php echo t('expenses_help_tip4', 'Yeni gider eklemek için sağ üstteki "Gider Ekle" butonunu kullanın.'); ?></li>
                        <li><?php echo t('expenses_help_tip5', 'Giderleri kategori, tarih aralığına göre filtreleyebilirsiniz.'); ?></li>
                        <li><?php echo t('expenses_help_tip6', 'Gider bilgilerini düzenlemek için düzenle simgesine tıklayın.'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        if ($.fn.DataTable) {
            if ($.fn.dataTable.isDataTable('#expenses-table')) {
                $('#expenses-table').DataTable().destroy();
            }
            
            $('#expenses-table').DataTable({
                language: {
                    url: 'assets/js/datatables-tr.json'
                },
                pageLength: 50,
                order: [[5, 'desc'], [0, 'desc']], // Sort by date and ID
                columnDefs: [
                    { orderable: false, targets: [9] } // Disable sorting for actions column
                ]
            });
        }
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Delete expense confirmation
        $(document).on('click', '.delete-expense', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var category = $(this).data('category');
            var amount = $(this).data('amount');
            
            if (confirm('<?php echo t('expenses_delete_confirm', 'Bu gideri silmek istediğinizden emin misiniz?'); ?>\n\n' +
                       '<?php echo t('expenses_category', 'Kategori'); ?>: ' + category + '\n' +
                       '<?php echo t('transactions_amount', 'Tutar'); ?>: ' + amount + ' ₺\n\n' +
                       '<?php echo t('message_confirm_delete', 'Bu işlem geri alınamaz!'); ?>')) {
                window.location.href = $(this).attr('href');
            }
        });
    });
</script>

<?php
include_once INCLUDES_PATH . 'footer.php';

