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
    $headers = ['No. Invoice', 'Tanggal', 'Pelanggan', 'Kasir', 'Metode', 'Subtotal', 'Diskon', 'Grand Total'];
    $stmt = $pdo->prepare('
        SELECT t.invoice_number, t.created_at, COALESCE(c.name, "Umum") AS customer_name,
               u.name AS cashier_name, UPPER(t.payment_method) AS method,
               t.subtotal, t.discount_amount, t.grand_total
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
            (float)$r['subtotal'],
            (float)$r['discount_amount'],
            (float)$r['grand_total'],
        ];
    }
} elseif ($type === 'profit') {
    $title = "Laporan Keuntungan Kotor ({$startDate} s/d {$endDate})";
    $headers = ['SKU', 'Nama Produk', 'Satuan', 'Qty Terjual', 'Total Modal', 'Total Penjualan', 'Keuntungan Kotor'];
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
            $r['unit'],
            (int)$r['total_qty'],
            (float)$r['total_modal'],
            (float)$r['total_penjualan'],
            (float)$r['laba_kotor'],
        ];
    }
} elseif ($type === 'bestseller') {
    $title = "Laporan Produk Terlaris ({$startDate} s/d {$endDate})";
    $headers = ['Peringkat', 'SKU', 'Nama Produk', 'Satuan', 'Total Terjual', 'Total Omzet'];
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
            $rank++,
            $r['sku'],
            $r['product_name'],
            $r['unit'],
            (int)$r['total_qty'],
            (float)$r['total_omzet'],
        ];
    }
} elseif ($type === 'inventory') {
    $title = 'Laporan Persediaan Barang (' . date('d-m-Y') . ')';
    $headers = ['SKU', 'Nama Produk', 'Kategori', 'Satuan', 'Stok', 'Stok Minimum', 'Harga Modal', 'Harga Jual', 'Nilai Modal Stok'];
    $stmt = $pdo->query('
        SELECT p.sku, p.name, c.name AS category_name, p.unit, p.stok, p.stok_minimum,
               p.harga_modal, p.harga_jual, (p.stok * p.harga_modal) AS nilai_modal
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
            $r['unit'],
            (int)$r['stok'],
            (int)$r['stok_minimum'],
            (float)$r['harga_modal'],
            (float)$r['harga_jual'],
            (float)$r['nilai_modal'],
        ];
    }
}

$filenameSlug = 'laporan-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($type)) . '-' . date('YmdHis');

// Gunakan PhpSpreadsheet jika library tersedia
if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan');

    $sheet->setCellValue('A1', $store['store_name']);
    $sheet->setCellValue('A2', $title);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11);

    $headerRow = 4;
    foreach ($headers as $colIdx => $heading) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
        $sheet->setCellValue($colLetter . $headerRow, $heading);
        $sheet->getStyle($colLetter . $headerRow)->getFont()->setBold(true);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    $currentRow = 5;
    foreach ($dataRows as $row) {
        foreach ($row as $colIdx => $val) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue($colLetter . $currentRow, $val);
        }
        $currentRow++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filenameSlug . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Fallback CSV jika composer install / PhpSpreadsheet belum dijalankan
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenameSlug . '.csv"');
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM untuk Excel
fputcsv($output, [$store['store_name']]);
fputcsv($output, [$title]);
fputcsv($output, []);
fputcsv($output, $headers);
foreach ($dataRows as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
