<?php
// chart.php - Halaman Khusus Grafik Data Sensor
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya dapat diakses ketika sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "sidebar.php";
include "koneksi.php";

// Ambil rentang tanggal data yang tersedia di database
$minDate = date('Y-m-d');
$maxDate = date('Y-m-d');
if ($koneksi) {
    $qRange = mysqli_query($koneksi, "SELECT MIN(DATE(waktu)) as min_d, MAX(DATE(waktu)) as max_d FROM sensor_log");
    if ($qRange && $rRange = mysqli_fetch_assoc($qRange)) {
        if (!empty($rRange['min_d'])) $minDate = $rRange['min_d'];
        if (!empty($rRange['max_d'])) $maxDate = $rRange['max_d'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafik Sensor - Sistem Monitoring</title>

    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="js/chart.umd.min.js"></script>
    <script>
        if (typeof Chart === 'undefined') {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js';
            document.head.appendChild(s);
        }
    </script>

    <style>
        :root {
            --primary: #dc2626;
            --primary-soft: #fef2f2;
            --primary-border: #fecaca;
            --accent-cyan: #0284c7;
            --cyan-soft: #f0f9ff;
            --cyan-border: #bae6fd;
            --accent-amber: #f59e0b;
            --amber-soft: #fffbeb;
            --amber-border: #fde68a;
            --bg-card: #ffffff;
            --text-main: #1c1917;
            --text-muted: #78716c;
            --border-color: #e7e5e4;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow-card: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 20px 60px;
        }

        /* ===== HEADER & FILTER CARD ===== */
        .chart-page-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 26px 28px;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .chart-page-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #dc2626 0%, #0284c7 50%, #f59e0b 100%);
        }

        .chart-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 22px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border-color);
        }

        .header-title-group h1 {
            margin: 0 0 4px 0;
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .header-title-group p {
            margin: 0;
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid var(--primary-border);
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header-badge .dot {
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
        }

        /* ===== TOOLBAR FILTER ===== */
        .filter-panel {
            background: #fafaf9;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            margin-bottom: 22px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .filter-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
        }

        .filter-section-title {
            font-size: 12px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Filter Sensor Buttons */
        .sensor-btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-sensor {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-main);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .btn-sensor:hover {
            background: #f5f5f4;
            border-color: #d6d3d1;
        }

        .btn-sensor.active {
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        }

        .btn-sensor.active[data-sensor="all"] {
            background: #1c1917;
            border-color: #1c1917;
        }

        .btn-sensor.active[data-sensor="suhu"] {
            background: #dc2626;
            border-color: #dc2626;
        }

        .btn-sensor.active[data-sensor="kelembapan"] {
            background: #0284c7;
            border-color: #0284c7;
        }

        .btn-sensor.active[data-sensor="asap"] {
            background: #d97706;
            border-color: #d97706;
        }

        /* Filter Tanggal */
        .date-filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .date-input-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: var(--radius-sm);
        }

        .date-input-wrap label {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-muted);
        }

        .date-input-wrap input[type="date"] {
            border: none;
            outline: none;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--text-main);
            background: transparent;
            font-weight: 600;
        }

        .preset-btn-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-preset {
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 11.5px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .btn-preset:hover {
            color: var(--text-main);
            background: #f5f5f4;
        }

        .btn-preset.active {
            background: #e7e5e4;
            color: #1c1917;
            border-color: #d6d3d1;
        }

        .btn-action-apply {
            padding: 8px 18px;
            border-radius: var(--radius-sm);
            background: #dc2626;
            color: #ffffff;
            border: 1px solid #dc2626;
            font-size: 12.5px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action-apply:hover {
            background: #b91c1c;
            border-color: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-action-reset {
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            background: #ffffff;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            font-size: 12.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-action-reset:hover {
            background: #f5f5f4;
            color: var(--text-main);
        }

        /* ===== STATS STRIP ===== */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .stat-card .label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .stat-card .val {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-main);
            font-family: 'JetBrains Mono', monospace;
        }

        /* ===== CANVAS WRAPPER ===== */
        .chart-viewport {
            width: 100%;
            height: 480px;
            position: relative;
        }

        /* Empty state */
        .empty-chart-overlay {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-md);
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
            z-index: 10;
        }

        .empty-chart-overlay.show {
            display: flex;
        }

        .empty-chart-icon {
            font-size: 40px;
        }

        .empty-chart-overlay h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--text-main);
        }

        .empty-chart-overlay p {
            margin: 0;
            font-size: 13px;
            color: var(--text-muted);
            max-width: 340px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-header-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 700;
            transition: var(--transition);
        }

        .btn-header-link:hover {
            background: #f5f5f4;
            border-color: #d6d3d1;
            transform: translateY(-1px);
        }

        /* Auto refresh switch */
        .refresh-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #047857;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 5px 12px;
            border-radius: 999px;
        }

        .refresh-pill .dot {
            width: 6px;
            height: 6px;
            background: #10b981;
            border-radius: 50%;
            animation: pulseDot 1.8s infinite;
        }

        @keyframes pulseDot {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
            70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        @media (max-width: 900px) {
            .stats-strip {
                grid-template-columns: repeat(2, 1fr);
            }
            .chart-viewport {
                height: 380px;
            }
        }

        @media (max-width: 640px) {
            .main-wrapper {
                padding: 16px 14px 40px;
            }
            .chart-page-card {
                padding: 20px 18px;
            }
            .filter-panel {
                padding: 14px;
            }
            .stats-strip {
                grid-template-columns: 1fr;
            }
            .date-filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            .chart-viewport {
                height: 320px;
            }
        }
    </style>
</head>

<body>
    <div class="main-wrapper">

        <!-- KARTU UTAMA GRAFIK -->
        <div class="chart-page-card">

            <!-- HEADER -->
            <div class="chart-page-header">
                <div class="header-title-group">
                    <div class="header-badge">
                        <span class="dot"></span>
                        ANALITIK TELEMETRI SENSOR
                    </div>
                    <h1>Grafik Data Sensor</h1>
                    <p>Visualisasi tren data parameter sensor IoT dengan filter tanggal dan jenis sensor.</p>
                </div>

                <div class="header-actions">
                    <a href="tabel.php" class="btn-header-link" title="Beralih ke tampilan tabel">
                        📋 Lihat Tabel
                    </a>
                    <div class="refresh-pill">
                        <span class="dot"></span>
                        <span id="refresh-status">Live Update (15s)</span>
                    </div>
                </div>
            </div>

            <!-- PANEL FILTER -->
            <div class="filter-panel">

                <!-- ROW 1: FILTER JENIS SENSOR -->
                <div>
                    <div class="filter-section-title">
                        <span>📊 Jenis Sensor:</span>
                    </div>
                    <div class="sensor-btn-group">
                        <button type="button" class="btn-sensor active" data-sensor="all" onclick="selectSensor('all', this)">
                            Semua Sensor (Suhu, Kel, Asap)
                        </button>
                        <button type="button" class="btn-sensor" data-sensor="suhu" onclick="selectSensor('suhu', this)">
                            🌡️ Suhu (°C)
                        </button>
                        <button type="button" class="btn-sensor" data-sensor="kelembapan" onclick="selectSensor('kelembapan', this)">
                            💧 Kelembapan (%)
                        </button>
                        <button type="button" class="btn-sensor" data-sensor="asap" onclick="selectSensor('asap', this)">
                            💨 Asap (PPM)
                        </button>
                    </div>
                </div>

                <!-- ROW 2: FILTER TANGGAL -->
                <div>
                    <div class="filter-section-title">
                        <span>📅 Rentang Tanggal:</span>
                    </div>
                    <div class="filter-row">
                        <div class="date-filter-group">
                            <div class="date-input-wrap">
                                <label for="startDate">Dari:</label>
                                <input type="date" id="startDate" value="">
                            </div>
                            <div class="date-input-wrap">
                                <label for="endDate">Sampai:</label>
                                <input type="date" id="endDate" value="">
                            </div>
                            <button type="button" class="btn-action-apply" onclick="applyFilters()">
                                🔍 Terapkan
                            </button>
                            <button type="button" class="btn-action-reset" onclick="resetFilters()">
                                🔄 Reset
                            </button>
                        </div>

                        <div class="preset-btn-group">
                            <button type="button" class="btn-preset" onclick="setPreset('today')">Hari Ini</button>
                            <button type="button" class="btn-preset" onclick="setPreset('7days')">7 Hari</button>
                            <button type="button" class="btn-preset" onclick="setPreset('30days')">30 Hari</button>
                            <button type="button" class="btn-preset active" onclick="setPreset('all')">Semua</button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RINGKASAN METRIK DARI DATA TERFILTER -->
            <div class="stats-strip">
                <div class="stat-card">
                    <span class="label">Total Titik Sampel</span>
                    <span class="val" id="stat-count">0</span>
                </div>
                <div class="stat-card">
                    <span class="label" id="stat-label-avg">Rata-rata Terpilih</span>
                    <span class="val" id="stat-avg">--</span>
                </div>
                <div class="stat-card">
                    <span class="label" id="stat-label-min">Nilai Terendah</span>
                    <span class="val" id="stat-min">--</span>
                </div>
                <div class="stat-card">
                    <span class="label" id="stat-label-max">Nilai Tertinggi</span>
                    <span class="val" id="stat-max">--</span>
                </div>
            </div>

            <!-- VIEWPORT CANVAS CHART -->
            <div class="chart-viewport">
                <div class="empty-chart-overlay" id="emptyState">
                    <div class="empty-chart-icon">📉</div>
                    <h3>Data Tidak Ditemukan</h3>
                    <p>Tidak ada catatan rekaman sensor pada rentang tanggal yang Anda pilih. Silakan ganti rentang tanggal.</p>
                </div>
                <canvas id="mainChart"></canvas>
            </div>

        </div>

    </div>

    <!-- SCRIPT LOGIKA CHART & FILTER -->
    <script>
        let chartInstance = null;
        let selectedSensor = 'all';
        let currentRawData = [];
        let autoRefreshTimer = null;

        // Inisialisasi tanggal default
        function initDates() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
        }

        // Preset Tanggal Cepat
        function setPreset(type) {
            document.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');

            const now = new Date();
            const formatD = (d) => {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const todayStr = formatD(now);

            if (type === 'today') {
                document.getElementById('startDate').value = todayStr;
                document.getElementById('endDate').value = todayStr;
            } else if (type === '7days') {
                const past7 = new Date();
                past7.setDate(now.getDate() - 7);
                document.getElementById('startDate').value = formatD(past7);
                document.getElementById('endDate').value = todayStr;
            } else if (type === '30days') {
                const past30 = new Date();
                past30.setDate(now.getDate() - 30);
                document.getElementById('startDate').value = formatD(past30);
                document.getElementById('endDate').value = todayStr;
            } else {
                document.getElementById('startDate').value = '';
                document.getElementById('endDate').value = '';
            }

            loadChartData();
        }

        // Pilih Jenis Sensor
        function selectSensor(sensor, btnEl) {
            selectedSensor = sensor;
            document.querySelectorAll('.btn-sensor').forEach(b => b.classList.remove('active'));
            if (btnEl) btnEl.classList.add('active');
            renderChartWithData(currentRawData);
        }

        // Terapkan Filter Tanggal
        function applyFilters() {
            document.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
            loadChartData();
        }

        // Reset Filter ke Default
        function resetFilters() {
            initDates();
            selectedSensor = 'all';
            document.querySelectorAll('.btn-sensor').forEach(b => b.classList.remove('active'));
            document.querySelector('.btn-sensor[data-sensor="all"]').classList.add('active');
            document.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
            document.querySelector('.btn-preset:last-child').classList.add('active');
            loadChartData();
        }

        // Ambil Data dari API
        async function loadChartData() {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;

            let url = 'api_sensor.php?limit=200';
            if (start) url += '&start_date=' + encodeURIComponent(start);
            if (end) url += '&end_date=' + encodeURIComponent(end);
            url += '&t=' + Date.now();

            try {
                const res = await fetch(url);
                const data = await res.json();
                if (data.status === 'success') {
                    currentRawData = data.chart_data || [];
                    renderChartWithData(currentRawData);
                } else {
                    currentRawData = [];
                    renderChartWithData([]);
                }
            } catch (err) {
                console.error('Gagal mengambil data:', err);
                currentRawData = [];
                renderChartWithData([]);
            }
        }

        // Render Grafik & Update Statistik
        function renderChartWithData(dataList) {
            const emptyEl = document.getElementById('emptyState');

            if (!dataList || dataList.length === 0) {
                if (emptyEl) emptyEl.classList.add('show');
                if (chartInstance) {
                    chartInstance.destroy();
                    chartInstance = null;
                }
                updateStats([]);
                return;
            }

            if (emptyEl) emptyEl.classList.remove('show');

            const labels = dataList.map(r => {
                const d = new Date(r.waktu);
                const tStr = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + ':' + String(d.getSeconds()).padStart(2, '0');
                const dtStr = String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0');
                return [tStr, dtStr];
            });

            const suhuValues = dataList.map(r => parseFloat(r.suhu));
            const humValues = dataList.map(r => parseFloat(r.kelembapan));
            const asapValues = dataList.map(r => parseInt(r.asap));

            updateStats(dataList);

            // Susun Dataset Berdasarkan Sensor Terpilih
            const datasets = [];

            if (selectedSensor === 'all' || selectedSensor === 'suhu') {
                datasets.push({
                    label: 'Suhu (°C)',
                    data: suhuValues,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.08)',
                    borderWidth: 2.5,
                    fill: (selectedSensor === 'suhu'),
                    tension: 0.35,
                    yAxisID: 'ySuhuKel',
                    pointRadius: dataList.length > 50 ? 0 : 3.5,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#dc2626'
                });
            }

            if (selectedSensor === 'all' || selectedSensor === 'kelembapan') {
                datasets.push({
                    label: 'Kelembapan (%)',
                    data: humValues,
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.08)',
                    borderWidth: 2.5,
                    fill: (selectedSensor === 'kelembapan'),
                    tension: 0.35,
                    yAxisID: 'ySuhuKel',
                    pointRadius: dataList.length > 50 ? 0 : 3.5,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#0284c7'
                });
            }

            if (selectedSensor === 'all' || selectedSensor === 'asap') {
                datasets.push({
                    label: 'Asap (PPM)',
                    data: asapValues,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.08)',
                    borderWidth: 2.5,
                    fill: (selectedSensor === 'asap'),
                    tension: 0.35,
                    yAxisID: (selectedSensor === 'all') ? 'yAsap' : 'ySuhuKel',
                    pointRadius: dataList.length > 50 ? 0 : 3.5,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#f59e0b'
                });
            }

            const ctx = document.getElementById('mainChart').getContext('2d');

            if (chartInstance) {
                chartInstance.destroy();
            }

            // Konfigurasi Skala Sumbu Y
            const scalesConfig = {
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.04)',
                        tickColor: 'transparent'
                    },
                    ticks: {
                        font: { family: 'Plus Jakarta Sans', size: 11 },
                        color: '#78716c',
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 12
                    }
                },
                ySuhuKel: {
                    type: 'linear',
                    position: 'left',
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        font: { family: 'JetBrains Mono', size: 11 },
                        color: '#78716c',
                        callback: function(val) {
                            if (selectedSensor === 'asap') return val + ' PPM';
                            if (selectedSensor === 'kelembapan') return val + ' %';
                            if (selectedSensor === 'suhu') return val + ' °C';
                            return val;
                        }
                    }
                }
            };

            // Tambahkan sumbu Y kedua khusus Asap jika Semua Sensor aktif
            if (selectedSensor === 'all') {
                scalesConfig.yAsap = {
                    type: 'linear',
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: {
                        font: { family: 'JetBrains Mono', size: 11 },
                        color: '#d97706',
                        callback: val => val + ' PPM'
                    }
                };
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { family: 'Plus Jakarta Sans', size: 12, weight: '700' },
                                boxWidth: 14,
                                padding: 18,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1c1917',
                            titleFont: { family: 'Plus Jakarta Sans', size: 12, weight: '700' },
                            bodyFont: { family: 'JetBrains Mono', size: 12 },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const l = context.dataset.label || '';
                                    return ` ${l}: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: scalesConfig
                }
            });
        }

        // Kalkulasi Statistik Data Terfilter
        function updateStats(dataList) {
            document.getElementById('stat-count').textContent = dataList.length;

            if (dataList.length === 0) {
                document.getElementById('stat-avg').textContent = '--';
                document.getElementById('stat-min').textContent = '--';
                document.getElementById('stat-max').textContent = '--';
                return;
            }

            let values = [];
            let unit = '';
            let labelSuffix = '';

            if (selectedSensor === 'suhu') {
                values = dataList.map(r => parseFloat(r.suhu));
                unit = '°C';
                labelSuffix = 'Suhu';
            } else if (selectedSensor === 'kelembapan') {
                values = dataList.map(r => parseFloat(r.kelembapan));
                unit = '%';
                labelSuffix = 'Kelembapan';
            } else if (selectedSensor === 'asap') {
                values = dataList.map(r => parseInt(r.asap));
                unit = ' PPM';
                labelSuffix = 'Asap';
            } else {
                // Default 'all': hitung statistik suhu
                values = dataList.map(r => parseFloat(r.suhu));
                unit = '°C';
                labelSuffix = 'Suhu';
            }

            document.getElementById('stat-label-avg').textContent = `Rata-rata (${labelSuffix})`;
            document.getElementById('stat-label-min').textContent = `Terendah (${labelSuffix})`;
            document.getElementById('stat-label-max').textContent = `Tertinggi (${labelSuffix})`;

            const sum = values.reduce((a, b) => a + b, 0);
            const avg = (sum / values.length).toFixed(1);
            const min = Math.min(...values).toFixed(1);
            const max = Math.max(...values).toFixed(1);

            document.getElementById('stat-avg').textContent = avg + unit;
            document.getElementById('stat-min').textContent = min + unit;
            document.getElementById('stat-max').textContent = max + unit;
        }

        // Mulai Siklus Auto Refresh (15 Detik)
        function startAutoRefresh() {
            autoRefreshTimer = setInterval(() => {
                // Hanya auto refresh jika tidak sedang memilih tanggal khusus masa lalu
                const s = document.getElementById('startDate').value;
                const e = document.getElementById('endDate').value;
                if (!s && !e) {
                    loadChartData();
                }
            }, 15000);
        }

        // Inisialisasi saat halaman selesai dimuat
        document.addEventListener('DOMContentLoaded', () => {
            initDates();
            loadChartData();
            startAutoRefresh();
        });
    </script>
</body>
</html>
