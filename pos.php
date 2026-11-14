<?php
declare(strict_types=1);

$pageTitle = 'Kasir / Point of Sale (POS)';
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$customers  = $pdo->query('SELECT id, name, phone FROM customers ORDER BY name ASC')->fetchAll();
$products   = $pdo->query('
    SELECT p.id, p.category_id, p.sku, p.barcode, p.name, p.unit, p.harga_jual, p.stok, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.name ASC
')->fetchAll();
?>

<div class="row g-3">
    <!-- Kolom Kiri: Pencarian & Katalog Produk -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-upc-scan text-primary"></i></span>
                            <input type="text" id="posSearch" class="form-control" placeholder="Scan barcode atau ketik nama / SKU produk (Enter)..." autofocus>
                            <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" title="Hapus Pencarian">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="posCategoryFilter" class="form-select">
                            <option value="0">Semua Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Grid Produk -->
                <div class="row g-2" id="productGrid" style="max-height: 64vh; overflow-y: auto;">
                    <?php foreach ($products as $prod): ?>
                        <?php $outOfStock = (int)$prod['stok'] <= 0; ?>
                        <div class="col-sm-6 col-md-4 product-item-col"
                             data-id="<?= (int)$prod['id'] ?>"
                             data-category="<?= (int)$prod['category_id'] ?>"
                             data-name="<?= e(strtolower($prod['name'])) ?>"
                             data-sku="<?= e(strtolower($prod['sku'])) ?>"
                             data-barcode="<?= e(strtolower((string)$prod['barcode'])) ?>">
                            <div class="card h-100 border product-card <?= $outOfStock ? 'out-of-stock' : '' ?>"
                                 onclick='addToCart(<?= json_encode([
                                     "id" => (int)$prod["id"],
                                     "sku" => $prod["sku"],
                                     "name" => $prod["name"],
                                     "unit" => $prod["unit"],
                                     "harga_jual" => (float)$prod["harga_jual"],
                                     "stok" => (int)$prod["stok"]
                                 ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <div class="card-body p-2 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge bg-light text-dark border" style="font-size:0.68rem;"><?= e($prod['sku']) ?></span>
                                            <span class="badge bg-<?= $outOfStock ? 'danger' : 'success' ?>" style="font-size:0.68rem;">
                                                Stok: <?= (int)$prod['stok'] ?>
                                            </span>
                                        </div>
                                        <div class="fw-semibold small text-dark mb-1" style="line-height:1.25;"><?= e($prod['name']) ?></div>
                                    </div>
                                    <div class="pt-1 border-top d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-primary small"><?= formatRupiah($prod['harga_jual']) ?></span>
                                        <span class="text-muted" style="font-size:0.7rem;">/ <?= e($prod['unit']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Keranjang & Pembayaran -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2"></i>Keranjang Belanja</h6>
                <button type="button" class="btn btn-sm btn-outline-light" onclick="clearCart()">
                    <i class="bi bi-trash me-1"></i>Kosongkan
                </button>
            </div>

            <div class="card-body p-3">
                <!-- Pilih Pelanggan -->
                <div class="mb-2">
                    <label class="form-label small fw-semibold mb-1">Pelanggan</label>
                    <select id="customerId" class="form-select form-select-sm">
                        <option value="">Pelanggan Umum</option>
                        <?php foreach ($customers as $cust): ?>
                            <option value="<?= (int)$cust['id'] ?>">
                                <?= e($cust['name']) ?><?= $cust['phone'] ? ' (' . e($cust['phone']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Daftar Item Keranjang -->
                <div class="border rounded mb-3" style="max-height: 240px; overflow-y: auto;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Item</th>
                                <th class="text-center" width="110">Qty</th>
                                <th class="text-end">Subtotal</th>
                                <th width="30"></th>
                            </tr>
                        </thead>
                        <tbody id="cartTableBody" class="small">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Keranjang masih kosong. Klik produk di sebelah kiri.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Ringkasan Perhitungan -->
                <div class="bg-light p-2 rounded border mb-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Subtotal:</span>
                        <span class="fw-semibold" id="summarySubtotal">Rp 0</span>
                    </div>

                    <div class="row g-1 align-items-center mb-1">
                        <div class="col-5">Diskon Transaksi:</div>
                        <div class="col-3">
                            <select id="discountType" class="form-select form-select-sm" onchange="recalculateTotals()">
                                <option value="nominal">Rp</option>
                                <option value="percent">%</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <input type="number" id="discountValue" class="form-control form-control-sm text-end" min="0" value="0" oninput="recalculateTotals()">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between text-danger mb-1">
                        <span>Potongan Diskon:</span>
                        <span id="summaryDiscount">- Rp 0</span>
                    </div>

                    <hr class="my-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold fs-6">TOTAL BAYAR:</span>
                        <span class="fw-bold fs-5 text-primary" id="summaryGrandTotal">Rp 0</span>
                    </div>
                </div>

                <!-- Metode Pembayaran -->
                <div class="mb-2">
                    <label class="form-label small fw-semibold mb-1">Metode Pembayaran</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="payment_method" id="payTunai" value="tunai" checked onchange="togglePaymentFields()">
                        <label class="btn btn-outline-primary btn-sm" for="payTunai"><i class="bi bi-cash me-1"></i>Tunai</label>

                        <input type="radio" class="btn-check" name="payment_method" id="payTransfer" value="transfer" onchange="togglePaymentFields()">
                        <label class="btn btn-outline-primary btn-sm" for="payTransfer"><i class="bi bi-bank me-1"></i>Transfer</label>

                        <input type="radio" class="btn-check" name="payment_method" id="payQris" value="qris" onchange="togglePaymentFields()">
                        <label class="btn btn-outline-primary btn-sm" for="payQris"><i class="bi bi-qr-code me-1"></i>QRIS</label>
                    </div>
                </div>

                <!-- Input Tunai -->
                <div id="cashSection">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1">Uang Diterima (Rp)</label>
                        <input type="number" id="amountPaid" class="form-control form-control-sm fw-bold text-end" min="0" value="0" oninput="recalculateTotals()">
                        <div class="d-flex gap-1 mt-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-0" style="font-size:0.75rem;" onclick="setExactCash()">Uang Pas</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-0" style="font-size:0.75rem;" onclick="addCash(50000)">50rb</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-0" style="font-size:0.75rem;" onclick="addCash(100000)">100rb</button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center bg-success bg-opacity-10 border border-success rounded px-2 py-1 mb-2">
                        <span class="small fw-semibold text-success">Uang Kembalian:</span>
                        <span class="fw-bold text-success" id="summaryChange">Rp 0</span>
                    </div>
                </div>

                <!-- Input Referensi Transfer / QRIS -->
                <div id="nonCashSection" style="display:none;" class="mb-2">
                    <label class="form-label small fw-semibold mb-1">No. Referensi / Catatan Transfer / QRIS</label>
                    <input type="text" id="paymentReference" class="form-control form-control-sm" placeholder="Contoh: REF-QRIS-88219 / Nama Pengirim">
                </div>

                <div class="mb-3">
                    <input type="text" id="transactionNotes" class="form-control form-control-sm" placeholder="Catatan transaksi (opsional)">
                </div>

                <button type="button" id="btnCheckout" class="btn btn-success w-100 fw-bold py-2" onclick="submitTransaction()">
                    <i class="bi bi-check-circle-fill me-1"></i> PROSES PEMBAYARAN &amp; CETAK STRUK
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(generateCsrfToken()) ?>;
const ALL_PRODUCTS = <?= json_encode(array_map(static fn($p) => [
    'id' => (int)$p['id'],
    'sku' => $p['sku'],
    'barcode' => (string)$p['barcode'],
    'name' => $p['name'],
    'unit' => $p['unit'],
    'harga_jual' => (float)$p['harga_jual'],
    'stok' => (int)$p['stok']
], $products)) ?>;

let cart = [];
let currentGrandTotal = 0;

// Filter Produk & Scan Barcode
const posSearch = document.getElementById('posSearch');
const posCategoryFilter = document.getElementById('posCategoryFilter');

function filterProducts() {
    const q = posSearch.value.trim().toLowerCase();
    const catId = posCategoryFilter.value;

    document.querySelectorAll('.product-item-col').forEach(col => {
        const matchCat = (catId === '0' || col.dataset.category === catId);
        const matchText = (
            q === '' ||
            col.dataset.name.includes(q) ||
            col.dataset.sku.includes(q) ||
            col.dataset.barcode.includes(q)
        );
        col.style.display = (matchCat && matchText) ? '' : 'none';
    });
}

posSearch.addEventListener('input', filterProducts);
posCategoryFilter.addEventListener('change', filterProducts);
document.getElementById('clearSearchBtn').addEventListener('click', () => {
    posSearch.value = '';
    filterProducts();
    posSearch.focus();
});

// Tekan Enter pada pencarian / barcode scanner -> langsung tambah jika cocok tepat
posSearch.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const q = posSearch.value.trim().toLowerCase();
        if (!q) return;
        const exact = ALL_PRODUCTS.find(p => p.barcode.toLowerCase() === q || p.sku.toLowerCase() === q);
        if (exact) {
            addToCart(exact);
            posSearch.value = '';
            filterProducts();
        }
    }
});

function addToCart(product) {
    if (product.stok <= 0) {
        alert('Stok produk "' + product.name + '" habis!');
        return;
    }
    const existing = cart.find(item => item.id === product.id);
    if (existing) {
        if (existing.qty + 1 > product.stok) {
            alert('Jumlah melebihi stok tersedia (' + product.stok + ' ' + product.unit + ').');
            return;
        }
        existing.qty += 1;
    } else {
        cart.push({
            id: product.id,
            sku: product.sku,
            name: product.name,
            unit: product.unit,
            harga_jual: product.harga_jual,
            stok: product.stok,
            qty: 1
        });
    }
    renderCart();
}

function updateQty(index, delta) {
    const item = cart[index];
    if (!item) return;
    const newQty = item.qty + delta;
    if (newQty <= 0) {
        cart.splice(index, 1);
    } else if (newQty > item.stok) {
        alert('Stok maksimal tersedia: ' + item.stok);
        return;
    } else {
        item.qty = newQty;
    }
    renderCart();
}

function setQtyDirect(index, value) {
    const item = cart[index];
    if (!item) return;
    let qty = parseInt(value, 10);
    if (isNaN(qty) || qty <= 0) {
        cart.splice(index, 1);
    } else if (qty > item.stok) {
        alert('Stok maksimal tersedia: ' + item.stok);
        item.qty = item.stok;
    } else {
        item.qty = qty;
    }
    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    if (confirm('Kosongkan seluruh keranjang belanja?')) {
        cart = [];
        document.getElementById('discountValue').value = 0;
        document.getElementById('amountPaid').value = 0;
        renderCart();
    }
}

function renderCart() {
    const tbody = document.getElementById('cartTableBody');
    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Keranjang masih kosong. Klik produk di sebelah kiri.</td></tr>';
        recalculateTotals();
        return;
    }

    let html = '';
    cart.forEach((item, idx) => {
        const sub = item.qty * item.harga_jual;
        html += `
            <tr>
                <td>
                    <div class="fw-semibold text-truncate" style="max-width:140px;" title="${item.name}">${item.name}</div>
                    <div class="text-muted" style="font-size:0.72rem;">${formatRupiahJS(item.harga_jual)}</div>
                </td>
                <td class="text-center">
                    <div class="input-group input-group-sm justify-content-center">
                        <button class="btn btn-outline-secondary px-1" type="button" onclick="updateQty(${idx}, -1)">-</button>
                        <input type="number" class="form-control text-center px-1" style="max-width:42px;" value="${item.qty}" min="1" max="${item.stok}" onchange="setQtyDirect(${idx}, this.value)">
                        <button class="btn btn-outline-secondary px-1" type="button" onclick="updateQty(${idx}, 1)">+</button>
                    </div>
                </td>
                <td class="text-end fw-semibold">${formatRupiahJS(sub)}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm text-danger p-0" onclick="removeItem(${idx})"><i class="bi bi-x-circle"></i></button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    recalculateTotals();
}

function recalculateTotals() {
    const subtotal = cart.reduce((acc, item) => acc + (item.qty * item.harga_jual), 0);
    const discType = document.getElementById('discountType').value;
    let discVal = parseFloat(document.getElementById('discountValue').value) || 0;
    if (discVal < 0) discVal = 0;

    let discAmount = 0;
    if (discType === 'percent') {
        if (discVal > 100) discVal = 100;
        discAmount = Math.round(subtotal * (discVal / 100));
    } else {
        discAmount = Math.min(subtotal, Math.round(discVal));
    }

    currentGrandTotal = Math.max(0, subtotal - discAmount);

    document.getElementById('summarySubtotal').textContent = formatRupiahJS(subtotal);
    document.getElementById('summaryDiscount').textContent = '- ' + formatRupiahJS(discAmount);
    document.getElementById('summaryGrandTotal').textContent = formatRupiahJS(currentGrandTotal);

    const method = document.querySelector('input[name="payment_method"]:checked').value;
    if (method === 'tunai') {
        const paid = parseFloat(document.getElementById('amountPaid').value) || 0;
        const change = Math.max(0, paid - currentGrandTotal);
        document.getElementById('summaryChange').textContent = formatRupiahJS(change);
    }
}

function togglePaymentFields() {
    const method = document.querySelector('input[name="payment_method"]:checked').value;
    document.getElementById('cashSection').style.display = (method === 'tunai') ? 'block' : 'none';
    document.getElementById('nonCashSection').style.display = (method !== 'tunai') ? 'block' : 'none';
    recalculateTotals();
}

function setExactCash() {
    document.getElementById('amountPaid').value = currentGrandTotal;
    recalculateTotals();
}

function addCash(amount) {
    const current = parseFloat(document.getElementById('amountPaid').value) || 0;
    document.getElementById('amountPaid').value = current + amount;
    recalculateTotals();
}

async function submitTransaction() {
    if (cart.length === 0) {
        alert('Keranjang belanja masih kosong!');
        return;
    }

    const method = document.querySelector('input[name="payment_method"]:checked').value;
    const amountPaid = method === 'tunai'
        ? (parseFloat(document.getElementById('amountPaid').value) || 0)
        : currentGrandTotal;

    if (method === 'tunai' && amountPaid < currentGrandTotal) {
        alert('Uang tunai yang diterima kurang dari total pembayaran!');
        document.getElementById('amountPaid').focus();
        return;
    }

    const payload = {
        customer_id: document.getElementById('customerId').value || null,
        discount_type: document.getElementById('discountType').value,
        discount_value: parseFloat(document.getElementById('discountValue').value) || 0,
        payment_method: method,
        payment_reference: document.getElementById('paymentReference').value.trim(),
        amount_paid: amountPaid,
        notes: document.getElementById('transactionNotes').value.trim(),
        items: cart.map(item => ({ id: item.id, qty: item.qty }))
    };

    const btn = document.getElementById('btnCheckout');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';

    try {
        const res = await fetch('pos_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (!res.ok || !data.success) {
            alert(data.message || 'Terjadi kesalahan saat menyimpan transaksi.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> PROSES PEMBAYARAN & CETAK STRUK';
            return;
        }

        window.location.href = 'receipt.php?id=' + data.transaction_id + '&autoprint=1';
    } catch (err) {
        alert('Gagal terhubung ke server: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> PROSES PEMBAYARAN & CETAK STRUK';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
