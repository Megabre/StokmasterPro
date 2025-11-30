<?php
/**
 * Megabre StokMaster Pro
 * Delete Category
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

// Get category ID from URL
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($categoryId <= 0) {
    Session::setFlash('error', 'Geçersiz kategori ID\'si.');
    redirect('index.php?module=categories');
}

// Get category data
$db->query("SELECT * FROM categories WHERE id = :id");
$db->bind(':id', $categoryId);
$category = $db->single();

if (!$category) {
    Session::setFlash('error', 'Kategori bulunamadı.');
    redirect('index.php?module=categories');
}

// Check if category has products
$db->query("SELECT COUNT(*) as count FROM products WHERE category_id = :category_id");
$db->bind(':category_id', $categoryId);
$productCount = $db->single()['count'];

if ($productCount > 0) {
    Session::setFlash('error', 'Bu kategoriye ait ' . $productCount . ' ürün bulunmaktadır. Lütfen önce ürünleri başka bir kategoriye taşıyın veya silin.');
    redirect('index.php?module=categories');
}

// Process deletion
if (isPost()) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=categories');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Delete category fields first (if table exists)
        try {
            $db->query("DELETE FROM category_fields WHERE category_id = :category_id");
            $db->bind(':category_id', $categoryId);
            $db->execute();
        } catch (PDOException $e) {
            // Table doesn't exist or column doesn't exist, skip
        }
        
        // Log activity before deletion
        logActivity('delete_category', 'category', $categoryId, [
            'name' => $category['name'],
            'description' => $category['description'] ?? ''
        ], null, "Kategori silindi: {$category['name']}");
        
        // Delete category
        $db->query("DELETE FROM categories WHERE id = :id");
        $db->bind(':id', $categoryId);
        $db->execute();
        
        // Commit transaction
        $db->endTransaction();
        
        // Set success message
        Session::setFlash('success', 'Kategori başarıyla silindi.');
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->cancelTransaction();
        
        // Set error message
        Session::setFlash('error', 'Kategori silinirken bir hata oluştu: ' . $e->getMessage());
    }
    
    // Redirect to categories list
    redirect('index.php?module=categories');
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Kategori Sil</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=categories'); ?>">Kategoriler</a></li>
                <li class="breadcrumb-item active">Kategori Sil</li>
            </ul>
        </div>
    </div>
</div>

<!-- Delete Category Confirmation -->
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">Kategori Silme Onayı</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Uyarı:</strong> Bu işlem geri alınamaz!
                </div>
                
                <p>Aşağıdaki kategoriyi silmek üzeresiniz:</p>
                
                <div class="category-info mb-4">
                    <h5><?php echo e($category['name']); ?></h5>
                    <p><?php echo !empty($category['description']) ? e($category['description']) : '<i>Açıklama yok</i>'; ?></p>
                    <p class="mb-0"><strong>Oluşturma Tarihi:</strong> <?php echo formatDateTime($category['created_at']); ?></p>
                </div>
                
                <p>Bu kategori silindiğinde, kategoriye ait tüm dinamik alanlar da silinecektir.</p>
                
                <form action="<?php echo url('index.php?module=categories&action=delete&id=' . $categoryId); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <a href="<?php echo url('index.php?module=categories'); ?>" class="btn btn-secondary w-100">
                                <i class="fas fa-times"></i> İptal
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-trash"></i> Kategoriyi Sil
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>