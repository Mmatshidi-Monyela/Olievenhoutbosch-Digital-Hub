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
$stmt = mysqli_prepare($conn, "SELECT l.*, u.full_name as owner_name, u.user_id as owner_id 
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

// NEW: Build all extensions array
$all_extensions = [$service['extension']];
if (!empty($service['service_extensions'])) {
    $additional = explode(',', $service['service_extensions']);
    $all_extensions = array_merge($all_extensions, $additional);
}
$ext_display = count($all_extensions) > 1 ? 'Multiple Ext' : 'Ext ' . $all_extensions[0];

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
if (count($comments) > 0) {
    $total = array_sum(array_column($comments, 'rating'));
    $avg_rating = round($total / count($comments), 1);
}

// NEW: Count images for gallery button
$image_count = 0;
$img_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM listing_images WHERE listing_id = $listing_id");
if ($img_res) $image_count = mysqli_fetch_assoc($img_res)['c'] ?? 0;

// Format contact number for display
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

// WhatsApp removed - using Call and Message only

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

// NEW: Payment options display
$payment_options = [];
if (!empty($service['payment_options'])) {
    $payment_options = array_map('trim', explode(',', $service['payment_options']));
}

// NEW: Delivery modes display
$delivery_modes = [];
if (!empty($service['delivery_mode'])) {
    $delivery_modes = array_map('trim', explode(',', $service['delivery_mode']));
}

// NEW: Listing type label
$type_label = 'Service';
if ($service['listing_type'] == 'product') $type_label = 'Goods';
if ($service['listing_type'] == 'both') $type_label = 'Service & Goods';

// Track view
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$viewer_id = $_SESSION['user_id'] ?? null;
$view_stmt = mysqli_prepare($conn, "INSERT INTO ListingView 
    (listing_id, viewer_ip, viewer_user_id) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($view_stmt, "isi", $listing_id, $ip, $viewer_id);
mysqli_stmt_execute($view_stmt);
mysqli_stmt_close($view_stmt);

// Update cached page_views
$update_stmt = mysqli_prepare($conn, "UPDATE listing SET page_views = page_views + 1 WHERE listing_id = ?");
mysqli_stmt_bind_param($update_stmt, "i", $listing_id);
mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);

// Helper: Get initials from name
function getInitials($name) {
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return substr($initials, 0, 2);
}

// Helper: Time ago
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($service['listing_name']); ?> - Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #f8c9c0;
            --copper: #ba745f;
            --light-grey: #f4f7f6;
        }

        body { 
            background-color: var(--light-grey); 
            font-family: 'Inter', sans-serif; 
        }

        .navbar-custom { 
            background-color: var(--plum) !important; 
            height: 60px;
            padding: 0 1rem;
            border-bottom: 3px solid var(--rose-gold); 
            display: flex;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .brand-text {
            font-size: 1.1rem;
            font-weight: bold;
            color: white;
            white-space: nowrap;
        }

        .back-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: opacity 0.2s;
            display: flex;
            align-items: center;
        }
        .back-link:hover { opacity: 0.8; color: white; }

        .service-card {
            background: white;
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .pricing-box {
            background-color: #fdfaf9;
            border-left: 5px solid var(--rose-gold);
            border-radius: 10px;
        }

        .avatar-rose {
            background-color: var(--rose-gold);
            color: var(--plum);
            font-weight: bold;
        }

        .badge-verified {
            background-color: var(--plum);
            color: white;
            border-radius: 50px;
            padding: 6px 15px;
            font-size: 0.8rem;
        }

        .btn-plum {
            background-color: var(--plum);
            color: white;
            border-radius: 50px;
            font-weight: bold;
            padding: 12px;
            border: none;
        }
        .btn-plum:hover { opacity: 0.9; color: white; }

        .btn-message {
            background-color: var(--rose-gold);
            color: var(--plum);
            border-radius: 50px;
            font-weight: bold;
            padding: 12px;
            border: none;
        }
        .btn-message:hover { background-color: #f0b8ad; color: var(--plum); }

        .payment-tag {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .delivery-tag {
            background: #fff3e0;
            color: #e65100;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .type-badge {
            background: var(--plum);
            color: white;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .comment-form {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .comment-image-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 10px;
            margin-top: 10px;
            display: none;
        }

        .sticky-sidebar {
            top: 80px;
        }

        /* ===== RATING STARS ===== */
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            cursor: pointer;
            font-size: 1.8rem;
            color: #ddd;
            transition: color 0.2s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffc107;
        }
        .star-rating label:hover {
            transform: scale(1.1);
        }

        /* Comment rating display */
        .comment-rating {
            color: #ffc107;
            font-size: 0.85rem;
        }
        .comment-rating .empty {
            color: #ddd;
        }

        /* Average rating big display */
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

        /* Message Modal */
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        .modal-header {
            background-color: var(--plum);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .modal-header .btn-close {
            filter: invert(1);
        }
        .modal-body textarea {
            border-radius: 10px;
            resize: none;
        }

        /* Gallery button and modal styles */
        .btn-view-photos {
            background: white;
            border: 2px solid var(--plum);
            color: var(--plum);
            border-radius: 50px;
            font-weight: bold;
            padding: 12px;
            width: 100%;
            margin-bottom: 20px;
        }
        .btn-view-photos:hover {
            background: var(--plum);
            color: white;
        }
        .gallery-modal .modal-header {
            background: var(--plum);
            color: white;
        }
        .gallery-modal .modal-header .btn-close {
            filter: invert(1);
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
        }
        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
        }
        .gallery-loading {
            text-align: center;
            padding: 40px;
        }
        /* Lightbox */
        .lightbox-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .lightbox-overlay.active {
            display: flex;
        }
        .lightbox-img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
        }
        .lightbox-nav {
            position: absolute;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 20px;
        }
        .lightbox-prev { left: 10px; }
        .lightbox-next { right: 10px; }

        /* NEW: Extension list display */
        .ext-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }
        .ext-tag {
            background: #e3f2fd;
            color: #0d47a1;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .ext-tag.primary {
            background: var(--plum);
            color: white;
        }
        /* Alert positioning */
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-custom sticky-top">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <a href="index.php" class="navbar-brand">
            <img src="images/logo.png" width="30" height="30" alt="logo" class="me-2">
            <span class="brand-text">Olievenhoutbosch Digital Hub</span>
        </a>

        <a href="main.php" class="back-link">
            Back
        </a>
    </div>
</nav>

<?php echo $alert_html; ?>

<main class="container my-5">
    <div class="row g-4">
        <!-- Left Column: Service Details -->
        <div class="col-lg-8">
            <div class="service-card p-4 mb-4">

                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h1 class="h3 fw-bold text-dark mb-1"><?php echo htmlspecialchars($service['listing_name']); ?></h1>
                        <p class="text-muted"><?php echo htmlspecialchars($service['category'] . ' • ' . $service['service_type']); ?></p>
                        <span class="type-badge"><?php echo $type_label; ?></span>
                    </div>
                    <?php if($service['verification_status'] == 'Verified'): ?>
                        <span class="badge-verified shadow-sm">
                            Verified
                        </span>
                    <?php endif; ?>
                </div>

                <hr>

                <!-- View Photos button -->
                <?php if ($image_count > 0): ?>
                <button class="btn btn-view-photos" onclick="loadGallery(<?php echo $listing_id; ?>)">
                    View Work Photos (<?php echo $image_count; ?>)
                </button>
                <?php endif; ?>

                <h5 class="fw-bold mb-3" style="color: black;">About this Listing</h5>
                <p class="text-secondary lh-lg"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>

                <div class="pricing-box p-4 mt-4">
                    <h6 class="fw-bold mb-2" style="color: var(--copper);">Pricing</h6>
                    <p class="mb-0 text-dark"><?php echo htmlspecialchars($service['price_description']); ?></p>
                </div>

                <div class="mt-4">
                    <h6 class="fw-bold mb-2" style="color: var(--plum);">Payment Options</h6>
                    <div>
                        <?php foreach ($payment_options as $pay): ?>
                            <span class="payment-tag"><?php echo htmlspecialchars($pay); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (in_array('EFT', $payment_options)): ?>
                    <p class="text-muted small mt-2">
                        EFT details shared via messaging for data privacy.
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Location Block (mobile) -->
            <div class="service-card p-4 text-center d-lg-none mb-4">
                <div class="mb-4">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                        <i class="fas fa-map-marker-alt fa-2x" style="color: var(--plum);"></i>
                    </div>
                    <h5 class="fw-bold">Service Areas</h5>

                    <!-- NEW: Extension tags -->
                    <div class="ext-list justify-content-center mb-2">
                        <?php foreach ($all_extensions as $idx => $ext): ?>
                            <span class="ext-tag <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo $ext; ?></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-2">
                        <?php foreach ($delivery_modes as $mode): 
                            $mode_label = $mode;
                            if ($mode == 'door_to_door') $mode_label = 'Door-to-Door';
                            if ($mode == 'customer_comes_to_me') $mode_label = 'Customer Comes to Me';
                            if ($mode == 'both_service') $mode_label = 'Both (Door-to-Door + On-site)';
                            if ($mode == 'i_deliver') $mode_label = 'I Deliver';
                            if ($mode == 'customer_pickup') $mode_label = 'Customer Pickup';
                        ?>
                            <span class="delivery-tag"><?php echo $mode_label; ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted small">
                        <?php 
                        if (!empty($service['street_address'])) {
                            echo htmlspecialchars($service['street_address']);
                        } else {
                            echo 'Mobile service';
                        }
                        ?>
                    </p>
                    <?php if (!empty($phone_display)): ?>
                        <p class="text-muted small">
                            <?php echo $phone_display; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-3">
                    <?php if (!empty($phone_link)): ?>
                        <a href="<?php echo $phone_link; ?>" class="btn btn-plum py-3">
                            Call
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-message py-3" data-bs-toggle="modal" data-bs-target="#messageModal">
                        Message
                    </button>
                </div>
            </div>

            <!-- Feedback Section -->
            <div class="service-card p-4">
                <h5 class="fw-bold mb-4" style="color: var(--plum);">Community Feedback</h5>

                <!-- Average Rating -->
                <div class="avg-rating-display">
                    <div class="avg-rating-number"><?php echo $avg_rating; ?></div>
                    <div class="avg-rating-stars">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="bi bi-star<?php echo $i <= round($avg_rating) ? '-fill' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="avg-rating-text">Based on <?php echo count($comments); ?> review(s)</div>
                </div>

                <!-- Comment Form -->
                <div class="comment-form">
                    <h6 class="fw-bold mb-3">Leave your feedback</h6>
                    <form action="add_comment.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="listing_id" value="<?php echo (int)$listing_id; ?>">

                        <div class="mb-3">
                            <label class="form-label small text-muted mb-2">Your Rating</label>
                            <div class="star-rating">
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
                            <textarea name="comment_text" class="form-control" rows="3" placeholder="Share your experience..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted">Attach an image (optional)</label>
                            <input type="file" name="comment_image" class="form-control" accept="image/*" onchange="previewImage(this)">
                            <img id="imagePreview" class="comment-image-preview" alt="Preview">
                        </div>

                        <button type="submit" class="btn btn-plum w-100">Post Comment</button>
                    </form>
                </div>

                <!-- Existing Comments -->
                <?php foreach($comments as $comment): 
                    $initials = getInitials($comment['full_name']);
                    $time_ago = timeAgo($comment['created_at']);
                ?>
                    <div class="d-flex mb-4">
                        <div class="rounded-circle avatar-rose d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; flex-shrink: 0;">
                            <?php echo $initials; ?>
                        </div>
                        <div class="border-bottom pb-3 w-100">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($comment['full_name']); ?></h6>
                                    <div class="comment-rating mb-1">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <i class="bi bi-star<?php echo $i <= $comment['rating'] ? '-fill' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <span class="text-muted small"><?php echo $time_ago; ?></span>
                            </div>
                            <?php if(!empty($comment['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($comment['image_path']); ?>" class="img-fluid rounded mt-2" style="max-height: 200px;" alt="Comment image">
                            <?php endif; ?>
                            <p class="text-secondary small mt-2"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Column: Sidebar (desktop) -->
        <div class="col-lg-4 d-none d-lg-block">
            <div class="service-card p-4 text-center sticky-top sticky-sidebar">
                <div class="mb-4">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                        <i class="fas fa-map-marker-alt fa-2x" style="color: var(--plum);"></i>
                    </div>
                    <h5 class="fw-bold">Service Areas</h5>

                    <!-- NEW: Extension tags -->
                    <div class="ext-list justify-content-center mb-2">
                        <?php foreach ($all_extensions as $idx => $ext): ?>
                            <span class="ext-tag <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo $ext; ?></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-2">
                        <?php foreach ($delivery_modes as $mode): 
                            $mode_label = $mode;
                            if ($mode == 'door_to_door') $mode_label = 'Door-to-Door';
                            if ($mode == 'customer_comes_to_me') $mode_label = 'Customer Comes to Me';
                            if ($mode == 'both_service') $mode_label = 'Both (Door-to-Door + On-site)';
                            if ($mode == 'i_deliver') $mode_label = 'I Deliver';
                            if ($mode == 'customer_pickup') $mode_label = 'Customer Pickup';
                        ?>
                            <span class="delivery-tag"><?php echo $mode_label; ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted small">
                        <?php 
                        if (!empty($service['street_address'])) {
                            echo htmlspecialchars($service['street_address']);
                        } else {
                            echo 'Mobile service';
                        }
                        ?>
                    </p>
                    <?php if (!empty($phone_display)): ?>
                        <p class="text-muted small">
                            <?php echo $phone_display; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-3">
                    <?php if (!empty($phone_link)): ?>
                        <a href="<?php echo $phone_link; ?>" class="btn btn-plum py-3">
                            Call
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-message py-3" data-bs-toggle="modal" data-bs-target="#messageModal">
                        Message
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Gallery Modal -->
<div class="modal fade gallery-modal" id="galleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Work Photos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="galleryContent">
                    <div class="gallery-loading">
                        <div class="spinner-border text-secondary" role="status"></div>
                        <p class="mt-2">Loading photos...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox Overlay -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()"><i class="bi bi-x-lg"></i></span>
    <span class="lightbox-nav lightbox-prev" onclick="event.stopPropagation(); changeImage(-1)"><i class="bi bi-chevron-left"></i></span>
    <img src="" class="lightbox-img" id="lightboxImg" onclick="event.stopPropagation()">
    <span class="lightbox-nav lightbox-next" onclick="event.stopPropagation(); changeImage(1)"><i class="bi bi-chevron-right"></i></span>
</div>

<!-- Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="messageModalLabel">
                    Message <?php echo htmlspecialchars($service['listing_name']); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    About: <?php echo htmlspecialchars($service['category']); ?>
                </p>
                <form action="send_message.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="listing_id" value="<?php echo (int)$listing_id; ?>">
                    <input type="hidden" name="receiver_id" value="<?php echo (int)$service['owner_id']; ?>">
                    <input type="hidden" name="return_to" value="messages.php?view=sent&thread=<?php echo (int)$service['owner_id']; ?>&listing=<?php echo (int)$listing_id; ?>">
                    <div class="mb-3">
                        <textarea name="message" class="form-control" rows="4" placeholder="Hi, I'm interested in your service..." required></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-plum flex-grow-1 rounded-pill">
                            Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewImage(input) {
    var preview = document.getElementById('imagePreview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Gallery lazy loading
let currentGalleryImages = [];
let currentImageIndex = 0;

function loadGallery(listingId) {
    const modal = new bootstrap.Modal(document.getElementById('galleryModal'));
    modal.show();

    const content = document.getElementById('galleryContent');
    content.innerHTML = '<div class="gallery-loading"><div class="spinner-border text-secondary"></div><p class="mt-2">Loading...</p></div>';

    fetch('get_listing_images.php?listing_id=' + listingId)
        .then(r => r.json())
        .then(data => {
            if (data.error || data.count === 0) {
                content.innerHTML = '<div class="gallery-loading"><p>No photos found.</p></div>';
                return;
            }
            currentGalleryImages = data.images;
            let html = '<div class="gallery-grid">';
            data.images.forEach((img, i) => {
                html += `<div class="gallery-item" onclick="openLightbox(${i})"><img src="${img}" alt="Photo ${i+1}" loading="lazy"></div>`;
            });
            html += '</div>';
            content.innerHTML = html;
        })
        .catch(() => {
            content.innerHTML = '<div class="gallery-loading"><p>Failed to load photos.</p></div>';
        });
}

function openLightbox(index) {
    currentImageIndex = index;
    document.getElementById('lightboxImg').src = currentGalleryImages[index];
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}

function changeImage(dir) {
    currentImageIndex += dir;
    if (currentImageIndex < 0) currentImageIndex = currentGalleryImages.length - 1;
    if (currentImageIndex >= currentGalleryImages.length) currentImageIndex = 0;
    document.getElementById('lightboxImg').src = currentGalleryImages[currentImageIndex];
}

document.addEventListener('keydown', (e) => {
    if (!document.getElementById('lightbox').classList.contains('active')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') changeImage(-1);
    if (e.key === 'ArrowRight') changeImage(1);
});

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