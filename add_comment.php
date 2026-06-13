<?php
session_start();
include 'includes/db_connect.php';

/** @var mysqli $conn */

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: main.php');
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// CSRF Validation
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';
if (empty($session_token) || !hash_equals($session_token, $submitted_token)) {
    $_SESSION['error_msg'] = 'Invalid request. Please try again.';
    header('Location: main.php');
    exit;
}

// Get and validate inputs
$listing_id = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$comment_text = isset($_POST['comment_text']) ? trim($_POST['comment_text']) : '';

$errors = [];

if (!$listing_id || $listing_id <= 0) {
    $errors[] = 'Invalid listing.';
}

if (!$rating || $rating < 1 || $rating > 5) {
    $errors[] = 'Please select a rating (1-5 stars).';
}

if (empty($comment_text)) {
    $errors[] = 'Please write a comment.';
}

if (strlen($comment_text) > 1000) {
    $errors[] = 'Comment is too long (max 1000 characters).';
}

// Verify listing exists and get owner info
$listing_info = null;
if (empty($errors) && $conn) {
    $listing_stmt = mysqli_prepare($conn, "SELECT l.listing_id, l.listing_name, l.user_id as owner_id, u.full_name as owner_name 
        FROM listing l 
        JOIN useraccount u ON l.user_id = u.user_id 
        WHERE l.listing_id = ? AND l.is_active = 1");
    if ($listing_stmt) {
        mysqli_stmt_bind_param($listing_stmt, "i", $listing_id);
        mysqli_stmt_execute($listing_stmt);
        $listing_result = mysqli_stmt_get_result($listing_stmt);
        $listing_info = mysqli_fetch_assoc($listing_result);
        if (!$listing_info) {
            $errors[] = 'Listing not found.';
        }
        mysqli_stmt_close($listing_stmt);
    }

    // Check user hasn't already commented on this listing (optional - prevent duplicates)
    $check_stmt = mysqli_prepare($conn, "SELECT comment_id FROM comment WHERE listing_id = ? AND user_id = ?");
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "ii", $listing_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        if (mysqli_fetch_assoc($check_result)) {
            // Update existing comment instead of inserting new one
            mysqli_stmt_close($check_stmt);

            // Handle image upload if provided
            $image_path = null;
            if (isset($_FILES['comment_image']) && $_FILES['comment_image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = handleImageUpload($_FILES['comment_image']);
                if ($upload_result['success']) {
                    $image_path = $upload_result['path'];
                } else {
                    $errors[] = $upload_result['error'];
                }
            }

            if (empty($errors)) {
                if ($image_path) {
                    $update_sql = "UPDATE comment SET rating = ?, comment_text = ?, image_path = ?, created_at = NOW() 
                                   WHERE listing_id = ? AND user_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "issii", $rating, $comment_text, $image_path, $listing_id, $user_id);
                } else {
                    $update_sql = "UPDATE comment SET rating = ?, comment_text = ?, created_at = NOW() 
                                   WHERE listing_id = ? AND user_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "isii", $rating, $comment_text, $listing_id, $user_id);
                }

                if ($update_stmt) {
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                    $_SESSION['success_msg'] = 'Your review has been updated.';
                }
            }

            header("Location: view_service.php?id=$listing_id");
            exit;
        }
        mysqli_stmt_close($check_stmt);
    }
}

// Handle image upload
function handleImageUpload($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid image format. Only JPG, PNG, GIF, WEBP allowed.'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Image too large (max 5MB).'];
    }

    $upload_dir = 'uploads/comments/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('comment_') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $filepath];
    }

    return ['success' => false, 'error' => 'Failed to upload image.'];
}

// Insert new comment
if (empty($errors) && $conn) {
    $image_path = null;
    if (isset($_FILES['comment_image']) && $_FILES['comment_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handleImageUpload($_FILES['comment_image']);
        if ($upload_result['success']) {
            $image_path = $upload_result['path'];
        } else {
            $errors[] = $upload_result['error'];
        }
    }

    if (empty($errors)) {
        if ($image_path) {
            $insert_sql = "INSERT INTO comment (listing_id, user_id, rating, comment_text, image_path, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "iiiss", $listing_id, $user_id, $rating, $comment_text, $image_path);
        } else {
            $insert_sql = "INSERT INTO comment (listing_id, user_id, rating, comment_text, created_at) 
                           VALUES (?, ?, ?, ?, NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "iiis", $listing_id, $user_id, $rating, $comment_text);
        }

        if ($insert_stmt) {
            $success = mysqli_stmt_execute($insert_stmt);
            mysqli_stmt_close($insert_stmt);

            if ($success) {
                $_SESSION['success_msg'] = 'Your review has been posted.';

                //      NOTIFY LISTING OWNER =====
                if ($listing_info && $listing_info['owner_id'] != $user_id) {
                    $notif_title = "New Review on Your Listing";
                    $notif_message = "Your listing \"" . $listing_info['listing_name'] . "\" received a new " . $rating . "-star review.";
                    $notif_link = "view_service.php?id=" . $listing_id;

                    $notif_stmt = mysqli_prepare($conn, "INSERT INTO notification (user_id, title, message, type, link, created_at) VALUES (?, ?, ?, 'info', ?, NOW())");
                    if ($notif_stmt) {
                        mysqli_stmt_bind_param($notif_stmt, "isss", $listing_info['owner_id'], $notif_title, $notif_message, $notif_link);
                        mysqli_stmt_execute($notif_stmt);
                        mysqli_stmt_close($notif_stmt);
                    }
                }
            } else {
                $_SESSION['error_msg'] = 'Failed to post review. Please try again.';
                error_log("Comment insert failed: " . mysqli_error($conn));
            }
        } else {
            $_SESSION['error_msg'] = 'Database error. Please try again.';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['error_msg'] = implode(' ', $errors);
}

header("Location: view_service.php?id=$listing_id");
exit;