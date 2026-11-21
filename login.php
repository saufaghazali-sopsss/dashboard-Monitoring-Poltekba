<?php
/*
 * =====================================================
 * login.php - Halaman Login Sistem Monitoring
 * =====================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "koneksi.php"; // menghasilkan variabel $koneksi

// Jika sudah login, tidak perlu melihat halaman login lagi
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$usernameInput = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usernameInput = trim($_POST['username'] ?? '');
    $password      = $_POST['password'] ?? '';
    $token         = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = "Sesi form tidak valid. Silakan muat ulang halaman.";

    } elseif ($usernameInput === '' || $password === '') {
        $error = "Username dan password wajib diisi.";

    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id, nama_lengkap, username, password, role, status FROM users WHERE username = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "s", $usernameInput);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {

            if ($user['status'] !== 'aktif') {
                $error = "Akun Anda dinonaktifkan. Hubungi administrator.";
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id']  = $user['id'];
                $_SESSION['nama']     = $user['nama_lengkap'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                $upd = mysqli_prepare($koneksi, "UPDATE users SET last_login = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($upd, "i", $user['id']);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);

                header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = "Username atau password salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <title>Masuk - Sistem Monitoring Telemetri</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #e11d48;
            --primary-hover: #be123c;
            --primary-soft: #fff1f2;
            --primary-border: #fecdd3;
            --accent-orange: #f97316;
            --accent-emerald: #10b981;
            --emerald-soft: #ecfdf5;
            --emerald-border: #a7f3d0;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-subtle: #94a3b8;
            --border-color: #e2e8f0;
            --border-hover: #cbd5e1;
            --shadow-card: 0 20px 45px -12px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
            --radius-xl: 20px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
            /* Technical dot grid pattern */
            background-image: radial-gradient(#cbd5e1 1.15px, transparent 1.15px);
            background-size: 24px 24px;
            background-attachment: fixed;
            position: relative;
            overflow-x: hidden;
        }

        /* Subtle ambient lighting */
        body::before {
            content: '';
            position: fixed;
            top: -150px;
            left: 50%;
            transform: translateX(-50%);
            width: 700px;
            height: 500px;
            background: radial-gradient(ellipse at center, rgba(225, 29, 72, 0.07) 0%, rgba(249, 115, 22, 0.03) 45%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ===== TOP BAR (NAV & STATUS) ===== */
        .login-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 4px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            transition: var(--transition);
        }

        .btn-back svg {
            transition: transform 0.2s ease;
        }

        .btn-back:hover {
            color: var(--text-main);
            background: #ffffff;
            border-color: var(--border-hover);
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
            transform: translateY(-1px);
        }

        .btn-back:hover svg {
            transform: translateX(-2px);
        }

        .live-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--emerald-soft);
            color: #047857;
            border: 1px solid var(--emerald-border);
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .live-pulse-dot {
            width: 7px;
            height: 7px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
            animation: pulseGreen 2s infinite;
        }

        @keyframes pulseGreen {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
            70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* ===== LOGIN CARD ===== */
        .login-card {
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 36px 32px 30px;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(12px);
        }

        /* Elegant top gradient highlight */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3.5px;
            background: linear-gradient(90deg, #e11d48 0%, #f43f5e 50%, #fb7185 100%);
        }

        /* ===== CARD HEADER ===== */
        .card-header-box {
            margin-bottom: 24px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
            border: 1px solid var(--primary-border);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            box-shadow: 0 2px 6px rgba(225, 29, 72, 0.12);
        }

        .brand-text-col {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .brand-name {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .brand-tag {
            font-size: 10px;
            font-weight: 700;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 1px 6px;
            border-radius: 9999px;
            letter-spacing: 0.4px;
            display: inline-block;
            width: fit-content;
        }

        h1.login-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-main);
            margin: 0 0 6px;
        }

        .login-subtitle {
            font-size: 13.5px;
            line-height: 1.55;
            color: var(--text-muted);
            margin: 0;
        }

        /* ===== ERROR ALERT ===== */
        .alert-error {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px 14px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.45;
            margin-bottom: 20px;
            animation: alertPop 0.3s ease-out;
        }

        .alert-error svg {
            flex-shrink: 0;
            margin-top: 1px;
            color: #dc2626;
        }

        @keyframes alertPop {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== FORM STYLING ===== */
        .form-group {
            margin-bottom: 18px;
        }

        label.form-label {
            display: block;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #475569;
            margin-bottom: 7px;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-prefix-icon {
            position: absolute;
            left: 14px;
            color: var(--text-subtle);
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .form-control {
            width: 100%;
            height: 44px;
            padding: 0 14px 0 42px;
            background: #fafaf9;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            color: var(--text-main);
            outline: none;
            transition: var(--transition);
        }

        .form-control::placeholder {
            color: #94a3b8;
            font-size: 13.5px;
        }

        .input-wrap:focus-within .input-prefix-icon {
            color: var(--primary);
        }

        .form-control:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3.5px rgba(225, 29, 72, 0.12);
        }

        /* Password specific input */
        .form-control.input-password {
            padding-right: 44px;
        }

        .btn-toggle-pass {
            position: absolute;
            right: 8px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            border-radius: 6px;
            color: var(--text-subtle);
            cursor: pointer;
            transition: var(--transition);
            outline: none;
        }

        .btn-toggle-pass:hover {
            color: var(--text-main);
            background: #f1f5f9;
        }

        .btn-toggle-pass:focus-visible {
            box-shadow: 0 0 0 2px var(--primary);
        }

        /* ===== SUBMIT BUTTON ===== */
        .btn-submit {
            width: 100%;
            height: 46px;
            margin-top: 6px;
            background: #0f172a;
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
            transition: var(--transition);
            position: relative;
        }

        .btn-submit:hover {
            background: #1e293b;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.18);
            transform: translateY(-1px);
        }

        .btn-submit:active {
            transform: translateY(0) scale(0.99);
        }

        .btn-submit:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
        }

        .btn-submit svg.btn-arrow-icon {
            transition: transform 0.2s ease;
        }

        .btn-submit:hover svg.btn-arrow-icon {
            transform: translateX(3px);
        }

        .spinner-icon {
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ===== CARD SECURITY BADGE ===== */
        .card-security-badge {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px dashed var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
        }

        .card-security-badge svg {
            color: #10b981;
            flex-shrink: 0;
        }

        /* ===== FOOTER ===== */
        .login-footer {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.5;
            padding: 0 8px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 28px 20px 24px;
                border-radius: 16px;
            }

            h1.login-title {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>

    <div class="login-wrapper">

        <!-- Bar navigasi atas -->
        <div class="login-top-bar">
            <a href="Beranda.php" class="btn-back">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <span>Kembali ke Beranda</span>
            </a>

            <div class="live-status-pill" title="Status node sensor online">
                <span class="live-pulse-dot"></span>
                <span>Node Aktif</span>
            </div>
        </div>

        <!-- Kartu login -->
        <div class="login-card">

            <div class="card-header-box">
                <div class="brand-badge">
                    <div class="brand-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path>
                        </svg>
                    </div>
                    <div class="brand-text-col">
                        <div class="brand-name">
                            Sistem Monitoring
                        </div>
                        <span class="brand-tag">IoT Telemetry</span>
                    </div>
                </div>

                <h1 class="login-title">Masuk ke Dashboard</h1>
                <p class="login-subtitle">Masukkan username dan password Anda untuk mengakses data sensor real-time.</p>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert-error" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="off" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- Input Username -->
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-wrap">
                        <span class="input-prefix-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input type="text"
                               id="username"
                               name="username"
                               class="form-control"
                               value="<?php echo htmlspecialchars($usernameInput); ?>"
                               placeholder="Masukkan username"
                               required
                               autofocus
                               autocomplete="username">
                    </div>
                </div>

                <!-- Input Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-prefix-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control input-password"
                               placeholder="Masukkan password"
                               required
                               autocomplete="current-password">
                        <button type="button"
                                class="btn-toggle-pass"
                                id="btnTogglePass"
                                onclick="togglePassword()"
                                aria-label="Tampilkan atau sembunyikan password"
                                title="Tampilkan / sembunyikan password">
                            <!-- Icon Eye (saat password tersembunyi) -->
                            <svg class="eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <!-- Icon Eye Off (saat password terlihat) -->
                            <svg class="eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                                <line x1="2" x2="22" y1="2" y2="22"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <button type="submit" class="btn-submit" id="btnSubmit">
                    <span class="btn-label">Masuk ke Dashboard</span>
                    <svg class="btn-arrow-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                    <svg class="spinner-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: none;">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                    </svg>
                </button>
            </form>

            <!-- Proteksi & Sesi Info -->
            <div class="card-security-badge">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <path d="m9 12 2 2 4-4"></path>
                </svg>
                <span>Enkripsi Sesi & Proteksi CSRF Aktif</span>
            </div>
        </div>

        <!-- Footer Bawah -->
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> Sistem Monitoring IoT. Seluruh hak cipta dilindungi.
        </div>

    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const eyeShow = document.querySelector('.eye-show');
            const eyeHide = document.querySelector('.eye-hide');

            if (input.type === 'password') {
                input.type = 'text';
                eyeShow.style.display = 'none';
                eyeHide.style.display = 'block';
            } else {
                input.type = 'password';
                eyeShow.style.display = 'block';
                eyeHide.style.display = 'none';
            }
        }

        // Efek loading halus pada submit tombol
        const form = document.getElementById('loginForm');
        const btnSubmit = document.getElementById('btnSubmit');
        const btnLabel = btnSubmit.querySelector('.btn-label');
        const btnArrow = btnSubmit.querySelector('.btn-arrow-icon');
        const btnSpinner = btnSubmit.querySelector('.spinner-icon');

        form.addEventListener('submit', function() {
            btnSubmit.disabled = true;
            btnLabel.textContent = 'Memverifikasi...';
            btnArrow.style.display = 'none';
            btnSpinner.style.display = 'inline-block';
        });
    </script>

</body>

</html>