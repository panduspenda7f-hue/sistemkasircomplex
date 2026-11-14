<?php
declare(strict_types=1);

$pageTitle = 'Manajemen Pengguna';
require_once __DIR__ . '/includes/header.php';
requireRole(['admin']);

$pdo = getDB();
$meId = (int)currentUser()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    assertValidCsrf();
    $action   = $_POST['action'] ?? '';
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim((string)($_POST['name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $role     = ($_POST['role'] ?? 'kasir') === 'admin' ? 'admin' : 'kasir';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = (string)($_POST['password'] ?? '');

    if ($action === 'create') {
        if ($name === '' || $username === '' || strlen($password) < 6) {
            setFlash('danger', 'Nama, username wajib diisi dan password minimal 6 karakter.');
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (name, username, password, role, is_active) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $username, $hash, $role, $isActive]);
                setFlash('success', 'Akun pengguna baru berhasil dibuat.');
            } catch (PDOException $e) {
                setFlash('danger', 'Gagal menambah pengguna: Username sudah digunakan.');
            }
        }
        header('Location: users.php');
        exit;
    }

    if ($action === 'update' && $id > 0) {
        if ($name === '' || $username === '') {
            setFlash('danger', 'Nama dan username wajib diisi.');
        } else {
            if ($id === $meId) {
                $isActive = 1; // Tidak bisa menonaktifkan akun sendiri
                $role = 'admin';
            }
            try {
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        setFlash('danger', 'Password baru minimal 6 karakter.');
                        header('Location: users.php');
                        exit;
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, password = ?, role = ?, is_active = ? WHERE id = ?');
                    $stmt->execute([$name, $username, $hash, $role, $isActive, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, role = ?, is_active = ? WHERE id = ?');
                    $stmt->execute([$name, $username, $role, $isActive, $id]);
                }
                setFlash('success', 'Data pengguna berhasil diperbarui.');
            } catch (PDOException $e) {
                setFlash('danger', 'Gagal memperbarui pengguna: Username sudah digunakan.');
            }
        }
        header('Location: users.php');
        exit;
    }

    if ($action === 'delete' && $id > 0) {
        if ($id === $meId) {
            setFlash('danger', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$id]);
                setFlash('success', 'Akun pengguna berhasil dihapus.');
            } catch (PDOException $e) {
                setFlash('danger', 'Pengguna tidak dapat dihapus karena memiliki riwayat transaksi. Nonaktifkan status akun saja.');
            }
        }
        header('Location: users.php');
        exit;
    }
}

$users = $pdo->query('SELECT id, name, username, role, is_active, created_at FROM users ORDER BY role ASC, name ASC')->fetchAll();
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus-fill text-primary me-2"></i>Tambah Akun Pengguna</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" minlength="6" required placeholder="Minimal 6 karakter">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role / Hak Akses <span class="text-danger">*</span></label>
                        <select name="role" class="form-select">
                            <option value="kasir">Kasir (Transaksi, Stok View, Pelanggan)</option>
                            <option value="admin">Admin (Akses Penuh Semua Modul)</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="newActive" checked>
                        <label class="form-check-label small" for="newActive">Akun Aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i> Buat Akun</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people text-primary me-2"></i>Daftar Pengguna Sistem</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Nama</th>
                                <th>Username</th>
                                <th class="text-center">Role</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" width="110">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($u['name']) ?></td>
                                    <td><code><?= e($u['username']) ?></code></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $u['role'] === 'admin' ? 'dark' : 'info text-dark' ?> text-uppercase">
                                            <?= e($u['role']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= (int)$u['is_active'] === 1 ? 'success' : 'secondary' ?>">
                                            <?= (int)$u['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editUser<?= (int)$u['id'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ((int)$u['id'] !== $meId): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus akun pengguna ini?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editUser<?= (int)$u['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST" class="modal-content">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <div class="modal-header">
                                                <h6 class="modal-title fw-bold">Edit Pengguna: <?= e($u['name']) ?></h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Nama Lengkap</label>
                                                    <input type="text" name="name" class="form-control" value="<?= e($u['name']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Username</label>
                                                    <input type="text" name="username" class="form-control" value="<?= e($u['username']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Password Baru (Kosongkan jika tidak diubah)</label>
                                                    <input type="password" name="password" class="form-control" minlength="6" placeholder="******">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Role</label>
                                                    <select name="role" class="form-select" <?= (int)$u['id'] === $meId ? 'disabled' : '' ?>>
                                                        <option value="kasir" <?= $u['role'] === 'kasir' ? 'selected' : '' ?>>Kasir</option>
                                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    </select>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_active" id="act<?= (int)$u['id'] ?>" <?= (int)$u['is_active'] === 1 ? 'checked' : '' ?> <?= (int)$u['id'] === $meId ? 'disabled' : '' ?>>
                                                    <label class="form-check-label small" for="act<?= (int)$u['id'] ?>">Akun Aktif</label>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
