<?php
// Mode terkunci: setiap potongan teks pada halaman diganti ciphertext sebelum dikirim ke browser.
// Teks dienkripsi dengan encrypt_content() (AES-256-GCM, kunci AES dibungkus RSA public key).
function kunci_teks(string $s): string
{
    if (trim($s) === '') {
        return $s;
    }
    preg_match('/^(\s*)(.*?)(\s*)$/s', $s, $m);
    $asli = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    try {
        $enc   = encrypt_content($asli);
        $hasil = $enc['ciphertext'];
    } catch (Throwable $e) {
        $hasil = base64_encode(random_bytes(max(8, strlen($asli))));
    }
    return $m[1] . e($hasil) . $m[3];
}

function kunci_tampilan(string $html, int $phase = 0): string
{
    $bagian      = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $lewatiTag   = false;
    $sudahCipher = false;
    $hasil       = '';

    foreach ($bagian as $p) {
        if ($p === '') {
            continue;
        }
        if ($p[0] === '<') {
            if (preg_match('/^<(style|script)\b/i', $p)) {
                $lewatiTag = true;
            } elseif (preg_match('/^<\/(style|script)\b/i', $p)) {
                $lewatiTag = false;
            }
            $hasil .= $p;
            continue;
        }
        if ($lewatiTag) {
            $hasil .= $p;
            continue;
        }
        foreach (preg_split('/([\x01\x02])/', $p, -1, PREG_SPLIT_DELIM_CAPTURE) as $s) {
            if ($s === "\x01") {
                $sudahCipher = true;
            } elseif ($s === "\x02") {
                $sudahCipher = false;
            } else {
                $hasil .= $sudahCipher ? $s : kunci_teks($s);
            }
        }
    }
    return $hasil;
}

function admin_head(string $title, string $active): void
{
    if (!admin_terbuka()) {
        ob_start('kunci_tampilan');
    }
    $menu = [
        'dashboard' => ['dashboard.php', '&#128202; Dashboard'],
        'pegawai'   => ['pegawai.php', '&#128101; Data Pegawai'],
    ];
    if (is_file(__DIR__ . '/enkripsi.php')) {
        $menu['enkripsi'] = ['enkripsi.php', '&#128274; Enkripsi &amp; Dekripsi'];
    }
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title); ?> - Admin Kepegawaian</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body {
            background-color: #1b3b36;
            background-image: radial-gradient(circle at 50% 50%, #244c45 0%, #152f2b 100%);
            color: #e2e8f0; display: flex; min-height: 100vh;
        }
        .sidebar {
            width: 260px; background-color: rgba(18, 38, 35, 0.95); border-right: 1px solid rgba(255, 255, 255, 0.08);
            height: 100vh; padding: 25px 20px; position: fixed; display: flex; flex-direction: column;
        }
        .sidebar h2 { font-size: 18px; margin-bottom: 35px; color: #7ee7df; letter-spacing: 0.5px; }
        .sidebar a { display: block; color: #94a3b8; text-decoration: none; padding: 12px 15px; border-radius: 10px; font-size: 14px; margin-bottom: 6px; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { background-color: rgba(126, 231, 223, 0.15); color: #7ee7df; font-weight: 600; }
        .main-content { margin-left: 260px; padding: 40px; width: calc(100% - 260px); }
        .card {
            background: rgba(27, 59, 54, 0.85); border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); margin-bottom: 25px;
        }
        h1 { font-size: 24px; color: #ffffff; margin-bottom: 8px; font-weight: 600; }
        h3 { font-size: 18px; color: #7ee7df; margin-bottom: 15px; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin-bottom: 10px; }
        .hl { color: #7ee7df; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.08); font-size: 13px; vertical-align: top; }
        th { background-color: rgba(0, 0, 0, 0.2); color: #7ee7df; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        td { color: #cbd5e1; }
        td.cipher { font-family: Consolas, monospace; font-size: 11px; color: #64748b; word-break: break-all; }
        .btn { padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; border: none; display: inline-block; transition: all 0.2s; }
        .btn-primary { background-color: #7ee7df; color: #112220; }
        .btn-primary:hover { background-color: #5bcaca; }
        .btn-edit { background-color: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .btn-danger { background-color: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-danger:hover { background-color: #ef4444; color: white; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
        .alert.success { background-color: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert.danger { background-color: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .form-group.full { grid-column: span 2; }
        label { display: block; font-weight: 500; font-size: 13px; color: #cbd5e1; margin-bottom: 6px; }
        input[type="text"], input[type="email"] {
            width: 100%; padding: 10px 14px; background: rgba(15, 32, 29, 0.6); border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px; color: #ffffff; font-size: 14px;
        }
        input:focus { outline: none; border-color: #7ee7df; box-shadow: 0 0 0 3px rgba(126, 231, 223, 0.15); }
        .btn-logout {
            background-color: rgba(239, 68, 68, 0.15) !important; color: #f87171 !important; border: 1px solid rgba(239, 68, 68, 0.25);
            margin-top: auto; text-align: center; font-weight: 600;
        }
        .btn-logout:hover { background-color: #ef4444 !important; color: white !important; }
        .table-wrap { overflow-x: auto; }
<?php if (!admin_terbuka()): ?>
        .main-content *, .sidebar * { word-break: break-all; font-family: Consolas, monospace; }
<?php endif; ?>
    </style>
</head>
<body>
<div class="sidebar">
    <h2>&#10024; Admin Panel</h2>
    <?php foreach ($menu as $key => [$href, $label]): ?>
        <a href="<?= $href; ?>" class="<?= $key === $active ? 'active' : ''; ?>"><?= $label; ?></a>
    <?php endforeach; ?>
    <a href="../auth/logout.php" class="btn-logout">Keluar Sistem</a>
</div>
<div class="main-content">
<?php if (!admin_terbuka()): ?>
    <div class="alert danger">Mode terkunci: private key yang dimasukkan tidak cocok dengan public key sistem. Seluruh teks hanya tampil sebagai ciphertext dan data tidak dapat diubah. <a href="../auth/login.php" style="color:#fecaca; font-weight:600;">Masukkan private key yang benar</a></div>
<?php endif; ?>
<?php
}

function admin_foot(): void
{
    ?>
</div>
</body>
</html>
<?php
    if (!admin_terbuka() && ob_get_level() > 0) {
        ob_end_flush();
    }
}