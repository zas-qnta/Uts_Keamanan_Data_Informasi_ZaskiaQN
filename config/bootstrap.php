<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../encryption/hybrid.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Sudah melewati halaman login (key cocok maupun tidak)
function require_login(): void
{
    if (empty($_SESSION['admin_login']) && empty($_SESSION['admin_private_key'])) {
        header('Location: ../auth/login.php');
        exit;
    }
}

// true bila private key yang dimasukkan saat login cocok dengan public key sistem
function admin_terbuka(): bool
{
    return !empty($_SESSION['admin_private_key']);
}

function admin_key(): string
{
    return (string)($_SESSION['admin_private_key'] ?? '');
}

// Dekripsi satu baris database. null bila mode terkunci atau dekripsi gagal.
function baca_baris(array $row): ?array
{
    if (!admin_terbuka()) {
        return null;
    }
    return decrypt_row($row, admin_key());
}

// Menandai teks yang sudah berupa ciphertext agar tidak dienkripsi ulang oleh mode terkunci
function tandai(string $html): string
{
    return admin_terbuka() ? $html : "\x01" . $html . "\x02";
}

// Teks ciphertext dari database (aman dari enkripsi ulang)
function ct($teks): string
{
    return tandai(e($teks));
}

// Isi sel tabel: data asli bila berhasil didekripsi, selain itu potongan ciphertext
function sel_data(?array $data, array $row, string $field, int $urut): string
{
    if ($data !== null) {
        return e($data[$field] ?? '');
    }
    $cipher = (string)($row['ciphertext'] ?? '');
    $potong = substr($cipher, $urut * 22, 22);
    if ($potong === '' || $potong === false) {
        $potong = substr($cipher, 0, 22);
    }
    return tandai('<span style="font-family:Consolas,monospace;font-size:11px;color:#64748b;word-break:break-all;">' . e($potong) . '</span>');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Token keamanan tidak valid. Muat ulang halaman.');
    }
}