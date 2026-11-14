<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireRole(['admin']);

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

$pdo       = getDB();
$store     = getStoreSettings();
$type      = $_GET['type'] ?? 'sales';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

$title = 'Laporan Penjualan';
$headers = [];
$dataRows = [];

if ($type === 'sales') {
    $title = "Laporan Penjualan ({$startDate} s/d {$endDate})";
    $headers = ['No. Invoice', 'Tanggal', 'Pelanggan', 'Kasir', 'Metode', 'Total'];
    $stmt = $pdo->prepare('
        SELECT t.invoice_number, t.created_at, COALESCE(c.name, "Umum") AS customer_name,
               u.name AS cashier_name, UPPER(t.payment_method) AS method, t.grand_total
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE DATE(t.created_at) BETWEEN ? AND ?
        ORDER BY t.created_at DESC
    ');
    $stmt->execute([$startDate, $endDate]);
    foreach ($stmt->fetchAll() as $r) {
        $dataRows[] = [
            $r['invoice_number'],
            formatDateTime($r['created_at']),
            $r['customer_name'],
            $r['cashier_name'],
            $r['method'],
            formatRupiah($r['grand_total']),
        ];
    }
} elseif ($type === 'profit') {
    $title = "Laporan Keuntungan Kotor ({$startDate} s/d {$endDate})";
    $headers = ['SKU', 'Nama Produk', 'Terjual', 'Total Modal', 'Total Penjualan', 'Laba Kotor'];
    $stmt = $pdo->prepare('
        SELECT ti.sku, ti.product_name, ti.unit, SUM(ti.qty) AS total_qty,
               SUM(ti.harga_modal * ti.qty) AS total_modal,
               SUM(ti.subtotal) AS total_penjualan,
               SUM(ti.subtotal - (ti.harga_modal * ti.qty)) AS laba_kotor
        FROM transaction_items ti
        JOIN transactions t ON ti.transaction_id = t.id
        WHERE DATE(t.created_at) BETWEEN ? AND ?
        GROUP BY ti.product_id, ti.sku, ti.product_name, ti.unit
        ORDER BY laba_kotor DESC
    ');
    $stmt->execute([$startDate, $endDate]);
    foreach ($stmt->fetchAll() as $r) {
        $dataRows[] = [
            $r['sku'],
            $r['product_name'],
            formatNumber($r['total_qty']) . ' ' . $r['unit'],
            formatRupiah($r['total_modal']),
            formatRupiah($r['total_penjualan']),
            formatRupiah($r['laba_kotor']),
        ];
    }
} elseif ($type === 'bestseller') {
    $title = "Laporan Produk Terlaris ({$startDate} s/d {$endDate})";
    $headers = ['Rank', 'SKU', 'Nama Produk', 'Total Terjual', 'Total Omzet'];
    $stmt = $pdo->prepare('
        SELECT ti.sku, ti.product_name, ti.unit, SUM(ti.qty) AS total_qty, SUM(ti.subtotal) AS total_omzet
        FROM transaction_items ti
        JOIN transactions t ON ti.transaction_id = t.id
        WHERE DATE(t.created_at) BETWEEN ? AND ?
        GROUP BY ti.product_id, ti.sku, ti.product_name, ti.unit
        ORDER BY total_qty DESC
    ');
    $stmt->execute([$startDate, $endDate]);
    $rank = 1;
    foreach ($stmt->fetchAll() as $r) {
        $dataRows[] = [
            '#' . $rank++,
            $r['sku'],
            $r['product_name'],
            formatNumber($r['total_qty']) . ' ' . $r['unit'],
            formatRupiah($r['total_omzet']),
        ];
    }
} elseif ($type === 'inventory') {
    $title = 'Laporan Persediaan Barang (' . date('d/m/Y') . ')';
    $headers = ['SKU', 'Nama Produk', 'Kategori', 'Stok', 'Harga Modal', 'Nilai Modal Stok'];
    $stmt = $pdo->query('
        SELECT p.sku, p.name, c.name AS category_name, p.unit, p.stok,
               p.harga_modal, (p.stok * p.harga_modal) AS nilai_modal
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        ORDER BY p.name ASC
    ');
    foreach ($stmt->fetchAll() as $r) {
        $dataRows[] = [
            $r['sku'],
            $r['name'],
            $r['category_name'],
            (int)$r['stok'] . ' ' . $r['unit'],
            formatRupiah($r['harga_modal']),
            formatRupiah($r['nilai_modal']),
        ];
    }
}

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= e($title) ?></title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #222; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 14px; }
        .header h2 { margin: 0 0 4px 0; font-size: 16px; }
        .header p { margin: 2px 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background-color: #f0f2f5; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 10px; color: #666; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h2><?= e($store['store_name']) ?></h2>
        <p><?= e($store['store_address']) ?> | Telp: <?= e($store['store_phone']) ?></p>
        <p><strong><?= e($title) ?></strong></p>
    </div>

    <table>
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= e($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dataRows)): ?>
                <tr><td colspan="<?= count($headers) ?>" style="text-align:center;">Tidak ada data.</td></tr>
            <?php else: ?>
                <?php foreach ($dataRows as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= e((string)$cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Dicetak pada: <?= date('d/m/Y H:i:s') ?> oleh <?= e(currentUser()['name']) ?>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Gunakan Dompdf jika library terpasang
if (class_exists(\Dompdf\Dompdf::class)) {
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $filenameSlug = 'laporan-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($type)) . '-' . date('YmdHis') . '.pdf';
    $dompdf->stream($filenameSlug, ['Attachment' => false]);
    exit;
}

// Fallback HTML siap cetak PDF browser jika composer install belum dijalankan
echo $html . '<script>window.print();</script>';
