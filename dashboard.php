<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "sidebar.php";
include "koneksi.php";
// ... sisanya tetap
// Query data awal untuk render pertama (server-side rendering agar tidak ada jeda kosong)
$initialQuery = "SELECT * FROM sensor_log ORDER BY id DESC LIMIT 15";
$initialResult = mysqli_query($koneksi, $initialQuery);

$thresholds = getSensorThresholds($koneksi);
$initialRows = [];
$totalRows = 0;
if ($initialResult) {
    while ($r = mysqli_fetch_assoc($initialResult)) {
        $ev = evaluateSensorStatus($r['suhu'], $r['asap'], $thresholds);
        $r['status_text'] = $ev['status_text'];
        $r['api_text'] = $ev['api_text'];
        $r['badge_class'] = $ev['badge_class'];
        $r['formatted_time'] = date('H:i:s', strtotime($r['waktu']));
        $r['formatted_date'] = date('d M Y, H:i:s', strtotime($r['waktu']));
        $initialRows[] = $r;
    }
}

$countQ = mysqli_query($koneksi, "SELECT COUNT(*) as tot FROM sensor_log");
if ($countQ && $c = mysqli_fetch_assoc($countQ)) {
    $totalRows = intval($c['tot']);
}

// Jika tabel kosong, reset AUTO_INCREMENT kembali ke 1
if ($totalRows === 0) {
    mysqli_query($koneksi, "ALTER TABLE sensor_log AUTO_INCREMENT = 1");
}

$latest = !empty($initialRows) ? $initialRows[0] : null;
$latestSuhu = $latest ? $latest['suhu'] : '--';
$latestKelembapan = $latest ? $latest['kelembapan'] : '--';
$latestAsap = $latest ? $latest['asap'] : '--';
$latestApi = $latest ? $latest['api_text'] : '--';
$latestStatus = $latest ? $latest['status_text'] : '--';
$latestBadgeClass = $latest ? $latest['badge_class'] : 'safe';

// Hitung persentase bar awal
$initSuhuPct = ($latest && is_numeric($latest['suhu'])) ? min(100, max(5, (floatval($latest['suhu']) / 80) * 100)) : 10;
$initAsapPct = ($latest && is_numeric($latest['asap'])) ? min(100, max(5, (intval($latest['asap']) / 800) * 100)) : 10;
$initKelPct = ($latest && is_numeric($latest['kelembapan'])) ? min(100, max(5, floatval($latest['kelembapan']))) : 10;

// Data kronologis untuk grafik (terlama ke terbaru)
$chartInitial = array_reverse($initialRows);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Monitoring</title>

    <!-- Chart.js: Lokal dengan Fallback ke CDN -->
    <script src="js/chart.umd.min.js"></script>
    <script>
        if (typeof Chart === 'undefined') {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js';
            document.head.appendChild(s);
        }
    </script>

    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        .container {
            max-width: 1140px;
            margin: 0 auto;
            padding: 28px 24px 60px;
        }

        /* ===== DASHBOARD HEADER ===== */
        .dash-header {
            background: #ffffff;
            border: 1px solid #e7e5e4;
            border-radius: 16px;
            padding: 26px 30px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 18px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .dash-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #dc2626;
        }

        .dh-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dh-badge-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }

        .dh-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .dh-badge .live-dot {
            width: 6px;
            height: 6px;
            background: #dc2626;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }

        .sync-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .sync-badge .sync-dot {
            width: 6px;
            height: 6px;
            background: #22c55e;
            border-radius: 50%;
            animation: blink 1s infinite;
        }

        /* Toggle Auto-simpan */
        .auto-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 600;
            color: #57534e;
            cursor: pointer;
            user-select: none;
        }

        .auto-toggle input {
            display: none;
        }

        .auto-toggle .switch {
            width: 30px;
            height: 16px;
            background: #d6d3d1;
            border-radius: 999px;
            position: relative;
            transition: background 0.2s;
        }

        .auto-toggle .switch::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 12px;
            height: 12px;
            background: #ffffff;
            border-radius: 50%;
            transition: transform 0.2s;
        }

        .auto-toggle input:checked + .switch {
            background: #16a34a;
        }

        .auto-toggle input:checked + .switch::after {
            transform: translateX(14px);
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        .dh-left h1 {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #1c1917;
            margin: 0;
        }

        .dh-left p {
            font-size: 13.5px;
            color: #57534e;
            margin: 0;
        }

        .dh-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .clock-box {
            background: #fafaf9;
            border: 1px solid #e7e5e4;
            border-radius: 10px;
            padding: 8px 16px;
            text-align: right;
        }

        .clock-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 15px;
            font-weight: 700;
            color: #1c1917;
        }

        .clock-date {
            font-size: 11px;
            color: #78716c;
        }

        .btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 15px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            font-family: inherit;
        }

        .btn-action.outline {
            background: #ffffff;
            border-color: #d6d3d1;
            color: #1c1917;
        }

        .btn-action.outline:hover {
            background: #f5f5f4;
            border-color: #a8a29e;
        }

        .btn-action.dark {
            background: #1c1917;
            color: #ffffff;
        }

        .btn-action.dark:hover {
            background: #292524;
        }

        .btn-action.outline.danger {
            color: #dc2626;
            border-color: #fecaca;
            background: #ffffff;
        }

        .btn-action.outline.danger:hover {
            background: #fef2f2;
            border-color: #f87171;
        }

        /* ===== METRIC CARDS (4-GRID) ===== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .m-card {
            background: #ffffff;
            border: 1px solid #e7e5e4;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .m-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(28, 25, 23, 0.06);
        }

        .m-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .m-title {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #78716c;
        }

        .m-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .m-icon.suhu { background: #fef2f2; color: #dc2626; }
        .m-icon.asap { background: #fff7ed; color: #ea580c; }
        .m-icon.api { background: #fff7ed; color: #c2410c; }
        .m-icon.status { background: #f0fdf4; color: #15803d; }

        .m-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 26px;
            font-weight: 800;
            color: #1c1917;
            line-height: 1.1;
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .m-value .unit {
            font-size: 14px;
            font-weight: 600;
            color: #a8a29e;
        }

        .m-track {
            width: 100%;
            height: 5px;
            background: #f5f5f4;
            border-radius: 999px;
            overflow: hidden;
            margin: 12px 0 10px;
        }

        .m-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.4s ease;
        }

        .m-fill.suhu { background: #dc2626; }
        .m-fill.asap { background: #ea580c; }
        .m-fill.kel { background: #2563eb; }
        .m-fill.status { background: #16a34a; }

        .m-footer {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            color: #78716c;
            font-weight: 500;
        }

        .dot-ind {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dot-ind.safe { background: #22c55e; }
        .dot-ind.warning { background: #f59e0b; }
        .dot-ind.danger { background: #ef4444; }

        /* ===== CHART SECTION ===== */
        .chart-card {
            background: #ffffff;
            border: 1px solid #e7e5e4;
            border-radius: 14px;
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .chart-header-left h2 {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #1c1917;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-header-left p {
            font-size: 13px;
            color: #78716c;
            margin: 0;
        }

        .filter-group {
            display: flex;
            gap: 4px;
            background: #f5f5f4;
            padding: 3px;
            border-radius: 8px;
            border: 1px solid #e7e5e4;
        }

        .filter-btn {
            background: transparent;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #57534e;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .filter-btn:hover {
            color: #1c1917;
        }

        .filter-btn.active {
            background: #ffffff;
            color: #1c1917;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            font-weight: 700;
        }

        .chart-canvas-wrap {
            height: 340px;
            position: relative;
        }

        /* ===== TABLE SECTION ===== */
        .table-card {
            background: #ffffff;
            border: 1px solid #e7e5e4;
            border-radius: 14px;
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .table-header-left h2 {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #1c1917;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-header-left p {
            font-size: 13px;
            color: #78716c;
            margin: 0;
        }

        .table-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .table-search {
            position: relative;
            min-width: 240px;
        }

        .table-search input {
            width: 100%;
            padding: 9px 14px 9px 36px;
            background: #fafaf9;
            border: 1px solid #e7e5e4;
            border-radius: 8px;
            font-size: 13px;
            color: #1c1917;
            outline: none;
            font-family: inherit;
            transition: all 0.15s;
        }

        .table-search input:focus {
            background: #ffffff;
            border-color: #a8a29e;
            box-shadow: 0 0 0 3px rgba(28, 25, 23, 0.05);
        }

        .table-search .search-ico {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: #a8a29e;
            pointer-events: none;
        }

        .table-wrap {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e7e5e4;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        table.data-table thead {
            background: #fafaf9;
            border-bottom: 1px solid #e7e5e4;
        }

        table.data-table th {
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #78716c;
            white-space: nowrap;
        }

        table.data-table tbody tr {
            border-bottom: 1px solid #f5f5f4;
            transition: background 0.15s ease;
        }

        table.data-table tbody tr:hover {
            background: #fafaf9;
        }

        table.data-table tbody tr:last-child {
            border-bottom: none;
        }

        table.data-table td {
            padding: 12px 16px;
            color: #1c1917;
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Metric Pills */
        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
        }

        .pill.suhu {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .pill.kel {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        .pill.asap {
            background: #fff7ed;
            color: #ea580c;
            border: 1px solid #fed7aa;
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .badge.safe {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge.warning {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .badge.danger {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .table-footer {
            margin-top: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #78716c;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* ===== TOAST NOTIFICATION ===== */
        .toast-notify {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1c1917;
            color: #ffffff;
            padding: 12px 18px;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(28, 25, 23, 0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            z-index: 2000;
            transform: translateY(80px);
            opacity: 0;
            transition: all 0.25s ease;
            pointer-events: none;
        }

        .toast-notify.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* ===== FOOTER ===== */
        .dash-footer {
            text-align: center;
            padding-top: 18px;
            color: #a8a29e;
            font-size: 12.5px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 16px 14px 40px;
            }
            .dash-header {
                padding: 20px 18px;
            }
            .dh-left h1 {
                font-size: 20px;
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .chart-card, .table-card {
                padding: 18px 16px;
            }
            .chart-canvas-wrap {
                height: 280px;
            }
            .table-search {
                min-width: 100%;
            }
        }

        /* ===== MAP CARD & LOKASI SENSOR (BALIKPAPAN) ===== */
        .dash-map-card {
            background: #ffffff;
            border: 1px solid #e7e5e4;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }

        .dash-map-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #dc2626;
        }

        .dash-map-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 18px;
        }

        .dash-map-header-left h2 {
            margin: 0 0 4px 0;
            font-size: 18px;
            font-weight: 800;
            color: #1c1917;
            letter-spacing: -0.02em;
        }

        .dash-map-header-left p {
            margin: 0;
            font-size: 13px;
            color: #78716c;
        }

        .dash-map-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-map-filter {
            padding: 6px 13px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            border: 1px solid #e7e5e4;
            background: #ffffff;
            color: #44403c;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-map-filter:hover {
            background: #f5f5f4;
            border-color: #d6d3d1;
        }

        .btn-map-filter.active {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
        }

        .dash-map-wrap {
            width: 100%;
            height: 460px;
            border-radius: 12px;
            border: 1px solid #e7e5e4;
            overflow: hidden;
            margin-bottom: 18px;
            position: relative;
        }

        #dashMap {
            width: 100%;
            height: 100%;
            z-index: 5;
        }

        /* Marker Pin Animasi Radar */
        .custom-pin {
            position: relative;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pin-radar {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(220, 38, 38, 0.35);
            animation: radarPulseDash 2s cubic-bezier(0, 0.2, 0.8, 1) infinite;
            z-index: 1;
        }

        .pin-radar.green {
            background: rgba(16, 185, 129, 0.35);
        }

        @keyframes radarPulseDash {
            0% { transform: scale(0.6); opacity: 1; }
            100% { transform: scale(2.2); opacity: 0; }
        }

        .pin-body {
            position: relative;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #dc2626;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.45);
            border: 2px solid #ffffff;
            z-index: 2;
            transition: transform 0.2s ease;
        }

        .pin-body.green {
            background: #059669;
            box-shadow: 0 4px 10px rgba(5, 150, 105, 0.45);
        }

        .custom-pin:hover .pin-body {
            transform: scale(1.15);
        }

        /* Kartu Ringkasan 2 Lokasi di Bawah Peta */
        .dash-locations-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .dash-loc-card {
            background: #fafaf9;
            border: 1px solid #e7e5e4;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .dash-loc-card:hover {
            background: #ffffff;
            border-color: #d6d3d1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .dash-loc-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .dash-loc-badge {
            font-size: 10.5px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 5px;
            background: #f5f5f4;
            color: #44403c;
            border: 1px solid #e7e5e4;
            letter-spacing: 0.3px;
        }

        .dash-loc-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            font-weight: 700;
            color: #047857;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 2px 8px;
            border-radius: 999px;
        }

        .dash-loc-card h4 {
            margin: 0 0 4px 0;
            font-size: 15px;
            font-weight: 800;
            color: #1c1917;
        }

        .dash-loc-address {
            margin: 0 0 12px 0;
            font-size: 12px;
            color: #78716c;
            line-height: 1.45;
        }

        .dash-loc-metrics {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .dash-metric-pill {
            background: #ffffff;
            border: 1px solid #e7e5e4;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 11px;
            color: #44403c;
        }

        .dash-metric-pill strong {
            font-family: 'JetBrains Mono', monospace;
            color: #1c1917;
        }

        .dash-loc-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 10px;
            border-top: 1px solid #e7e5e4;
        }

        .dash-coord-text {
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
            color: #a8a29e;
        }

        .btn-loc-focus {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            background: #ffffff;
            color: #1c1917;
            border: 1px solid #e7e5e4;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-loc-focus:hover {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        /* Leaflet popup customization */
        .dash-map-wrap .leaflet-popup-content-wrapper {
            border-radius: 12px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 10px 25px -4px rgba(0, 0, 0, 0.15);
        }

        .dash-map-wrap .leaflet-popup-content {
            margin: 0 !important;
            min-width: 240px;
            max-width: 280px;
        }

        @media (max-width: 768px) {
            .dash-locations-grid {
                grid-template-columns: 1fr;
            }
            .dash-map-wrap {
                height: 360px;
            }
        }
    </style>
</head>

<body>

    <div class="container">

        <!-- HEADER -->
        <div class="dash-header">
            <div class="dh-left">
                <div class="dh-badge-group">
                    <div class="dh-badge">
                        <span class="live-dot"></span>
                        <span>Telemetri Real-Time</span>
                    </div>
                    <div class="sync-badge">
                        <span class="sync-dot"></span>
                        <span>Update Otomatis: <strong id="countdown-text">15s</strong></span>
                    </div>
                    <label class="auto-toggle" title="Simpan data baru ke database setiap 15 detik">
                        <input type="checkbox" id="autoSimToggle" checked>
                        <span class="switch"></span>
                        <span>Auto-simpan data</span>
                    </label>
                </div>
                <h1>Dashboard Sistem Monitoring</h1>
                <p>Pemantauan telemetri sensor lingkungan dan deteksi dini secara real-time.</p>
            </div>

            <div class="dh-right">
                <div class="clock-box">
                    <div class="clock-time" id="live-clock">--:--:-- WIB</div>
                    <div class="clock-date" id="live-date">Memuat tanggal...</div>
                </div>

                <div class="btn-group">
                    <button class="btn-action outline" onclick="manualRefresh()" title="Segarkan Data Sekarang">
                        <span id="refresh-spinner">🔄</span> Segarkan
                    </button>
                    <button class="btn-action dark" onclick="simulateData()" title="Kirim Data Sensor Uji">
                        🧪 Uji Sensor
                    </button>
                    <button class="btn-action outline danger" onclick="resetDatabase()" title="Kosongkan database dan mulai ID baru dari 1">
                        🗑️ Reset Log
                    </button>
                </div>
            </div>
        </div>

        <!-- 4 METRIC CARDS -->
        <div class="dashboard-grid">

            <!-- CARD 1: SUHU -->
            <div class="m-card">
                <div class="m-header">
                    <span class="m-title">Suhu Lingkungan</span>
                    <div class="m-icon suhu">🌡️</div>
                </div>
                <div class="m-value">
                    <span id="val-suhu"><?php echo htmlspecialchars($latestSuhu); ?></span>
                    <span class="unit">°C</span>
                </div>
                <div class="m-track">
                    <div class="m-fill suhu" id="bar-suhu" style="width: <?php echo $initSuhuPct; ?>%;"></div>
                </div>
                <div class="m-footer" id="badge-suhu">
                    <span class="dot-ind <?php echo $latestBadgeClass; ?>"></span>
                    <span id="text-suhu-status"><?php echo (floatval($latestSuhu) >= ($thresholds['suhu']['batas_max'] ?? 40)) ? 'Suhu Tinggi' : 'Normal Standby'; ?></span>
                </div>
            </div>

            <!-- CARD 2: ASAP / GAS -->
            <div class="m-card">
                <div class="m-header">
                    <span class="m-title">Asap / Gas (MQ-2)</span>
                    <div class="m-icon asap">💨</div>
                </div>
                <div class="m-value">
                    <span id="val-asap"><?php echo htmlspecialchars($latestAsap); ?></span>
                    <span class="unit">PPM</span>
                </div>
                <div class="m-track">
                    <div class="m-fill asap" id="bar-asap" style="width: <?php echo $initAsapPct; ?>%;"></div>
                </div>
                <div class="m-footer" id="badge-asap">
                    <span class="dot-ind <?php echo $latestBadgeClass; ?>"></span>
                    <span id="text-asap-status"><?php echo (intval($latestAsap) >= ($thresholds['asap']['batas_max'] ?? 350)) ? 'Pekat / Bahaya' : 'Udara Bersih'; ?></span>
                </div>
            </div>

            <!-- CARD 3: API & KELEMBAPAN -->
            <div class="m-card">
                <div class="m-header">
                    <span class="m-title">Indikasi Api</span>
                    <div class="m-icon api">🔥</div>
                </div>
                <div class="m-value">
                    <span id="val-api"><?php echo htmlspecialchars($latestApi); ?></span>
                </div>
                <div class="m-track">
                    <div class="m-fill kel" id="bar-kel" style="width: <?php echo $initKelPct; ?>%;"></div>
                </div>
                <div class="m-footer" id="badge-api">
                    <span>💧 Kelembapan: <strong id="val-kelembapan" style="color: #2563eb;"><?php echo htmlspecialchars($latestKelembapan); ?>%</strong></span>
                </div>
            </div>

            <!-- CARD 4: STATUS SISTEM -->
            <div class="m-card">
                <div class="m-header">
                    <span class="m-title">Status Sistem</span>
                    <div class="m-icon status">🚨</div>
                </div>
                <div class="m-value">
                    <span id="val-status"><?php echo htmlspecialchars($latestStatus); ?></span>
                </div>
                <div class="m-track">
                    <div class="m-fill status" id="bar-status" style="width: 100%;"></div>
                </div>
                <div class="m-footer" id="badge-status">
                    <span class="dot-ind <?php echo $latestBadgeClass; ?>"></span>
                    <span id="text-system-status"><?php echo ($latestStatus === 'BAHAYA') ? 'Alarm Peringatan!' : 'Sistem Memantau'; ?></span>
                </div>
            </div>

        </div>

        <!-- GRAFIK TELEMETRI -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-header-left">
                    <h2>📈 Grafik Telemetri Sensor Real-Time</h2>
                    <p>Visualisasi tren Suhu, Kelembapan, dan Asap secara berkala (Auto-update 15 detik).</p>
                </div>

                <div class="filter-group">
                    <button class="filter-btn active" onclick="setChartFilter('all', this)">Semua Metrik</button>
                    <button class="filter-btn" onclick="setChartFilter('suhu', this)">🌡️ Suhu</button>
                    <button class="filter-btn" onclick="setChartFilter('kelembapan', this)">💧 Kelembapan</button>
                    <button class="filter-btn" onclick="setChartFilter('asap', this)">💨 Asap</button>
                </div>
            </div>

            <div class="chart-canvas-wrap">
                <canvas id="telemetryChart"></canvas>
            </div>
        </div>

        <!-- PETA PERSEBARAN LOKASI ALAT SENSOR (BALIKPAPAN) -->
        <div class="dash-map-card">
            <div class="dash-map-header">
                <div class="dash-map-header-left">
                    <h2>🗺️ Peta Persebaran Titik Alat Sensor (Balikpapan)</h2>
                    <p>Lokasi geografis perangkat telemetri IoT terpasang di Politeknik Negeri Balikpapan &amp; Lapangan Merdeka.</p>
                </div>

                <div class="dash-map-toolbar">
                    <button type="button" class="btn-map-filter active" id="btnDashFitAll" onclick="fitAllDashLocations()">
                        Semua Titik (2)
                    </button>
                    <button type="button" class="btn-map-filter" id="btnDashFocusNode1" onclick="focusDashNode(0)">
                        📍 Node 01: Poltekba
                    </button>
                    <button type="button" class="btn-map-filter" id="btnDashFocusNode2" onclick="focusDashNode(1)">
                        📍 Node 02: Lap. Merdeka
                    </button>
                    <button type="button" class="btn-map-filter active" id="btnDashStreet" onclick="switchDashBaseMap('street')">
                        🗺️ Jalan
                    </button>
                    <button type="button" class="btn-map-filter" id="btnDashSatellite" onclick="switchDashBaseMap('satellite')">
                        🛰️ Satelit
                    </button>
                </div>
            </div>

            <div class="dash-map-wrap">
                <div id="dashMap"></div>
            </div>

            <div class="dash-locations-grid">
                <!-- KARTU NODE 01: POLTEKBA -->
                <div class="dash-loc-card" id="dash-card-node-0">
                    <div>
                        <div class="dash-loc-top">
                            <span class="dash-loc-badge">NODE #01 &middot; POLTEKBA</span>
                            <span class="dash-loc-status">
                                <span style="width:6px; height:6px; border-radius:50%; background:#10b981;"></span>
                                Online &middot; Monitoring
                            </span>
                        </div>
                        <h4>Politeknik Negeri Balikpapan (POLTEKBA)</h4>
                        <p class="dash-loc-address">Jl. Soekarno-Hatta Km. 8, Batu Ampar, Balikpapan Utara, Kota Balikpapan</p>

                        <div class="dash-loc-metrics">
                            <div class="dash-metric-pill">
                                Suhu: <strong id="dash-node1-suhu"><?php echo htmlspecialchars($latestSuhu); ?><?php echo is_numeric($latestSuhu) ? '°C' : ''; ?></strong>
                            </div>
                            <div class="dash-metric-pill">
                                Kelembapan: <strong id="dash-node1-kel"><?php echo htmlspecialchars($latestKelembapan); ?><?php echo is_numeric($latestKelembapan) ? '%' : ''; ?></strong>
                            </div>
                            <div class="dash-metric-pill">
                                Asap: <strong id="dash-node1-asap"><?php echo htmlspecialchars($latestAsap); ?><?php echo is_numeric($latestAsap) ? ' PPM' : ''; ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="dash-loc-footer">
                        <span class="dash-coord-text">Lat: -1.20200 &middot; Lng: 116.88564</span>
                        <button type="button" class="btn-loc-focus" onclick="focusDashNode(0)">
                            🔍 Lihat di Peta
                        </button>
                    </div>
                </div>

                <!-- KARTU NODE 02: LAPANGAN MERDEKA -->
                <div class="dash-loc-card" id="dash-card-node-1">
                    <div>
                        <div class="dash-loc-top">
                            <span class="dash-loc-badge">NODE #02 &middot; PUSAT KOTA</span>
                            <span class="dash-loc-status">
                                <span style="width:6px; height:6px; border-radius:50%; background:#10b981;"></span>
                                Siaga &middot; Normal
                            </span>
                        </div>
                        <h4>Lapangan Merdeka Balikpapan</h4>
                        <p class="dash-loc-address">Kawasan Lapangan Merdeka, Jl. Jend. Sudirman, Prapatan, Balikpapan Kota</p>

                        <div class="dash-loc-metrics">
                            <div class="dash-metric-pill">
                                Suhu: <strong>30.1°C</strong>
                            </div>
                            <div class="dash-metric-pill">
                                Kelembapan: <strong>71.0%</strong>
                            </div>
                            <div class="dash-metric-pill">
                                Kualitas: <strong>Aman (Normal)</strong>
                            </div>
                        </div>
                    </div>

                    <div class="dash-loc-footer">
                        <span class="dash-coord-text">Lat: -1.27960 &middot; Lng: 116.81461</span>
                        <button type="button" class="btn-loc-focus" onclick="focusDashNode(1)">
                            🔍 Lihat di Peta
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABEL LOG RIWAYAT -->
        <div class="table-card">
            <div class="table-header">
                <div class="table-header-left">
                    <h2>📋 Log Riwayat Data Sensor</h2>
                    <p>Daftar rekaman sensor terbaru dari database, update otomatis setiap 15 detik.</p>
                </div>

                <div class="table-actions">
                    <div class="table-search">
                        <span class="search-ico">🔍</span>
                        <input type="text" id="tableSearch" placeholder="Cari data sensor..." onkeyup="filterTable()">
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table" id="sensorTable">
                    <thead>
                        <tr>
                            <th># ID</th>
                            <th>🕒 Waktu & Tanggal</th>
                            <th>🌡️ Suhu (°C)</th>
                            <th>💧 Kelembapan (%)</th>
                            <th>💨 Asap (PPM)</th>
                            <th>🔥 Indikasi Api</th>
                            <th>🚨 Status Sistem</th>
                        </tr>
                    </thead>
                    <tbody id="sensor-table-body">
                        <?php if (empty($initialRows)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 28px; color: #a8a29e;">
                                    Belum ada data sensor tercatat. Data baru akan otomatis dimulai dari ID 1.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($initialRows as $row): ?>
                                <tr>
                                    <td><span style="font-family:'JetBrains Mono',monospace;font-weight:600;color:#78716c;">#<?php echo $row['id']; ?></span></td>
                                    <td>
                                        <div style="font-family:'JetBrains Mono',monospace;font-weight:600;color:#1c1917;"><?php echo $row['formatted_time']; ?></div>
                                        <div style="font-size:11px;color:#a8a29e;"><?php echo date('d M Y', strtotime($row['waktu'])); ?></div>
                                    </td>
                                    <td>
                                        <span class="pill suhu"><?php echo $row['suhu']; ?> &deg;C</span>
                                    </td>
                                    <td>
                                        <span class="pill kel"><?php echo $row['kelembapan']; ?> %</span>
                                    </td>
                                    <td>
                                        <span class="pill asap"><?php echo $row['asap']; ?> PPM</span>
                                    </td>
                                    <td style="font-weight:600;color:#1c1917;">
                                        <?php echo $row['api_text']; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $row['badge_class']; ?>">
                                            ● <?php echo $row['status_text']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <div>
                    Menampilkan <strong id="log-count" style="color: #1c1917;"><?php echo count($initialRows); ?></strong> data terbaru (Total: <strong id="total-count" style="color: #1c1917;"><?php echo $totalRows; ?></strong> log).
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #22c55e;"></span>
                    <span>Database: MySQL Aktif</span>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="dash-footer">
            <p>&copy; <?php echo date('Y'); ?> Sistem Monitoring</p>
        </footer>

    </div>

    <!-- TOAST NOTIFICATION -->
    <div class="toast-notify" id="toastBox">
        <span id="toastIcon">⚡</span>
        <span id="toastMsg">Data berhasil diperbarui!</span>
    </div>

    <!-- JAVASCRIPT ENGINE -->
    <script>
        const REFRESH_INTERVAL = 15; // detik
        const initialChartData = <?php echo json_encode($chartInitial); ?>;
        let chartInstance = null;
        let currentFilter = 'all';
        let countdownSeconds = REFRESH_INTERVAL;
        let isFetching = false;

        // Status auto-simpan data (diingat di browser)
        let autoSimulate = true;
        try {
            const saved = localStorage.getItem('autoSimulate');
            if (saved !== null) autoSimulate = (saved === '1');
        } catch (e) {}

        // 1. UPDATE JAM REALTIME
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('live-clock').textContent = `${hours}:${minutes}:${seconds} WIB`;

            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('live-date').textContent = now.toLocaleDateString('id-ID', options);
        }
        setInterval(updateClock, 1000);
        updateClock();

        // 2. SIKLUS OTOMATIS: simpan data baru ke DB (jika aktif) lalu muat ulang tampilan
        async function autoCycle() {
            if (autoSimulate) {
                try {
                    await fetch('api_sensor.php?action=simulate&t=' + Date.now());
                } catch (e) {
                    console.error('Gagal menyimpan data otomatis:', e);
                }
            }
            await fetchSensorData(false);
        }

        // 3. HITUNG MUNDUR 15 DETIK
        function startAutoUpdateTimer() {
            const toggle = document.getElementById('autoSimToggle');
            if (toggle) {
                toggle.checked = autoSimulate;
                toggle.addEventListener('change', () => {
                    autoSimulate = toggle.checked;
                    try { localStorage.setItem('autoSimulate', autoSimulate ? '1' : '0'); } catch (e) {}
                    showToast(autoSimulate ? 'Auto-simpan data aktif' : 'Auto-simpan data dimatikan', autoSimulate ? '✅' : '⏸️');
                });
            }

            setInterval(() => {
                countdownSeconds--;
                const countdownEl = document.getElementById('countdown-text');
                if (countdownSeconds <= 0) {
                    if (countdownEl) countdownEl.textContent = 'Memperbarui...';
                    countdownSeconds = REFRESH_INTERVAL;
                    autoCycle();
                } else {
                    if (countdownEl) countdownEl.textContent = `${countdownSeconds}s`;
                }
            }, 1000);
        }

        // 4. INISIALISASI GRAFIK
        function initChart(dataList) {
            const ctx = document.getElementById('telemetryChart').getContext('2d');

            const labels = dataList.map(item => item.formatted_time || (item.waktu ? item.waktu.substring(11, 19) : ''));
            const dataSuhu = dataList.map(item => parseFloat(item.suhu));
            const dataKelembapan = dataList.map(item => parseFloat(item.kelembapan));
            const dataAsap = dataList.map(item => parseInt(item.asap));

            const gradSuhu = ctx.createLinearGradient(0, 0, 0, 320);
            gradSuhu.addColorStop(0, 'rgba(220, 38, 38, 0.16)');
            gradSuhu.addColorStop(1, 'rgba(220, 38, 38, 0.0)');

            const gradKelembapan = ctx.createLinearGradient(0, 0, 0, 320);
            gradKelembapan.addColorStop(0, 'rgba(37, 99, 235, 0.14)');
            gradKelembapan.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

            const gradAsap = ctx.createLinearGradient(0, 0, 0, 320);
            gradAsap.addColorStop(0, 'rgba(234, 88, 12, 0.14)');
            gradAsap.addColorStop(1, 'rgba(234, 88, 12, 0.0)');

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Suhu (°C)',
                            data: dataSuhu,
                            borderColor: '#dc2626',
                            backgroundColor: gradSuhu,
                            borderWidth: 2.2,
                            tension: 0.35,
                            cubicInterpolationMode: 'monotone',
                            fill: true,
                            pointRadius: 3.5,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#dc2626',
                            pointBorderWidth: 2,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Kelembapan (%)',
                            data: dataKelembapan,
                            borderColor: '#2563eb',
                            backgroundColor: gradKelembapan,
                            borderWidth: 2.2,
                            tension: 0.35,
                            cubicInterpolationMode: 'monotone',
                            fill: true,
                            pointRadius: 3.5,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#2563eb',
                            pointBorderWidth: 2,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Asap / Gas (PPM)',
                            data: dataAsap,
                            borderColor: '#ea580c',
                            backgroundColor: gradAsap,
                            borderWidth: 2.2,
                            tension: 0.35,
                            cubicInterpolationMode: 'monotone',
                            fill: true,
                            pointRadius: 3.5,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#ea580c',
                            pointBorderWidth: 2,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 700,
                        easing: 'easeOutQuart'
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 7,
                                color: '#57534e',
                                font: {
                                    family: "'Inter', sans-serif",
                                    weight: 600,
                                    size: 12
                                },
                                padding: 14
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1c1917',
                            borderColor: '#44403c',
                            borderWidth: 1,
                            titleColor: '#ffffff',
                            bodyColor: '#e7e5e4',
                            titleFont: { family: "'Inter', sans-serif", weight: 700, size: 12 },
                            bodyFont: { family: "'Inter', sans-serif", size: 11.5 },
                            padding: 10,
                            cornerRadius: 8,
                            boxPadding: 4,
                            usePointStyle: true
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: '#f5f5f4' },
                            ticks: {
                                font: { family: "'Inter', sans-serif", size: 11 },
                                color: '#78716c'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Suhu (°C) & Kelembapan (%)',
                                font: { family: "'Inter', sans-serif", size: 11, weight: 600 },
                                color: '#78716c'
                            },
                            suggestedMin: 10,
                            suggestedMax: 100,
                            grid: { color: '#f5f5f4' },
                            ticks: { color: '#78716c' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Asap / Gas (PPM)',
                                font: { family: "'Inter', sans-serif", size: 11, weight: 600 },
                                color: '#ea580c'
                            },
                            suggestedMin: 0,
                            suggestedMax: 800,
                            grid: { drawOnChartArea: false },
                            ticks: { color: '#ea580c' }
                        }
                    }
                }
            });
        }

        // 5. FILTER METRIK GRAFIK
        function setChartFilter(type, btn) {
            currentFilter = type;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            if (!chartInstance) return;

            if (type === 'all') {
                chartInstance.setDatasetVisibility(0, true);
                chartInstance.setDatasetVisibility(1, true);
                chartInstance.setDatasetVisibility(2, true);
                chartInstance.options.scales.y.display = true;
                chartInstance.options.scales.y1.display = true;
            } else if (type === 'suhu') {
                chartInstance.setDatasetVisibility(0, true);
                chartInstance.setDatasetVisibility(1, false);
                chartInstance.setDatasetVisibility(2, false);
                chartInstance.options.scales.y.display = true;
                chartInstance.options.scales.y1.display = false;
            } else if (type === 'kelembapan') {
                chartInstance.setDatasetVisibility(0, false);
                chartInstance.setDatasetVisibility(1, true);
                chartInstance.setDatasetVisibility(2, false);
                chartInstance.options.scales.y.display = true;
                chartInstance.options.scales.y1.display = false;
            } else if (type === 'asap') {
                chartInstance.setDatasetVisibility(0, false);
                chartInstance.setDatasetVisibility(1, false);
                chartInstance.setDatasetVisibility(2, true);
                chartInstance.options.scales.y.display = false;
                chartInstance.options.scales.y1.display = true;
            }

            chartInstance.update({ duration: 500 });
        }

        // 6. AMBIL DATA SENSOR DARI DB & UPDATE TAMPILAN
        async function fetchSensorData(isManual = false) {
            if (isFetching) return;
            isFetching = true;

            try {
                const response = await fetch('api_sensor.php?limit=15&t=' + Date.now());
                const data = await response.json();

                if (data.status === 'success') {
                    // Update 4 Card Utama
                    if (data.latest) {
                        const l = data.latest;
                        document.getElementById('val-suhu').textContent = l.suhu;
                        document.getElementById('val-asap').textContent = l.asap;
                        document.getElementById('val-api').textContent = l.api_text;
                        document.getElementById('val-kelembapan').textContent = l.kelembapan + '%';
                        document.getElementById('val-status').textContent = l.status_text;

                        const sVal = parseFloat(l.suhu);
                        const aVal = parseInt(l.asap);
                        const kVal = parseFloat(l.kelembapan);

                        const barSuhu = document.getElementById('bar-suhu');
                        if (barSuhu) barSuhu.style.width = Math.min(100, Math.max(5, (sVal / 80) * 100)) + '%';
                        const barAsap = document.getElementById('bar-asap');
                        if (barAsap) barAsap.style.width = Math.min(100, Math.max(5, (aVal / 800) * 100)) + '%';
                        const barKel = document.getElementById('bar-kel');
                        if (barKel) barKel.style.width = Math.min(100, Math.max(5, kVal)) + '%';

                        const suhuStatusEl = document.getElementById('text-suhu-status');
                        const thSuhuW = (data.thresholds && data.thresholds.suhu) ? parseFloat(data.thresholds.suhu.batas_max) : <?php echo floatval($thresholds['suhu']['batas_max'] ?? 40); ?>;
                        const thAsapW = (data.thresholds && data.thresholds.asap) ? parseFloat(data.thresholds.asap.batas_max) : <?php echo floatval($thresholds['asap']['batas_max'] ?? 350); ?>;

                        const suhuStatusEl = document.getElementById('text-suhu-status');
                        if (suhuStatusEl) suhuStatusEl.textContent = (sVal >= thSuhuW) ? 'Suhu Tinggi' : 'Normal Standby';

                        const asapStatusEl = document.getElementById('text-asap-status');
                        if (asapStatusEl) asapStatusEl.textContent = (aVal >= thAsapW) ? 'Pekat / Bahaya' : 'Udara Bersih';

                        const sysStatusEl = document.getElementById('text-system-status');
                        if (sysStatusEl) sysStatusEl.textContent = (l.status_text === 'BAHAYA') ? 'Alarm Peringatan!' : 'Sistem Memantau';

                        document.querySelectorAll('.dot-ind').forEach(dot => {
                            dot.className = `dot-ind ${l.badge_class}`;
                        });

                        // Update metrik peta Node 01 (Poltekba)
                        const mSuhu = document.getElementById('dash-node1-suhu');
                        if (mSuhu) mSuhu.textContent = l.suhu + '°C';
                        const mKel = document.getElementById('dash-node1-kel');
                        if (mKel) mKel.textContent = l.kelembapan + '%';
                        const mAsap = document.getElementById('dash-node1-asap');
                        if (mAsap) mAsap.textContent = l.asap + ' PPM';
                    } else {
                        // Data kosong di database
                        document.getElementById('val-suhu').textContent = '--';
                        document.getElementById('val-asap').textContent = '--';
                        document.getElementById('val-api').textContent = '--';
                        document.getElementById('val-kelembapan').textContent = '--';
                        document.getElementById('val-status').textContent = '--';

                        // Kosongkan metrik peta Node 01
                        const mSuhu = document.getElementById('dash-node1-suhu');
                        if (mSuhu) mSuhu.textContent = '--';
                        const mKel = document.getElementById('dash-node1-kel');
                        if (mKel) mKel.textContent = '--';
                        const mAsap = document.getElementById('dash-node1-asap');
                        if (mAsap) mAsap.textContent = '--';

                        const barSuhu = document.getElementById('bar-suhu');
                        if (barSuhu) barSuhu.style.width = '10%';
                        const barAsap = document.getElementById('bar-asap');
                        if (barAsap) barAsap.style.width = '10%';
                        const barKel = document.getElementById('bar-kel');
                        if (barKel) barKel.style.width = '10%';

                        const suhuStatusEl = document.getElementById('text-suhu-status');
                        if (suhuStatusEl) suhuStatusEl.textContent = 'Menunggu Data';
                        const asapStatusEl = document.getElementById('text-asap-status');
                        if (asapStatusEl) asapStatusEl.textContent = 'Menunggu Data';
                        const sysStatusEl = document.getElementById('text-system-status');
                        if (sysStatusEl) sysStatusEl.textContent = 'Standby (ID: 1)';

                        document.querySelectorAll('.dot-ind').forEach(dot => {
                            dot.className = 'dot-ind safe';
                        });
                    }

                    // Update Grafik
                    if (chartInstance) {
                        if (data.chart_data && data.chart_data.length > 0) {
                            chartInstance.data.labels = data.chart_data.map(item => item.formatted_time || (item.waktu ? item.waktu.substring(11, 19) : ''));
                            chartInstance.data.datasets[0].data = data.chart_data.map(item => parseFloat(item.suhu));
                            chartInstance.data.datasets[1].data = data.chart_data.map(item => parseFloat(item.kelembapan));
                            chartInstance.data.datasets[2].data = data.chart_data.map(item => parseInt(item.asap));
                        } else {
                            chartInstance.data.labels = [];
                            chartInstance.data.datasets[0].data = [];
                            chartInstance.data.datasets[1].data = [];
                            chartInstance.data.datasets[2].data = [];
                        }

                        chartInstance.update({
                            duration: 600,
                            easing: 'easeOutQuart'
                        });
                    }

                    // Update Tabel Riwayat
                    if (data.table_data !== undefined) {
                        updateTable(data.table_data);
                        document.getElementById('log-count').textContent = data.table_data.length;
                        document.getElementById('total-count').textContent = data.total_records || 0;
                    }

                    if (isManual) {
                        showToast('Data berhasil diperbarui!');
                    }
                }
            } catch (err) {
                console.error('Error fetching sensor data:', err);
                if (isManual) showToast('Gagal memuat data dari server', '⚠️');
            } finally {
                isFetching = false;
            }
        }

        // 7. UPDATE TABEL
        function updateTable(rows) {
            const tbody = document.getElementById('sensor-table-body');
            if (!tbody) return;

            if (!rows || rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 28px; color: #a8a29e;">Belum ada data sensor tercatat. Data baru akan otomatis dimulai dari ID 1.</td></tr>';
                return;
            }

            let html = '';
            rows.forEach(r => {
                const dateStr = r.formatted_date ? r.formatted_date.split(',')[0] : (r.waktu ? r.waktu.substring(0, 10) : '');
                html += `
                    <tr>
                        <td><span style="font-family:'JetBrains Mono',monospace;font-weight:600;color:#78716c;">#${r.id}</span></td>
                        <td>
                            <div style="font-family:'JetBrains Mono',monospace;font-weight:600;color:#1c1917;">${r.formatted_time || (r.waktu ? r.waktu.substring(11, 19) : '')}</div>
                            <div style="font-size:11px;color:#a8a29e;">${dateStr}</div>
                        </td>
                        <td>
                            <span class="pill suhu">${r.suhu} &deg;C</span>
                        </td>
                        <td>
                            <span class="pill kel">${r.kelembapan} %</span>
                        </td>
                        <td>
                            <span class="pill asap">${r.asap} PPM</span>
                        </td>
                        <td style="font-weight:600;color:#1c1917;">
                            ${r.api_text}
                        </td>
                        <td>
                            <span class="badge ${r.badge_class}">
                                ● ${r.status_text}
                            </span>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            filterTable();
        }

        // 8. SEGARKAN MANUAL
        function manualRefresh() {
            countdownSeconds = REFRESH_INTERVAL;
            const el = document.getElementById('countdown-text');
            if (el) el.textContent = REFRESH_INTERVAL + 's';
            fetchSensorData(true);
        }

        // 9. FILTER TABEL
        function filterTable() {
            const input = document.getElementById('tableSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('sensorTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                if (td.length > 0) {
                    const rowText = tr[i].textContent.toLowerCase();
                    tr[i].style.display = rowText.includes(filter) ? '' : 'none';
                }
            }
        }

        // 10. UJI SENSOR (KIRIM 1 DATA SIMULASI SEKARANG)
        async function simulateData() {
            try {
                const res = await fetch('api_sensor.php?action=simulate&t=' + Date.now());
                const result = await res.json();
                if (result.status === 'success') {
                    showToast('Data sensor berhasil diuji (ID: #' + (result.data ? result.data.id : '') + ')', '🚀');
                    countdownSeconds = REFRESH_INTERVAL;
                    await fetchSensorData(false);
                } else {
                    showToast('Gagal mengirim simulasi', '❌');
                }
            } catch (e) {
                showToast('Terjadi kesalahan simulasi', '❌');
            }
        }

        // 11. RESET DATABASE (KOSONGKAN & KEMBALIKAN ID KE 1)
        async function resetDatabase() {
            if (!confirm('Apakah Anda yakin ingin mengosongkan semua riwayat log di database?\n\nSemua data sensor akan dihapus dan ID data baru berikutnya akan kembali dimulai dari ID 1.')) {
                return;
            }

            try {
                const res = await fetch('api_sensor.php?action=reset&t=' + Date.now());
                const result = await res.json();
                if (result.status === 'success') {
                    showToast('Log berhasil dikosongkan. ID direset ke 1!', '🗑️');
                    countdownSeconds = REFRESH_INTERVAL;
                    await fetchSensorData(false);
                } else {
                    showToast('Gagal mengosongkan database', '❌');
                }
            } catch (e) {
                showToast('Terjadi kesalahan', '❌');
            }
        }

        // 12. TOAST NOTIFIKASI
        function showToast(message, icon = '⚡') {
            const box = document.getElementById('toastBox');
            document.getElementById('toastIcon').textContent = icon;
            document.getElementById('toastMsg').textContent = message;
            box.classList.add('show');
            setTimeout(() => {
                box.classList.remove('show');
            }, 3000);
        }

        // 13. PETA PERSEBARAN LOKASI ALAT SENSOR (BALIKPAPAN)
        let dashMap = null;
        let dashMarkers = [];
        let dashBounds = null;
        let dashStreetLayer = null;
        let dashSatelliteLayer = null;

        const dashDeviceLocations = [
            {
                id: 'NODE-01',
                name: 'Politeknik Negeri Balikpapan (POLTEKBA)',
                sub: 'Gedung Workshop & Lab IoT',
                address: 'Jl. Soekarno-Hatta Km. 8, Batu Ampar, Balikpapan Utara',
                lat: -1.20200,
                lng: 116.88564,
                status: 'ONLINE',
                badgeClass: 'online',
                color: 'red',
                iconSvg: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path></svg>`,
                coordStr: '-1.20200, 116.88564'
            },
            {
                id: 'NODE-02',
                name: 'Lapangan Merdeka Balikpapan',
                sub: 'Pos Pantau Lingkungan & Smart City',
                address: 'Kawasan Lapangan Merdeka, Jl. Jend. Sudirman, Prapatan, Balikpapan Kota',
                lat: -1.27960,
                lng: 116.81461,
                status: 'SIAGA',
                badgeClass: 'online',
                color: 'green',
                iconSvg: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>`,
                coordStr: '-1.27960, 116.81461'
            }
        ];

        function initDashboardMap() {
            const mapEl = document.getElementById('dashMap');
            if (!mapEl || typeof L === 'undefined') return;

            dashMap = L.map('dashMap', {
                center: [-1.23965, 116.85614],
                zoom: 12,
                scrollWheelZoom: false
            });

            dashStreetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap kontributor'
            }).addTo(dashMap);

            dashSatelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: '&copy; Esri World Imagery'
            });

            dashBounds = L.latLngBounds();

            dashDeviceLocations.forEach((dev, idx) => {
                const customIcon = L.divIcon({
                    className: 'dash-custom-pin-container',
                    html: `
                        <div class="custom-pin">
                            <div class="pin-radar ${dev.color === 'green' ? 'green' : ''}"></div>
                            <div class="pin-body ${dev.color === 'green' ? 'green' : ''}">
                                ${dev.iconSvg}
                            </div>
                        </div>
                    `,
                    iconSize: [36, 36],
                    iconAnchor: [18, 18],
                    popupAnchor: [0, -18]
                });

                const marker = L.marker([dev.lat, dev.lng], { icon: customIcon }).addTo(dashMap);

                const popupHtml = `
                    <div style="font-family: inherit;">
                        <div style="background:#1c1917; color:#fff; padding:10px 14px; font-weight:800; font-size:12px; display:flex; justify-content:space-between; align-items:center;">
                            <span>${dev.id} &middot; ${dev.name.split(' ')[0]}</span>
                            <span style="background:#22c55e; color:#fff; font-size:9.5px; padding:2px 6px; border-radius:999px;">${dev.status}</span>
                        </div>
                        <div style="padding:12px 14px; background:#fff;">
                            <strong style="font-size:13px; color:#1c1917; display:block; margin-bottom:3px;">${dev.name}</strong>
                            <p style="font-size:11.5px; color:#78716c; margin:0 0 10px 0; line-height:1.4;">${dev.address}</p>
                            <div style="background:#f5f5f4; font-family:'JetBrains Mono', monospace; font-size:10.5px; padding:4px 8px; border-radius:6px; color:#44403c; margin-bottom:10px;">
                                Koordinat: ${dev.coordStr}
                            </div>
                            <a href="https://www.google.com/maps?q=${dev.lat},${dev.lng}" target="_blank" style="display:block; text-align:center; padding:6px 10px; background:#dc2626; color:#fff; font-size:11px; font-weight:700; border-radius:6px; text-decoration:none;">
                                Buka di Google Maps &rarr;
                            </a>
                        </div>
                    </div>
                `;

                marker.bindPopup(popupHtml, { maxWidth: 280 });
                dashMarkers.push(marker);
                dashBounds.extend([dev.lat, dev.lng]);
            });

            dashMap.fitBounds(dashBounds, { padding: [40, 40] });

            // Invalidate size setelah delay singkat agar seluruh tile ter-render sempurna
            setTimeout(() => {
                if (dashMap) dashMap.invalidateSize();
            }, 500);
        }

        function fitAllDashLocations() {
            setDashMapActiveBtn('btnDashFitAll');
            if (dashMap && dashBounds) {
                dashMap.closePopup();
                dashMap.fitBounds(dashBounds, { padding: [40, 40], maxZoom: 13 });
            }
        }

        function focusDashNode(idx) {
            if (!dashMap || !dashDeviceLocations[idx]) return;
            const target = dashDeviceLocations[idx];

            setDashMapActiveBtn(idx === 0 ? 'btnDashFocusNode1' : 'btnDashFocusNode2');

            const mapEl = document.getElementById('dashMap');
            if (window.innerWidth < 768 && mapEl) {
                mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            dashMap.flyTo([target.lat, target.lng], 17, {
                animate: true,
                duration: 1.2
            });

            setTimeout(() => {
                if (dashMarkers[idx]) dashMarkers[idx].openPopup();
            }, 1300);
        }

        function switchDashBaseMap(type) {
            if (!dashMap) return;
            if (type === 'satellite') {
                dashMap.removeLayer(dashStreetLayer);
                dashMap.addLayer(dashSatelliteLayer);
                document.getElementById('btnDashSatellite').classList.add('active');
                document.getElementById('btnDashStreet').classList.remove('active');
            } else {
                dashMap.removeLayer(dashSatelliteLayer);
                dashMap.addLayer(dashStreetLayer);
                document.getElementById('btnDashStreet').classList.add('active');
                document.getElementById('btnDashSatellite').classList.remove('active');
            }
        }

        function setDashMapActiveBtn(id) {
            ['btnDashFitAll', 'btnDashFocusNode1', 'btnDashFocusNode2'].forEach(bId => {
                const btn = document.getElementById(bId);
                if (btn) btn.classList.remove('active');
            });
            const activeBtn = document.getElementById(id);
            if (activeBtn) activeBtn.classList.add('active');
        }

        // 14. INISIALISASI
        document.addEventListener('DOMContentLoaded', () => {
            initChart(initialChartData);
            initDashboardMap();
            startAutoUpdateTimer();
        });
    </script>

</body>

</html>