<?php
// absensi.php — Absensi Saya & Form Isi Absensi Mandiri (Mahasiswa)
define('BASE_URL', '/uts_aplikasi');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Person.php';
require_once __DIR__ . '/classes/Karyawan.php';
require_once __DIR__ . '/includes/auth.php';
requireMahasiswa();

$mhs   = Mahasiswa::findById($_SESSION['user_id']);
$db    = Database::getInstance();
$conn  = $db->getConnection();
$mhsId = $mhs->getId();

$errorAbsen = '';

// ---- PROSES SIMPAN ABSENSI MANDIRI ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_absen'])) {
    $mkId           = (int)($_POST['mata_kuliah_id'] ?? 0);
    $pertemuanKe    = (int)($_POST['pertemuan_ke'] ?? 0);
    $status         = $_POST['status'] ?? 'hadir';
    $keterangan     = trim($_POST['keterangan'] ?? '');
    $tanggalHariIni = date('Y-m-d');

    $allowedStatus = ['hadir', 'izin', 'sakit'];

    if ($mkId <= 0 || $pertemuanKe <= 0 || $pertemuanKe > 16) {
        $errorAbsen = 'Pilih mata kuliah dan pertemuan ke- (1–16) yang valid.';
    } elseif (!in_array($status, $allowedStatus, true)) {
        $errorAbsen = 'Status kehadiran tidak valid.';
    } else {
        // 1. Cek apakah sesi pertemuan sudah ada di tabel pertemuan
        $cekPertemuan = $conn->prepare(
            "SELECT id FROM pertemuan WHERE mata_kuliah_id = ? AND pertemuan_ke = ? LIMIT 1"
        );
        $cekPertemuan->bind_param("ii", $mkId, $pertemuanKe);
        $cekPertemuan->execute();
        $rowPertemuan = $cekPertemuan->get_result()->fetch_assoc();

        if ($rowPertemuan) {
            $pertemuanId = (int)$rowPertemuan['id'];
        } else {
            // Jika belum dibuat dosen, otomatis buat sesi pertemuan untuk hari ini
            $materiDefault = "Pertemuan ke-" . $pertemuanKe;
            $buatPertemuan = $conn->prepare(
                "INSERT INTO pertemuan (mata_kuliah_id, pertemuan_ke, tanggal, materi) VALUES (?, ?, ?, ?)"
            );
            $buatPertemuan->bind_param("iiss", $mkId, $pertemuanKe, $tanggalHariIni, $materiDefault);
            $buatPertemuan->execute();
            $pertemuanId = (int)$conn->insert_id;
        }

        // 2. Simpan atau perbarui absensi mahasiswa
        $ketVal = $keterangan !== '' ? $keterangan : null;
        $simpanAbsen = $conn->prepare(
            "INSERT INTO absensi (pertemuan_id, mahasiswa_id, status, waktu_absen, keterangan)
             VALUES (?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), waktu_absen = NOW(), keterangan = VALUES(keterangan)"
        );
        $simpanAbsen->bind_param("iiss", $pertemuanId, $mhsId, $status, $ketVal);

        if ($simpanAbsen->execute()) {
            flashMessage('success', 'Absensi berhasil dicatat (' . ucfirst($status) . ' - Pertemuan ke-' . $pertemuanKe . ').');
            header("Location: absensi.php");
            exit;
        } else {
            $errorAbsen = 'Gagal menyimpan absensi: ' . $simpanAbsen->error;
        }
    }
}

// Filter matkul
$filterMk = (int)($_GET['mk'] ?? 0);

// Rekap per matkul
$rekap = $mhs->getRekapAbsensi($filterMk ?: null);

// Daftar matkul untuk dropdown & filter
$daftarMatkul = $conn->query("SELECT id, kode_mk, nama_mk FROM mata_kuliah ORDER BY nama_mk")->fetch_all(MYSQLI_ASSOC);

// Detail absensi
$stmt = $conn->prepare(
    "SELECT a.*, p.pertemuan_ke, p.tanggal, p.materi, mk.nama_mk, mk.id AS mk_id
     FROM absensi a
     JOIN pertemuan p ON p.id = a.pertemuan_id
     JOIN mata_kuliah mk ON mk.id = p.mata_kuliah_id
     WHERE a.mahasiswa_id = ?"
    . ($filterMk ? " AND mk.id = $filterMk" : "")
    . " ORDER BY p.tanggal DESC, p.pertemuan_ke DESC"
);
$stmt->bind_param("i", $mhsId);
$stmt->execute();
$detailAbsensi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Absensi Saya';
include __DIR__ . '/includes/header.php';
?>

<?php showFlash(); ?>

<?php if ($errorAbsen): ?>
<div class="alert alert-danger" style="margin-bottom:16px;">⚠️ <?= htmlspecialchars($errorAbsen) ?></div>
<?php endif; ?>

<div class="page-header">
  <h2>Absensi Saya</h2>
  <p>Catat kehadiran dan pantau riwayat per mata kuliah</p>
</div>

<!-- FORM ISI ABSENSI MANDIRI -->
<div class="card mb-24" style="border: 1.5px solid #3b82f6;">
  <div class="card-header" style="background: #eff6ff;">
    <h3 style="color: #1d4ed8;">✍️ Isi Absensi Kehadiran</h3>
    <span class="badge badge-primary"><?= date('d M Y') ?></span>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="submit_absen" value="1">
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:14px;">
        <div>
          <label class="form-label" style="font-size:12px;font-weight:600;margin-bottom:6px;display:block;">Mata Kuliah <span style="color:#ef4444;">*</span></label>
          <select name="mata_kuliah_id" class="form-control" required style="background:white;color:var(--text);">
            <option value="">— Pilih Mata Kuliah —</option>
            <?php foreach ($daftarMatkul as $mk): ?>
            <option value="<?= $mk['id'] ?>"><?= htmlspecialchars($mk['kode_mk'] . ' - ' . $mk['nama_mk']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="form-label" style="font-size:12px;font-weight:600;margin-bottom:6px;display:block;">Pertemuan Ke- <span style="color:#ef4444;">*</span></label>
          <select name="pertemuan_ke" class="form-control" required style="background:white;color:var(--text);">
            <?php for ($ke = 1; $ke <= 16; $ke++): ?>
            <option value="<?= $ke ?>">Pertemuan Ke-<?= $ke ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div>
          <label class="form-label" style="font-size:12px;font-weight:600;margin-bottom:6px;display:block;">Status Kehadiran <span style="color:#ef4444;">*</span></label>
          <select name="status" class="form-control" required style="background:white;color:var(--text);">
            <option value="hadir">✅ Hadir</option>
            <option value="izin">📩 Izin</option>
            <option value="sakit">🏥 Sakit</option>
          </select>
        </div>

        <div>
          <label class="form-label" style="font-size:12px;font-weight:600;margin-bottom:6px;display:block;">Keterangan (Opsional)</label>
          <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Hadir tepat waktu" style="background:white;color:var(--text);">
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;">
        <button type="submit" class="btn btn-primary" style="padding:10px 24px;font-weight:700;">
          ✓ Simpan Absensi
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Filter -->
<div class="card mb-24">
  <div class="card-body" style="padding:16px 20px;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div style="flex:1;min-width:180px;">
        <label class="form-label" style="font-size:12px;color:var(--muted);margin-bottom:6px;display:block;">Filter Riwayat Mata Kuliah</label>
        <select name="mk" class="form-control" onchange="this.form.submit()" style="background:white;color:var(--text);">
          <option value="">— Semua Mata Kuliah —</option>
          <?php foreach ($daftarMatkul as $mk): ?>
          <option value="<?= $mk['id'] ?>" <?= $filterMk == $mk['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mk['nama_mk']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($filterMk): ?>
      <a href="absensi.php" class="btn btn-outline btn-sm">Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Rekap Ringkasan -->
<?php foreach ($rekap as $r):
  $total = (int)$r['total_pertemuan'];
  $hadir = (int)$r['hadir'];
  $pct   = $total > 0 ? round(($hadir / $total) * 100) : 0;
  $barColor = $pct >= 75 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
?>
<div class="card mb-16" style="overflow:visible;">
  <div class="card-body">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
      <div>
        <div style="font-size:15px;font-weight:700;"><?= htmlspecialchars($r['nama_mk']) ?></div>
        <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($r['dosen_pengampu']) ?></div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <span class="badge badge-success">Hadir: <?= $r['hadir'] ?></span>
        <span class="badge badge-info">Izin: <?= $r['izin'] ?></span>
        <span class="badge badge-warning">Sakit: <?= $r['sakit'] ?></span>
        <span class="badge badge-danger">Alpha: <?= $r['alpha'] ?></span>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
      <div class="progress-bar" style="flex:1;height:8px;">
        <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
      </div>
      <span style="font-size:14px;font-weight:800;color:<?= $barColor ?>;min-width:44px;text-align:right;"><?= $pct ?>%</span>
    </div>
    <div style="font-size:11px;color:var(--muted);margin-top:4px;"><?= $hadir ?> dari <?= $total ?> pertemuan · <?= $pct >= 75 ? '✅ Memenuhi syarat' : '⚠️ Di bawah batas minimal 75%' ?></div>
  </div>
</div>
<?php endforeach; ?>

<!-- Detail Tabel -->
<div class="card mt-16">
  <div class="card-header">
    <h3>Detail Pertemuan</h3>
    <span class="badge badge-muted"><?= count($detailAbsensi) ?> data</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Tanggal</th>
          <th>Mata Kuliah</th>
          <th>Pertemuan Ke-</th>
          <th>Materi</th>
          <th>Status</th>
          <th>Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($detailAbsensi)): ?>
        <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--muted);">Belum ada data absensi.</td></tr>
        <?php else: ?>
        <?php
          $statusBadge = [
            'hadir' => 'badge-success',
            'izin'  => 'badge-info',
            'sakit' => 'badge-warning',
            'alpha' => 'badge-danger',
          ];
          foreach ($detailAbsensi as $i => $a):
        ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td style="white-space:nowrap;"><?= date('d M Y', strtotime($a['tanggal'])) ?></td>
          <td style="font-weight:600;font-size:13px;"><?= htmlspecialchars($a['nama_mk']) ?></td>
          <td style="text-align:center;"><?= $a['pertemuan_ke'] ?></td>
          <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($a['materi'] ?? '-') ?></td>
          <td><span class="badge <?= $statusBadge[$a['status']] ?? 'badge-muted' ?>"><?= ucfirst($a['status']) ?></span></td>
          <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($a['keterangan'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>