<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatRupiah(float|int|string $amount): string
{
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

function formatNumber(float|int|string $number): string
{
    return number_format((float)$number, 0, ',', '.');
}

function formatDateTime(string $datetime, string $format = 'd/m/Y H:i'): string
{
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : $datetime;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function getStoreSettings(): array
{
    static $settings = null;
    if ($settings === null) {
        $pdo = getDB();
        $stmt = $pdo->query('SELECT * FROM store_settings WHERE id = 1 LIMIT 1');
        $row = $stmt->fetch();
        $settings = $row ?: [
            'store_name'     => 'TOKO MANDIRI',
            'store_address'  => 'Alamat Toko Belum Diatur',
            'store_phone'    => '-',
            'store_logo'     => null,
            'receipt_header' => '',
            'receipt_footer' => 'Terima kasih telah berbelanja!',
            'show_logo'      => 1,
            'show_cashier'   => 1,
            'show_customer'  => 1,
        ];
    }
    return $settings;
}

function getLowStockCount(): int
{
    $pdo = getDB();
    $stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stok <= stok_minimum');
    return (int)$stmt->fetchColumn();
}

function generateInvoiceNumber(PDO $pdo): string
{
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $pdo->prepare('SELECT invoice_number FROM transactions WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    $nextSeq = 1;
    if ($last) {
        $parts = explode('-', (string)$last);
        $lastSeq = (int)end($parts);
        $nextSeq = $lastSeq + 1;
    }

    return $prefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);
}
