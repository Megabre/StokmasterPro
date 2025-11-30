<?php
/**
 * Megabre StokMaster Pro
 * Delete Transaction
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Get transaction ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // Initialize database connection
    $db = Database::getInstance();
    
    // Get transaction details
    $db->query("SELECT t.*, 
        CONCAT(c.first_name, ' ', c.last_name) as customer_name,
        c.phone as customer_phone,
        c.email as customer_email
        FROM transactions t
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE t.id = :id");
    $db->bind(':id', $id);
    $transaction = $db->single();
    
    if ($transaction) {
        // Log activity before deletion
        logActivity('delete_transaction', 'transaction', $id, [
            'customer_id' => $transaction['customer_id'],
            'type' => $transaction['type'],
            'amount' => $transaction['amount'],
            'date' => $transaction['date'],
            'payment_method' => $transaction['payment_method'] ?? '',
            'reference_no' => $transaction['reference_no'] ?? '',
            'notes' => $transaction['notes'] ?? ''
        ], null, "İşlem silindi: {$transaction['type']} - {$transaction['amount']} ₺");
        
        // Delete transaction
        $db->query("DELETE FROM transactions WHERE id = :id");
        $db->bind(':id', $id);
        
        if ($db->execute()) {
            // Set success message
            Session::setFlash('success', t('transactions_delete_success', 'İşlem başarıyla silindi.'));
        } else {
            // Set error message
            Session::setFlash('error', t('transactions_delete_error', 'İşlem silinirken bir hata oluştu.'));
        }
    } else {
        // Set error message
        Session::setFlash('error', t('transactions_not_found', 'İşlem bulunamadı.'));
    }
}

// Redirect back to transactions page
redirect('index.php?module=transactions');
?>