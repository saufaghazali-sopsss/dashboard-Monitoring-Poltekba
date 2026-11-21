<?php
// porto.php - Halaman Portofolio Lokasi Perangkat Sensor
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Halaman portofolio lokasi alat dapat diakses oleh publik maupun admin
include "sidebar.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Porto Lokasi Alat - Sistem Monitoring Balikpapan</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #e11d48;
            --primary-soft: #fff1f2;
            --primary-border: #fecdd3;
            --primary-hover: #be123c;
            --accent-orange: #f97316;
            --accent-emerald: #10b981;
            --emerald-soft: #ecfdf5;
            --emerald-border: #a7f3d0;
            --accent-blue: #0284c7;
            --blue-soft: #f0f9ff;
            --blue-border: #bae6fd;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius-md: 14px;
            --radius-sm: 10px;
            --shadow-card: 0 4px 20px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-wrapper {
            max-width: 1240px;
            margin: 0 auto;
            padding: 24px 20px 60px;
        }

        /* ===== HEADER SECTION ===== */
        .page-header {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 26px 28px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, #f43f5e 50%, #fb7185 100%);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title-box {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid var(--primary-border);
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.3px;
            width: fit-content;
        }

        .header-badge .dot {
            width: 7px;
            height: 7px;
            background: var(--primary);
            border-radius: 50%;
            animation: pulseDot 2s infinite;
        }

        @keyframes pulseDot {
            0% { box-shadow: 0 0 0 0 rgba(225, 29, 72, 0.6); }
            70% { box-shadow: 0 0 0 6px rgba(225, 29, 72, 0); }
            100% { box-shadow: 0 0 0 0 rgba(225, 29, 72, 0); }
        }

        .page-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .page-header p {
            margin: 0;
            font-size: 13.5px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .header-stats {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .stat-chip {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-chip .stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .stat-icon.red {
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid var(--primary-border);
        }

        .stat-icon.green {
            background: var(--emerald-soft);
            color: var(--accent-emerald);
            border: 1px solid var(--emerald-border);
        }

        .stat-chip .stat-text {
            display: flex;
            flex-direction: column;
        }

        .stat-chip .stat-text span {
            font-size: 10.5px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .stat-chip .stat-text strong {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text-main);
        }

        /* ===== SEARCH BAR SECTION ===== */
        .search-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-card);
            position: relative;
        }

        .search-input-wrapper {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 4px 14px;
            gap: 12px;
            position: relative;
            transition: var(--transition);
        }

        .search-input-wrapper:focus-within {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.12);
        }

        .search-icon {
            color: var(--text-muted);
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .search-input-wrapper:focus-within .search-icon {
            color: var(--primary);
        }

        .search-input {
            flex: 1;
            border: none;
            background: transparent;
            outline: none;
            font-size: 13.5px;
            font-family: inherit;
            color: var(--text-main);
            padding: 8px 0;
        }

        .search-input::placeholder {
            color: #94a3b8;
        }

        .search-clear-btn {
            border: none;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            flex-shrink: 0;
            padding: 0;
        }

        .search-clear-btn:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }

        .search-badge-info {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 4px 10px;
            border-radius: 999px;
            white-space: nowrap;
            flex-shrink: 0;
            font-family: 'JetBrains Mono', monospace;
        }

        .search-badge-info .count-num {
            color: var(--primary);
            font-weight: 800;
        }

        /* Dropdown Saran Pencarian */
        .search-suggestions {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            box-shadow: 0 14px 30px -4px rgba(15, 23, 42, 0.15);
            z-index: 1000;
            overflow: hidden;
            max-height: 280px;
            overflow-y: auto;
        }

        .suggestion-item {
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 1px solid #f1f5f9;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background: var(--primary-soft);
        }

        .sugg-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sugg-pin {
            font-size: 16px;
            flex-shrink: 0;
        }

        .sugg-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
        }

        .sugg-sub {
            font-size: 11px;
            color: var(--text-muted);
        }

        .sugg-badge {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--primary);
            background: #ffffff;
            border: 1px solid var(--primary-border);
            padding: 2px 8px;
            border-radius: 6px;
        }

        /* Filter Chips / Tags */
        .search-tags-row {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tags-label {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-muted);
            margin-right: 2px;
        }

        .tag-chip {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            font-family: inherit;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .tag-chip:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
            transform: translateY(-1px);
        }

        .tag-chip.active {
            background: var(--primary-soft);
            color: var(--primary);
            border-color: var(--primary-border);
            font-weight: 700;
        }

        /* ===== TOOLBAR / FILTER BUTTONS ===== */
        .map-toolbar {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .toolbar-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            margin-right: 4px;
        }

        .btn-tool {
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
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

        .btn-tool:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .btn-tool.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 2px 6px rgba(225, 29, 72, 0.25);
        }

        .btn-tool .btn-badge {
            font-size: 10px;
            background: rgba(0, 0, 0, 0.08);
            padding: 1px 6px;
            border-radius: 999px;
        }

        .btn-tool.active .btn-badge {
            background: rgba(255, 255, 255, 0.25);
        }

        /* ===== MAP CONTAINER ===== */
        .map-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 10px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-card);
            position: relative;
        }

        #map {
            width: 100%;
            height: 560px;
            border-radius: calc(var(--radius-md) - 4px);
            z-index: 10;
        }

        /* ===== MAP PIN CUSTOM STYLING ===== */
        .custom-pin {
            position: relative;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pin-radar {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(225, 29, 72, 0.35);
            animation: radarPulse 2s cubic-bezier(0, 0.2, 0.8, 1) infinite;
            z-index: 1;
        }

        .pin-radar.green {
            background: rgba(16, 185, 129, 0.35);
        }

        @keyframes radarPulse {
            0% {
                transform: scale(0.6);
                opacity: 1;
            }
            100% {
                transform: scale(2.2);
                opacity: 0;
            }
        }

        .pin-body {
            position: relative;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(225, 29, 72, 0.45);
            border: 2.5px solid #ffffff;
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

        /* ===== LEAFLET POPUP STYLING (HANYA INFORMASI LOKASI, TANPA BOCOR DATA SENSOR) ===== */
        .leaflet-popup-content-wrapper {
            border-radius: 14px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 14px 35px -6px rgba(15, 23, 42, 0.25);
            border: 1px solid #e2e8f0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .leaflet-popup-content {
            margin: 0 !important;
            line-height: 1.5;
            min-width: 270px;
            max-width: 320px;
        }

        .popup-card-header {
            padding: 13px 16px;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .popup-card-header h4 {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .popup-badge {
            font-size: 9.5px;
            font-weight: 700;
            padding: 2.5px 8px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .popup-badge.online {
            background: #10b981;
            color: #ffffff;
        }

        .popup-card-body {
            padding: 14px 16px;
            background: #ffffff;
        }

        .popup-location-name {
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 3px 0;
            line-height: 1.35;
        }

        .popup-sub-info {
            font-size: 11px;
            font-weight: 600;
            color: var(--primary);
            margin: 0 0 8px 0;
        }

        .popup-address {
            font-size: 11.5px;
            color: #64748b;
            margin: 0 0 10px 0;
            line-height: 1.4;
        }

        .popup-specs-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 10px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .popup-spec-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            line-height: 1.4;
        }

        .popup-spec-row .ps-label {
            color: #64748b;
            font-weight: 600;
        }

        .popup-spec-row .ps-val {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        .popup-coord {
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
            background: #f1f5f9;
            padding: 5px 8px;
            border-radius: 6px;
            color: #475569;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .popup-btn {
            display: block;
            width: 100%;
            text-align: center;
            padding: 8px 12px;
            background: var(--primary);
            color: #ffffff;
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: none;
            border-radius: 7px;
            transition: var(--transition);
            box-sizing: border-box;
        }

        .popup-btn:hover {
            background: var(--primary-hover);
        }

        /* ===== DEVICE CARDS GRID ===== */
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        .device-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 22px;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: var(--transition);
        }

        .device-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08);
        }

        .device-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 12px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .device-top-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .device-id-badge {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 4px 10px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
        }

        .device-cat-badge {
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .device-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #047857;
            background: var(--emerald-soft);
            border: 1px solid var(--emerald-border);
            padding: 3px 10px;
            border-radius: 999px;
        }

        .device-status .dot-live {
            width: 7px;
            height: 7px;
            background: #10b981;
            border-radius: 50%;
            animation: pulseDot 1.8s infinite;
        }

        .device-card h3 {
            margin: 0 0 4px 0;
            font-size: 17px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.35;
        }

        .device-sublabel {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            margin: 0 0 8px 0;
        }

        .device-address {
            font-size: 12.5px;
            color: var(--text-muted);
            margin: 0 0 14px 0;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }

        /* Grid Spesifikasi Penempatan Lokasi (Hanya Data Lokasi, Tanpa Data Telemetri) */
        .device-specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-bottom: 16px;
        }

        .spec-item {
            display: flex;
            flex-direction: column;
        }

        .spec-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }

        .spec-val {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
        }

        .device-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 10px;
        }

        .coord-info {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
        }

        .coord-tag {
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
            color: #64748b;
        }

        .card-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-focus {
            padding: 7px 14px;
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
        }

        .btn-focus:hover {
            background: var(--primary-soft);
            color: var(--primary);
            border-color: var(--primary-border);
        }

        .btn-gmaps {
            padding: 7px 12px;
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            font-family: inherit;
        }

        .btn-gmaps:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: var(--primary);
        }

        /* ===== EMPTY STATE RESULT ===== */
        .empty-state-box {
            background: #ffffff;
            border: 1.5px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 44px 20px;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow-card);
        }

        .empty-icon {
            font-size: 38px;
            margin-bottom: 10px;
        }

        .empty-state-box h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 800;
            color: var(--text-main);
        }

        .empty-state-box p {
            margin: 0 auto 16px;
            font-size: 13px;
            color: var(--text-muted);
            max-width: 440px;
            line-height: 1.5;
        }

        .empty-state-box p span {
            font-weight: 700;
            color: var(--primary);
        }

        .btn-reset-search {
            padding: 8px 18px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }

        .btn-reset-search:hover {
            background: var(--primary-hover);
        }

        /* ===== FOOTER ===== */
        .page-footer {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
            color: #94a3b8;
            font-size: 12px;
        }

        @media (max-width: 900px) {
            .devices-grid {
                grid-template-columns: 1fr;
            }
            #map {
                height: 440px;
            }
        }

        @media (max-width: 640px) {
            .main-wrapper {
                padding: 16px 14px 40px;
            }
            .page-header {
                padding: 20px 18px;
            }
            .page-header h1 {
                font-size: 20px;
            }
            .device-specs-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            #map {
                height: 380px;
            }
            .search-input-wrapper {
                flex-wrap: wrap;
            }
            .search-badge-info {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="main-wrapper">

        <!-- HEADER SECTION -->
        <header class="page-header">
            <div class="header-content">
                <div class="header-title-box">
                    <div class="header-badge">
                        <span class="dot"></span>
                        PORTOFOLIO LOKASI ALAT IOT
                    </div>
                    <h1>Peta Persebaran Titik Alat Sensor</h1>
                    <p>Direktori titik koordinat dan penempatan fisik unit telemetri di wilayah Kota Balikpapan.</p>
                </div>

                <div class="header-stats">
                    <div class="stat-chip">
                        <div class="stat-icon red">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </div>
                        <div class="stat-text">
                            <span>Titik Terpasang</span>
                            <strong>2 Lokasi Balikpapan</strong>
                        </div>
                    </div>

                    <div class="stat-chip">
                        <div class="stat-icon green">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                        </div>
                        <div class="stat-text">
                            <span>Status Penempatan</span>
                            <strong>Unit Terverifikasi</strong>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- SEARCH BAR CARI LOKASI ALAT -->
        <section class="search-card">
            <div class="search-input-wrapper">
                <div class="search-icon">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
                <input 
                    type="text" 
                    id="deviceSearchInput" 
                    class="search-input" 
                    placeholder="Cari lokasi alat (cth: Poltekba, Lapangan Merdeka, Balikpapan Utara, NODE-01)..." 
                    autocomplete="off"
                    spellcheck="false"
                    oninput="handleSearch(this.value)"
                >
                <button type="button" id="searchClearBtn" class="search-clear-btn" title="Hapus pencarian" style="display:none;" onclick="clearSearch()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="search-badge-info" id="searchCountBadge">
                    <span class="count-num">2</span> dari 2 Lokasi
                </div>

                <!-- Dropdown Saran Hasil Pencarian -->
                <div class="search-suggestions" id="searchSuggestions" style="display:none;"></div>
            </div>

            <!-- Filter Cepat / Shortcut Kategori -->
            <div class="search-tags-row">
                <span class="tags-label">Filter Cepat:</span>
                <button type="button" class="tag-chip active" data-filter="all" onclick="applyTagFilter('all', this)">
                    Semua Titik (2)
                </button>
                <button type="button" class="tag-chip" data-filter="NODE-01" onclick="applyTagFilter('NODE-01', this)">
                    📍 Poltekba (Balikpapan Utara)
                </button>
                <button type="button" class="tag-chip" data-filter="NODE-02" onclick="applyTagFilter('NODE-02', this)">
                    📍 Lapangan Merdeka (Balikpapan Kota)
                </button>
            </div>
        </section>

        <!-- TOOLBAR KONTROL PETA -->
        <div class="map-toolbar">
            <div class="toolbar-group">
                <span class="toolbar-label">Fokus Cepat:</span>
                <button type="button" class="btn-tool active" id="btnFitAll" onclick="fitAllLocations()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    Semua Alat <span class="btn-badge">2</span>
                </button>
                <button type="button" class="btn-tool" id="btnFocusNode1" onclick="focusNode(0)">
                    📍 Node 01: Poltekba
                </button>
                <button type="button" class="btn-tool" id="btnFocusNode2" onclick="focusNode(1)">
                    📍 Node 02: Lap. Merdeka
                </button>
            </div>

            <div class="toolbar-group">
                <span class="toolbar-label">Tipe Peta:</span>
                <button type="button" class="btn-tool active" id="btnStreetView" onclick="switchBaseMap('street')">
                    🗺️ Peta Jalan
                </button>
                <button type="button" class="btn-tool" id="btnSatelliteView" onclick="switchBaseMap('satellite')">
                    🛰️ Citra Satelit
                </button>
            </div>
        </div>

        <!-- CONTAINER PETA LEAFLET -->
        <section class="map-card">
            <div id="map"></div>
        </section>

        <!-- EMPTY STATE (JIKA PENCARIAN TIDAK COCOK) -->
        <div id="noResultsState" class="empty-state-box" style="display:none;">
            <div class="empty-icon">🔍</div>
            <h3>Lokasi Alat Tidak Ditemukan</h3>
            <p>Tidak ada titik lokasi alat yang cocok dengan kata kunci "<span id="emptyQueryText"></span>". Silakan gunakan kata kunci lain seperti nama gedung, jalan, atau kecamatan.</p>
            <button type="button" class="btn-reset-search" onclick="clearSearch()">
                Reset Pencarian
            </button>
        </div>

        <!-- KARTU DAFTAR PERANGKAT LOKASI (HANYA INFORMASI PENEMPATAN, TANPA BOCORAN DATA SENSOR) -->
        <section class="devices-grid" id="devicesGrid">

            <!-- KARTU NODE 01: POLITEKNIK NEGERI BALIKPAPAN -->
            <div class="device-card" id="card-node-0" data-id="NODE-01" data-name="Politeknik Negeri Balikpapan POLTEKBA" data-area="Balikpapan Utara Batu Ampar Km. 8" data-sub="Gedung Workshop Lab IoT">
                <div>
                    <div class="device-top">
                        <div class="device-top-meta">
                            <span class="device-id-badge">NODE #01 &middot; POLTEKBA</span>
                            <span class="device-cat-badge">Pendidikan & Riset</span>
                        </div>
                        <span class="device-status">
                            <span class="dot-live"></span>
                            Terpasang &middot; Siaga
                        </span>
                    </div>

                    <h3>Politeknik Negeri Balikpapan (POLTEKBA)</h3>
                    <p class="device-sublabel">Unit Pemantau Lingkungan Lab Terpadu</p>
                    <p class="device-address">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
                            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        Jl. Soekarno-Hatta Km. 8, Batu Ampar, Balikpapan Utara, Kota Balikpapan, Kalimantan Timur
                    </p>

                    <!-- Spesifikasi Penempatan & Fisik Alat (Tanpa Data Telemetri) -->
                    <div class="device-specs-grid">
                        <div class="spec-item">
                            <span class="spec-label">Wilayah / Kecamatan</span>
                            <span class="spec-val">Balikpapan Utara</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Titik Penempatan</span>
                            <span class="spec-val">Gedung Workshop & Lab IoT</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Kategori Lokasi</span>
                            <span class="spec-val">Kampus / Fasilitas Edukasi</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Jenis Pemasangan</span>
                            <span class="spec-val">Permanen (Indoor Wall-Mount)</span>
                        </div>
                    </div>
                </div>

                <div class="device-footer">
                    <div class="coord-info">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        <span class="coord-tag">Lat: -1.20200 &middot; Lng: 116.88564</span>
                    </div>
                    <div class="card-actions">
                        <button type="button" class="btn-focus" onclick="focusNode(0)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            Fokus di Peta
                        </button>
                        <a href="https://www.google.com/maps?q=-1.20200,116.88564" target="_blank" class="btn-gmaps" title="Buka rute navigasi Google Maps">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                            Google Maps
                        </a>
                    </div>
                </div>
            </div>

            <!-- KARTU NODE 02: LAPANGAN MERDEKA BALIKPAPAN -->
            <div class="device-card" id="card-node-1" data-id="NODE-02" data-name="Lapangan Merdeka Balikpapan" data-area="Balikpapan Kota Prapatan Sudirman" data-sub="Pos Pantau RTH Smart City">
                <div>
                    <div class="device-top">
                        <div class="device-top-meta">
                            <span class="device-id-badge">NODE #02 &middot; PUSAT KOTA</span>
                            <span class="device-cat-badge">Ruang Publik & RTH</span>
                        </div>
                        <span class="device-status">
                            <span class="dot-live"></span>
                            Terpasang &middot; Siaga
                        </span>
                    </div>

                    <h3>Lapangan Merdeka Balikpapan</h3>
                    <p class="device-sublabel">Unit Pemantau Ruang Terbuka Hijau & Publik</p>
                    <p class="device-address">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;">
                            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        Kawasan Lapangan Merdeka, Jl. Jend. Sudirman, Prapatan, Balikpapan Kota, Kalimantan Timur
                    </p>

                    <!-- Spesifikasi Penempatan & Fisik Alat (Tanpa Data Telemetri) -->
                    <div class="device-specs-grid">
                        <div class="spec-item">
                            <span class="spec-label">Wilayah / Kecamatan</span>
                            <span class="spec-val">Balikpapan Kota</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Titik Penempatan</span>
                            <span class="spec-val">Pos Pantau Kawasan Merdeka</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Kategori Lokasi</span>
                            <span class="spec-val">Taman Publik / Fasilitas Kota</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Jenis Pemasangan</span>
                            <span class="spec-val">Permanen (Outdoor Weatherproof)</span>
                        </div>
                    </div>
                </div>

                <div class="device-footer">
                    <div class="coord-info">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        <span class="coord-tag">Lat: -1.27960 &middot; Lng: 116.81461</span>
                    </div>
                    <div class="card-actions">
                        <button type="button" class="btn-focus" onclick="focusNode(1)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            Fokus di Peta
                        </button>
                        <a href="https://www.google.com/maps?q=-1.27960,116.81461" target="_blank" class="btn-gmaps" title="Buka rute navigasi Google Maps">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                            Google Maps
                        </a>
                    </div>
                </div>
            </div>

        </section>

        <!-- FOOTER -->
        <footer class="page-footer">
            <p>&copy; <?php echo date('Y'); ?> Sistem Monitoring Telemetri IoT &middot; Wilayah Balikpapan</p>
        </footer>

    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
    // ===== DATA LOKASI ALAT SENSOR (HANYA INFORMASI LOKASI & PENEMPATAN FISIK) =====
    const deviceLocations = [
        {
            id: 'NODE-01',
            cardId: 'card-node-0',
            name: 'Politeknik Negeri Balikpapan (POLTEKBA)',
            shortName: 'Poltekba',
            sub: 'Gedung Workshop & Lab IoT Terpadu',
            address: 'Jl. Soekarno-Hatta Km. 8, Batu Ampar, Balikpapan Utara, Kota Balikpapan',
            area: 'Balikpapan Utara',
            category: 'Kawasan Pendidikan & Riset',
            installation: 'Indoor Wall-Mount Permanen',
            lat: -1.20200,
            lng: 116.88564,
            status: 'TERPASANG',
            badgeClass: 'online',
            color: 'red',
            iconSvg: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path></svg>`
        },
        {
            id: 'NODE-02',
            cardId: 'card-node-1',
            name: 'Lapangan Merdeka Balikpapan',
            shortName: 'Lap. Merdeka',
            sub: 'Pos Pantau RTH & Fasilitas Publik',
            address: 'Jl. Jenderal Sudirman, Prapatan, Kec. Balikpapan Kota, Kota Balikpapan',
            area: 'Balikpapan Kota',
            category: 'Ruang Terbuka Publik (RTH)',
            installation: 'Outdoor Weatherproof Permanen',
            lat: -1.27960,
            lng: 116.81461,
            status: 'TERPASANG',
            badgeClass: 'online',
            color: 'green',
            iconSvg: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>`
        }
    ];

    // ===== INISIALISASI PETA LEAFLET =====
    const mapCenter = [-1.23965, 116.85614];
    const map = L.map('map', {
        center: mapCenter,
        zoom: 12,
        zoomControl: true,
        scrollWheelZoom: true
    });

    // ===== LAYER BASEMAP (JALAN & CITRA SATELIT) =====
    const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> kontributor'
    }).addTo(map);

    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: '&copy; Esri, Maxar, Earthstar Geographics'
    });

    function switchBaseMap(type) {
        if (type === 'satellite') {
            map.removeLayer(streetLayer);
            map.addLayer(satelliteLayer);
            document.getElementById('btnSatelliteView').classList.add('active');
            document.getElementById('btnStreetView').classList.remove('active');
        } else {
            map.removeLayer(satelliteLayer);
            map.addLayer(streetLayer);
            document.getElementById('btnStreetView').classList.add('active');
            document.getElementById('btnSatelliteView').classList.remove('active');
        }
    }

    // ===== MEMBUAT MARKER KUSTOM & POPUP BERSIH =====
    const markers = [];
    const bounds = L.latLngBounds();

    deviceLocations.forEach((device, index) => {
        const customIcon = L.divIcon({
            className: 'custom-pin-container',
            html: `
                <div class="custom-pin" id="pin-marker-${index}">
                    <div class="pin-radar ${device.color === 'green' ? 'green' : ''}"></div>
                    <div class="pin-body ${device.color === 'green' ? 'green' : ''}">
                        ${device.iconSvg}
                    </div>
                </div>
            `,
            iconSize: [38, 38],
            iconAnchor: [19, 19],
            popupAnchor: [0, -19]
        });

        const marker = L.marker([device.lat, device.lng], { icon: customIcon }).addTo(map);

        // Konten popup interaktif (Murni Informasi Lokasi, Tanpa Bocoran Data Sensor Telemetri)
        const popupContent = `
            <div>
                <div class="popup-card-header">
                    <h4>
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10b981;"></span>
                        ${device.id} &middot; ${device.shortName}
                    </h4>
                    <span class="popup-badge ${device.badgeClass}">${device.status}</span>
                </div>
                <div class="popup-card-body">
                    <h5 class="popup-location-name">${device.name}</h5>
                    <p class="popup-sub-info">${device.sub}</p>
                    <p class="popup-address">${device.address}</p>

                    <div class="popup-specs-box">
                        <div class="popup-spec-row">
                            <span class="ps-label">Wilayah:</span>
                            <span class="ps-val">${device.area}</span>
                        </div>
                        <div class="popup-spec-row">
                            <span class="ps-label">Kategori:</span>
                            <span class="ps-val">${device.category}</span>
                        </div>
                        <div class="popup-spec-row">
                            <span class="ps-label">Pemasangan:</span>
                            <span class="ps-val">${device.installation}</span>
                        </div>
                    </div>

                    <div class="popup-coord">
                        <span>Koordinat Titik:</span>
                        <strong>${device.lat.toFixed(5)}, ${device.lng.toFixed(5)}</strong>
                    </div>

                    <a href="https://www.google.com/maps?q=${device.lat},${device.lng}" target="_blank" class="popup-btn">
                        Buka di Google Maps &rarr;
                    </a>
                </div>
            </div>
        `;

        marker.bindPopup(popupContent, { maxWidth: 320 });
        markers.push(marker);
        bounds.extend([device.lat, device.lng]);
    });

    // Posisikan peta agar kedua titik terlihat nyaman
    map.fitBounds(bounds, { padding: [60, 60] });

    // ===== FUNGSI PENCARIAN REAL-TIME LOKASI ALAT =====
    function handleSearch(query) {
        const q = (query || '').toLowerCase().trim();
        const clearBtn = document.getElementById('searchClearBtn');
        const suggestionsBox = document.getElementById('searchSuggestions');
        const badgeCount = document.getElementById('searchCountBadge');
        const noResultsState = document.getElementById('noResultsState');
        const emptyQueryText = document.getElementById('emptyQueryText');
        const gridEl = document.getElementById('devicesGrid');

        // Tampilkan/sembunyikan tombol clear
        if (clearBtn) {
            clearBtn.style.display = q.length > 0 ? 'flex' : 'none';
        }

        let matchedCount = 0;
        const matchedIndices = [];
        let suggestionHtml = '';

        deviceLocations.forEach((dev, index) => {
            const cardEl = document.getElementById(dev.cardId);
            const searchTarget = `${dev.id} ${dev.name} ${dev.shortName} ${dev.sub} ${dev.address} ${dev.area} ${dev.category}`.toLowerCase();
            const isMatch = (q === '') || searchTarget.includes(q);

            if (isMatch) {
                matchedCount++;
                matchedIndices.push(index);
                if (cardEl) {
                    cardEl.style.display = 'flex';
                }

                // Tambahkan ke dropdown saran jika sedang mengetik
                if (q.length > 0) {
                    suggestionHtml += `
                        <div class="suggestion-item" onclick="selectSuggestion(${index})">
                            <div class="sugg-left">
                                <span class="sugg-pin">📍</span>
                                <div>
                                    <div class="sugg-title"><strong>${dev.id}</strong> &middot; ${dev.name}</div>
                                    <div class="sugg-sub">${dev.area} &bull; ${dev.sub}</div>
                                </div>
                            </div>
                            <span class="sugg-badge">Fokus</span>
                        </div>
                    `;
                }
            } else {
                if (cardEl) {
                    cardEl.style.display = 'none';
                }
            }
        });

        // Update badge jumlah hasil
        if (badgeCount) {
            badgeCount.innerHTML = `<span class="count-num">${matchedCount}</span> dari ${deviceLocations.length} Lokasi`;
        }

        // Tampilkan/sembunyikan dropdown saran
        if (suggestionsBox) {
            if (q.length > 0 && suggestionHtml !== '') {
                suggestionsBox.innerHTML = suggestionHtml;
                suggestionsBox.style.display = 'block';
            } else {
                suggestionsBox.style.display = 'none';
            }
        }

        // Tampilkan/sembunyikan empty state
        if (matchedCount === 0) {
            if (gridEl) gridEl.style.display = 'none';
            if (noResultsState) {
                noResultsState.style.display = 'block';
                if (emptyQueryText) emptyQueryText.textContent = query;
            }
        } else {
            if (gridEl) gridEl.style.display = 'grid';
            if (noResultsState) noResultsState.style.display = 'none';
        }

        // Respon peta saat mencari
        if (matchedCount === 1) {
            // Tepat 1 hasil ditemukan: fokuskan peta dan buka popup marker
            const targetIndex = matchedIndices[0];
            const target = deviceLocations[targetIndex];
            map.flyTo([target.lat, target.lng], 17, { animate: true, duration: 1.0 });
            markers[targetIndex].openPopup();
            setActiveToolBtn(targetIndex === 0 ? 'btnFocusNode1' : 'btnFocusNode2');
        } else if (matchedCount > 1) {
            const matchedBounds = L.latLngBounds();
            matchedIndices.forEach(idx => {
                matchedBounds.extend([deviceLocations[idx].lat, deviceLocations[idx].lng]);
            });
            map.fitBounds(matchedBounds, { padding: [60, 60], maxZoom: 13 });
            setActiveToolBtn('btnFitAll');
        }
    }

    // Memilih saran dari dropdown
    function selectSuggestion(index) {
        const dev = deviceLocations[index];
        if (!dev) return;
        const input = document.getElementById('deviceSearchInput');
        if (input) input.value = dev.shortName;

        const suggestionsBox = document.getElementById('searchSuggestions');
        if (suggestionsBox) suggestionsBox.style.display = 'none';

        handleSearch(dev.shortName);
        focusNode(index);
    }

    // Bersihkan pencarian
    function clearSearch() {
        const input = document.getElementById('deviceSearchInput');
        if (input) {
            input.value = '';
            input.focus();
        }

        const clearBtn = document.getElementById('searchClearBtn');
        if (clearBtn) clearBtn.style.display = 'none';

        const suggestionsBox = document.getElementById('searchSuggestions');
        if (suggestionsBox) suggestionsBox.style.display = 'none';

        // Reset filter chips
        document.querySelectorAll('.tag-chip').forEach(btn => {
            if (btn.getAttribute('data-filter') === 'all') btn.classList.add('active');
            else btn.classList.remove('active');
        });

        handleSearch('');
        fitAllLocations();
    }

    // Filter Cepat Kategori / Tag
    function applyTagFilter(filter, el) {
        document.querySelectorAll('.tag-chip').forEach(b => b.classList.remove('active'));
        if (el) el.classList.add('active');

        const input = document.getElementById('deviceSearchInput');
        if (filter === 'all') {
            if (input) input.value = '';
            handleSearch('');
            fitAllLocations();
        } else if (filter === 'NODE-01') {
            if (input) input.value = 'Poltekba';
            handleSearch('Poltekba');
            focusNode(0);
        } else if (filter === 'NODE-02') {
            if (input) input.value = 'Lapangan Merdeka';
            handleSearch('Lapangan Merdeka');
            focusNode(1);
        }
    }

    // Tutup dropdown saran saat klik di luar kotak pencarian
    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.search-input-wrapper');
        const suggestionsBox = document.getElementById('searchSuggestions');
        if (suggestionsBox && wrapper && !wrapper.contains(e.target)) {
            suggestionsBox.style.display = 'none';
        }
    });

    // ===== FUNGSI KONTROL NAVIGASI PETA =====
    function fitAllLocations() {
        setActiveToolBtn('btnFitAll');
        map.closePopup();
        map.fitBounds(bounds, { padding: [60, 60], maxZoom: 13 });
    }

    function focusNode(index) {
        if (!deviceLocations[index]) return;
        const target = deviceLocations[index];

        setActiveToolBtn(index === 0 ? 'btnFocusNode1' : 'btnFocusNode2');

        // Scroll halus ke arah peta jika di layar kecil
        const mapEl = document.getElementById('map');
        if (window.innerWidth < 768) {
            mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Terbang ke lokasi marker
        map.flyTo([target.lat, target.lng], 17, {
            animate: true,
            duration: 1.2
        });

        setTimeout(() => {
            markers[index].openPopup();
        }, 1300);
    }

    function setActiveToolBtn(id) {
        ['btnFitAll', 'btnFocusNode1', 'btnFocusNode2'].forEach(bId => {
            const btn = document.getElementById(bId);
            if (btn) btn.classList.remove('active');
        });
        const activeBtn = document.getElementById(id);
        if (activeBtn) activeBtn.classList.add('active');
    }
    </script>
</body>
</html>
