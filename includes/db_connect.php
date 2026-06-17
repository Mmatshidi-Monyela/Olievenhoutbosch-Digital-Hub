<?php
// includes/db_connect.php — MySQLi Procedural Connection

/**
 * @var mysqli $conn $conn Global database connection variable for MySQLi procedural style.
*/

$servername = "localhost";
$username = "root";
$password = "";
$database = "olieven_dh_db_2";

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to handle special characters
mysqli_set_charset($conn, "utf8mb4");
?>