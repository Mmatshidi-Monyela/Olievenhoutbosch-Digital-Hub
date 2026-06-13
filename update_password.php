<?php
session_start();
include 'includes/db_connect.php';
/** @var mysqli $conn */

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

// Get inputs
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validation
$errors = [];

if (empty($current_password)) {
    $errors[] = "Current password is required.";
}
if (empty($new_password) || strlen($new_password) < 6) {
    $errors[] = "New password must be at least 6 characters.";
}
if ($new_password !== $confirm_password) {
    $errors[] = "New password and confirmation do not match.";
}

if (!empty($errors)) {
    $_SESSION['password_error'] = implode(" ", $errors);
    header("Location: profile.php#password-settings");
    exit();
}

// Fetch current password hash
$stmt = mysqli_prepare($conn, "SELECT password FROM useraccount WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['password_error'] = "User not found.";
    header("Location: profile.php#password-settings");
    exit();
}

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    $_SESSION['password_error'] = "Current password is incorrect.";
    header("Location: profile.php#password-settings");
    exit();
}

// Hash new password
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update password
$update_stmt = mysqli_prepare($conn, "UPDATE useraccount SET password = ? WHERE user_id = ?");
mysqli_stmt_bind_param($update_stmt, "si", $new_hash, $user_id);

if (mysqli_stmt_execute($update_stmt)) {
    // Password changed successfully - log user out and redirect to login
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['password_success'] = "Password updated successfully! Please log in with your new password.";
    header("Location: login.php");
    exit();
} else {
    $_SESSION['password_error'] = "Failed to update password. Please try again.";
    header("Location: profile.php#password-settings");
    exit();
}

mysqli_stmt_close($update_stmt);
?>