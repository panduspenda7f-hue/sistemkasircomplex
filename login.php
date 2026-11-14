<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$store = getStoreSettings();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    assertValidCsrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id, name, username, password, role, is_active FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        $isValid = false;
        if ($user && (int)$user['is_active'] === 1) {
            // Auto-upgrade hash akun bawaan seed pada login pertama
            if ($user['password'] === '$2y$10$SEED_DEFAULT_ADMIN_HASH_AUTO_UPGRADE' && $username === 'admin' && $password === 'admin123') {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$newHash, $user['id']]);
                $isValid = true;
            } elseif ($user['password'] === '$2y$10$SEED_DEFAULT_KASIR_HASH_AUTO_UPGRADE' && $username === 'kasir' && $password === 'kasir123') {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$newHash, $user['id']]);
                $isValid = true;
            } else {
                $isValid = password_verify($password, $user['password']);
            }
        }

        if ($isValid) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'       => (int)$user['id'],
                'name'     => $user['name'],
                'username' => $user['username'],
                'role'     => $user['role'],
            ];
            setFlash('success', 'Selamat datang kembali, ' . $user['name'] . '!');
            header('Location: index.php');
            exit;
        } else {
            $error = 'Username atau password salah, atau akun tidak aktif.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e($store['store_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:56px;height:56px;">
                            <i class="bi bi-shop fs-3"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?= e($store['store_name']) ?></h4>
                        <p class="text-muted small mb-0">Sistem Kasir &amp; Manajemen Stok</p>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php if ($flash = getFlash()): ?>
                        <div class="alert alert-<?= e($flash['type']) ?> py-2 small"><?= e($flash['message']) ?></div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" placeholder="Masukkan username" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Masukkan password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePwd" title="Tampilkan Password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Sistem
                        </button>
                    </form>

                    <div class="mt-4 p-2 bg-light rounded border small text-muted">
                        <div class="fw-semibold text-dark mb-1">Akun Demo Default:</div>
                        <div>Admin: <code>admin</code> / <code>admin123</code></div>
                        <div>Kasir: <code>kasir</code> / <code>kasir123</code></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('togglePwd').addEventListener('click', function () {
    const pwd = document.getElementById('loginPassword');
    const icon = this.querySelector('i');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});
</script>
</body>
</html>
