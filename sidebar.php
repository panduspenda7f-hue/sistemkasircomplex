<?php
declare(strict_types=1);
$isAdmin = hasRole('admin');
?>
<aside class="bg-dark text-white border-end" id="sidebar-wrapper">
    <div class="sidebar-heading p-3 border-bottom border-secondary d-flex align-items-center gap-2">
        <?php if (!empty($store['store_logo']) && file_exists(UPLOAD_LOGO_DIR . $store['store_logo'])): ?>
            <img src="<?= e(UPLOAD_LOGO_URL . $store['store_logo']) ?>" alt="Logo" class="rounded bg-white p-1" style="width:36px;height:36px;object-fit:contain;">
        <?php else: ?>
            <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;">
                <i class="bi bi-shop fs-5"></i>
            </div>
        <?php endif; ?>
        <div class="overflow-hidden">
            <div class="fw-bold text-truncate small"><?= e($store['store_name']) ?></div>
            <div class="text-secondary" style="font-size: 0.72rem;">POS & Inventory System</div>
        </div>
    </div>

    <div class="list-group list-group-flush py-2">
        <div class="px-3 py-1 text-uppercase text-secondary fw-semibold" style="font-size: 0.7rem;">Utama</div>
        <a href="index.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= $currentPage === 'index.php' ? 'active-nav' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>
        <a href="pos.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= $currentPage === 'pos.php' ? 'active-nav' : '' ?>">
            <i class="bi bi-calculator me-2"></i>Kasir / Transaksi
        </a>
        <a href="transactions.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= in_array($currentPage, ['transactions.php', 'receipt.php'], true) ? 'active-nav' : '' ?>">
            <i class="bi bi-receipt me-2"></i>Riwayat Transaksi
        </a>

        <div class="px-3 pt-3 pb-1 text-uppercase text-secondary fw-semibold" style="font-size: 0.7rem;">Inventori & Master</div>
        <a href="products.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= $currentPage === 'products.php' ? 'active-nav' : '' ?>">
            <i class="bi bi-box-seam me-2"></i>Produk
        </a>
        <?php if ($isAdmin): ?>
            <a href="categories.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= $currentPage === 'categories.php' ? 'active-nav' : '' ?>">
                <i class="bi bi-tags me-2"></i>Kategori
            </a>
            <a href="stock.php" class="list-group-item list-group-item-action bg-dark text-white border-0 d-flex justify-content-between align-items-center <?= $currentPage === 'stock.php' ? 'active-nav' : '' ?>">
                <span><i class="bi bi-arrow-left-right me-2"></i>Manajemen Stok</span>
                <?php if ($lowStockCount > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $lowStockCount ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <div class="px-3 pt-3 pb-1 text-uppercase text-secondary fw-semibold" style="font-size: 0.7rem;">Relasi</div>
        <a href="customers.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= $currentPage === 'customers.php' ? 'active-nav' : '' ?>">
            <i class="bi bi-person-vcard me-2"></i>Pelanggan
        </a>
        <?php if ($isAdmin): ?>
            <a href="suppliers.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= $currentPage === 'suppliers.php' ? 'active-nav' : '' ?>">
                <i class="bi bi-truck me-2"></i>Supplier / Pemasok
            </a>

            <div class="px-3 pt-3 pb-1 text-uppercase text-secondary fw-semibold" style="font-size: 0.7rem;">Laporan & Sistem</div>
            <a href="reports.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= $currentPage === 'reports.php' ? 'active-nav' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>Laporan
            </a>
            <a href="users.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= $currentPage === 'users.php' ? 'active-nav' : '' ?>">
                <i class="bi bi-people me-2"></i>Pengguna
            </a>
            <a href="settings.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?= $currentPage === 'settings.php' ? 'active-nav' : '' ?>">
                <i class="bi bi-gear me-2"></i>Pengaturan Toko
            </a>
        <?php endif; ?>
    </div>
</aside>
