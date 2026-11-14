<?php
declare(strict_types=1);

$pageTitle = 'Laporan & Analisis';
require_once __DIR__ . '/includes/header.php';
requireRole(['admin']);

$pdo = getDB();

$type      = $_GET['type'] ?? 'sales';
$period    = $_GET['period'] ?? 'custom';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

if ($period === 'today') {
    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d');
} elseif ($period === 'month') {
    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-t');
}

$rows = [];
$summary = [];

if ($type === 'sales') {
    $stmt = $pdo->prepare('
        SELECT t.*, u.name AS cashier_name, COALESCE(c.name, "Umum") AS customer_name
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE DATE(t.created_at) BETWEEN ? AND ?
        ORDER BY t.created_at DESC
    ');
    $stmt->execute([$startDate, $endDate]);
    $rows = $stmt->fetchAll();

    $summary = [
        'count'    => count($rows),
        'subtotal' => array_sum(array_column($rows, 'subtotal')),
        'discount' => array_sum(array_column($rows, 'discount_amount')),
        'total'    => array_sum(array_column($rows, 'grand_total')),
    ];
} elseif ($type === 'profit') {
    $stmt = $pdo->prepare('
        SELECT
            ti.sku,
            ti.product_name,
            ti.unit,
            SUM(ti.qty) AS total_qty,
            SUM(ti.harga_modal * ti.qty) AS total_modal,
            SUM(ti.subtotal) AS total_penjualan,
            SUM(ti.subtotal - (ti.harga_modal * ti.qty)) AS laba_kotor
        FROM transaction_items ti
        JOIN transactions t ON ti.transaction_id = t.id
        WHERE DATE(t.created_at) BETWEEN ? AND ?
        GROUP BY ti.product_id, ti.sku, ti.product_name, ti.unit
        ORDER BY laba_kotor DESC
    ');
    $stmt->execute([$startDate, $endDate]);
    $rows = $stmt->fetchAll();

    $summary = [
        'penjualan' => array_sum(array_column($rows, 'total_penjualan')),
        'modal'     => array_sum(array_column($rows, 'total_modal')),
        'laba'      => array_sum(array_column($rows, 'laba_kotor')),
    ];
} elseif ($type === 'bestseller') {
    $stmt = $pdo->prepare('
        SELECT
            ti.sku,
            ti.product_name,
            ti.unit,
            SUM(ti.qty) AS total_qty,
            SUM(ti.subtotal) AS total_omzet
        FROM transaction_items ti
        JOIN transactions t ON ti.transaction_id = t.id
        WHERE DATE(t.created_at) BETWEEN ? AND ?
        GROUP BY ti.product_id, ti.sku, ti.product_name, ti.unit
        ORDER BY total_qty DESC, total_omzet DESC
    ');
    $stmt->execute([$startDate, $endDate]);
    $rows = $stmt->fetchAll();
} elseif ($type === 'inventory') {
    $rows = $pdo->query('
        SELECT p.*, c.name AS category_name,
               (p.stok * p.harga_modal) AS nilai_modal,
               (p.stok * p.harga_jual) AS nilai_jual
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        ORDER BY p.stok ASC, p.name ASC
    ')->fetchAll();

    $summary = [
        'total_items' => count($rows),
        'total_stok'  => array_sum(array_column($rows, 'stok')),
        'nilai_modal' => array_sum(array_column($rows, 'nilai_modal')),
        'nilai_jual'  => array_sum(array_column($rows, 'nilai_jual')),
    ];
}

$exportParams = http_build_query([
    'type'       => $type,
    'start_date' => $startDate,
    'end_date'   => $endDate,
]);
?>

<!-- Tab Jenis Laporan -->
<ul class="nav nav-pills mb-3 gap-2">
    <li class="nav-item">
        <a class="nav-link <?= $type === 'sales' ? 'active' : 'bg-white border' ?>" href="?type=sales&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">
            <i class="bi bi-receipt me-1"></i> Penjualan &amp; Transaksi
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $type === 'profit' ? 'active' : 'bg-white border' ?>" href="?type=profit&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">
            <i class="bi bi-cash-coin me-1"></i> Keuntungan Kotor
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $type === 'bestseller' ? 'active' : 'bg-white border' ?>" href="?type=bestseller&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">
            <i class="bi bi-trophy me-1"></i> Produk Terlaris
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $type === 'inventory' ? 'active' : 'bg-white border' ?>" href="?type=inventory">
            <i class="bi bi-boxes me-1"></i> Persediaan Barang
        </a>
    </li>
</ul>

<!-- Filter & Tombol Ekspor -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <?php if ($type !== 'inventory'): ?>
                <form method="GET" class="col-lg-8 row g-2 align-items-end">
                    <input type="hidden" name="type" value="<?= e($type) ?>">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
                    </div>
                    <div class="col-md-6 d-flex flex-wrap gap-1">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Tampilkan</button>
                        <a href="?type=<?= e($type) ?>&period=today" class="btn btn-outline-secondary btn-sm">Harian (Hari Ini)</a>
                        <a href="?type=<?= e($type) ?>&period=month" class="btn btn-outline-secondary btn-sm">Bulanan (Bulan Ini)</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="col-lg-8">
                    <div class="text-muted small">Menampilkan posisi stok dan valuasi persediaan barang aktif saat ini (<strong><?= date('d/m/Y H:i') ?></strong>).</div>
                </div>
            <?php endif; ?>

            <div class="col-lg-4 text-lg-end">
                <a href="export_excel.php?<?= e($exportParams) ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-file-earmark-excel me-1"></i> Ekspor Excel
                </a>
                <a href="export_pdf.php?<?= e($exportParams) ?>" target="_blank" class="btn btn-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Ekspor PDF
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Ringkasan Angka Laporan -->
<?php if ($type === 'sales'): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Jumlah Transaksi</div><div class="fs-5 fw-bold"><?= formatNumber($summary['count']) ?></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Total Subtotal</div><div class="fs-5 fw-bold"><?= formatRupiah($summary['subtotal']) ?></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Total Diskon</div><div class="fs-5 fw-bold text-danger"><?= formatRupiah($summary['discount']) ?></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Total Omzet Bersih</div><div class="fs-5 fw-bold text-primary"><?= formatRupiah($summary['total']) ?></div></div></div>
    </div>
<?php elseif ($type === 'profit'): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Total Penjualan (Kotor)</div><div class="fs-5 fw-bold text-primary"><?= formatRupiah($summary['penjualan']) ?></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Total Harga Modal (HPP)</div><div class="fs-5 fw-bold text-secondary"><?= formatRupiah($summary['modal']) ?></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Total Keuntungan Kotor</div><div class="fs-5 fw-bold text-success"><?= formatRupiah($summary['laba']) ?></div></div></div>
    </div>
<?php elseif ($type === 'inventory'): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Jumlah SKU Aktif</div><div class="fs-5 fw-bold"><?= formatNumber($summary['total_items']) ?></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Total Fisik Stok</div><div class="fs-5 fw-bold"><?= formatNumber($summary['total_stok']) ?> Unit</div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Valuasi Modal Stok</div><div class="fs-5 fw-bold text-secondary"><?= formatRupiah($summary['nilai_modal']) ?></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="small text-muted">Potensi Nilai Jual</div><div class="fs-5 fw-bold text-success"><?= formatRupiah($summary['nilai_jual']) ?></div></div></div>
    </div>
<?php endif; ?>

<!-- Tabel Data Laporan -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <?php if ($type === 'sales'): ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>No. Invoice</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>Kasir</th>
                            <th>Metode</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Diskon</th>
                            <th class="text-end">Grand Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada data penjualan pada rentang tanggal ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($r['invoice_number']) ?></td>
                                    <td><?= formatDateTime($r['created_at']) ?></td>
                                    <td><?= e($r['customer_name']) ?></td>
                                    <td><?= e($r['cashier_name']) ?></td>
                                    <td class="text-uppercase"><?= e($r['payment_method']) ?></td>
                                    <td class="text-end"><?= formatRupiah($r['subtotal']) ?></td>
                                    <td class="text-end text-danger"><?= formatRupiah($r['discount_amount']) ?></td>
                                    <td class="text-end fw-bold"><?= formatRupiah($r['grand_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($type === 'profit'): ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Nama Produk</th>
                            <th class="text-center">Qty Terjual</th>
                            <th class="text-end">Total Modal</th>
                            <th class="text-end">Total Penjualan</th>
                            <th class="text-end">Keuntungan Kotor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data penjualan produk pada periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= e($r['sku']) ?></td>
                                    <td class="fw-semibold"><?= e($r['product_name']) ?></td>
                                    <td class="text-center"><?= formatNumber($r['total_qty']) ?> <?= e($r['unit']) ?></td>
                                    <td class="text-end text-muted"><?= formatRupiah($r['total_modal']) ?></td>
                                    <td class="text-end"><?= formatRupiah($r['total_penjualan']) ?></td>
                                    <td class="text-end fw-bold text-success"><?= formatRupiah($r['laba_kotor']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($type === 'bestseller'): ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th width="60">Rank</th>
                            <th>SKU</th>
                            <th>Nama Produk</th>
                            <th class="text-center">Total Terjual</th>
                            <th class="text-end">Total Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada produk terjual pada periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $i => $r): ?>
                                <tr>
                                    <td class="fw-bold">#<?= $i + 1 ?></td>
                                    <td><?= e($r['sku']) ?></td>
                                    <td class="fw-semibold"><?= e($r['product_name']) ?></td>
                                    <td class="text-center fw-bold text-primary"><?= formatNumber($r['total_qty']) ?> <?= e($r['unit']) ?></td>
                                    <td class="text-end fw-semibold"><?= formatRupiah($r['total_omzet']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($type === 'inventory'): ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th class="text-center">Stok</th>
                            <th class="text-center">Min</th>
                            <th class="text-end">Harga Modal</th>
                            <th class="text-end">Nilai Modal Stok</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php $isLow = (int)$r['stok'] <= (int)$r['stok_minimum']; ?>
                            <tr>
                                <td><?= e($r['sku']) ?></td>
                                <td class="fw-semibold"><?= e($r['name']) ?></td>
                                <td><?= e($r['category_name']) ?></td>
                                <td class="text-center fw-bold"><?= (int)$r['stok'] ?> <?= e($r['unit']) ?></td>
                                <td class="text-center text-muted"><?= (int)$r['stok_minimum'] ?></td>
                                <td class="text-end"><?= formatRupiah($r['harga_modal']) ?></td>
                                <td class="text-end fw-semibold"><?= formatRupiah($r['nilai_modal']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $isLow ? 'danger' : 'success' ?>">
                                        <?= $isLow ? 'Menipis' : 'Aman' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
