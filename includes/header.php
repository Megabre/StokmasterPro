<?php
/**
 * Megabre StokMaster Pro
 * Header Template
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Get current page
$current_page = basename($_SERVER['SCRIPT_NAME']);

// Get module from URL
$module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';

// Get page title
$page_title = APP_NAME;
switch ($module) {
    case 'dashboard':
        $page_title .= ' - Ana Sayfa';
        break;
    case 'categories':
        $page_title .= ' - Kategoriler';
        break;
    case 'products':
        $page_title .= ' - Ürünler';
        break;
    case 'customers':
        $page_title .= ' - Müşteriler';
        break;
    case 'stock':
        $page_title .= ' - Stok Yönetimi';
        break;
    case 'orders':
        $page_title .= ' - Siparişler';
        break;
    case 'transactions':
        $page_title .= ' - Mali İşlemler';
        break;
    case 'tools':
        $page_title .= ' - Araçlar';
        break;
    case 'settings':
        $page_title .= ' - Ayarlar';
        break;
    case 'profile':
        $page_title .= ' - Profil';
        break;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?php echo asset('img/favicon.png'); ?>" type="image/png">
    
    <!-- Tabler CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler-flags.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler-payments.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler-vendors.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- ApexCharts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.css">
    
    <!-- Custom CSS -->
    <link href="<?php echo asset('css/style.css'); ?>" rel="stylesheet">
    <?php if ($module == 'dashboard'): ?>
    <link href="<?php echo asset('css/dashboard.css'); ?>" rel="stylesheet">
    <?php endif; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- jQuery UI -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <!-- Tabler JS -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>

    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>

    <!-- Main JS -->
    <script src="<?php echo asset('js/main.js'); ?>"></script>
</head>
<body class="theme-light">
    <div class="page">
        <!-- Header -->
        <header class="navbar navbar-expand-md d-print-none">
            <div class="container-xl">
                <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
                    <?php echo APP_NAME; ?>
                </h1>
                <div class="navbar-nav flex-row order-md-last">
                    <!-- Language Dropdown -->
                    <div class="nav-item dropdown d-none d-md-flex me-3">
                        <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1" aria-label="Dil">
                            <i class="ti ti-language"></i>
                            <span class="d-none d-md-inline ms-2">
                                <?php 
                                $current_lang = 'tr';
                                if (!empty($current_user['language'])) {
                                    $current_lang = $current_user['language'];
                                } elseif (isset($GLOBALS['language'])) {
                                    $current_lang = $GLOBALS['language']->getCurrentLanguage();
                                } elseif (Session::exists('language')) {
                                    $current_lang = Session::get('language');
                                }
                                $lang_names = ['tr' => 'Türkçe', 'en' => 'English'];
                                echo isset($lang_names[$current_lang]) ? $lang_names[$current_lang] : 'TR';
                                ?>
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-card">
                            <div class="card">
                                <div class="list-group list-group-flush">
                                    <?php 
                                    $available_langs = ['tr' => ['code' => 'tr', 'native_name' => 'Türkçe'], 'en' => ['code' => 'en', 'native_name' => 'English']];
                                    if (isset($GLOBALS['language'])) {
                                        $available_langs = $GLOBALS['language']->getAvailableLanguages();
                                    }
                                    foreach ($available_langs as $lang_code => $lang_info): 
                                        $queryString = $_SERVER['QUERY_STRING'] ?? '';
                                        parse_str($queryString, $params);
                                        $params['language'] = $lang_code;
                                        $scriptName = basename($_SERVER['SCRIPT_NAME']);
                                        $cleanQuery = http_build_query($params);
                                        $url = $scriptName;
                                        if (!empty($cleanQuery)) {
                                            $url .= '?' . $cleanQuery;
                                        }
                                    ?>
                                    <a href="<?php echo url($url); ?>" class="list-group-item list-group-item-action <?php echo $current_lang == $lang_code ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($lang_info['native_name']); ?>
                                        <?php if ($current_lang == $lang_code): ?>
                                        <i class="ti ti-check ms-auto"></i>
                                        <?php endif; ?>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Dropdown -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Kullanıcı menüsü">
                            <span class="avatar avatar-sm" style="background-image: url(<?php echo asset('img/logo-small.png'); ?>)"></span>
                            <div class="d-none d-xl-block ps-2">
                                <div><?php echo $current_user['name']; ?></div>
                                <div class="mt-1 small text-muted"><?php echo isset($current_user['role']) ? $current_user['role'] : 'Kullanıcı'; ?></div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a href="<?php echo url('index.php?module=profile'); ?>" class="dropdown-item">
                                <i class="ti ti-user"></i> <?php echo isset($GLOBALS['L']['profile_my_profile']) ? $GLOBALS['L']['profile_my_profile'] : 'Profilim'; ?>
                            </a>
                            <a href="<?php echo url('index.php?module=profile&action=change-password'); ?>" class="dropdown-item">
                                <i class="ti ti-key"></i> <?php echo isset($GLOBALS['L']['profile_change_password']) ? $GLOBALS['L']['profile_change_password'] : 'Şifre Değiştir'; ?>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo url('logout.php'); ?>" class="dropdown-item">
                                <i class="ti ti-logout"></i> <?php echo isset($GLOBALS['L']['nav_logout']) ? $GLOBALS['L']['nav_logout'] : 'Çıkış'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Sidebar -->
        <?php include_once INCLUDES_PATH . 'sidebar.php'; ?>
        
        <!-- Page Wrapper -->
        <div class="page-wrapper">
            <!-- Page Body -->
            <div class="page-body">
                <div class="container-xl">
                    <!-- Flash Messages -->
                    <?php echo getFlashMessages(); ?>