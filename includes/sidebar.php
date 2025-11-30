<?php
/**
 * Megabre StokMaster Pro
 * Sidebar Template - Tabler.io
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Get current module and action
$current_module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';
$current_action = isset($_GET['action']) ? $_GET['action'] : 'index';
?>
<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
    <div class="container-fluid">
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <h1 class="navbar-brand navbar-brand-autodark">
            <a href="<?php echo url('index.php'); ?>">
                <?php if (isset($settings['company_logo']) && !empty($settings['company_logo'])): ?>
                    <img src="<?php echo url('uploads/company/' . $settings['company_logo']); ?>" alt="<?php echo e($settings['company_name'] ?? APP_NAME); ?>" class="navbar-brand-image" height="32">
                <?php else: ?>
                    <img src="<?php echo asset('img/logo-small.png'); ?>" alt="<?php echo APP_NAME; ?>" class="navbar-brand-image" height="32">
                <?php endif; ?>
                <?php if (isset($settings['company_name']) && !empty($settings['company_name'])): ?>
                    <span class="ms-2 d-none d-md-inline"><?php echo e($settings['company_name']); ?></span>
                <?php endif; ?>
            </a>
        </h1>
        <div class="collapse navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav pt-lg-3">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'dashboard' ? 'active' : ''; ?>" href="<?php echo url('index.php?module=dashboard'); ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-home"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_dashboard']) ? $GLOBALS['L']['nav_dashboard'] : 'Ana Sayfa'; ?></span>
                    </a>
                </li>
                
                <!-- Categories -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'categories' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#sidebar-categories" aria-expanded="<?php echo $current_module == 'categories' ? 'true' : 'false'; ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-tags"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_categories']) ? $GLOBALS['L']['nav_categories'] : 'Kategoriler'; ?></span>
                        <span class="nav-link-toggle"></span>
                    </a>
                    <div class="collapse <?php echo $current_module == 'categories' ? 'show' : ''; ?>" id="sidebar-categories">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=categories'); ?>" class="nav-link <?php echo $current_module == 'categories' && $current_action == 'index' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_all_categories']) ? $GLOBALS['L']['menu_all_categories'] : 'Tüm Kategoriler'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=categories&action=add'); ?>" class="nav-link <?php echo $current_module == 'categories' && $current_action == 'add' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_add_category']) ? $GLOBALS['L']['menu_add_category'] : 'Kategori Ekle'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=categories&action=fields'); ?>" class="nav-link <?php echo $current_module == 'categories' && $current_action == 'fields' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_dynamic_fields']) ? $GLOBALS['L']['menu_dynamic_fields'] : 'Dinamik Alanlar'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <!-- Products -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'products' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#sidebar-products" aria-expanded="<?php echo $current_module == 'products' ? 'true' : 'false'; ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-package"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_products']) ? $GLOBALS['L']['nav_products'] : 'Ürünler'; ?></span>
                        <span class="nav-link-toggle"></span>
                    </a>
                    <div class="collapse <?php echo $current_module == 'products' ? 'show' : ''; ?>" id="sidebar-products">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=products'); ?>" class="nav-link <?php echo $current_module == 'products' && $current_action == 'index' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_all_products']) ? $GLOBALS['L']['menu_all_products'] : 'Tüm Ürünler'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=products&action=add'); ?>" class="nav-link <?php echo $current_module == 'products' && $current_action == 'add' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_add_product']) ? $GLOBALS['L']['menu_add_product'] : 'Ürün Ekle'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=products&action=fields'); ?>" class="nav-link <?php echo $current_module == 'products' && $current_action == 'fields' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_dynamic_fields']) ? $GLOBALS['L']['menu_dynamic_fields'] : 'Dinamik Alanlar'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <!-- Customers -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'customers' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#sidebar-customers" aria-expanded="<?php echo $current_module == 'customers' ? 'true' : 'false'; ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_customers']) ? $GLOBALS['L']['nav_customers'] : 'Müşteriler'; ?></span>
                        <span class="nav-link-toggle"></span>
                    </a>
                    <div class="collapse <?php echo $current_module == 'customers' ? 'show' : ''; ?>" id="sidebar-customers">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=customers'); ?>" class="nav-link <?php echo $current_module == 'customers' && $current_action == 'index' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_all_customers']) ? $GLOBALS['L']['menu_all_customers'] : 'Tüm Müşteriler'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=customers&action=add'); ?>" class="nav-link <?php echo $current_module == 'customers' && $current_action == 'add' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_add_customer']) ? $GLOBALS['L']['menu_add_customer'] : 'Müşteri Ekle'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=customers&action=fields'); ?>" class="nav-link <?php echo $current_module == 'customers' && $current_action == 'fields' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_dynamic_fields']) ? $GLOBALS['L']['menu_dynamic_fields'] : 'Dinamik Alanlar'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <!-- Stock -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'stock' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#sidebar-stock" aria-expanded="<?php echo $current_module == 'stock' ? 'true' : 'false'; ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-package"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_stock']) ? $GLOBALS['L']['nav_stock'] : 'Stoklar'; ?></span>
                        <span class="nav-link-toggle"></span>
                    </a>
                    <div class="collapse <?php echo $current_module == 'stock' ? 'show' : ''; ?>" id="sidebar-stock">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=stock'); ?>" class="nav-link <?php echo $current_module == 'stock' && $current_action == 'index' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_all_stocks']) ? $GLOBALS['L']['menu_all_stocks'] : 'Tüm Stoklar'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=stock&action=add'); ?>" class="nav-link <?php echo $current_module == 'stock' && $current_action == 'add' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_add_stock']) ? $GLOBALS['L']['menu_add_stock'] : 'Stok Ekle'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=stock&action=fields'); ?>" class="nav-link <?php echo $current_module == 'stock' && $current_action == 'fields' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_dynamic_fields']) ? $GLOBALS['L']['menu_dynamic_fields'] : 'Dinamik Alanlar'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <!-- Orders -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'orders' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#sidebar-orders" aria-expanded="<?php echo $current_module == 'orders' ? 'true' : 'false'; ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-shopping-cart"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_orders']) ? $GLOBALS['L']['nav_orders'] : 'Siparişler'; ?></span>
                        <span class="nav-link-toggle"></span>
                    </a>
                    <div class="collapse <?php echo $current_module == 'orders' ? 'show' : ''; ?>" id="sidebar-orders">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=orders'); ?>" class="nav-link <?php echo $current_module == 'orders' && $current_action == 'index' && !isset($_GET['status']) ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_all_orders']) ? $GLOBALS['L']['menu_all_orders'] : 'Tüm Siparişler'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=orders&action=add'); ?>" class="nav-link <?php echo $current_module == 'orders' && $current_action == 'add' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_add_order']) ? $GLOBALS['L']['menu_add_order'] : 'Sipariş Ekle'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=orders&status=pending'); ?>" class="nav-link <?php echo $current_module == 'orders' && isset($_GET['status']) && $_GET['status'] == 'pending' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_pending_orders']) ? $GLOBALS['L']['menu_pending_orders'] : 'Bekleyen Siparişler'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=orders&status=processing'); ?>" class="nav-link <?php echo $current_module == 'orders' && isset($_GET['status']) && $_GET['status'] == 'processing' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_processing_orders']) ? $GLOBALS['L']['menu_processing_orders'] : 'İşlemdeki Siparişler'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=orders&status=completed'); ?>" class="nav-link <?php echo $current_module == 'orders' && isset($_GET['status']) && $_GET['status'] == 'completed' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_completed_orders']) ? $GLOBALS['L']['menu_completed_orders'] : 'Tamamlanan Siparişler'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=orders&status=cancelled'); ?>" class="nav-link <?php echo $current_module == 'orders' && isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_cancelled_orders']) ? $GLOBALS['L']['menu_cancelled_orders'] : 'İptal Edilen Siparişler'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <!-- Transactions -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'transactions' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#sidebar-transactions" aria-expanded="<?php echo $current_module == 'transactions' ? 'true' : 'false'; ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-currency-dollar"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_transactions']) ? $GLOBALS['L']['nav_transactions'] : 'Mali İşlemler'; ?></span>
                        <span class="nav-link-toggle"></span>
                    </a>
                    <div class="collapse <?php echo $current_module == 'transactions' ? 'show' : ''; ?>" id="sidebar-transactions">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=transactions'); ?>" class="nav-link <?php echo $current_module == 'transactions' && $current_action == 'index' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_debt_receivable_list']) ? $GLOBALS['L']['menu_debt_receivable_list'] : 'Alacak/Verecek Listesi'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=transactions&action=add-payment'); ?>" class="nav-link <?php echo $current_module == 'transactions' && $current_action == 'add-payment' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_add_payment']) ? $GLOBALS['L']['menu_add_payment'] : 'Ödeme Ekle'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=transactions&action=add-debt'); ?>" class="nav-link <?php echo $current_module == 'transactions' && $current_action == 'add-debt' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_add_debt']) ? $GLOBALS['L']['menu_add_debt'] : 'Borç Ekle'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=transactions&action=expenses'); ?>" class="nav-link <?php echo $current_module == 'transactions' && $current_action == 'expenses' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_expenses']) ? $GLOBALS['L']['menu_expenses'] : 'Dış Giderler'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=transactions&action=cash-summary'); ?>" class="nav-link <?php echo $current_module == 'transactions' && $current_action == 'cash-summary' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_cash_summary']) ? $GLOBALS['L']['menu_cash_summary'] : 'Toplam Kasa'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <!-- Tools -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'tools' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#sidebar-tools" aria-expanded="<?php echo $current_module == 'tools' ? 'true' : 'false'; ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-tool"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_tools']) ? $GLOBALS['L']['nav_tools'] : 'Araçlar'; ?></span>
                        <span class="nav-link-toggle"></span>
                    </a>
                    <div class="collapse <?php echo $current_module == 'tools' ? 'show' : ''; ?>" id="sidebar-tools">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=tools&action=reports'); ?>" class="nav-link <?php echo $current_module == 'tools' && $current_action == 'reports' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_detailed_report']) ? $GLOBALS['L']['menu_detailed_report'] : 'Detaylı Rapor'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=tools&action=calculators'); ?>" class="nav-link <?php echo $current_module == 'tools' && $current_action == 'calculators' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_calculators']) ? $GLOBALS['L']['menu_calculators'] : 'Hesaplama Araçları'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=tools&action=cache'); ?>" class="nav-link <?php echo $current_module == 'tools' && $current_action == 'cache' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_cache_clear']) ? $GLOBALS['L']['menu_cache_clear'] : 'Cache Temizleme'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=tools&action=backup'); ?>" class="nav-link <?php echo $current_module == 'tools' && $current_action == 'backup' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_system_backup']) ? $GLOBALS['L']['menu_system_backup'] : 'Sistem Yedekle'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=tools&action=import-export'); ?>" class="nav-link <?php echo $current_module == 'tools' && $current_action == 'import-export' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_import_export']) ? $GLOBALS['L']['menu_import_export'] : 'İmport/Export'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=tools&action=optimize'); ?>" class="nav-link <?php echo $current_module == 'tools' && $current_action == 'optimize' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_database_optimization']) ? $GLOBALS['L']['menu_database_optimization'] : 'Veritabanı Optimizasyonu'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=activity'); ?>" class="nav-link <?php echo $current_module == 'activity' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['nav_activity_logs']) ? $GLOBALS['L']['nav_activity_logs'] : 'Son İşlemler'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <!-- Settings -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'settings' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#sidebar-settings" aria-expanded="<?php echo $current_module == 'settings' ? 'true' : 'false'; ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-settings"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_settings']) ? $GLOBALS['L']['nav_settings'] : 'Ayarlar'; ?></span>
                        <span class="nav-link-toggle"></span>
                    </a>
                    <div class="collapse <?php echo $current_module == 'settings' ? 'show' : ''; ?>" id="sidebar-settings">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=settings&action=system'); ?>" class="nav-link <?php echo $current_module == 'settings' && $current_action == 'system' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_system_settings']) ? $GLOBALS['L']['menu_system_settings'] : 'Sistem Ayarları'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=settings&action=users'); ?>" class="nav-link <?php echo $current_module == 'settings' && $current_action == 'users' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_user_settings']) ? $GLOBALS['L']['menu_user_settings'] : 'Kullanıcı Ayarları'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=settings&action=inventory'); ?>" class="nav-link <?php echo $current_module == 'settings' && $current_action == 'inventory' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_inventory_settings']) ? $GLOBALS['L']['menu_inventory_settings'] : 'Envanter Ayarları'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=settings&action=currencies'); ?>" class="nav-link <?php echo $current_module == 'settings' && $current_action == 'currencies' ? 'active' : ''; ?>">
                                    <?php echo t('menu_currencies', 'Para Birimleri'); ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=settings&action=customer-tags'); ?>" class="nav-link <?php echo $current_module == 'settings' && $current_action == 'customer-tags' ? 'active' : ''; ?>">
                                    <?php echo t('menu_customer_tags', 'Müşteri Etiketleri'); ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <!-- Profile -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_module == 'profile' ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#sidebar-profile" aria-expanded="<?php echo $current_module == 'profile' ? 'true' : 'false'; ?>">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-user"></i></span>
                        <span class="nav-link-title"><?php echo isset($GLOBALS['L']['nav_profile']) ? $GLOBALS['L']['nav_profile'] : 'Profil'; ?></span>
                        <span class="nav-link-toggle"></span>
                    </a>
                    <div class="collapse <?php echo $current_module == 'profile' ? 'show' : ''; ?>" id="sidebar-profile">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=profile'); ?>" class="nav-link <?php echo $current_module == 'profile' && $current_action == 'index' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_user_profile']) ? $GLOBALS['L']['menu_user_profile'] : 'Kullanıcı Profili'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('index.php?module=profile&action=change-password'); ?>" class="nav-link <?php echo $current_module == 'profile' && $current_action == 'change-password' ? 'active' : ''; ?>">
                                    <?php echo isset($GLOBALS['L']['menu_change_password']) ? $GLOBALS['L']['menu_change_password'] : 'Şifre Değiştir'; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo url('logout.php'); ?>" class="nav-link">
                                    <?php echo isset($GLOBALS['L']['nav_logout']) ? $GLOBALS['L']['nav_logout'] : 'Çıkış'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</aside>
