<?php
declare(strict_types=1);

$pageTitle = 'Manajemen Kategori';
require_once __DIR__ . '/includes/header.php';
requireRole(['admin']);

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    assertValidCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            setFlash('danger', 'Nama kategori wajib diisi.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
                $stmt->execute([$name, $description !== '' ? $description : null]);
                setFlash('success', 'Kategori berhasil ditambahkan.');
            } catch (PDOException $e) {
                setFlash('danger', 'Gagal menambah kategori: Nama kategori mungkin sudah ada.');
            }
        }
        header('Location: categories.php');
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($id <= 0 || $name === '') {
            setFlash('danger', 'Data kategori tidak valid.');
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
                $stmt->execute([$name, $description !== '' ? $description : null, $id]);
                setFlash('success', 'Kategori berhasil diperbarui.');
            } catch (PDOException $e) {
                setFlash('danger', 'Gagal memperbarui kategori: Nama kategori sudah digunakan.');
            }
        }
        header('Location: categories.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmtCheck->execute([$id]);
        $usedCount = (int)$stmtCheck->fetchColumn();

        if ($usedCount > 0) {
            setFlash('danger', "Kategori tidak dapat dihapus karena masih digunakan oleh {$usedCount} produk.");
        } else {
            $stmtDel = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $stmtDel->execute([$id]);
            setFlash('success', 'Kategori berhasil dihapus.');
        }
        header('Location: categories.php');
        exit;
    }
}

$categories = $pdo->query('
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
    GROUP BY c.id
    ORDER BY c.name ASC
')->fetchAll();
?>

<div class="row g-3">
    <!-- Form Tambah Kategori -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Tambah Kategori Baru</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Minuman Dingin" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Deskripsi singkat kategori (opsional)"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i> Simpan Kategori
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Daftar Kategori -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-tags text-primary me-2"></i>Daftar Kategori Produk</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th width="50">#</th>
                                <th>Nama Kategori</th>
                                <th>Keterangan</th>
                                <th class="text-center">Jumlah Produk</th>
                                <th class="text-center" width="140">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada kategori.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $idx => $cat): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td class="fw-semibold"><?= e($cat['name']) ?></td>
                                        <td class="text-muted"><?= e($cat['description'] ?: '-') ?></td>
                                        <td class="text-center">
                                            <a href="products.php?category_id=<?= (int)$cat['id'] ?>" class="badge bg-info text-dark text-decoration-none">
                                                <?= (int)$cat['product_count'] ?> Produk
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-warning"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editCatModal<?= (int)$cat['id'] ?>"
                                                    title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <?php if ((int)$cat['product_count'] === 0): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Kategori masih digunakan produk">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Modal Edit Kategori -->
                                    <div class="modal fade" id="editCatModal<?= (int)$cat['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" class="modal-content">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                                <div class="modal-header">
                                                    <h6 class="modal-title fw-bold">Edit Kategori</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Nama Kategori</label>
                                                        <input type="text" name="name" class="form-control" value="<?= e($cat['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Keterangan</label>
                                                        <textarea name="description" class="form-control" rows="2"><?= e($cat['description']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
