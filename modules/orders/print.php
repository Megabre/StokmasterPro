<?php
/**
 * Megabre StokMaster Pro
 * Print Order
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

// Get order ID from URL
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId <= 0) {
    Session::setFlash('error', 'Geçersiz sipariş ID\'si.');
    redirect('index.php?module=orders');
}

// Get order details
$db->query("
    SELECT o.*, 
           CONCAT(c.first_name, ' ', c.last_name) as customer_name,
           c.company as customer_company,
           c.phone as customer_phone,
           c.email as customer_email,
           c.address as customer_address,
           DATE_FORMAT(o.order_date, '%d.%m.%Y') as formatted_date,
           DATE_FORMAT(o.created_at, '%d.%m.%Y %H:%i') as created_date,
           DATE_FORMAT(o.updated_at, '%d.%m.%Y %H:%i') as updated_date
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.id = :id
");
$db->bind(':id', $orderId);
$order = $db->single();

if (!$order) {
    Session::setFlash('error', 'Sipariş bulunamadı.');
    redirect('index.php?module=orders');
}

// Get order items
$db->query("
    SELECT oi.*, p.name as product_name, p.sku, p.barcode, c.name as category_name,
           (oi.quantity * oi.unit_price) as total
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = :order_id
");
$db->bind(':order_id', $orderId);
$items = $db->resultSet();

// Calculate totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += ($item['quantity'] * $item['unit_price']);
}
$order['subtotal'] = $subtotal;

// Get status label
$statusLabels = [
    'pending' => ['class' => 'warning', 'text' => 'Bekleyen'],
    'processing' => ['class' => 'info', 'text' => 'İşlemde'],
    'completed' => ['class' => 'success', 'text' => 'Tamamlandı'],
    'cancelled' => ['class' => 'danger', 'text' => 'İptal']
];
$status = $statusLabels[$order['status']] ?? ['class' => 'secondary', 'text' => 'Bilinmeyen'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h2 {
            font-size: 16px;
            margin: 0 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo APP_NAME; ?></h1>
        <p>Sipariş Detayı</p>
    </div>

    <div class="info-section">
        <h2>Sipariş Bilgileri</h2>
        <table>
            <tr>
                <td width="150"><strong>Sipariş No:</strong></td>
                <td>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                <td width="150"><strong>Sipariş Tarihi:</strong></td>
                <td><?php echo $order['formatted_date']; ?></td>
            </tr>
            <tr>
                <td><strong>Durum:</strong></td>
                <td><?php echo $status['text']; ?></td>
                <td><strong>Oluşturma:</strong></td>
                <td><?php echo $order['created_date']; ?></td>
            </tr>
        </table>
    </div>

    <div class="info-section">
        <h2>Müşteri Bilgileri</h2>
        <table>
            <tr>
                <td width="150"><strong>Müşteri:</strong></td>
                <td><?php echo e($order['customer_name']); ?></td>
            </tr>
            <?php if (!empty($order['customer_company'])): ?>
            <tr>
                <td><strong>Firma:</strong></td>
                <td><?php echo e($order['customer_company']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Telefon:</strong></td>
                <td><?php echo formatPhone($order['customer_phone']); ?></td>
            </tr>
            <?php if (!empty($order['customer_email'])): ?>
            <tr>
                <td><strong>E-posta:</strong></td>
                <td><?php echo e($order['customer_email']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($order['customer_address'])): ?>
            <tr>
                <td><strong>Adres:</strong></td>
                <td><?php echo nl2br(e($order['customer_address'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="info-section">
        <h2>Sipariş Kalemleri</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ürün</th>
                    <th>SKU/Barkod</th>
                    <th class="text-right">Miktar</th>
                    <th class="text-right">Birim Fiyat</th>
                    <th class="text-right">Toplam</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td class="text-center"><?php echo $index + 1; ?></td>
                    <td><?php echo e($item['product_name']); ?></td>
                    <td>
                        <?php if (!empty($item['sku'])): ?>
                            SKU: <?php echo e($item['sku']); ?>
                        <?php endif; ?>
                        <?php if (!empty($item['barcode'])): ?>
                            <br>Barkod: <?php echo e($item['barcode']); ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?php echo number_format($item['quantity'], 2); ?></td>
                    <td class="text-right"><?php echo formatPrice($item['unit_price']); ?> ₺</td>
                    <td class="text-right"><?php echo formatPrice($item['total']); ?> ₺</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="5" class="text-right"><strong>Ara Toplam:</strong></td>
                    <td class="text-right"><?php echo formatPrice($order['subtotal']); ?> ₺</td>
                </tr>
                <?php if ($order['vat_rate'] > 0): ?>
                <tr>
                    <td colspan="5" class="text-right">KDV (%<?php echo $order['vat_rate']; ?>):</td>
                    <td class="text-right"><?php echo formatPrice($order['vat_amount']); ?> ₺</td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="5" class="text-right"><strong>Genel Toplam:</strong></td>
                    <td class="text-right"><?php echo formatPrice($order['total_amount']); ?> ₺</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if (!empty($order['notes'])): ?>
    <div class="info-section">
        <h2>Sipariş Notu</h2>
        <p><?php echo nl2br(e($order['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p>Bu belge <?php echo date('d.m.Y H:i'); ?> tarihinde <?php echo APP_NAME; ?> tarafından oluşturulmuştur.</p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()">Yazdır</button>
        <button onclick="window.close()">Kapat</button>
    </div>

    <script>
        // Sayfa yüklendiğinde otomatik yazdırma penceresini aç
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html> 