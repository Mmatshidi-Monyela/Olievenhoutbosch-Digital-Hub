<?php
session_start();
include 'includes/db_connect.php';

/**@var mysqli $conn */

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle session messages
$alert_html = '';
if (isset($_SESSION['success_msg'])) {
    $alert_html = '<div class="alert alert-success alert-dismissible fade show mx-3 mt-3 shadow-sm" role="alert">'
                . htmlspecialchars($_SESSION['success_msg'])
                . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['success_msg']);
} elseif (isset($_SESSION['error_msg'])) {
    $alert_html = '<div class="alert alert-danger alert-dismissible fade show mx-3 mt-3 shadow-sm" role="alert">'
                . htmlspecialchars($_SESSION['error_msg'])
                . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['error_msg']);
}

$listing_id = $_GET['id'] ?? 0;

if ($listing_id == 0) {
    header("Location: main.php");
    exit();
}

// Fetch listing from database
$stmt = mysqli_prepare($conn, "SELECT l.*, u.full_name as owner_name, u.user_id as owner_id, u.created_at as member_since
    FROM listing l 
    JOIN UserAccount u ON l.user_id = u.user_id 
    WHERE l.listing_id = ? AND l.is_active = 1");
mysqli_stmt_bind_param($stmt, "i", $listing_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$service = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$service) {
    header("Location: main.php");
    exit();
}

// Determine if current user is the owner
$current_user_id = $_SESSION['user_id'] ?? 0;
$is_owner = ($current_user_id == $service['owner_id']);

// Build all extensions array
$all_extensions = [$service['extension']];
if (!empty($service['service_extensions'])) {
    $additional = explode(',', $service['service_extensions']);
    $all_extensions = array_merge($all_extensions, $additional);
}

// Fetch gallery images
$gallery_images = [];
$gal_stmt = mysqli_prepare($conn, "SELECT image_id, image_path FROM listing_images WHERE listing_id = ? ORDER BY uploaded_at ASC");
mysqli_stmt_bind_param($gal_stmt, "i", $listing_id);
mysqli_stmt_execute($gal_stmt);
$gal_result = mysqli_stmt_get_result($gal_stmt);
while ($g = mysqli_fetch_assoc($gal_result)) {
    $gallery_images[] = $g;
}
mysqli_stmt_close($gal_stmt);

// Main image
$main_image = $gallery_images[0]['image_path'] ?? $service['image_path'] ?? 'uploads/listings/default_listing.jpg';

// Fetch comments with user info
$comments_stmt = mysqli_prepare($conn, "SELECT c.*, u.full_name, u.user_id 
    FROM comment c 
    JOIN UserAccount u ON c.user_id = u.user_id 
    WHERE c.listing_id = ? 
    ORDER BY c.created_at DESC");
mysqli_stmt_bind_param($comments_stmt, "i", $listing_id);
mysqli_stmt_execute($comments_stmt);
$comments_result = mysqli_stmt_get_result($comments_stmt);
$comments = [];
while ($row = mysqli_fetch_assoc($comments_result)) {
    $comments[] = $row;
}
mysqli_stmt_close($comments_stmt);

// Calculate average rating
$avg_rating = 0;
$review_count = count($comments);
if ($review_count > 0) {
    $total = array_sum(array_column($comments, 'rating'));
    $avg_rating = round($total / $review_count, 1);
}

// Format contact number
$contact_number = '';
$provider_phone_stmt = mysqli_prepare($conn, "SELECT contact_number FROM useraccount WHERE user_id = ?");
mysqli_stmt_bind_param($provider_phone_stmt, "i", $service['owner_id']);
mysqli_stmt_execute($provider_phone_stmt);
$phone_result = mysqli_stmt_get_result($provider_phone_stmt);
if ($phone_row = mysqli_fetch_assoc($phone_result)) {
    $contact_number = $phone_row['contact_number'] ?? '';
}
mysqli_stmt_close($provider_phone_stmt);

$digits_only = preg_replace('/[^0-9]/', '', $contact_number);
$phone_display = '';
if (strlen($digits_only) >= 10) {
    if (strpos($digits_only, '27') === 0) {
        $phone_display = '+27 ' . substr($digits_only, 2, 2) . ' ' . 
                        substr($digits_only, 4, 3) . ' ' . 
                        substr($digits_only, 7);
    } else {
        $phone_display = '0' . substr($digits_only, 0, 2) . ' ' . 
                        substr($digits_only, 2, 3) . ' ' . 
                        substr($digits_only, 5);
    }
}
$phone_link = 'tel:' . $digits_only;

// Payment options
$payment_options = [];
if (!empty($service['payment_options'])) {
    $payment_options = array_map('trim', explode(',', $service['payment_options']));
}

// Delivery modes
$delivery_modes = [];
if (!empty($service['delivery_mode'])) {
    $delivery_modes = array_map('trim', explode(',', $service['delivery_mode']));
}

// Listing type label
$type_label = 'Service';
if ($service['listing_type'] == 'product') $type_label = 'Goods';
if ($service['listing_type'] == 'both') $type_label = 'Service & Goods';

// Build category display string based on listing type
$category_parts = [htmlspecialchars($service['category'])];
if ($service['listing_type'] == 'product' && !empty($service['product_type'])) {
    $category_parts[] = htmlspecialchars($service['product_type']);
} elseif ($service['listing_type'] == 'both') {
    if (!empty($service['service_type'])) {
        $category_parts[] = htmlspecialchars($service['service_type']);
    }
    if (!empty($service['product_type'])) {
        $category_parts[] = htmlspecialchars($service['product_type']);
    }
} elseif ($service['listing_type'] == 'service' && !empty($service['service_type'])) {
    $category_parts[] = htmlspecialchars($service['service_type']);
}
$category_display = implode(' &bull; ', $category_parts);

// Track view
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$viewer_id = $_SESSION['user_id'] ?? null;
$view_stmt = mysqli_prepare($conn, "INSERT INTO ListingView (listing_id, viewer_ip, viewer_user_id) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($view_stmt, "isi", $listing_id, $ip, $viewer_id);
mysqli_stmt_execute($view_stmt);
mysqli_stmt_close($view_stmt);

$update_stmt = mysqli_prepare($conn, "UPDATE listing SET page_views = page_views + 1 WHERE listing_id = ?");
mysqli_stmt_bind_param($update_stmt, "i", $listing_id);
mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);

// Member since
$member_since = '';
if (!empty($service['member_since'])) {
    $member_since = date('F Y', strtotime($service['member_since']));
}

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
    <title><?php echo htmlspecialchars($service['listing_name']); ?> - Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            padding: 0 16px;
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
            flex: 1;
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

        /* CHANGED: Mobile navbar adjustments */
        @media (max-width: 575.98px) {
            .navbar-custom {
                height: 52px;
                padding: 0 12px;
            }
            .brand-text {
                font-size: 0.85rem;
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

        /* Thumbnail strip */
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
        .verified-badge {
            background: var(--rose-gold);
            color: var(--plum);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
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

        /* ===== PRICE ===== */
        .price-section {
            background: white;
            padding: 0 16px 16px;
        }
        .price-tag {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--plum);
        }
        .price-note {
            font-size: 0.8rem;
            color: #888;
            margin-top: 2px;
        }

        /* ===== SELLER AVATAR ===== */
        .seller-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--rose-gold);
            color: var(--plum);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
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

        /* ===== COMMENTS / FEEDBACK SECTION ===== */
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

        /* Average rating display */
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

        /* Comment form */
        .comment-form {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .comment-form h6 {
            font-weight: 700;
            margin-bottom: 12px;
        }

        /* Star rating input */
        .star-rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
            margin-bottom: 12px;
        }
        .star-rating-input input {
            display: none;
        }
        .star-rating-input label {
            cursor: pointer;
            font-size: 1.8rem;
            color: #ddd;
            transition: color 0.2s, transform 0.15s;
        }
        .star-rating-input label:hover,
        .star-rating-input label:hover ~ label,
        .star-rating-input input:checked ~ label {
            color: #ffc107;
        }
        .star-rating-input label:hover {
            transform: scale(1.1);
        }

        /* Comment image preview */
        .comment-image-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 10px;
            margin-top: 10px;
            display: none;
        }

        /* Comment card */
        .comment-card {
            display: flex;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
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

        /* Comment delete button — tiny copper bin bottom-right of comment card */
        .comment-delete-btn {
            position: absolute;
            bottom: 8px;
            right: 0;
            background: none;
            border: none;
            color: var(--copper);
            font-size: 0.75rem;
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            transition: color 0.2s, background 0.2s, opacity 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            opacity: 0.6;
            z-index: 2;
        }
        .comment-delete-btn:hover {
            color: #dc3545;
            background: #fdf2f2;
            opacity: 1;
        }
        .comment-delete-btn i {
            font-size: 0.8rem;
        }

        /* ===== OWNER READ-ONLY COMMENT STYLES (matching listing_details_owner.php) ===== */
        .owner-comment-box {
            border-left: 4px solid var(--rose-gold);
            padding-left: 15px;
            margin-bottom: 20px;
        }
        .owner-comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--rose-gold);
            color: var(--plum);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-right: 12px;
        }
        .owner-comment-rating {
            color: #ffc107;
            font-size: 0.85rem;
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
        .mobile-bubble-card .eft-note {
            font-size: 0.8rem;
            color: #888;
            margin-top: 8px;
        }
        .mobile-bubble-card .detail-value {
            font-size: 0.9rem;
            color: #444;
            margin-bottom: 8px;
        }

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
            .info-card, .price-section, .desc-section, .comments-section {
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
            .sidebar-price {
                font-size: 1.8rem;
                font-weight: 700;
                color: var(--plum);
                margin-bottom: 4px;
            }
            .sidebar-btn {
                width: 100%;
                padding: 14px;
                border-radius: 10px;
                font-weight: 700;
                font-size: 1rem;
                margin-bottom: 10px;
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                text-decoration: none;
            }
            .sidebar-btn-primary {
                background: var(--plum);
                color: white;
            }
            .sidebar-btn-primary:hover { background: #3a065e; color: white; }
            .sidebar-btn-secondary {
                background: var(--rose-gold);
                color: var(--plum);
            }
            .sidebar-btn-secondary:hover { background: #f0b8ad; color: var(--plum); }
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
            pointer-events: auto;
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

        /* Alert */
        .alert {
            border-radius: 12px;
            border: none;
            font-size: 0.9rem;
        }
        .alert-success {
            background: #f0f7f0;
            color: #2d5a2d;
        }
        .alert-danger {
            background: #fdf2f2;
            color: #8b3a3a;
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
    <div class="container-fluid d-flex align-items-center justify-content-between" style="width:100%;padding:0 16px;">
        <a href="index.php" class="navbar-brand" style="text-decoration:none;">
            <img src="images/logo.png" width="28" height="28" alt="logo" style="flex-shrink:0;">
            <span class="brand-text">Olievenhoutbosch Digital Hub</span>
        </a>
        <a href="main.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            <span class="d-none d-sm-inline">Back</span>
        </a>
    </div>
</nav>

<?php echo $alert_html; ?>

<!-- Mobile: Hero Photo -->
<div class="d-lg-none">
    <div class="photo-hero" id="mobileHero" onclick="openLightbox(currentMobileIndex)" style="cursor:pointer;">
        <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($service['listing_name']); ?>" id="heroImgMobile">
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
            <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($service['listing_name']); ?>" id="heroImgDesktop" style="cursor:pointer;" onclick="openLightbox(currentDesktopIndex)">
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

        <!-- Info -->
        <div class="info-card" style="border-radius:16px;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="type-badge"><?php echo $type_label; ?></span>
                <span class="status-badge <?php 
                    if($service['verification_status'] == 'Verified') echo 'status-verified';
                    elseif($service['verification_status'] == 'Pending') echo 'status-pending';
                    else echo 'status-unverified';
                ?>">
                    <?php echo $service['verification_status']; ?>
                </span>
            </div>
            <h1 class="listing-title"><?php echo htmlspecialchars($service['listing_name']); ?></h1>
            <p class="listing-meta"><?php echo $category_display; ?></p>
        </div>

        <!-- Description -->
        <div class="desc-section" style="border-radius:16px;">
            <div class="section-label" style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Description</div>
            <p class="desc-text"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
        </div>

        <!-- Comments / Feedback -->
        <div class="comments-section" style="border-radius:16px;">
            <div class="comments-title">Community Feedback</div>

            <!-- Average Rating -->
            <div class="avg-rating-display">
                <div class="avg-rating-number"><?php echo $avg_rating; ?></div>
                <div class="avg-rating-stars">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="bi bi-star<?php echo $i <= round($avg_rating) ? '-fill' : ''; ?>"></i>
                    <?php endfor; ?>
                </div>
                <div class="avg-rating-text">Based on <?php echo $review_count; ?> review(s)</div>
            </div>

            <?php if ($is_owner): ?>
            <!-- OWNER VIEW: Read-only comments, no form -->
            <div class="owner-readonly-notice">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Owner View:</strong> You cannot comment on your own listing. Below is what customers see.
            </div>
            <?php else: ?>
            <!-- NON-OWNER VIEW: Full comment form (Both users can comment here) -->
            <div class="comment-form">
                <h6>Leave your feedback</h6>
                <form action="add_comment.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="listing_id" value="<?php echo (int)$listing_id; ?>">

                    <div class="mb-3">
                        <label class="form-label small text-muted mb-2">Your Rating</label>
                        <div class="star-rating-input">
                            <input type="radio" id="star5" name="rating" value="5" required>
                            <label for="star5"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star4" name="rating" value="4">
                            <label for="star4"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star3" name="rating" value="3">
                            <label for="star3"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star2" name="rating" value="2">
                            <label for="star2"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star1" name="rating" value="1">
                            <label for="star1"><i class="bi bi-star-fill"></i></label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <textarea name="comment_text" class="form-control" rows="3" placeholder="Share your experience..." required style="border-radius:10px;resize:none;"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Attach an image (optional)</label>
                        <input type="file" name="comment_image" class="form-control" accept="image/*" onchange="previewImage(this)" style="font-size:0.85rem;">
                        <img id="imagePreview" class="comment-image-preview" alt="Preview">
                    </div>

                    <button type="submit" class="btn-cta-primary" style="border-radius:10px;">Post Comment</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Existing Comments -->
            <?php foreach($comments as $comment): 
                $initials = getInitials($comment['full_name']);
                $time_ago = timeAgo($comment['created_at']);
                $can_delete = ($current_user_id == $comment['user_id']);
            ?>
            <div class="comment-card" id="comment-<?php echo (int)$comment['comment_id']; ?>">
                <?php if ($can_delete): ?>
                <button type="button" class="comment-delete-btn" onclick="deleteComment(<?php echo (int)$comment['comment_id']; ?>)" title="Delete your comment">
                    <i class="bi bi-trash3"></i> Delete
                </button>
                <?php endif; ?>
                <div class="comment-avatar"><?php echo $initials; ?></div>
                <div class="comment-body">
                    <div class="comment-header">
                        <div>
                            <div class="comment-name"><?php echo htmlspecialchars($comment['full_name']); ?></div>
                            <div class="comment-rating">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= $comment['rating'] ? '-fill' : ' empty'; ?>"></i>
                                <?php endfor; ?>
                            </div>
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
        <div class="sidebar-card">
            <div class="sidebar-price"><?php echo htmlspecialchars($service['price_description']); ?></div>

            <?php if (!$is_owner): ?>
            <?php if (!empty($phone_link)): ?>
            <a href="<?php echo $phone_link; ?>" class="sidebar-btn sidebar-btn-primary">
                <i class="bi bi-telephone"></i> Call Seller
            </a>
            <?php endif; ?>
            <button type="button" class="sidebar-btn sidebar-btn-secondary" data-bs-toggle="modal" data-bs-target="#messageModal">
                <i class="bi bi-chat-dots"></i> Message
            </button>
            <?php endif; ?>
        </div>

        <div class="sidebar-card">
            <div class="section-label" style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Seller</div>
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="seller-avatar" style="width:48px;height:48px;">
                    <?php echo getInitials($service['owner_name']); ?>
                </div>
                <div style="min-width:0;">
                    <div style="font-weight:600;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($service['owner_name']); ?></div>
                    <div style="font-size:0.8rem;color:#888;">
                        Member since <?php echo $member_since; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="sidebar-card">
            <div class="section-label" style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Location</div>
            <div style="font-size:0.9rem;color:#444;margin-bottom:8px;">
                <?php echo !empty($service['street_address']) ? htmlspecialchars($service['street_address']) : 'Mobile service'; ?>
            </div>
            <div class="ext-list">
                <?php foreach ($all_extensions as $idx => $ext): ?>
                    <span class="tag-pill <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo $ext; ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sidebar-card">
            <div class="section-label" style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Delivery</div>
            <?php foreach ($delivery_modes as $mode): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:0.9rem;">
                    <i class="bi bi-check-circle-fill" style="color:var(--copper);"></i>
                    <?php echo getDeliveryLabel($mode); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-card">
            <div class="section-label" style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Payment</div>
            <?php foreach ($payment_options as $pay): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:0.9rem;">
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
    </div>
</div>

<!-- Mobile Content -->
<div class="d-lg-none">
    <!-- Info -->
    <div class="info-card">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="type-badge"><?php echo $type_label; ?></span>
            <span class="status-badge <?php 
                if($service['verification_status'] == 'Verified') echo 'status-verified';
                elseif($service['verification_status'] == 'Pending') echo 'status-pending';
                else echo 'status-unverified';
            ?>">
                <?php echo $service['verification_status']; ?>
            </span>
        </div>
        <h1 class="listing-title"><?php echo htmlspecialchars($service['listing_name']); ?></h1>
        <p class="listing-meta"><?php echo $category_display; ?></p>
    </div>

    <!-- Price -->
    <div class="price-section">
        <div class="price-tag"><?php echo htmlspecialchars($service['price_description']); ?></div>
        <div class="price-note"><?php echo $type_label; ?></div>
    </div>

    <!-- Seller Bubble Card -->
    <div class="mobile-bubble-card">
        <div class="section-label">Seller</div>
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="seller-avatar" style="width:48px;height:48px;">
                <?php echo getInitials($service['owner_name']); ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($service['owner_name']); ?></div>
                <div style="font-size:0.8rem;color:#888;">
                    Member since <?php echo $member_since; ?>
                </div>
            </div>
        </div>
    </div>

        <!-- Description -->
    <div class="mobile-bubble-card">
        <div class="section-label">Description</div>
        <p class="desc-text" style="margin:0;"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
    </div>

<!-- Location Bubble Card -->
    <div class="mobile-bubble-card">
        <div class="section-label">Location</div>
        <div class="detail-value">
            <?php echo !empty($service['street_address']) ? htmlspecialchars($service['street_address']) : 'Mobile service'; ?>
        </div>
        <div class="ext-list">
            <?php foreach ($all_extensions as $idx => $ext): ?>
                <span class="tag-pill <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo $ext; ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Delivery Bubble Card -->
    <div class="mobile-bubble-card">
        <div class="section-label">Delivery</div>
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

    <!-- Comments / Feedback -->
    <div class="comments-section" style="margin:8px 16px;border-radius:16px;">
        <div class="comments-title" style="font-size:1rem;font-weight:700;color:var(--plum);margin-bottom:16px;">Community Feedback</div>
        <!-- Average Rating -->
        <div class="avg-rating-display">
            <div class="avg-rating-number"><?php echo $avg_rating; ?></div>
            <div class="avg-rating-stars">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="bi bi-star<?php echo $i <= round($avg_rating) ? '-fill' : ''; ?>"></i>
                <?php endfor; ?>
            </div>
            <div class="avg-rating-text">Based on <?php echo $review_count; ?> review(s)</div>
        </div>

        <?php if ($is_owner): ?>
        <!-- OWNER VIEW: Read-only comments, no form -->
        <div class="owner-readonly-notice">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Owner View:</strong> You cannot comment on your own listing.
        </div>
        <?php else: ?>
        <!-- NON-OWNER VIEW: Full comment form -->
        <div class="comment-form">
            <h6>Leave your feedback</h6>
            <form action="add_comment.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="listing_id" value="<?php echo (int)$listing_id; ?>">

                <div class="mb-3">
                    <label class="form-label small text-muted mb-2">Your Rating</label>
                    <div class="star-rating-input">
                        <input type="radio" id="mstar5" name="rating" value="5" required>
                        <label for="mstar5"><i class="bi bi-star-fill"></i></label>
                        <input type="radio" id="mstar4" name="rating" value="4">
                        <label for="mstar4"><i class="bi bi-star-fill"></i></label>
                        <input type="radio" id="mstar3" name="rating" value="3">
                        <label for="mstar3"><i class="bi bi-star-fill"></i></label>
                        <input type="radio" id="mstar2" name="rating" value="2">
                        <label for="mstar2"><i class="bi bi-star-fill"></i></label>
                        <input type="radio" id="mstar1" name="rating" value="1">
                        <label for="mstar1"><i class="bi bi-star-fill"></i></label>
                    </div>
                </div>

                <div class="mb-3">
                    <textarea name="comment_text" class="form-control" rows="3" placeholder="Share your experience..." required style="border-radius:10px;resize:none;"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">Attach an image (optional)</label>
                    <input type="file" name="comment_image" class="form-control" accept="image/*" onchange="previewImage(this)" style="font-size:0.85rem;">
                    <img id="imagePreviewMobile" class="comment-image-preview" alt="Preview">
                </div>

                <button type="submit" class="btn-cta-primary" style="border-radius:10px;">Post Comment</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Existing Comments -->
        <?php foreach($comments as $comment): 
            $initials = getInitials($comment['full_name']);
            $time_ago = timeAgo($comment['created_at']);
            $can_delete = ($current_user_id == $comment['user_id']);
        ?>
        <div class="comment-card" id="comment-<?php echo (int)$comment['comment_id']; ?>">
            <?php if ($can_delete): ?>
            <button type="button" class="comment-delete-btn" onclick="deleteComment(<?php echo (int)$comment['comment_id']; ?>)" title="Delete your comment">
                <i class="bi bi-trash3"></i> Delete
            </button>
            <?php endif; ?>
            <div class="comment-avatar"><?php echo $initials; ?></div>
            <div class="comment-body">
                <div class="comment-header">
                    <div>
                        <div class="comment-name"><?php echo htmlspecialchars($comment['full_name']); ?></div>
                        <div class="comment-rating">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi bi-star<?php echo $i <= $comment['rating'] ? '-fill' : ' empty'; ?>"></i>
                            <?php endfor; ?>
                        </div>
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

<!-- Mobile Sticky CTA -->
<?php if (!$is_owner): ?>
<div class="sticky-cta d-lg-none">
    <?php if (!empty($phone_link)): ?>
    <a href="<?php echo $phone_link; ?>" class="btn-cta-secondary">
        <i class="bi bi-telephone"></i>
    </a>
    <?php endif; ?>
    <button type="button" class="btn-cta-primary" data-bs-toggle="modal" data-bs-target="#messageModal">
        <i class="bi bi-chat-dots"></i> Message Seller
    </button>
</div>
<?php endif; ?>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightbox(event)"><i class="bi bi-x-lg"></i></button>
    <button class="lightbox-nav lightbox-prev" onclick="changeImage(-1, event)"><i class="bi bi-chevron-left"></i></button>
    <img src="" class="lightbox-img" id="lightboxImg" onclick="event.stopPropagation()">
    <button class="lightbox-nav lightbox-next" onclick="changeImage(1, event)"><i class="bi bi-chevron-right"></i></button>
    <div class="lightbox-counter" id="lightboxCounter">1 / 5</div>
</div>

<?php if (!$is_owner): ?>
<!-- Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header" style="background:var(--plum);border-radius:16px 16px 0 0;">
                <h5 class="modal-title fw-bold text-white">Message <?php echo htmlspecialchars($service['listing_name']); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3"><?php echo $category_display; ?></p>
                <form action="send_message.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="listing_id" value="<?php echo (int)$listing_id; ?>">
                    <input type="hidden" name="receiver_id" value="<?php echo (int)$service['owner_id']; ?>">
                    <textarea name="message" class="form-control" rows="4" placeholder="Hi, I'm interested in your listing..." required style="border-radius:10px;resize:none;margin-bottom:12px;"></textarea>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="modal" style="border-radius:10px;">Cancel</button>
                        <button type="submit" class="btn flex-grow-1 text-white" style="background:var(--plum);border-radius:10px;font-weight:600;">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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

// ===== COMMENT IMAGE PREVIEW =====
function previewImage(input) {
    var preview = document.getElementById('imagePreview') || document.getElementById('imagePreviewMobile');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// ===== DELETE COMMENT =====
function deleteComment(commentId) {
    if (!confirm('Are you sure you want to delete your comment? This cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('comment_id', commentId);
    formData.append('csrf_token', '<?php echo htmlspecialchars($csrf_token); ?>');

    fetch('delete_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const commentEl = document.getElementById('comment-' + commentId);
            if (commentEl) {
                commentEl.style.transition = 'opacity 0.3s, transform 0.3s';
                commentEl.style.opacity = '0';
                commentEl.style.transform = 'translateX(-20px)';
                setTimeout(() => commentEl.remove(), 300);
            }
            // Optionally update average rating display here if you want live updates
        } else {
            alert(data.message || 'Failed to delete comment. Please try again.');
        }
    })
    .catch(err => {
        console.error('Delete error:', err);
        alert('Something went wrong. Please try again.');
    });
}

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(r => console.log('SW registered:', r.scope))
            .catch(e => console.log('SW failed:', e));
    });
}
</script>
</body>
</html>