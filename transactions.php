<?php
declare(strict_types=1);

$pageTitle = 'Riwayat Transaksi Penjualan';
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$search    = trim((string)($_GET['q'] ?? ''));

$sql = '
    SELECT t.*, u.name AS cashier_name, COALESCE(c.name, "Umum") AS customer_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN customers c ON t.customer_id = c.id
    WHERE DATE(t.created_at) BETWEEN ? AND ?
';
$params = [$startDate, $endDate];

if ($search !== '') {
    $sql .= ' AND (t.invoice_number LIKE ? OR c.name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql .= ' ORDER BY t.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Cari Invoice / Pelanggan</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="No. Invoice atau nama pelanggan..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-filter"></i> Filter</button>
                <a href="transactions.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>No. Invoice</th>
                        <th>Tanggal &amp; Waktu</th>
                        <th>Pelanggan</th>
                        <th>Kasir</th>
                        <th>Metode</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Diskon</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Struk</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">Tidak ada transaksi pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= e($t['invoice_number']) ?></td>
                                <td><?= formatDateTime($t['created_at']) ?></td>
                                <td><?= e($t['customer_name']) ?></td>
                                <td><?= e($t['cashier_name']) ?></td>
                                <td>
                                    <span class="badge bg-secondary text-uppercase"><?= e($t['payment_method']) ?></span>
                                    <?php if (!empty($t['payment_reference'])): ?>
                                        <div class="text-muted" style="font-size:0.72rem;">Ref: <?= e($t['payment_reference']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= formatRupiah($t['subtotal']) ?></td>
                                <td class="text-end text-danger"><?= formatRupiah($t['discount_amount']) ?></td>
                                <td class="text-end fw-bold"><?= formatRupiah($t['grand_total']) ?></td>
                                <td class="text-center">
                                    <a href="receipt.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-dark">
                                        <i class="bi bi-printer me-1"></i>Struk
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
