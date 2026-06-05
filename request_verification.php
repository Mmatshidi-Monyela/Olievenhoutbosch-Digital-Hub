<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$listing_id = intval($_GET['id'] ?? 0);

if ($listing_id <= 0) {
    $_SESSION['verify_msg'] = "Invalid listing.";
    header("Location: listing_dashboard.php");
    exit();
}

// DB connection
$conn = null;
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
}

if (!$conn) {
    $_SESSION['verify_msg'] = "Database connection failed.";
    header("Location: listing_details_owner.php?id=" . $listing_id);
    exit();
}

// Fetch listing with calculated average rating
$stmt = mysqli_prepare($conn, "
    SELECT 
        l.listing_id, 
        l.verification_status,
        COALESCE(AVG(c.rating), 0) as avg_rating,
        COUNT(c.comment_id) as review_count
    FROM listing l
    LEFT JOIN comments c ON l.listing_id = c.listing_id
    WHERE l.listing_id = ? AND l.user_id = ?
    GROUP BY l.listing_id, l.verification_status
");
mysqli_stmt_bind_param($stmt, "ii", $listing_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    // Check if already pending or verified
    if ($row['verification_status'] == 'Pending') {
        $_SESSION['verify_msg'] = "Your verification request is already pending admin review.";
        header("Location: listing_details_owner.php?id=" . $listing_id);
        exit();
    }

    if ($row['verification_status'] == 'Verified') {
        $_SESSION['verify_msg'] = "Your listing is already verified!";
        header("Location: listing_details_owner.php?id=" . $listing_id);
        exit();
    }

    // Check minimum rating requirement (4.0) — FIX: handle NULL with COALESCE
    $avg_rating = floatval($row['avg_rating']);
    $review_count = intval($row['review_count']);

    if ($avg_rating < 4.0) {
        $_SESSION['verify_msg'] = "You don't qualify yet. Your average rating is " . round($avg_rating, 1) . " based on " . $review_count . " review(s). You need at least 4.0 stars. Check the verification tip.";
        header("Location: listing_details_owner.php?id=" . $listing_id);
        exit();
    }

    // Also require at least 1 review (optional but recommended)
    if ($review_count < 1) {
        $_SESSION['verify_msg'] = "You need at least 1 review before requesting verification.";
        header("Location: listing_details_owner.php?id=" . $listing_id);
        exit();
    }

    // All checks passed — change status to Pending
    $update = mysqli_prepare($conn, "UPDATE listing SET verification_status = 'Pending' WHERE listing_id = ?");
    mysqli_stmt_bind_param($update, "i", $listing_id);

    if (mysqli_stmt_execute($update)) {
        $_SESSION['verify_msg'] = "Verification request sent! An admin will review your listing.";
    } else {
        $_SESSION['verify_msg'] = "Something went wrong. Please try again.";
    }
    mysqli_stmt_close($update);
} else {
    $_SESSION['verify_msg'] = "Listing not found.";
}

mysqli_stmt_close($stmt);
header("Location: listing_details_owner.php?id=" . $listing_id);
exit();
?>







