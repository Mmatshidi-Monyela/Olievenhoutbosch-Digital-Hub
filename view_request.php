<?php
session_start();

// Admin auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit();
}

$conn = null;
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
}

$listing_id = intval($_GET['id'] ?? 0);
if ($listing_id <= 0 || !$conn) {
    header("Location: admin_requests.php");
    exit();
}

// ============================================
// HANDLE APPROVE / REJECT ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['admin_action'] ?? '';

    if ($action === 'approve') {
        $stmt = mysqli_prepare($conn, "UPDATE listing SET verification_status = 'Verified' WHERE listing_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $listing_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['admin_msg'] = 'Listing verified successfully.';
        header("Location: admin_requests.php");
        exit();
    }

    if ($action === 'reject') {
        $reason = $_POST['rejection_reason'] ?? '';
        $stmt = mysqli_prepare($conn, "UPDATE listing SET verification_status = 'Unverified' WHERE listing_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $listing_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['admin_msg'] = 'Verification request rejected.' . (!empty($reason) ? ' Reason: ' . $reason : '');
        header("Location: admin_requests.php");
        exit();
    }
}

// ============================================
// FETCH LISTING DATA
// ============================================
$stmt = mysqli_prepare($conn, "
    SELECT l.*, u.full_name as owner_name, u.user_id as owner_id, u.created_at as member_since
    FROM listing l 
    JOIN useraccount u ON l.user_id = u.user_id 
    WHERE l.listing_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $listing_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$listing = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$listing) {
    header("Location: admin_requests.php");
    exit();
}

// Fetch gallery images
$gallery_images = [];
$gal_stmt = mysqli_prepare($conn, "SELECT image_id, image_path FROM listing_images WHERE listing_id = ? ORDER BY uploaded_at ASC");
mysqli_stmt_bind_param($gal_stmt, 'i', $listing_id);
mysqli_stmt_execute($gal_stmt);
$gal_result = mysqli_stmt_get_result($gal_stmt);
while ($g = mysqli_fetch_assoc($gal_result)) {
    $gallery_images[] = $g;
}
mysqli_stmt_close($gal_stmt);

$main_image = $gallery_images[0]['image_path'] ?? $listing['image_path'] ?? 'uploads/listings/default_listing.jpg';

// Fetch comments with ratings
$comments = [];
$total_rating = 0;
$rated_count = 0;
$flaggedKeywords = ['scam', 'terrible', 'worst', 'never again', 'rip off', 'fraud', 
                    'disappointed', 'horrible', 'awful', 'garbage', 'trash', 
                    'waste of money', 'broken', 'fake', 'liar', 'stole', 'scared', 
                    'sick', 'unprofessional', 'bad', 'not recommend', 'poor', 'awful', 
                    'dissatisfied', 'unhappy', 'regret', 'untrustworthy', 'rude', 
                    'unresponsive', 'late', 'no show', 'unhelpful', 'disgusting', 'not worth it',
                    'avoid', 'do not use', 'never use', 'worst experience', 'scammed', 'horrendous', 
                    'atrocious', 'not tasty', 'rotten', 'undercooked', 'overcooked', 'dirty', 'filthy', 
                    'unsanitary', 'lack of hygiene', 'sick from', 'food poisoning', 'vomited', 'diarrhea',
                    'allergic reaction', 'burned', 'raw', 'spoiled', 'inedible', 'disaster', 'nightmare', 
                    'terrible service', 'broken', 'damaged', 'poor quality', 'cheap materials',
                    'speeding', 'speeds','reckless', 'dangerous', 'accident', 'injury', 'unlicensed', 'illegal', 
                    'fraudulent','overloaded', 'overloading', 'overloads'];

$keyword_alert_count = 0;

$cmt_stmt = mysqli_prepare($conn, "SELECT c.*, u.full_name FROM comment c JOIN useraccount u ON c.user_id = u.user_id WHERE c.listing_id = ? ORDER BY c.created_at DESC");
mysqli_stmt_bind_param($cmt_stmt, 'i', $listing_id);
mysqli_stmt_execute($cmt_stmt);
$cmt_result = mysqli_stmt_get_result($cmt_stmt);
while ($row = mysqli_fetch_assoc($cmt_result)) {
    $comments[] = $row;
    if (isset($row['rating']) && $row['rating'] > 0) {
        $total_rating += $row['rating'];
        $rated_count++;
    }
    $lowerComment = strtolower($row['comment_text'] ?? '');
    foreach ($flaggedKeywords as $keyword) {
        if (strpos($lowerComment, $keyword) !== false) {
            $keyword_alert_count++;
            break;
        }
    }
}
mysqli_stmt_close($cmt_stmt);

$avg_rating = $rated_count > 0 ? round($total_rating / $rated_count, 1) : 0;
$rating_alert = ($avg_rating > 0 && $avg_rating < 3.5);
$keyword_alert = ($keyword_alert_count > 0);

$all_extensions = [$listing['extension']];
if (!empty($listing['service_extensions'])) {
    $additional = explode(',', $listing['service_extensions']);
    $all_extensions = array_merge($all_extensions, $additional);
}

$payment_options = [];
if (!empty($listing['payment_options'])) {
    $payment_options = array_map('trim', explode(',', $listing['payment_options']));
}

$delivery_modes = [];
if (!empty($listing['delivery_mode'])) {
    $delivery_modes = array_map('trim', explode(',', $listing['delivery_mode']));
}

$type_label = 'Service';
if ($listing['listing_type'] == 'product') $type_label = 'Goods';
if ($listing['listing_type'] == 'both') $type_label = 'Service & Goods';

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

$admin_name = $_SESSION['full_name'] ?? 'Administrator';
$admin_first_name = explode(' ', $admin_name)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Request - <?php echo htmlspecialchars($listing['listing_name']); ?> | Admin</title>
    <link rel="icon" type="image/png" href="images/logo.png"> 
    <link rel="apple-touch-icon" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #c99383;
            --copper: #ba745f;
            --light-grey: #f4f7f6;
        }
        body { background-color: var(--light-grey); font-family: 'Inter', sans-serif; color: #333; padding-bottom: 100px; }

        /* ===== NEW NAVBAR ===== */
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
        .navbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        }
        .back-link:hover { color: var(--rose-gold); }

        /* ===== CARDS ===== */
        .glass-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border: none;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header-custom {
            padding: 16px 20px;
            font-weight: 700;
            color: var(--plum);
            font-size: 1rem;
        }
        .card-body-custom {
            padding: 0 20px 20px;
        }

        /* ===== PHOTO HERO ===== */
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
            border-radius: 12px;
        }
        .photo-hero img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .thumb-strip {
            display: flex;
            gap: 8px;
            padding: 12px 0;
            overflow-x: auto;
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
            display: block;
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
        }
        .tag-pill.primary {
            background: var(--plum);
            color: white;
            border-color: var(--plum);
        }

        /* ===== STATS GRID (6 blocks) ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .stat-box {
            background: #fdfaf9;
            border: 1px solid var(--rose-gold);
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }
        .stat-box .value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--plum);
            line-height: 1.2;
        }
        .stat-box .label {
            font-size: 0.7rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        .stat-box.alert-rating {
            background: #fff3cd;
            border-color: #ffeeba;
        }
        .stat-box.alert-rating .value,
        .stat-box.alert-rating .label {
            color: #856404;
        }
        .stat-box.alert-keyword {
            background: #ffe5e5;
            border-color: #f5c6cb;
        }
        .stat-box.alert-keyword .value,
        .stat-box.alert-keyword .label {
            color: #d9534f;
        }
        .stat-box.ok {
            background: #e6ffed;
            border-color: #c3e6cb;
        }
        .stat-box.ok .value,
        .stat-box.ok .label {
            color: #28a745;
        }

        /* ===== COMMENTS ===== */
        .comment-card {
            display: flex;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        .comment-card:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--rose-gold);
            color: var(--plum);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
            margin-right: 12px;
        }
        .comment-body { flex: 1; min-width: 0; }
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
        }
        .comment-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: #1a1a1a;
        }
        .comment-time {
            font-size: 0.75rem;
            color: #aaa;
            white-space: nowrap;
        }
        .comment-rating {
            color: #ffc107;
            font-size: 0.8rem;
            margin-bottom: 4px;
        }
        .comment-rating .empty { color: #ddd; }
        .comment-text {
            font-size: 0.85rem;
            color: #555;
            line-height: 1.5;
            margin: 0;
        }
        .comment-image {
            max-height: 150px;
            border-radius: 8px;
            margin-top: 6px;
            max-width: 100%;
            display: block;
        }
        .flagged-keyword {
            background: #ffe5e5;
            color: #d9534f;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
            text-transform: uppercase;
        }

        /* ===== ACTION BUTTONS ===== */
        .action-bar-mobile {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 16px;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.08);
            z-index: 1030;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
            justify-content: center;
        }
        .btn-approve:hover { background: #218838; }
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
            justify-content: center;
        }
        .btn-reject:hover { background: #c82333; }

        /* Desktop sidebar actions */
        .desktop-actions {
            display: none;
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
        .lightbox-close:hover { background: rgba(255,255,255,0.3); }
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

        /* ===== DESKTOP LAYOUT ===== */
        @media (min-width: 992px) {
            body { padding-bottom: 0; }
            .action-bar-mobile { display: none !important; }
            .desktop-actions {
                display: block;
            }
            .desktop-actions .btn-approve,
            .desktop-actions .btn-reject {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 575.98px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .brand-text {
                font-size: 0.85rem;
                max-width: 160px;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="navbar-inner">
        <a href="admin_dashboard.php" class="navbar-brand">
            <img src="images/logo.png" width="28" height="28" alt="logo" style="flex-shrink:0;">
            <span class="brand-text">Olievenhoutbosch Digital Hub</span>
        </a>
        <a href="admin_requests.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</nav>

<div class="container py-4">

    <!-- Page Header -->
    <div class="mb-4">
        <h4 class="fw-bold mb-0" style="color: var(--plum);">Verification Review</h4>
    </div>

    <div class="row">
        <!-- LEFT COLUMN -->
        <div class="col-lg-8">

            <!-- Images Card -->
            <div class="glass-card">
                <div class="card-header-custom">Listing Images</div>
                <div class="card-body-custom">
                    <div class="photo-hero" onclick="openLightbox(0)" style="cursor: pointer;">
                        <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($listing['listing_name']); ?>" id="heroImg">
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
            </div>

            <!-- Listing Info Card -->
            <div class="glass-card">
                <div class="card-header-custom">Listing Information</div>
                <div class="card-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Listing Name</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($listing['listing_name']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Owner</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($listing['owner_name']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Category</div>
                            <div><?php echo htmlspecialchars($listing['category']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Type</div>
                            <div><span class="badge" style="background: var(--plum);"><?php echo $type_label; ?></span></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Service Type</div>
                            <div><?php echo !empty($listing['service_type']) ? htmlspecialchars($listing['service_type']) : '<span class="text-muted">N/A</span>'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Product Type</div>
                            <div><?php echo !empty($listing['product_type']) ? htmlspecialchars($listing['product_type']) : '<span class="text-muted">N/A</span>'; ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small mb-1">Extensions</div>
                            <div>
                                <?php foreach ($all_extensions as $idx => $ext): ?>
                                    <span class="tag-pill <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo htmlspecialchars(trim($ext)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small mb-1">Address</div>
                            <div><?php echo !empty($listing['street_address']) ? htmlspecialchars($listing['street_address']) : '<span class="text-muted">Mobile service</span>'; ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small mb-1">Description</div>
                            <div style="line-height: 1.7; color: #444;"><?php echo nl2br(htmlspecialchars($listing['description'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comments / Reviews Card -->
            <div class="glass-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <span>User Comments & Reviews</span>
                    <span class="badge bg-secondary"><?php echo count($comments); ?> total</span>
                </div>
                <div class="card-body-custom">
                    <?php if (empty($comments)): ?>
                        <div class="text-center py-4 text-muted">
                            <p class="mt-2">No comments yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): 
                            $initials = getInitials($comment['full_name']);
                            $time_ago = timeAgo($comment['created_at']);
                            $comment_rating = $comment['rating'] ?? 0;
                            $has_flagged = false;
                            $lowerComment = strtolower($comment['comment_text'] ?? '');
                            foreach ($flaggedKeywords as $keyword) {
                                if (strpos($lowerComment, $keyword) !== false) {
                                    $has_flagged = true;
                                    break;
                                }
                            }
                        ?>
                        <div class="comment-card">
                            <div class="comment-avatar"><?php echo $initials; ?></div>
                            <div class="comment-body">
                                <div class="comment-header">
                                    <div>
                                        <span class="comment-name"><?php echo htmlspecialchars($comment['full_name']); ?></span>
                                        <?php if ($has_flagged): ?>
                                            <span class="flagged-keyword">Flagged</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="comment-time"><?php echo $time_ago; ?></span>
                                </div>
                                <?php if ($comment_rating > 0): ?>
                                <div class="comment-rating">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $comment_rating ? '-fill' : ' empty'; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="text-muted small ms-1">(<?php echo $comment_rating; ?>/5)</span>
                                </div>
                                <?php endif; ?>
                                <?php if(!empty($comment['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($comment['image_path']); ?>" class="comment-image" alt="Comment image">
                                <?php endif; ?>
                                <p class="comment-text"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="col-lg-4">

            <!-- Performance Stats (6 blocks including alerts) -->
            <div class="glass-card">
                <div class="card-header-custom">Performance</div>
                <div class="card-body-custom">
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="value"><?php echo number_format($avg_rating, 1); ?></div>
                            <div class="label">Avg Rating</div>
                        </div>
                        <div class="stat-box">
                            <div class="value"><?php echo count($comments); ?></div>
                            <div class="label">Reviews</div>
                        </div>
                        <div class="stat-box">
                            <div class="value"><?php echo $listing['page_views'] ?? 0; ?></div>
                            <div class="label">Page Views</div>
                        </div>
                        <div class="stat-box">
                            <div class="value"><?php echo count($gallery_images); ?></div>
                            <div class="label">Photos</div>
                        </div>
                        <div class="stat-box <?php echo $rating_alert ? 'alert-rating' : 'ok'; ?>">
                            <div class="value"><?php echo $rating_alert ? '!' : 'OK'; ?></div>
                            <div class="label"><?php echo $rating_alert ? 'Rating Alert' : 'Rating OK'; ?></div>
                        </div>
                        <div class="stat-box <?php echo $keyword_alert ? 'alert-keyword' : 'ok'; ?>">
                            <div class="value"><?php echo $keyword_alert_count; ?></div>
                            <div class="label"><?php echo $keyword_alert ? 'Keyword Alerts' : 'No Keywords'; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Actions -->
            <div class="glass-card desktop-actions">
                <div class="card-header-custom">Actions</div>
                <div class="card-body-custom">
                    <button type="button" class="btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        Reject
                    </button>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Approve this listing for verification?');">
                        <input type="hidden" name="admin_action" value="approve">
                        <button type="submit" class="btn-approve">
                            Approve Verification
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sticky Action Bar -->
<div class="action-bar-mobile d-lg-none">
    <button type="button" class="btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal" style="flex: 1;">
        Reject
    </button>
    <form method="POST" style="display: flex; flex: 1; margin: 0;" onsubmit="return confirm('Approve this listing for verification?');">
        <input type="hidden" name="admin_action" value="approve">
        <button type="submit" class="btn-approve" style="width: 100%;">
            Approve
        </button>
    </form>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header" style="border-bottom: 2px solid #f8d7da;">
                <h5 class="modal-title fw-bold" style="color: #dc3545;">Reject Verification Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <p class="text-muted">You are about to reject the verification request for <strong><?php echo htmlspecialchars($listing['listing_name']); ?></strong>.</p>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Rejection Reason (optional)</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="e.g. Insufficient reviews, low rating, missing information..." style="border-radius: 10px; resize: none;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #f0f0f0;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <input type="hidden" name="admin_action" value="reject">
                    <button type="submit" class="btn btn-danger" style="border-radius: 8px; background: #dc3545; border: none;">
                        Confirm Rejection
                    </button>
                </div>
            </form>
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
const galleryImages = <?php echo json_encode(array_column($gallery_images, 'image_path')); ?>;
let currentHeroIndex = 0;

function setHero(index) {
    if (index < 0 || index >= galleryImages.length) return;
    currentHeroIndex = index;
    const img = document.getElementById('heroImg');
    if (img) img.src = galleryImages[index];
    document.querySelectorAll('.thumb-item').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

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

document.addEventListener('keydown', (e) => {
    const lightbox = document.getElementById('lightbox');
    if (!lightbox.classList.contains('active')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') changeImage(-1);
    if (e.key === 'ArrowRight') changeImage(1);
});
</script>
</body>
</html>