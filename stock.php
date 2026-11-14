<?php
declare(strict_types=1);

$pageTitle = 'Manajemen Stok';
require_once __DIR__ . '/includes/header.php';
requireRole(['admin']);

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    assertValidCsrf();

    $productId   = (int)($_POST['product_id'] ?? 0);
    $type        = (string)($_POST['type'] ?? '');
    $qtyInput    = (int)($_POST['qty'] ?? 0);
    $supplierId  = (int)($_POST['supplier_id'] ?? 0);
    $reference   = trim((string)($_POST['reference_code'] ?? ''));
    $notes       = trim((string)($_POST['notes'] ?? ''));

    if ($productId <= 0 || !in_array($type, ['in', 'out', 'adjustment'], true) || $qtyInput < 0) {
        setFlash('danger', 'Input pergerakan stok tidak valid.');
        header('Location: stock.php');
        exit;
    }

    if (in_array($type, ['in', 'out'], true) && $qtyInput <= 0) {
        setFlash('danger', 'Jumlah barang untuk stok masuk/keluar harus lebih dari 0.');
        header('Location: stock.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtProd = $pdo->prepare('SELECT id, name, stok FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
        $stmtProd->execute([$productId]);
        $product = $stmtProd->fetch();

        if (!$product) {
            throw new RuntimeException('Produk tidak ditemukan.');
        }

        $stockBefore = (int)$product['stok'];
        $stockAfter  = $stockBefore;
        $qtyChange   = 0;

        if ($type === 'in') {
            $qtyChange  = $qtyInput;
            $stockAfter = $stockBefore + $qtyInput;
        } elseif ($type === 'out') {
            if ($qtyInput > $stockBefore) {
                throw new RuntimeException("Stok keluar ({$qtyInput}) melebihi stok tersedia ({$stockBefore}).");
            }
            $qtyChange  = -$qtyInput;
            $stockAfter = $stockBefore - $qtyInput;
        } elseif ($type === 'adjustment') {
            // Pada penyesuaian (stock opname), qtyInput adalah stok fisik aktual baru
            $stockAfter = $qtyInput;
            $qtyChange  = $stockAfter - $stockBefore;
        }

        $stmtUpdate = $pdo->prepare('UPDATE products SET stok = ? WHERE id = ?');
        $stmtUpdate->execute([$stockAfter, $productId]);

        $stmtLog = $pdo->prepare('
            INSERT INTO stock_movements (product_id, user_id, supplier_id, type, qty_change, stock_before, stock_after, reference_code, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmtLog->execute([
            $productId,
            currentUser()['id'],
            ($type === 'in' && $supplierId > 0) ? $supplierId : null,
            $type,
            $qtyChange,
            $stockBefore,
            $stockAfter,
            $reference !== '' ? $reference : null,
            $notes !== '' ? $notes : null
        ]);

        $pdo->commit();
        setFlash('success', "Stok produk "{$product['name']}" berhasil diperbarui ({$stockBefore} -> {$stockAfter}).");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('danger', 'Gagal memproses stok: ' . $e->getMessage());
    }

    header('Location: stock.php');
    exit;
}

$filter = $_GET['filter'] ?? '';
$products = $pdo->query('SELECT id, sku, name, unit, stok, stok_minimum FROM products WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll();

$lowStockProducts = array_filter($products, static fn($p) => (int)$p['stok'] <= (int)$p['stok_minimum']);

$movements = $pdo->query('
    SELECT sm.*, p.name AS product_name, p.sku, p.unit, u.name AS user_name, s.name AS supplier_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    JOIN users u ON sm.user_id = u.id
    LEFT JOIN suppliers s ON sm.supplier_id = s.id
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT 100
')->fetchAll();
?>

<?php if (!empty($lowStockProducts)): ?>
<div class="alert alert-warning border-warning shadow-sm mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Notifikasi Stok Minimum (<?= count($lowStockProducts) ?> Produk)</h6>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($lowStockProducts as $lp): ?>
            <span class="badge bg-white text-dark border border-danger p-2">
                <strong><?= e($lp['name']) ?></strong>: Sisa <span class="text-danger fw-bold"><?= (int)$lp['stok'] ?> <?= e($lp['unit']) ?></span> (Min: <?= (int)$lp['stok_minimum'] ?>)
            </span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Form Pencatatan Stok -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right text-primary me-2"></i>Catat Pergerakan Stok</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Jenis Transaksi Stok <span class="text-danger">*</span></label>
                        <select name="type" id="stockType" class="form-select" required>
                            <option value="in">Stok Masuk (Pembelian / Restock)</option>
                            <option value="out">Stok Keluar (Rusak / Kedaluwarsa / Internal)</option>
                            <option value="adjustment">Penyesuaian Stok (Stock Opname)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Produk <span class="text-danger">*</span></label>
                        <select name="product_id" id="stockProductSelect" class="form-select" required>
                            <option value="">-- Pilih Produk --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" data-stok="<?= (int)$p['stok'] ?>" data-unit="<?= e($p['unit']) ?>">
                                    <?= e($p['sku']) ?> - <?= e($p['name']) ?> (Stok: <?= (int)$p['stok'] ?> <?= e($p['unit']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="supplierGroup">
                        <label class="form-label small fw-semibold">Supplier / Pemasok (Opsional)</label>
                        <select name="supplier_id" class="form-select">
                            <option value="0">-- Tanpa Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= (int)$sup['id'] ?>"><?= e($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" id="qtyLabel">Jumlah Masuk <span class="text-danger">*</span></label>
                        <input type="number" name="qty" class="form-control" min="0" required placeholder="0">
                        <div class="form-text small" id="qtyHint">Masukkan jumlah barang yang ditambahkan ke stok.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. Referensi / Surat Jalan</label>
                        <input type="text" name="reference_code" class="form-control" placeholder="Contoh: PO-2026-001">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan / Catatan</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan perubahan stok..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan Stok
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Riwayat Perubahan Stok -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Perubahan Stok</h6>
                <span class="badge bg-light text-dark border">100 Aktivitas Terakhir</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Waktu</th>
                                <th>Produk</th>
                                <th class="text-center">Jenis</th>
                                <th class="text-center">Perubahan</th>
                                <th class="text-center">Stok Akhir</th>
                                <th>Keterangan / Ref</th>
                                <th>Petugas</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($movements)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat perubahan stok.</td></tr>
                            <?php else: ?>
                                <?php foreach ($movements as $m): ?>
                                    <?php
                                    $badgeMap = [
                                        'in'         => ['bg-success', 'Stok Masuk'],
                                        'out'        => ['bg-warning text-dark', 'Stok Keluar'],
                                        'adjustment' => ['bg-info text-dark', 'Penyesuaian'],
                                        'sale'       => ['bg-primary', 'Penjualan POS'],
                                    ];
                                    [$badgeClass, $typeLabel] = $badgeMap[$m['type']] ?? ['bg-secondary', $m['type']];
                                    $change = (int)$m['qty_change'];
                                    ?>
                                    <tr>
                                        <td><?= formatDateTime($m['created_at']) ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= e($m['product_name']) ?></div>
                                            <div class="text-muted" style="font-size:0.75rem;"><?= e($m['sku']) ?></div>
                                        </td>
                                        <td class="text-center"><span class="badge <?= $badgeClass ?>"><?= $typeLabel ?></span></td>
                                        <td class="text-center fw-bold <?= $change >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= $change > 0 ? '+' . $change : $change ?> <?= e($m['unit']) ?>
                                        </td>
                                        <td class="text-center fw-semibold"><?= (int)$m['stock_after'] ?> <?= e($m['unit']) ?></td>
                                        <td>
                                            <?php if ($m['reference_code']): ?>
                                                <span class="badge bg-light text-dark border"><?= e($m['reference_code']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($m['supplier_name']): ?>
                                                <div class="text-primary" style="font-size:0.75rem;">Supplier: <?= e($m['supplier_name']) ?></div>
                                            <?php endif; ?>
                                            <div class="text-muted" style="font-size:0.75rem;"><?= e($m['notes'] ?: '-') ?></div>
                                        </td>
                                        <td><?= e($m['user_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const stockType = document.getElementById('stockType');
const supplierGroup = document.getElementById('supplierGroup');
const qtyLabel = document.getElementById('qtyLabel');
const qtyHint = document.getElementById('qtyHint');

stockType.addEventListener('change', function () {
    if (this.value === 'in') {
        supplierGroup.style.display = 'block';
        qtyLabel.innerHTML = 'Jumlah Masuk <span class="text-danger">*</span>';
        qtyHint.textContent = 'Masukkan jumlah barang yang ditambahkan ke stok.';
    } else if (this.value === 'out') {
        supplierGroup.style.display = 'none';
        qtyLabel.innerHTML = 'Jumlah Keluar <span class="text-danger">*</span>';
        qtyHint.textContent = 'Masukkan jumlah barang yang dikurangi dari stok.';
    } else {
        supplierGroup.style.display = 'none';
        qtyLabel.innerHTML = 'Stok Fisik Aktual Baru <span class="text-danger">*</span>';
        qtyHint.textContent = 'Masukkan jumlah stok fisik sebenarnya hasil perhitungan (stock opname).';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
