<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_login();

$list    = [];
$dbError = '';

try {
    foreach ($pdo->query("SELECT * FROM pegawai_terenkripsi ORDER BY id ASC")->fetchAll() as $r) {
        $list[] = ['row' => $r, 'data' => baca_baris($r)];
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

function potong(string $teks, int $batas = 96): string
{
    $panjang = strlen($teks);
    if ($panjang <= $batas) {
        return $teks;
    }
    return substr($teks, 0, $batas) . '... (' . $panjang . ' karakter)';
}

$label = ['nip' => 'NIP', 'nama' => 'Nama', 'jabatan' => 'Jabatan', 'email' => 'Email', 'telepon' => 'Telepon'];

admin_head('Enkripsi dan Dekripsi', 'enkripsi');
?>
<style>
    .pair { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .kolom { background: rgba(15, 32, 29, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 18px; }
    .kolom h4 { color: #7ee7df; font-size: 14px; margin-bottom: 14px; }
    .baris { margin-bottom: 10px; }
    .baris .k { display: block; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
    .baris .v { display: block; font-size: 14px; color: #e2e8f0; word-break: break-word; }
    .baris .v.cipher { font-family: Consolas, monospace; font-size: 12px; color: #7dd3c8; }
    @media (max-width: 900px) { .pair { grid-template-columns: 1fr; } }
</style>

<div class="card">
    <h1>Enkripsi dan Dekripsi Data</h1>
    <p>Halaman ini membandingkan data yang tersimpan di database (terenkripsi) dengan hasil dekripsinya menggunakan private key admin.</p>
    <p>Kolom kanan adalah isi database: data pegawai dienkripsi AES-256-GCM, lalu kunci AES-nya dibungkus RSA-OAEP. Kolom kiri adalah data asli setelah dibuka dengan private key. Tanpa key yang cocok, kolom kiri tetap berupa ciphertext.</p>
    <?php if ($dbError !== ''): ?>
        <div class="alert danger">Gagal membaca database: <?= e($dbError); ?></div>
    <?php endif; ?>
    <p>Total data: <strong class="hl"><?= count($list); ?></strong></p>
</div>

<?php if (count($list) === 0): ?>
    <div class="card"><p>Belum ada data pegawai.</p></div>
<?php endif; ?>

<?php foreach ($list as $i => $it): $r = $it['row']; $d = $it['data']; ?>
<div class="card">
    <h3>Pegawai <?= $i + 1; ?> (ID database: <?= (int)$r['id']; ?>)</h3>
    <div class="pair">
        <div class="kolom">
            <h4><?= $d !== null ? '&#128275; Hasil Dekripsi' : '&#128274; Tidak Dapat Didekripsi'; ?></h4>
            <?php $urut = 0; foreach ($label as $k => $nama): ?>
                <div class="baris"><span class="k"><?= e($nama); ?></span><span class="v"><?= sel_data($d, $r, $k, $urut++); ?></span></div>
            <?php endforeach; ?>
        </div>
        <div class="kolom">
            <h4>&#128274; Data Terenkripsi (di database)</h4>
            <div class="baris"><span class="k">Ciphertext (AES-256-GCM)</span><span class="v cipher"><?= ct(potong((string)$r['ciphertext'])); ?></span></div>
            <div class="baris"><span class="k">IV</span><span class="v cipher"><?= ct($r['iv']); ?></span></div>
            <div class="baris"><span class="k">Tag Autentikasi</span><span class="v cipher"><?= ct($r['tag']); ?></span></div>
            <div class="baris"><span class="k">Kunci AES Terbungkus (RSA-OAEP)</span><span class="v cipher"><?= ct(potong((string)$r['wrapped_key'])); ?></span></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php admin_foot(); ?>