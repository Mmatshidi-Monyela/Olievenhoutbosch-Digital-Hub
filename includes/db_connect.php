<?php
// includes/db_connect.php — MySQLi Procedural Connection

/**
 * @var mysqli $conn $conn Global database connection variable for MySQLi procedural style.
*/

$host = "localhost";
$username = "root";
$password = "";
$database = "olieven_dh_db";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to handle special characters
mysqli_set_charset($conn, "utf8mb4");
?>