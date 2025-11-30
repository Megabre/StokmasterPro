<?php
/**
 * Megabre StokMaster Pro
 * Toplam Kasa / Kar-Zarar Raporu
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

// Get date range filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Calculate income (Gelirler)
// 1. Müşterilerden alınan ödemeler (tarih aralığına göre)
$db->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
            WHERE type = 'payment' AND date >= :start_date AND date <= :end_date");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
$paymentIncome = $db->single()['total'];

// 2. Müşterilerin bize olan TOPLAM borçları (alacaklarımız)
// Her müşterinin toplam borçlarını ve ödemelerini hesapla, net borçları topla
$db->query("SELECT 
            customer_id,
            COALESCE(SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END), 0) as total_debts,
            COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) as total_payments
            FROM transactions
            GROUP BY customer_id
            HAVING (total_debts - total_payments) > 0");
$customerDebtsList = $db->resultSet();
$customerReceivables = 0;
foreach ($customerDebtsList as $customerDebt) {
    $netDebt = $customerDebt['total_debts'] - $customerDebt['total_payments'];
    if ($netDebt > 0) {
        $customerReceivables += $netDebt;
    }
}

// Total Income = Müşteri ödemeleri + Müşteri alacakları
$totalIncome = $paymentIncome + $customerReceivables;

// Calculate expenses (Giderler)
// 1. Dış giderler
$db->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
            WHERE date >= :start_date AND date <= :end_date");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
$externalExpenses = $db->single()['total'];

// Total Expenses
$totalExpenses = $externalExpenses;

// Calculate profit/loss
$profitLoss = $totalIncome - $totalExpenses;

// Get detailed breakdown by category
$db->query("SELECT category, COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE date >= :start_date AND date <= :end_date
            GROUP BY category
            ORDER BY total DESC");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
$expensesByCategory = $db->resultSet();

// Get payment methods breakdown
$db->query("SELECT payment_method, COALESCE(SUM(amount), 0) as total 
            FROM transactions 
            WHERE type = 'payment' AND date >= :start_date AND date <= :end_date
            GROUP BY payment_method");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
$paymentsByMethod = $db->resultSet();

// Get monthly trend (last 6 months)
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    
    // Income
    $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                WHERE type = 'payment' AND date >= :start_date AND date <= :end_date");
    $db->bind(':start_date', $monthStart);
    $db->bind(':end_date', $monthEnd);
    $monthIncome = $db->single()['total'];
    
    // Expenses
    $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                WHERE date >= :start_date AND date <= :end_date");
    $db->bind(':start_date', $monthStart);
    $db->bind(':end_date', $monthEnd);
    $monthExpenses = $db->single()['total'];
    
    $monthlyData[] = [
        'month' => $monthName,
        'income' => $monthIncome,
        'expenses' => $monthExpenses,
        'profit' => $monthIncome - $monthExpenses
    ];
}

// Include header
include_once INCLUDES_PATH . 'header.php';

// Show success/error messages
if (Session::hasFlash('success')) {
    $flash = Session::getFlash('success');
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . $flash['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}

if (Session::hasFlash('error')) {
    $flash = Session::getFlash('error');
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            ' . $flash['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('cash_summary_title', 'Toplam Kasa'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=transactions'); ?>"><?php echo t('transactions_title', 'Mali İşlemler'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('cash_summary_title', 'Toplam Kasa'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Date Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end" method="get" action="<?php echo url('index.php'); ?>">
            <input type="hidden" name="module" value="transactions">
            <input type="hidden" name="action" value="cash-summary">
            
            <div class="col-md-3">
                <label for="start_date" class="form-label"><?php echo t('transactions_from_date', 'Başlangıç Tarihi'); ?></label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
            </div>
            
            <div class="col-md-3">
                <label for="end_date" class="form-label"><?php echo t('transactions_to_date', 'Bitiş Tarihi'); ?></label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> <?php echo t('filter', 'Filtrele'); ?>
                </button>
            </div>
            
            <div class="col-md-2">
                <a href="<?php echo url('index.php?module=transactions&action=cash-summary'); ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-sync"></i> <?php echo t('filter', 'Sıfırla'); ?>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row">
    <!-- Customer Payments -->
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2"><?php echo t('cash_summary_customer_payments', 'Müşteri Ödemeleri'); ?></h6>
                        <h3 class="mb-0 text-success"><?php echo formatPrice($paymentIncome); ?> ₺</h3>
                        <small class="text-muted"><?php echo t('cash_summary_in_period', 'Dönem içinde alınan ödemeler'); ?></small>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Customer Receivables (Debts) -->
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2"><?php echo t('cash_summary_customer_receivables', 'Müşteri Alacakları (Borçlar)'); ?></h6>
                        <h3 class="mb-0 text-info"><?php echo formatPrice($customerReceivables); ?> ₺</h3>
                        <small class="text-muted"><?php echo t('cash_summary_total_debts', 'Müşterilerin bize olan toplam borçları'); ?></small>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-hand-holding-usd fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Income -->
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2"><?php echo t('cash_summary_total_income', 'Toplam Gelir'); ?></h6>
                        <h3 class="mb-0 text-primary"><?php echo formatPrice($totalIncome); ?> ₺</h3>
                        <small class="text-muted"><?php echo t('cash_summary_total_income_desc', 'Ödemeler + Alacaklar'); ?></small>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-arrow-up fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Expenses -->
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2"><?php echo t('cash_summary_total_expenses', 'Toplam Gider'); ?></h6>
                        <h3 class="mb-0 text-danger"><?php echo formatPrice($totalExpenses); ?> ₺</h3>
                        <small class="text-muted"><?php echo t('cash_summary_external_expenses', 'Dış giderler'); ?></small>
                    </div>
                    <div class="text-danger">
                        <i class="fas fa-arrow-down fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profit/Loss and Net Cash -->
<div class="row mt-3">
    <!-- Profit/Loss -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2"><?php echo t('cash_summary_profit_loss', 'Kar / Zarar'); ?></h6>
                        <h3 class="mb-0 <?php echo $profitLoss >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatPrice(abs($profitLoss)); ?> ₺
                        </h3>
                        <small class="<?php echo $profitLoss >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $profitLoss >= 0 ? t('transactions_profit', 'Kâr') : t('transactions_loss', 'Zarar'); ?>
                        </small>
                    </div>
                    <div class="<?php echo $profitLoss >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-<?php echo $profitLoss >= 0 ? 'check-circle' : 'times-circle'; ?> fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Net Cash -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2"><?php echo t('cash_summary_net_cash', 'Net Kasa'); ?></h6>
                        <h3 class="mb-0 text-primary"><?php echo formatPrice($totalIncome - $totalExpenses); ?> ₺</h3>
                        <small class="text-muted"><?php echo t('cash_summary_net_cash_desc', 'Gelir - Gider'); ?></small>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-wallet fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Details -->
<div class="row mt-4">
    <!-- Monthly Trend Chart -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('cash_summary_monthly_trend', 'Aylık Trend'); ?></h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyTrendChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Expenses by Category -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('cash_summary_expenses_by_category', 'Kategoriye Göre Giderler'); ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($expensesByCategory)): ?>
                <div class="list-group">
                    <?php foreach ($expensesByCategory as $cat): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?php echo e($cat['category']); ?></span>
                        <span class="badge bg-danger"><?php echo formatPrice($cat['total']); ?> ₺</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center"><?php echo t('ui_no_data_found', 'Kayıt bulunamadı.'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payment Methods Breakdown -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('cash_summary_payments_by_method', 'Ödeme Yöntemine Göre Gelirler'); ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($paymentsByMethod)): ?>
                <canvas id="paymentMethodsChart" height="150"></canvas>
                <?php else: ?>
                <p class="text-muted text-center"><?php echo t('ui_no_data_found', 'Kayıt bulunamadı.'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Help & Tips -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><?php echo t('cash_summary_help_tips', 'Yardım & İpuçları'); ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <h6><?php echo t('cash_summary_title', 'Toplam Kasa'); ?></h6>
                    <ul class="mb-0">
                        <li><?php echo t('cash_summary_help_tip1', 'Bu sayfa tüm gelir ve giderleri karşılaştırarak kar/zarar durumunu gösterir.'); ?></li>
                        <li><?php echo t('cash_summary_help_tip2', 'Gelirler: Müşterilerden alınan ödemeler ve müşterilerin bize olan toplam borçları (alacaklarımız).'); ?></li>
                        <li><?php echo t('cash_summary_help_tip3', 'Giderler: Dış giderler (elektrik, su, kira vb.).'); ?></li>
                        <li><?php echo t('cash_summary_help_tip4', 'Tarih aralığı seçerek belirli dönemlere göre rapor alabilirsiniz.'); ?></li>
                        <li><?php echo t('cash_summary_help_tip5', 'Müşteri alacakları: Müşterilerin bize olan toplam borçları (ödenmemiş borçlar).'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Monthly Trend Chart
    var monthlyData = <?php echo json_encode($monthlyData); ?>;
    var ctx1 = document.getElementById('monthlyTrendChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: monthlyData.map(d => d.month),
            datasets: [
                {
                    label: '<?php echo t('cash_summary_total_income', 'Gelir'); ?>',
                    data: monthlyData.map(d => d.income),
                    borderColor: 'rgb(40, 167, 69)',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                },
                {
                    label: '<?php echo t('cash_summary_total_expenses', 'Gider'); ?>',
                    data: monthlyData.map(d => d.expenses),
                    borderColor: 'rgb(220, 53, 69)',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                },
                {
                    label: '<?php echo t('cash_summary_profit_loss', 'Kar/Zarar'); ?>',
                    data: monthlyData.map(d => d.profit),
                    borderColor: 'rgb(0, 123, 255)',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Payment Methods Chart
    <?php if (!empty($paymentsByMethod)): ?>
    var paymentMethods = <?php echo json_encode($paymentsByMethod); ?>;
    var ctx2 = document.getElementById('paymentMethodsChart').getContext('2d');
    
    var paymentMethodNames = {
        'cash': '<?php echo t('transactions_cash', 'Nakit'); ?>',
        'check': '<?php echo t('transactions_check', 'Çek'); ?>',
        'promissory_note': '<?php echo t('transactions_promissory', 'Senet'); ?>',
        'credit_card': '<?php echo t('transactions_credit_card', 'Kredi Kartı'); ?>',
        'bank_transfer': '<?php echo t('transactions_bank_transfer', 'Havale / EFT'); ?>'
    };
    
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: paymentMethods.map(m => paymentMethodNames[m.payment_method] || m.payment_method),
            datasets: [{
                data: paymentMethods.map(m => m.total),
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(0, 123, 255, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(220, 53, 69, 0.8)',
                    'rgba(108, 117, 125, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });
    <?php endif; ?>
});
</script>

<?php
include_once INCLUDES_PATH . 'footer.php';
?>

