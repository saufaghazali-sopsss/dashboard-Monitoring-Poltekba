<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "data_sensor";


$koneksi = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);


if (!$koneksi) {

    die(
        "Koneksi database gagal: "
        . mysqli_connect_error()
    );

}

require_once __DIR__ . '/sensor_thresholds.php';

?>