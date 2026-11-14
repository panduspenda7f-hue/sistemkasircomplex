<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$user = currentUser();
$store = getStoreSettings();
$lowStockCount = getLowStockCount();
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e($store['store_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="d-flex" id="wrapper">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div id="page-content-wrapper" class="flex-grow-1 d-flex flex-column min-vh-100">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-3 px-lg-4 py-2 sticky-top shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle" title="Toggle Menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h5 class="mb-0 fw-semibold text-dark"><?= e($pageTitle) ?></h5>
            </div>

            <div class="ms-auto d-flex align-items-center gap-3">
                <?php if ($lowStockCount > 0): ?>
                    <a href="stock.php?filter=low_stock" class="btn btn-warning btn-sm position-relative fw-medium">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span class="d-none d-md-inline">Stok Menipis</span>
                        <span class="badge bg-danger ms-1"><?= $lowStockCount ?></span>
                    </a>
                <?php endif; ?>

                <a href="pos.php" class="btn btn-primary btn-sm fw-medium">
                    <i class="bi bi-cart-plus me-1"></i>Kasir / POS
                </a>

                <div class="dropdown">
                    <button class="btn btn-light border btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle text-primary fs-6"></i>
                        <span class="d-none d-sm-inline"><?= e($user['name']) ?></span>
                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'dark' : 'info text-dark' ?> text-uppercase" style="font-size: 0.65rem;">
                            <?= e($user['role']) ?>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="px-3 py-1 small text-muted">Login sebagai <strong><?= e($user['username']) ?></strong></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($user['role'] === 'admin'): ?>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-shop me-2"></i>Pengaturan Toko</a></li>
                            <li><a class="dropdown-item" href="users.php"><i class="bi bi-people me-2"></i>Manajemen Pengguna</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php" onclick="return confirm('Yakin ingin keluar dari sistem?')">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="container-fluid p-3 p-lg-4 flex-grow-1">
            <?php if ($flash = getFlash()): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
