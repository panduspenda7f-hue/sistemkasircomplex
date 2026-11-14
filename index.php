<?php
declare(strict_types=1);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

// 1. Omzet & Jumlah Transaksi Hari Ini
$stmtToday = $pdo->query("
    SELECT
        COALESCE(SUM(grand_total), 0) AS total_omzet,
        COUNT(*) AS total_transaksi
    FROM transactions
    WHERE DATE(created_at) = CURDATE()
");
$todayStats = $stmtToday->fetch();

// 2. Jumlah Produk Aktif
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

// 3. Produk Stok Menipis
$stmtLowStock = $pdo->query("
    SELECT p.id, p.sku, p.name, p.unit, p.stok, p.stok_minimum, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1 AND p.stok <= p.stok_minimum
    ORDER BY p.stok ASC, p.name ASC
    LIMIT 8
");
$lowStockProducts = $stmtLowStock->fetchAll();

// 4. Data Grafik Penjualan 7 Hari Terakhir
$chartLabels = [];
$chartTotals = [];
$salesMap = [];

$stmtChart = $pdo->query("
    SELECT DATE(created_at) AS tgl, COALESCE(SUM(grand_total), 0) AS omzet
    FROM transactions
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY tgl ASC
");
foreach ($stmtChart->fetchAll() as $row) {
    $salesMap[$row['tgl']] = (float)$row['omzet'];
}

for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('d M', strtotime($dateKey));
    $chartTotals[] = $salesMap[$dateKey] ?? 0;
}

// 5. Daftar Transaksi Terbaru
$stmtRecent = $pdo->query("
    SELECT t.id, t.invoice_number, t.grand_total, t.payment_method, t.created_at,
           u.name AS cashier_name, COALESCE(c.name, 'Umum') AS customer_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN customers c ON t.customer_id = c.id
    ORDER BY t.created_at DESC
    LIMIT 8
");
$recentTransactions = $stmtRecent->fetchAll();
?>

<!-- Ringkasan KPI -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm stat-card border-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Omzet Hari Ini</div>
                        <h4 class="fw-bold mb-0 mt-1 text-primary"><?= formatRupiah($todayStats['total_omzet']) ?></h4>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded p-3">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm stat-card border-success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Transaksi Hari Ini</div>
                        <h4 class="fw-bold mb-0 mt-1 text-success"><?= formatNumber($todayStats['total_transaksi']) ?> Transaksi</h4>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded p-3">
                        <i class="bi bi-receipt-cutoff fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm stat-card border-info h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Produk</div>
                        <h4 class="fw-bold mb-0 mt-1 text-dark"><?= formatNumber($totalProducts) ?> Item</h4>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info rounded p-3">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm stat-card border-danger h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Stok Menipis</div>
                        <h4 class="fw-bold mb-0 mt-1 text-danger"><?= formatNumber($lowStockCount) ?> Produk</h4>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger rounded p-3">
                        <i class="bi bi-exclamation-octagon fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Penjualan & Informasi Stok Menipis -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Grafik Penjualan (7 Hari Terakhir)</h6>
                <span class="badge bg-light text-dark border">Update Real-time</span>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="130"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Peringatan Stok Menipis</h6>
                <?php if (hasRole('admin')): ?>
                    <a href="stock.php" class="btn btn-outline-primary btn-sm">Kelola Stok</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockProducts)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle text-success fs-2 d-block mb-2"></i>
                        Semua stok produk dalam kondisi aman.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-center">Sisa</th>
                                    <th class="text-center">Min</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= e($item['name']) ?></div>
                                            <div class="text-muted" style="font-size:0.75rem;"><?= e($item['sku']) ?> &bull; <?= e($item['category_name']) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?= (int)$item['stok'] ?> <?= e($item['unit']) ?></span>
                                        </td>
                                        <td class="text-center text-muted"><?= (int)$item['stok_minimum'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Transaksi Terbaru -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Transaksi Terbaru</h6>
        <a href="transactions.php" class="btn btn-sm btn-outline-secondary">Lihat Semua</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>No. Invoice</th>
                        <th>Waktu</th>
                        <th>Pelanggan</th>
                        <th>Kasir</th>
                        <th>Metode</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if (empty($recentTransactions)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada transaksi penjualan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTransactions as $trx): ?>
                            <tr>
                                <td class="fw-semibold text-primary"><?= e($trx['invoice_number']) ?></td>
                                <td><?= formatDateTime($trx['created_at']) ?></td>
                                <td><?= e($trx['customer_name']) ?></td>
                                <td><?= e($trx['cashier_name']) ?></td>
                                <td><span class="badge bg-secondary text-uppercase"><?= e($trx['payment_method']) ?></span></td>
                                <td class="text-end fw-bold"><?= formatRupiah($trx['grand_total']) ?></td>
                                <td class="text-center">
                                    <a href="receipt.php?id=<?= (int)$trx['id'] ?>" class="btn btn-sm btn-outline-dark" title="Cetak Struk">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('salesChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Omzet Penjualan (Rp)',
            data: <?= json_encode($chartTotals) ?>,
            backgroundColor: 'rgba(13, 110, 253, 0.75)',
            borderColor: 'rgb(13, 110, 253)',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context) => 'Omzet: ' + formatRupiahJS(context.raw)
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value) => 'Rp ' + Number(value).toLocaleString('id-ID')
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
