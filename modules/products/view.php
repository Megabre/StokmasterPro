<?php
/**
 * Megabre StokMaster Pro
 * View Product
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

// Get product ID from URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    Session::setFlash('error', 'Geçersiz ürün ID\'si.');
    redirect('index.php?module=products');
}

// Get product data
$db->query("SELECT p.*, c.name as category_name 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE p.id = :id");
$db->bind(':id', $productId);
$product = $db->single();

if (!$product) {
    Session::setFlash('error', 'Ürün bulunamadı.');
    redirect('index.php?module=products');
}

// Get product dynamic fields
$productFields = $dynamicFields->getProductFields($productId);

// Get company information from settings
$db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone', 'company_email', 'company_tax_id', 'company_logo')");
$settingsResult = $db->resultSet();
$companyInfo = [];
foreach ($settingsResult as $row) {
    $companyInfo[$row['setting_key']] = $row['setting_value'];
}

// Get current stock level
$db->query("SELECT SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock_level 
            FROM stock_movements 
            WHERE product_id = :product_id");
$db->bind(':product_id', $productId);
$stockResult = $db->single();
$currentStock = $stockResult ? $stockResult['stock_level'] : 0;

// Get stock movements (last 10)
$db->query("SELECT sm.*, DATE_FORMAT(sm.date, '%d.%m.%Y') as formatted_date 
            FROM stock_movements sm
            WHERE sm.product_id = :product_id
            ORDER BY sm.date DESC, sm.id DESC
            LIMIT 10");
$db->bind(':product_id', $productId);
$stockMovements = $db->resultSet();

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header no-print">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Ürün Detayları</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=products'); ?>">Ürünler</a></li>
                <li class="breadcrumb-item active"><?php echo e($product['name']); ?></li>
            </ul>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print"></i> Yazdır
            </button>
            <a href="<?php echo url('index.php?module=products&action=edit&id=' . $productId); ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Düzenle
            </a>
            <a href="<?php echo url('index.php?module=products'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
        </div>
    </div>
</div>

<div class="row print-content">
    <!-- Company Information (Print Only) -->
    <div class="col-12 print-only" style="display: none;">
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <?php if (!empty($companyInfo['company_name'])): ?>
                        <h3 class="mb-2"><?php echo e($companyInfo['company_name']); ?></h3>
                        <?php endif; ?>
                        
                        <?php if (!empty($companyInfo['company_address'])): ?>
                        <p class="mb-1"><strong>Adres:</strong> <?php echo nl2br(e($companyInfo['company_address'])); ?></p>
                        <?php endif; ?>
                        
                        <div class="row">
                            <?php if (!empty($companyInfo['company_phone'])): ?>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Telefon:</strong> <?php echo e($companyInfo['company_phone']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($companyInfo['company_email'])): ?>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>E-posta:</strong> <?php echo e($companyInfo['company_email']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($companyInfo['company_tax_id'])): ?>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Vergi No:</strong> <?php echo e($companyInfo['company_tax_id']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if (!empty($companyInfo['company_logo'])): ?>
                        <img src="<?php echo url('uploads/company/' . $companyInfo['company_logo']); ?>" alt="Logo" style="max-height: 100px;">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Information -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Ürün Bilgileri</h5>
            </div>
            <div class="card-body">
                <!-- Table View for Print -->
                <div class="print-table-view" style="display: none;">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th width="30%">Kategori</th>
                                <td><?php echo e($product['category_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Ürün Adı</th>
                                <td><?php echo e($product['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Fiyat (₺)</th>
                                <td><?php 
                                    $priceDisplay = $product['price'];
                                    if (is_numeric($priceDisplay)) {
                                        $priceDisplay = rtrim(rtrim(sprintf('%.10f', $priceDisplay), '0'), '.');
                                    }
                                    echo $priceDisplay . ' ₺';
                                ?></td>
                            </tr>
                            <tr>
                                <th>SKU</th>
                                <td><?php echo !empty($product['sku']) ? e($product['sku']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Barkod</th>
                                <td><?php echo !empty($product['barcode']) ? e($product['barcode']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Stok Bilgisi</th>
                                <td><?php 
                                    $stockDisplay = $currentStock;
                                    if (is_numeric($stockDisplay)) {
                                        $stockDisplay = rtrim(rtrim(sprintf('%.10f', $stockDisplay), '0'), '.');
                                    }
                                    echo $stockDisplay;
                                ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Normal View for Screen -->
                <div class="screen-view">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Ürün Adı:</strong></label>
                                <p class="mb-0"><?php echo e($product['name']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Kategori:</strong></label>
                                <p class="mb-0"><?php echo e($product['category_name']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><strong>Fiyat:</strong></label>
                                <p class="mb-0"><?php 
                                    $priceDisplay = $product['price'];
                                    if (is_numeric($priceDisplay)) {
                                        $priceDisplay = rtrim(rtrim(sprintf('%.10f', $priceDisplay), '0'), '.');
                                    }
                                    echo $priceDisplay;
                                ?> ₺</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><strong>SKU:</strong></label>
                                <p class="mb-0"><?php echo !empty($product['sku']) ? e($product['sku']) : '-'; ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><strong>Barkod:</strong></label>
                                <p class="mb-0"><?php echo !empty($product['barcode']) ? e($product['barcode']) : '-'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($product['description'])): ?>
                <div class="mb-3">
                    <label class="form-label"><strong>Açıklama:</strong></label>
                    <p class="mb-0"><?php echo nl2br(e($product['description'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($product['image'])): ?>
                <div class="mb-3">
                    <label class="form-label"><strong>Ürün Resmi:</strong></label>
                    <div>
                        <img src="<?php echo url('uploads/products/' . $product['image']); ?>" alt="<?php echo e($product['name']); ?>" class="img-thumbnail" style="max-width: 300px;">
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Dynamic Fields -->
        <?php if (!empty($productFields)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Ürün Özellikleri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($productFields as $field): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><strong><?php echo e($field['field_name']); ?>:</strong></label>
                        <p class="mb-0"><?php echo !empty($field['field_value']) ? e($field['field_value']) : '-'; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Side Panel -->
    <div class="col-md-4">
        <!-- Stock Information -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Stok Bilgisi</h5>
            </div>
            <div class="card-body">
                <div class="stock-info text-center p-3">
                    <h2 class="mb-0 <?php echo $currentStock > 0 ? ($currentStock <= $product['min_stock_level'] ? 'text-warning' : 'text-success') : 'text-danger'; ?>">
                        <?php 
                            $stockDisplay = $currentStock;
                            if (is_numeric($stockDisplay)) {
                                $stockDisplay = rtrim(rtrim(sprintf('%.10f', $stockDisplay), '0'), '.');
                            }
                            echo $stockDisplay;
                        ?>
                    </h2>
                    <p class="text-muted mb-0">Mevcut Stok</p>
                    
                    <?php if ($currentStock <= 0): ?>
                    <div class="alert alert-danger mt-3 mb-0">
                        <i class="fas fa-exclamation-circle"></i> Stokta yok
                    </div>
                    <?php elseif ($currentStock <= $product['min_stock_level']): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Kritik seviye
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success mt-3 mb-0">
                        <i class="fas fa-check-circle"></i> Stok yeterli
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center mt-3">
                    <a href="<?php echo url('index.php?module=stock&action=add&product_id=' . $productId); ?>" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Stok Ekle
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Stock History -->
        <?php if (!empty($stockMovements)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Son Stok Hareketleri</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Tür</th>
                                <th>Miktar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stockMovements as $movement): ?>
                            <tr>
                                <td><?php echo $movement['formatted_date']; ?></td>
                                <td>
                                    <?php if ($movement['type'] == 'in'): ?>
                                    <span class="badge bg-success">Giriş</span>
                                    <?php elseif ($movement['type'] == 'out'): ?>
                                    <span class="badge bg-danger">Çıkış</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning">Düzeltme</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php 
                                    $qtyDisplay = $movement['quantity'];
                                    if (is_numeric($qtyDisplay)) {
                                        $qtyDisplay = rtrim(rtrim(sprintf('%.10f', $qtyDisplay), '0'), '.');
                                    }
                                    echo $qtyDisplay;
                                ?> <?php echo $movement['unit']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-2">
                    <a href="<?php echo url('index.php?module=stock&product_id=' . $productId); ?>" class="btn btn-sm btn-outline-secondary">
                        Tüm Hareketleri Gör
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Product Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Sistem Bilgisi</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>ID:</span>
                    <strong><?php echo $product['id']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Oluşturma Tarihi:</span>
                    <strong><?php echo formatDateTime($product['created_at']); ?></strong>
                </div>
                <?php if (!empty($product['updated_at']) && $product['updated_at'] != $product['created_at']): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Son Güncelleme:</span>
                    <strong><?php echo formatDateTime($product['updated_at']); ?></strong>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Min. Stok Seviyesi:</span>
                    <strong><?php echo $product['min_stock_level']; ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        background: white !important;
    }
    
    .sidebar, .navbar, .footer, .page-header {
        display: none !important;
    }
    
    .print-content {
        margin: 0;
        padding: 20px;
    }
    
    /* Show company info and table view for print */
    .print-only {
        display: block !important;
    }
    
    .print-table-view {
        display: block !important;
    }
    
    .screen-view {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd;
        page-break-inside: avoid;
        margin-bottom: 20px;
        box-shadow: none;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 2px solid #ddd;
        padding: 10px 15px;
    }
    
    .card-title {
        font-size: 18px;
        font-weight: bold;
        margin: 0;
        color: #000 !important;
    }
    
    .card-body {
        padding: 15px;
    }
    
    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 15px;
    }
    
    table th, table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    
    table th {
        background-color: #f8f9fa;
        font-weight: bold;
        width: 30%;
    }
    
    .row {
        margin-bottom: 15px;
    }
    
    .mb-2, .mb-3 {
        margin-bottom: 10px !important;
    }
    
    .text-center {
        text-align: center;
    }
    
    .badge {
        padding: 5px 10px;
        border: 1px solid #000;
        background-color: #f8f9fa !important;
        color: #000 !important;
    }
    
    h2, h3, h4, h5 {
        color: #000 !important;
    }
    
    .btn {
        display: none !important;
    }
    
    img {
        max-width: 100% !important;
        height: auto !important;
    }
}

/* Screen styles */
@media screen {
    .print-only {
        display: none !important;
    }
    
    .print-table-view {
        display: none !important;
    }
    
    .screen-view {
        display: block !important;
    }
}
</style>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>

