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

// Get and sanitize inputs
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$extension = $_POST['extension'] ?? '';

// Basic validation
$errors = [];

if (empty($full_name)) {
    $errors[] = "Full name is required.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Valid email is required.";
}

// Re-format contact number with +27 prefix
if (!empty($contact_number)) {
    $digits = preg_replace('/[^0-9]/', '', $contact_number);
    // Remove leading 27 if user included it
    if (strpos($digits, '27') === 0 && strlen($digits) > 10) {
        $digits = substr($digits, 2);
    }
    $contact_number = '+27' . $digits;
} else {
    $contact_number = null;
}

// Check if email already exists for another user
$check_stmt = mysqli_prepare($conn, "SELECT user_id FROM useraccount WHERE email = ? AND user_id != ?");
mysqli_stmt_bind_param($check_stmt, "si", $email, $user_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) > 0) {
    $errors[] = "This email address is already in use by another account.";
}
mysqli_stmt_close($check_stmt);

// If errors, redirect back with error message
if (!empty($errors)) {
    $_SESSION['profile_error'] = implode(" ", $errors);
    header("Location: profile.php");
    exit();
}

// Update the database
$update_stmt = mysqli_prepare($conn, 
    "UPDATE useraccount 
     SET full_name = ?, email = ?, contact_number = ?, extension = ? 
     WHERE user_id = ?"
);

// Handle nullable contact_number
if ($contact_number === null) {
    $update_stmt = mysqli_prepare($conn, 
        "UPDATE useraccount 
         SET full_name = ?, email = ?, contact_number = NULL, extension = ? 
         WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($update_stmt, "sssi", $full_name, $email, $extension, $user_id);
} else {
    mysqli_stmt_bind_param($update_stmt, "ssssi", $full_name, $email, $contact_number, $extension, $user_id);
}

if (mysqli_stmt_execute($update_stmt)) {
    $_SESSION['profile_success'] = "Your profile has been updated successfully.";
} else {
    $_SESSION['profile_error'] = "Something went wrong. Please try again.";
}

mysqli_stmt_close($update_stmt);

header("Location: profile.php");
exit();
?>