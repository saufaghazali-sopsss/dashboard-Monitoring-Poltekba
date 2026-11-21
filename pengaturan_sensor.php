<?php
// pengaturan_sensor.php - Halaman Terpisah Pengaturan Ambang Batas Sensor
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "koneksi.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$myId    = intval($_SESSION['user_id']);

function labelRole($role) {
    return $role === 'admin' ? 'Admin' : 'User';
}

// ===== PROSES AKSI ADMIN (Ubah / Tambah / Reset / Hapus Batas Sensor) =====
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isAdmin) {
    $msg  = "";
    $type = "ok"; // ok | err

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msg = "Sesi form tidak valid. Silakan muat ulang halaman.";
        $type = "err";
    } else {
        $aksi = $_POST['aksi'] ?? '';

        // ---------- 1. EDIT / UBAH BATAS SENSOR ----------
        if ($aksi === 'edit') {
            $id         = intval($_POST['id'] ?? 0);
            $namaSensor = trim($_POST['nama_sensor'] ?? '');
            $satuan     = trim($_POST['satuan'] ?? '');
            $batasMin   = floatval($_POST['batas_min'] ?? 0);
            $batasMax   = floatval($_POST['batas_max'] ?? 0);
            $nilaiAlarm = floatval($_POST['nilai_alarm'] ?? 0);
            $deskripsi  = trim($_POST['deskripsi'] ?? '');

            if ($id <= 0 || $namaSensor === '') {
                $msg = "Nama sensor wajib diisi dan ID harus valid.";
                $type = "err";
            } else {
                $st = mysqli_prepare($koneksi, "UPDATE batas_sensor SET nama_sensor = ?, satuan = ?, batas_min = ?, batas_max = ?, nilai_alarm = ?, deskripsi = ?, updated_at = NOW() WHERE id = ?");
                if ($st) {
                    mysqli_stmt_bind_param($st, "sssddsi", $namaSensor, $satuan, $batasMin, $batasMax, $nilaiAlarm, $deskripsi, $id);
                    mysqli_stmt_execute($st);
                    mysqli_stmt_close($st);
                    $msg = "Batas sensor '{$namaSensor}' berhasil diperbarui.";
                } else {
                    $msg = "Gagal memperbarui database: " . mysqli_error($koneksi);
                    $type = "err";
                }
            }

        // ---------- 2. TAMBAH PARAMETER SENSOR BARU ----------
        } elseif ($aksi === 'tambah') {
            $namaSensor = trim($_POST['nama_sensor'] ?? '');
            $paramRaw   = trim($_POST['parameter'] ?? '');
            $parameter  = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $paramRaw));
            $satuan     = trim($_POST['satuan'] ?? '');
            $batasMin   = floatval($_POST['batas_min'] ?? 0);
            $batasMax   = floatval($_POST['batas_max'] ?? 0);
            $nilaiAlarm = floatval($_POST['nilai_alarm'] ?? 0);
            $deskripsi  = trim($_POST['deskripsi'] ?? '');

            if ($namaSensor === '' || $parameter === '') {
                $msg = "Nama sensor dan kode parameter wajib diisi.";
                $type = "err";
            } else {
                $cek = mysqli_prepare($koneksi, "SELECT id FROM batas_sensor WHERE parameter = ? LIMIT 1");
                mysqli_stmt_bind_param($cek, "s", $parameter);
                mysqli_stmt_execute($cek);
                mysqli_stmt_store_result($cek);
                $exists = mysqli_stmt_num_rows($cek) > 0;
                mysqli_stmt_close($cek);

                if ($exists) {
                    $msg = "Parameter '{$parameter}' sudah ada. Silakan gunakan kode parameter lain atau ubah yang sudah ada.";
                    $type = "err";
                } else {
                    $st = mysqli_prepare($koneksi, "INSERT INTO batas_sensor (nama_sensor, parameter, satuan, batas_min, batas_max, nilai_alarm, deskripsi, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    if ($st) {
                        mysqli_stmt_bind_param($st, "sssddds", $namaSensor, $parameter, $satuan, $batasMin, $batasMax, $nilaiAlarm, $deskripsi);
                        mysqli_stmt_execute($st);
                        mysqli_stmt_close($st);
                        $msg = "Batas sensor baru '{$namaSensor}' berhasil ditambahkan.";
                    } else {
                        $msg = "Gagal menyimpan sensor baru: " . mysqli_error($koneksi);
                        $type = "err";
                    }
                }
            }

        // ---------- 3. RESET KE STANDAR KALIBRASI PABRIK ----------
        } elseif ($aksi === 'reset') {
            // Update sensor suhu
            mysqli_query($koneksi, "UPDATE batas_sensor SET batas_min = 18.0, batas_max = 40.0, nilai_alarm = 55.0, satuan = '°C', updated_at = NOW() WHERE parameter = 'suhu'");
            // Update sensor asap
            mysqli_query($koneksi, "UPDATE batas_sensor SET batas_min = 0.0, batas_max = 350.0, nilai_alarm = 600.0, satuan = 'PPM', updated_at = NOW() WHERE parameter = 'asap'");
            // Update sensor kelembapan
            mysqli_query($koneksi, "UPDATE batas_sensor SET batas_min = 30.0, batas_max = 80.0, nilai_alarm = 20.0, satuan = '%', updated_at = NOW() WHERE parameter = 'kelembapan'");

            $msg = "Seluruh batas sensor berhasil dikembalikan ke standar kalibrasi awal (Suhu: 40/55°C, Asap: 350/600 PPM, Kelembapan: 30/80%).";

        // ---------- 4. HAPUS SENSOR TAMBAHAN ----------
        } elseif ($aksi === 'hapus') {
            $id = intval($_POST['id'] ?? 0);
            $cek = mysqli_prepare($koneksi, "SELECT parameter, nama_sensor FROM batas_sensor WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($cek, "i", $id);
            mysqli_stmt_execute($cek);
            $res = mysqli_stmt_get_result($cek);
            $r = mysqli_fetch_assoc($res);
            mysqli_stmt_close($cek);

            if (!$r) {
                $msg = "Data sensor tidak ditemukan.";
                $type = "err";
            } elseif (in_array(strtolower($r['parameter']), ['suhu', 'asap', 'kelembapan'], true)) {
                $msg = "Sensor utama sistem ('{$r['nama_sensor']}') tidak boleh dihapus. Anda dapat mengubah nilainya lewat tombol Edit.";
                $type = "err";
            } else {
                $del = mysqli_prepare($koneksi, "DELETE FROM batas_sensor WHERE id = ?");
                mysqli_stmt_bind_param($del, "i", $id);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);
                $msg = "Sensor kustom '{$r['nama_sensor']}' berhasil dihapus.";
            }
        }
    }

    $_SESSION['flash']      = $msg;
    $_SESSION['flash_type'] = $type;
    header("Location: pengaturan_sensor.php");
    exit;
}

$flash     = $_SESSION['flash'] ?? "";
$flashType = $_SESSION['flash_type'] ?? "ok";
unset($_SESSION['flash'], $_SESSION['flash_type']);

// Ambil daftar batas sensor
ensureBatasSensorDefault($koneksi);
$res = mysqli_query($koneksi, "SELECT * FROM batas_sensor ORDER BY id ASC");
$sensors = [];
while ($row = mysqli_fetch_assoc($res)) {
    $sensors[] = $row;
}

// Data ringkasan untuk preview cards
$map = [];
foreach ($sensors as $s) {
    $map[$s['parameter']] = $s;
}
$suhuInfo = $map['suhu'] ?? ['batas_min' => 18, 'batas_max' => 40, 'nilai_alarm' => 55, 'satuan' => '°C'];
$asapInfo = $map['asap'] ?? ['batas_min' => 0, 'batas_max' => 350, 'nilai_alarm' => 600, 'satuan' => 'PPM'];
$kelInfo  = $map['kelembapan'] ?? ['batas_min' => 30, 'batas_max' => 80, 'nilai_alarm' => 20, 'satuan' => '%'];

include "sidebar.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batas Sensor - Sistem Monitoring</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        .set-wrap { max-width: 1140px; margin: 0 auto; padding: 28px 24px 60px; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #1c1917; }
        .set-card { background: #fff; border: 1px solid #e7e5e4; border-radius: 14px; padding: 24px 28px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.02); position: relative; overflow: hidden; }
        .set-card.top::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #dc2626; }
        .set-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; }
        .set-card h1 { font-size: 22px; font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
        .set-card h2 { font-size: 17px; font-weight: 800; margin: 0; letter-spacing: -0.01em; }
        .set-card p { margin: 0; font-size: 13.5px; color: #57534e; }
        .list-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }

        /* ===== SUB-TABS PENGATURAN ===== */
        .set-tabs { display: flex; gap: 10px; margin-bottom: 22px; flex-wrap: wrap; }
        .set-tab-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 18px; border-radius: 10px; font-size: 13.5px; font-weight: 700; text-decoration: none; color: #64748b; background: #ffffff; border: 1px solid #e2e8f0; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .set-tab-btn:hover { color: #0f172a; background: #f8fafc; border-color: #cbd5e1; transform: translateY(-1px); }
        .set-tab-btn.active { color: #ffffff; background: #dc2626; border-color: #dc2626; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.22); }

        .set-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 15px; border-radius: 8px; font-size: 12.5px; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid #fecaca; background: #fff; color: #dc2626; font-family: inherit; transition: all 0.15s ease; }
        .set-btn:hover { background: #fef2f2; }
        .set-btn.plain { border-color: #d6d3d1; color: #1c1917; }
        .set-btn.plain:hover { background: #f5f5f4; }
        .set-btn.dark { background: #1c1917; border-color: #1c1917; color: #fff; }
        .set-btn.dark:hover { background: #292524; }
        .set-btn.amber { background: #fffbeb; border-color: #fde68a; color: #b45309; }
        .set-btn.amber:hover { background: #fef3c7; }

        .set-flash { padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .set-flash.ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .set-flash.err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        /* ===== CARDS GRID PREVIEW ===== */
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .p-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(15,23,42,0.04); position: relative; }
        .p-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .p-card-title { font-size: 14px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .p-card-badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; background: #f1f5f9; color: #475569; }
        .p-levels { display: flex; flex-direction: column; gap: 8px; }
        .p-level-row { display: flex; align-items: center; justify-content: space-between; font-size: 12.5px; }
        .p-level-label { color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .p-level-val { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 13.5px; }
        .val-waspada { color: #b45309; }
        .val-bahaya { color: #dc2626; }
        .val-normal { color: #15803d; }

        /* ===== INFO BANNER ===== */
        .info-banner { background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #0284c7; border-radius: 10px; padding: 14px 18px; margin-bottom: 22px; font-size: 13px; line-height: 1.55; color: #334155; }
        .info-banner strong { color: #0f172a; }
        .status-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; border-radius: 5px; font-size: 11px; font-weight: 800; font-family: inherit; }
        .status-pill.safe { background: #dcfce7; color: #15803d; }
        .status-pill.warn { background: #fef3c7; color: #b45309; }
        .status-pill.dang { background: #fee2e2; color: #dc2626; }

        /* ===== TABLE ===== */
        .set-table-wrap { overflow-x: auto; border: 1px solid #e7e5e4; border-radius: 10px; background: #fff; }
        .set-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        .set-table th { background: #fafaf9; padding: 12px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #78716c; white-space: nowrap; border-bottom: 1px solid #e7e5e4; }
        .set-table td { padding: 13px 16px; border-bottom: 1px solid #f5f5f4; vertical-align: middle; }
        .set-table tr:last-child td { border-bottom: none; }
        .set-table tr:hover { background: #fafaf9; }

        .param-badge { font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #334155; padding: 2px 7px; border-radius: 4px; border: 1px solid #e2e8f0; }
        .unit-badge { font-family: 'JetBrains Mono', monospace; font-size: 11.5px; font-weight: 700; color: #0284c7; }
        .thresh-val { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; }
        .thresh-val.waspada { color: #b45309; background: #fffbeb; padding: 3px 8px; border-radius: 6px; border: 1px solid #fef3c7; }
        .thresh-val.bahaya { color: #dc2626; background: #fef2f2; padding: 3px 8px; border-radius: 6px; border: 1px solid #fee2e2; }
        .thresh-val.normal { color: #15803d; background: #f0fdf4; padding: 3px 8px; border-radius: 6px; border: 1px solid #dcfce7; }

        .mini-btn { padding: 6px 12px; border-radius: 6px; border: 1px solid #d6d3d1; background: #fff; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 4px; }
        .mini-btn:hover { background: #f5f5f4; border-color: #a8a29e; }
        .mini-btn.del { color: #dc2626; border-color: #fecaca; }
        .mini-btn.del:hover { background: #fef2f2; }

        /* ===== MODAL ===== */
        .modal-bg { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: none; align-items: center; justify-content: center; z-index: 1500; padding: 16px; backdrop-filter: blur(4px); }
        .modal-bg.show { display: flex; }
        .modal { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; padding: 26px 26px 22px; box-shadow: 0 16px 40px rgba(15,23,42,.2); position: relative; overflow: hidden; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #1c1917; }
        .modal::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #dc2626; }
        .modal h3 { margin: 0 0 16px; font-size: 18px; font-weight: 800; letter-spacing: -0.01em; }
        .f-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .f-group { margin-bottom: 14px; }
        .f-group label { display: block; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #78716c; margin-bottom: 5px; }
        .f-group input, .f-group select, .f-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #e7e5e4; border-radius: 8px; background: #fafaf9; font-size: 13.5px; font-family: inherit; outline: none; transition: border-color 0.15s; }
        .f-group input:focus, .f-group textarea:focus { background: #fff; border-color: #dc2626; }
        .f-group textarea { resize: vertical; min-height: 64px; }
        .f-hint { font-size: 11.5px; color: #a8a29e; margin-top: 4px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }
        .btn-save { padding: 10px 18px; border: none; border-radius: 8px; background: #1c1917; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; transition: background 0.15s; }
        .btn-save:hover { background: #292524; }
        .btn-save.red { background: #dc2626; }
        .btn-save.red:hover { background: #b91c1c; }
        .btn-cancel { padding: 10px 16px; border: 1px solid #d6d3d1; border-radius: 8px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .btn-cancel:hover { background: #f5f5f4; }
        .del-text { font-size: 13.5px; line-height: 1.6; color: #57534e; margin: 0; }
        .role-notice { font-size: 12px; color: #b45309; background: #fffbeb; border: 1px solid #fef3c7; padding: 8px 12px; border-radius: 6px; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="set-wrap">

    <!-- PROFIL + LOGOUT -->
    <div class="set-card top">
        <div class="set-head">
            <div>
                <h1>⚙️ Pengaturan Batas Sensor</h1>
                <p>Login sebagai <strong><?php echo htmlspecialchars($_SESSION['nama']); ?></strong>
                   (<?php echo htmlspecialchars($_SESSION['username']); ?>) &middot; Role: <strong><?php echo htmlspecialchars(labelRole($_SESSION['role'])); ?></strong></p>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="dashboard.php" class="set-btn plain">← Dashboard</a>
                <a href="logout.php" class="set-btn" onclick="openLogoutModal(); return false;">🚪 Keluar</a>
            </div>
        </div>
    </div>

    <!-- TAB NAVIGASI PENGATURAN (TERPISAH DARI DAFTAR USER) -->
    <div class="set-tabs">
        <a href="pengaturan.php" class="set-tab-btn">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Manajemen Pengguna</span>
        </a>
        <a href="pengaturan_sensor.php" class="set-tab-btn active">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="21" x2="4" y2="14"></line>
                <line x1="4" y1="10" x2="4" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12" y2="3"></line>
                <line x1="20" y1="21" x2="20" y2="16"></line>
                <line x1="20" y1="12" x2="20" y2="3"></line>
                <line x1="1" y1="14" x2="7" y2="14"></line>
                <line x1="9" y1="8" x2="15" y2="8"></line>
                <line x1="17" y1="16" x2="23" y2="16"></line>
            </svg>
            <span>Batas & Ambang Sensor</span>
        </a>
    </div>

    <?php if (!$isAdmin): ?>
        <div class="role-notice">
            🔒 <strong>Mode Hanya Baca:</strong> Anda masuk sebagai User biasa. Hanya akun Admin yang berhak memodifikasi nilai ambang batas sensor.
        </div>
    <?php endif; ?>

    <!-- PANDUAN LOGIKA STATUS SENSOR -->
    <div class="info-banner">
        <strong>💡 Panduan Logika Ambang Batas Sensor:</strong>
        Perubahan nilai di bawah ini langsung memengaruhi perhitungan status telemetri di Dashboard, Tabel Sensor, dan Notifikasi:
        <ul style="margin: 6px 0 0 18px; padding: 0;">
            <li><span class="status-pill safe">🟢 AMAN</span>: Nilai sensor berada dalam batas normal operasi harian.</li>
            <li><span class="status-pill warn">🟡 WASPADA</span>: Nilai mencapai atau melebihi <strong>Batas Max (Ambang Waspada)</strong>. Peringatan dini diaktifkan.</li>
            <li><span class="status-pill dang">🔴 BAHAYA</span>: Nilai mencapai atau melebihi <strong>Nilai Alarm (Ambang Bahaya)</strong>. Alarm kebakaran/bahaya diaktifkan.</li>
        </ul>
    </div>

    <!-- PREVIEW RINGKASAN 3 SENSOR UTAMA -->
    <div class="preview-grid">
        <!-- CARD SUHU -->
        <div class="p-card">
            <div class="p-card-header">
                <span class="p-card-title">🌡️ Sensor Suhu</span>
                <span class="p-card-badge"><?php echo htmlspecialchars($suhuInfo['satuan'] ?? '°C'); ?></span>
            </div>
            <div class="p-levels">
                <div class="p-level-row">
                    <span class="p-level-label">Normal Min:</span>
                    <span class="p-level-val val-normal"><?php echo floatval($suhuInfo['batas_min']); ?> °C</span>
                </div>
                <div class="p-level-row">
                    <span class="p-level-label">Ambang Waspada:</span>
                    <span class="p-level-val val-waspada">≥ <?php echo floatval($suhuInfo['batas_max']); ?> °C</span>
                </div>
                <div class="p-level-row">
                    <span class="p-level-label">Alarm Bahaya:</span>
                    <span class="p-level-val val-bahaya">≥ <?php echo floatval($suhuInfo['nilai_alarm']); ?> °C</span>
                </div>
            </div>
        </div>

        <!-- CARD ASAP / GAS -->
        <div class="p-card">
            <div class="p-card-header">
                <span class="p-card-title">💨 Asap & Gas MQ-2</span>
                <span class="p-card-badge"><?php echo htmlspecialchars($asapInfo['satuan'] ?? 'PPM'); ?></span>
            </div>
            <div class="p-levels">
                <div class="p-level-row">
                    <span class="p-level-label">Normal Min:</span>
                    <span class="p-level-val val-normal"><?php echo floatval($asapInfo['batas_min']); ?> PPM</span>
                </div>
                <div class="p-level-row">
                    <span class="p-level-label">Ambang Waspada:</span>
                    <span class="p-level-val val-waspada">≥ <?php echo floatval($asapInfo['batas_max']); ?> PPM</span>
                </div>
                <div class="p-level-row">
                    <span class="p-level-label">Alarm Bahaya:</span>
                    <span class="p-level-val val-bahaya">≥ <?php echo floatval($asapInfo['nilai_alarm']); ?> PPM</span>
                </div>
            </div>
        </div>

        <!-- CARD KELEMBAPAN -->
        <div class="p-card">
            <div class="p-card-header">
                <span class="p-card-title">💧 Kelembapan Relatif</span>
                <span class="p-card-badge"><?php echo htmlspecialchars($kelInfo['satuan'] ?? '%'); ?></span>
            </div>
            <div class="p-levels">
                <div class="p-level-row">
                    <span class="p-level-label">Batas Kering:</span>
                    <span class="p-level-val val-waspada">&lt; <?php echo floatval($kelInfo['batas_min']); ?> %</span>
                </div>
                <div class="p-level-row">
                    <span class="p-level-label">Batas Lembap Max:</span>
                    <span class="p-level-val val-normal"><?php echo floatval($kelInfo['batas_max']); ?> %</span>
                </div>
                <div class="p-level-row">
                    <span class="p-level-label">Alarm Kering Kritis:</span>
                    <span class="p-level-val val-bahaya">≤ <?php echo floatval($kelInfo['nilai_alarm']); ?> %</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL KONFIGURASI BATAS SENSOR -->
    <div class="set-card">
        <div class="list-head">
            <div>
                <h2>🎛️ Konfigurasi Ambang Batas Sensor</h2>
                <p>Klik tombol <strong>Edit</strong> untuk mengubah batas peringatan dan pemicu alarm pada tiap sensor.</p>
            </div>
            <?php if ($isAdmin): ?>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="set-btn plain" onclick="openReset()">🔄 Reset Standar</button>
                <button type="button" class="set-btn dark" onclick="openAdd()">➕ Tambah Sensor</button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($flash !== ""): ?>
            <div class="set-flash <?php echo $flashType === 'err' ? 'err' : 'ok'; ?>">
                <?php echo ($flashType === 'ok' ? '✅' : '⚠️') . ' ' . htmlspecialchars($flash); ?>
            </div>
        <?php endif; ?>

        <div class="set-table-wrap">
            <table class="set-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Nama Sensor</th>
                        <th>Parameter</th>
                        <th>Satuan</th>
                        <th>Batas Min (Normal)</th>
                        <th>Batas Max (Waspada)</th>
                        <th>Nilai Alarm (Bahaya)</th>
                        <th>Keterangan</th>
                        <th>Terakhir Diperbarui</th>
                        <?php if ($isAdmin): ?><th style="text-align: right;">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sensors as $s): 
                    $isCore = in_array(strtolower($s['parameter']), ['suhu', 'asap', 'kelembapan'], true);
                ?>
                    <tr>
                        <td>#<?php echo $s['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($s['nama_sensor']); ?></strong>
                            <?php if ($isCore): ?>
                                <span style="font-size: 10px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 4px;">Sistem Inti</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="param-badge"><?php echo htmlspecialchars($s['parameter']); ?></span></td>
                        <td><span class="unit-badge"><?php echo htmlspecialchars($s['satuan'] ?? '-'); ?></span></td>
                        <td>
                            <span class="thresh-val normal"><?php echo floatval($s['batas_min']); ?> <?php echo htmlspecialchars($s['satuan'] ?? ''); ?></span>
                        </td>
                        <td>
                            <span class="thresh-val waspada">⚠️ ≥ <?php echo floatval($s['batas_max']); ?> <?php echo htmlspecialchars($s['satuan'] ?? ''); ?></span>
                        </td>
                        <td>
                            <span class="thresh-val bahaya">🚨 ≥ <?php echo floatval($s['nilai_alarm']); ?> <?php echo htmlspecialchars($s['satuan'] ?? ''); ?></span>
                        </td>
                        <td style="max-width: 260px; font-size: 12px; color: #64748b; white-space: normal;">
                            <?php echo htmlspecialchars($s['deskripsi'] ?? '-'); ?>
                        </td>
                        <td style="font-size: 12px; color: #78716c; white-space: nowrap;">
                            <?php 
                            $timeStr = $s['updated_at'] ?? $s['last_update'] ?? '';
                            echo $timeStr ? date('d M Y, H:i', strtotime($timeStr)) : '-'; 
                            ?>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td style="text-align: right; white-space: nowrap;">
                            <button type="button" class="mini-btn"
                                    data-id="<?php echo $s['id']; ?>"
                                    data-nama="<?php echo htmlspecialchars($s['nama_sensor'], ENT_QUOTES); ?>"
                                    data-param="<?php echo htmlspecialchars($s['parameter'], ENT_QUOTES); ?>"
                                    data-satuan="<?php echo htmlspecialchars($s['satuan'] ?? '', ENT_QUOTES); ?>"
                                    data-min="<?php echo floatval($s['batas_min']); ?>"
                                    data-max="<?php echo floatval($s['batas_max']); ?>"
                                    data-alarm="<?php echo floatval($s['nilai_alarm']); ?>"
                                    data-deskripsi="<?php echo htmlspecialchars($s['deskripsi'] ?? '', ENT_QUOTES); ?>"
                                    onclick="openEdit(this)">✏️ Ubah Batas</button>

                            <?php if (!$isCore): ?>
                                <button type="button" class="mini-btn del"
                                        data-id="<?php echo $s['id']; ?>"
                                        data-nama="<?php echo htmlspecialchars($s['nama_sensor'], ENT_QUOTES); ?>"
                                        onclick="openDelete(this)">🗑️ Hapus</button>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php if ($isAdmin): ?>

<!-- MODAL UBAH / EDIT BATAS SENSOR -->
<div class="modal-bg" id="editModal" onclick="if(event.target===this) closeAll()">
    <div class="modal">
        <h3>✏️ Ubah Batas Sensor</h3>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="e-id">

            <div class="f-group">
                <label for="e-nama">Nama Sensor</label>
                <input type="text" name="nama_sensor" id="e-nama" required>
            </div>

            <div class="f-row">
                <div class="f-group">
                    <label for="e-param">Parameter (Kunci)</label>
                    <input type="text" id="e-param" disabled style="background:#f1f5f9; color:#64748b; font-family:'JetBrains Mono',monospace;">
                    <div class="f-hint">Kunci parameter sistem telemetri</div>
                </div>
                <div class="f-group">
                    <label for="e-satuan">Satuan Pengukuran</label>
                    <input type="text" name="satuan" id="e-satuan" placeholder="Contoh: °C, PPM, %">
                </div>
            </div>

            <div class="f-row">
                <div class="f-group">
                    <label for="e-min">Batas Min (Normal)</label>
                    <input type="number" step="any" name="batas_min" id="e-min" required>
                    <div class="f-hint">Ambang bawah operasi normal</div>
                </div>
                <div class="f-group">
                    <label for="e-max" style="color: #b45309;">⚠️ Batas Max (Waspada)</label>
                    <input type="number" step="any" name="batas_max" id="e-max" required style="border-color: #fde68a;">
                    <div class="f-hint">Status Waspada jika nilai ≥ ini</div>
                </div>
            </div>

            <div class="f-group">
                <label for="e-alarm" style="color: #dc2626;">🚨 Nilai Alarm (Bahaya)</label>
                <input type="number" step="any" name="nilai_alarm" id="e-alarm" required style="border-color: #fecaca; font-weight: 700;">
                <div class="f-hint">Status Bahaya dan Sirine Alarm aktif jika nilai ≥ ini</div>
            </div>

            <div class="f-group">
                <label for="e-deskripsi">Deskripsi & Keterangan</label>
                <textarea name="deskripsi" id="e-deskripsi" placeholder="Tuliskan keterangan sensor dan panduan ambang batas..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAll()">Batal</button>
                <button type="submit" class="btn-save red">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH PARAMETER SENSOR -->
<div class="modal-bg" id="addModal" onclick="if(event.target===this) closeAll()">
    <div class="modal">
        <h3>➕ Tambah Sensor & Batas Baru</h3>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="aksi" value="tambah">

            <div class="f-group">
                <label for="a-nama">Nama Sensor</label>
                <input type="text" name="nama_sensor" id="a-nama" placeholder="Contoh: Sensor Api Flame IR" required>
            </div>

            <div class="f-row">
                <div class="f-group">
                    <label for="a-param">Kode Parameter (Slug)</label>
                    <input type="text" name="parameter" id="a-param" placeholder="contoh: api, co2, tekanan" required style="font-family:'JetBrains Mono',monospace;">
                    <div class="f-hint">Huruf kecil tanpa spasi</div>
                </div>
                <div class="f-group">
                    <label for="a-satuan">Satuan</label>
                    <input type="text" name="satuan" id="a-satuan" placeholder="Contoh: lux, hPa, PPM">
                </div>
            </div>

            <div class="f-row">
                <div class="f-group">
                    <label for="a-min">Batas Min</label>
                    <input type="number" step="any" name="batas_min" id="a-min" value="0" required>
                </div>
                <div class="f-group">
                    <label for="a-max" style="color: #b45309;">Batas Max (Waspada)</label>
                    <input type="number" step="any" name="batas_max" id="a-max" value="50" required>
                </div>
            </div>

            <div class="f-group">
                <label for="a-alarm" style="color: #dc2626;">Nilai Alarm (Bahaya)</label>
                <input type="number" step="any" name="nilai_alarm" id="a-alarm" value="80" required>
            </div>

            <div class="f-group">
                <label for="a-deskripsi">Deskripsi</label>
                <textarea name="deskripsi" id="a-deskripsi" placeholder="Keterangan fungsi sensor dan kriteria peringatan..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAll()">Batal</button>
                <button type="submit" class="btn-save">Simpan Sensor</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL KONFIRMASI RESET STANDAR -->
<div class="modal-bg" id="resetModal" onclick="if(event.target===this) closeAll()">
    <div class="modal">
        <h3>🔄 Kembalikan ke Standar Pabrik?</h3>
        <p class="del-text">
            Batas ambang sensor utama akan dikembalikan ke nilai standar kalibrasi rekomendasi:
        </p>
        <ul style="margin: 10px 0 16px 20px; font-size: 13px; color: #475569;">
            <li><strong>Suhu:</strong> Min 18 °C &middot; Waspada 40 °C &middot; Bahaya 55 °C</li>
            <li><strong>Asap & Gas:</strong> Min 0 PPM &middot; Waspada 350 PPM &middot; Bahaya 600 PPM</li>
            <li><strong>Kelembapan:</strong> Min 30 % &middot; Lembap Max 80 % &middot; Kering Kritis 20 %</li>
        </ul>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="aksi" value="reset">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAll()">Batal</button>
                <button type="submit" class="btn-save red">Ya, Reset Standar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL HAPUS SENSOR TAMBAHAN -->
<div class="modal-bg" id="delModal" onclick="if(event.target===this) closeAll()">
    <div class="modal">
        <h3>🗑️ Hapus Batas Sensor?</h3>
        <p class="del-text">Sensor kustom <strong id="d-nama"></strong> akan dihapus dari daftar batas pemantauan.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" id="d-id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAll()">Batal</button>
                <button type="submit" class="btn-save red">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<script>
function closeAll() {
    ['addModal', 'editModal', 'resetModal', 'delModal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('show');
    });
}

function openEdit(btn) {
    var d = btn.dataset;
    document.getElementById('e-id').value        = d.id;
    document.getElementById('e-nama').value      = d.nama;
    document.getElementById('e-param').value     = d.param;
    document.getElementById('e-satuan').value    = d.satuan;
    document.getElementById('e-min').value       = d.min;
    document.getElementById('e-max').value       = d.max;
    document.getElementById('e-alarm').value     = d.alarm;
    document.getElementById('e-deskripsi').value = d.deskripsi;
    document.getElementById('editModal').classList.add('show');
    document.getElementById('e-max').focus();
}

function openAdd() {
    document.getElementById('a-nama').value      = '';
    document.getElementById('a-param').value     = '';
    document.getElementById('a-satuan').value    = '';
    document.getElementById('a-min').value       = '0';
    document.getElementById('a-max').value       = '50';
    document.getElementById('a-alarm').value     = '80';
    document.getElementById('a-deskripsi').value = '';
    document.getElementById('addModal').classList.add('show');
    document.getElementById('a-nama').focus();
}

function openReset() {
    document.getElementById('resetModal').classList.add('show');
}

function openDelete(btn) {
    document.getElementById('d-id').value = btn.dataset.id;
    document.getElementById('d-nama').textContent = btn.dataset.nama;
    document.getElementById('delModal').classList.add('show');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAll();
});
</script>
<?php endif; ?>

</body>
</html>
