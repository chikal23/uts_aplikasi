<?php
// register.php — Halaman Pendaftaran Akun (Mahasiswa & Dosen/Admin)
define('BASE_URL', '/uts_aplikasi');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Person.php';
require_once __DIR__ . '/classes/Karyawan.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . (isDosen() ? "/admin/dashboard.php" : "/dashboard.php"));
    exit;
}

$error = '';
$success = '';
$roleType = $_POST['role_type'] ?? 'mahasiswa';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleType   = $_POST['role_type'] ?? 'mahasiswa';
    $nama       = trim($_POST['nama'] ?? '');
    $password   = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    $db   = Database::getInstance();
    $conn = $db->getConnection();

    if ($roleType === 'dosen') {
        // ---- PENDAFTARAN DOSEN / ADMIN ----
        $username  = trim($_POST['username'] ?? '');
        $roleDosen = ($_POST['role_dosen'] ?? 'dosen') === 'admin' ? 'admin' : 'dosen';

        if (empty($username) || empty($nama) || empty($password)) {
            $error = 'Username, Nama Lengkap, dan Password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } elseif ($password !== $konfirmasi) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            // Cek apakah username sudah ada di tabel dosen
            $cek = $conn->prepare("SELECT id FROM dosen WHERE username = ? LIMIT 1");
            $cek->bind_param("s", $username);
            $cek->execute();

            if ($cek->get_result()->num_rows > 0) {
                $error = 'Username tersebut sudah digunakan. Silakan pilih username lain.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $insert = $conn->prepare(
                    "INSERT INTO dosen (username, nama, password, role) VALUES (?, ?, ?, ?)"
                );
                $insert->bind_param("ssss", $username, $nama, $hashedPassword, $roleDosen);

                if ($insert->execute()) {
                    $success = 'Akun ' . strtoupper($roleDosen) . ' berhasil dibuat! Silakan masuk lewat tab Admin/Dosen menggunakan username "' . htmlspecialchars($username) . '".';
                    $_POST = [];
                } else {
                    $error = 'Gagal menyimpan akun dosen: ' . $insert->error;
                }
            }
        }
    } else {
        // ---- PENDAFTARAN MAHASISWA ----
        $nim      = trim($_POST['nim'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $kelas    = trim($_POST['kelas'] ?? 'TI-C');
        $angkatan = (int)($_POST['angkatan'] ?? 2025);

        if (empty($nim) || empty($nama) || empty($email) || empty($password)) {
            $error = 'NIM, Nama Lengkap, Email, dan Password wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } elseif ($password !== $konfirmasi) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            // Pastikan kolom email tersedia di tabel mahasiswa
            $cekKolom = $conn->query("SHOW COLUMNS FROM mahasiswa LIKE 'email'");
            if ($cekKolom && $cekKolom->num_rows === 0) {
                $conn->query("ALTER TABLE mahasiswa ADD COLUMN email VARCHAR(100) NULL AFTER nama");
            }

            $stmt = $conn->prepare("SELECT id FROM mahasiswa WHERE nim = ? OR email = ? LIMIT 1");
            $stmt->bind_param("ss", $nim, $email);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $error = 'NIM atau Email tersebut sudah terdaftar. Silakan langsung masuk.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $status = 'aktif';
                $insert = $conn->prepare(
                    "INSERT INTO mahasiswa (nim, nama, email, kelas, angkatan, password, status) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $insert->bind_param("ssssiss", $nim, $nama, $email, $kelas, $angkatan, $hashedPassword, $status);

                if ($insert->execute()) {
                    $success = 'Akun Mahasiswa berhasil dibuat! Silakan masuk menggunakan NIM atau Email Anda.';
                    $_POST = [];
                } else {
                    $error = 'Gagal menyimpan akun mahasiswa: ' . $insert->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buat Akun — AbsensiTI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-left">
    <div class="login-left-brand">
      <div class="logo-icon">
        <svg viewBox="0 0 24 24" style="width:22px;height:22px;"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm-2 14l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
      </div>
      <div>
        <h1>AbsensiTI</h1>
        <span>Sistem Absensi Mahasiswa</span>
      </div>
    </div>
    <div class="login-headline">
      <h2>Registrasi Akun <span>AbsensiTI</span></h2>
      <p>Pilih peran Anda sebagai Mahasiswa atau Dosen/Admin untuk membuat akun yang langsung terhubung ke database.</p>
    </div>
    <div class="login-features">
      <div class="login-feature">
        <div class="feat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">
          <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <div class="login-feature-text">
          <strong>Aktivasi Instan</strong>
          <span>Akun Mahasiswa maupun Dosen langsung tersimpan dan aktif untuk login</span>
        </div>
      </div>
    </div>
  </div>

  <div class="login-right">
    <div class="login-card">
      <div class="login-card-header">
        <h3>Buat Akun Baru</h3>
        <p>Pilih jenis akun yang ingin didaftarkan</p>
      </div>

      <?php if ($error): ?>
      <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:20px;">
        ⚠️ <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.35);color:#6ee7b7;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:20px;">
        ✅ <?= $success ?>
        <div style="margin-top:8px;">
          <a href="<?= BASE_URL ?>/index.php" style="color:#fff;font-weight:700;text-decoration:underline;">Klik di sini untuk Masuk →</a>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST">
        <!-- Tab pilih peran -->
        <div style="display:flex;gap:8px;margin-bottom:20px;background:rgba(255,255,255,.05);border-radius:8px;padding:4px;">
          <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px 12px;border-radius:6px;cursor:pointer;font-size:13px;color:rgba(255,255,255,.5);transition:all .2s;" id="regTabMhs">
            <input type="radio" name="role_type" value="mahasiswa" <?= $roleType !== 'dosen' ? 'checked' : '' ?> onchange="switchRegRole('mahasiswa')" style="display:none;">
            Mahasiswa
          </label>
          <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px 12px;border-radius:6px;cursor:pointer;font-size:13px;color:rgba(255,255,255,.5);transition:all .2s;" id="regTabDosen">
            <input type="radio" name="role_type" value="dosen" <?= $roleType === 'dosen' ? 'checked' : '' ?> onchange="switchRegRole('dosen')" style="display:none;">
            Dosen / Admin
          </label>
        </div>

        <div class="form-group">
          <label>NAMA LENGKAP (BESERTA GELAR JIKA DOSEN)</label>
          <input type="text" name="nama" class="form-control" placeholder="Masukkan nama lengkap"
            value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
        </div>

        <!-- FIELD KHUSUS MAHASISWA -->
        <div id="fieldsMahasiswa">
          <div class="form-group">
            <label>NIM MAHASISWA</label>
            <input type="text" name="nim" id="inputNim" class="form-control" placeholder="Contoh: 2025006"
              value="<?= htmlspecialchars($_POST['nim'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>EMAIL</label>
            <input type="email" name="email" id="inputEmail" class="form-control" placeholder="nama@email.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>

          <div style="display:flex;gap:12px;">
            <div class="form-group" style="flex:1;">
              <label>KELAS</label>
              <input type="text" name="kelas" class="form-control" value="<?= htmlspecialchars($_POST['kelas'] ?? 'TI-C') ?>">
            </div>
            <div class="form-group" style="flex:1;">
              <label>ANGKATAN</label>
              <input type="number" name="angkatan" class="form-control" value="<?= htmlspecialchars($_POST['angkatan'] ?? '2025') ?>">
            </div>
          </div>
        </div>

        <!-- FIELD KHUSUS DOSEN / ADMIN -->
        <div id="fieldsDosen" style="display:none;">
          <div class="form-group">
            <label>USERNAME LOGIN DOSEN / ADMIN</label>
            <input type="text" name="username" id="inputUsername" class="form-control" placeholder="Contoh: dosen_agung"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>PERAN AKSES</label>
            <select name="role_dosen" class="form-control" style="background:#1e293b;color:#fff;">
              <option value="dosen" <?= ($_POST['role_dosen'] ?? '') === 'dosen' ? 'selected' : '' ?>>Dosen</option>
              <option value="admin" <?= ($_POST['role_dosen'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>PASSWORD</label>
          <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
        </div>

        <div class="form-group">
          <label>KONFIRMASI PASSWORD</label>
          <input type="password" name="konfirmasi_password" class="form-control" placeholder="Ulangi password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
          Daftar Akun Sekarang →
        </button>

        <div style="text-align:center;margin-top:16px;font-size:13px;color:rgba(255,255,255,.5);">
          Sudah punya akun?
          <a href="<?= BASE_URL ?>/index.php" style="color:#60a5fa;text-decoration:none;font-weight:600;margin-left:4px;">
            Masuk di sini
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
var regTabMhs   = document.getElementById('regTabMhs');
var regTabDosen = document.getElementById('regTabDosen');
var fieldsMhs   = document.getElementById('fieldsMahasiswa');
var fieldsDosen = document.getElementById('fieldsDosen');
var activeStyle = 'background:rgba(99,102,241,.2);color:white;border:1px solid rgba(99,102,241,.3);';
var inactiveStyle = '';

function switchRegRole(role) {
    if (role === 'dosen') {
        regTabDosen.style.cssText = activeStyle;
        regTabMhs.style.cssText   = inactiveStyle;
        fieldsDosen.style.display = 'block';
        fieldsMhs.style.display   = 'none';
        document.getElementById('inputUsername').required = true;
        document.getElementById('inputNim').required      = false;
        document.getElementById('inputEmail').required    = false;
    } else {
        regTabMhs.style.cssText   = activeStyle;
        regTabDosen.style.cssText = inactiveStyle;
        fieldsMhs.style.display   = 'block';
        fieldsDosen.style.display = 'none';
        document.getElementById('inputUsername').required = false;
        document.getElementById('inputNim').required      = true;
        document.getElementById('inputEmail').required    = true;
    }
}

// Jalankan saat halaman dimuat sesuai pilihan terakhir
switchRegRole('<?= $roleType === 'dosen' ? 'dosen' : 'mahasiswa' ?>');
</script>
</body>
</html>