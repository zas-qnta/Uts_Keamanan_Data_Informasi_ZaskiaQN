<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (admin_terbuka()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = (string)($_POST['private_key'] ?? '');

    if (trim($raw) === '') {
        $error = 'Private key tidak boleh kosong.';
    } else {
        $pem   = normalize_private_key($raw);
        $cocok = ($pem !== '' && verify_private_key($pem));

        // Key salah tetap masuk, tetapi tanpa private key (mode terkunci: data tampil sebagai ciphertext)
        session_regenerate_id(true);
        $_SESSION['admin_login']       = true;
        $_SESSION['admin_private_key'] = $cocok ? $pem : '';
        $_SESSION['csrf']              = bin2hex(random_bytes(16));
        header('Location: ../admin/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Sistem Kepegawaian</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body {
            background-color: #1b3b36;
            background-image: radial-gradient(circle at 50% 50%, #244c45 0%, #152f2b 100%);
            color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .login-card {
            width: 100%; max-width: 520px; padding: 36px;
            background: rgba(27, 59, 54, 0.85); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        h2 { color: #ffffff; font-size: 24px; margin-bottom: 8px; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin-bottom: 20px; }
        label { display: block; font-size: 13px; color: #cbd5e1; margin-bottom: 6px; font-weight: 500; }
        textarea {
            width: 100%; height: 190px; padding: 12px 14px; resize: vertical;
            background: rgba(15, 32, 29, 0.6); border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px; color: #ffffff; font-family: Consolas, monospace; font-size: 12px;
        }
        textarea:focus { outline: none; border-color: #7ee7df; box-shadow: 0 0 0 3px rgba(126, 231, 223, 0.15); }
        .file-row { margin: 14px 0 0 0; font-size: 13px; color: #94a3b8; }
        .file-row input { color: #94a3b8; font-size: 13px; }
        button[type="submit"] {
            width: 100%; background-color: #7ee7df; color: #112220; border: none; padding: 12px;
            border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 20px; transition: all 0.2s;
        }
        button[type="submit"]:hover { background-color: #5bcaca; transform: translateY(-1px); }
        .error-msg {
            background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171;
            padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;
        }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #94a3b8; text-decoration: none; font-size: 13px; }
        .back-link:hover { color: #7ee7df; }
    </style>
</head>
<body>
<div class="login-card">
    <h2>&#128272; Login Admin</h2>
    <p>Masuk menggunakan RSA Private Key, tanpa password. Jika key cocok, data didekripsi dan ditampilkan. Jika key tidak cocok, panel tetap terbuka dalam mode terkunci: seluruh teks pada panel hanya tampil sebagai ciphertext.</p>

    <?php if ($error !== ''): ?>
        <div class="error-msg"><?= e($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="private_key">Tempel RSA Private Key (format PEM):</label>
        <textarea name="private_key" id="private_key" spellcheck="false" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"></textarea>

        <div class="file-row">
            atau pilih file key: <input type="file" id="keyfile" accept=".pem,.key,.txt">
        </div>

        <button type="submit">Masuk sebagai Admin</button>
    </form>

    <a href="../index.php" class="back-link">&larr; Kembali ke Awal</a>
</div>
<script>
    document.getElementById('keyfile').addEventListener('change', function (ev) {
        var f = ev.target.files[0];
        if (!f) return;
        var r = new FileReader();
        r.onload = function () { document.getElementById('private_key').value = r.result; };
        r.readAsText(f);
    });
</script>
</body>
</html>