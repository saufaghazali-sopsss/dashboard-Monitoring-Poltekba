<?php
// tabel.php - Halaman Khusus Tabel Data Sensor dengan Pagination
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

// Ambil rentang tanggal data yang tersedia di database & total rekaman
$minDate = date('Y-m-d');
$maxDate = date('Y-m-d');
$totalDbRecords = 0;
$allRows = [];

if ($koneksi) {
    $qRange = mysqli_query($koneksi, "SELECT MIN(DATE(waktu)) as min_d, MAX(DATE(waktu)) as max_d, COUNT(*) as total_count FROM sensor_log");
    if ($qRange && $rRange = mysqli_fetch_assoc($qRange)) {
        if (!empty($rRange['min_d'])) $minDate = $rRange['min_d'];
        if (!empty($rRange['max_d'])) $maxDate = $rRange['max_d'];
        $totalDbRecords = intval($rRange['total_count'] ?? 0);
    }

    // Ambil data log (maksimal 1000 untuk pagination sisi klien yang sangat cepat & mulus)
    $initQ = mysqli_query($koneksi, "SELECT * FROM sensor_log ORDER BY id DESC LIMIT 1000");
    $thresholds = getSensorThresholds($koneksi);
    if ($initQ) {
        while ($row = mysqli_fetch_assoc($initQ)) {
            $ev = evaluateSensorStatus($row['suhu'], $row['asap'], $thresholds);
            $row['status_text'] = $ev['status_text'];
            $row['api_text'] = $ev['api_text'];
            $row['badge_class'] = $ev['badge_class'];
            $row['formatted_time'] = date('H:i:s', strtotime($row['waktu']));
            $row['formatted_date'] = date('d M Y', strtotime($row['waktu']));
            $allRows[] = $row;
        }
    }
}

// Pengaturan jumlah data per halaman (default 25, maks 100)
$initialPerPage = 25;
if (isset($_GET['limit']) || isset($_GET['per_page'])) {
    $reqLim = intval($_GET['limit'] ?? $_GET['per_page']);
    if ($reqLim > 0 && $reqLim <= 100) {
        $initialPerPage = $reqLim;
    }
}

// Slice untuk render awal di HTML halaman 1 (data 1 - 25)
$totalInitial = count($allRows);
$initialPageRows = array_slice($allRows, 0, $initialPerPage);
$totalPagesInitial = max(1, (int)ceil($totalInitial / $initialPerPage));

// Hitung statistik data
$initCount = $totalInitial;
$initAvgSuhu = '--';
$initAvgKel = '--';
$initMaxAsap = '--';

if ($initCount > 0) {
    $sumS = 0; $sumK = 0; $maxA = 0;
    foreach ($allRows as $r) {
        $sumS += floatval($r['suhu']);
        $sumK += floatval($r['kelembapan']);
        $asapVal = intval($r['asap']);
        if ($asapVal > $maxA) $maxA = $asapVal;
    }
    $initAvgSuhu = number_format($sumS / $initCount, 1) . '°C';
    $initAvgKel = number_format($sumK / $initCount, 1) . '%';
    $initMaxAsap = $maxA . ' PPM';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabel Data Sensor - Sistem Monitoring</title>

    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">

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
            --accent-emerald: #10b981;
            --emerald-soft: #ecfdf5;
            --emerald-border: #a7f3d0;
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
            max-width: 1240px;
            margin: 0 auto;
            padding: 24px 20px 60px;
        }

        /* ===== KARTU UTAMA TABEL ===== */
        .table-page-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 26px 28px;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .table-page-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #dc2626 0%, #0284c7 50%, #f59e0b 100%);
        }

        .table-page-header {
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

        .refresh-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #047857;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 6px 14px;
            border-radius: 999px;
            cursor: pointer;
            user-select: none;
            transition: var(--transition);
        }

        .refresh-pill:hover {
            background: #d1fae5;
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

        .filter-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
        }

        /* Status Filter Buttons */
        .status-btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-status-filter {
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            font-size: 12.5px;
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

        .btn-status-filter:hover {
            background: #f5f5f4;
            border-color: #d6d3d1;
        }

        .btn-status-filter.active {
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        }

        .btn-status-filter.active[data-status="all"] {
            background: #1c1917;
            border-color: #1c1917;
        }

        .btn-status-filter.active[data-status="AMAN"] {
            background: #15803d;
            border-color: #15803d;
        }

        .btn-status-filter.active[data-status="WASPADA"] {
            background: #d97706;
            border-color: #d97706;
        }

        .btn-status-filter.active[data-status="BAHAYA"] {
            background: #dc2626;
            border-color: #dc2626;
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

        /* Control Tampilan & Limit Row */
        .controls-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            padding-top: 14px;
            border-top: 1px dashed var(--border-color);
        }

        .limit-picker-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .limit-picker-label {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .limit-select {
            padding: 7px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: #ffffff;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
            outline: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .limit-select:focus {
            border-color: var(--accent-cyan);
            box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
        }

        .limit-badge-max {
            font-size: 11px;
            font-weight: 700;
            background: #f1f5f9;
            color: #64748b;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .search-input-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 6px 12px;
            min-width: 240px;
            transition: var(--transition);
        }

        .search-input-wrap:focus-within {
            border-color: var(--accent-cyan);
            box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
        }

        .search-input-wrap input {
            border: none;
            outline: none;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--text-main);
            width: 100%;
            background: transparent;
        }

        /* ===== STATS STRIP ===== */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 22px;
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

        /* ===== ACTION BAR ATAS TABEL ===== */
        .table-action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
        }

        .selection-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
        }

        .selection-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #e2e8f0;
            color: #334155;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .selection-badge.has-selected {
            background: #fee2e2;
            color: #b91c1c;
        }

        .table-btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-bulk-delete {
            padding: 7px 16px;
            border-radius: var(--radius-sm);
            background: #ef4444;
            color: #ffffff;
            border: 1px solid #dc2626;
            font-size: 12.5px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .btn-bulk-delete:hover:not(:disabled) {
            background: #dc2626;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
            transform: translateY(-1px);
        }

        .btn-bulk-delete:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            border-color: #cbd5e1;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-tool {
            padding: 7px 13px;
            border-radius: var(--radius-sm);
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            font-size: 12px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .btn-tool:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        /* ===== TABEL DATA ===== */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            border: 1px solid var(--border-color);
            border-bottom: none;
            background: #ffffff;
        }

        table.sensor-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        table.sensor-table thead {
            background: #fafaf9;
            border-bottom: 1px solid var(--border-color);
        }

        table.sensor-table th {
            padding: 13px 16px;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #78716c;
            white-space: nowrap;
            user-select: none;
        }

        table.sensor-table th.col-checkbox {
            width: 44px;
            text-align: center;
            padding-left: 14px;
            padding-right: 10px;
        }

        table.sensor-table td.col-checkbox {
            text-align: center;
            padding-left: 14px;
            padding-right: 10px;
        }

        table.sensor-table th.col-no {
            width: 48px;
            text-align: center;
            color: #a8a29e;
        }

        table.sensor-table td.col-no {
            text-align: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
        }

        table.sensor-table tbody tr {
            border-bottom: 1px solid #f5f5f4;
            transition: background 0.15s ease;
        }

        table.sensor-table tbody tr:hover {
            background: #fafaf9;
        }

        table.sensor-table tbody tr.row-selected {
            background: #fef2f2 !important;
        }

        table.sensor-table tbody tr:last-child {
            border-bottom: none;
        }

        table.sensor-table td {
            padding: 12px 16px;
            color: var(--text-main);
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Custom Checkbox */
        input[type="checkbox"].custom-chk {
            width: 17px;
            height: 17px;
            border-radius: 4px;
            cursor: pointer;
            accent-color: #dc2626;
            vertical-align: middle;
        }

        /* Metric Pills */
        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .pill.suhu {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .pill.kel {
            background: #eff6ff;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }

        .pill.asap {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
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

        /* Tombol Aksi Row */
        .btn-row-del {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #fecaca;
            background: #ffffff;
            color: #dc2626;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-row-del:hover {
            background: #dc2626;
            color: #ffffff;
            transform: scale(1.06);
        }

        /* Empty State */
        .empty-table-overlay {
            padding: 50px 20px;
            text-align: center;
            display: none;
        }

        .empty-table-overlay.show {
            display: block;
        }

        .empty-table-icon {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .empty-table-overlay h3 {
            margin: 0 0 6px 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--text-main);
        }

        .empty-table-overlay p {
            margin: 0;
            font-size: 13px;
            color: var(--text-muted);
            max-width: 400px;
            margin: 0 auto;
        }

        /* ===== PAGINATION BAR ===== */
        .pagination-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            padding: 14px 20px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 0 0 var(--radius-md) var(--radius-md);
        }

        .pagination-summary {
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pagination-summary strong {
            color: var(--text-main);
        }

        .page-count-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #f1f5f9;
            color: #475569;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            border: 1px solid #e2e8f0;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 6px;
            user-select: none;
        }

        .btn-page {
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-main);
            font-family: 'JetBrains Mono', 'Plus Jakarta Sans', monospace;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .btn-page:hover:not(:disabled):not(.active) {
            background: #f5f5f4;
            border-color: #d6d3d1;
            transform: translateY(-1px);
        }

        .btn-page.active {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
        }

        .btn-page:disabled {
            background: #f8fafc;
            color: #cbd5e1;
            border-color: #e2e8f0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-page-nav {
            padding: 0 12px;
            font-size: 14px;
        }

        .page-dots {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 36px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-muted);
        }

        /* Table Info Footer */
        .table-info-footer {
            margin-top: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12.5px;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 10px;
            padding: 4px 6px;
        }

        /* ===== MODAL KONFIRMASI HAPUS ===== */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .modal-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 440px;
            padding: 26px 26px 22px;
            box-shadow: 0 20px 40px -8px rgba(15, 23, 42, 0.2);
            border: 1px solid var(--border-color);
            transform: scale(0.95);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
        }

        .modal-backdrop.active .modal-card {
            transform: scale(1);
        }

        .modal-icon-danger {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #fee2e2;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 16px;
            box-shadow: 0 0 0 8px #fef2f2;
        }

        .modal-card h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 800;
            color: var(--text-main);
        }

        .modal-card p {
            margin: 0 0 22px 0;
            font-size: 13.5px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-modal-cancel {
            flex: 1;
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-modal-cancel:hover {
            background: #f5f5f4;
        }

        .btn-modal-delete {
            flex: 1;
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            background: #dc2626;
            color: #ffffff;
            border: 1px solid #dc2626;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-modal-delete:hover {
            background: #b91c1c;
            border-color: #b91c1c;
        }

        /* ===== TOAST NOTIFIKASI ===== */
        .toast-notify {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1c1917;
            color: #ffffff;
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 1100;
            transform: translateY(80px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
        }

        .toast-notify.show {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }

        @media (max-width: 900px) {
            .stats-strip {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .main-wrapper {
                padding: 16px 14px 40px;
            }
            .table-page-card {
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
            .controls-row {
                flex-direction: column;
                align-items: stretch;
            }
            .search-input-wrap {
                min-width: 100%;
            }
            .pagination-wrapper {
                flex-direction: column;
                align-items: center;
                gap: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="main-wrapper">

        <!-- KARTU UTAMA TABEL -->
        <div class="table-page-card">

            <!-- HEADER -->
            <div class="table-page-header">
                <div class="header-title-group">
                    <div class="header-badge">
                        <span class="dot"></span>
                        TABEL DATA TELEMETRI
                    </div>
                    <h1>Tabel Data Sensor</h1>
                    <p>Daftar riwayat rekaman telemetri parameter sensor IoT dengan pagination halaman, filter tanggal, dan kelola data.</p>
                </div>

                <div class="header-actions">
                    <a href="chart.php" class="btn-header-link" title="Beralih ke tampilan grafik">
                        📈 Lihat Grafik
                    </a>
                    <div class="refresh-pill" onclick="toggleAutoRefresh()" title="Klik untuk menjeda/melanjutkan refresh otomatis">
                        <span class="dot" id="refresh-dot"></span>
                        <span id="refresh-status">Live Update (15s)</span>
                    </div>
                </div>
            </div>

            <!-- PANEL FILTER -->
            <div class="filter-panel">

                <!-- ROW 1: FILTER STATUS / KONDISI SENSOR -->
                <div>
                    <div class="filter-section-title">
                        <span>🚨 Status Sensor:</span>
                    </div>
                    <div class="status-btn-group">
                        <button type="button" class="btn-status-filter active" data-status="all" onclick="selectStatus('all', this)">
                            Semua Status
                        </button>
                        <button type="button" class="btn-status-filter" data-status="AMAN" onclick="selectStatus('AMAN', this)">
                            🟢 Aman
                        </button>
                        <button type="button" class="btn-status-filter" data-status="WASPADA" onclick="selectStatus('WASPADA', this)">
                            🟡 Waspada
                        </button>
                        <button type="button" class="btn-status-filter" data-status="BAHAYA" onclick="selectStatus('BAHAYA', this)">
                            🔴 Bahaya
                        </button>
                    </div>
                </div>

                <!-- ROW 2: FILTER TANGGAL (SAMA SEPERTI CHART) -->
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

                <!-- ROW 3: PILIHAN DATA PER HALAMAN (MAX 100) & PENCARIAN -->
                <div class="controls-row">
                    <div class="limit-picker-wrap">
                        <label for="perPageSelect" class="limit-picker-label">
                            📄 Data Per Halaman:
                        </label>
                        <select id="perPageSelect" class="limit-select" onchange="onPerPageChange(this.value)">
                            <option value="10" <?php echo ($initialPerPage == 10) ? 'selected' : ''; ?>>10 Data per hal</option>
                            <option value="25" <?php echo ($initialPerPage == 25) ? 'selected' : ''; ?>>25 Data per hal</option>
                            <option value="50" <?php echo ($initialPerPage == 50) ? 'selected' : ''; ?>>50 Data per hal</option>
                            <option value="100" <?php echo ($initialPerPage == 100) ? 'selected' : ''; ?>>100 Data per hal (Maks)</option>
                        </select>
                        <span class="limit-badge-max">Maksimal 100 Data/Hal</span>
                    </div>

                    <div class="search-input-wrap">
                        <span>🔍</span>
                        <input type="text" id="quickSearchInput" placeholder="Cari ID, suhu, status..." oninput="onQuickSearch(this.value)">
                    </div>
                </div>

            </div>

            <!-- RINGKASAN METRIK DARI DATA TERFILTER -->
            <div class="stats-strip">
                <div class="stat-card">
                    <span class="label">Total Rekaman Terfilter</span>
                    <span class="val" id="stat-count"><?php echo $initCount; ?></span>
                </div>
                <div class="stat-card">
                    <span class="label">Rata-rata Suhu</span>
                    <span class="val" id="stat-avg-suhu"><?php echo $initAvgSuhu; ?></span>
                </div>
                <div class="stat-card">
                    <span class="label">Rata-rata Kelembapan</span>
                    <span class="val" id="stat-avg-kel"><?php echo $initAvgKel; ?></span>
                </div>
                <div class="stat-card">
                    <span class="label">Asap Maksimum</span>
                    <span class="val" id="stat-max-asap"><?php echo $initMaxAsap; ?></span>
                </div>
            </div>

            <!-- ACTION BAR: CHECKBOX SELECTION & BULK DELETE -->
            <div class="table-action-bar">
                <div class="selection-indicator">
                    <span>Pilihan:</span>
                    <span class="selection-badge" id="selectedBadge">0 data dipilih</span>
                </div>

                <div class="table-btn-group">
                    <button type="button" class="btn-bulk-delete" id="btnBulkDelete" onclick="confirmBulkDelete()" disabled>
                        🗑️ Hapus Terpilih (<span id="deleteCountText">0</span>)
                    </button>
                    <button type="button" class="btn-tool" onclick="exportToCSV()" title="Ekspor seluruh data terfilter ke file CSV">
                        📥 Export CSV
                    </button>
                    <button type="button" class="btn-tool" onclick="loadTableData(true)" title="Perbarui data dari server">
                        🔄 Muat Ulang
                    </button>
                </div>
            </div>

            <!-- CONTAINER TABEL SENSOR -->
            <div class="table-responsive">
                <table class="sensor-table" id="sensorTable">
                    <thead>
                        <tr>
                            <th class="col-checkbox">
                                <input type="checkbox" id="selectAllCheckbox" class="custom-chk" onchange="toggleSelectAll(this)" title="Pilih Semua di Halaman Ini">
                            </th>
                            <th class="col-no">No.</th>
                            <th># ID</th>
                            <th>🕒 Waktu & Tanggal</th>
                            <th>🌡️ Suhu (°C)</th>
                            <th>💧 Kelembapan (%)</th>
                            <th>💨 Asap (PPM)</th>
                            <th>🔥 Indikasi Api</th>
                            <th>🚨 Status Sistem</th>
                            <th style="text-align: center;">⚙️ Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if (empty($initialPageRows)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-table-overlay show">
                                        <div class="empty-table-icon">📋</div>
                                        <h3>Belum Ada Data Rekaman</h3>
                                        <p>Data sensor belum tersedia di database atau tidak ada rekaman yang sesuai.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($initialPageRows as $idx => $row): ?>
                                <tr id="row-<?php echo $row['id']; ?>" data-id="<?php echo $row['id']; ?>" data-status="<?php echo $row['status_text']; ?>">
                                    <td class="col-checkbox">
                                        <input type="checkbox" class="custom-chk row-chk" value="<?php echo $row['id']; ?>" onchange="onRowCheckboxChange(this)">
                                    </td>
                                    <td class="col-no">
                                        <?php echo ($idx + 1); ?>
                                    </td>
                                    <td>
                                        <span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#78716c;">#<?php echo $row['id']; ?></span>
                                    </td>
                                    <td>
                                        <div style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#1c1917;"><?php echo $row['formatted_time']; ?></div>
                                        <div style="font-size:11px;color:#a8a29e;"><?php echo $row['formatted_date']; ?></div>
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
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-row-del" onclick="confirmSingleDelete(<?php echo $row['id']; ?>)" title="Hapus rekaman #<?php echo $row['id']; ?>">
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="empty-table-overlay" id="emptyState">
                    <div class="empty-table-icon">🔍</div>
                    <h3>Data Tidak Ditemukan</h3>
                    <p>Tidak ada rekaman sensor yang sesuai dengan rentang tanggal atau kata kunci pencarian Anda.</p>
                </div>
            </div>

            <!-- PAGINATION BAR: < 1 2 > -->
            <div class="pagination-wrapper" id="paginationWrapper">
                <div class="pagination-summary" id="paginationSummary">
                    Menampilkan data <strong>1 - <?php echo count($initialPageRows); ?></strong> dari <strong><?php echo $totalInitial; ?></strong> data
                    <span class="page-count-badge">Halaman 1 dari <?php echo $totalPagesInitial; ?></span>
                </div>

                <div class="pagination-controls">
                    <button type="button" class="btn-page btn-page-nav" id="btnPrevPage" onclick="goToPage(currentPage - 1)" title="Halaman Sebelumnya" <?php echo ($totalPagesInitial <= 1) ? 'disabled' : 'disabled'; ?>>
                        &lt;
                    </button>
                    <div id="pageNumberButtons" style="display:inline-flex;align-items:center;gap:6px;">
                        <!-- Nomor halaman 1 2 dst. dirender otomatis oleh JavaScript -->
                        <button type="button" class="btn-page active">1</button>
                        <?php if ($totalPagesInitial > 1): ?>
                            <button type="button" class="btn-page" onclick="goToPage(2)">2</button>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn-page btn-page-nav" id="btnNextPage" onclick="goToPage(currentPage + 1)" title="Halaman Selanjutnya" <?php echo ($totalPagesInitial <= 1) ? 'disabled' : ''; ?>>
                        &gt;
                    </button>
                </div>
            </div>

            <!-- TABLE FOOTER INFO -->
            <div class="table-info-footer">
                <div>
                    Pilihan limit: <strong id="info-current-limit" style="color:#1c1917;"><?php echo $initialPerPage; ?></strong> data/halaman (Total Rekaman di Database: <strong id="info-total-db" style="color:#1c1917;"><?php echo $totalDbRecords; ?></strong> log).
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="width:7px;height:7px;border-radius:50%;background:#10b981;"></span>
                    <span>Database: MySQL Aktif</span>
                </div>
            </div>

        </div>

    </div>

    <!-- MODAL KONFIRMASI HAPUS -->
    <div class="modal-backdrop" id="deleteModal" onclick="if(event.target === this) closeDeleteModal()">
        <div class="modal-card">
            <div class="modal-icon-danger">
                🗑️
            </div>
            <h3 id="modalTitle">Hapus Data Sensor?</h3>
            <p id="modalDesc">
                Apakah Anda yakin ingin menghapus data sensor yang dipilih? Tindakan ini permanen dan data yang dihapus tidak dapat dipulihkan.
            </p>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeDeleteModal()">
                    Batal
                </button>
                <button type="button" class="btn-modal-delete" id="btnConfirmDelete" onclick="executeDelete()">
                    Ya, Hapus Data
                </button>
            </div>
        </div>
    </div>

    <!-- TOAST NOTIFIKASI -->
    <div class="toast-notify" id="toastBox">
        <span id="toastIcon">⚡</span>
        <span id="toastMsg">Data berhasil diperbarui!</span>
    </div>

    <!-- SCRIPT LOGIKA TABEL, PAGINATION, FILTER, CHECKBOX, & HAPUS -->
    <script>
        let currentPage = 1;
        let perPage = <?php echo $initialPerPage; ?>;
        let selectedStatus = 'all';
        let currentRawData = <?php echo json_encode($allRows); ?>;
        let filteredData = [...currentRawData];
        let selectedIds = new Set();
        let idsToDelete = [];
        let autoRefreshTimer = null;
        let isAutoRefreshActive = true;
        let totalDbCount = <?php echo $totalDbRecords; ?>;

        // Inisialisasi awal saat DOM siap
        document.addEventListener('DOMContentLoaded', () => {
            renderPagination();
            updateSelectionUI();
            startAutoRefresh();
        });

        // ==========================================
        // AUTO REFRESH (15s)
        // ==========================================
        function startAutoRefresh() {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = setInterval(() => {
                if (isAutoRefreshActive) {
                    loadTableData(false);
                }
            }, 15000);
        }

        function toggleAutoRefresh() {
            isAutoRefreshActive = !isAutoRefreshActive;
            const dot = document.getElementById('refresh-dot');
            const status = document.getElementById('refresh-status');

            if (isAutoRefreshActive) {
                dot.style.background = '#10b981';
                dot.style.animation = 'pulseDot 1.8s infinite';
                status.innerText = 'Live Update (15s)';
                showToast('⚡ Live Update diaktifkan');
                loadTableData(false);
            } else {
                dot.style.background = '#94a3b8';
                dot.style.animation = 'none';
                status.innerText = 'Jeda (Paused)';
                showToast('⏸️ Live Update dijeda');
            }
        }

        // ==========================================
        // PRESET TANGGAL CEPAT (SAMA DENGAN CHART)
        // ==========================================
        function setPreset(type) {
            document.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
            if (event && event.target) event.target.classList.add('active');

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

            currentPage = 1;
            loadTableData(true);
        }

        // ==========================================
        // FILTER HANDLERS
        // ==========================================
        function selectStatus(status, btnEl) {
            selectedStatus = status;
            document.querySelectorAll('.btn-status-filter').forEach(b => b.classList.remove('active'));
            if (btnEl) btnEl.classList.add('active');
            currentPage = 1;
            applyClientSideFilters();
        }

        function applyFilters() {
            document.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
            currentPage = 1;
            loadTableData(true);
        }

        function resetFilters() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('quickSearchInput').value = '';
            selectedStatus = 'all';

            document.querySelectorAll('.btn-status-filter').forEach(b => b.classList.remove('active'));
            document.querySelector('.btn-status-filter[data-status="all"]').classList.add('active');

            document.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
            document.querySelector('.btn-preset:last-child').classList.add('active');

            currentPage = 1;
            loadTableData(true);
        }

        // Pilihan Jumlah Data Per Halaman
        function onPerPageChange(val) {
            let num = parseInt(val) || 25;
            if (num > 100) num = 100;
            if (num < 1) num = 10;
            perPage = num;
            currentPage = 1;
            document.getElementById('perPageSelect').value = num;
            document.getElementById('info-current-limit').innerText = num;
            showToast(`📄 Menampilkan ${num} data per halaman`);
            renderPagination();
        }

        function onQuickSearch(query) {
            currentPage = 1;
            applyClientSideFilters();
        }

        // ==========================================
        // AMBIL DATA DARI API
        // ==========================================
        async function loadTableData(showFeedback = false) {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;

            // Muat hingga 1000 data rekaman dari API untuk kebutuhan pagination
            let url = `api_sensor.php?limit=1000`;
            if (start) url += '&start_date=' + encodeURIComponent(start);
            if (end) url += '&end_date=' + encodeURIComponent(end);
            url += '&t=' + Date.now();

            try {
                const res = await fetch(url);
                const data = await res.json();

                if (data.status === 'success') {
                    currentRawData = data.table_data || [];
                    totalDbCount = data.total_records || currentRawData.length;
                    document.getElementById('info-total-db').innerText = totalDbCount;
                    applyClientSideFilters();

                    if (showFeedback) {
                        showToast(`✅ Berhasil memuat ${filteredData.length} data`);
                    }
                } else {
                    currentRawData = [];
                    applyClientSideFilters();
                    if (showFeedback) showToast('⚠️ Data tidak ditemukan');
                }
            } catch (err) {
                console.error('Gagal memuat data:', err);
                if (showFeedback) showToast('❌ Gagal memuat data dari server');
            }
        }

        // Filter data di sisi browser (Status & Quick Search)
        function applyClientSideFilters() {
            const searchQ = (document.getElementById('quickSearchInput').value || '').toLowerCase().trim();

            filteredData = currentRawData.filter(item => {
                // Filter status
                if (selectedStatus !== 'all' && item.status_text !== selectedStatus) {
                    return false;
                }

                // Filter pencarian teks
                if (searchQ) {
                    const matchId = String(item.id).includes(searchQ);
                    const matchSuhu = String(item.suhu).includes(searchQ);
                    const matchKel = String(item.kelembapan).includes(searchQ);
                    const matchAsap = String(item.asap).includes(searchQ);
                    const matchStatus = (item.status_text || '').toLowerCase().includes(searchQ);
                    const matchTime = (item.formatted_time || '').toLowerCase().includes(searchQ);
                    const matchDate = (item.formatted_date || '').toLowerCase().includes(searchQ);

                    if (!matchId && !matchSuhu && !matchKel && !matchAsap && !matchStatus && !matchTime && !matchDate) {
                        return false;
                    }
                }

                return true;
            });

            renderPagination();
            updateStats(filteredData);
        }

        // ==========================================
        // SISTEM PAGINATION (< 1 2 >)
        // ==========================================
        function goToPage(page) {
            const totalPages = Math.ceil(filteredData.length / perPage) || 1;
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            renderPagination();

            // Gulir halus ke tabel jika posisi layar sedang di bawah
            const tableEl = document.getElementById('sensorTable');
            if (tableEl && window.scrollY > tableEl.offsetTop + 100) {
                tableEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function renderPagination() {
            const totalItems = filteredData.length;
            const totalPages = Math.ceil(totalItems / perPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * perPage;
            const endIdx = Math.min(startIdx + perPage, totalItems);
            const startNum = (totalItems === 0) ? 0 : (startIdx + 1);

            // Ringkasan Teks Pagination
            const summaryEl = document.getElementById('paginationSummary');
            if (summaryEl) {
                if (totalItems === 0) {
                    summaryEl.innerHTML = `Tidak ada rekaman untuk ditampilkan`;
                } else {
                    summaryEl.innerHTML = `Menampilkan data <strong>${startNum} - ${endIdx}</strong> dari <strong>${totalItems}</strong> data <span class="page-count-badge">Halaman ${currentPage} dari ${totalPages}</span>`;
                }
            }

            // Tombol Navigasi Sebelumnya & Selanjutnya
            const btnPrev = document.getElementById('btnPrevPage');
            const btnNext = document.getElementById('btnNextPage');
            if (btnPrev) btnPrev.disabled = (currentPage <= 1 || totalItems === 0);
            if (btnNext) btnNext.disabled = (currentPage >= totalPages || totalItems === 0);

            // Render Tombol Nomor Halaman: 1 2 3 ...
            const container = document.getElementById('pageNumberButtons');
            if (container) {
                container.innerHTML = '';
                const pages = generatePageNumbers(currentPage, totalPages);

                pages.forEach(p => {
                    if (p === '...') {
                        const dots = document.createElement('span');
                        dots.className = 'page-dots';
                        dots.innerText = '...';
                        container.appendChild(dots);
                    } else {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = `btn-page ${p === currentPage ? 'active' : ''}`;
                        btn.innerText = p;
                        btn.onclick = () => goToPage(p);
                        container.appendChild(btn);
                    }
                });
            }

            // Potong data untuk halaman yang aktif saat ini
            const pageData = filteredData.slice(startIdx, endIdx);
            renderTablePage(pageData, startIdx);
        }

        // Generator nomor halaman pintar (maksimal 7 tombol tampak)
        function generatePageNumbers(current, total) {
            if (total <= 7) {
                const arr = [];
                for (let i = 1; i <= total; i++) arr.push(i);
                return arr;
            }

            const arr = [];
            if (current <= 4) {
                for (let i = 1; i <= 5; i++) arr.push(i);
                arr.push('...');
                arr.push(total);
            } else if (current >= total - 3) {
                arr.push(1);
                arr.push('...');
                for (let i = total - 4; i <= total; i++) arr.push(i);
            } else {
                arr.push(1);
                arr.push('...');
                arr.push(current - 1);
                arr.push(current);
                arr.push(current + 1);
                arr.push('...');
                arr.push(total);
            }
            return arr;
        }

        // ==========================================
        // RENDER BARIS TABEL HALAMAN AKTIF
        // ==========================================
        function renderTablePage(pageItems, startIdx) {
            const tbody = document.getElementById('tableBody');
            const emptyEl = document.getElementById('emptyState');
            const masterChk = document.getElementById('selectAllCheckbox');

            if (!pageItems || pageItems.length === 0) {
                tbody.innerHTML = '';
                if (emptyEl) emptyEl.classList.add('show');
                masterChk.checked = false;
                masterChk.disabled = true;
                updateSelectionUI();
                return;
            }

            if (emptyEl) emptyEl.classList.remove('show');
            masterChk.disabled = false;

            let html = '';
            let allPageSelected = true;

            pageItems.forEach((row, idx) => {
                const isSelected = selectedIds.has(parseInt(row.id));
                if (!isSelected) allPageSelected = false;

                const trClass = isSelected ? 'row-selected' : '';
                const rowNumber = startIdx + idx + 1; // Nomor urut berurutan: 1-25 di hal 1, 26-50 di hal 2

                html += `
                    <tr id="row-${row.id}" class="${trClass}" data-id="${row.id}" data-status="${row.status_text}">
                        <td class="col-checkbox">
                            <input type="checkbox" class="custom-chk row-chk" value="${row.id}" ${isSelected ? 'checked' : ''} onchange="onRowCheckboxChange(this)">
                        </td>
                        <td class="col-no">
                            ${rowNumber}
                        </td>
                        <td>
                            <span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#78716c;">#${row.id}</span>
                        </td>
                        <td>
                            <div style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#1c1917;">${row.formatted_time}</div>
                            <div style="font-size:11px;color:#a8a29e;">${row.formatted_date}</div>
                        </td>
                        <td>
                            <span class="pill suhu">${row.suhu} &deg;C</span>
                        </td>
                        <td>
                            <span class="pill kel">${row.kelembapan} %</span>
                        </td>
                        <td>
                            <span class="pill asap">${row.asap} PPM</span>
                        </td>
                        <td style="font-weight:600;color:#1c1917;">
                            ${row.api_text}
                        </td>
                        <td>
                            <span class="badge ${row.badge_class}">
                                ● ${row.status_text}
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-row-del" onclick="confirmSingleDelete(${row.id})" title="Hapus rekaman #${row.id}">
                                🗑️
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            masterChk.checked = (pageItems.length > 0 && allPageSelected);

            updateSelectionUI();
        }

        // Update Statistik Keseluruhan Data Terfilter
        function updateStats(dataList) {
            const countEl = document.getElementById('stat-count');
            const avgSuhuEl = document.getElementById('stat-avg-suhu');
            const avgKelEl = document.getElementById('stat-avg-kel');
            const maxAsapEl = document.getElementById('stat-max-asap');

            if (!dataList || dataList.length === 0) {
                countEl.innerText = '0';
                avgSuhuEl.innerText = '--';
                avgKelEl.innerText = '--';
                maxAsapEl.innerText = '--';
                return;
            }

            countEl.innerText = dataList.length;

            let sumS = 0;
            let sumK = 0;
            let maxA = 0;

            dataList.forEach(r => {
                sumS += parseFloat(r.suhu) || 0;
                sumK += parseFloat(r.kelembapan) || 0;
                const a = parseInt(r.asap) || 0;
                if (a > maxA) maxA = a;
            });

            const avgS = (sumS / dataList.length).toFixed(1);
            const avgK = (sumK / dataList.length).toFixed(1);

            avgSuhuEl.innerText = avgS + '°C';
            avgKelEl.innerText = avgK + '%';
            maxAsapEl.innerText = maxA + ' PPM';
        }

        // ==========================================
        // CHECKBOX & SELECT ALL LOGIC
        // ==========================================
        function toggleSelectAll(masterEl) {
            const isChecked = masterEl.checked;
            const checkboxes = document.querySelectorAll('.row-chk');

            checkboxes.forEach(chk => {
                chk.checked = isChecked;
                const id = parseInt(chk.value);
                const tr = document.getElementById(`row-${id}`);

                if (isChecked) {
                    selectedIds.add(id);
                    if (tr) tr.classList.add('row-selected');
                } else {
                    selectedIds.delete(id);
                    if (tr) tr.classList.remove('row-selected');
                }
            });

            updateSelectionUI();
        }

        function onRowCheckboxChange(chkEl) {
            const id = parseInt(chkEl.value);
            const tr = document.getElementById(`row-${id}`);

            if (chkEl.checked) {
                selectedIds.add(id);
                if (tr) tr.classList.add('row-selected');
            } else {
                selectedIds.delete(id);
                if (tr) tr.classList.remove('row-selected');
            }

            // Cek apakah semua baris pada halaman saat ini tercentang
            const checkboxes = document.querySelectorAll('.row-chk');
            let allChecked = checkboxes.length > 0;
            checkboxes.forEach(chk => {
                if (!chk.checked) allChecked = false;
            });

            const masterChk = document.getElementById('selectAllCheckbox');
            if (masterChk) masterChk.checked = allChecked;

            updateSelectionUI();
        }

        function updateSelectionUI() {
            const count = selectedIds.size;
            const badge = document.getElementById('selectedBadge');
            const btnBulk = document.getElementById('btnBulkDelete');
            const countText = document.getElementById('deleteCountText');

            countText.innerText = count;

            if (count > 0) {
                badge.innerText = `${count} data dipilih`;
                badge.classList.add('has-selected');
                btnBulk.disabled = false;
            } else {
                badge.innerText = `0 data dipilih`;
                badge.classList.remove('has-selected');
                btnBulk.disabled = true;
            }
        }

        // ==========================================
        // PROSES HAPUS (DELETE MODAL & EXECUTION)
        // ==========================================
        function confirmBulkDelete() {
            if (selectedIds.size === 0) return;
            idsToDelete = Array.from(selectedIds);

            document.getElementById('modalTitle').innerText = `Hapus ${idsToDelete.length} Data Terpilih?`;
            document.getElementById('modalDesc').innerText = `Apakah Anda yakin ingin menghapus ${idsToDelete.length} rekaman data sensor ini secara permanen? Data yang telah dihapus tidak dapat dipulihkan.`;
            openDeleteModal();
        }

        function confirmSingleDelete(id) {
            idsToDelete = [parseInt(id)];
            document.getElementById('modalTitle').innerText = `Hapus Rekaman #${id}?`;
            document.getElementById('modalDesc').innerText = `Apakah Anda yakin ingin menghapus rekaman sensor #${id}? Tindakan ini permanen.`;
            openDeleteModal();
        }

        function openDeleteModal() {
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            idsToDelete = [];
        }

        async function executeDelete() {
            if (!idsToDelete || idsToDelete.length === 0) {
                closeDeleteModal();
                return;
            }

            const btn = document.getElementById('btnConfirmDelete');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'Menghapus...';

            try {
                const res = await fetch('api_sensor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        ids: idsToDelete
                    })
                });

                const result = await res.json();

                if (result.status === 'success') {
                    showToast(`🗑️ ${result.message || 'Data berhasil dihapus!'}`);

                    // Hapus ID yang sukses dari selectedIds
                    idsToDelete.forEach(id => selectedIds.delete(id));

                    closeDeleteModal();
                    // Muat ulang data terbaru
                    await loadTableData(false);
                } else {
                    showToast(`❌ ${result.message || 'Gagal menghapus data'}`);
                    closeDeleteModal();
                }
            } catch (err) {
                console.error('Error saat menghapus data:', err);
                showToast('❌ Terjadi kesalahan saat menghapus data');
                closeDeleteModal();
            } finally {
                btn.disabled = false;
                btn.innerText = originalText;
            }
        }

        // ==========================================
        // EXPORT TO CSV
        // ==========================================
        function exportToCSV() {
            if (!filteredData || filteredData.length === 0) {
                showToast('⚠️ Tidak ada data untuk diekspor');
                return;
            }

            let csv = 'No,ID,Waktu,Suhu (C),Kelembapan (%),Asap (PPM),Indikasi Api,Status Sistem\n';

            filteredData.forEach((r, idx) => {
                const cleanApi = (r.api_text || '').replace(/"/g, '""');
                csv += `"${idx + 1}","${r.id}","${r.waktu}","${r.suhu}","${r.kelembapan}","${r.asap}","${cleanApi}","${r.status_text}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const nowStr = new Date().toISOString().slice(0, 10);

            link.setAttribute('href', url);
            link.setAttribute('download', `data_sensor_${nowStr}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showToast('📥 Berhasil mengekspor data ke file CSV');
        }

        // ==========================================
        // TOAST NOTIFIKASI
        // ==========================================
        let toastTimeout = null;
        function showToast(msg) {
            const box = document.getElementById('toastBox');
            const msgEl = document.getElementById('toastMsg');
            if (!box || !msgEl) return;

            msgEl.innerText = msg;
            box.classList.add('show');

            clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => {
                box.classList.remove('show');
            }, 3200);
        }
    </script>
</body>
</html>
