<?php
/**
 * Megabre StokMaster Pro
 * Customers Filter
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    die('Unauthorized');
}

// Initialize database connection
$db = Database::getInstance();

// Get filter parameters
$search = isset($_POST['search']) ? $_POST['search'] : '';
$orderStatus = isset($_POST['order_status']) ? $_POST['order_status'] : 'all';
$balanceStatus = isset($_POST['balance_status']) ? $_POST['balance_status'] : 'all';

// Build query
$query = "SELECT 
    c.*,
    CONCAT(c.first_name, ' ', c.last_name) AS full_name,
    (SELECT COUNT(DISTINCT id) FROM orders WHERE customer_id = c.id) AS order_count,
    (SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE customer_id = c.id) AS total_order_amount,
    (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE customer_id = c.id AND type = 'payment') as total_payments,
    (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE customer_id = c.id AND type = 'debt') as total_debts
FROM customers c
WHERE 1=1";

$params = array();

// Add search condition
if (!empty($search)) {
    $query .= " AND (
        c.first_name LIKE :search1 
        OR c.last_name LIKE :search2 
        OR c.phone LIKE :search3 
        OR c.email LIKE :search4
    )";
    $searchTerm = '%' . $search . '%';
    $params[':search1'] = $searchTerm;
    $params[':search2'] = $searchTerm;
    $params[':search3'] = $searchTerm;
    $params[':search4'] = $searchTerm;
}

// Add order status filter
if ($orderStatus === 'has_orders') {
    $query .= " AND EXISTS (SELECT 1 FROM orders WHERE customer_id = c.id)";
} elseif ($orderStatus === 'no_orders') {
    $query .= " AND NOT EXISTS (SELECT 1 FROM orders WHERE customer_id = c.id)";
}

// Add balance status filter
if ($balanceStatus !== 'all') {
    // Use subquery in WHERE clause instead of HAVING (since we're not using GROUP BY)
    if ($balanceStatus === 'debt') {
        $query .= " AND (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE customer_id = c.id AND type = 'payment') - 
                         (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE customer_id = c.id AND type = 'debt') < 0";
    } elseif ($balanceStatus === 'credit') {
        $query .= " AND (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE customer_id = c.id AND type = 'payment') - 
                         (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE customer_id = c.id AND type = 'debt') > 0";
    } elseif ($balanceStatus === 'zero') {
        $query .= " AND (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE customer_id = c.id AND type = 'payment') - 
                         (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE customer_id = c.id AND type = 'debt') = 0";
    }
}

$query .= " ORDER BY c.id DESC";

// Execute query
$db->query($query);
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$customers = $db->resultSet();

// Calculate balances
foreach ($customers as &$customer) {
    $customer['balance'] = $customer['total_payments'] - $customer['total_debts'];
}

// Return filtered results
if (empty($customers)) {
    echo '<tr>
        <td colspan="6" class="text-center py-4">
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle"></i> Filtreye uygun müşteri bulunamadı.
            </div>
        </td>
    </tr>';
} else {
    foreach ($customers as $customer) {
        echo '<tr>
            <td class="align-middle">' . $customer['id'] . '</td>
            <td class="align-middle">
                <a href="' . url('index.php?module=customers&action=edit&id=' . $customer['id']) . '" class="text-decoration-none">
                    ' . e($customer['full_name']) . '
                </a>
            </td>
            <td class="align-middle">
                <div class="mb-1">
                    <i class="fas fa-phone text-muted me-2"></i> ' . e($customer['phone']) . '
                </div>';
        if (!empty($customer['email'])) {
            echo '<div>
                    <i class="fas fa-envelope text-muted me-2"></i> ' . e($customer['email']) . '
                </div>';
        }
        echo '</td>
            <td class="align-middle">' . e($customer['company']) . '</td>
            <td class="align-middle">
                <div class="mb-1">
                    <strong>Toplam Sipariş:</strong> ' . $customer['order_count'] . '
                </div>';
        if ($customer['order_count'] > 0) {
            echo '<div>
                    <strong>Toplam Tutar:</strong> ' . formatPrice($customer['total_order_amount']) . ' ₺
                </div>';
        }
        echo '</td>
            <td class="align-middle">
                <div class="btn-group">
                    <a href="' . url('index.php?module=customers&action=edit&id=' . $customer['id']) . '" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="' . t('customers_show_edit_tooltip', 'Göster/Düzenle') . '">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="' . url('index.php?module=transactions&action=add-payment&customer_id=' . $customer['id']) . '" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="' . t('customers_add_payment_tooltip', 'Ödeme Ekle') . '">
                        <i class="fas fa-plus-circle"></i>
                    </a>
                    <a href="' . url('index.php?module=customers&action=delete&id=' . $customer['id']) . '" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="' . t('customers_delete_tooltip', 'Sil') . '">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </td>
        </tr>';
    }
}
?> 