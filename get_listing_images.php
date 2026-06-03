<?php
session_start();
header('Content-Type: application/json');
include 'includes/db_connect.php';
/** @var mysqli $conn */

$listing_id = intval($_GET['listing_id'] ?? 0);
if ($listing_id <= 0) { echo json_encode(['error' => 'Invalid']); exit(); }

$stmt = mysqli_prepare($conn, "SELECT image_path FROM listing_images WHERE listing_id = ? ORDER BY uploaded_at ASC");
mysqli_stmt_bind_param($stmt, "i", $listing_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$images = [];
while ($row = mysqli_fetch_assoc($result)) $images[] = $row['image_path'];
mysqli_stmt_close($stmt);

echo json_encode(['listing_id' => $listing_id, 'count' => count($images), 'images' => $images]);
?>