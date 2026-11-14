<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data keranjang belanja tidak valid.']);
    exit;
}

$paymentMethod = (string)($data['payment_method'] ?? 'tunai');
if (!in_array($paymentMethod, ['tunai', 'transfer', 'qris'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Metode pembayaran tidak valid.']);
    exit;
}

$discountType  = ($data['discount_type'] ?? 'nominal') === 'percent' ? 'percent' : 'nominal';
$discountValue = max(0, (float)($data['discount_value'] ?? 0));
$customerId    = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
$paymentRef    = trim((string)($data['payment_reference'] ?? ''));
$notes         = trim((string)($data['notes'] ?? ''));
$userId        = (int)currentUser()['id'];

$pdo = getDB();

try {
    $pdo->beginTransaction();

    $subtotal = 0.0;
    $resolvedItems = [];

    $stmtProduct = $pdo->prepare('
        SELECT id, sku, name, unit, harga_modal, harga_jual, stok
        FROM products
        WHERE id = ? AND is_active = 1
        FOR UPDATE
    ');

    foreach ($data['items'] as $item) {
        $prodId = (int)($item['id'] ?? 0);
        $qty    = (int)($item['qty'] ?? 0);

        if ($prodId <= 0 || $qty <= 0) {
            throw new RuntimeException('Item produk atau kuantitas tidak valid.');
        }

        $stmtProduct->execute([$prodId]);
        $prod = $stmtProduct->fetch();

        if (!$prod) {
            throw new RuntimeException("Produk ID #{$prodId} tidak ditemukan atau tidak aktif.");
        }

        if ((int)$prod['stok'] < $qty) {
            throw new RuntimeException("Stok produk "{$prod['name']}" tidak mencukupi (tersedia: {$prod['stok']}).");
        }

        $lineSubtotal = round((float)$prod['harga_jual'] * $qty, 2);
        $subtotal += $lineSubtotal;

        $resolvedItems[] = [
            'product_id'   => (int)$prod['id'],
            'product_name' => $prod['name'],
            'sku'          => $prod['sku'],
            'unit'         => $prod['unit'],
            'harga_modal'  => (float)$prod['harga_modal'],
            'harga_jual'   => (float)$prod['harga_jual'],
            'stock_before' => (int)$prod['stok'],
            'qty'          => $qty,
            'subtotal'     => $lineSubtotal,
        ];
    }

    // Hitung diskon & grand total
    if ($discountType === 'percent') {
        $discountValue  = min(100, $discountValue);
        $discountAmount = round($subtotal * ($discountValue / 100), 2);
    } else {
        $discountAmount = min($subtotal, round($discountValue, 2));
    }

    $grandTotal = max(0, round($subtotal - $discountAmount, 2));
    $amountPaid = $paymentMethod === 'tunai' ? round((float)($data['amount_paid'] ?? 0), 2) : $grandTotal;

    if ($paymentMethod === 'tunai' && $amountPaid < $grandTotal) {
        throw new RuntimeException('Nominal uang bayar kurang dari total tagihan.');
    }

    $changeAmount  = $paymentMethod === 'tunai' ? max(0, round($amountPaid - $grandTotal, 2)) : 0.0;
    $invoiceNumber = generateInvoiceNumber($pdo);

    // 1. Simpan Header Transaksi
    $stmtTrx = $pdo->prepare('
        INSERT INTO transactions (
            invoice_number, user_id, customer_id, subtotal,
            discount_type, discount_value, discount_amount, grand_total,
            payment_method, payment_reference, amount_paid, change_amount, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmtTrx->execute([
        $invoiceNumber,
        $userId,
        $customerId,
        $subtotal,
        $discountType,
        $discountValue,
        $discountAmount,
        $grandTotal,
        $paymentMethod,
        $paymentRef !== '' ? $paymentRef : null,
        $amountPaid,
        $changeAmount,
        $notes !== '' ? $notes : null
    ]);
    $transactionId = (int)$pdo->lastInsertId();

    // 2. Simpan Detail Item, Kurangi Stok Otomatis, dan Catat Riwayat Pergerakan Stok
    $stmtItem = $pdo->prepare('
        INSERT INTO transaction_items (
            transaction_id, product_id, product_name, sku, unit, harga_modal, harga_jual, qty, subtotal
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmtDecStock = $pdo->prepare('UPDATE products SET stok = ? WHERE id = ?');
    $stmtStockLog = $pdo->prepare('
        INSERT INTO stock_movements (
            product_id, user_id, type, qty_change, stock_before, stock_after, reference_code, notes
        ) VALUES (?, ?, "sale", ?, ?, ?, ?, ?)
    ');

    foreach ($resolvedItems as $rItem) {
        $stmtItem->execute([
            $transactionId,
            $rItem['product_id'],
            $rItem['product_name'],
            $rItem['sku'],
            $rItem['unit'],
            $rItem['harga_modal'],
            $rItem['harga_jual'],
            $rItem['qty'],
            $rItem['subtotal']
        ]);

        $stockAfter = $rItem['stock_before'] - $rItem['qty'];
        $stmtDecStock->execute([$stockAfter, $rItem['product_id']]);

        $stmtStockLog->execute([
            $rItem['product_id'],
            $userId,
            -$rItem['qty'],
            $rItem['stock_before'],
            $stockAfter,
            $invoiceNumber,
            'Penjualan Kasir (' . $invoiceNumber . ')'
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success'        => true,
        'transaction_id' => $transactionId,
        'invoice_number' => $invoiceNumber,
        'message'        => 'Transaksi berhasil disimpan.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
