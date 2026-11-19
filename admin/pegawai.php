<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_login();

$fields = ['nip', 'nama', 'jabatan', 'email', 'telepon'];

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = [$type, $msg];
}

// Salinan data publik (nama dan jabatan). NIP, email, telepon hanya ada di paket terenkripsi.
function sync_publik(PDO $pdo, int $id, array $data): void
{
    if ($id <= 0) {
        return;
    }
    try {
        $st = $pdo->prepare("INSERT INTO pegawai_publik (pegawai_id, nama, jabatan) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nama = ?, jabatan = ?");
        $st->execute([$id, $data['nama'], $data['jabatan'], $data['nama'], $data['jabatan']]);
    } catch (Throwable $e) {
        // tabel pegawai_publik belum dibuat: abaikan
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Mode terkunci: hanya boleh melihat
    if (!admin_terbuka()) {
        flash('danger', 'Mode terkunci: data tidak dapat diubah tanpa private key yang cocok.');
        header('Location: pegawai.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add' || $action === 'edit') {
            $data = [];
            foreach ($fields as $f) {
                $data[$f] = trim((string)($_POST[$f] ?? ''));
            }

            if ($data['nip'] === '' || $data['nama'] === '') {
                flash('danger', 'NIP dan Nama wajib diisi.');
            } else {
                $enc = encrypt_content((string)json_encode($data, JSON_UNESCAPED_UNICODE));

                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO pegawai_terenkripsi (ciphertext, iv, tag, wrapped_key) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$enc['ciphertext'], $enc['iv'], $enc['tag'], $enc['wrapped_key']]);
                    sync_publik($pdo, (int)$pdo->lastInsertId(), $data);
                    flash('success', 'Data pegawai dienkripsi dan disimpan ke database.');
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE pegawai_terenkripsi SET ciphertext = ?, iv = ?, tag = ?, wrapped_key = ? WHERE id = ?");
                    $stmt->execute([$enc['ciphertext'], $enc['iv'], $enc['tag'], $enc['wrapped_key'], $id]);
                    sync_publik($pdo, $id, $data);
                    flash('success', 'Data pegawai diperbarui dan dienkripsi ulang.');
                }
            }
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM pegawai_terenkripsi WHERE id = ?");
            $stmt->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Data pegawai dihapus.');
        }
    } catch (Throwable $e) {
        flash('danger', 'Terjadi kesalahan: ' . $e->getMessage());
    }

    header('Location: pegawai.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$list = [];
try {
    foreach ($pdo->query("SELECT * FROM pegawai_terenkripsi ORDER BY id DESC")->fetchAll() as $r) {
        $list[] = ['row' => $r, 'data' => baca_baris($r)];
    }
} catch (Throwable $e) {
    $flash = ['danger', 'Gagal membaca database: ' . $e->getMessage()];
}

$edit = null;
if (admin_terbuka() && isset($_GET['edit'])) {
    foreach ($list as $it) {
        if ((int)$it['row']['id'] === (int)$_GET['edit'] && $it['data']) {
            $edit = $it['data'] + ['id' => $it['row']['id']];
            break;
        }
    }
}
$val = function (string $k) use ($edit): string {
    return e($edit[$k] ?? '');
};

admin_head('Data Pegawai', 'pegawai');
?>
<div class="card">
    <h1>Manajemen Data Pegawai</h1>
    <p>Data dienkripsi sebelum masuk database dan didekripsi dengan private key admin saat ditampilkan.</p>

    <?php if ($flash): ?>
        <div class="alert <?= e($flash[0]); ?>"><?= e($flash[1]); ?></div>
    <?php endif; ?>

    <?php if (admin_terbuka()): ?>
    <h3 style="margin-top: 20px;"><?= $edit ? 'Edit Data Pegawai' : '+ Tambah Pegawai Baru'; ?></h3>
    <form method="POST" action="pegawai.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add'; ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id']; ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="form-group"><label>NIP</label><input type="text" name="nip" required value="<?= $val('nip'); ?>"></div>
            <div class="form-group"><label>Nama Lengkap &amp; Gelar</label><input type="text" name="nama" required value="<?= $val('nama'); ?>"></div>
            <div class="form-group"><label>Jabatan</label><input type="text" name="jabatan" value="<?= $val('jabatan'); ?>"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= $val('email'); ?>"></div>
            <div class="form-group full"><label>Telepon / WhatsApp</label><input type="text" name="telepon" value="<?= $val('telepon'); ?>"></div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top: 14px;"><?= $edit ? 'Simpan Perubahan' : 'Enkripsi &amp; Simpan'; ?></button>
        <?php if ($edit): ?><a href="pegawai.php" class="btn btn-edit" style="margin-top: 14px;">Batal</a><?php endif; ?>
    </form>
    <?php else: ?>
        <p>Form tambah dan ubah data hanya tersedia untuk admin dengan private key yang cocok.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Daftar Pegawai <?= admin_terbuka() ? '(hasil dekripsi)' : '(ciphertext)'; ?></h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>No</th><th>NIP</th><th>Nama</th><th>Jabatan</th><th>Email</th><th>Telepon</th><th>Ciphertext di DB</th><?php if (admin_terbuka()): ?><th>Aksi</th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php if (count($list) > 0): ?>
                <?php $no = 1; foreach ($list as $it): $r = $it['row']; $d = $it['data']; ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= sel_data($d, $r, 'nip', 0); ?></td>
                        <td><?= sel_data($d, $r, 'nama', 1); ?></td>
                        <td><?= sel_data($d, $r, 'jabatan', 2); ?></td>
                        <td><?= sel_data($d, $r, 'email', 3); ?></td>
                        <td><?= sel_data($d, $r, 'telepon', 4); ?></td>
                        <td class="cipher"><?= ct(substr($r['ciphertext'], 0, 24) . '...'); ?></td>
                        <?php if (admin_terbuka()): ?>
                        <td style="white-space: nowrap;">
                            <?php if ($d): ?><a href="pegawai.php?edit=<?= (int)$r['id']; ?>" class="btn btn-edit">Edit</a><?php endif; ?>
                            <form method="POST" action="pegawai.php" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus data ini?')">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$r['id']; ?>">
                                <button type="submit" class="btn btn-danger">Hapus</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center; padding: 28px;">Belum ada data pegawai.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php admin_foot(); ?>