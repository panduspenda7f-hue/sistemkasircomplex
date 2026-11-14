<?php
declare(strict_types=1);

$pageTitle = 'Manajemen Produk';
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();
$isAdmin = hasRole('admin');

// Proses CRUD khusus Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['admin']);
    assertValidCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id          = (int)($_POST['id'] ?? 0);
        $sku         = strtoupper(trim((string)($_POST['sku'] ?? '')));
        $barcode     = trim((string)($_POST['barcode'] ?? ''));
        $name        = trim((string)($_POST['name'] ?? ''));
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $unit        = trim((string)($_POST['unit'] ?? 'pcs'));
        $hargaModal  = (float)($_POST['harga_modal'] ?? 0);
        $hargaJual   = (float)($_POST['harga_jual'] ?? 0);
        $stok        = (int)($_POST['stok'] ?? 0);
        $stokMinimum = (int)($_POST['stok_minimum'] ?? 5);

        if ($sku === '' || $name === '' || $categoryId <= 0 || $hargaJual < 0 || $hargaModal < 0) {
            setFlash('danger', 'Harap lengkapi semua data wajib produk dengan benar.');
            header('Location: products.php');
            exit;
        }

        try {
            if ($action === 'create') {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('
                    INSERT INTO products (category_id, sku, barcode, name, unit, harga_modal, harga_jual, stok, stok_minimum)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $categoryId,
                    $sku,
                    $barcode !== '' ? $barcode : null,
                    $name,
                    $unit,
                    $hargaModal,
                    $hargaJual,
                    $stok,
                    $stokMinimum
                ]);
                $newProductId = (int)$pdo->lastInsertId();

                if ($stok > 0) {
                    $stmtMove = $pdo->prepare('
                        INSERT INTO stock_movements (product_id, user_id, type, qty_change, stock_before, stock_after, reference_code, notes)
                        VALUES (?, ?, "in", ?, 0, ?, "INIT-STOCK", "Stok awal saat tambah produk")
                    ');
                    $stmtMove->execute([$newProductId, currentUser()['id'], $stok, $stok]);
                }

                $pdo->commit();
                setFlash('success', 'Produk baru berhasil ditambahkan.');
            } else {
                $stmt = $pdo->prepare('
                    UPDATE products
                    SET category_id = ?, sku = ?, barcode = ?, name = ?, unit = ?, harga_modal = ?, harga_jual = ?, stok_minimum = ?
                    WHERE id = ?
                ');
                $stmt->execute([
                    $categoryId,
                    $sku,
                    $barcode !== '' ? $barcode : null,
                    $name,
                    $unit,
                    $hargaModal,
                    $hargaJual,
                    $stokMinimum,
                    $id
                ]);
                setFlash('success', 'Data produk berhasil diperbarui.');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', 'Gagal menyimpan produk: Kode SKU mungkin sudah terdaftar.');
        }

        header('Location: products.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Soft delete agar riwayat transaksi lama tetap utuh
        $stmt = $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Produk berhasil dihapus dari daftar aktif.');
        header('Location: products.php');
        exit;
    }
}

// Filter & Pencarian
$search = trim((string)($_GET['q'] ?? ''));
$filterCat = (int)($_GET['category_id'] ?? 0);

$sql = '
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
';
$params = [];

if ($search !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filterCat > 0) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $filterCat;
}

$sql .= ' ORDER BY p.name ASC';
$stmtProducts = $pdo->prepare($sql);
$stmtProducts->execute($params);
$products = $stmtProducts->fetchAll();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$units = ['pcs', 'kg', 'liter', 'dus', 'pack', 'box', 'botol', 'bungkus'];
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <input type="text" name="q" class="form-control form-control-sm" style="width:240px;" placeholder="Cari nama, SKU, atau barcode..." value="<?= e($search) ?>">
            <select name="category_id" class="form-select form-select-sm" style="width:180px;">
                <option value="0">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $filterCat === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> Cari</button>
            <?php if ($search !== '' || $filterCat > 0): ?>
                <a href="products.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="bi bi-plus-lg me-1"></i> Tambah Produk
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>SKU / Barcode</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <?php if ($isAdmin): ?>
                            <th class="text-end">Harga Modal</th>
                        <?php endif; ?>
                        <th class="text-end">Harga Jual</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Min. Stok</th>
                        <?php if ($isAdmin): ?>
                            <th class="text-center" width="120">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if (empty($products)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada produk ditemukan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $prod): ?>
                            <?php $isLow = (int)$prod['stok'] <= (int)$prod['stok_minimum']; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($prod['sku']) ?></div>
                                    <?php if (!empty($prod['barcode'])): ?>
                                        <div class="text-muted" style="font-size:0.75rem;"><i class="bi bi-upc-scan"></i> <?= e($prod['barcode']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold"><?= e($prod['name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($prod['category_name']) ?></span></td>
                                <?php if ($isAdmin): ?>
                                    <td class="text-end text-muted"><?= formatRupiah($prod['harga_modal']) ?></td>
                                <?php endif; ?>
                                <td class="text-end fw-bold text-primary"><?= formatRupiah($prod['harga_jual']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $isLow ? 'danger' : 'success' ?>">
                                        <?= (int)$prod['stok'] ?> <?= e($prod['unit']) ?>
                                    </span>
                                </td>
                                <td class="text-center text-muted"><?= (int)$prod['stok_minimum'] ?> <?= e($prod['unit']) ?></td>
                                <?php if ($isAdmin): ?>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editProductModal<?= (int)$prod['id'] ?>"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus produk <?= e($prod['name']) ?>?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>

                            <?php if ($isAdmin): ?>
                            <!-- Modal Edit Produk -->
                            <div class="modal fade" id="editProductModal<?= (int)$prod['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <form method="POST" class="modal-content">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
                                        <div class="modal-header">
                                            <h6 class="modal-title fw-bold">Edit Produk: <?= e($prod['name']) ?></h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Kode Produk / SKU <span class="text-danger">*</span></label>
                                                    <input type="text" name="sku" class="form-control" value="<?= e($prod['sku']) ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Barcode (Opsional)</label>
                                                    <input type="text" name="barcode" class="form-control" value="<?= e($prod['barcode']) ?>">
                                                </div>
                                                <div class="col-md-8">
                                                    <label class="form-label small fw-semibold">Nama Produk <span class="text-danger">*</span></label>
                                                    <input type="text" name="name" class="form-control" value="<?= e($prod['name']) ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Kategori <span class="text-danger">*</span></label>
                                                    <select name="category_id" class="form-select" required>
                                                        <?php foreach ($categories as $cat): ?>
                                                            <option value="<?= (int)$cat['id'] ?>" <?= (int)$prod['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                                                <?= e($cat['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Harga Modal (Rp) <span class="text-danger">*</span></label>
                                                    <input type="number" name="harga_modal" class="form-control" min="0" step="1" value="<?= (int)$prod['harga_modal'] ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Harga Jual (Rp) <span class="text-danger">*</span></label>
                                                    <input type="number" name="harga_jual" class="form-control" min="0" step="1" value="<?= (int)$prod['harga_jual'] ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Satuan <span class="text-danger">*</span></label>
                                                    <select name="unit" class="form-select" required>
                                                        <?php foreach ($units as $u): ?>
                                                            <option value="<?= e($u) ?>" <?= $prod['unit'] === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Stok Saat Ini</label>
                                                    <input type="text" class="form-control bg-light" value="<?= (int)$prod['stok'] ?> <?= e($prod['unit']) ?> (Ubah melalui menu Manajemen Stok)" disabled>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold">Batas Minimum Stok <span class="text-danger">*</span></label>
                                                    <input type="number" name="stok_minimum" class="form-control" min="0" value="<?= (int)$prod['stok_minimum'] ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Modal Tambah Produk -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Tambah Produk Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Kode Produk / SKU <span class="text-danger">*</span></label>
                        <input type="text" name="sku" class="form-control" placeholder="Contoh: PRD-008" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Barcode Produk (Opsional)</label>
                        <input type="text" name="barcode" class="form-control" placeholder="Scan atau ketik kode barcode">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Nama lengkap produk" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Kategori <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Harga Modal (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="harga_modal" class="form-control" min="0" step="1" placeholder="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Harga Jual (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="harga_jual" class="form-control" min="0" step="1" placeholder="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Satuan Produk <span class="text-danger">*</span></label>
                        <select name="unit" class="form-select" required>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= e($u) ?>"><?= e($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Stok Awal <span class="text-danger">*</span></label>
                        <input type="number" name="stok" class="form-control" min="0" value="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Batas Minimum Stok <span class="text-danger">*</span></label>
                        <input type="number" name="stok_minimum" class="form-control" min="0" value="5" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
