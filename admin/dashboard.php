<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_login();

$total   = 0;
$gagal   = 0;
$terbaru = [];
$dbError = '';

try {
    $rows  = $pdo->query("SELECT * FROM pegawai_terenkripsi ORDER BY id DESC")->fetchAll();
    $total = count($rows);
    foreach ($rows as $r) {
        $d = baca_baris($r);
        if ($d === null) {
            $gagal++;
        }
        if (count($terbaru) < 5) {
            $terbaru[] = ['row' => $r, 'data' => $d];
        }
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

admin_head('Dashboard', 'dashboard');
?>
<div class="card">
    <h1>Dashboard Administrator</h1>
    <p>Data pada halaman admin dibuka dengan mendekripsi isi database menggunakan private key. Jika key tidak cocok, data tetap tampil sebagai ciphertext.</p>
</div>

<div class="card">
    <h3>Statistik Sistem</h3>
    <?php if ($dbError !== ''): ?>
        <div class="alert danger">Gagal membaca database: <?= e($dbError); ?></div>
    <?php endif; ?>
    <p>Total pegawai tersimpan: <strong class="hl"><?= $total; ?> orang</strong></p>
    <p>Status autentikasi: <?= admin_terbuka() ? 'private key cocok, akses penuh.' : 'private key tidak cocok, mode terkunci.'; ?></p>
    <p>Status dekripsi: <?= $total - $gagal; ?> dari <?= $total; ?> data berhasil didekripsi (AES-256-GCM + RSA-OAEP).</p>
</div>

<div class="card">
    <h3>5 Pegawai Terbaru <?= admin_terbuka() ? '(hasil dekripsi)' : '(ciphertext)'; ?></h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>NIP</th><th>Nama</th><th>Jabatan</th></tr></thead>
            <tbody>
            <?php if (count($terbaru) > 0): ?>
                <?php foreach ($terbaru as $it): ?>
                    <tr>
                        <td><?= sel_data($it['data'], $it['row'], 'nip', 0); ?></td>
                        <td><?= sel_data($it['data'], $it['row'], 'nama', 1); ?></td>
                        <td><?= sel_data($it['data'], $it['row'], 'jabatan', 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3">Belum ada data pegawai.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php admin_foot(); ?>