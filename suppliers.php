<?php
declare(strict_types=1);

$pageTitle = 'Manajemen Supplier / Pemasok';
require_once __DIR__ . '/includes/header.php';
requireRole(['admin']);

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    assertValidCsrf();
    $action  = $_POST['action'] ?? '';
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim((string)($_POST['name'] ?? ''));
    $contact = trim((string)($_POST['contact_person'] ?? ''));
    $phone   = trim((string)($_POST['phone'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if ($action === 'create') {
        if ($name === '') {
            setFlash('danger', 'Nama supplier wajib diisi.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $contact ?: null, $phone ?: null, $email ?: null, $address ?: null]);
            setFlash('success', 'Data supplier berhasil ditambahkan.');
        }
        header('Location: suppliers.php');
        exit;
    }

    if ($action === 'update' && $id > 0) {
        if ($name === '') {
            setFlash('danger', 'Nama supplier wajib diisi.');
        } else {
            $stmt = $pdo->prepare('UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?');
            $stmt->execute([$name, $contact ?: null, $phone ?: null, $email ?: null, $address ?: null, $id]);
            setFlash('success', 'Data supplier berhasil diperbarui.');
        }
        header('Location: suppliers.php');
        exit;
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Data supplier berhasil dihapus.');
        header('Location: suppliers.php');
        exit;
    }
}

$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY name ASC')->fetchAll();
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-truck text-primary me-2"></i>Tambah Supplier Baru</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Perusahaan / Supplier <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: PT Sumber Makmur">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Kontak (PIC)</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Nama sales / kontak">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor Telepon</label>
                        <input type="text" name="phone" class="form-control" placeholder="0812-xxxx-xxxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="supplier@email.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Alamat gudang / kantor supplier"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i> Simpan Supplier</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-building text-primary me-2"></i>Daftar Supplier / Pemasok</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Supplier</th>
                                <th>Kontak (PIC)</th>
                                <th>Telepon / Email</th>
                                <th>Alamat</th>
                                <th class="text-center" width="110">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($suppliers)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data supplier.</td></tr>
                            <?php else: ?>
                                <?php foreach ($suppliers as $s): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($s['name']) ?></td>
                                        <td><?= e($s['contact_person'] ?: '-') ?></td>
                                        <td>
                                            <div><?= e($s['phone'] ?: '-') ?></div>
                                            <div class="text-muted" style="font-size:0.75rem;"><?= e($s['email'] ?: '') ?></div>
                                        </td>
                                        <td><?= e($s['address'] ?: '-') ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editSup<?= (int)$s['id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus supplier ini?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editSup<?= (int)$s['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" class="modal-content">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <div class="modal-header">
                                                    <h6 class="modal-title fw-bold">Edit Supplier</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Nama Supplier</label>
                                                        <input type="text" name="name" class="form-control" value="<?= e($s['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Kontak (PIC)</label>
                                                        <input type="text" name="contact_person" class="form-control" value="<?= e($s['contact_person']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Telepon</label>
                                                        <input type="text" name="phone" class="form-control" value="<?= e($s['phone']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Email</label>
                                                        <input type="email" name="email" class="form-control" value="<?= e($s['email']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Alamat</label>
                                                        <textarea name="address" class="form-control" rows="2"><?= e($s['address']) ?></textarea>
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
