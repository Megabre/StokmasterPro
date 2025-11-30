<?php
/**
 * Megabre StokMaster Pro
 * Calculators Tool
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Get default currency symbol
$db = Database::getInstance();
$db->query("SELECT prefix, suffix FROM currencies WHERE is_default = 1 AND is_active = 1 LIMIT 1");
$defaultCurrency = $db->single();
$currencySymbol = '₺'; // Default fallback
if ($defaultCurrency) {
    $currencySymbol = $defaultCurrency['prefix'] ?: ($defaultCurrency['suffix'] ?: '₺');
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('calculators_title', 'Hesaplama Araçları'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=tools'); ?>"><?php echo t('tools_title', 'Araçlar'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('calculators_title', 'Hesaplama Araçları'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Calculators Tabs -->
<div class="card">
    <div class="card-body">
        <ul class="nav nav-tabs nav-tabs-solid nav-justified" id="calculatorTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="volume-tab" data-bs-toggle="tab" data-bs-target="#volume" type="button" role="tab" aria-controls="volume" aria-selected="true">
                    <i class="fas fa-cube me-2"></i> <?php echo t('calculators_volume_title', 'Metreküp Hesaplama'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="area-tab" data-bs-toggle="tab" data-bs-target="#area" type="button" role="tab" aria-controls="area" aria-selected="false">
                    <i class="fas fa-vector-square me-2"></i> <?php echo t('calculators_area_title', 'Metrekare Hesaplama'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="vat-tab" data-bs-toggle="tab" data-bs-target="#vat" type="button" role="tab" aria-controls="vat" aria-selected="false">
                    <i class="fas fa-percentage me-2"></i> <?php echo t('calculators_vat_title', 'KDV Hesaplama'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="profit-tab" data-bs-toggle="tab" data-bs-target="#profit" type="button" role="tab" aria-controls="profit" aria-selected="false">
                    <i class="fas fa-chart-line me-2"></i> <?php echo t('calculators_profit_title', 'Kâr-Zarar Hesaplama'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="discount-tab" data-bs-toggle="tab" data-bs-target="#discount" type="button" role="tab" aria-controls="discount" aria-selected="false">
                    <i class="fas fa-tags me-2"></i> <?php echo t('calculators_discount_title', 'İndirim Hesaplama'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="installment-tab" data-bs-toggle="tab" data-bs-target="#installment" type="button" role="tab" aria-controls="installment" aria-selected="false">
                    <i class="fas fa-money-bill-wave me-2"></i> <?php echo t('calculators_installment_title', 'Taksit Hesaplama'); ?>
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="calculatorTabContent">
            <!-- Volume Calculator -->
            <div class="tab-pane fade show active" id="volume" role="tabpanel" aria-labelledby="volume-tab">
                <div class="card border-0 shadow-none">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="calculator-form">
                                    <h4 class="mb-4"><?php echo t('calculators_volume_calculator', 'Metreküp (m³) Hesaplayıcı'); ?></h4>
                                    <p class="text-muted mb-4"><?php echo t('calculators_volume_desc', 'Bu hesaplayıcı, uzunluk, genişlik ve yükseklik değerlerini kullanarak metreküp (hacim) hesaplar.'); ?></p>
                                    
                                    <form id="volumeForm">
                                        <div class="mb-3 row">
                                            <label for="length" class="col-sm-4 col-form-label"><?php echo t('calculators_length', 'Uzunluk:'); ?></label>
                                            <div class="col-sm-5">
                                                <input type="number" class="form-control" id="length" step="0.01" min="0">
                                            </div>
                                            <div class="col-sm-3">
                                                <select class="form-select" id="lengthUnit">
                                                    <option value="m">metre</option>
                                                    <option value="cm">santimetre</option>
                                                    <option value="mm">milimetre</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="width" class="col-sm-4 col-form-label"><?php echo t('calculators_width', 'Genişlik:'); ?></label>
                                            <div class="col-sm-5">
                                                <input type="number" class="form-control" id="width" step="0.01" min="0">
                                            </div>
                                            <div class="col-sm-3">
                                                <select class="form-select" id="widthUnit">
                                                    <option value="m">metre</option>
                                                    <option value="cm">santimetre</option>
                                                    <option value="mm">milimetre</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="height" class="col-sm-4 col-form-label"><?php echo t('calculators_height', 'Yükseklik:'); ?></label>
                                            <div class="col-sm-5">
                                                <input type="number" class="form-control" id="height" step="0.01" min="0">
                                            </div>
                                            <div class="col-sm-3">
                                                <select class="form-select" id="heightUnit">
                                                    <option value="m">metre</option>
                                                    <option value="cm">santimetre</option>
                                                    <option value="mm">milimetre</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="quantity" class="col-sm-4 col-form-label"><?php echo t('calculators_quantity', 'Adet:'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="quantity" min="1" value="1">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="unitPrice" class="col-sm-4 col-form-label"><?php echo t('calculators_unit_price', 'Birim Fiyat (İsteğe Bağlı):'); ?></label>
                                            <div class="col-sm-8">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="unitPrice" step="0.01" min="0" placeholder="<?php echo t('calculators_unit_price_placeholder', 'Örn: 9500'); ?>">
                                                    <span class="input-group-text"><?php echo e($currencySymbol); ?></span>
                                                </div>
                                                <small class="text-muted"><?php echo t('calculators_unit_price_help', '1 metreküp için fiyat'); ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4 row">
                                            <div class="col-sm-8 offset-sm-4">
                                                <button type="button" class="btn btn-primary" id="calculateVolume">
                                                    <i class="fas fa-calculator me-2"></i> <?php echo t('calculators_calculate', 'Hesapla'); ?>
                                                </button>
                                                <button type="reset" class="btn btn-secondary ms-2">
                                                    <i class="fas fa-redo me-2"></i> <?php echo t('calculators_clear', 'Temizle'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="result-container">
                                    <h4 class="mb-4"><?php echo t('calculators_results', 'Sonuçlar'); ?></h4>
                                    <div class="border p-3 mb-3 bg-light rounded">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <th><?php echo t('calculators_unit_volume', 'Birim Hacim:'); ?></th>
                                                <td class="text-end"><span id="unitVolume">0</span> m³</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_total_volume', 'Toplam Hacim:'); ?></th>
                                                <td class="text-end"><span id="totalVolume">0</span> m³</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_equivalent_volume', 'Metreküpe Eşit:'); ?></th>
                                                <td class="text-end"><span id="equivalentVolume">0</span> metreküp</td>
                                            </tr>
                                            <tr id="volumePriceRow" style="display: none;">
                                                <th><?php echo t('calculators_total_price', 'Toplam Fiyat:'); ?></th>
                                                <td class="text-end"><span id="totalVolumePrice">0,00</span> <?php echo e($currencySymbol); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong><?php echo t('calculators_info', 'Bilgi:'); ?></strong> <?php echo t('calculators_volume_info', '1 metreküp = 1.000.000 santimetreküp = 1.000.000.000 milimetreküp'); ?>
                                    </div>
                                    
                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0"><?php echo t('calculators_unit_conversions', 'Birim Dönüşümleri'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="mb-2"><strong><?php echo t('calculators_cubic_meters', 'Metreküp (m³):'); ?></strong> <span id="inCubicMeters">0</span></div>
                                                    <div class="mb-2"><strong><?php echo t('calculators_cubic_centimeters', 'Santimetreküp (cm³):'); ?></strong> <span id="inCubicCentimeters">0</span></div>
                                                    <div><strong><?php echo t('calculators_cubic_millimeters', 'Milimetreküp (mm³):'); ?></strong> <span id="inCubicMillimeters">0</span></div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="mb-2"><strong><?php echo t('calculators_liters', 'Litre (L):'); ?></strong> <span id="inLiters">0</span></div>
                                                    <div class="mb-2"><strong><?php echo t('calculators_milliliters', 'Mililitre (mL):'); ?></strong> <span id="inMilliliters">0</span></div>
                                                    <div><strong><?php echo t('calculators_gallons', 'Galon (gal):'); ?></strong> <span id="inGallons">0</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Area Calculator -->
            <div class="tab-pane fade" id="area" role="tabpanel" aria-labelledby="area-tab">
                <div class="card border-0 shadow-none">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="calculator-form">
                                    <h4 class="mb-4"><?php echo t('calculators_area_calculator', 'Metrekare (m²) Hesaplayıcı'); ?></h4>
                                    <p class="text-muted mb-4"><?php echo t('calculators_area_desc', 'Bu hesaplayıcı, uzunluk ve genişlik değerlerini kullanarak metrekare (alan) hesaplar.'); ?></p>
                                    
                                    <form id="areaForm">
                                        <div class="mb-3 row">
                                            <label for="areaLength" class="col-sm-4 col-form-label"><?php echo t('calculators_length', 'Uzunluk:'); ?></label>
                                            <div class="col-sm-5">
                                                <input type="number" class="form-control" id="areaLength" step="0.01" min="0">
                                            </div>
                                            <div class="col-sm-3">
                                                <select class="form-select" id="areaLengthUnit">
                                                    <option value="m">metre</option>
                                                    <option value="cm">santimetre</option>
                                                    <option value="mm">milimetre</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="areaWidth" class="col-sm-4 col-form-label"><?php echo t('calculators_width', 'Genişlik:'); ?></label>
                                            <div class="col-sm-5">
                                                <input type="number" class="form-control" id="areaWidth" step="0.01" min="0">
                                            </div>
                                            <div class="col-sm-3">
                                                <select class="form-select" id="areaWidthUnit">
                                                    <option value="m">metre</option>
                                                    <option value="cm">santimetre</option>
                                                    <option value="mm">milimetre</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="areaQuantity" class="col-sm-4 col-form-label"><?php echo t('calculators_quantity', 'Adet:'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="areaQuantity" min="1" value="1">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="areaUnitPrice" class="col-sm-4 col-form-label"><?php echo t('calculators_unit_price', 'Birim Fiyat (İsteğe Bağlı):'); ?></label>
                                            <div class="col-sm-8">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="areaUnitPrice" step="0.01" min="0" placeholder="<?php echo t('calculators_unit_price_placeholder', 'Örn: 9500'); ?>">
                                                    <span class="input-group-text"><?php echo e($currencySymbol); ?></span>
                                                </div>
                                                <small class="text-muted"><?php echo t('calculators_area_unit_price_help', '1 metrekare için fiyat'); ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4 row">
                                            <div class="col-sm-8 offset-sm-4">
                                                <button type="button" class="btn btn-primary" id="calculateArea">
                                                    <i class="fas fa-calculator me-2"></i> <?php echo t('calculators_calculate', 'Hesapla'); ?>
                                                </button>
                                                <button type="reset" class="btn btn-secondary ms-2">
                                                    <i class="fas fa-redo me-2"></i> <?php echo t('calculators_clear', 'Temizle'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="result-container">
                                    <h4 class="mb-4"><?php echo t('calculators_results', 'Sonuçlar'); ?></h4>
                                    <div class="border p-3 mb-3 bg-light rounded">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <th><?php echo t('calculators_unit_area', 'Birim Alan:'); ?></th>
                                                <td class="text-end"><span id="unitArea">0</span> m²</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_total_area', 'Toplam Alan:'); ?></th>
                                                <td class="text-end"><span id="totalArea">0</span> m²</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_equivalent_area', 'Metrekareye Eşit:'); ?></th>
                                                <td class="text-end"><span id="equivalentArea">0</span> metrekare</td>
                                            </tr>
                                            <tr id="areaPriceRow" style="display: none;">
                                                <th><?php echo t('calculators_total_price', 'Toplam Fiyat:'); ?></th>
                                                <td class="text-end"><span id="totalAreaPrice">0,00</span> <?php echo e($currencySymbol); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong><?php echo t('calculators_info', 'Bilgi:'); ?></strong> <?php echo t('calculators_area_info', '1 metrekare = 10.000 santimetrekare = 1.000.000 milimetrekare'); ?>
                                    </div>
                                    
                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0"><?php echo t('calculators_unit_conversions', 'Birim Dönüşümleri'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="mb-2"><strong><?php echo t('calculators_square_meters', 'Metrekare (m²):'); ?></strong> <span id="inSquareMeters">0</span></div>
                                                    <div class="mb-2"><strong><?php echo t('calculators_square_centimeters', 'Santimetrekare (cm²):'); ?></strong> <span id="inSquareCentimeters">0</span></div>
                                                    <div><strong><?php echo t('calculators_square_millimeters', 'Milimetrekare (mm²):'); ?></strong> <span id="inSquareMillimeters">0</span></div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="mb-2"><strong><?php echo t('calculators_hectares', 'Hektar (ha):'); ?></strong> <span id="inHectares">0</span></div>
                                                    <div class="mb-2"><strong><?php echo t('calculators_ares', 'Ar (a):'); ?></strong> <span id="inAres">0</span></div>
                                                    <div><strong><?php echo t('calculators_donums', 'Dönüm:'); ?></strong> <span id="inDonums">0</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- VAT Calculator -->
            <div class="tab-pane fade" id="vat" role="tabpanel" aria-labelledby="vat-tab">
                <div class="card border-0 shadow-none">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="calculator-form">
                                    <h4 class="mb-4"><?php echo t('calculators_vat_calculator', 'KDV Hesaplayıcı'); ?></h4>
                                    <p class="text-muted mb-4"><?php echo t('calculators_vat_desc', 'Bu hesaplayıcı, fiyata KDV eklemek veya fiyattan KDV çıkarmak için kullanılabilir.'); ?></p>
                                    
                                    <form id="vatForm">
                                        <div class="mb-3">
                                            <label class="form-label"><?php echo t('calculators_calculation_type', 'Hesaplama Türü:'); ?></label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="vatType" id="vatAdd" value="add" checked>
                                                <label class="form-check-label" for="vatAdd">
                                                    <?php echo t('calculators_add_vat', 'Fiyata KDV Ekle (KDV Hariç → KDV Dahil)'); ?>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="vatType" id="vatRemove" value="remove">
                                                <label class="form-check-label" for="vatRemove">
                                                    <?php echo t('calculators_remove_vat', 'Fiyattan KDV Çıkar (KDV Dahil → KDV Hariç)'); ?>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="vatRate" class="col-sm-4 col-form-label"><?php echo t('calculators_vat_rate', 'KDV Oranı:'); ?></label>
                                            <div class="col-sm-8">
                                                <select class="form-select" id="vatRate">
                                                    <option value="20"><?php echo t('calculators_vat_rate_general', '%20 (Genel)'); ?></option>
                                                    <option value="18">%18</option>
                                                    <option value="10">%10</option>
                                                    <option value="8">%8</option>
                                                    <option value="1">%1</option>
                                                    <option value="0">%0</option>
                                                    <option value="custom"><?php echo t('calculators_custom_rate', 'Özel'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row" id="customVatRateRow" style="display: none;">
                                            <label for="customVatRate" class="col-sm-4 col-form-label"><?php echo t('calculators_custom_rate_label', 'Özel Oran (%):'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="customVatRate" min="0" max="100" step="0.01">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="vatAmount" class="col-sm-4 col-form-label"><?php echo t('calculators_amount', 'Tutar (₺):'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="vatAmount" step="0.01" min="0">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4 row">
                                            <div class="col-sm-8 offset-sm-4">
                                                <button type="button" class="btn btn-primary" id="calculateVat">
                                                    <i class="fas fa-calculator me-2"></i> <?php echo t('calculators_calculate', 'Hesapla'); ?>
                                                </button>
                                                <button type="reset" class="btn btn-secondary ms-2">
                                                    <i class="fas fa-redo me-2"></i> <?php echo t('calculators_clear', 'Temizle'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="result-container">
                                    <h4 class="mb-4"><?php echo t('calculators_results', 'Sonuçlar'); ?></h4>
                                    <div class="border p-3 mb-3 bg-light rounded">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <th><?php echo t('calculators_vat_excluded', 'KDV Hariç Tutar:'); ?></th>
                                                <td class="text-end"><span id="vatExcluded">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_vat_only', 'KDV Tutarı:'); ?></th>
                                                <td class="text-end"><span id="vatOnly">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_vat_included', 'KDV Dahil Tutar:'); ?></th>
                                                <td class="text-end"><span id="vatIncluded">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_applied_vat_rate', 'Uygulanan KDV Oranı:'); ?></th>
                                                <td class="text-end"><span id="appliedVatRate">0</span>%</td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong><?php echo t('calculators_formulas', 'Formüller:'); ?></strong><br>
                                        <?php echo t('calculators_vat_formula1', 'KDV Hariç → KDV Dahil: Tutar + (Tutar × KDV Oranı / 100)'); ?><br>
                                        <?php echo t('calculators_vat_formula2', 'KDV Dahil → KDV Hariç: Tutar / (1 + KDV Oranı / 100)'); ?>
                                    </div>
                                    
                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0"><?php echo t('calculators_vat_example_table', 'KDV Örnek Hesaplama Tablosu'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th><?php echo t('calculators_vat_rate', 'KDV Oranı'); ?></th>
                                                            <th><?php echo t('calculators_vat_excluded', 'KDV Hariç'); ?></th>
                                                            <th><?php echo t('calculators_vat_only', 'KDV Tutarı'); ?></th>
                                                            <th><?php echo t('calculators_vat_included', 'KDV Dahil'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="vatExampleTable">
                                                        <tr>
                                                            <td>%20</td>
                                                            <td>100,00 ₺</td>
                                                            <td>20,00 ₺</td>
                                                            <td>120,00 ₺</td>
                                                        </tr>
                                                        <tr>
                                                            <td>%18</td>
                                                            <td>100,00 ₺</td>
                                                            <td>18,00 ₺</td>
                                                            <td>118,00 ₺</td>
                                                        </tr>
                                                        <tr>
                                                            <td>%10</td>
                                                            <td>100,00 ₺</td>
                                                            <td>10,00 ₺</td>
                                                            <td>110,00 ₺</td>
                                                        </tr>
                                                        <tr>
                                                            <td>%8</td>
                                                            <td>100,00 ₺</td>
                                                            <td>8,00 ₺</td>
                                                            <td>108,00 ₺</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Profit Calculator -->
            <div class="tab-pane fade" id="profit" role="tabpanel" aria-labelledby="profit-tab">
                <div class="card border-0 shadow-none">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="calculator-form">
                                    <h4 class="mb-4"><?php echo t('calculators_profit_calculator', 'Kâr-Zarar Hesaplayıcı'); ?></h4>
                                    <p class="text-muted mb-4"><?php echo t('calculators_profit_desc', 'Bu hesaplayıcı, maliyet ve satış fiyatı kullanarak kâr-zarar durumunu hesaplar.'); ?></p>
                                    
                                    <form id="profitForm">
                                        <div class="mb-3 row">
                                            <label for="costPrice" class="col-sm-4 col-form-label"><?php echo t('calculators_cost_price', 'Maliyet Fiyatı (₺):'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="costPrice" step="0.01" min="0">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="sellingPrice" class="col-sm-4 col-form-label"><?php echo t('calculators_selling_price', 'Satış Fiyatı (₺):'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="sellingPrice" step="0.01" min="0">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="quantity" class="col-sm-4 col-form-label"><?php echo t('calculators_quantity', 'Adet:'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="profitQuantity" min="1" value="1">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4 row">
                                            <div class="col-sm-8 offset-sm-4">
                                                <button type="button" class="btn btn-primary" id="calculateProfit">
                                                    <i class="fas fa-calculator me-2"></i> <?php echo t('calculators_calculate', 'Hesapla'); ?>
                                                </button>
                                                <button type="reset" class="btn btn-secondary ms-2">
                                                    <i class="fas fa-redo me-2"></i> <?php echo t('calculators_clear', 'Temizle'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    
                                    <div class="mt-4">
                                        <h5><?php echo t('calculators_alternative_margin', 'Alternatif: Kâr Marjı ile Hesaplama'); ?></h5>
                                        <form id="marginForm">
                                            <div class="mb-3 row">
                                                <label for="baseCost" class="col-sm-4 col-form-label"><?php echo t('calculators_cost_price', 'Maliyet Fiyatı (₺):'); ?></label>
                                                <div class="col-sm-8">
                                                    <input type="number" class="form-control" id="baseCost" step="0.01" min="0">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 row">
                                                <label for="profitMargin" class="col-sm-4 col-form-label"><?php echo t('calculators_profit_margin_label', 'Kâr Marjı (%):'); ?></label>
                                                <div class="col-sm-8">
                                                    <input type="number" class="form-control" id="profitMargin" step="0.1" min="0" max="1000">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-4 row">
                                                <div class="col-sm-8 offset-sm-4">
                                                    <button type="button" class="btn btn-primary" id="calculateMargin">
                                                        <i class="fas fa-calculator me-2"></i> <?php echo t('calculators_calculate', 'Hesapla'); ?>
                                                    </button>
                                                    <button type="reset" class="btn btn-secondary ms-2">
                                                        <i class="fas fa-redo me-2"></i> <?php echo t('calculators_clear', 'Temizle'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="result-container">
                                    <h4 class="mb-4"><?php echo t('calculators_results', 'Sonuçlar'); ?></h4>
                                    <div class="border p-3 mb-3 bg-light rounded">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <th><?php echo t('calculators_total_cost', 'Toplam Maliyet:'); ?></th>
                                                <td class="text-end"><span id="totalCost">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_total_sales', 'Toplam Satış:'); ?></th>
                                                <td class="text-end"><span id="totalSales">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_profit_loss', 'Kâr/Zarar:'); ?></th>
                                                <td class="text-end">
                                                    <span id="profitAmount">0,00</span> ₺
                                                    <span id="profitStatus" class="badge bg-secondary ms-1">-</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_profit_margin', 'Kâr Marjı:'); ?></th>
                                                <td class="text-end"><span id="calculatedMargin">0,00</span>%</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_profit_ratio', 'Kâr Oranı:'); ?></th>
                                                <td class="text-end"><span id="profitRatio">0,00</span>%</td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong><?php echo t('calculators_formulas', 'Formüller:'); ?></strong><br>
                                        <?php echo t('calculators_profit_formula1', 'Kâr/Zarar = Satış Fiyatı - Maliyet Fiyatı'); ?><br>
                                        <?php echo t('calculators_profit_formula2', 'Kâr Marjı (%) = (Kâr/Zarar ÷ Satış Fiyatı) × 100'); ?><br>
                                        <?php echo t('calculators_profit_formula3', 'Kâr Oranı (%) = (Kâr/Zarar ÷ Maliyet Fiyatı) × 100'); ?>
                                    </div>
                                    
                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0"><?php echo t('calculators_margin_price_title', 'Kâr Marjına Göre Satış Fiyatı'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th><?php echo t('calculators_margin_label', 'Kâr Marjı'); ?></th>
                                                            <th><?php echo t('calculators_cost', 'Maliyet'); ?></th>
                                                            <th><?php echo t('calculators_selling_price_label', 'Satış Fiyatı'); ?></th>
                                                            <th><?php echo t('calculators_profit_amount', 'Kâr'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="marginExampleTable">
                                                        <tr>
                                                            <td>%10</td>
                                                            <td>100,00 ₺</td>
                                                            <td>111,11 ₺</td>
                                                            <td>11,11 ₺</td>
                                                        </tr>
                                                        <tr>
                                                            <td>%20</td>
                                                            <td>100,00 ₺</td>
                                                            <td>125,00 ₺</td>
                                                            <td>25,00 ₺</td>
                                                        </tr>
                                                        <tr>
                                                            <td>%30</td>
                                                            <td>100,00 ₺</td>
                                                            <td>142,86 ₺</td>
                                                            <td>42,86 ₺</td>
                                                        </tr>
                                                        <tr>
                                                            <td>%50</td>
                                                            <td>100,00 ₺</td>
                                                            <td>200,00 ₺</td>
                                                            <td>100,00 ₺</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <div class="card bg-light">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo t('calculators_margin_calculation', 'Kâr Marjından Satış Fiyatı Hesabı'); ?></h6>
                                                        <p class="mb-0"><?php echo t('calculators_margin_formula', 'Satış Fiyatı = Maliyet ÷ (1 - (Kâr Marjı / 100))'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Discount Calculator -->
            <div class="tab-pane fade" id="discount" role="tabpanel" aria-labelledby="discount-tab">
                <div class="card border-0 shadow-none">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="calculator-form">
                                    <h4 class="mb-4"><?php echo t('calculators_discount_calculator', 'İndirim Hesaplayıcı'); ?></h4>
                                    <p class="text-muted mb-4"><?php echo t('calculators_discount_desc', 'Bu hesaplayıcı, ürün fiyatına belirli bir indirim oranı uygulayarak indirimli fiyatı hesaplar.'); ?></p>
                                    
                                    <form id="discountForm">
                                        <div class="mb-3 row">
                                            <label for="originalPrice" class="col-sm-4 col-form-label"><?php echo t('calculators_original_price', 'Orijinal Fiyat (₺):'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="originalPrice" step="0.01" min="0">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="discountRate" class="col-sm-4 col-form-label"><?php echo t('calculators_discount_rate', 'İndirim Oranı (%):'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="discountRate" step="0.1" min="0" max="100">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4 row">
                                            <div class="col-sm-8 offset-sm-4">
                                                <button type="button" class="btn btn-primary" id="calculateDiscount">
                                                    <i class="fas fa-calculator me-2"></i> <?php echo t('calculators_calculate', 'Hesapla'); ?>
                                                </button>
                                                <button type="reset" class="btn btn-secondary ms-2">
                                                    <i class="fas fa-redo me-2"></i> <?php echo t('calculators_clear', 'Temizle'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    
                                    <div class="mt-4">
                                        <h5><?php echo t('calculators_alternative_reverse', 'Alternatif: İndirimli Fiyattan Hesaplama'); ?></h5>
                                        <form id="reverseDiscountForm">
                                            <div class="mb-3 row">
                                                <label for="discountedPrice" class="col-sm-4 col-form-label"><?php echo t('calculators_discounted_price_label', 'İndirimli Fiyat (₺):'); ?></label>
                                                <div class="col-sm-8">
                                                    <input type="number" class="form-control" id="discountedPrice" step="0.01" min="0">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 row">
                                                <label for="reverseDiscountRate" class="col-sm-4 col-form-label"><?php echo t('calculators_reverse_discount_rate', 'İndirim Oranı (%):'); ?></label>
                                                <div class="col-sm-8">
                                                    <input type="number" class="form-control" id="reverseDiscountRate" step="0.1" min="0" max="100">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-4 row">
                                                <div class="col-sm-8 offset-sm-4">
                                                    <button type="button" class="btn btn-primary" id="calculateReverseDiscount">
                                                        <i class="fas fa-calculator me-2"></i> <?php echo t('calculators_calculate', 'Hesapla'); ?>
                                                    </button>
                                                    <button type="reset" class="btn btn-secondary ms-2">
                                                        <i class="fas fa-redo me-2"></i> <?php echo t('calculators_clear', 'Temizle'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="result-container">
                                    <h4 class="mb-4"><?php echo t('calculators_results', 'Sonuçlar'); ?></h4>
                                    <div class="border p-3 mb-3 bg-light rounded">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <th><?php echo t('calculators_result_original_price', 'Orijinal Fiyat:'); ?></th>
                                                <td class="text-end"><span id="resultOriginalPrice">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_discount_amount', 'İndirim Tutarı:'); ?></th>
                                                <td class="text-end"><span id="discountAmount">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_discounted_price', 'İndirimli Fiyat:'); ?></th>
                                                <td class="text-end"><span id="resultDiscountedPrice">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_applied_discount', 'Uygulanan İndirim:'); ?></th>
                                                <td class="text-end"><span id="appliedDiscountRate">0</span>%</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_savings', 'Tasarruf:'); ?></th>
                                                <td class="text-end"><span id="savings">0,00</span> ₺</td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong><?php echo t('calculators_formulas', 'Formüller:'); ?></strong><br>
                                        <?php echo t('calculators_discount_formula1', 'İndirim Tutarı = Orijinal Fiyat × (İndirim Oranı / 100)'); ?><br>
                                        <?php echo t('calculators_discount_formula2', 'İndirimli Fiyat = Orijinal Fiyat - İndirim Tutarı'); ?><br>
                                        <?php echo t('calculators_discount_formula3', 'Orijinal Fiyat = İndirimli Fiyat / (1 - (İndirim Oranı / 100))'); ?>
                                    </div>
                                    
                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0"><?php echo t('calculators_discount_table', 'İndirim Tablosu'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th><?php echo t('calculators_discount_rate_label', 'İndirim Oranı'); ?></th>
                                                            <th><?php echo t('calculators_result_original_price', 'Orijinal Fiyat'); ?></th>
                                                            <th><?php echo t('calculators_discount_amount', 'İndirim Tutarı'); ?></th>
                                                            <th><?php echo t('calculators_discounted_price', 'İndirimli Fiyat'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="discountExampleTable">
                                                        <tr>
                                                            <td>%5</td>
                                                            <td>100,00 ₺</td>
                                                            <td>5,00 ₺</td>
                                                            <td>95,00 ₺</td>
                                                        </tr>
                                                        <tr>
                                                            <td>%10</td>
                                                            <td>100,00 ₺</td>
                                                            <td>10,00 ₺</td>
                                                            <td>90,00 ₺</td>
                                                        </tr>
                                                        <tr>
                                                            <td>%20</td>
                                                            <td>100,00 ₺</td>
                                                            <td>20,00 ₺</td>
                                                            <td>80,00 ₺</td>
                                                        </tr>
                                                        <tr>
                                                            <td>%50</td>
                                                            <td>100,00 ₺</td>
                                                            <td>50,00 ₺</td>
                                                            <td>50,00 ₺</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Installment Calculator -->
            <div class="tab-pane fade" id="installment" role="tabpanel" aria-labelledby="installment-tab">
                <div class="card border-0 shadow-none">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="calculator-form">
                                    <h4 class="mb-4"><?php echo t('calculators_installment_calculator', 'Taksit Hesaplayıcı'); ?></h4>
                                    <p class="text-muted mb-4"><?php echo t('calculators_installment_desc', 'Bu hesaplayıcı, toplam tutarı belirli sayıda taksite bölerek taksit tutarlarını hesaplar.'); ?></p>
                                    
                                    <form id="installmentForm">
                                        <div class="mb-3 row">
                                            <label for="totalAmount" class="col-sm-4 col-form-label"><?php echo t('calculators_total_amount', 'Toplam Tutar (₺):'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="totalAmount" step="0.01" min="0">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="installmentCount" class="col-sm-4 col-form-label"><?php echo t('calculators_installment_count', 'Taksit Sayısı:'); ?></label>
                                            <div class="col-sm-8">
                                                <select class="form-select" id="installmentCount">
                                                    <option value="2">2 <?php echo t('calculators_installment', 'Taksit'); ?></option>
                                                    <option value="3">3 <?php echo t('calculators_installment', 'Taksit'); ?></option>
                                                    <option value="6" selected>6 <?php echo t('calculators_installment', 'Taksit'); ?></option>
                                                    <option value="9">9 <?php echo t('calculators_installment', 'Taksit'); ?></option>
                                                    <option value="12">12 <?php echo t('calculators_installment', 'Taksit'); ?></option>
                                                    <option value="18">18 <?php echo t('calculators_installment', 'Taksit'); ?></option>
                                                    <option value="24">24 <?php echo t('calculators_installment', 'Taksit'); ?></option>
                                                    <option value="36">36 <?php echo t('calculators_installment', 'Taksit'); ?></option>
                                                    <option value="custom"><?php echo t('calculators_custom_installment', 'Özel'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row" id="customInstallmentRow" style="display: none;">
                                            <label for="customInstallmentCount" class="col-sm-4 col-form-label"><?php echo t('calculators_custom_installment_count', 'Özel Taksit Sayısı:'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="customInstallmentCount" min="2" max="60" step="1">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="interestRate" class="col-sm-4 col-form-label"><?php echo t('calculators_monthly_interest', 'Aylık Faiz (%):'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="number" class="form-control" id="interestRate" step="0.01" min="0" value="0">
                                                <small class="form-text text-muted"><?php echo t('calculators_interest_free_note', 'Faizsiz hesaplama için 0 girin.'); ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 row">
                                            <label for="startDate" class="col-sm-4 col-form-label"><?php echo t('calculators_start_date', 'Başlangıç Tarihi:'); ?></label>
                                            <div class="col-sm-8">
                                                <input type="date" class="form-control" id="startDate" value="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4 row">
                                            <div class="col-sm-8 offset-sm-4">
                                                <button type="button" class="btn btn-primary" id="calculateInstallment">
                                                    <i class="fas fa-calculator me-2"></i> <?php echo t('calculators_calculate', 'Hesapla'); ?>
                                                </button>
                                                <button type="reset" class="btn btn-secondary ms-2">
                                                    <i class="fas fa-redo me-2"></i> <?php echo t('calculators_clear', 'Temizle'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="result-container">
                                    <h4 class="mb-4"><?php echo t('calculators_results', 'Sonuçlar'); ?></h4>
                                    <div class="border p-3 mb-3 bg-light rounded">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <th><?php echo t('calculators_result_total_amount', 'Toplam Tutar:'); ?></th>
                                                <td class="text-end"><span id="resultTotalAmount">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_result_installment_count', 'Taksit Sayısı:'); ?></th>
                                                <td class="text-end"><span id="resultInstallmentCount">0</span> <?php echo t('calculators_months', 'ay'); ?></td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_monthly_interest_rate', 'Aylık Faiz Oranı:'); ?></th>
                                                <td class="text-end"><span id="resultInterestRate">0,00</span>%</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_monthly_payment', 'Aylık Taksit Tutarı:'); ?></th>
                                                <td class="text-end"><span id="monthlyPayment">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_total_payment', 'Toplam Ödenecek:'); ?></th>
                                                <td class="text-end"><span id="totalPayment">0,00</span> ₺</td>
                                            </tr>
                                            <tr>
                                                <th><?php echo t('calculators_total_interest', 'Toplam Faiz:'); ?></th>
                                                <td class="text-end"><span id="totalInterest">0,00</span> ₺</td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <h5><?php echo t('calculators_installment_plan', 'Taksit Planı'); ?></h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th><?php echo t('calculators_installment_no', 'Taksit No'); ?></th>
                                                        <th><?php echo t('calculators_date', 'Tarih'); ?></th>
                                                        <th><?php echo t('calculators_installment_amount', 'Taksit Tutarı'); ?></th>
                                                        <th><?php echo t('calculators_principal', 'Ana Para'); ?></th>
                                                        <th><?php echo t('calculators_interest', 'Faiz'); ?></th>
                                                        <th><?php echo t('calculators_remaining_debt', 'Kalan Borç'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="installmentTable">
                                                    <tr>
                                                        <td colspan="6" class="text-center"><?php echo t('calculators_not_calculated', 'Henüz hesaplama yapılmadı'); ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Currency symbol from PHP
        const currencySymbol = '<?php echo e($currencySymbol); ?>';
        
        // Format number as currency
        function formatCurrency(number) {
            return number.toLocaleString('tr-TR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Format number with comma as decimal separator
        function formatNumber(number, decimals) {
            if (typeof decimals === 'undefined') {
                decimals = 2;
            }
            return number.toLocaleString('tr-TR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }
        
        // Volume Calculator
        $('#calculateVolume').on('click', function() {
            let length = parseFloat($('#length').val()) || 0;
            let width = parseFloat($('#width').val()) || 0;
            let height = parseFloat($('#height').val()) || 0;
            let quantity = parseInt($('#quantity').val()) || 1;
            const unitPrice = parseFloat($('#unitPrice').val()) || 0;
            
            // Convert to meters
            const lengthUnit = $('#lengthUnit').val();
            const widthUnit = $('#widthUnit').val();
            const heightUnit = $('#heightUnit').val();
            
            if (lengthUnit === 'cm') length /= 100;
            else if (lengthUnit === 'mm') length /= 1000;
            
            if (widthUnit === 'cm') width /= 100;
            else if (widthUnit === 'mm') width /= 1000;
            
            if (heightUnit === 'cm') height /= 100;
            else if (heightUnit === 'mm') height /= 1000;
            
            // Calculate volume in cubic meters
            const unitVolume = length * width * height;
            const totalVolume = unitVolume * quantity;
            
            // Update results
            $('#unitVolume').text(formatNumber(unitVolume, 6));
            $('#totalVolume').text(formatNumber(totalVolume, 6));
            $('#equivalentVolume').text(formatNumber(totalVolume, 6));
            
            // Calculate and display price if unit price is provided
            if (unitPrice > 0) {
                const totalPrice = totalVolume * unitPrice;
                $('#totalVolumePrice').text(formatCurrency(totalPrice));
                $('#volumePriceRow').show();
            } else {
                $('#volumePriceRow').hide();
            }
            
            // Update conversions
            $('#inCubicMeters').text(formatNumber(totalVolume, 6));
            $('#inCubicCentimeters').text(formatNumber(totalVolume * 1000000, 2));
            $('#inCubicMillimeters').text(formatNumber(totalVolume * 1000000000, 0));
            $('#inLiters').text(formatNumber(totalVolume * 1000, 2));
            $('#inMilliliters').text(formatNumber(totalVolume * 1000000, 0));
            $('#inGallons').text(formatNumber(totalVolume * 264.172, 2));
        });
        
        // Area Calculator
        $('#calculateArea').on('click', function() {
            let length = parseFloat($('#areaLength').val()) || 0;
            let width = parseFloat($('#areaWidth').val()) || 0;
            let quantity = parseInt($('#areaQuantity').val()) || 1;
            const unitPrice = parseFloat($('#areaUnitPrice').val()) || 0;
            
            // Convert to meters
            const lengthUnit = $('#areaLengthUnit').val();
            const widthUnit = $('#areaWidthUnit').val();
            
            if (lengthUnit === 'cm') length /= 100;
            else if (lengthUnit === 'mm') length /= 1000;
            
            if (widthUnit === 'cm') width /= 100;
            else if (widthUnit === 'mm') width /= 1000;
            
            // Calculate area in square meters
            const unitArea = length * width;
            const totalArea = unitArea * quantity;
            
            // Update results
            $('#unitArea').text(formatNumber(unitArea, 6));
            $('#totalArea').text(formatNumber(totalArea, 6));
            $('#equivalentArea').text(formatNumber(totalArea, 6));
            
            // Calculate and display price if unit price is provided
            if (unitPrice > 0) {
                const totalPrice = totalArea * unitPrice;
                $('#totalAreaPrice').text(formatCurrency(totalPrice));
                $('#areaPriceRow').show();
            } else {
                $('#areaPriceRow').hide();
            }
            
            // Update conversions
            $('#inSquareMeters').text(formatNumber(totalArea, 6));
            $('#inSquareCentimeters').text(formatNumber(totalArea * 10000, 2));
            $('#inSquareMillimeters').text(formatNumber(totalArea * 1000000, 0));
            $('#inHectares').text(formatNumber(totalArea / 10000, 8));
            $('#inAres').text(formatNumber(totalArea / 100, 6));
            $('#inDonums').text(formatNumber(totalArea / 1000, 6));
        });
        
        // VAT Calculator
        $('#vatRate').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#customVatRateRow').show();
            } else {
                $('#customVatRateRow').hide();
            }
        });
        
        $('#calculateVat').on('click', function() {
            const amount = parseFloat($('#vatAmount').val()) || 0;
            let rate = $('#vatRate').val();
            
            if (rate === 'custom') {
                rate = parseFloat($('#customVatRate').val()) || 0;
            } else {
                rate = parseFloat(rate);
            }
            
            const vatType = $('input[name="vatType"]:checked').val();
            let vatExcluded, vatOnly, vatIncluded;
            
            if (vatType === 'add') {
                // KDV Hariç → KDV Dahil
                vatExcluded = amount;
                vatOnly = amount * (rate / 100);
                vatIncluded = amount + vatOnly;
            } else {
                // KDV Dahil → KDV Hariç
                vatIncluded = amount;
                vatExcluded = amount / (1 + (rate / 100));
                vatOnly = vatIncluded - vatExcluded;
            }
            
            // Update results
            $('#vatExcluded').text(formatCurrency(vatExcluded));
            $('#vatOnly').text(formatCurrency(vatOnly));
            $('#vatIncluded').text(formatCurrency(vatIncluded));
            $('#appliedVatRate').text(rate);
            
            // Update example table with the selected rate
            const exampleBase = 100;
            const exampleVat = exampleBase * (rate / 100);
            const exampleTotal = exampleBase + exampleVat;
            
            // Clear and build new example table
            $('#vatExampleTable').html(`
                <tr>
                    <td>%${rate}</td>
                    <td>${formatCurrency(exampleBase)} ₺</td>
                    <td>${formatCurrency(exampleVat)} ₺</td>
                    <td>${formatCurrency(exampleTotal)} ₺</td>
                </tr>
                <tr>
                    <td>%${rate}</td>
                    <td>${formatCurrency(exampleBase * 2)} ₺</td>
                    <td>${formatCurrency(exampleVat * 2)} ₺</td>
                    <td>${formatCurrency(exampleTotal * 2)} ₺</td>
                </tr>
                <tr>
                    <td>%${rate}</td>
                    <td>${formatCurrency(exampleBase * 5)} ₺</td>
                    <td>${formatCurrency(exampleVat * 5)} ₺</td>
                    <td>${formatCurrency(exampleTotal * 5)} ₺</td>
                </tr>
                <tr>
                    <td>%${rate}</td>
                    <td>${formatCurrency(exampleBase * 10)} ₺</td>
                    <td>${formatCurrency(exampleVat * 10)} ₺</td>
                    <td>${formatCurrency(exampleTotal * 10)} ₺</td>
                </tr>
            `);
        });
        
        // Profit Calculator
        $('#calculateProfit').on('click', function() {
            const costPrice = parseFloat($('#costPrice').val()) || 0;
            const sellingPrice = parseFloat($('#sellingPrice').val()) || 0;
            const quantity = parseInt($('#profitQuantity').val()) || 1;
            
            const totalCost = costPrice * quantity;
            const totalSales = sellingPrice * quantity;
            const profit = totalSales - totalCost;
            
            // Calculate profit margin and ratio
            let profitMargin = 0;
            let profitRatio = 0;
            
            if (totalSales > 0) {
                profitMargin = (profit / totalSales) * 100;
            }
            
            if (totalCost > 0) {
                profitRatio = (profit / totalCost) * 100;
            }
            
            // Update results
            $('#totalCost').text(formatCurrency(totalCost));
            $('#totalSales').text(formatCurrency(totalSales));
            $('#profitAmount').text(formatCurrency(Math.abs(profit)));
            
            if (profit > 0) {
                $('#profitStatus').text('<?php echo t('calculators_profit_label', 'Kâr'); ?>').removeClass('bg-danger bg-secondary').addClass('bg-success');
            } else if (profit < 0) {
                $('#profitStatus').text('<?php echo t('calculators_loss_label', 'Zarar'); ?>').removeClass('bg-success bg-secondary').addClass('bg-danger');
            } else {
                $('#profitStatus').text('<?php echo t('calculators_break_even_label', 'Başabaş'); ?>').removeClass('bg-success bg-danger').addClass('bg-secondary');
            }
            
            $('#calculatedMargin').text(formatNumber(profitMargin, 2));
            $('#profitRatio').text(formatNumber(profitRatio, 2));
        });
        
        // Margin Calculator
        $('#calculateMargin').on('click', function() {
            const costPrice = parseFloat($('#baseCost').val()) || 0;
            const margin = parseFloat($('#profitMargin').val()) || 0;
            
            // Calculate selling price from margin
            let sellingPrice = 0;
            if (margin < 100) {
                sellingPrice = costPrice / (1 - (margin / 100));
            } else {
                sellingPrice = costPrice * ((margin / 100) + 1);
            }
            
            const profit = sellingPrice - costPrice;
            
            // Update results
            $('#totalCost').text(formatCurrency(costPrice));
            $('#totalSales').text(formatCurrency(sellingPrice));
            $('#profitAmount').text(formatCurrency(profit));
            $('#profitStatus').text('<?php echo t('calculators_profit_label', 'Kâr'); ?>').removeClass('bg-danger bg-secondary').addClass('bg-success');
            $('#calculatedMargin').text(formatNumber(margin, 2));
            $('#profitRatio').text(formatNumber((profit / costPrice) * 100, 2));
            
            // Update margin table
            const exampleBase = 100;
            $('#marginExampleTable').html('');
            
            [10, 20, 30, 50, margin].forEach(rate => {
                let exampleSellingPrice = 0;
                if (rate < 100) {
                    exampleSellingPrice = exampleBase / (1 - (rate / 100));
                } else {
                    exampleSellingPrice = exampleBase * ((rate / 100) + 1);
                }
                const exampleProfit = exampleSellingPrice - exampleBase;
                
                $('#marginExampleTable').append(`
                    <tr>
                        <td>%${formatNumber(rate, 1)}</td>
                        <td>${formatCurrency(exampleBase)} ₺</td>
                        <td>${formatCurrency(exampleSellingPrice)} ₺</td>
                        <td>${formatCurrency(exampleProfit)} ₺</td>
                    </tr>
                `);
            });
        });
        
        // Discount Calculator
        $('#calculateDiscount').on('click', function() {
            const originalPrice = parseFloat($('#originalPrice').val()) || 0;
            const discountRate = parseFloat($('#discountRate').val()) || 0;
            
            const discountAmount = originalPrice * (discountRate / 100);
            const discountedPrice = originalPrice - discountAmount;
            
            // Update results
            $('#resultOriginalPrice').text(formatCurrency(originalPrice));
            $('#discountAmount').text(formatCurrency(discountAmount));
            $('#resultDiscountedPrice').text(formatCurrency(discountedPrice));
            $('#appliedDiscountRate').text(discountRate);
            $('#savings').text(formatCurrency(discountAmount));
            
            // Update discount example table
            const exampleBase = 100;
            $('#discountExampleTable').html('');
            
            [5, 10, 20, 50, discountRate].forEach(rate => {
                if (rate === discountRate && (rate === 5 || rate === 10 || rate === 20 || rate === 50)) {
                    return; // Skip duplicate rates
                }
                
                const exampleDiscount = exampleBase * (rate / 100);
                const exampleFinal = exampleBase - exampleDiscount;
                
                $('#discountExampleTable').append(`
                    <tr>
                        <td>%${formatNumber(rate, 1)}</td>
                        <td>${formatCurrency(exampleBase)} ₺</td>
                        <td>${formatCurrency(exampleDiscount)} ₺</td>
                        <td>${formatCurrency(exampleFinal)} ₺</td>
                    </tr>
                `);
            });
        });
        
        // Reverse Discount Calculator
        $('#calculateReverseDiscount').on('click', function() {
            const discountedPrice = parseFloat($('#discountedPrice').val()) || 0;
            const discountRate = parseFloat($('#reverseDiscountRate').val()) || 0;
            
            if (discountRate >= 100) {
                alert('İndirim oranı %100\'den küçük olmalıdır!');
                return;
            }
            
            const originalPrice = discountedPrice / (1 - (discountRate / 100));
            const discountAmount = originalPrice - discountedPrice;
            
            // Update results
            $('#resultOriginalPrice').text(formatCurrency(originalPrice));
            $('#discountAmount').text(formatCurrency(discountAmount));
            $('#resultDiscountedPrice').text(formatCurrency(discountedPrice));
            $('#appliedDiscountRate').text(discountRate);
            $('#savings').text(formatCurrency(discountAmount));
            
            // Update discount example table
            const exampleBase = 100;
            $('#discountExampleTable').html('');
            
            [5, 10, 20, 50, discountRate].forEach(rate => {
                if (rate === discountRate && (rate === 5 || rate === 10 || rate === 20 || rate === 50)) {
                    return; // Skip duplicate rates
                }
                
                const exampleDiscount = exampleBase * (rate / 100);
                const exampleFinal = exampleBase - exampleDiscount;
                
                $('#discountExampleTable').append(`
                    <tr>
                        <td>%${formatNumber(rate, 1)}</td>
                        <td>${formatCurrency(exampleBase)} ₺</td>
                        <td>${formatCurrency(exampleDiscount)} ₺</td>
                        <td>${formatCurrency(exampleFinal)} ₺</td>
                    </tr>
                `);
            });
        });
        
        // Installment Calculator
        $('#installmentCount').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#customInstallmentRow').show();
            } else {
                $('#customInstallmentRow').hide();
            }
        });
        
        $('#calculateInstallment').on('click', function() {
            const totalAmount = parseFloat($('#totalAmount').val()) || 0;
            let installmentCount = $('#installmentCount').val();
            
            if (installmentCount === 'custom') {
                installmentCount = parseInt($('#customInstallmentCount').val()) || 6;
            } else {
                installmentCount = parseInt(installmentCount);
            }
            
            const interestRate = parseFloat($('#interestRate').val()) || 0;
            const startDate = new Date($('#startDate').val() || new Date());
            
            let monthlyPayment, totalPayment, totalInterest;
            
            if (interestRate === 0) {
                // No interest calculation
                monthlyPayment = totalAmount / installmentCount;
                totalPayment = totalAmount;
                totalInterest = 0;
            } else {
                // Compound interest calculation
                const monthlyInterestRate = interestRate / 100;
                monthlyPayment = totalAmount * (monthlyInterestRate * Math.pow(1 + monthlyInterestRate, installmentCount)) / 
                                (Math.pow(1 + monthlyInterestRate, installmentCount) - 1);
                totalPayment = monthlyPayment * installmentCount;
                totalInterest = totalPayment - totalAmount;
            }
            
            // Update results
            $('#resultTotalAmount').text(formatCurrency(totalAmount));
            $('#resultInstallmentCount').text(installmentCount);
            $('#resultInterestRate').text(formatNumber(interestRate, 2));
            $('#monthlyPayment').text(formatCurrency(monthlyPayment));
            $('#totalPayment').text(formatCurrency(totalPayment));
            $('#totalInterest').text(formatCurrency(totalInterest));
            
            // Build installment table
            let remainingPrincipal = totalAmount;
            let installmentTable = '';
            
            for (let i = 1; i <= installmentCount; i++) {
                const installmentDate = new Date(startDate);
                installmentDate.setMonth(startDate.getMonth() + i - 1);
                
                let interestPayment = 0;
                let principalPayment = 0;
                
                if (interestRate === 0) {
                    // No interest
                    principalPayment = monthlyPayment;
                    interestPayment = 0;
                } else {
                    // Calculate interest portion
                    interestPayment = remainingPrincipal * (interestRate / 100);
                    principalPayment = monthlyPayment - interestPayment;
                }
                
                remainingPrincipal -= principalPayment;
                if (i === installmentCount) {
                    // Ensure the last payment clears the remaining principal
                    principalPayment += remainingPrincipal;
                    remainingPrincipal = 0;
                }
                
                const formattedDate = installmentDate.toLocaleDateString('tr-TR');
                
                installmentTable += `
                    <tr>
                        <td>${i}</td>
                        <td>${formattedDate}</td>
                        <td>${formatCurrency(monthlyPayment)} ₺</td>
                        <td>${formatCurrency(principalPayment)} ₺</td>
                        <td>${formatCurrency(interestPayment)} ₺</td>
                        <td>${formatCurrency(Math.max(0, remainingPrincipal))} ₺</td>
                    </tr>
                `;
            }
            
            $('#installmentTable').html(installmentTable);
        });
    });
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>