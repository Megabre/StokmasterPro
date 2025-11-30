<?php
/**
 * Megabre StokMaster Pro
 * Transactions Index
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
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$transactionType = isset($_GET['type']) ? $_GET['type'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Process actions
switch ($action) {
    case 'add-payment':
        include_once MODULES_PATH . 'transactions/add-payment.php';
        break;
        
    case 'add-debt':
        include_once MODULES_PATH . 'transactions/add-debt.php';
        break;
        
    case 'delete':
        include_once MODULES_PATH . 'transactions/delete.php';
        break;
        
    case 'expenses':
        include_once MODULES_PATH . 'transactions/expenses.php';
        break;
        
    case 'add-expense':
        include_once MODULES_PATH . 'transactions/add-expense.php';
        break;
        
    case 'edit-expense':
        include_once MODULES_PATH . 'transactions/add-expense.php';
        break;
        
    case 'delete-expense':
        include_once MODULES_PATH . 'transactions/delete-expense.php';
        break;
        
    case 'cash-summary':
        include_once MODULES_PATH . 'transactions/cash-summary.php';
        break;
        
    default:
        // Default action: list all transactions
        
        // Create cache key based on filters
        $cacheKey = 'transactions_list_' . md5($customerId . '_' . $transactionType . '_' . $startDate . '_' . $endDate);
        
        // Get transactions from cache or database
        $transactions = $cache->remember($cacheKey, function() use ($db, $customerId, $transactionType, $startDate, $endDate) {
        // Build query based on filters
        $query = "SELECT t.*, c.first_name as customer_name, c.last_name as customer_surname, 
                  DATE_FORMAT(t.date, '%d.%m.%Y') as formatted_date 
                  FROM transactions t 
                  JOIN customers c ON t.customer_id = c.id
                  WHERE 1=1";
        
        $params = [];
        
        // Add customer filter if specified
        if ($customerId > 0) {
            $query .= " AND t.customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        // Add transaction type filter if specified
        if (!empty($transactionType)) {
            $query .= " AND t.type = :type";
            $params[':type'] = $transactionType;
        }
        
        // Add date range filter if specified
        if (!empty($startDate)) {
            $query .= " AND t.date >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if (!empty($endDate)) {
            $query .= " AND t.date <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        $query .= " ORDER BY t.date DESC, t.id DESC";
        
        // Execute query
        $db->query($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $db->bind($key, $value);
        }
        
            return $db->resultSet();
        }, 300); // Cache for 5 minutes
        
        // Get customers for filter dropdown (cached)
        $customers = $cache->remember('customers_list_names', function() use ($db) {
            $db->query("SELECT id, first_name, last_name FROM customers ORDER BY first_name ASC, last_name ASC");
            return $db->resultSet();
        }, 600); // Cache for 10 minutes
        
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
            <h3 class="page-title"><?php echo t('transactions_title', 'Mali İşlemler'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('transactions_title', 'Mali İşlemler'); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary" id="columnFilterBtn" data-bs-toggle="modal" data-bs-target="#columnFilterModal">
                <i class="fas fa-filter"></i> <?php echo t('transactions_column_filter', 'Sütun Filtresi'); ?>
            </button>
            <a href="<?php echo url('index.php?module=transactions&action=add-payment'); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> <?php echo t('transactions_add_payment', 'Ödeme Ekle'); ?>
            </a>
            <a href="<?php echo url('index.php?module=transactions&action=add-debt'); ?>" class="btn btn-danger">
                <i class="fas fa-plus"></i> <?php echo t('transactions_add_debt', 'Borç Ekle'); ?>
            </a>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end" method="get" action="<?php echo url('index.php'); ?>">
            <input type="hidden" name="module" value="transactions">
            
            <div class="col-md-3">
                <label for="customer_id" class="form-label"><?php echo t('transactions_customer', 'Müşteri'); ?></label>
                <select id="customer_id" name="customer_id" class="form-select select2">
                    <option value="0"><?php echo t('transactions_filter_all', 'Tümü'); ?></option>
                    <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['id']; ?>" <?php echo $customerId == $customer['id'] ? 'selected' : ''; ?>>
                        <?php echo e($customer['first_name'] . ' ' . $customer['last_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="type" class="form-label"><?php echo t('transactions_type', 'İşlem Türü'); ?></label>
                <select id="type" name="type" class="form-select">
                    <option value=""><?php echo t('transactions_filter_all', 'Tümü'); ?></option>
                    <option value="payment" <?php echo $transactionType == 'payment' ? 'selected' : ''; ?>><?php echo t('transactions_filter_payment', 'Ödeme'); ?></option>
                    <option value="debt" <?php echo $transactionType == 'debt' ? 'selected' : ''; ?>><?php echo t('transactions_filter_debt', 'Borç'); ?></option>
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
                    <i class="fas fa-search"></i> <?php echo t('transactions_filter', 'Filtrele'); ?>
                </button>
            </div>
            
            <div class="col-md-1">
                <a href="<?php echo url('index.php?module=transactions'); ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-sync"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable" id="transactions-table" data-page-length="50">
                <thead>
                    <tr>
                        <th width="60"><?php echo t('transactions_column_id', 'ID'); ?></th>
                        <th><?php echo t('transactions_column_customer', 'Müşteri'); ?></th>
                        <th><?php echo t('transactions_column_type', 'Tür'); ?></th>
                        <th><?php echo t('transactions_column_amount', 'Tutar (₺)'); ?></th>
                        <th><?php echo t('transactions_column_date', 'Tarih'); ?></th>
                        <th><?php echo t('transactions_column_payment_method', 'Ödeme Yöntemi'); ?></th>
                        <th><?php echo t('transactions_column_reference_no', 'Referans No'); ?></th>
                        <th><?php echo t('transactions_column_notes', 'Not'); ?></th>
                        <th><?php echo t('transactions_column_created_at', 'Oluşturma'); ?></th>
                        <th width="120"><?php echo t('transactions_column_actions', 'İşlemler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo $transaction['id']; ?></td>
                        <td>
                            <a href="<?php echo url('index.php?module=customers&action=edit&id=' . $transaction['customer_id']); ?>">
                                <?php echo e($transaction['customer_name'] . ' ' . $transaction['customer_surname']); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($transaction['type'] == 'payment'): ?>
                            <span class="badge bg-success"><?php echo t('transactions_type_payment', 'ÖDEME'); ?></span>
                            <?php else: ?>
                            <span class="badge bg-danger"><?php echo t('transactions_type_debt', 'BORÇ'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?php echo formatPrice($transaction['amount']); ?> ₺
                        </td>
                        <td>
                            <?php echo $transaction['formatted_date']; ?>
                        </td>
                        <td>
                            <?php
                            $paymentMethods = [
                                'cash' => t('transactions_cash', 'Nakit'),
                                'check' => t('transactions_check', 'Çek'),
                                'promissory' => t('transactions_promissory', 'Senet'),
                                'credit_card' => t('transactions_credit_card', 'Kredi Kartı'),
                                'bank_transfer' => t('transactions_bank_transfer', 'Havale / EFT')
                            ];
                            echo isset($paymentMethods[$transaction['payment_method']]) ? $paymentMethods[$transaction['payment_method']] : $transaction['payment_method'];
                            ?>
                        </td>
                        <td>
                            <?php echo !empty($transaction['reference_no']) ? e($transaction['reference_no']) : '-'; ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($transaction['notes'])) {
                                $note = e($transaction['notes']);
                                echo strlen($note) > 30 ? substr($note, 0, 30) . '...' : $note;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo formatDateTime($transaction['created_at']); ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="<?php echo url('index.php?module=transactions&action=show&id=' . $transaction['id']); ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="<?php echo t('view', 'Göster'); ?>">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($transaction['type'] == 'payment'): ?>
                                <a href="<?php echo url('index.php?module=transactions&action=add-payment&id=' . $transaction['id']); ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="<?php echo t('edit', 'Düzenle'); ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php else: ?>
                                <a href="<?php echo url('index.php?module=transactions&action=add-debt&id=' . $transaction['id']); ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="<?php echo t('edit', 'Düzenle'); ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo url('index.php?module=transactions&action=delete&id=' . $transaction['id']); ?>" class="btn btn-sm btn-danger delete-transaction" data-bs-toggle="tooltip" title="<?php echo t('delete', 'Sil'); ?>" data-id="<?php echo $transaction['id']; ?>" data-customer="<?php echo e($transaction['customer_name'] . ' ' . $transaction['customer_surname']); ?>" data-amount="<?php echo formatPrice($transaction['amount']); ?>" data-type="<?php echo $transaction['type']; ?>">
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

<!-- Transaction Statistics -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('transactions_statistics', 'Mali İstatistikler'); ?></h5>
            </div>
            <div class="card-body">
                <?php
                // Calculate statistics
                $totalPayments = 0;
                $totalDebts = 0;
                $totalCustomers = [];
                
                foreach ($transactions as $transaction) {
                    if ($transaction['type'] == 'payment') {
                        $totalPayments += $transaction['amount'];
                    } else {
                        $totalDebts += $transaction['amount'];
                    }
                    
                    if (!in_array($transaction['customer_id'], $totalCustomers)) {
                        $totalCustomers[] = $transaction['customer_id'];
                    }
                }
                
                $balance = $totalPayments - $totalDebts;
                ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('transactions_total_payments', 'Toplam Ödeme'); ?></h6>
                            <h4 class="text-success"><?php echo formatPrice($totalPayments); ?> ₺</h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('transactions_total_debts', 'Toplam Borç'); ?></h6>
                            <h4 class="text-danger"><?php echo formatPrice($totalDebts); ?> ₺</h4>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('transactions_net_status', 'Net Durum'); ?></h6>
                            <h4 class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo formatPrice(abs($balance)); ?> ₺ 
                                <small>(<?php echo $balance >= 0 ? t('transactions_profit', 'Kâr') : t('transactions_loss', 'Zarar'); ?>)</small>
                            </h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-info">
                            <h6><?php echo t('transactions_customers_with_transactions', 'İşlem Yapılan Müşteriler'); ?></h6>
                            <h4><?php echo count($totalCustomers); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('transactions_help_tips', 'Yardım & İpuçları'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <h6><?php echo t('transactions_title', 'Mali İşlemler'); ?></h6>
                    <ul class="mb-0">
                        <li><?php echo t('transactions_help_tip1', 'Yeni ödeme eklemek için sağ üstteki "Ödeme Ekle" butonunu kullanın.'); ?></li>
                        <li><?php echo t('transactions_help_tip2', 'Yeni borç eklemek için sağ üstteki "Borç Ekle" butonunu kullanın.'); ?></li>
                        <li><?php echo t('transactions_help_tip3', 'İşlemleri tarihe, müşteriye veya türe göre filtreleyebilirsiniz.'); ?></li>
                        <li><?php echo t('transactions_help_tip4', 'İşlem detaylarını görmek için göz simgesine tıklayın.'); ?></li>
                        <li><?php echo t('transactions_help_tip5', 'Taksitli ödemeler otomatik olarak aylık olarak kaydedilir.'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionModalLabel"><?php echo t('transactions_transaction_details', 'İşlem Detayları'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden"><?php echo t('transactions_loading', 'Yükleniyor...'); ?></span>
                    </div>
                    <p class="mt-2"><?php echo t('transactions_loading_details', 'İşlem detayları yükleniyor...'); ?></p>
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
                <h5 class="modal-title" id="columnFilterModalLabel"><?php echo t('transactions_column_filter', 'Sütun Filtresi'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><?php echo t('transactions_column_select', 'Tabloda görmek istediğiniz sütunları seçin:'); ?></p>
                <form id="columnFilterForm">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_id" checked disabled>
                        <label class="form-check-label" for="column_id"><?php echo t('transactions_column_id', 'ID'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_customer" checked>
                        <label class="form-check-label" for="column_customer"><?php echo t('transactions_column_customer', 'Müşteri'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_type" checked>
                        <label class="form-check-label" for="column_type"><?php echo t('transactions_column_type', 'Tür'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_amount" checked>
                        <label class="form-check-label" for="column_amount"><?php echo t('transactions_column_amount', 'Tutar'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_date" checked>
                        <label class="form-check-label" for="column_date"><?php echo t('transactions_column_date', 'Tarih'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_payment_method" checked>
                        <label class="form-check-label" for="column_payment_method"><?php echo t('transactions_column_payment_method', 'Ödeme Yöntemi'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_reference_no" checked>
                        <label class="form-check-label" for="column_reference_no"><?php echo t('transactions_column_reference_no', 'Referans No'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_notes" checked>
                        <label class="form-check-label" for="column_notes"><?php echo t('transactions_column_notes', 'Not'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_created_at" checked>
                        <label class="form-check-label" for="column_created_at"><?php echo t('transactions_column_created_at', 'Oluşturma'); ?></label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="column_actions" checked disabled>
                        <label class="form-check-label" for="column_actions"><?php echo t('transactions_column_actions', 'İşlemler'); ?></label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel', 'İptal'); ?></button>
                <button type="button" class="btn btn-primary" id="applyColumnFilter"><?php echo t('apply', 'Uygula'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize Select2
        if ($.fn.select2) {
            $('.select2').select2({
                width: '100%',
                placeholder: '<?php echo t('orders_select', 'Seçiniz'); ?>'
            });
        }
        
        // Initialize DataTable
        if ($.fn.DataTable) {
            if ($.fn.dataTable.isDataTable('#transactions-table')) {
                $('#transactions-table').DataTable().destroy();
            }
            
            $('#transactions-table').DataTable({
                language: {
                    url: 'assets/js/datatables-tr.json'
                },
                pageLength: 50,
                order: [[4, 'desc'], [0, 'desc']], // Sort by date and ID
                columnDefs: [
                    { orderable: false, targets: [9] } // Disable sorting for actions column
                ]
            });
        }
        
        // Initialize datepickers
        if ($.fn.datepicker) {
            $('#start_date, #end_date').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true
            });
        }
        
        // Load column visibility state from localStorage
        const columnVisibility = JSON.parse(localStorage.getItem('transactionsColumnVisibility')) || {};
        
        // Apply saved column visibility
        if (Object.keys(columnVisibility).length > 0) {
            Object.keys(columnVisibility).forEach(key => {
                $('#transactions-table').DataTable().column(key).visible(columnVisibility[key]);
                $(`#column_${key}`).prop('checked', columnVisibility[key]);
            });
        }
        
        // Apply column filter
        $('#applyColumnFilter').on('click', function() {
            const newColumnVisibility = {};
            
            // Update column visibility
            newColumnVisibility[1] = $('#column_customer').is(':checked');
            newColumnVisibility[2] = $('#column_type').is(':checked');
            newColumnVisibility[3] = $('#column_amount').is(':checked');
            newColumnVisibility[4] = $('#column_date').is(':checked');
            newColumnVisibility[5] = $('#column_payment_method').is(':checked');
            newColumnVisibility[6] = $('#column_reference_no').is(':checked');
            newColumnVisibility[7] = $('#column_notes').is(':checked');
            newColumnVisibility[8] = $('#column_created_at').is(':checked');
            
            // Apply changes
            Object.keys(newColumnVisibility).forEach(key => {
                $('#transactions-table').DataTable().column(key).visible(newColumnVisibility[key]);
            });
            
            // Save to localStorage
            localStorage.setItem('transactionsColumnVisibility', JSON.stringify(newColumnVisibility));
            
            // Close modal
            $('#columnFilterModal').modal('hide');
        });
        
        // Filter form submit
        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            $('#transactions-table').DataTable().ajax.reload();
        });
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Delete transaction confirmation
        $(document).on('click', '.delete-transaction', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var customerName = $(this).data('customer');
            var amount = $(this).data('amount');
            var type = $(this).data('type');
            
            if (confirm('<?php echo t('transactions_delete_confirm', 'Bu işlemi silmek istediğinizden emin misiniz?'); ?>\n\n' +
                       '<?php echo t('transactions_customer', 'Müşteri'); ?>: ' + customerName + '\n' +
                       '<?php echo t('transactions_amount', 'Tutar'); ?>: ' + amount + ' ₺\n' +
                       '<?php echo t('transactions_type', 'Tür'); ?>: ' + (type === 'payment' ? '<?php echo t('transactions_filter_payment', 'Ödeme'); ?>' : '<?php echo t('transactions_filter_debt', 'Borç'); ?>') + '\n\n' +
                       '<?php echo t('common_irreversible', 'Bu işlem geri alınamaz!'); ?>')) {
                window.location.href = $(this).attr('href');
            }
        });
        
        // Helper function to get payment method name
        function getPaymentMethodName(method) {
            const methods = {
                'cash': '<?php echo t('transactions_cash', 'Nakit'); ?>',
                'check': '<?php echo t('transactions_check', 'Çek'); ?>',
                'promissory': '<?php echo t('transactions_promissory', 'Senet'); ?>',
                'credit_card': '<?php echo t('transactions_credit_card', 'Kredi Kartı'); ?>',
                'bank_transfer': '<?php echo t('transactions_bank_transfer', 'Havale / EFT'); ?>'
            };
            
            return methods[method] || method;
        }
        
        // Helper function to format price
        function formatPrice(price, decimals = 2) {
            return parseFloat(price).toLocaleString('tr-TR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }
    });
</script>

<?php
        // Include footer
        include_once INCLUDES_PATH . 'footer.php';
        break;
}
?>