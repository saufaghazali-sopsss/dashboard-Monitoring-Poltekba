<?php
// sidebar.php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$currentPage = strtolower(basename($_SERVER['PHP_SELF']));
$isLoggedIn  = isset($_SESSION['user_id']);
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #e11d48;
        --primary-soft: #fff1f2;
        --primary-border: #fecdd3;
        --accent-orange: #f97316;
        --accent-amber: #f59e0b;
        --accent-cyan: #0284c7;
        --accent-emerald: #10b981;
        --emerald-soft: #ecfdf5;
        --emerald-border: #a7f3d0;
        --bg-body: #f8fafc;
        --bg-card: #ffffff;
        --bg-surface: #f1f5f9;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --text-subtle: #94a3b8;
        --border-color: #e2e8f0;
        --border-hover: #cbd5e1;
        --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
        --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
        --shadow-md: 0 4px 16px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
        --shadow-lg: 0 12px 32px -4px rgba(15, 23, 42, 0.08), 0 4px 12px -2px rgba(15, 23, 42, 0.03);
        --radius-lg: 20px;
        --radius-md: 14px;
        --radius-sm: 10px;
        --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background-color: var(--bg-body);
        color: var(--text-main);
        -webkit-font-smoothing: antialiased;
        /* Technical precision subtle dot grid */
        background-image: radial-gradient(#cbd5e1 1.15px, transparent 1.15px);
        background-size: 24px 24px;
        background-attachment: fixed;
    }

    /* =========================================
       NAVBAR (BRIGHT & CRISP)
    ========================================= */
    .navbar {
        height: 72px;
        background: rgba(255, 255, 255, 0.88);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 32px;
        border-bottom: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
        position: sticky;
        top: 0;
        z-index: 950;
    }

    .navbar-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .hamburger {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        color: var(--text-main);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: var(--transition);
        outline: none;
        box-shadow: var(--shadow-xs);
    }

    .hamburger:hover {
        background: var(--bg-surface);
        border-color: var(--border-hover);
        color: var(--primary);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    .hamburger:active {
        transform: scale(0.96);
    }

    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: inherit;
    }

    .navbar-brand-icon {
        width: 38px;
        height: 38px;
        background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
        border: 1px solid var(--primary-border);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        box-shadow: 0 2px 6px rgba(225, 29, 72, 0.12);
    }

    .navbar-brand-text {
        display: flex;
        flex-direction: column;
    }

    .navbar-brand-title {
        font-size: 17px;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .navbar-brand-title .badge-ver {
        font-size: 10px;
        font-weight: 700;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        padding: 2px 7px;
        border-radius: 9999px;
        letter-spacing: 0.5px;
    }

    .navbar-brand-sub {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        letter-spacing: 0.2px;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .nav-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--emerald-soft);
        color: #047857;
        border: 1px solid var(--emerald-border);
        padding: 7px 16px;
        border-radius: 9999px;
        font-size: 12.5px;
        font-weight: 700;
        box-shadow: 0 1px 3px rgba(16, 185, 129, 0.1);
    }

    .pulse-dot-nav {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
        animation: pulseGreenNav 2s infinite;
    }

    @keyframes pulseGreenNav {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
        70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    /* =========================================
       SIDEBAR DRAWER (BRIGHT & CRISP)
    ========================================= */
    .sidebar {
        position: fixed;
        top: 0;
        left: -320px;
        width: 300px;
        height: 100vh;
        background: #ffffff;
        border-right: 1px solid var(--border-color);
        box-shadow: 12px 0 40px rgba(15, 23, 42, 0.12);
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
        display: flex;
        flex-direction: column;
    }

    .sidebar.active {
        left: 0;
    }

    .sidebar-header {
        height: 72px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 22px;
        border-bottom: 1px solid var(--border-color);
        background: #fafbfc;
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 800;
        color: var(--text-main);
    }

    .sidebar-brand-icon {
        width: 32px;
        height: 32px;
        background: var(--primary-soft);
        border: 1px solid var(--primary-border);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
    }

    .close {
        width: 36px;
        height: 36px;
        font-size: 18px;
        border: 1px solid var(--border-color);
        background: #ffffff;
        color: var(--text-muted);
        border-radius: var(--radius-sm);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
        line-height: 1;
    }

    .close:hover {
        background: var(--primary-soft);
        color: var(--primary);
        border-color: var(--primary-border);
        transform: rotate(90deg);
    }

    .menu {
        padding: 24px 16px;
        flex: 1;
        overflow-y: auto;
    }

    .menu-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-subtle);
        margin: 0 0 10px 14px;
    }

    .menu a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        margin-bottom: 6px;
        text-decoration: none;
        color: #475569;
        font-weight: 600;
        font-size: 14.5px;
        border-radius: var(--radius-sm);
        transition: var(--transition);
        position: relative;
        border: 1px solid transparent;
    }

    .menu a .nav-icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        transition: color 0.2s;
    }

    .menu a:hover {
        background: #f1f5f9;
        color: var(--text-main);
        transform: translateX(3px);
    }

    .menu a:hover .nav-icon {
        color: var(--primary);
    }

    .menu a.active {
        background: #fff1f2;
        color: var(--primary);
        font-weight: 700;
        border-color: var(--primary-border);
        box-shadow: 0 2px 8px rgba(225, 29, 72, 0.08);
    }

    .menu a.active .nav-icon {
        color: var(--primary);
    }

    .menu a.active::before {
        content: '';
        position: absolute;
        left: -16px;
        top: 8px;
        bottom: 8px;
        width: 4px;
        background: var(--primary);
        border-radius: 0 4px 4px 0;
    }

    .sidebar-footer {
        padding: 18px 16px;
        border-top: 1px solid var(--border-color);
        background: #fafbfc;
    }

    .sidebar-footer-card {
        background: #ffffff;
        border: 1px solid var(--border-color);
        padding: 12px 14px;
        border-radius: var(--radius-sm);
        font-size: 12px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: var(--shadow-xs);
    }

    .sidebar-footer-card .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 6px rgba(16, 185, 129, 0.4);
    }

    /* =========================================
       USER CARD & TOMBOL KELUAR
    ========================================= */
    .user-card {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
        padding: 4px 2px;
    }

    .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: var(--primary-soft);
        border: 1px solid var(--primary-border);
        color: var(--primary);
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .user-info {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .user-info strong {
        font-size: 13.5px;
        color: var(--text-main);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-info span {
        font-size: 11.5px;
        color: var(--text-muted);
        text-transform: capitalize;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 14px;
        margin-bottom: 12px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--primary-border);
        background: #ffffff;
        color: var(--primary);
        font-size: 13.5px;
        font-weight: 700;
        text-decoration: none;
        transition: var(--transition);
    }

    .logout-btn:hover {
        background: var(--primary-soft);
    }

    /* =========================================
       POP-UP KONFIRMASI KELUAR
    ========================================= */
    .logout-modal-bg {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        z-index: 2000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .logout-modal-bg.show {
        opacity: 1;
        visibility: visible;
    }

    .logout-modal {
        width: 100%;
        max-width: 380px;
        background: #ffffff;
        border-radius: 16px;
        padding: 30px 26px 24px;
        text-align: center;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
        transform: translateY(12px) scale(0.97);
        transition: transform 0.2s ease;
    }

    .logout-modal-bg.show .logout-modal {
        transform: translateY(0) scale(1);
    }

    .logout-modal::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: var(--primary);
    }

    .logout-modal-icon {
        width: 54px;
        height: 54px;
        margin: 0 auto 16px;
        border-radius: 14px;
        background: var(--primary-soft);
        border: 1px solid var(--primary-border);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .logout-modal h3 {
        margin: 0 0 6px;
        font-size: 19px;
        font-weight: 800;
        color: var(--text-main);
    }

    .logout-modal p {
        margin: 0 0 22px;
        font-size: 13.5px;
        color: var(--text-muted);
    }

    .logout-modal-actions {
        display: flex;
        gap: 10px;
    }

    .logout-modal-actions button,
    .logout-modal-actions a {
        flex: 1;
        padding: 11px 14px;
        border-radius: var(--radius-sm);
        font-size: 14px;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        transition: var(--transition);
    }

    .btn-modal-cancel {
        background: #ffffff;
        color: var(--text-main);
        border: 1px solid var(--border-color);
    }

    .btn-modal-cancel:hover {
        background: var(--bg-surface);
        border-color: var(--border-hover);
    }

    .btn-modal-confirm {
        background: var(--primary);
        color: #ffffff;
        border: 1px solid var(--primary);
    }

    .btn-modal-confirm:hover {
        background: #be123c;
        border-color: #be123c;
    }

    /* OVERLAY */
    .overlay {
        display: block;
        opacity: 0;
        visibility: hidden;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 999;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }

    .overlay.active {
        opacity: 1;
        visibility: visible;
    }

    @media (max-width: 640px) {
        .navbar {
            padding: 0 16px;
        }
        .navbar-brand-sub {
            display: none;
        }
        .nav-status-badge span.status-text-nav {
            display: none;
        }
    }
</style>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">

    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path>
                </svg>
            </div>
            <span>Sistem monitoring</span>
        </div>
        <button class="close" onclick="toggleSidebar()" aria-label="Tutup Menu">
            ✕
        </button>
    </div>

    <div class="menu">
        <div class="menu-label">Navigasi Utama</div>

        <a href="Beranda.php" class="<?php echo ($currentPage == 'beranda.php') ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </span>
            <span>Beranda</span>
        </a>

        <a href="dashboard.php" class="<?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="7" height="9" x="3" y="3" rx="1"></rect>
                    <rect width="7" height="5" x="14" y="3" rx="1"></rect>
                    <rect width="7" height="9" x="14" y="12" rx="1"></rect>
                    <rect width="7" height="5" x="3" y="16" rx="1"></rect>
                </svg>
            </span>
            <span>Dashboard Telemetri</span>
        </a>

        <?php if (!$isLoggedIn): ?>
        <a href="porto.php" class="<?php echo ($currentPage == 'porto.php') ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
            </span>
            <span>Porto Lokasi Alat</span>
        </a>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
        <a href="chart.php" class="<?php echo ($currentPage == 'chart.php') ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
            </span>
            <span>Grafik Sensor</span>
        </a>
        <a href="tabel.php" class="<?php echo ($currentPage == 'tabel.php') ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <path d="M3 9h18"></path>
                    <path d="M3 15h18"></path>
                    <path d="M9 3v18"></path>
                </svg>
            </span>
            <span>Tabel Sensor</span>
        </a>
        <div class="menu-label" style="margin-top: 16px;">Pengaturan</div>

        <a href="pengaturan.php" class="<?php echo ($currentPage == 'pengaturan.php') ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </span>
            <span>Pengaturan User</span>
        </a>

        <a href="pengaturan_sensor.php" class="<?php echo ($currentPage == 'pengaturan_sensor.php') ? 'active' : ''; ?>">
            <span class="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
            </span>
            <span>Batas Sensor</span>
        </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <?php if ($isLoggedIn): ?>
            <div class="user-card">
                <div class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['nama'] ?? 'U', 0, 1))); ?></div>
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($_SESSION['nama'] ?? ''); ?></strong>
                    <span><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></span>
                </div>
            </div>
            <a href="logout.php" class="logout-btn" onclick="openLogoutModal(); return false;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" x2="9" y1="12" y2="12"></line>
                </svg>
                Keluar
            </a>
        <?php endif; ?>

        <div class="sidebar-footer-card">
            <span class="dot"></span>
            <div>
                <strong style="color: #0f172a; display: block; margin-bottom: 2px;">Sensor Node #01</strong>
                <span style="font-size: 11px;">Polling Otomatis (15s)</span>
            </div>
        </div>
    </div>

</div>

<!-- OVERLAY -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<?php if ($isLoggedIn): ?>
<!-- POP-UP KONFIRMASI KELUAR -->
<div class="logout-modal-bg" id="logoutModal" onclick="if (event.target === this) closeLogoutModal()">
    <div class="logout-modal">
        <div class="logout-modal-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" x2="9" y1="12" y2="12"></line>
            </svg>
        </div>
        <h3>Keluar dari akun?</h3>
        <p>Apakah Anda yakin ingin keluar?</p>
        <div class="logout-modal-actions">
            <button type="button" class="btn-modal-cancel" onclick="closeLogoutModal()">Batal</button>
            <a href="logout.php" class="btn-modal-confirm">Ya, Keluar</a>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- NAVBAR -->
<div class="navbar">

    <div class="navbar-left">
        <button class="hamburger" onclick="toggleSidebar()" aria-label="Buka Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" x2="20" y1="12" y2="12"></line>
                <line x1="4" x2="20" y1="6" y2="6"></line>
                <line x1="4" x2="20" y1="18" y2="18"></line>
            </svg>
        </button>

        <a href="Beranda.php" class="navbar-brand">
            <div class="navbar-brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path>
                </svg>
            </div>
            <div class="navbar-brand-text">
                <span class="navbar-brand-title">
                    Sistem monitoring <span class="badge-ver">v1.0</span>
                </span>
                <span class="navbar-brand-sub">Sistem Monitoring</span>
            </div>
        </a>
    </div>

    <div class="navbar-right">
        <div class="nav-status-badge">
            <span class="pulse-dot-nav"></span>
            <span class="status-text-nav">Sistem Normal</span>
        </div>
    </div>

</div>

<script>
function toggleSidebar() {
    document
        .getElementById("sidebar")
        .classList.toggle("active");

    document
        .getElementById("overlay")
        .classList.toggle("active");
}

// Pop-up konfirmasi keluar
function openLogoutModal() {
    var m = document.getElementById("logoutModal");
    if (m) m.classList.add("show");
}
function closeLogoutModal() {
    var m = document.getElementById("logoutModal");
    if (m) m.classList.remove("show");
}
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeLogoutModal();
});

// Menutup sidebar jika tombol Escape ditekan
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        var sidebar = document.getElementById("sidebar");
        var overlay = document.getElementById("overlay");
        if (sidebar && sidebar.classList.contains("active")) {
            sidebar.classList.remove("active");
            overlay.classList.remove("active");
        }
    }
});
</script>