<?php
session_start();
/**@var mysqli $conn */

// ============================================
// ADMIN VIEW REQUEST - Fully Database Driven
// ============================================

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit();
}

$listing_id = intval($_GET['id'] ?? 0);

if ($listing_id == 0) {
    header("Location: admin_requests.php");
    exit();
}

$conn = null;
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
}

if (!$conn) {
    die("Database connection failed.");
}

// ============================================
// FETCH LISTING DATA FROM DATABASE
// ============================================
$request = null;
$stmt = mysqli_prepare($conn, "SELECT l.*, u.full_name as owner_name, u.contact_number as owner_phone, u.email as owner_email 
    FROM listing l 
    JOIN useraccount u ON l.user_id = u.user_id 
    WHERE l.listing_id = ?");
mysqli_stmt_bind_param($stmt, "i", $listing_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$request) {
    header("Location: admin_requests.php");
    exit();
}

// ============================================
// FETCH COMMENTS WITH RATINGS
// ============================================
$comments = [];
$cmt_stmt = mysqli_prepare($conn, "SELECT c.*, u.full_name FROM comment c JOIN useraccount u ON c.user_id = u.user_id WHERE c.listing_id = ? ORDER BY c.created_at DESC");
mysqli_stmt_bind_param($cmt_stmt, "i", $listing_id);
mysqli_stmt_execute($cmt_stmt);
$cmt_result = mysqli_stmt_get_result($cmt_stmt);
while ($row = mysqli_fetch_assoc($cmt_result)) {
    $comments[] = $row;
}
mysqli_stmt_close($cmt_stmt);

// ============================================
// FETCH GALLERY IMAGES
// ============================================
$gallery_images = [];
$gal_stmt = mysqli_prepare($conn, "SELECT image_path FROM listing_images WHERE listing_id = ? ORDER BY uploaded_at ASC");
mysqli_stmt_bind_param($gal_stmt, "i", $listing_id);
mysqli_stmt_execute($gal_stmt);
$gal_result = mysqli_stmt_get_result($gal_stmt);
while ($g = mysqli_fetch_assoc($gal_result)) {
    $gallery_images[] = $g['image_path'];
}
mysqli_stmt_close($gal_stmt);

// ============================================
// CALCULATE AVERAGE RATING FROM COMMENTS
// ============================================
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

// ============================================
// BUILD EXTENSIONS ARRAY
// ============================================
$all_extensions = [$request['extension'] ?? ''];
if (!empty($request['service_extensions'])) {
    $additional = array_map('trim', explode(',', $request['service_extensions']));
    $all_extensions = array_merge($all_extensions, $additional);
}

// Payment options
$payment_options = [];
if (!empty($request['payment_options'])) {
    $payment_options = array_map('trim', explode(',', $request['payment_options']));
}

// Delivery modes
$delivery_modes = [];
if (!empty($request['delivery_mode'])) {
    $delivery_modes = array_map('trim', explode(',', $request['delivery_mode']));
}

// Listing type label
$type_label = 'Service';
if ($request['listing_type'] == 'product') $type_label = 'Goods';
if ($request['listing_type'] == 'both') $type_label = 'Service & Goods';

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request | Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --plum: #230344;
            --rose-gold: #c99383;
            --light-bg: #f4f7f6;
        }

        body { 
            background-color: var(--light-bg); 
            font-family: 'Inter', sans-serif; 
        }

        .navbar-custom {
            background-color: var(--plum) !important;
            border-bottom: 3px solid var(--rose-gold);
            padding: 12px 0;
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
            font-size: 0.9rem;
            transition: opacity 0.2s;
        }
        .back-link:hover { opacity: 0.8; color: white; }

        .business-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .status-badge {
            background-color: #f1f1f1;
            color: #666;
            padding: 5px 15px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .info-section-title {
            color: var(--plum);
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .performance-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .performance-title {
            color: var(--plum);
            font-weight: 800;
            font-size: 1.2rem;
            margin-bottom: 20px;
        }

        .stat-box {
            border: 1px solid var(--rose-gold);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            flex: 1;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--plum);
            display: block;
            line-height: 1.3;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .location-box {
            background-color: #f8f9fa;
            border-left: 4px solid var(--plum);
            border-radius: 6px;
            padding: 15px;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
            border: none;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-approve:hover { background-color: #218838; color: white; }

        .btn-reject {
            background-color: #dc3545;
            color: white;
            border: none;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-reject:hover { background-color: #c82333; color: white; }

        .comment-box {
            border-left: 4px solid var(--plum);
            background: #f8f9fa;
            border-radius: 0 6px 6px 0;
            padding: 15px;
            margin-bottom: 15px;
        }

        .comment-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .avatar-rose {
            background-color: var(--rose-gold);
            color: var(--plum);
            font-weight: bold;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

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

        .admin-gallery {
            margin-top: 20px;
        }
        .admin-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .admin-gallery-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }
        .no-photos {
            color: #999;
            font-style: italic;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .comment-rating {
            color: #ffc107;
            font-size: 0.85rem;
        }
        .comment-rating .empty {
            color: #ddd;
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

        .owner-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-custom sticky-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
                <img src="images/logo.png" width="30" height="30" alt="logo" class="me-2">
                <span class="brand-text">Olievenhoutbosch Digital Hub</span>
            </a>
            <a href="admin_requests.php" class="back-link">
                Back
            </a>
        </div>
    </nav>

    <div class="container mb-5 mt-4">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="business-card mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="fw-bold mb-1" style="color: var(--plum);"><?php echo htmlspecialchars($request['listing_name'] ?? 'Unknown'); ?></h2>
                            <p class="text-muted small"><?php echo htmlspecialchars($request['category'] ?? ''); ?> &bull; <?php echo htmlspecialchars($request['service_type'] ?? ''); ?></p>
                            <span class="type-badge"><?php echo $type_label; ?></span>
                        </div>
                        <span class="status-badge">Status: <?php echo htmlspecialchars($request['verification_status'] ?? 'Unknown'); ?></span>
                    </div>

                    <hr class="my-4" style="opacity: 0.1;">

                    <div class="mb-3">
                        <h6 class="fw-bold small mb-2" style="color: var(--plum);">Service Areas</h6>
                        <div class="ext-list">
                            <?php foreach ($all_extensions as $idx => $ext): ?>
                                <?php if (!empty(trim($ext))): ?>
                                <span class="ext-tag <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo htmlspecialchars(trim($ext)); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="info-section-title">Description</h6>
                        <p class="text-muted small" style="line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($request['description'] ?? 'No description provided.')); ?>
                        </p>
                        <p class="small"><strong>Pricing:</strong> <span class="text-danger"><?php echo htmlspecialchars($request['price_description'] ?? 'Not specified'); ?></span></p>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold small mb-2" style="color: var(--plum);">Payment Options</h6>
                        <div>
                            <?php foreach ($payment_options as $pay): ?>
                                <span class="payment-tag"><?php echo htmlspecialchars($pay); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="location-box">
                        <h6 class="fw-bold small mb-2">How Customers Receive</h6>
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
                        <?php if (!empty($request['street_address'])): ?>
                        <p class="mb-0 small text-muted"><strong>Address:</strong> <?php echo htmlspecialchars($request['street_address']); ?>, Ext <?php echo htmlspecialchars($request['extension'] ?? ''); ?></p>
                        <?php else: ?>
                        <p class="mb-0 small text-muted"><em>Mobile service (No physical address listed)</em></p>
                        <?php endif; ?>
                    </div>

                    <div class="owner-info">
                        <h6 class="fw-bold small mb-2" style="color: var(--plum);">Owner Information</h6>
                        <p class="mb-1 small"><strong>Name:</strong> <?php echo htmlspecialchars($request['owner_name'] ?? 'Unknown'); ?></p>
                        <?php if (!empty($request['owner_phone'])): ?>
                        <p class="mb-1 small"><strong>Phone:</strong> <?php echo htmlspecialchars($request['owner_phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($request['owner_email'])): ?>
                        <p class="mb-0 small"><strong>Email:</strong> <?php echo htmlspecialchars($request['owner_email']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="admin-gallery">
                        <h6 class="info-section-title">Work Photos</h6>
                        <?php if (!empty($gallery_images)): ?>
                        <div class="admin-gallery-grid">
                            <?php foreach ($gallery_images as $img): ?>
                            <div class="admin-gallery-item">
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="Work photo">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="no-photos">
                            No photos uploaded
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="business-card">
                    <h6 class="fw-bold mb-3" style="color: var(--plum);">User Comments & Feedback</h6>

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

                    <?php if (empty($comments)): ?>
                        <p class="text-muted small">No comments yet.</p>
                    <?php else: ?>
                        <?php foreach($comments as $comment): 
                            $comment_rating = $comment['rating'] ?? 0;
                            $initials = getInitials($comment['full_name'] ?? 'Anonymous');
                            $name = $comment['full_name'] ?? 'Anonymous';
                            $time = timeAgo($comment['created_at'] ?? 'now');
                            $text = $comment['comment_text'] ?? '';
                            $img = $comment['image_path'] ?? null;
                        ?>
                            <div class="comment-box">
                                <div class="d-flex align-items-start">
                                    <div class="avatar-rose me-3">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <p class="small mb-1"><strong><?php echo htmlspecialchars($name); ?></strong> <span class="text-muted fw-normal">- <?php echo $time; ?></span></p>
                                                <?php if ($comment_rating > 0): ?>
                                                <div class="comment-rating mb-1">
                                                    <?php for($i=1; $i<=5; $i++): ?>
                                                        <i class="bi bi-star<?php echo $i <= $comment_rating ? '-fill' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="text-muted small ms-1">(<?php echo $comment_rating; ?>/5)</span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="small mb-0"><?php echo htmlspecialchars($text); ?></p>
                                        <?php if(!empty($img)): ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" class="comment-image" alt="Comment image">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="performance-card mb-4">
                    <div class="performance-title">Performance</div>
                    <div class="d-flex gap-3">
                        <div class="stat-box">
                            <span class="stat-value"><?php echo $request['page_views'] ?? 0; ?></span>
                            <div class="stat-label">Page Views</div>
                        </div>
                        <div class="stat-box">
                            <span class="stat-value"><?php echo $avg_rating; ?></span>
                            <div class="stat-label">Avg. Rating</div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <form method="POST" action="approve_listing.php">
                        <input type="hidden" name="id" value="<?php echo $listing_id; ?>">
                        <button type="submit" class="btn btn-approve p-3 w-100 d-flex align-items-center justify-content-center">
                            Approve Listing
                        </button>
                    </form>
                    <form method="POST" action="reject_listing.php">
                        <input type="hidden" name="id" value="<?php echo $listing_id; ?>">
                        <button type="submit" class="btn btn-reject p-3 w-100 d-flex align-items-center justify-content-center" onclick="return confirm('Reject this listing? It will be set back to Unverified.')">
                            Reject Request
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>