<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "sidebar.php";
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - Sistem Monitoring</title>

    <style>
        .main-wrapper {
            max-width: 1140px;
            margin: 0 auto;
            padding: 28px 24px 60px;
        }

        /* ===== HERO ===== */
        .hero {
            margin-bottom: 32px;
            background: #fff;
            border: 1px solid #e7e5e4;
            border-radius: 16px;
            padding: 60px 40px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: #dc2626;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .hero-tag .live-dot {
            width: 6px; height: 6px;
            background: #dc2626;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        .hero h1 {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.25;
            margin: 0 0 14px 0;
            color: #1c1917;
        }

        .hero h1 em {
            font-style: normal;
            color: #dc2626;
        }

        .hero-desc {
            font-size: 15px;
            line-height: 1.7;
            color: #57534e;
            margin: 0;
            max-width: 560px;
        }

        /* ===== TENTANG ===== */
        .about-card {
            background: #fff;
            border: 1px solid #e7e5e4;
            border-left: 3px solid #dc2626;
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 32px;
        }

        .about-card h2 {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 10px 0;
            color: #1c1917;
        }

        .about-card p {
            font-size: 14px;
            line-height: 1.7;
            color: #57534e;
            margin: 0 0 16px 0;
        }

        .tech-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tech-tag {
            background: #f5f5f4;
            border: 1px solid #e7e5e4;
            color: #44403c;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        /* ===== CARA KERJA ===== */
        .sec-head h2 {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #1c1917;
            margin: 0 0 4px 0;
        }

        .sec-head p {
            font-size: 13.5px;
            color: #78716c;
            margin: 0 0 18px 0;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .step-card {
            background: #fff;
            border: 1px solid #e7e5e4;
            border-radius: 12px;
            padding: 22px;
        }

        .step-num {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            font-weight: 800;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }

        .step-card h3 {
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 6px 0;
            color: #1c1917;
        }

        .step-card p {
            font-size: 13px;
            line-height: 1.6;
            color: #78716c;
            margin: 0;
        }

        /* ===== TENTANG ===== */
        .about-card {
            background: #fff;
            border: 1px solid #e7e5e4;
            border-left: 3px solid #dc2626;
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 20px;
        }

        .about-card h2 {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 10px 0;
            color: #1c1917;
        }

        .about-card p {
            font-size: 14.5px;
            line-height: 1.75;
            color: #57534e;
            margin: 0 0 14px 0;
        }

        .about-card p:last-child { margin-bottom: 0; }

        .about-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .about-grid .about-card {
            margin-bottom: 0;
            border-left: 1px solid #e7e5e4;
        }

        .about-grid .about-card h3 {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: #1c1917;
        }

        .tech-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .tech-tag {
            background: #f5f5f4;
            border: 1px solid #e7e5e4;
            color: #44403c;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        /* ===== FOOTER ===== */
        .page-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e7e5e4;
            color: #a8a29e;
            font-size: 12.5px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .steps { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .about-grid { grid-template-columns: 1fr; }
            .main-wrapper { padding: 16px 16px 40px; }
            .hero { padding: 32px 20px; }
            .hero h1 { font-size: 26px; }
        }
    </style>
</head>

<body>
    <div class="main-wrapper">

        <!-- HERO -->
        <section class="hero">
            <div class="hero-tag">
                <span class="live-dot"></span>
                Sistem Monitoring Aktif
            </div>

            <h1>Sistem Monitoring <em>Real-Time</em> &amp; Akurat</h1>

            <p class="hero-desc">
                Platform sistem monitoring berbasis sensor mikrokontroler yang memantau suhu, kelembapan, asap, dan potensi bahaya secara real-time.
            </p>
        </section>

        <!-- TENTANG -->
        <section>
            <div class="about-card">
                <h2>Tentang Sistem Monitoring</h2>
                <p>
                    Sistem Monitoring adalah proyek pemantauan lingkungan berbasis mikrokontroler dan sensor yang terhubung ke sebuah website. Sistem ini dirancang untuk membantu pengguna mengetahui kondisi suatu ruangan dari jarak jauh, sehingga potensi bahaya dapat dikenali lebih awal sebelum berkembang menjadi masalah yang lebih besar.
                </p>
                <p>
                    Seluruh informasi dikumpulkan, disimpan, dan ditampilkan dalam satu halaman dashboard yang dapat diakses kapan saja oleh pengguna yang telah terdaftar.
                </p>
                <div class="tech-list">
                    <span class="tech-tag">ESP32</span>
                    <span class="tech-tag">DHT-22</span>
                    <span class="tech-tag">MQ-2</span>
                    <span class="tech-tag">Flame Sensor</span>
                    <span class="tech-tag">PHP 8 + MySQL</span>
                </div>
            </div>

            <div class="about-grid">
                <div class="about-card">
                    <h3>Tujuan</h3>
                    <p>Menyediakan sarana pemantauan yang mudah dipahami, cepat diakses, dan dapat diandalkan untuk mendukung deteksi dini kebakaran.</p>
                </div>
                <div class="about-card">
                    <h3>Akses Sistem</h3>
                    <p>Dashboard hanya dapat dibuka oleh pengguna yang sudah login. Silakan buka menu di pojok kiri atas, lalu pilih Dashboard Telemetri untuk masuk.</p>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="page-footer">
            <p>&copy; <?php echo date('Y'); ?> Sistem Monitoring</p>
        </footer>

    </div>
</body>
</html>