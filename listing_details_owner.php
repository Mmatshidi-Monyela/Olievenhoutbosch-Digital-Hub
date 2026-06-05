<?php
session_start();
/**@var mysqli $conn */ 

// ============================================
// BUSINESS DETAILS OWNER (Manage Listing)
// Shows verification status + Request Verification button + DELETE LISTING
// ============================================

$listing_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

// Redirect if not logged in
if ($user_id == 0) {
    header('Location: login.php');
    exit;
}

// Handle verification request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_verification'])) {
    if (file_exists('includes/db_connect.php')) {
        include 'includes/db_connect.php';
    }

    if (!$conn) {
        $_SESSION['verify_msg'] = "Database connection failed.";
        header('Location: listing_details_owner.php?id=' . $listing_id);
        exit;
    }

    // Fetch listing with calculated average rating
    $v_stmt = mysqli_prepare($conn, "
        SELECT 
            l.listing_id, 
            l.verification_status,
            COALESCE(AVG(c.rating), 0) as avg_rating,
            COUNT(c.comment_id) as review_count
        FROM listing l
        LEFT JOIN comment c ON l.listing_id = c.listing_id
        WHERE l.listing_id = ? AND l.user_id = ?
        GROUP BY l.listing_id, l.verification_status
    ");
    mysqli_stmt_bind_param($v_stmt, "ii", $listing_id, $user_id);
    mysqli_stmt_execute($v_stmt);
    $v_result = mysqli_stmt_get_result($v_stmt);

    if ($v_row = mysqli_fetch_assoc($v_result)) {
        // Check if already pending or verified
        if ($v_row['verification_status'] == 'Pending') {
            $_SESSION['verify_msg'] = "Your verification request is already pending admin review.";
        } elseif ($v_row['verification_status'] == 'Verified') {
            $_SESSION['verify_msg'] = "Your listing is already verified!";
        } else {
            $avg_rating = floatval($v_row['avg_rating']);
            $review_count = intval($v_row['review_count']);

            if ($review_count < 1) {
                $_SESSION['verify_msg'] = "You need at least 1 review before requesting verification.";
            } elseif ($avg_rating < 4.0) {
                $_SESSION['verify_msg'] = "You don't qualify yet. Your average rating is " . round($avg_rating, 1) . " based on " . $review_count . " review(s). You need at least 4.0 stars.";
            } else {
                // All checks passed — change status to Pending
                $upd = mysqli_prepare($conn, "UPDATE listing SET verification_status = 'Pending' WHERE listing_id = ?");
                mysqli_stmt_bind_param($upd, "i", $listing_id);

                if (mysqli_stmt_execute($upd)) {
                    $_SESSION['verify_msg'] = "Verification request sent! An admin will review your listing.";
                } else {
                    $_SESSION['verify_msg'] = "Something went wrong. Please try again.";
                }
                mysqli_stmt_close($upd);
            }
        }
    } else {
        $_SESSION['verify_msg'] = "Listing not found or you don't have permission.";
    }
    mysqli_stmt_close($v_stmt);

    header('Location: listing_details_owner.php?id=' . $listing_id);
    exit;
}

// Handle DELETE listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_listing'])) {
    // TODO: DELETE FROM Listing WHERE listing_id = ? AND user_id = ?
    // TODO: DELETE FROM comments WHERE listing_id = ?
    // TODO: DELETE FROM verification_requests WHERE listing_id = ?
    $_SESSION['dashboard_message'] = 'Your listing has been permanently deleted.';
    header('Location: listing_dashboard.php');
    exit;
}

// NEW: Handle delete single image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $image_id = intval($_POST['image_id'] ?? 0);
    if ($image_id > 0 && file_exists('includes/db_connect.php')) {
        include 'includes/db_connect.php';
        $del_stmt = mysqli_prepare($conn, "SELECT image_path FROM listing_images WHERE image_id = ? AND listing_id = ?");
        mysqli_stmt_bind_param($del_stmt, "ii", $image_id, $listing_id);
        mysqli_stmt_execute($del_stmt);
        $del_result = mysqli_stmt_get_result($del_stmt);
        $del_row = mysqli_fetch_assoc($del_result);
        mysqli_stmt_close($del_stmt);

        if ($del_row) {
            if (file_exists($del_row['image_path'])) unlink($del_row['image_path']);
            $del_stmt2 = mysqli_prepare($conn, "DELETE FROM listing_images WHERE image_id = ?");
            mysqli_stmt_bind_param($del_stmt2, "i", $image_id);
            mysqli_stmt_execute($del_stmt2);
            mysqli_stmt_close($del_stmt2);
        }
    }
    header('Location: listing_details_owner.php?id=' . $listing_id);
    exit;
}

// NEW: Handle add more images
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_photos'])) {
    if (!empty($_FILES['new_photos']) && file_exists('includes/db_connect.php')) {
        include 'includes/db_connect.php';

        $uploaded = [];
        $max_images = 5;
        $max_size = 2 * 1024 * 1024;
        $allowed = ['image/jpeg', 'image/png', 'image/jpg'];

        $cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM listing_images WHERE listing_id = ?");
        mysqli_stmt_bind_param($cnt_stmt, "i", $listing_id);
        mysqli_stmt_execute($cnt_stmt);
        $cnt_result = mysqli_stmt_get_result($cnt_stmt);
        $cnt_row = mysqli_fetch_assoc($cnt_result);
        $existing = $cnt_row['cnt'] ?? 0;
        mysqli_stmt_close($cnt_stmt);

        $remaining = $max_images - $existing;

        for ($i = 0; $i < count($_FILES['new_photos']['name']) && $i < $remaining; $i++) {
            if (empty($_FILES['new_photos']['tmp_name'][$i])) continue;

            $tmp = $_FILES['new_photos']['tmp_name'][$i];
            $name = $_FILES['new_photos']['name'][$i];
            $size = $_FILES['new_photos']['size'][$i];
            $type = $_FILES['new_photos']['type'][$i];

            if ($size > $max_size || !in_array($type, $allowed)) continue;
            if (getimagesize($tmp) === false) continue;

            $dir = "uploads/listings/";
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $safe = preg_replace('/[^a-zA-Z0-9.-]/', '_', $name);
            $target = $dir . time() . "_" . $i . "_" . $safe;

            if (move_uploaded_file($tmp, $target)) {
                $uploaded[] = $target;
            }
        }

        if (!empty($uploaded)) {
            $ins_stmt = mysqli_prepare($conn, "INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)");
            foreach ($uploaded as $path) {
                mysqli_stmt_bind_param($ins_stmt, "is", $listing_id, $path);
                mysqli_stmt_execute($ins_stmt);
            }
            mysqli_stmt_close($ins_stmt);
        }
    }
    header('Location: listing_details_owner.php?id=' . $listing_id);
    exit;
}

// Fetch listing from database

$user_id = $_SESSION["user_id"] ?? 0;
$listing = null;
$comments = [];
$gallery_images = [];
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
    /** @var mysqli $conn */

    // Fetch listing
    $stmt = mysqli_prepare($conn, 'SELECT * FROM listing WHERE listing_id = ? AND user_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $listing_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $listing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Fetch comments
    $cmt_stmt = mysqli_prepare($conn, 'SELECT c.*, u.full_name FROM comment c JOIN useraccount u ON c.user_id = u.user_id WHERE c.listing_id = ? ORDER BY c.created_at DESC');
    mysqli_stmt_bind_param($cmt_stmt, 'i', $listing_id);
    mysqli_stmt_execute($cmt_stmt);
    $cmt_result = mysqli_stmt_get_result($cmt_stmt);
    while ($row = mysqli_fetch_assoc($cmt_result)) {
        $comments[] = $row;
    }
    mysqli_stmt_close($cmt_stmt);

    // Fetch gallery images
    $gal_stmt = mysqli_prepare($conn, 'SELECT image_id, image_path FROM listing_images WHERE listing_id = ? ORDER BY uploaded_at ASC');
    mysqli_stmt_bind_param($gal_stmt, 'i', $listing_id);
    mysqli_stmt_execute($gal_stmt);
    $gal_result = mysqli_stmt_get_result($gal_stmt);
    while ($g = mysqli_fetch_assoc($gal_result)) {
        $gallery_images[] = $g;
    }
    mysqli_stmt_close($gal_stmt);
}

// Redirect if listing not found or not owned by user
if (!$listing) {
    header('Location: listing_dashboard.php');
    exit();
}

// Calculate average rating from comments (not just cached value)
$avg_rating = 0;
if (count($comments) > 0) {
    $total = 0;
    $count = 0;
    foreach ($comments as $c) {
        if (isset($c['rating']) && $c['rating'] > 0) {
            $total += $c['rating'];
            $count++;
        }
    }
    if ($count > 0) {
        $avg_rating = round($total / $count, 1);
    }
}

// Build all extensions array (same as client side)
$all_extensions = [$listing['extension']];
if (!empty($listing['service_extensions'])) {
    $additional = explode(',', $listing['service_extensions']);
    $all_extensions = array_merge($all_extensions, $additional);
}
// Payment options
$payment_options = [];
if (!empty($listing['payment_options'])) {
    $payment_options = array_map('trim', explode(',', $listing['payment_options']));
}

// Delivery modes
$delivery_modes = [];
if (!empty($listing['delivery_mode'])) {
    $delivery_modes = array_map('trim', explode(',', $listing['delivery_mode']));
}

// Listing type label
$type_label = 'Service';
if ($listing['listing_type'] == 'product') $type_label = 'Goods';
if ($listing['listing_type'] == 'both') $type_label = 'Service & Goods';

$verify_msg = $_SESSION['verify_msg'] ?? '';
unset($_SESSION['verify_msg']);

$statusClass = 'status-unverified';
if ($listing['verification_status'] == 'Verified') $statusClass = 'status-verified';
if ($listing['verification_status'] == 'Pending') $statusClass = 'status-pending';

// Helper functions
function getInitials($name) {
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return substr($initials, 0, 2);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}

function getDeliveryLabel($mode) {
    $labels = [
        'door_to_door' => 'Door-to-Door',
        'customer_comes_to_me' => 'Customer Comes to Me',
        'both_service' => 'Both (Door-to-Door + On-site)',
        'both_product' => 'Both (Delivery + Pickup)',
        'i_deliver' => 'I Deliver',
        'customer_pickup' => 'Customer Pickup'
    ];
    return $labels[$mode] ?? $mode;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Listing - <?php echo htmlspecialchars($listing['listing_name']); ?></title>
    <link rel="icon" type="image/png" href="images/logo.png"> 
    <link rel="apple-touch-icon" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #c99383;
            --copper: #ba745f;
            --light-grey: #f4f7f6;
        }

        * { 
            -webkit-tap-highlight-color: transparent; 
            box-sizing: border-box;
        }

        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            background-color: var(--light-grey);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #333;
            padding-bottom: 80px;
            margin: 0;
        }

        /* ===== NAVBAR ===== */
        .navbar-custom {
            background-color: var(--plum);
            height: 56px;
            padding: 0;
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1030;
            width: 100%;
            border-bottom: 3px solid var(--rose-gold);
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            min-width: 0;
        }
        .brand-text {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .back-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .back-link:hover { color: var(--rose-gold); }

        @media (max-width: 575.98px) {
            .navbar-custom {
                height: 52px;
                padding: 0 12px;
            }
            .brand-text {
                font-size: 0.85rem;
                max-width: 160px;
            }
            .back-link {
                font-size: 1.1rem;
                padding: 8px;
                margin-right: -8px;
            }
            .back-link span {
                display: none;
            }
        }

        /* ===== PHOTO GALLERY ===== */
        .photo-hero {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            max-height: 360px;
            background: #ffffff;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 575.98px) {
            .photo-hero {
                aspect-ratio: 1 / 1;
                max-height: 320px;
            }
        }
        .photo-hero img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .photo-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.9);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--plum);
            font-size: 1.2rem;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 2;
        }
        .photo-hero:hover .photo-nav { opacity: 1; }
        .photo-nav.prev { left: 12px; }
        .photo-nav.next { right: 12px; }

        .thumb-strip {
            display: flex;
            gap: 8px;
            padding: 12px 16px;
            overflow-x: auto;
            background: white;
            scrollbar-width: none;
            width: 100%;
        }
        .thumb-strip::-webkit-scrollbar { display: none; }
        .thumb-item {
            width: 72px;
            height: 72px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s;
        }
        .thumb-item.active { border-color: var(--plum); }
        .thumb-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* ===== INFO CARD ===== */
        .info-card {
            background: white;
            padding: 20px 16px;
        }
        .listing-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.3;
            margin-bottom: 6px;
            word-break: break-word;
        }
        .listing-meta {
            color: #888;
            font-size: 0.85rem;
            margin-bottom: 12px;
            word-break: break-word;
        }
        .type-badge {
            background: var(--plum);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        .status-badge {
            border-radius: 8px;
            padding: 8px 15px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .status-unverified { background-color: #eee; color: #666; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-verified { background-color: #d4edda; color: #155724; }

        /* ===== TAG PILLS ===== */
        .tag-pill {
            background: #fdfaf9;
            color: var(--copper);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            margin-right: 6px;
            margin-bottom: 6px;
            border: 1px solid #f0e0dc;
            white-space: nowrap;
        }
        .tag-pill.primary {
            background: var(--plum);
            color: white;
            border-color: var(--plum);
        }
        .tag-pill.delivery {
            background: #fff8f0;
            color: #c9782a;
            border-color: #f5e6d3;
        }
        .tag-pill.payment {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #c8e6c9;
        }
        .tag-pill.product-type {
            background: #fff3e0;
            color: #e65100;
            border-color: #ffe0b2;
        }
        .tag-pill.service-type {
            background: #e3f2fd;
            color: #0d47a1;
            border-color: #bbdefb;
        }

        /* ===== DESCRIPTION ===== */
        .desc-section {
            background: white;
            margin: 8px 0;
            padding: 16px;
        }
        .desc-text {
            font-size: 0.95rem;
            line-height: 1.7;
            color: #444;
            word-break: break-word;
        }

        /* ===== COMMENTS SECTION ===== */
        .comments-section {
            background: white;
            margin: 8px 0;
            padding: 16px;
        }
        .comments-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--plum);
            margin-bottom: 16px;
        }
        .avg-rating-display {
            background: var(--plum);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .avg-rating-number {
            font-size: 3rem;
            font-weight: bold;
            line-height: 1;
        }
        .avg-rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
            margin: 8px 0;
        }
        .avg-rating-text {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .comment-card {
            display: flex;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .comment-card:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .comment-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--rose-gold);
            color: var(--plum);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-right: 14px;
        }
        .comment-body {
            flex: 1;
            min-width: 0;
        }
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
        }
        .comment-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: #1a1a1a;
        }
        .comment-time {
            font-size: 0.75rem;
            color: #aaa;
            white-space: nowrap;
            margin-left: 8px;
        }
        .comment-rating {
            color: #ffc107;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        .comment-rating .empty {
            color: #ddd;
        }
        .comment-text {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.5;
            word-break: break-word;
            margin: 0;
        }
        .comment-image {
            max-height: 200px;
            border-radius: 10px;
            margin-top: 8px;
            max-width: 100%;
            display: block;
        }
        .owner-readonly-notice {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 10px;
            padding: 12px 16px;
            color: #0d47a1;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        /* ===== MOBILE BUBBLE CARDS ===== */
        .mobile-bubble-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin: 8px 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .mobile-bubble-card .section-label {
            font-size: 1rem;
            font-weight: 700;
            color: var(--plum);
            margin-bottom: 16px;
        }
        .mobile-bubble-card .check-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #444;
        }
        .mobile-bubble-card .check-item:last-child {
            margin-bottom: 0;
        }
        .mobile-bubble-card .detail-value {
            font-size: 0.9rem;
            color: #444;
            margin-bottom: 8px;
        }

        /* ===== SIDEBAR ACTION BUTTONS (DESKTOP) ===== */
        .sidebar-action-card {
            background: white ;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .sidebar-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 10px;
        }
        .sidebar-action-btn:last-child {
            margin-bottom: 0;
        }
        .sidebar-action-btn.edit {
            background: var(--rose-gold);
            color: white;
        }
        .sidebar-action-btn.edit:hover {
            background: var(--rose-gold);
            color: var(--plum);
        }
        .sidebar-action-btn.verify {
            background: var(--plum);
            color: white;
        }
        .sidebar-action-btn.verify:hover {
            background: #f0b8ad;
        }
        .sidebar-action-btn.pending {
            background: #704081;
            color: white;
            cursor: not-allowed;
            opacity: 0.8;
        }
        .sidebar-action-btn.pending:hover { background: #3a065e; color: white; }

        /**hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh */
        .sidebar-action-btn.verified {
            background: #28a745;
            color: white;
            cursor: default;
        }

        /* ===== OWNER ACTION BUTTONS ===== */
        .btn-verify {
            background-color: var(--plum);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: bold;
        }
        .btn-verify:hover { background-color: #350666; color: white; }
        .btn-verify:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .btn-edit {
            border: 2px solid var(--rose-gold);
            color: var(--plum);
            font-weight: bold;
            border-radius: 8px;
            padding: 10px 25px;
        }
        .btn-edit:hover { background-color: var(--rose-gold); color: var(--plum); }
        .btn-delete-listing {
            background-color: #ffe5e5;
            color: #d9534f;
            border: 2px solid #d9534f;
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-delete-listing:hover {
            background-color: #d9534f;
            color: white;
        }

        /* ===== STICKY BOTTOM CTA ===== */
        .sticky-cta {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 16px;
            display: flex;
            gap: 10px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
            z-index: 1040;
        }
        .btn-cta-primary {
            flex: 1;
            padding: 14px;
            background: var(--plum);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-cta-primary:hover { background: #3a065e; color: white; }
        .btn-cta-secondary {
            width: 52px;
            height: 52px;
            background: var(--rose-gold);
            color: var(--plum);
            border: none;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .btn-cta-secondary:hover { background: #f0b8ad; }

        /* ===== DESKTOP LAYOUT ===== */
        @media (min-width: 992px) {
            body { padding-bottom: 0; }
            .page-container {
                max-width: 1100px;
                margin: 0 auto;
                display: grid;
                grid-template-columns: 1fr 360px;
                gap: 20px;
                padding: 20px;
            }
            .photo-hero { aspect-ratio: 4 / 3; max-height: 420px; border-radius: 16px; }
            .info-card, .desc-section, .comments-section {
                border-radius: 16px;
                margin: 0 0 16px 0;
            }
            .sticky-cta { display: none; }
            .sidebar-desktop {
                position: sticky;
                top: 72px;
                height: fit-content;
            }
            .sidebar-card {
                background: white;
                border-radius: 16px;
                padding: 20px;
                margin-bottom: 16px;
            }
        }

        @media (max-width: 991.98px) {
            .sidebar-desktop { display: none !important; }
        }

        /* ===== LIGHTBOX ===== */
        .lightbox-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .lightbox-overlay.active { display: flex; }
        .lightbox-img {
            max-width: 95%;
            max-height: 85%;
            border-radius: 8px;
            object-fit: contain;
        }
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            background: rgba(255,255,255,0.15);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            z-index: 10000;
            pointer-events: auto;
        }
        .lightbox-close:hover {
            background: rgba(255,255,255,0.3);
        }
        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 2rem;
            cursor: pointer;
            background: rgba(255,255,255,0.1);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
        }
        .lightbox-nav:hover { background: rgba(255,255,255,0.2); }
        .lightbox-prev { left: 16px; }
        .lightbox-next { right: 16px; }
        .lightbox-counter {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 0.9rem;
            background: rgba(0,0,0,0.5);
            padding: 6px 16px;
            border-radius: 20px;
        }

        img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="container-fluid d-flex align-items-center justify-content-between px-3 px-lg-4">
        <!-- LEFT: Logo + Brand -->
        <a href="listing_dashboard.php" class="navbar-brand">
            <img src="images/logo.png" width="28" height="28" alt="logo" style="flex-shrink:0;">
            <span class="brand-text d-sm-none">Olievenhoutbosch DH</span>
            <span class="brand-text d-none d-sm-inline">Olievenhoutbosch Digital Hub</span>
        </a>
        
        <!-- RIGHT: Back button -->
        <a href="listing_dashboard.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            <span class="d-none d-sm-inline">Back</span>
        </a>
    </div>
</nav>

<?php if (!empty($verify_msg)): ?>
<div class="container-fluid" style="max-width:1100px;margin:0 auto;padding:0;">
    <div class="alert alert-info alert-dismissible fade show mx-3 mt-3" role="alert" style="border-radius:12px;background:#e3f2fd;border:none;color:#0d47a1;">
        <i class="bi bi-info-circle-fill me-2"></i><?php echo htmlspecialchars($verify_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>

<!-- Mobile: Hero Photo -->
<div class="d-lg-none">
    <div class="photo-hero" id="mobileHero" onclick="openLightbox(currentMobileIndex)" style="cursor:pointer;">
        <img src="<?php echo htmlspecialchars($gallery_images[0]['image_path'] ?? $listing['image_path'] ?? 'uploads/listings/default_listing.jpg'); ?>" alt="<?php echo htmlspecialchars($listing['listing_name']); ?>" id="heroImgMobile">
        <?php if (count($gallery_images) > 1): ?>
        <button class="photo-nav prev" onclick="changeHeroMobile(-1)"><i class="bi bi-chevron-left"></i></button>
        <button class="photo-nav next" onclick="changeHeroMobile(1)"><i class="bi bi-chevron-right"></i></button>
        <?php endif; ?>
    </div>
    <?php if (count($gallery_images) > 1): ?>
    <div class="thumb-strip" id="mobileThumbStrip">
        <?php foreach ($gallery_images as $idx => $img): ?>
        <div class="thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="setHeroMobile(<?php echo $idx; ?>)">
            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Desktop: Grid Layout -->
<div class="page-container d-none d-lg-grid">
    <!-- Left Column -->
    <div>
        <!-- Desktop Gallery -->
        <div class="photo-hero" style="border-radius:16px;">
            <img src="<?php echo htmlspecialchars($gallery_images[0]['image_path'] ?? $listing['image_path'] ?? 'uploads/listings/default_listing.jpg'); ?>" alt="<?php echo htmlspecialchars($listing['listing_name']); ?>" id="heroImgDesktop" style="cursor:pointer;" onclick="openLightbox(currentDesktopIndex)">
            <?php if (count($gallery_images) > 1): ?>
            <button class="photo-nav prev" onclick="changeHeroDesktop(-1)"><i class="bi bi-chevron-left"></i></button>
            <button class="photo-nav next" onclick="changeHeroDesktop(1)"><i class="bi bi-chevron-right"></i></button>
            <?php endif; ?>
        </div>
        <?php if (count($gallery_images) > 1): ?>
        <div class="thumb-strip" id="desktopThumbStrip" style="border-radius:0 0 16px 16px;">
            <?php foreach ($gallery_images as $idx => $img): ?>
            <div class="thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="setHeroDesktop(<?php echo $idx; ?>)">
                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Info Card -->
        <div class="info-card" style="border-radius:16px;">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="type-badge"><?php echo $type_label; ?></span>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $listing['verification_status']; ?>
                        </span>
                    </div>
                    <h1 class="listing-title"><?php echo htmlspecialchars($listing['listing_name']); ?></h1>
                    <p class="listing-meta"><?php echo htmlspecialchars($listing['category']); ?> &bull; 
                        <?php 
                        if ($listing['listing_type'] == 'both') {
                            echo htmlspecialchars($listing['service_type']) . ' / ' . htmlspecialchars($listing['product_type']);
                        } elseif ($listing['listing_type'] == 'product') {
                            echo htmlspecialchars($listing['product_type']);
                        } else {
                            echo htmlspecialchars($listing['service_type']);
                        }
                        ?>
                    </p>
                    <?php if($listing['verification_status'] == 'Pending'): ?>
                    <div class="pending-notice mt-2" style="font-size:0.8rem;padding:8px 12px;">
                        <strong>Under Review:</strong> Your verification request is being reviewed by our admin team.
                    </div>
                    <?php elseif($listing['verification_status'] == 'Verified'): ?>
                    <div class="verified-notice mt-2" style="font-size:0.8rem;padding:8px 12px;">
                        <strong>Verified:</strong> Your listing is verified and will display a verified badge to customers.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="desc-section" style="border-radius:16px;">
            <div class="section-label" style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Description</div>
            <p class="desc-text"><?php echo nl2br(htmlspecialchars($listing['description'])); ?></p>
        </div>

        <!-- Comments / Feedback (Read-only for owner) -->
        <div class="comments-section" style="border-radius:16px;">
            <div class="comments-title">User Comments & Feedback</div>

            <?php if (count($comments) > 0): ?>
            <div class="avg-rating-display">
                <div class="avg-rating-number"><?php echo $avg_rating; ?></div>
                <div class="avg-rating-stars">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="bi bi-star<?php echo $i <= round($avg_rating) ? '-fill' : ''; ?>"></i>
                    <?php endfor; ?>
                </div>
                <div class="avg-rating-text">Based on <?php echo count($comments); ?> review(s)</div>
            </div>
            <?php endif; ?>

            <?php foreach($comments as $comment): 
                $initials = getInitials($comment['full_name']);
                $time_ago = timeAgo($comment['created_at']);
                $comment_rating = $comment['rating'] ?? 0;
            ?>
            <div class="comment-card">
                <div class="comment-avatar"><?php echo $initials; ?></div>
                <div class="comment-body">
                    <div class="comment-header">
                        <div>
                            <div class="comment-name"><?php echo htmlspecialchars($comment['full_name']); ?></div>
                            <?php if ($comment_rating > 0): ?>
                            <div class="comment-rating">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= $comment_rating ? '-fill' : ' empty'; ?>"></i>
                                <?php endfor; ?>
                                <span class="text-muted small ms-1">(<?php echo $comment_rating; ?>/5)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <span class="comment-time"><?php echo $time_ago; ?></span>
                    </div>
                    <?php if(!empty($comment['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($comment['image_path']); ?>" class="comment-image" alt="Comment image">
                    <?php endif; ?>
                    <p class="comment-text"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right Sidebar (Desktop) -->
    <div class="sidebar-desktop">
        <!-- Action Buttons -->
        <div class="sidebar-action-card">
            <a href="edit_listing.php?id=<?php echo $listing['listing_id']; ?>" class="sidebar-action-btn edit">
                <i class="bi bi-pencil"></i> Edit Listing
            </a>
            <?php if($listing['verification_status'] == 'Unverified'): ?>
            <form method="POST" style="margin:0;">
                <button type="submit" name="request_verification" class="sidebar-action-btn verify">
                    <i class="bi bi-patch-check"></i> Request Verification
                </button>
            </form>
            <?php elseif($listing['verification_status'] == 'Pending'): ?>
            <button class="sidebar-action-btn pending" disabled style="opacity:0.7;cursor:not-allowed;flex:1;">
                <i class="bi bi-clock"></i> Verification Pending
            </button>
            <?php elseif($listing['verification_status'] == 'Verified'): ?>
            <button class="sidebar-action-btn verified" disabled>
                <i class="bi bi-patch-check-fill"></i> Verified
            </button>
            <?php endif; ?>
        </div>

        <!-- Location Card -->
        <div class="sidebar-card">
            <div style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Location</div>
            <div style="font-size:0.9rem;color:#444;margin-bottom:8px;">
                <?php echo !empty($listing['street_address']) ? htmlspecialchars($listing['street_address']) : 'Mobile service'; ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach ($all_extensions as $idx => $ext): ?>
                    <span class="tag-pill <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo $ext; ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Delivery Card -->
        <div class="sidebar-card">
            <div style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">How Customers Receive</div>
            <?php foreach ($delivery_modes as $mode): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:0.9rem;color:#444;">
                    <i class="bi bi-check-circle-fill" style="color:var(--copper);"></i>
                    <?php echo getDeliveryLabel($mode); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Payment Card -->
        <div class="sidebar-card">
            <div style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Payment</div>
            <?php foreach ($payment_options as $pay): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:0.9rem;color:#444;">
                    <i class="bi bi-check-circle-fill" style="color:var(--copper);"></i>
                    <?php echo htmlspecialchars($pay); ?>
                </div>
            <?php endforeach; ?>
            <?php if (in_array('EFT', $payment_options)): ?>
            <div style="font-size:0.8rem;color:#888;margin-top:8px;">
                <i class="bi bi-shield-check"></i> EFT details shared via messaging
            </div>
            <?php endif; ?>
        </div>

        <div class="sidebar-card">
            <div class="section-label" style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Performance</div>
            <div class="row g-2">
                <div class="col-6">
                    <div style="background:#fdfaf9;border:1px solid var(--rose-gold);border-radius:10px;padding:15px;text-align:center;">
                        <div style="font-size:1.5rem;font-weight:bold;color:var(--plum);"><?php echo $listing['page_views']; ?></div>
                        <div class="small text-muted">Page Views</div>
                    </div>
                </div>
                <div class="col-6">
                    <div style="background:#fdfaf9;border:1px solid var(--rose-gold);border-radius:10px;padding:15px;text-align:center;">
                        <div style="font-size:1.5rem;font-weight:bold;color:var(--plum);">&#9733; <?php echo $avg_rating; ?></div>
                        <div class="small text-muted">Avg. Rating</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sidebar-card" style="background:#e3f2fd;border:none;color:#0d47a1;">
            <strong>Verification Tip:</strong> 4+ star ratings, consistent views, and positive feedback improve chances of getting verified!
        </div>

        <div class="sidebar-card" style="border:2px solid #f8d7da;background:#fffafa;">
            <div style="color:#d9534f;font-weight:bold;margin-bottom:10px;">Danger Zone</div>
            <p style="color:#666;font-size:0.9rem;margin-bottom:15px;">
                Deleting your listing is permanent. All data including comments, ratings, and verification history will be removed. This action cannot be undone.
            </p>
            <button class="btn-delete-listing" data-bs-toggle="modal" data-bs-target="#deleteModal">
                Delete This Listing
            </button>
        </div>
    </div>
</div>

<!-- Mobile Content -->
<div class="d-lg-none">
    <!-- Info -->
    <div class="info-card">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="type-badge"><?php echo $type_label; ?></span>
            <span class="status-badge <?php echo $statusClass; ?>">
                <?php echo $listing['verification_status']; ?>
            </span>
        </div>
        <h1 class="listing-title"><?php echo htmlspecialchars($listing['listing_name']); ?></h1>
        <p class="listing-meta"><?php echo htmlspecialchars($listing['category']); ?> &bull; 
            <?php 
            if ($listing['listing_type'] == 'both') {
                echo htmlspecialchars($listing['service_type']) . ' / ' . htmlspecialchars($listing['product_type']);
            } elseif ($listing['listing_type'] == 'product') {
                echo htmlspecialchars($listing['product_type']);
            } else {
                echo htmlspecialchars($listing['service_type']);
            }
            ?>
        </p>
        <?php if($listing['verification_status'] == 'Pending'): ?>
        <div class="pending-notice mt-2" style="font-size:0.8rem;padding:8px 12px;">
            <strong>Under Review:</strong> Your verification request is being reviewed.
        </div>
        <?php elseif($listing['verification_status'] == 'Verified'): ?>
        <div class="verified-notice mt-2" style="font-size:0.8rem;padding:8px 12px;">
            <strong>Verified:</strong> Your listing displays a verified badge to customers.
        </div>
        <?php endif; ?>
    </div>

    <!-- Price -->
    <div class="price-section" style="background:white;padding:0 16px 16px;">
        <div style="font-size:1.6rem;font-weight:700;color:var(--plum);"><?php echo htmlspecialchars($listing['price_description']); ?></div>
    </div>

    <!-- Description -->
    <div class="desc-section" style="margin:8px 16px;border-radius:16px;">
        <div class="section-label" style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Description</div>
        <p class="desc-text"><?php echo nl2br(htmlspecialchars($listing['description'])); ?></p>
    </div>

    <!-- Location Bubble Card -->
    <div class="mobile-bubble-card">
        <div class="section-label">Location</div>
        <div class="detail-value">
            <?php echo !empty($listing['street_address']) ? htmlspecialchars($listing['street_address']) : 'Mobile service'; ?>
        </div>
        <div class="ext-list">
            <?php foreach ($all_extensions as $idx => $ext): ?>
                <span class="tag-pill <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo $ext; ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Delivery Bubble Card -->
    <div class="mobile-bubble-card">
        <div class="section-label">How Customers Receive</div>
        <?php foreach ($delivery_modes as $mode): ?>
            <div class="check-item">
                <i class="bi bi-check-circle-fill" style="color:var(--copper);"></i>
                <?php echo getDeliveryLabel($mode); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Payment Bubble Card -->
    <div class="mobile-bubble-card">
        <div class="section-label">Payment</div>
        <?php foreach ($payment_options as $pay): ?>
            <div class="check-item">
                <i class="bi bi-check-circle-fill" style="color:var(--copper);"></i>
                <?php echo htmlspecialchars($pay); ?>
            </div>
        <?php endforeach; ?>
        <?php if (in_array('EFT', $payment_options)): ?>
        <div class="eft-note">
            <i class="bi bi-shield-check"></i> EFT details shared via messaging for privacy
        </div>
        <?php endif; ?>
    </div>

    <!-- Comments / Feedback (Read-only for owner) -->
    <div class="comments-section" style="margin:8px 16px;border-radius:16px;">
    <div style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">User Comments & Feedback</div>
        <?php if (count($comments) > 0): ?>
        <div class="avg-rating-display">
            <div class="avg-rating-number"><?php echo $avg_rating; ?></div>
            <div class="avg-rating-stars">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="bi bi-star<?php echo $i <= round($avg_rating) ? '-fill' : ''; ?>"></i>
                <?php endfor; ?>
            </div>
            <div class="avg-rating-text">Based on <?php echo count($comments); ?> review(s)</div>
        </div>
        <?php endif; ?>

        <?php foreach($comments as $comment): 
            $initials = getInitials($comment['full_name']);
            $time_ago = timeAgo($comment['created_at']);
            $comment_rating = $comment['rating'] ?? 0;
        ?>
        <div class="comment-card">
            <div class="comment-avatar"><?php echo $initials; ?></div>
            <div class="comment-body">
                <div class="comment-header">
                    <div>
                        <div class="comment-name"><?php echo htmlspecialchars($comment['full_name']); ?></div>
                        <?php if ($comment_rating > 0): ?>
                        <div class="comment-rating">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi bi-star<?php echo $i <= $comment_rating ? '-fill' : ' empty'; ?>"></i>
                            <?php endfor; ?>
                            <span class="text-muted small ms-1">(<?php echo $comment_rating; ?>/5)</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="comment-time"><?php echo $time_ago; ?></span>
                </div>
                <?php if(!empty($comment['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($comment['image_path']); ?>" class="comment-image" alt="Comment image">
                <?php endif; ?>
                <p class="comment-text"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Danger Zone (Mobile) -->
    <div class="mobile-bubble-card" style="border:2px solid #f8d7da;background:#fffafa;">
        <div style="color:#d9534f;font-weight:bold;margin-bottom:10px;">Danger Zone</div>
        <p style="color:#666;font-size:0.9rem;margin-bottom:15px;">
            Deleting your listing is permanent. All data including comments, ratings, and verification history will be removed.
        </p>
        <button class="btn-delete-listing" data-bs-toggle="modal" data-bs-target="#deleteModal">
            Delete This Listing
        </button>
    </div>
</div>

<!-- Mobile Sticky Actions Bar -->
<div class="sticky-cta d-lg-none">
    <a href="edit_listing.php?id=<?php echo $listing['listing_id']; ?>" class="btn-cta-secondary" style="text-decoration:none;">
        <i class="bi bi-pencil"></i>
    </a>
    <?php if($listing['verification_status'] == 'Unverified'): ?>
    <form method="POST" style="display:inline;flex:1;">
        <button type="submit" name="request_verification" class="btn-cta-primary" style="width:100%;">
            <i class="bi bi-patch-check"></i> Request Verification
        </button>
    </form>
    <?php elseif($listing['verification_status'] == 'Pending'): ?>
    <button class="btn-cta-primary" disabled style="opacity:0.7;cursor:not-allowed;flex:1;">
        <i class="bi bi-clock"></i> Pending
    </button>
    <?php elseif($listing['verification_status'] == 'Verified'): ?>
    <button class="btn-cta-primary" disabled style="opacity:0.7;cursor:not-allowed;background:#28a745;flex:1;">
        <i class="bi bi-patch-check-fill"></i> Verified
    </button>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="border-bottom: 2px solid #f8d7da;">
                <h5 class="modal-title fw-bold" style="color: #d9534f;">Delete Listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <p class="text-secondary">
                    Are you sure you want to delete <strong style="color: var(--plum);">"<?php echo htmlspecialchars($listing['listing_name']); ?>"</strong>?
                </p>
                <ul class="text-muted small mb-0">
                    <li>All listing data will be permanently removed</li>
                    <li>All comments and ratings will be deleted</li>
                    <li>Verification history will be cleared</li>
                    <li>This action <strong>cannot</strong> be undone</li>
                </ul>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f0f0f0;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="delete_listing" value="1">
                    <button type="submit" class="btn btn-danger" style="border-radius: 8px; background: #d9534f; border: none;">
                        Yes, Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightbox(event)"><i class="bi bi-x-lg"></i></button>
    <button class="lightbox-nav lightbox-prev" onclick="changeImage(-1, event)"><i class="bi bi-chevron-left"></i></button>
    <img src="" class="lightbox-img" id="lightboxImg" onclick="event.stopPropagation()">
    <button class="lightbox-nav lightbox-next" onclick="changeImage(1, event)"><i class="bi bi-chevron-right"></i></button>
    <div class="lightbox-counter" id="lightboxCounter">1 / 5</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== GALLERY DATA =====
const galleryImages = <?php echo json_encode(array_column($gallery_images, 'image_path')); ?>;

// ===== MOBILE GALLERY =====
let currentMobileIndex = 0;

function setHeroMobile(index) {
    if (index < 0 || index >= galleryImages.length) return;
    currentMobileIndex = index;
    const img = document.getElementById('heroImgMobile');
    if (img) img.src = galleryImages[index];

    const strip = document.getElementById('mobileThumbStrip');
    if (strip) {
        strip.querySelectorAll('.thumb-item').forEach((thumb, i) => {
            thumb.classList.toggle('active', i === index);
        });
    }
}

function changeHeroMobile(dir) {
    let newIndex = currentMobileIndex + dir;
    if (newIndex < 0) newIndex = galleryImages.length - 1;
    if (newIndex >= galleryImages.length) newIndex = 0;
    setHeroMobile(newIndex);
}

// ===== DESKTOP GALLERY =====
let currentDesktopIndex = 0;

function setHeroDesktop(index) {
    if (index < 0 || index >= galleryImages.length) return;
    currentDesktopIndex = index;
    const img = document.getElementById('heroImgDesktop');
    if (img) img.src = galleryImages[index];

    const strip = document.getElementById('desktopThumbStrip');
    if (strip) {
        strip.querySelectorAll('.thumb-item').forEach((thumb, i) => {
            thumb.classList.toggle('active', i === index);
        });
    }
}

function changeHeroDesktop(dir) {
    let newIndex = currentDesktopIndex + dir;
    if (newIndex < 0) newIndex = galleryImages.length - 1;
    if (newIndex >= galleryImages.length) newIndex = 0;
    setHeroDesktop(newIndex);
}

// ===== LIGHTBOX =====
let currentLightboxIndex = 0;

function openLightbox(index) {
    if (galleryImages.length === 0) return;
    currentLightboxIndex = index;
    document.getElementById('lightboxImg').src = galleryImages[index];
    document.getElementById('lightboxCounter').textContent = (index + 1) + ' / ' + galleryImages.length;
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox(e) {
    // Always close if clicking the close button itself
    if (e && (e.target.closest('.lightbox-close') || e.target.classList.contains('lightbox-close'))) {
        document.getElementById('lightbox').classList.remove('active');
        document.body.style.overflow = '';
        return;
    }
    // Close if clicking the overlay background (not the image or nav buttons)
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}

function changeImage(dir, e) {
    if (e) e.stopPropagation();
    currentLightboxIndex += dir;
    if (currentLightboxIndex < 0) currentLightboxIndex = galleryImages.length - 1;
    if (currentLightboxIndex >= galleryImages.length) currentLightboxIndex = 0;
    document.getElementById('lightboxImg').src = galleryImages[currentLightboxIndex];
    document.getElementById('lightboxCounter').textContent = (currentLightboxIndex + 1) + ' / ' + galleryImages.length;
}

// Keyboard nav
document.addEventListener('keydown', (e) => {
    const lightbox = document.getElementById('lightbox');
    if (!lightbox.classList.contains('active')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') changeImage(-1);
    if (e.key === 'ArrowRight') changeImage(1);
});

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('Service Worker registered:', registration.scope);
            })
            .catch((error) => {
                console.log('Service Worker registration failed:', error);
            });
    });
}
</script>
</body>
</html>