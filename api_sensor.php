<?php
header('Content-Type: application/json');
include 'koneksi.php';

if (!$koneksi) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal terhubung ke database: ' . mysqli_connect_error()
    ]);
    exit;

}

// Fungsi untuk memastikan AUTO_INCREMENT selalu sinkron
// Jika tabel kosong (misal data baru dihapus di DB), kembalikan ID ke 1
function ensureAutoIncrementSync($koneksi) {
    $countQuery = mysqli_query($koneksi, "SELECT COUNT(*) as total, MAX(id) as max_id FROM sensor_log");
    if ($countQuery && $cRow = mysqli_fetch_assoc($countQuery)) {
        $total = intval($cRow['total']);
        $maxId = intval($cRow['max_id']);
        if ($total === 0) {
            mysqli_query($koneksi, "ALTER TABLE sensor_log AUTO_INCREMENT = 1");
        } else {
            $next = $maxId + 1;
            mysqli_query($koneksi, "ALTER TABLE sensor_log AUTO_INCREMENT = $next");
        }
    }
}

$rawInput = file_get_contents('php://input');
$jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : null;
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : ($jsonBody['action'] ?? 'get_data');

// ACTION: Hapus Satu atau Beberapa Data Sensor Berdasarkan ID
if ($action === 'delete') {
    $ids = [];
    if (isset($jsonBody['ids'])) {
        $ids = is_array($jsonBody['ids']) ? $jsonBody['ids'] : explode(',', (string)$jsonBody['ids']);
    } elseif (isset($_REQUEST['ids'])) {
        $ids = is_array($_REQUEST['ids']) ? $_REQUEST['ids'] : explode(',', (string)$_REQUEST['ids']);
    } elseif (isset($jsonBody['id'])) {
        $ids = [$jsonBody['id']];
    } elseif (isset($_REQUEST['id'])) {
        $ids = [$_REQUEST['id']];
    }

    $ids = array_values(array_filter(array_map('intval', $ids), function($v) {
        return $v > 0;
    }));

    if (empty($ids)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Tidak ada ID data valid yang dipilih untuk dihapus.'
        ]);
        exit;
    }

    $idListStr = implode(',', $ids);
    $delQuery = "DELETE FROM sensor_log WHERE id IN ($idListStr)";
    $delRes = mysqli_query($koneksi, $delQuery);

    if ($delRes) {
        $deletedCount = mysqli_affected_rows($koneksi);
        ensureAutoIncrementSync($koneksi);
        echo json_encode([
            'status' => 'success',
            'message' => $deletedCount . ' data sensor berhasil dihapus.',
            'deleted_count' => $deletedCount
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menghapus data sensor: ' . mysqli_error($koneksi)
        ]);
    }
    exit;
}

// ACTION: Reset / Kosongkan Log Data Sensor dan kembalikan ID ke 1
if ($action === 'reset' || $action === 'clear' || $action === 'truncate') {
    $res = mysqli_query($koneksi, "TRUNCATE TABLE sensor_log");
    if (!$res) {
        mysqli_query($koneksi, "DELETE FROM sensor_log");
    }
    mysqli_query($koneksi, "ALTER TABLE sensor_log AUTO_INCREMENT = 1");

    echo json_encode([
        'status' => 'success',
        'message' => 'Database log berhasil dikosongkan. ID data baru akan kembali dimulai dari 1.'
    ]);
    exit;
}

// ACTION: Nomor Ulang ID Data Yang Sudah Ada (1, 2, 3...)
if ($action === 'renumber') {
    mysqli_query($koneksi, "SET @count = 0");
    mysqli_query($koneksi, "UPDATE sensor_log SET id = (@count:=@count + 1) ORDER BY waktu ASC, id ASC");
    $maxRes = mysqli_query($koneksi, "SELECT MAX(id) as max_id FROM sensor_log");
    $maxRow = mysqli_fetch_assoc($maxRes);
    $next = isset($maxRow['max_id']) ? (intval($maxRow['max_id']) + 1) : 1;
    mysqli_query($koneksi, "ALTER TABLE sensor_log AUTO_INCREMENT = $next");

    echo json_encode([
        'status' => 'success',
        'message' => 'Seluruh ID data log berhasil dinomori ulang berurutan mulai dari 1.'
    ]);
    exit;
}

// ACTION: Menerima data sensor asli dari ESP32 / Arduino / HTTP Request
if ($action === 'send' || $action === 'insert' || (isset($_REQUEST['suhu']) && !isset($_REQUEST['action']))) {
    $suhu = isset($_REQUEST['suhu']) ? floatval($_REQUEST['suhu']) : null;
    $kelembapan = isset($_REQUEST['kelembapan']) ? floatval($_REQUEST['kelembapan']) : 0.0;
    $asap = isset($_REQUEST['asap']) ? intval($_REQUEST['asap']) : 0;

    if ($suhu !== null) {
        // Cek jika tabel kosong, pastikan auto_increment kembali ke 1
        ensureAutoIncrementSync($koneksi);

        $stmt = mysqli_prepare($koneksi, "INSERT INTO sensor_log (waktu, suhu, kelembapan, asap) VALUES (NOW(), ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ddi", $suhu, $kelembapan, $asap);
        $exec = mysqli_stmt_execute($stmt);

        if ($exec) {
            $insertId = mysqli_insert_id($koneksi);
            echo json_encode([
                'status' => 'success',
                'message' => 'Data sensor berhasil disimpan!',
                'data' => [
                    'id' => $insertId,
                    'suhu' => $suhu,
                    'kelembapan' => $kelembapan,
                    'asap' => $asap
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal menyimpan data sensor: ' . mysqli_error($koneksi)
            ]);
        }
        exit;
    }
}

// ACTION: Simulasi pengiriman data sensor baru
if ($action === 'simulate') {
    // Pastikan jika tabel baru dihapus di DB, ID kembali ke 1
    ensureAutoIncrementSync($koneksi);

    // Generate telemetri sensor realistis
    $isAlert = (rand(1, 10) === 10); // 10% peluang kondisi bahaya
    if ($isAlert) {
        $suhu = round(rand(450, 750) / 10, 1);
        $kelembapan = round(rand(300, 500) / 10, 1);
        $asap = rand(450, 850);
    } else {
        $suhu = round(rand(240, 360) / 10, 1);
        $kelembapan = round(rand(450, 750) / 10, 1);
        $asap = rand(80, 290);
    }

    $stmt = mysqli_prepare($koneksi, "INSERT INTO sensor_log (waktu, suhu, kelembapan, asap) VALUES (NOW(), ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ddi", $suhu, $kelembapan, $asap);
    $exec = mysqli_stmt_execute($stmt);

    if ($exec) {
        $insertId = mysqli_insert_id($koneksi);
        echo json_encode([
            'status' => 'success',
            'message' => 'Simulasi data sensor berhasil dikirim!',
            'data' => [
                'id' => $insertId,
                'suhu' => $suhu,
                'kelembapan' => $kelembapan,
                'asap' => $asap
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menyimpan data simulasi: ' . mysqli_error($koneksi)
        ]);
    }
    exit;
}

// ACTION DEFAULT: Mengambil data log untuk grafik & tabel
$startDate = isset($_GET['start_date']) && trim($_GET['start_date']) !== '' ? trim($_GET['start_date']) : null;
$endDate   = isset($_GET['end_date']) && trim($_GET['end_date']) !== '' ? trim($_GET['end_date']) : null;

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 25;
if ($limit < 1) $limit = 10;
if ($limit > 1000) $limit = 1000;

// Sinkronisasi status auto-increment jika tabel saat ini kosong
ensureAutoIncrementSync($koneksi);

$whereClauses = [];
$params = [];
$types = "";

if ($startDate) {
    $whereClauses[] = "DATE(waktu) >= ?";
    $params[] = $startDate;
    $types .= "s";
}
if ($endDate) {
    $whereClauses[] = "DATE(waktu) <= ?";
    $params[] = $endDate;
    $types .= "s";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Total records
$countSql = "SELECT COUNT(*) as total FROM sensor_log $whereSql";
$totalCount = 0;
if (!empty($params)) {
    $cStmt = mysqli_prepare($koneksi, $countSql);
    mysqli_stmt_bind_param($cStmt, $types, ...$params);
    mysqli_stmt_execute($cStmt);
    $cRes = mysqli_stmt_get_result($cStmt);
    if ($cRow = mysqli_fetch_assoc($cRes)) {
        $totalCount = intval($cRow['total']);
    }
    mysqli_stmt_close($cStmt);
} else {
    $countQuery = mysqli_query($koneksi, $countSql);
    if ($countQuery && $cRow = mysqli_fetch_assoc($countQuery)) {
        $totalCount = intval($cRow['total']);
    }
}

// Ambil data terbaru secara descending
$query = "SELECT * FROM sensor_log $whereSql ORDER BY id DESC LIMIT $limit";
if (!empty($params)) {
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($koneksi, $query);
}

$rows = [];
$thresholds = getSensorThresholds($koneksi);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $ev = evaluateSensorStatus($row['suhu'], $row['asap'], $thresholds);
        $row['status_text'] = $ev['status_text'];
        $row['api_text'] = $ev['api_text'];
        $row['badge_class'] = $ev['badge_class'];
        $row['formatted_time'] = date('H:i:s', strtotime($row['waktu']));
        $row['formatted_date'] = date('d M Y, H:i:s', strtotime($row['waktu']));

        $rows[] = $row;
    }
}

$latest = !empty($rows) ? $rows[0] : null;

// Untuk grafik: Urutkan dari terlama ke terbaru (kronologis kiri ke kanan)
$chartData = array_reverse($rows);

echo json_encode([
    'status' => 'success',
    'total_records' => $totalCount,
    'server_time' => date('d M Y, H:i:s'),
    'thresholds' => $thresholds,
    'latest' => $latest,
    'table_data' => $rows, // Terkini di atas untuk tabel
    'chart_data' => $chartData // Kronologis untuk chart
]);
