<?php
session_start();

include 'includes/db_connect.php';
/** @var mysqli $conn */

$listing_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// Get listing details with calculated average rating from comment table
$stmt = mysqli_prepare($conn, "
    SELECT l.listing_id, l.verification_status, COALESCE(AVG(c.rating), 0) as avg_rating
    FROM listing l
    LEFT JOIN comment c ON l.listing_id = c.listing_id AND c.rating > 0
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
        header("Location: business_details_owner.php?id=" . $listing_id);
        exit();
    }

    if ($row['verification_status'] == 'Verified') {
        $_SESSION['verify_msg'] = "Your listing is already verified!";
        header("Location: business_details_owner.php?id=" . $listing_id);
        exit();
    }

    // Check minimum rating requirement (4.0) using calculated average from comments
    if ($row['avg_rating'] < 4.0) {
        $_SESSION['verify_msg'] = "You don't qualify yet. Your average rating is " . round($row['avg_rating'], 1) . ". You need at least 4.0 stars. Check the verification tip.";
        header("Location: business_details_owner.php?id=" . $listing_id);
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
header("Location: business_details_owner.php?id=" . $listing_id);
exit();
?>