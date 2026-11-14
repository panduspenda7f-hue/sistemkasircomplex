<?php
declare(strict_types=1);

$pageTitle = 'Pengaturan Toko & Struk';
require_once __DIR__ . '/includes/header.php';
requireRole(['admin']);

$pdo = getDB();
$store = getStoreSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    assertValidCsrf();

    $storeName     = trim((string)($_POST['store_name'] ?? ''));
    $storeAddress  = trim((string)($_POST['store_address'] ?? ''));
    $storePhone    = trim((string)($_POST['store_phone'] ?? ''));
    $receiptHeader = trim((string)($_POST['receipt_header'] ?? ''));
    $receiptFooter = trim((string)($_POST['receipt_footer'] ?? ''));
    $showLogo      = isset($_POST['show_logo']) ? 1 : 0;
    $showCashier   = isset($_POST['show_cashier']) ? 1 : 0;
    $showCustomer  = isset($_POST['show_customer']) ? 1 : 0;

    if ($storeName === '' || $storeAddress === '') {
        setFlash('danger', 'Nama toko dan alamat toko wajib diisi.');
        header('Location: settings.php');
        exit;
    }

    $logoFilename = $store['store_logo'] ?? null;

    // Upload Logo Toko dengan Validasi MIME Type & Ukuran
    if (!empty($_FILES['store_logo']['name']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
        $tmpFile  = $_FILES['store_logo']['tmp_name'];
        $fileSize = (int)$_FILES['store_logo']['size'];

        if ($fileSize > 2 * 1024 * 1024) {
            setFlash('danger', 'Ukuran file logo maksimal 2MB.');
            header('Location: settings.php');
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpFile);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedMimes[$mimeType])) {
            setFlash('danger', 'Format logo harus berupa JPG, PNG, atau WEBP.');
            header('Location: settings.php');
            exit;
        }

        $newFilename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $allowedMimes[$mimeType];
        if (move_uploaded_file($tmpFile, UPLOAD_LOGO_DIR . $newFilename)) {
            if (!empty($logoFilename) && file_exists(UPLOAD_LOGO_DIR . $logoFilename)) {
                @unlink(UPLOAD_LOGO_DIR . $logoFilename);
            }
            $logoFilename = $newFilename;
        }
    }

    $stmt = $pdo->prepare('
        UPDATE store_settings
        SET store_name = ?, store_address = ?, store_phone = ?, store_logo = ?,
            receipt_header = ?, receipt_footer = ?, show_logo = ?, show_cashier = ?, show_customer = ?
        WHERE id = 1
    ');
    $stmt->execute([
        $storeName,
        $storeAddress,
        $storePhone,
        $logoFilename,
        $receiptHeader,
        $receiptFooter,
        $showLogo,
        $showCashier,
        $showCustomer
    ]);

    setFlash('success', 'Pengaturan toko dan tampilan struk berhasil disimpan.');
    header('Location: settings.php');
    exit;
}
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shop text-primary me-2"></i>Identitas Toko &amp; Konfigurasi Struk</h6>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Toko <span class="text-danger">*</span></label>
                            <input type="text" name="store_name" class="form-control" value="<?= e($store['store_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor Telepon Toko <span class="text-danger">*</span></label>
                            <input type="text" name="store_phone" class="form-control" value="<?= e($store['store_phone']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Alamat Lengkap Toko <span class="text-danger">*</span></label>
                            <textarea name="store_address" class="form-control" rows="2" required><?= e($store['store_address']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Logo Toko (JPG / PNG / WEBP, Maks. 2MB)</label>
                            <input type="file" name="store_logo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold small text-uppercase text-muted mb-3">Pengaturan Informasi pada Struk Penjualan</h6>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Slogan / Header Struk</label>
                        <input type="text" name="receipt_header" class="form-control" value="<?= e($store['receipt_header']) ?>" placeholder="Contoh: Melayani dengan Sepenuh Hati">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan Kaki / Footer Struk</label>
                        <textarea name="receipt_footer" class="form-control" rows="2"><?= e($store['receipt_footer']) ?></textarea>
                    </div>

                    <div class="d-flex flex-wrap gap-4 mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_logo" id="showLogo" <?= (int)$store['show_logo'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="showLogo">Tampilkan Logo di Struk</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_cashier" id="showCashier" <?= (int)$store['show_cashier'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="showCashier">Tampilkan Nama Kasir</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_customer" id="showCustomer" <?= (int)$store['show_customer'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="showCustomer">Tampilkan Nama Pelanggan</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary fw-semibold px-4">
                        <i class="bi bi-save me-1"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-image text-primary me-2"></i>Pratinjau Logo Toko</h6>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($store['store_logo']) && file_exists(UPLOAD_LOGO_DIR . $store['store_logo'])): ?>
                    <img src="<?= e(UPLOAD_LOGO_URL . $store['store_logo']) ?>" alt="Logo Toko" class="img-fluid rounded border p-2" style="max-height: 150px;">
                <?php else: ?>
                    <div class="py-5 bg-light rounded border text-muted small">
                        <i class="bi bi-shop fs-1 d-block mb-2"></i>
                        Belum ada logo yang diunggah.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
