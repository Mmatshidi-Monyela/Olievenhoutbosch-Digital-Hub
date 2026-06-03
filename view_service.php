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

// Count seller's other listings
$seller_listings_count = 0;
$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM listing WHERE user_id = ? AND listing_id != ? AND is_active = 1");
mysqli_stmt_bind_param($count_stmt, "ii", $service['owner_id'], $listing_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
if ($count_row = mysqli_fetch_assoc($count_result)) {
    $seller_listings_count = $count_row['cnt'];
}
mysqli_stmt_close($count_stmt);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($service['listing_name']); ?> - Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #f8c9c0;
            --copper: #ba745f;
            --light-grey: #f4f7f6;
        }

        * { -webkit-tap-highlight-color: transparent; }

        body {
            background-color: var(--light-grey);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #333;
            padding-bottom: 80px;
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
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .brand-text {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            white-space: nowrap;
        }
        .back-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .back-link:hover { color: var(--rose-gold); }

        /* ===== PHOTO GALLERY ===== */
        .photo-hero {
            position: relative;
            width: 100%;
            height: 320px;
            background: #e0e0e0;
            overflow: hidden;
        }
        .photo-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-counter {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(35, 3, 68, 0.85);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        .photo-counter:hover { background: var(--plum); }
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
        }
        .listing-meta {
            color: #888;
            font-size: 0.85rem;
            margin-bottom: 12px;
        }
        .type-badge {
            background: var(--plum);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
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
        }

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

        /* ===== SELLER CARD ===== */
        .seller-card {
            background: white;
            margin: 8px 0;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
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
        .seller-info { flex: 1; }
        .seller-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: #1a1a1a;
            margin-bottom: 2px;
        }
        .seller-stats {
            font-size: 0.8rem;
            color: #888;
        }
        .seller-arrow {
            color: #ccc;
            font-size: 1.2rem;
        }

        /* ===== DETAILS SECTION ===== */
        .details-section {
            background: white;
            margin: 8px 0;
            padding: 16px;
        }
        .section-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
        }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #fdfaf9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--copper);
            font-size: 1rem;
            flex-shrink: 0;
        }
        .detail-content {
            flex: 1;
        }
        .detail-label {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 0.9rem;
            color: #1a1a1a;
            font-weight: 500;
        }

        /* Tag pills */
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
        }

        /* ===== REVIEWS ===== */
        .reviews-section {
            background: white;
            margin: 8px 0;
            padding: 16px;
        }
        .reviews-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .reviews-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1a1a1a;
        }
        .reviews-count {
            font-size: 0.85rem;
            color: #888;
        }
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        .rating-big {
            font-size: 2rem;
            font-weight: 700;
            color: var(--plum);
            line-height: 1;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 0.9rem;
        }
        .rating-stars .empty { color: #e0e0e0; }

        /* Review card */
        .review-card {
            padding: 14px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .review-card:last-child { border-bottom: none; }
        .review-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .review-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--rose-gold);
            color: var(--plum);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .review-meta { flex: 1; }
        .review-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1a1a1a;
        }
        .review-time {
            font-size: 0.75rem;
            color: #aaa;
        }
        .review-stars {
            color: #ffc107;
            font-size: 0.8rem;
        }
        .review-stars .empty { color: #e0e0e0; }
        .review-text {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.5;
            margin-top: 6px;
        }
        .review-image {
            max-height: 180px;
            border-radius: 10px;
            margin-top: 8px;
            object-fit: cover;
        }

        /* Write review button */
        .btn-write-review {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--plum);
            background: white;
            color: var(--plum);
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 16px;
            transition: all 0.2s;
        }
        .btn-write-review:hover {
            background: var(--plum);
            color: white;
        }

        /* Review form (collapsible) */
        .review-form {
            display: none;
            background: #fafafa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .review-form.active { display: block; }
        .star-rating-input {
            display: flex;
            gap: 6px;
            margin-bottom: 12px;
        }
        .star-rating-input input { display: none; }
        .star-rating-input label {
            cursor: pointer;
            font-size: 1.6rem;
            color: #e0e0e0;
            transition: color 0.15s;
        }
        .star-rating-input label:hover,
        .star-rating-input label:hover ~ label,
        .star-rating-input input:checked ~ label {
            color: #ffc107;
        }
        .review-form textarea {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            resize: none;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .review-form textarea:focus {
            outline: none;
            border-color: var(--rose-gold);
        }
        .btn-submit-review {
            width: 100%;
            padding: 12px;
            background: var(--plum);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .btn-submit-review:hover { background: #3a065e; }

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
            .photo-hero { height: 420px; border-radius: 16px; }
            .info-card, .price-section, .details-section, .desc-section, .reviews-section {
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
            background: rgba(255,255,255,0.1);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
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
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="container-fluid d-flex align-items-center justify-content-between" style="max-width:1100px;margin:0 auto;">
        <a href="index.php" class="navbar-brand">
            <img src="images/logo.png" width="28" height="28" alt="logo">
            <span class="brand-text">Olievenhoutbosch Digital Hub</span>
        </a>
        <a href="main.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</nav>

<?php echo $alert_html; ?>

<!-- Mobile: Hero Photo -->
<div class="d-lg-none">
    <div class="photo-hero" id="mobileHero">
        <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($service['listing_name']); ?>" id="heroImg">
        <?php if (count($gallery_images) > 1): ?>
        <button class="photo-nav prev" onclick="changeHero(-1)"><i class="bi bi-chevron-left"></i></button>
        <button class="photo-nav next" onclick="changeHero(1)"><i class="bi bi-chevron-right"></i></button>
        <?php endif; ?>
        <?php if (count($gallery_images) > 1): ?>
        <button class="photo-counter" onclick="openLightbox(currentHeroIndex)">
            <i class="bi bi-images"></i> <?php echo count($gallery_images); ?> photos
        </button>
        <?php endif; ?>
    </div>
    <?php if (count($gallery_images) > 1): ?>
    <div class="thumb-strip">
        <?php foreach ($gallery_images as $idx => $img): ?>
        <div class="thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="setHero(<?php echo $idx; ?>)">
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
            <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($service['listing_name']); ?>" id="desktopHeroImg" style="cursor:pointer;" onclick="openLightbox(0)">
            <?php if (count($gallery_images) > 1): ?>
            <button class="photo-nav prev" onclick="changeHero(-1)"><i class="bi bi-chevron-left"></i></button>
            <button class="photo-nav next" onclick="changeHero(1)"><i class="bi bi-chevron-right"></i></button>
            <button class="photo-counter" onclick="openLightbox(currentHeroIndex)">
                <i class="bi bi-images"></i> <?php echo count($gallery_images); ?> photos
            </button>
            <?php endif; ?>
        </div>
        <?php if (count($gallery_images) > 1): ?>
        <div class="thumb-strip" style="border-radius:0 0 16px 16px;">
            <?php foreach ($gallery_images as $idx => $img): ?>
            <div class="thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="setHero(<?php echo $idx; ?>)">
                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Info -->
        <div class="info-card" style="border-radius:16px;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="type-badge"><?php echo $type_label; ?></span>
                <?php if($service['verification_status'] == 'Verified'): ?>
                <span class="verified-badge"><i class="bi bi-patch-check-fill"></i> Verified</span>
                <?php endif; ?>
            </div>
            <h1 class="listing-title"><?php echo htmlspecialchars($service['listing_name']); ?></h1>
            <p class="listing-meta"><?php echo htmlspecialchars($service['category']); ?> &bull; <?php echo htmlspecialchars($service['service_type']); ?></p>
        </div>

        <!-- Description -->
        <div class="desc-section" style="border-radius:16px;">
            <div class="section-label">Description</div>
            <p class="desc-text"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
        </div>

        <!-- Details -->
        <div class="details-section" style="border-radius:16px;">
            <div class="section-label">Details</div>

            <div class="detail-row">
                <div class="detail-icon"><i class="bi bi-geo-alt"></i></div>
                <div class="detail-content">
                    <div class="detail-label">Location</div>
                    <div class="detail-value">
                        <?php 
                        if (!empty($service['street_address'])) {
                            echo htmlspecialchars($service['street_address']);
                        } else {
                            echo 'Mobile service - no fixed address';
                        }
                        ?>
                    </div>
                    <div class="ext-list mt-1">
                        <?php foreach ($all_extensions as $idx => $ext): ?>
                            <span class="tag-pill <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo $ext; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="detail-row">
                <div class="detail-icon"><i class="bi bi-truck"></i></div>
                <div class="detail-content">
                    <div class="detail-label">How you\'ll get it</div>
                    <div>
                        <?php foreach ($delivery_modes as $mode): ?>
                            <span class="tag-pill delivery"><?php echo getDeliveryLabel($mode); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="detail-row">
                <div class="detail-icon"><i class="bi bi-credit-card"></i></div>
                <div class="detail-content">
                    <div class="detail-label">Payment</div>
                    <div>
                        <?php foreach ($payment_options as $pay): ?>
                            <span class="tag-pill"><?php echo htmlspecialchars($pay); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (in_array('EFT', $payment_options)): ?>
                    <div class="detail-value mt-1" style="font-size:0.8rem;color:#888;">
                        <i class="bi bi-shield-check"></i> EFT details shared via messaging for privacy
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reviews -->
        <div class="reviews-section" style="border-radius:16px;">
            <div class="reviews-header">
                <span class="reviews-title">Reviews</span>
                <span class="reviews-count"><?php echo $review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?></span>
            </div>

            <?php if ($review_count > 0): ?>
            <div class="rating-summary">
                <span class="rating-big"><?php echo $avg_rating; ?></span>
                <div>
                    <div class="rating-stars">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="bi bi-star<?php echo $i <= round($avg_rating) ? '-fill' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div style="font-size:0.8rem;color:#888;">Based on <?php echo $review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?></div>
                </div>
            </div>
            <?php endif; ?>

            <button class="btn-write-review" onclick="toggleReviewForm()">
                <i class="bi bi-pencil-square"></i> Write a Review
            </button>

            <div class="review-form" id="reviewForm">
                <form action="add_comment.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="listing_id" value="<?php echo (int)$listing_id; ?>">
                    <div class="star-rating-input">
                        <input type="radio" id="star5" name="rating" value="5" required><label for="star5"><i class="bi bi-star-fill"></i></label>
                        <input type="radio" id="star4" name="rating" value="4"><label for="star4"><i class="bi bi-star-fill"></i></label>
                        <input type="radio" id="star3" name="rating" value="3"><label for="star3"><i class="bi bi-star-fill"></i></label>
                        <input type="radio" id="star2" name="rating" value="2"><label for="star2"><i class="bi bi-star-fill"></i></label>
                        <input type="radio" id="star1" name="rating" value="1"><label for="star1"><i class="bi bi-star-fill"></i></label>
                    </div>
                    <textarea name="comment_text" rows="3" placeholder="Share your experience..." required></textarea>
                    <input type="file" name="comment_image" accept="image/*" style="margin-bottom:10px;font-size:0.85rem;">
                    <button type="submit" class="btn-submit-review">Post Review</button>
                </form>
            </div>

            <?php foreach($comments as $comment): 
                $initials = getInitials($comment['full_name']);
                $time_ago = timeAgo($comment['created_at']);
            ?>
            <div class="review-card">
                <div class="review-header">
                    <div class="review-avatar"><?php echo $initials; ?></div>
                    <div class="review-meta">
                        <div class="review-name"><?php echo htmlspecialchars($comment['full_name']); ?></div>
                        <div class="review-time"><?php echo $time_ago; ?></div>
                    </div>
                    <div class="review-stars">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="bi bi-star<?php echo $i <= $comment['rating'] ? '-fill' : ' empty'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <p class="review-text"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                <?php if(!empty($comment['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($comment['image_path']); ?>" class="review-image" alt="Review photo">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right Sidebar (Desktop) -->
    <div class="sidebar-desktop">
        <div class="sidebar-card">
            <div class="sidebar-price"><?php echo htmlspecialchars($service['price_description']); ?></div>
            <div style="font-size:0.85rem;color:#888;margin-bottom:16px;">
                <?php echo $type_label; ?> &bull; <?php echo htmlspecialchars($service['category']); ?>
            </div>

            <?php if (!empty($phone_link)): ?>
            <a href="<?php echo $phone_link; ?>" class="sidebar-btn sidebar-btn-primary">
                <i class="bi bi-telephone"></i> Call Seller
            </a>
            <?php endif; ?>
            <button type="button" class="sidebar-btn sidebar-btn-secondary" data-bs-toggle="modal" data-bs-target="#messageModal">
                <i class="bi bi-chat-dots"></i> Message
            </button>
        </div>

        <div class="sidebar-card">
            <div class="section-label" style="margin-bottom:12px;">Seller</div>
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="seller-avatar" style="width:48px;height:48px;">
                    <?php echo getInitials($service['owner_name']); ?>
                </div>
                <div>
                    <div style="font-weight:600;color:#1a1a1a;"><?php echo htmlspecialchars($service['owner_name']); ?></div>
                    <div style="font-size:0.8rem;color:#888;">
                        Member since <?php echo $member_since; ?>
                    </div>
                </div>
            </div>
            <?php if ($seller_listings_count > 0): ?>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f0;">
                <a href="seller_listings.php?user_id=<?php echo $service['owner_id']; ?>" style="color:var(--copper);font-size:0.9rem;font-weight:500;text-decoration:none;">
                    <i class="bi bi-grid"></i> <?php echo $seller_listings_count; ?> other listing<?php echo $seller_listings_count != 1 ? 's' : ''; ?>
                    <i class="bi bi-chevron-right" style="float:right;"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="sidebar-card">
            <div class="section-label" style="margin-bottom:12px;">Location</div>
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
            <div class="section-label" style="margin-bottom:12px;">Delivery</div>
            <?php foreach ($delivery_modes as $mode): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:0.9rem;">
                    <i class="bi bi-check-circle-fill" style="color:var(--copper);"></i>
                    <?php echo getDeliveryLabel($mode); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-card">
            <div class="section-label" style="margin-bottom:12px;">Payment</div>
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

<!-- Mobile Content (same structure, no grid) -->
<div class="d-lg-none">
    <!-- Info -->
    <div class="info-card">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="type-badge"><?php echo $type_label; ?></span>
            <?php if($service['verification_status'] == 'Verified'): ?>
            <span class="verified-badge"><i class="bi bi-patch-check-fill"></i> Verified</span>
            <?php endif; ?>
        </div>
        <h1 class="listing-title"><?php echo htmlspecialchars($service['listing_name']); ?></h1>
        <p class="listing-meta"><?php echo htmlspecialchars($service['category']); ?> &bull; <?php echo htmlspecialchars($service['service_type']); ?></p>
    </div>

    <!-- Price -->
    <div class="price-section">
        <div class="price-tag"><?php echo htmlspecialchars($service['price_description']); ?></div>
        <div class="price-note"><?php echo $type_label; ?></div>
    </div>

    <!-- Seller Card -->
    <div class="seller-card" onclick="location.href='seller_listings.php?user_id=<?php echo $service['owner_id']; ?>'" style="cursor:pointer;">
        <div class="seller-avatar"><?php echo getInitials($service['owner_name']); ?></div>
        <div class="seller-info">
            <div class="seller-name"><?php echo htmlspecialchars($service['owner_name']); ?></div>
            <div class="seller-stats">
                Member since <?php echo $member_since; ?>
                <?php if ($seller_listings_count > 0): ?>
                &bull; <?php echo $seller_listings_count; ?> other listing<?php echo $seller_listings_count != 1 ? 's' : ''; ?>
                <?php endif; ?>
            </div>
        </div>
        <i class="bi bi-chevron-right seller-arrow"></i>
    </div>

    <!-- Details -->
    <div class="details-section">
        <div class="section-label">Details</div>

        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-geo-alt"></i></div>
            <div class="detail-content">
                <div class="detail-label">Location</div>
                <div class="detail-value">
                    <?php echo !empty($service['street_address']) ? htmlspecialchars($service['street_address']) : 'Mobile service'; ?>
                </div>
                <div class="ext-list mt-1">
                    <?php foreach ($all_extensions as $idx => $ext): ?>
                        <span class="tag-pill <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo $ext; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-truck"></i></div>
            <div class="detail-content">
                <div class="detail-label">How you\'ll get it</div>
                <div>
                    <?php foreach ($delivery_modes as $mode): ?>
                        <span class="tag-pill delivery"><?php echo getDeliveryLabel($mode); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-credit-card"></i></div>
            <div class="detail-content">
                <div class="detail-label">Payment</div>
                <div>
                    <?php foreach ($payment_options as $pay): ?>
                        <span class="tag-pill"><?php echo htmlspecialchars($pay); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if (in_array('EFT', $payment_options)): ?>
                <div class="detail-value mt-1" style="font-size:0.8rem;color:#888;">
                    <i class="bi bi-shield-check"></i> EFT details shared via messaging for privacy
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div class="desc-section">
        <div class="section-label">Description</div>
        <p class="desc-text"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
    </div>

    <!-- Reviews -->
    <div class="reviews-section">
        <div class="reviews-header">
            <span class="reviews-title">Reviews</span>
            <span class="reviews-count"><?php echo $review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?></span>
        </div>

        <?php if ($review_count > 0): ?>
        <div class="rating-summary">
            <span class="rating-big"><?php echo $avg_rating; ?></span>
            <div>
                <div class="rating-stars">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="bi bi-star<?php echo $i <= round($avg_rating) ? '-fill' : ''; ?>"></i>
                    <?php endfor; ?>
                </div>
                <div style="font-size:0.8rem;color:#888;">Based on <?php echo $review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <button class="btn-write-review" onclick="toggleReviewForm()">
            <i class="bi bi-pencil-square"></i> Write a Review
        </button>

        <div class="review-form" id="reviewFormMobile">
            <form action="add_comment.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="listing_id" value="<?php echo (int)$listing_id; ?>">
                <div class="star-rating-input">
                    <input type="radio" id="mstar5" name="rating" value="5" required><label for="mstar5"><i class="bi bi-star-fill"></i></label>
                    <input type="radio" id="mstar4" name="rating" value="4"><label for="mstar4"><i class="bi bi-star-fill"></i></label>
                    <input type="radio" id="mstar3" name="rating" value="3"><label for="mstar3"><i class="bi bi-star-fill"></i></label>
                    <input type="radio" id="mstar2" name="rating" value="2"><label for="mstar2"><i class="bi bi-star-fill"></i></label>
                    <input type="radio" id="mstar1" name="rating" value="1"><label for="mstar1"><i class="bi bi-star-fill"></i></label>
                </div>
                <textarea name="comment_text" rows="3" placeholder="Share your experience..." required></textarea>
                <input type="file" name="comment_image" accept="image/*" style="margin-bottom:10px;font-size:0.85rem;">
                <button type="submit" class="btn-submit-review">Post Review</button>
            </form>
        </div>

        <?php foreach($comments as $comment): 
            $initials = getInitials($comment['full_name']);
            $time_ago = timeAgo($comment['created_at']);
        ?>
        <div class="review-card">
            <div class="review-header">
                <div class="review-avatar"><?php echo $initials; ?></div>
                <div class="review-meta">
                    <div class="review-name"><?php echo htmlspecialchars($comment['full_name']); ?></div>
                    <div class="review-time"><?php echo $time_ago; ?></div>
                </div>
                <div class="review-stars">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="bi bi-star<?php echo $i <= $comment['rating'] ? '-fill' : ' empty'; ?>"></i>
                    <?php endfor; ?>
                </div>
            </div>
            <p class="review-text"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
            <?php if(!empty($comment['image_path'])): ?>
                <img src="<?php echo htmlspecialchars($comment['image_path']); ?>" class="review-image" alt="Review photo">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Mobile Sticky CTA -->
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

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox(event)">
    <button class="lightbox-close" onclick="closeLightbox(event)"><i class="bi bi-x-lg"></i></button>
    <button class="lightbox-nav lightbox-prev" onclick="changeImage(-1, event)"><i class="bi bi-chevron-left"></i></button>
    <img src="" class="lightbox-img" id="lightboxImg" onclick="event.stopPropagation()">
    <button class="lightbox-nav lightbox-next" onclick="changeImage(1, event)"><i class="bi bi-chevron-right"></i></button>
    <div class="lightbox-counter" id="lightboxCounter">1 / 5</div>
</div>

<!-- Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header" style="background:var(--plum);border-radius:16px 16px 0 0;">
                <h5 class="modal-title fw-bold text-white">Message <?php echo htmlspecialchars($service['listing_name']); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3"><?php echo htmlspecialchars($service['category']); ?> &bull; <?php echo htmlspecialchars($service['service_type']); ?></p>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Gallery data
const galleryImages = <?php echo json_encode(array_column($gallery_images, 'image_path')); ?>;
let currentHeroIndex = 0;

function setHero(index) {
    if (index < 0 || index >= galleryImages.length) return;
    currentHeroIndex = index;
    const img = document.getElementById('heroImg') || document.getElementById('desktopHeroImg');
    if (img) img.src = galleryImages[index];

    // Update thumbnails
    document.querySelectorAll('.thumb-item').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

function changeHero(dir) {
    let newIndex = currentHeroIndex + dir;
    if (newIndex < 0) newIndex = galleryImages.length - 1;
    if (newIndex >= galleryImages.length) newIndex = 0;
    setHero(newIndex);
}

// Lightbox
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
    if (e && e.target !== e.currentTarget && !e.target.classList.contains('lightbox-close')) return;
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

// Review form toggle
function toggleReviewForm() {
    const form = document.getElementById('reviewForm') || document.getElementById('reviewFormMobile');
    if (form) form.classList.toggle('active');
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