<?php
declare(strict_types=1);

$pageTitle = 'Manajemen Pelanggan';
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    assertValidCsrf();
    $action  = $_POST['action'] ?? '';
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim((string)($_POST['name'] ?? ''));
    $phone   = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $notes   = trim((string)($_POST['notes'] ?? ''));

    if ($action === 'create') {
        if ($name === '') {
            setFlash('danger', 'Nama pelanggan wajib diisi.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO customers (name, phone, address, notes) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $phone ?: null, $address ?: null, $notes ?: null]);
            setFlash('success', 'Data pelanggan berhasil ditambahkan.');
        }
        header('Location: customers.php');
        exit;
    }

    if ($action === 'update' && $id > 0) {
        if ($name === '') {
            setFlash('danger', 'Nama pelanggan wajib diisi.');
        } else {
            $stmt = $pdo->prepare('UPDATE customers SET name = ?, phone = ?, address = ?, notes = ? WHERE id = ?');
            $stmt->execute([$name, $phone ?: null, $address ?: null, $notes ?: null, $id]);
            setFlash('success', 'Data pelanggan berhasil diperbarui.');
        }
        header('Location: customers.php');
        exit;
    }

    if ($action === 'delete' && $id > 0 && hasRole('admin')) {
        $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Data pelanggan berhasil dihapus.');
        header('Location: customers.php');
        exit;
    }
}

$search = trim((string)($_GET['q'] ?? ''));
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE name LIKE ? OR phone LIKE ? OR address LIKE ? ORDER BY name ASC');
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like, $like]);
    $customers = $stmt->fetchAll();
} else {
    $customers = $pdo->query('SELECT * FROM customers ORDER BY name ASC')->fetchAll();
}
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>Tambah Pelanggan</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Pelanggan <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Nama lengkap pelanggan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor Telepon / WA</label>
                        <input type="text" name="phone" class="form-control" placeholder="0812-xxxx-xxxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Alamat lengkap"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan</label>
                        <input type="text" name="notes" class="form-control" placeholder="Keterangan tambahan (opsional)">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i> Simpan Pelanggan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-vcard text-primary me-2"></i>Daftar Pelanggan</h6>
                <form method="GET" class="d-flex gap-1">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama / telepon..." value="<?= e($search) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Nama</th>
                                <th>No. Telepon</th>
                                <th>Alamat</th>
                                <th>Catatan</th>
                                <th class="text-center" width="110">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($customers)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data pelanggan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($customers as $c): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($c['name']) ?></td>
                                        <td><?= e($c['phone'] ?: '-') ?></td>
                                        <td><?= e($c['address'] ?: '-') ?></td>
                                        <td class="text-muted"><?= e($c['notes'] ?: '-') ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editCust<?= (int)$c['id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if (hasRole('admin')): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus pelanggan ini?')">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editCust<?= (int)$c['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" class="modal-content">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                <div class="modal-header">
                                                    <h6 class="modal-title fw-bold">Edit Data Pelanggan</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Nama Pelanggan</label>
                                                        <input type="text" name="name" class="form-control" value="<?= e($c['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">No. Telepon</label>
                                                        <input type="text" name="phone" class="form-control" value="<?= e($c['phone']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Alamat</label>
                                                        <textarea name="address" class="form-control" rows="2"><?= e($c['address']) ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Catatan</label>
                                                        <input type="text" name="notes" class="form-control" value="<?= e($c['notes']) ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
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
