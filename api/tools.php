<?php
/**
 * Megabre StokMaster Pro
 * API Tools
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Include required files
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/core/Session.php';
require_once BASE_PATH . '/core/Authentication.php';
require_once BASE_PATH . '/core/helpers.php';

// Check if user is logged in
$auth = new Authentication();
if (!$auth->isLoggedIn()) {
    die('Unauthorized access');
}

// Initialize database connection
$db = Database::getInstance();

// Get request parameters
$action = isset($_GET['action']) ? $_GET['action'] : '';
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Handle export report action
if ($action === 'export_report') {
    try {
        // Set headers for Excel download
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="rapor_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: max-age=0');
        
        // Get report data based on type
        switch ($reportType) {
            case 'sales':
                require_once BASE_PATH . '/modules/tools/reports.php';
                $reportData = getSalesData($startDate, $endDate);
                
                // Create Excel content
                echo '<table border="1">';
                echo '<tr><th colspan="4">Satış Raporu (' . date('d.m.Y', strtotime($startDate)) . ' - ' . date('d.m.Y', strtotime($endDate)) . ')</th></tr>';
                
                // Summary
                echo '<tr><th colspan="4">Özet Bilgiler</th></tr>';
                echo '<tr><td>Toplam Ürün</td><td>' . $reportData['totals']['total_products'] . '</td>';
                echo '<td>Toplam Müşteri</td><td>' . $reportData['totals']['total_customers'] . '</td></tr>';
                echo '<tr><td>Toplam Sipariş</td><td>' . $reportData['totals']['total_orders'] . '</td>';
                echo '<td>Toplam Gelir</td><td>' . number_format($reportData['totals']['total_income'], 2) . ' ₺</td></tr>';
                
                // Top Products
                echo '<tr><th colspan="4">En Çok Satan Ürünler</th></tr>';
                echo '<tr><th>Ürün</th><th>Kategori</th><th>Miktar</th><th>Tutar</th></tr>';
                foreach ($reportData['top_products'] as $product) {
                    echo '<tr>';
                    echo '<td>' . $product['name'] . '</td>';
                    echo '<td>' . $product['category'] . '</td>';
                    echo '<td>' . $product['quantity_sold'] . '</td>';
                    echo '<td>' . number_format($product['total_amount'], 2) . ' ₺</td>';
                    echo '</tr>';
                }
                
                // Top Customers
                echo '<tr><th colspan="4">En Çok Alışveriş Yapan Müşteriler</th></tr>';
                echo '<tr><th>Müşteri</th><th>Firma</th><th>Sipariş</th><th>Tutar</th></tr>';
                foreach ($reportData['top_customers'] as $customer) {
                    echo '<tr>';
                    echo '<td>' . $customer['first_name'] . ' ' . $customer['last_name'] . '</td>';
                    echo '<td>' . ($customer['company'] ?? '-') . '</td>';
                    echo '<td>' . $customer['order_count'] . '</td>';
                    echo '<td>' . number_format($customer['total_amount'], 2) . ' ₺</td>';
                    echo '</tr>';
                }
                
                // Sales by Category
                echo '<tr><th colspan="5">Kategori Bazlı Satışlar</th></tr>';
                echo '<tr><th>Kategori</th><th>Ürün Adedi</th><th>Satış Miktarı</th><th>Toplam Satış</th><th>Yüzde</th></tr>';
                $totalAmount = 0;
                foreach ($reportData['sales_by_category'] as $category) {
                    $totalAmount += $category['total_amount'];
                }
                foreach ($reportData['sales_by_category'] as $category) {
                    echo '<tr>';
                    echo '<td>' . $category['category'] . '</td>';
                    echo '<td>' . $category['item_count'] . '</td>';
                    echo '<td>' . $category['quantity'] . '</td>';
                    echo '<td>' . number_format($category['total_amount'], 2) . ' ₺</td>';
                    echo '<td>' . number_format(($category['total_amount'] / $totalAmount) * 100, 2) . '%</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
                break;
                
            case 'inventory':
                // Inventory report export
                require_once BASE_PATH . '/modules/tools/reports.php';
                $reportData = getInventoryData();
                
                echo '<table border="1">';
                echo '<tr><th colspan="5">Stok Raporu</th></tr>';
                
                // Summary
                echo '<tr><th colspan="5">Stok Özeti</th></tr>';
                echo '<tr><td>Toplam Ürün</td><td>' . $reportData['stock_summary']['total_products'] . '</td>';
                echo '<td>Kritik Stok</td><td>' . $reportData['stock_summary']['low_stock'] . '</td>';
                echo '<td>Stokta Olmayan</td><td>' . $reportData['stock_summary']['out_of_stock'] . '</td></tr>';
                
                // Low Stock Products
                echo '<tr><th colspan="5">Kritik Seviyedeki Ürünler</th></tr>';
                echo '<tr><th>Ürün</th><th>Kategori</th><th>Mevcut Stok</th><th>Min. Stok</th><th>Eksik Miktar</th></tr>';
                foreach ($reportData['low_stock'] as $product) {
                    echo '<tr>';
                    echo '<td>' . $product['name'] . '</td>';
                    echo '<td>' . $product['category'] . '</td>';
                    echo '<td>' . $product['stock_level'] . '</td>';
                    echo '<td>' . $product['min_stock_level'] . '</td>';
                    echo '<td>' . $product['missing_quantity'] . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
                break;
                
            default:
                die('Invalid report type');
        }
        
    } catch (Exception $e) {
        die('Error: ' . $e->getMessage());
    }
    exit;
}

// If no valid action is provided
die('Invalid action'); 