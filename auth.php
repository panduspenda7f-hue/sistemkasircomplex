<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function hasRole(string $role): bool
{
    return isLoggedIn() && ($_SESSION['user']['role'] ?? '') === $role;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.');
        header('Location: login.php');
        exit;
    }
}

function requireRole(array $allowedRoles): void
{
    requireLogin();
    $userRole = $_SESSION['user']['role'] ?? '';
    if (!in_array($userRole, $allowedRoles, true)) {
        setFlash('danger', 'Akses ditolak! Anda tidak memiliki hak akses ke halaman tersebut.');
        header('Location: index.php');
        exit;
    }
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function assertValidCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        die('Token keamanan (CSRF) tidak valid atau sesi telah kedaluwarsa. Silakan muat ulang halaman.');
    }
}
