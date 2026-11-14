<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT t.*, u.name AS cashier_name, COALESCE(c.name, "Umum") AS customer_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN customers c ON t.customer_id = c.id
    WHERE t.id = ?
    LIMIT 1
');
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    setFlash('danger', 'Data transaksi tidak ditemukan.');
    header('Location: transactions.php');
    exit;
}

$stmtItems = $pdo->prepare('SELECT * FROM transaction_items WHERE transaction_id = ? ORDER BY id ASC');
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$store = getStoreSettings();
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk <?= e($trx['invoice_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f1f3f5;
            font-family: 'Courier New', Courier, monospace;
        }
        .receipt-paper {
            width: 80mm;
            max-width: 100%;
            margin: 20px auto;
            background: #fff;
            padding: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 12px;
            color: #000;
        }
        .dashed-line {
            border-top: 1px dashed #333;
            margin: 8px 0;
        }
        @media print {
            body {
                background: #fff;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .receipt-paper {
                width: 100%;
                margin: 0;
                padding: 4px;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

<div class="text-center mt-3 no-print">
    <button onclick="window.print()" class="btn btn-primary btn-sm">Cetak Struk</button>
    <a href="pos.php" class="btn btn-success btn-sm">Transaksi Baru</a>
    <a href="transactions.php" class="btn btn-secondary btn-sm">Riwayat Transaksi</a>
</div>

<div class="receipt-paper">
    <div class="text-center">
        <?php if ((int)$store['show_logo'] === 1 && !empty($store['store_logo']) && file_exists(UPLOAD_LOGO_DIR . $store['store_logo'])): ?>
            <img src="<?= e(UPLOAD_LOGO_URL . $store['store_logo']) ?>" alt="Logo" style="max-height:48px;margin-bottom:6px;"><br>
        <?php endif; ?>
        <div class="fw-bold" style="font-size:15px;"><?= e($store['store_name']) ?></div>
        <div><?= nl2br(e($store['store_address'])) ?></div>
        <div>Telp: <?= e($store['store_phone']) ?></div>
        <?php if (!empty($store['receipt_header'])): ?>
            <div class="mt-1 fst-italic"><?= e($store['receipt_header']) ?></div>
        <?php endif; ?>
    </div>

    <div class="dashed-line"></div>

    <div>
        <div>No   : <?= e($trx['invoice_number']) ?></div>
        <div>Tgl  : <?= formatDateTime($trx['created_at'], 'd/m/Y H:i:s') ?></div>
        <?php if ((int)$store['show_cashier'] === 1): ?>
            <div>Kasir: <?= e($trx['cashier_name']) ?></div>
        <?php endif; ?>
        <?php if ((int)$store['show_customer'] === 1): ?>
            <div>Plg  : <?= e($trx['customer_name']) ?></div>
        <?php endif; ?>
    </div>

    <div class="dashed-line"></div>

    <?php foreach ($items as $item): ?>
        <div class="mb-1">
            <div class="fw-bold"><?= e($item['product_name']) ?></div>
            <div class="d-flex justify-content-between">
                <span><?= (int)$item['qty'] ?> <?= e($item['unit']) ?> x <?= number_format((float)$item['harga_jual'], 0, ',', '.') ?></span>
                <span><?= number_format((float)$item['subtotal'], 0, ',', '.') ?></span>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="dashed-line"></div>

    <div class="d-flex justify-content-between">
        <span>Subtotal</span>
        <span><?= formatRupiah($trx['subtotal']) ?></span>
    </div>
    <?php if ((float)$trx['discount_amount'] > 0): ?>
        <div class="d-flex justify-content-between">
            <span>Diskon</span>
            <span>- <?= formatRupiah($trx['discount_amount']) ?></span>
        </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between fw-bold" style="font-size:14px;">
        <span>TOTAL</span>
        <span><?= formatRupiah($trx['grand_total']) ?></span>
    </div>

    <div class="dashed-line"></div>

    <div class="d-flex justify-content-between">
        <span>Bayar (<?= strtoupper(e($trx['payment_method'])) ?>)</span>
        <span><?= formatRupiah($trx['amount_paid']) ?></span>
    </div>
    <?php if ($trx['payment_method'] === 'tunai'): ?>
        <div class="d-flex justify-content-between">
            <span>Kembalian</span>
            <span><?= formatRupiah($trx['change_amount']) ?></span>
        </div>
    <?php elseif (!empty($trx['payment_reference'])): ?>
        <div>Ref: <?= e($trx['payment_reference']) ?></div>
    <?php endif; ?>

    <div class="dashed-line"></div>

    <div class="text-center mt-2">
        <?= nl2br(e($store['receipt_footer'])) ?>
    </div>
</div>

<?php if ($autoprint): ?>
<script>
window.addEventListener('load', () => window.print());
</script>
<?php endif; ?>
</body>
</html>
