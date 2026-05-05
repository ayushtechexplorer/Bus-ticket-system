<?php
$conn = new mysqli("localhost", "root", "", "bus_booking");
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>
