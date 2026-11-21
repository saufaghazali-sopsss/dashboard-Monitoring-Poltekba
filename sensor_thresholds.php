<?php
// sensor_thresholds.php - Pengelola konfigurasi ambang batas sensor

/**
 * Memastikan tabel batas_sensor terisi nilai default kalibrasi jika kosong
 */
function ensureBatasSensorDefault($koneksi = null) {
    if (!$koneksi && isset($GLOBALS['koneksi'])) {
        $koneksi = $GLOBALS['koneksi'];
    }
    if (!$koneksi) return;
    $check = mysqli_query($koneksi, "SELECT COUNT(*) as tot FROM batas_sensor");
    if ($check && $row = mysqli_fetch_assoc($check)) {
        if (intval($row['tot']) === 0) {
            $defaults = [
                [
                    'nama_sensor' => 'Sensor Suhu DHT22',
                    'parameter'   => 'suhu',
                    'satuan'      => '°C',
                    'batas_min'   => 18.0,
                    'batas_max'   => 40.0,
                    'nilai_alarm' => 55.0,
                    'deskripsi'   => 'Ambang batas suhu waspada dan bahaya kebakaran. Suhu ≥ batas max memicu status Waspada, dan ≥ nilai alarm memicu status Bahaya (Alarm).'
                ],
                [
                    'nama_sensor' => 'Sensor Asap & Gas MQ-2',
                    'parameter'   => 'asap',
                    'satuan'      => 'PPM',
                    'batas_min'   => 0.0,
                    'batas_max'   => 350.0,
                    'nilai_alarm' => 600.0,
                    'deskripsi'   => 'Ambang batas kepekatan asap dan gas mudah terbakar. Nilai ≥ batas max memicu status Waspada, dan ≥ nilai alarm memicu status Bahaya.'
                ],
                [
                    'nama_sensor' => 'Sensor Kelembapan Relatif',
                    'parameter'   => 'kelembapan',
                    'satuan'      => '%',
                    'batas_min'   => 30.0,
                    'batas_max'   => 80.0,
                    'nilai_alarm' => 20.0,
                    'deskripsi'   => 'Ambang batas kelembapan udara. Kelembapan di bawah 30% menandakan kondisi sangat kering yang rawan memicu penyebaran api.'
                ]
            ];

            foreach ($defaults as $d) {
                $stmt = mysqli_prepare($koneksi, "INSERT INTO batas_sensor (nama_sensor, parameter, satuan, batas_min, batas_max, nilai_alarm, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssddds", $d['nama_sensor'], $d['parameter'], $d['satuan'], $d['batas_min'], $d['batas_max'], $d['nilai_alarm'], $d['deskripsi']);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

/**
 * Mengambil konfigurasi seluruh batas sensor dari database
 */
function getSensorThresholds($koneksi = null, $forceReload = false) {
    static $cache = null;
    if ($cache !== null && !$forceReload) {
        return $cache;
    }

    if (!$koneksi && isset($GLOBALS['koneksi'])) {
        $koneksi = $GLOBALS['koneksi'];
    }

    $fallback = [
        'suhu' => [
            'id'          => 1,
            'nama_sensor' => 'Sensor Suhu DHT22',
            'parameter'   => 'suhu',
            'satuan'      => '°C',
            'batas_min'   => 18.0,
            'batas_max'   => 40.0,
            'nilai_alarm' => 55.0,
            'deskripsi'   => 'Ambang batas suhu waspada dan bahaya kebakaran'
        ],
        'asap' => [
            'id'          => 2,
            'nama_sensor' => 'Sensor Asap & Gas MQ-2',
            'parameter'   => 'asap',
            'satuan'      => 'PPM',
            'batas_min'   => 0.0,
            'batas_max'   => 350.0,
            'nilai_alarm' => 600.0,
            'deskripsi'   => 'Ambang batas kepekatan asap dan gas mudah terbakar'
        ],
        'kelembapan' => [
            'id'          => 3,
            'nama_sensor' => 'Sensor Kelembapan Relatif',
            'parameter'   => 'kelembapan',
            'satuan'      => '%',
            'batas_min'   => 30.0,
            'batas_max'   => 80.0,
            'nilai_alarm' => 20.0,
            'deskripsi'   => 'Ambang batas kelembapan udara relatif'
        ]
    ];

    if (!$koneksi) {
        $cache = $fallback;
        return $cache;
    }

    ensureBatasSensorDefault($koneksi);

    $res = mysqli_query($koneksi, "SELECT * FROM batas_sensor ORDER BY id ASC");
    if ($res && mysqli_num_rows($res) > 0) {
        $map = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $param = strtolower(trim($row['parameter'] ?? ''));
            if ($param !== '') {
                $map[$param] = [
                    'id'          => intval($row['id']),
                    'nama_sensor' => $row['nama_sensor'],
                    'parameter'   => $param,
                    'satuan'      => $row['satuan'] ?? '',
                    'batas_min'   => floatval($row['batas_min']),
                    'batas_max'   => floatval($row['batas_max']),
                    'nilai_alarm' => floatval($row['nilai_alarm']),
                    'deskripsi'   => $row['deskripsi'] ?? '',
                    'updated_at'  => $row['updated_at'] ?? $row['last_update'] ?? ''
                ];
            }
        }
        $cache = array_merge($fallback, $map);
        return $cache;
    }

    $cache = $fallback;
    return $cache;
}

/**
 * Menghitung evaluasi status sensor (BAHAYA / WASPADA / AMAN) secara dinamis
 */
function evaluateSensorStatus($suhu, $asap, $thresholds = null, $koneksi = null) {
    if ($thresholds === null) {
        $thresholds = getSensorThresholds($koneksi);
    }

    $suhuAlarm   = isset($thresholds['suhu']['nilai_alarm']) ? floatval($thresholds['suhu']['nilai_alarm']) : 55.0;
    $suhuWaspada = isset($thresholds['suhu']['batas_max'])   ? floatval($thresholds['suhu']['batas_max'])   : 40.0;
    $asapAlarm   = isset($thresholds['asap']['nilai_alarm']) ? floatval($thresholds['asap']['nilai_alarm']) : 600.0;
    $asapWaspada = isset($thresholds['asap']['batas_max'])   ? floatval($thresholds['asap']['batas_max'])   : 350.0;

    $s = floatval($suhu);
    $a = intval($asap);

    if ($s >= $suhuAlarm || $a >= $asapAlarm) {
        return [
            'status_text' => 'BAHAYA',
            'api_text'    => 'Terdeteksi 🔥',
            'badge_class' => 'danger'
        ];
    } elseif ($s >= $suhuWaspada || $a >= $asapWaspada) {
        return [
            'status_text' => 'WASPADA',
            'api_text'    => 'Potensi Api ⚠️',
            'badge_class' => 'warning'
        ];
    } else {
        return [
            'status_text' => 'AMAN',
            'api_text'    => 'Aman (Nihil)',
            'badge_class' => 'safe'
        ];
    }
}
