<?php
session_start();
include 'includes/db_connect.php';
/**@var mysqli $conn */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$comment_id = (int)($_POST['comment_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

if ($comment_id == 0 || $user_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Verify ownership
$stmt = mysqli_prepare($conn, "SELECT user_id FROM comment WHERE comment_id = ?");
mysqli_stmt_bind_param($stmt, "i", $comment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row || $row['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'Not your comment']);
    exit;
}

// Delete
$del = mysqli_prepare($conn, "DELETE FROM comment WHERE comment_id = ?");
mysqli_stmt_bind_param($del, "i", $comment_id);
$success = mysqli_stmt_execute($del);
mysqli_stmt_close($del);

echo json_encode(['success' => $success]);