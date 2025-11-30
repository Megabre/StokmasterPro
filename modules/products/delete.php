<?php
/**
 * Megabre StokMaster Pro
 * Delete Product
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

// Get product ID from URL
$productId = get('id');

if (!$productId) {
    Session::setFlash('error', 'Geçersiz ürün ID\'si.');
    redirect('index.php?module=products');
}

// Check if product exists
$db->query("SELECT p.*, c.name as category_name, 
           (SELECT SUM(quantity) FROM stock_movements WHERE product_id = p.id) as total_stock 
           FROM products p 
           LEFT JOIN categories c ON p.category_id = c.id 
           WHERE p.id = :id");
$db->bind(':id', $productId);
$product = $db->single();

if (!$product) {
    Session::setFlash('error', 'Ürün bulunamadı.');
    redirect('index.php?module=products');
}

// Check if product has stock
$hasStock = $product['total_stock'] > 0;

// Process delete request
if (isPost() && post('confirm_delete')) {
    // Validate CSRF token
    if (!validateCsrf()) {
        Session::setFlash('error', 'Güvenlik doğrulaması başarısız.');
        redirect('index.php?module=products');
    }
    
    // Check if force delete is requested
    $forceDelete = post('force_delete') == '1';
    
    // If product has stock and force delete is not requested, show error
    if ($hasStock && !$forceDelete) {
        Session::setFlash('error', 'Bu ürünün stokta ' . $product['total_stock'] . ' adet ürünü bulunmaktadır. Silmek için "Zorla Sil" seçeneğini işaretlemeniz gerekmektedir.');
        redirect('index.php?module=products&action=delete&id=' . $productId);
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Delete product field values if table exists (check first)
        try {
            $db->query("DELETE FROM product_field_values WHERE product_id = :id");
            $db->bind(':id', $productId);
            $db->execute();
        } catch (PDOException $e) {
            // Table doesn't exist or column doesn't exist, skip
        }
        
        // Delete order items if table exists
        try {
            $db->query("DELETE FROM order_items WHERE product_id = :id");
            $db->bind(':id', $productId);
            $db->execute();
        } catch (PDOException $e) {
            // Table doesn't exist or column doesn't exist, skip
        }
        
        // Delete stock movements
        $db->query("DELETE FROM stock_movements WHERE product_id = :id");
        $db->bind(':id', $productId);
        $db->execute();
        
        // Log activity before deletion
        logActivity('delete_product', 'product', $productId, [
            'name' => $product['name'],
            'price' => $product['price'],
            'sku' => $product['sku'] ?? '',
            'category_id' => $product['category_id']
        ], null, "Ürün silindi: {$product['name']}");
        
        // Delete product
        $db->query("DELETE FROM products WHERE id = :id");
        $db->bind(':id', $productId);
        $db->execute();
        
        // Delete product image if exists
        if ($product['image'] && file_exists(UPLOADS_PATH . 'products/' . $product['image'])) {
            unlink(UPLOADS_PATH . 'products/' . $product['image']);
        }
        
        // Commit transaction
        $db->endTransaction();
        
        // Set success message
        Session::setFlash('success', 'Ürün başarıyla silindi.');
        
        // Redirect to products list
        redirect('index.php?module=products');
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->cancelTransaction();
        
        Session::setFlash('error', 'Silme işlemi sırasında bir hata oluştu: ' . $e->getMessage());
        redirect('index.php?module=products&action=delete&id=' . $productId);
    }
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Ürün Sil</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=products'); ?>">Ürünler</a></li>
                <li class="breadcrumb-item active">Ürün Sil</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="<?php echo url('index.php?module=products'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
        </div>
    </div>
</div>

<!-- Delete Confirmation -->
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">Ürün Silme Onayı</h5>
            </div>
            <div class="card-body">
                <?php if ($hasStock): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Bu ürünün stokta <?php echo $product['total_stock']; ?> adet ürünü bulunmaktadır. 
                    Ürünü silmek için "Zorla Sil" seçeneğini işaretlemeniz gerekmektedir.
                </div>
                <?php endif; ?>
                
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Aşağıdaki ürünü silmek istediğinizden emin misiniz?
                </div>
                
                <div class="product-info mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo url('uploads/products/' . $product['image']); ?>" alt="<?php echo e($product['name']); ?>" class="img-thumbnail w-100">
                            <?php else: ?>
                            <img src="<?php echo asset('img/no-image.png'); ?>" alt="No Image" class="img-thumbnail w-100">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h5><?php echo e($product['name']); ?></h5>
                            <p><strong>Kategori:</strong> <?php echo e($product['category_name']); ?></p>
                            <p><strong>Fiyat:</strong> <?php echo formatPrice($product['price']); ?> ₺</p>
                            <?php if (!empty($product['sku'])): ?>
                            <p><strong>SKU:</strong> <?php echo e($product['sku']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($product['barcode'])): ?>
                            <p><strong>Barkod:</strong> <?php echo e($product['barcode']); ?></p>
                            <?php endif; ?>
                            <p><strong>Stok Durumu:</strong> <?php echo $product['total_stock']; ?> adet</p>
                        </div>
                    </div>
                </div>
                
                <form action="<?php echo url('index.php?module=products&action=delete&id=' . $productId); ?>" method="post">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="confirm_delete" value="1">
                    
                    <?php if ($hasStock): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="force_delete" name="force_delete" value="1">
                            <label class="form-check-label" for="force_delete">
                                <strong>Zorla Sil</strong> - İlişkili tüm kayıtları da sil (stok hareketleri)
                            </label>
                        </div>
                        <small class="text-muted">Bu seçenek işaretlendiğinde, ürün ve tüm stok hareketleri kalıcı olarak silinecektir.</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <a href="<?php echo url('index.php?module=products'); ?>" class="btn btn-secondary w-100">
                                <i class="fas fa-times"></i> İptal
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-danger w-100" <?php echo $hasStock ? 'id="deleteButton" disabled' : ''; ?>>
                                <i class="fas fa-trash"></i> Ürünü Sil
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($hasStock): ?>
<script>
$(document).ready(function() {
    // Enable/disable delete button based on force delete checkbox
    $('#force_delete').on('change', function() {
        if ($(this).is(':checked')) {
            $('#deleteButton').prop('disabled', false);
        } else {
            $('#deleteButton').prop('disabled', true);
        }
    });
});
</script>
<?php endif; ?>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>