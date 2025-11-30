<?php
/**
 * Megabre StokMaster Pro
 * View Customer Details
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

// Initialize dynamic fields class
$dynamicFields = new DynamicFields();

// Get customer ID from URL
$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customerId <= 0) {
    Session::setFlash('error', t('customers_invalid_id', 'Geçersiz müşteri ID\'si.'));
    redirect('index.php?module=customers');
}

// Get customer data
$db->query("SELECT * FROM customers WHERE id = :id");
$db->bind(':id', $customerId);
$customer = $db->single();

if (!$customer) {
    Session::setFlash('error', t('customers_not_found', 'Müşteri bulunamadı.'));
    redirect('index.php?module=customers');
}

// Get customer balance
$db->query("SELECT COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) as total_payments,
            COALESCE(SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END), 0) as total_debts
            FROM transactions 
            WHERE customer_id = :customer_id");
$db->bind(':customer_id', $customerId);
$balance = $db->single();

$totalPayments = $balance['total_payments'];
$totalDebts = $balance['total_debts'];
$netBalance = $totalPayments - $totalDebts;

// Get customer dynamic fields
$customerFields = $dynamicFields->getCustomerFields($customerId);

// Get active customer fields (system-wide dynamic fields)
$db->query("SELECT * FROM customer_fields WHERE (customer_id = 0 OR customer_id IS NULL) AND is_active = 1 ORDER BY field_order ASC, id ASC");
$systemFields = $db->resultSet();

// Get system field values for this customer
$systemFieldValues = [];
if (!empty($systemFields)) {
    try {
        $fieldIds = array_column($systemFields, 'id');
        if (!empty($fieldIds)) {
            $placeholders = [];
            foreach ($fieldIds as $index => $fieldId) {
                $placeholders[] = ':field_id_' . $index;
            }
            $placeholdersStr = implode(',', $placeholders);
            
            $db->query("SELECT field_id, field_value FROM customer_field_values WHERE customer_id = :customer_id AND field_id IN ($placeholdersStr)");
            $db->bind(':customer_id', $customerId);
            foreach ($fieldIds as $index => $fieldId) {
                $db->bind(':field_id_' . $index, $fieldId);
            }
            $values = $db->resultSet();
            foreach ($values as $value) {
                $systemFieldValues[$value['field_id']] = $value['field_value'];
            }
        }
    } catch (PDOException $e) {
        // Table might not exist, skip
        error_log('Customer field values table not found: ' . $e->getMessage());
    }
}

// Get all customer tags
$db->query("SELECT * FROM customer_tags WHERE is_active = 1 ORDER BY name ASC");
$allTags = $db->resultSet();

// Get customer tags
$db->query("SELECT tag_id FROM customer_tag_relations WHERE customer_id = :customer_id");
$db->bind(':customer_id', $customerId);
$customerTagRelations = $db->resultSet();
$customerTagIds = array_column($customerTagRelations, 'tag_id');

// Get customer orders
$db->query("SELECT o.*, 
            COUNT(oi.id) AS item_count,
            (SELECT COUNT(*) FROM stock_movements sm WHERE sm.order_id = o.id) AS has_stock_movements
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.customer_id = :customer_id
            GROUP BY o.id
            ORDER BY o.order_date DESC, o.id DESC
            LIMIT 10");
$db->bind(':customer_id', $customerId);
$customerOrders = $db->resultSet();

// Get customer transactions
$db->query("SELECT t.*, 
            o.id AS order_id
            FROM transactions t
            LEFT JOIN orders o ON t.order_id = o.id
            WHERE t.customer_id = :customer_id
            ORDER BY t.date DESC, t.id DESC
            LIMIT 10");
$db->bind(':customer_id', $customerId);
$customerTransactions = $db->resultSet();

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle"><?php echo t('customers_title', 'Müşteriler'); ?></div>
                <h2 class="page-title"><?php echo t('customers_view_title', 'Müşteri Detayları'); ?></h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="<?php echo url('index.php?module=customers&action=edit&id=' . $customerId); ?>" class="btn btn-primary">
                        <i class="ti ti-edit"></i> <?php echo t('customers_edit', 'Düzenle'); ?>
                    </a>
                    <a href="<?php echo url('index.php?module=transactions&action=add-payment&customer_id=' . $customerId); ?>" class="btn btn-success">
                        <i class="ti ti-circle-plus"></i> <?php echo t('customers_add_payment_button', 'Ödeme Ekle'); ?>
                    </a>
                    <a href="<?php echo url('index.php?module=customers'); ?>" class="btn">
                        <i class="ti ti-arrow-left"></i> <?php echo t('back', 'Geri'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="page-body">
    <div class="container-xl">
        <!-- Quick Info Row -->
        <div class="row mb-4">
            <!-- Customer Info (Compact) -->
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo e($customer['first_name'] . ' ' . $customer['last_name']); ?></h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width: 40%;"><?php echo t('customers_phone', 'Telefon'); ?>:</td>
                                <td><i class="ti ti-phone"></i> <?php echo e($customer['phone']); ?></td>
                            </tr>
                            <?php if (!empty($customer['email'])): ?>
                            <tr>
                                <td class="text-muted"><?php echo t('customers_email', 'E-posta'); ?>:</td>
                                <td><i class="ti ti-mail"></i> <a href="mailto:<?php echo e($customer['email']); ?>" class="text-decoration-none"><?php echo e($customer['email']); ?></a></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($customer['company'])): ?>
                            <tr>
                                <td class="text-muted"><?php echo t('customers_company', 'Şirket'); ?>:</td>
                                <td><?php echo e($customer['company']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($customer['address'])): ?>
                            <tr>
                                <td class="text-muted" style="vertical-align: top;"><?php echo t('customers_address', 'Adres'); ?>:</td>
                                <td><small><?php echo nl2br(e($customer['address'])); ?></small></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($customerTagIds)): ?>
                            <tr>
                                <td class="text-muted"><?php echo t('customers_tags', 'Etiketler'); ?>:</td>
                                <td>
                                    <?php foreach ($allTags as $tag): ?>
                                    <?php if (in_array($tag['id'], $customerTagIds)): ?>
                                    <span class="badge bg-primary"><?php echo e($tag['name']); ?></span>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Balance Card -->
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo t('customers_balance', 'Bakiye'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <div class="text-muted small"><?php echo t('customers_total_payments', 'Toplam Ödemeler'); ?></div>
                            <div class="h4 text-success mb-0"><?php echo formatPrice($totalPayments); ?> ₺</div>
                        </div>
                        <div class="mb-2">
                            <div class="text-muted small"><?php echo t('customers_total_debts', 'Toplam Borçlar'); ?></div>
                            <div class="h4 text-danger mb-0"><?php echo formatPrice($totalDebts); ?> ₺</div>
                        </div>
                        <div class="border-top pt-2">
                            <div class="text-muted small"><?php echo t('customers_net_balance', 'Net Bakiye'); ?></div>
                            <div class="h3 mb-0 <?php echo $netBalance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo formatPrice(abs($netBalance)); ?> ₺
                                <small class="d-block text-muted" style="font-size: 0.6em;">
                                    <?php if ($netBalance >= 0): ?>
                                    (<?php echo t('customers_credit', 'Alacaklı'); ?>)
                                    <?php else: ?>
                                    (<?php echo t('customers_debt', 'Borçlu'); ?>)
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo t('customers_quick_actions', 'Hızlı İşlemler'); ?></h3>
                    </div>
                     <div class="card-body">
                         <div class="row g-2">
                             <div class="col-md-6">
                                 <a href="<?php echo url('index.php?module=orders&action=add&customer_id=' . $customerId); ?>" class="btn btn-primary w-100">
                                     <i class="ti ti-shopping-cart-plus"></i> <?php echo t('customers_new_order', 'Yeni Sipariş'); ?>
                                 </a>
                             </div>
                             <div class="col-md-6">
                                 <a href="<?php echo url('index.php?module=transactions&action=add-payment&customer_id=' . $customerId); ?>" class="btn btn-success w-100">
                                     <i class="ti ti-circle-plus"></i> <?php echo t('customers_add_payment_button', 'Ödeme Ekle'); ?>
                                 </a>
                             </div>
                             <div class="col-md-6">
                                 <a href="<?php echo url('index.php?module=transactions&action=add-debt&customer_id=' . $customerId); ?>" class="btn btn-danger w-100">
                                     <i class="ti ti-minus"></i> <?php echo t('customers_add_debt_button', 'Borç Ekle'); ?>
                                 </a>
                             </div>
                             <div class="col-md-6">
                                 <a href="<?php echo url('index.php?module=transactions&customer_id=' . $customerId); ?>" class="btn btn-info w-100">
                                     <i class="ti ti-list"></i> <?php echo t('customers_view_transactions', 'Tüm İşlemler'); ?>
                                 </a>
                             </div>
                         </div>
                     </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title"><?php echo t('customers_recent_orders', 'Sipariş Geçmişi'); ?></h3>
            </div>
            <?php if (!empty($customerOrders)): ?>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th><?php echo t('orders_order_id', 'Sipariş No'); ?></th>
                                <th><?php echo t('orders_date', 'Tarih'); ?></th>
                                <th><?php echo t('orders_status', 'Durum'); ?></th>
                                <th><?php echo t('orders_items', 'Ürün Sayısı'); ?></th>
                                <th><?php echo t('orders_total', 'Toplam'); ?></th>
                                <th><?php echo t('actions', 'İşlemler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerOrders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $statusText = [
                                        'pending' => t('orders_status_pending', 'Bekleyen'),
                                        'processing' => t('orders_status_processing', 'İşlemde'),
                                        'completed' => t('orders_status_completed', 'Tamamlandı'),
                                        'cancelled' => t('orders_status_cancelled', 'İptal')
                                    ];
                                    $status = $order['status'] ?? 'pending';
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass[$status] ?? 'secondary'; ?>">
                                        <?php echo $statusText[$status] ?? $status; ?>
                                    </span>
                                </td>
                                <td><?php echo $order['item_count']; ?></td>
                                <td><?php echo formatPrice($order['grand_total']); ?> ₺</td>
                                <td>
                                    <div class="btn-list">
                                        <a href="<?php echo url('index.php?module=orders&action=view&id=' . $order['id']); ?>" class="btn btn-sm btn-info">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="card-body">
                <p class="text-muted mb-0 text-center"><?php echo t('customers_no_orders', 'Henüz sipariş bulunmuyor.'); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Transactions -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title"><?php echo t('customers_recent_transactions', 'Mali Geçmiş'); ?></h3>
            </div>
            <?php if (!empty($customerTransactions)): ?>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th><?php echo t('transactions_date', 'Tarih'); ?></th>
                                <th><?php echo t('transactions_type', 'Tür'); ?></th>
                                <th><?php echo t('transactions_amount', 'Tutar'); ?></th>
                                <th><?php echo t('transactions_payment_method', 'Ödeme Yöntemi'); ?></th>
                                <th><?php echo t('transactions_notes', 'Not'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerTransactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('d.m.Y', strtotime($transaction['date'])); ?></td>
                                <td>
                                    <?php if ($transaction['type'] == 'payment'): ?>
                                    <span class="badge bg-success"><?php echo t('transactions_payment', 'Ödeme'); ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-danger"><?php echo t('transactions_debt', 'Borç'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $transaction['type'] == 'payment' ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $transaction['type'] == 'payment' ? '+' : '-'; ?><?php echo formatPrice($transaction['amount']); ?> ₺
                                </td>
                                <td><?php echo e($transaction['payment_method'] ?? '-'); ?></td>
                                <td><?php echo e($transaction['notes'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="card-body">
                <p class="text-muted mb-0 text-center"><?php echo t('customers_no_transactions', 'Henüz işlem bulunmuyor.'); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>

