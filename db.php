<?php

// Connect PHP to MySQL database
$connect = mysqli_connect("localhost", "root", "", "parking_permit");

// Check database connection
if (!$connect) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set character encoding
mysqli_set_charset($connect, "utf8mb4");

?>