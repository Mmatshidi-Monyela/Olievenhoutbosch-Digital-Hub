<?php
session_start();
include 'includes/db_connect.php';

/** @var mysqli $conn */

// Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: main.php");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$sender_id = $_SESSION['user_id'];

// Get and validate inputs
$listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$return_to = isset($_POST['return_to']) ? $_POST['return_to'] : 'messages.php';

// Validate inputs
$errors = [];

if (!$listing_id || $listing_id <= 0) {
    $errors[] = 'Invalid listing.';
}

if (!$receiver_id || $receiver_id <= 0) {
    $errors[] = 'Invalid recipient.';
}

if (empty($message)) {
    $errors[] = 'Message cannot be empty.';
}

if (strlen($message) > 2000) {
    $errors[] = 'Message is too long (max 2000 characters).';
}

// Prevent self-messaging
if ($sender_id == $receiver_id) {
    $errors[] = 'You cannot message yourself.';
}

// Validate that the listing exists and receiver is valid
if (empty($errors) && $conn) {
    // Check listing exists and is active
    $listing_stmt = mysqli_prepare($conn, "SELECT user_id FROM listing WHERE listing_id = ? AND is_active = 1");
    if ($listing_stmt) {
        mysqli_stmt_bind_param($listing_stmt, "i", $listing_id);
        mysqli_stmt_execute($listing_stmt);
        $listing_result = mysqli_stmt_get_result($listing_stmt);
        $listing = mysqli_fetch_assoc($listing_result);
        mysqli_stmt_close($listing_stmt);

        if (!$listing) {
            $errors[] = 'Listing not found or inactive.';
        } else {
            $owner_id = (int)$listing['user_id'];

            // If receiver is not the listing owner, verify they are a previous conversation participant
            if ($receiver_id !== $owner_id) {
                $verify_stmt = mysqli_prepare($conn, 
                    "SELECT COUNT(*) as cnt FROM message 
                     WHERE listing_id = ? AND sender_id = ? AND receiver_id = ?");
                if ($verify_stmt) {
                    mysqli_stmt_bind_param($verify_stmt, "iii", $listing_id, $receiver_id, $sender_id);
                    mysqli_stmt_execute($verify_stmt);
                    $verify_result = mysqli_stmt_get_result($verify_stmt);
                    $verify_row = mysqli_fetch_assoc($verify_result);
                    mysqli_stmt_close($verify_stmt);

                    if ((int)$verify_row['cnt'] === 0) {
                        $errors[] = 'Invalid recipient for this listing.';
                    }
                }
            }
        }
    }

    // Verify receiver exists
    if (empty($errors)) {
        $user_stmt = mysqli_prepare($conn, "SELECT user_id FROM useraccount WHERE user_id = ?");
        if ($user_stmt) {
            mysqli_stmt_bind_param($user_stmt, "i", $receiver_id);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
            mysqli_stmt_close($user_stmt);

            if (!$user) {
                $errors[] = 'Recipient not found.';
            }
        }
    }
}

// If errors, redirect back with error
if (!empty($errors)) {
    $_SESSION['error_msg'] = implode(' ', $errors);
    // Sanitize return_to to prevent open redirect
    $safe_return = 'messages.php';
    if (strpos($return_to, 'messages.php') === 0 || strpos($return_to, 'view_service.php') === 0) {
        $safe_return = $return_to;
    }
    header("Location: " . $safe_return);
    exit();
}

// Insert message
if ($conn) {
    $stmt = mysqli_prepare($conn, "INSERT INTO message 
        (listing_id, sender_id, receiver_id, message, read_status, created_at) 
        VALUES (?, ?, ?, ?, 0, NOW())");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iiis", $listing_id, $sender_id, $receiver_id, $message);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_msg'] = "Message sent successfully!";
        } else {
            $_SESSION['error_msg'] = "Failed to send message.";
            error_log("Message insert failed: " . mysqli_error($conn));
        }

        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_msg'] = "Database error. Please try again.";
        error_log("Message prepare failed: " . mysqli_error($conn));
    }
} else {
    $_SESSION['error_msg'] = "Database connection failed.";
}

// Sanitize return_to to prevent open redirect
$safe_return = 'messages.php';
if (strpos($return_to, 'messages.php') === 0 || strpos($return_to, 'view_service.php') === 0) {
    $safe_return = $return_to;
}

header("Location: " . $safe_return);
exit();