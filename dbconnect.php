<?php
$conn = new mysqli("localhost", "root", "", "long");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
mysqli_set_charset($conn, "utf8");
?>