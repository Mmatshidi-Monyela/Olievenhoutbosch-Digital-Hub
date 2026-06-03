<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit();
}

include 'includes/db_connect.php';
/** @var mysqli $conn */

$listing_id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($conn && $listing_id > 0) {
    // Get listing name and owner before updating
    $info_stmt = mysqli_prepare($conn, "SELECT listing_name, user_id FROM listing WHERE listing_id = ?");
    mysqli_stmt_bind_param($info_stmt, "i", $listing_id);
    mysqli_stmt_execute($info_stmt);
    $info_result = mysqli_stmt_get_result($info_stmt);
    $listing_info = mysqli_fetch_assoc($info_result);
    mysqli_stmt_close($info_stmt);

    // Update status
    $stmt = mysqli_prepare($conn, "UPDATE listing SET verification_status = 'Unverified' WHERE listing_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $listing_id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['admin_msg'] = "Listing rejected.";

        // Notify owner
        if ($listing_info) {
            $title = "Verification Rejected";
            $message = 'Your verification request for "' . $listing_info['listing_name'] . '" was rejected. Please review your listing details and try again.';
            $link = "listing_details_owner.php?id=" . $listing_id;

            $notif_stmt = mysqli_prepare($conn, "INSERT INTO notification (user_id, title, message, type, link) VALUES (?, ?, ?, 'danger', ?)");
            mysqli_stmt_bind_param($notif_stmt, "isss", $listing_info['user_id'], $title, $message, $link);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
        }
    } else {
        $_SESSION['admin_msg'] = "Error rejecting listing.";
    }
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['admin_msg'] = "Database not connected or invalid listing ID.";
}

header("Location: admin_requests.php");
exit();
?>