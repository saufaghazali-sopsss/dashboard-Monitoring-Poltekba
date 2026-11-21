<?php
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

// Role hanya 2 pilihan: admin dan user biasa (disimpan sebagai 'viewer' di database)
function labelRole($role) {
    return $role === 'admin' ? 'Admin' : 'User';
}

// ===== Proses aksi admin (tambah / edit / hapus) =====
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isAdmin) {
    $msg  = "";
    $type = "ok"; // ok | err

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msg = "Sesi form tidak valid.";
        $type = "err";
    } else {
        $id   = intval($_POST['id'] ?? 0);
        $aksi = $_POST['aksi'] ?? '';

        // ---------- TAMBAH USER ----------
        if ($aksi === 'tambah') {
            $nama     = trim($_POST['nama_lengkap'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $pass     = $_POST['password'] ?? '';
            $role     = $_POST['role'] ?? '';
            $status   = $_POST['status'] ?? 'aktif';

            if ($nama === '' || $username === '' || $pass === '') {
                $msg = "Nama lengkap, username, dan password wajib diisi.";
                $type = "err";
            } elseif (strlen($pass) < 6) {
                $msg = "Password minimal 6 karakter.";
                $type = "err";
            } elseif (!in_array($role, ['admin', 'viewer'], true) || !in_array($status, ['aktif', 'nonaktif'], true)) {
                $msg = "Role atau status tidak valid.";
                $type = "err";
            } else {
                $cek = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? LIMIT 1");
                mysqli_stmt_bind_param($cek, "s", $username);
                mysqli_stmt_execute($cek);
                mysqli_stmt_store_result($cek);
                $dipakai = mysqli_stmt_num_rows($cek) > 0;
                mysqli_stmt_close($cek);

                if ($dipakai) {
                    $msg = "Username sudah dipakai.";
                    $type = "err";
                } else {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $st = mysqli_prepare($koneksi, "INSERT INTO users (nama_lengkap, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($st, "sssss", $nama, $username, $hash, $role, $status);
                    mysqli_stmt_execute($st);
                    mysqli_stmt_close($st);
                    $msg = "User baru berhasil ditambahkan.";
                }
            }

        // ---------- HAPUS USER ----------
        } elseif ($aksi === 'hapus') {
            if ($id === $myId) {
                $msg = "Anda tidak bisa menghapus akun sendiri.";
                $type = "err";
            } else {
                $st = mysqli_prepare($koneksi, "DELETE FROM users WHERE id = ?");
                mysqli_stmt_bind_param($st, "i", $id);
                mysqli_stmt_execute($st);
                $terhapus = mysqli_stmt_affected_rows($st);
                mysqli_stmt_close($st);
                if ($terhapus > 0) {
                    $msg = "User berhasil dihapus.";
                } else {
                    $msg = "User tidak ditemukan.";
                    $type = "err";
                }
            }

        // ---------- EDIT USER ----------
        } elseif ($aksi === 'edit') {
            $nama     = trim($_POST['nama_lengkap'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $role     = $_POST['role'] ?? '';
            $status   = $_POST['status'] ?? '';
            $passBaru = $_POST['password'] ?? '';

            if ($nama === '' || $username === '') {
                $msg = "Nama lengkap dan username wajib diisi.";
                $type = "err";
            } elseif ($passBaru !== '' && strlen($passBaru) < 6) {
                $msg = "Password baru minimal 6 karakter.";
                $type = "err";
            } elseif ($id !== $myId && (!in_array($role, ['admin', 'viewer'], true) || !in_array($status, ['aktif', 'nonaktif'], true))) {
                $msg = "Role atau status tidak valid.";
                $type = "err";
            } else {
                $cek = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
                mysqli_stmt_bind_param($cek, "si", $username, $id);
                mysqli_stmt_execute($cek);
                mysqli_stmt_store_result($cek);
                $dipakai = mysqli_stmt_num_rows($cek) > 0;
                mysqli_stmt_close($cek);

                if ($dipakai) {
                    $msg = "Username sudah dipakai user lain.";
                    $type = "err";
                } else {
                    if ($id === $myId) {
                        if ($passBaru !== '') {
                            $hash = password_hash($passBaru, PASSWORD_DEFAULT);
                            $st = mysqli_prepare($koneksi, "UPDATE users SET nama_lengkap = ?, username = ?, password = ? WHERE id = ?");
                            mysqli_stmt_bind_param($st, "sssi", $nama, $username, $hash, $id);
                        } else {
                            $st = mysqli_prepare($koneksi, "UPDATE users SET nama_lengkap = ?, username = ? WHERE id = ?");
                            mysqli_stmt_bind_param($st, "ssi", $nama, $username, $id);
                        }
                    } else {
                        if ($passBaru !== '') {
                            $hash = password_hash($passBaru, PASSWORD_DEFAULT);
                            $st = mysqli_prepare($koneksi, "UPDATE users SET nama_lengkap = ?, username = ?, role = ?, status = ?, password = ? WHERE id = ?");
                            mysqli_stmt_bind_param($st, "sssssi", $nama, $username, $role, $status, $hash, $id);
                        } else {
                            $st = mysqli_prepare($koneksi, "UPDATE users SET nama_lengkap = ?, username = ?, role = ?, status = ? WHERE id = ?");
                            mysqli_stmt_bind_param($st, "ssssi", $nama, $username, $role, $status, $id);
                        }
                    }
                    mysqli_stmt_execute($st);
                    mysqli_stmt_close($st);

                    if ($id === $myId) {
                        $_SESSION['nama']     = $nama;
                        $_SESSION['username'] = $username;
                    }
                    $msg = "Data user berhasil diperbarui.";
                }
            }
        }
    }

    $_SESSION['flash']      = $msg;
    $_SESSION['flash_type'] = $type;
    header("Location: pengaturan.php");
    exit;
}

$flash     = $_SESSION['flash'] ?? "";
$flashType = $_SESSION['flash_type'] ?? "ok";
unset($_SESSION['flash'], $_SESSION['flash_type']);

// ===== Ambil daftar user =====
if ($isAdmin) {
    $res = mysqli_query($koneksi, "SELECT id, nama_lengkap, username, role, status, last_login, created_at FROM users ORDER BY id ASC");
} else {
    $st = mysqli_prepare($koneksi, "SELECT id, nama_lengkap, username, role, status, last_login, created_at FROM users WHERE id = ?");
    mysqli_stmt_bind_param($st, "i", $myId);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
}
$users = [];
while ($r = mysqli_fetch_assoc($res)) {
    $users[] = $r;
}

include "sidebar.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Sistem Monitoring</title>
    <style>
        .set-wrap { max-width: 1140px; margin: 0 auto; padding: 28px 24px 60px; font-family: 'Inter', system-ui, sans-serif; color: #1c1917; }
        .set-card { background: #fff; border: 1px solid #e7e5e4; border-radius: 14px; padding: 24px 28px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.02); position: relative; overflow: hidden; }
        .set-card.top::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #dc2626; }
        .set-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; }
        .set-card h1 { font-size: 22px; font-weight: 800; margin: 0 0 4px; }
        .set-card h2 { font-size: 17px; font-weight: 800; margin: 0; }
        .set-card p { margin: 0; font-size: 13.5px; color: #57534e; }
        .list-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        .set-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 15px; border-radius: 8px; font-size: 12.5px; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid #fecaca; background: #fff; color: #dc2626; font-family: inherit; }
        .set-btn:hover { background: #fef2f2; }
        .set-btn.plain { border-color: #d6d3d1; color: #1c1917; }
        .set-btn.plain:hover { background: #f5f5f4; }
        .set-btn.dark { background: #1c1917; border-color: #1c1917; color: #fff; }
        .set-btn.dark:hover { background: #292524; }
        .set-flash { padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 18px; }
        .set-flash.ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .set-flash.err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .set-table-wrap { overflow-x: auto; border: 1px solid #e7e5e4; border-radius: 10px; }
        .set-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        .set-table th { background: #fafaf9; padding: 12px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #78716c; white-space: nowrap; border-bottom: 1px solid #e7e5e4; }
        .set-table td { padding: 12px 16px; border-bottom: 1px solid #f5f5f4; white-space: nowrap; vertical-align: middle; }
        .set-table tr:last-child td { border-bottom: none; }
        .tag { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 700; }
        .tag.aktif { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .tag.nonaktif { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .tag.role { background: #f5f5f4; color: #44403c; border: 1px solid #e7e5e4; }
        .tag.role.admin { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .me { font-size: 11px; color: #78716c; margin-left: 6px; }
        .mini-btn { padding: 6px 12px; border-radius: 6px; border: 1px solid #d6d3d1; background: #fff; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .mini-btn:hover { background: #f5f5f4; }
        .mini-btn.del { color: #dc2626; border-color: #fecaca; }
        .mini-btn.del:hover { background: #fef2f2; }

        /* ===== MODAL (tambah / edit / hapus) ===== */
        .modal-bg { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: none; align-items: center; justify-content: center; z-index: 1500; padding: 16px; }
        .modal-bg.show { display: flex; }
        .modal { background: #fff; border-radius: 14px; width: 100%; max-width: 440px; padding: 26px 26px 22px; box-shadow: 0 12px 40px rgba(15,23,42,.2); position: relative; overflow: hidden; font-family: 'Inter', system-ui, sans-serif; color: #1c1917; }
        .modal::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #dc2626; }
        .modal h3 { margin: 0 0 16px; font-size: 18px; font-weight: 800; }
        .f-group { margin-bottom: 14px; }
        .f-group label { display: block; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #78716c; margin-bottom: 5px; }
        .f-group input, .f-group select { width: 100%; padding: 10px 12px; border: 1px solid #e7e5e4; border-radius: 8px; background: #fafaf9; font-size: 14px; font-family: inherit; outline: none; }
        .f-group input:focus, .f-group select:focus { background: #fff; border-color: #a8a29e; }
        .f-group input:disabled, .f-group select:disabled { background: #f5f5f4; color: #a8a29e; cursor: not-allowed; }
        .f-hint { font-size: 11.5px; color: #a8a29e; margin-top: 4px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
        .btn-save { padding: 10px 18px; border: none; border-radius: 8px; background: #1c1917; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-save:hover { background: #292524; }
        .btn-save.red { background: #dc2626; }
        .btn-save.red:hover { background: #b91c1c; }
        .btn-cancel { padding: 10px 16px; border: 1px solid #d6d3d1; border-radius: 8px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .btn-cancel:hover { background: #f5f5f4; }
        .del-text { font-size: 14px; line-height: 1.6; color: #57534e; margin: 0; }

        /* ===== SUB-TABS PENGATURAN ===== */
        .set-tabs { display: flex; gap: 10px; margin-bottom: 22px; flex-wrap: wrap; }
        .set-tab-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 18px; border-radius: 10px; font-size: 13.5px; font-weight: 700; text-decoration: none; color: #64748b; background: #ffffff; border: 1px solid #e2e8f0; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .set-tab-btn:hover { color: #0f172a; background: #f8fafc; border-color: #cbd5e1; transform: translateY(-1px); }
        .set-tab-btn.active { color: #ffffff; background: #dc2626; border-color: #dc2626; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.22); }
    </style>
</head>
<body>
<div class="set-wrap">

    <!-- PROFIL + LOGOUT -->
    <div class="set-card top">
        <div class="set-head">
            <div>
                <h1>⚙️ Pengaturan Pengguna</h1>
                <p>Login sebagai <strong><?php echo htmlspecialchars($_SESSION['nama']); ?></strong>
                   (<?php echo htmlspecialchars($_SESSION['username']); ?>) &middot; Role: <strong><?php echo htmlspecialchars(labelRole($_SESSION['role'])); ?></strong></p>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="dashboard.php" class="set-btn plain">← Dashboard</a>
                <a href="logout.php" class="set-btn" onclick="openLogoutModal(); return false;">🚪 Keluar</a>
            </div>
        </div>
    </div>

    <!-- TAB NAVIGASI PENGATURAN -->
    <div class="set-tabs">
        <a href="pengaturan.php" class="set-tab-btn active">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Manajemen Pengguna</span>
        </a>
        <a href="pengaturan_sensor.php" class="set-tab-btn">
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

    <!-- DAFTAR USER -->
    <div class="set-card">
        <div class="list-head">
            <h2>👥 <?php echo $isAdmin ? 'Daftar User' : 'Akun Saya'; ?></h2>
            <?php if ($isAdmin): ?>
                <button type="button" class="set-btn dark" onclick="openAdd()">➕ Tambah User</button>
            <?php endif; ?>
        </div>

        <?php if ($flash !== ""): ?>
            <div class="set-flash <?php echo $flashType === 'err' ? 'err' : 'ok'; ?>"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>

        <div class="set-table-wrap">
            <table class="set-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th>Dibuat</th>
                        <?php if ($isAdmin): ?><th>Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): $isMe = ($u['id'] == $myId); ?>
                    <tr>
                        <td>#<?php echo $u['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($u['nama_lengkap']); ?></strong>
                            <?php if ($isMe): ?><span class="me">(Anda)</span><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><span class="tag role <?php echo $u['role'] === 'admin' ? 'admin' : ''; ?>"><?php echo labelRole($u['role']); ?></span></td>
                        <td><span class="tag <?php echo $u['status'] === 'aktif' ? 'aktif' : 'nonaktif'; ?>"><?php echo htmlspecialchars($u['status']); ?></span></td>
                        <td><?php echo $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : '-'; ?></td>
                        <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                        <?php if ($isAdmin): ?>
                        <td>
                            <button type="button" class="mini-btn"
                                    data-id="<?php echo $u['id']; ?>"
                                    data-nama="<?php echo htmlspecialchars($u['nama_lengkap'], ENT_QUOTES); ?>"
                                    data-username="<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>"
                                    data-role="<?php echo $u['role'] === 'admin' ? 'admin' : 'viewer'; ?>"
                                    data-status="<?php echo htmlspecialchars($u['status'], ENT_QUOTES); ?>"
                                    data-me="<?php echo $isMe ? '1' : '0'; ?>"
                                    onclick="openEdit(this)">✏️ Edit</button>

                            <?php if (!$isMe): ?>
                                <button type="button" class="mini-btn del"
                                        data-id="<?php echo $u['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>"
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

<!-- MODAL TAMBAH USER -->
<div class="modal-bg" id="addModal" onclick="if(event.target===this) closeAll()">
    <div class="modal">
        <h3>➕ Tambah User</h3>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="aksi" value="tambah">

            <div class="f-group">
                <label for="a-nama">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" id="a-nama" required>
            </div>
            <div class="f-group">
                <label for="a-username">Username</label>
                <input type="text" name="username" id="a-username" required>
            </div>
            <div class="f-group">
                <label for="a-password">Password</label>
                <input type="password" name="password" id="a-password" required autocomplete="new-password">
                <div class="f-hint">Minimal 6 karakter.</div>
            </div>
            <div class="f-group">
                <label for="a-role">Role</label>
                <select name="role" id="a-role">
                    <option value="viewer">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="f-group">
                <label for="a-status">Status</label>
                <select name="status" id="a-status">
                    <option value="aktif">aktif</option>
                    <option value="nonaktif">nonaktif</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAll()">Batal</button>
                <button type="submit" class="btn-save">Tambah</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT USER -->
<div class="modal-bg" id="editModal" onclick="if(event.target===this) closeAll()">
    <div class="modal">
        <h3>✏️ Edit User</h3>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="e-id">

            <div class="f-group">
                <label for="e-nama">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" id="e-nama" required>
            </div>
            <div class="f-group">
                <label for="e-username">Username</label>
                <input type="text" name="username" id="e-username" required>
            </div>
            <div class="f-group">
                <label for="e-role">Role</label>
                <select name="role" id="e-role">
                    <option value="viewer">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="f-group">
                <label for="e-status">Status</label>
                <select name="status" id="e-status">
                    <option value="aktif">aktif</option>
                    <option value="nonaktif">nonaktif</option>
                </select>
                <div class="f-hint" id="e-me-hint" style="display:none;">Role dan status akun sendiri tidak bisa diubah.</div>
            </div>
            <div class="f-group">
                <label for="e-password">Password Baru</label>
                <input type="password" name="password" id="e-password" placeholder="Kosongkan jika tidak diganti" autocomplete="new-password">
                <div class="f-hint">Minimal 6 karakter.</div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAll()">Batal</button>
                <button type="submit" class="btn-save">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL HAPUS USER -->
<div class="modal-bg" id="delModal" onclick="if(event.target===this) closeAll()">
    <div class="modal">
        <h3>🗑️ Hapus User?</h3>
        <p class="del-text">User <strong id="d-username"></strong> akan dihapus permanen dan tidak bisa dikembalikan.</p>
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
    ['addModal', 'editModal', 'delModal'].forEach(function (id) {
        document.getElementById(id).classList.remove('show');
    });
}

function openAdd() {
    document.getElementById('a-nama').value     = '';
    document.getElementById('a-username').value = '';
    document.getElementById('a-password').value = '';
    document.getElementById('a-role').value     = 'viewer';
    document.getElementById('a-status').value   = 'aktif';
    document.getElementById('addModal').classList.add('show');
    document.getElementById('a-nama').focus();
}

function openEdit(btn) {
    var d = btn.dataset;
    document.getElementById('e-id').value       = d.id;
    document.getElementById('e-nama').value     = d.nama;
    document.getElementById('e-username').value = d.username;
    document.getElementById('e-role').value     = d.role;
    document.getElementById('e-status').value   = d.status;
    document.getElementById('e-password').value = '';

    // Akun sendiri: role & status dikunci
    var isMe = d.me === '1';
    document.getElementById('e-role').disabled   = isMe;
    document.getElementById('e-status').disabled = isMe;
    document.getElementById('e-me-hint').style.display = isMe ? 'block' : 'none';

    document.getElementById('editModal').classList.add('show');
}

function openDelete(btn) {
    document.getElementById('d-id').value = btn.dataset.id;
    document.getElementById('d-username').textContent = btn.dataset.username;
    document.getElementById('delModal').classList.add('show');
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll();
});
</script>
<?php endif; ?>

</body>
</html>