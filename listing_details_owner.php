<?php
session_start();
/**@var mysqli $conn */ 

// ============================================
// BUSINESS DETAILS OWNER (Manage Listing)
// Shows verification status + Request Verification button + DELETE LISTING
// ============================================

$listing_id = $_GET['id'] ?? 1;
$user_id = $_SESSION['user_id'] ?? 0;

// Handle verification request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_verification'])) {
    $_SESSION['verify_msg'] = 'Your verification request has been submitted and is pending admin review.';
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
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
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
$listing_id = $_GET["id"] ?? 0;
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
    $cmt_stmt = mysqli_prepare($conn, 'SELECT c.*, u.full_name FROM Comment c JOIN UserAccount u ON c.user_id = u.user_id WHERE c.listing_id = ? ORDER BY c.created_at DESC');
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


// NEW: Fetch real images from DB
$gallery_images = [];
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
    $gal_stmt = mysqli_prepare($conn, "SELECT image_id, image_path FROM listing_images WHERE listing_id = ? ORDER BY uploaded_at ASC");
    mysqli_stmt_bind_param($gal_stmt, "i", $listing_id);
    mysqli_stmt_execute($gal_stmt);
    $gal_result = mysqli_stmt_get_result($gal_stmt);
    while ($g = mysqli_fetch_assoc($gal_result)) {
        $gallery_images[] = $g;
    }
    mysqli_stmt_close($gal_stmt);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Listing - <?php echo $listing['listing_name']; ?></title>
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

        body { background-color: var(--light-grey); font-family: 'Inter', sans-serif; }

        .navbar-custom {
            background-color: var(--plum);
            padding: 0.6rem 1rem;
            border-bottom: 3px solid var(--rose-gold);
        }

        .brand-text {
            font-size: clamp(0.8rem, 2.2vw, 1.1rem);
            white-space: nowrap;
        }

        .back-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: opacity 0.2s;
        }
        .back-link:hover { opacity: 0.8; color: white; }

        .management-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border: none;
        }

        /* REMOVED: .business-image style no longer needed */

        .stat-box {
            background: #fdfaf9;
            border: 1px solid var(--rose-gold);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }

        .stat-number { font-size: 1.5rem; font-weight: bold; color: var(--plum); }

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

        /* Delete Button */
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

        .status-badge {
            border-radius: 8px;
            padding: 8px 15px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .status-unverified { background-color: #eee; color: #666; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-verified { background-color: #d4edda; color: #155724; }

        .location-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid var(--plum);
        }

        .comment-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 10px;
        }

        .comment-box {
            border-left: 4px solid var(--rose-gold);
            padding-left: 15px;
            margin-bottom: 20px;
        }

        .pending-notice {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 10px;
            padding: 12px 16px;
            color: #856404;
            font-size: 0.9rem;
        }

        .verified-notice {
            background: #e6ffed;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 12px 16px;
            color: #155724;
            font-size: 0.9rem;
        }

        /* Danger zone card */
        .danger-zone {
            border: 2px solid #f8d7da;
            border-radius: 15px;
            padding: 25px;
            background: #fffafa;
        }
        .danger-zone-title {
            color: #d9534f;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .danger-zone-text {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        /* NEW: Gallery styles only */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .gallery-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }
        .gallery-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .gallery-delete {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(217, 83, 79, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gallery-add {
            border: 2px dashed var(--rose-gold);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            color: var(--copper);
            transition: all 0.3s;
        }
        .gallery-add:hover {
            background: #fff9f8;
            border-color: var(--copper);
        }

        /* Extension tags */
        .ext-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 8px;
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

        /* Comment rating stars */
        .comment-rating {
            color: #ffc107;
            font-size: 0.85rem;
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
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center ms-2" href="listing_dashboard.php">
            <img src="images/logo.png" width="28" height="28" alt="logo" class="me-2">
            <span class="brand-text fw-bold text-white">Olievenhoutbosch Digital Hub</span>
        </a>

        <a href="listing_dashboard.php" class="back-link">
            Back
        </a>
    </div>
</nav>

<div class="container mt-5">
    <div class="row">
        <!-- Main Details Column -->
        <div class="col-lg-8">
            <div class="management-card">
                <!-- REMOVED: <img> hero image line -->

                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h2 style="color: var(--plum); font-weight: bold; margin-bottom: 5px;"><?php echo $listing['listing_name']; ?></h2>
                        <p class="text-muted mb-1"><?php echo $listing['category']; ?> &bull; <?php echo $listing['service_type']; ?></p>
                        <span class="type-badge"><?php echo $type_label; ?></span>
                        <div class="ext-list">
                            <?php foreach ($all_extensions as $idx => $ext): ?>
                                <span class="ext-tag <?php echo $idx === 0 ? 'primary' : ''; ?>">Ext <?php echo htmlspecialchars(trim($ext)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="status-badge <?php echo $statusClass; ?>">
                        Status: <?php echo $listing['verification_status']; ?>
                    </div>
                </div>

                <hr class="mb-4">

                <h5 class="fw-bold" style="color: var(--plum);">Description</h5>
                <p class="text-secondary"><?php echo $listing['description']; ?></p>

                <p class="mb-1"><strong>Pricing:</strong> <span style="color: var(--plum);"><?php echo $listing['price_description']; ?></span></p>

                <div class="mt-3">
                    <strong class="small">Payment Options:</strong>
                    <div class="mt-1">
                        <?php foreach ($payment_options as $pay): ?>
                            <span class="payment-tag"><?php echo htmlspecialchars($pay); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (in_array('EFT', $payment_options)): ?>
                    <p class="text-muted small mt-2">EFT details shared via messaging for data privacy.</p>
                    <?php endif; ?>
                </div>

                <div class="location-info mt-4">
                    <h6 class="fw-bold mb-2" style="color: var(--plum);">How Customers Receive</h6>
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
                    <?php if(!empty($listing['street_address'])): ?>
                        <p class="mb-0 text-secondary small"><strong>Address:</strong> <?php echo $listing['street_address']; ?>, Ext <?php echo $listing['extension']; ?></p>
                    <?php else: ?>
                        <p class="mb-0 text-secondary small"><em>Mobile service (No physical address listed)</em></p>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="mt-4 pt-2">
                    <a href="edit_listing.php?id=<?php echo $listing['listing_id']; ?>" class="btn btn-edit me-2">Edit Details</a>

                    <?php if($listing['verification_status'] == 'Unverified'): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="request_verification" class="btn btn-verify">Request Verification</button>
                        </form>
                    <?php elseif($listing['verification_status'] == 'Pending'): ?>
                        <button class="btn btn-verify" disabled>Verification Pending</button>
                    <?php elseif($listing['verification_status'] == 'Verified'): ?>
                        <button class="btn btn-verify" disabled>Verified</button>
                    <?php endif; ?>
                </div>

                <!-- Status Messages -->
                <?php if($listing['verification_status'] == 'Pending'): ?>
                <div class="pending-notice mt-3">
                    
                    <strong>Under Review:</strong> Your verification request is being reviewed by our admin team. You'll be notified once a decision is made.
                </div>
                <?php elseif($listing['verification_status'] == 'Verified'): ?>
                <div class="verified-notice mt-3">
                    
                    <strong>Verified Listing:</strong> Your listing is verified and will display a verified badge to customers.
                </div>
                <?php endif; ?>

                <?php if(!empty($verify_msg)): ?>
                    <div class="alert mt-3 <?php echo (strpos($verify_msg, 'don\'t qualify') !== false) ? 'alert-warning' : 'alert-success'; ?>">
                        <?php echo $verify_msg; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- NEW: Gallery Section (inserted here) -->
            <div class="management-card">
                <h5 class="fw-bold mb-3" style="color: var(--plum);">Work Photos</h5>
                <div class="gallery-grid">
                    <?php foreach ($gallery_images as $img): ?>
                    <div class="gallery-item">
                        <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Work photo">
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this photo?');">
                            <input type="hidden" name="delete_image" value="1">
                            <input type="hidden" name="image_id" value="<?php echo $img['image_id']; ?>">
                            <button type="submit" class="gallery-delete" title="Delete">×</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($gallery_images) < 5): ?>
                    <div class="gallery-add" onclick="document.getElementById('addPhotosInput').click()">
                        <i class="bi bi-plus-lg" style="font-size: 1.5rem;"></i>
                        <div class="small mt-1">Add Photos</div>
                        <div class="small text-muted"><?php echo count($gallery_images); ?>/5</div>
                    </div>
                    <form method="POST" enctype="multipart/form-data" style="display: none;" id="addPhotosForm">
                        <input type="file" name="new_photos[]" id="addPhotosInput" multiple accept="image/jpeg,image/png" onchange="document.getElementById('addPhotosForm').submit()">
                        <input type="hidden" name="add_photos" value="1">
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Feedback Section -->
            <div class="management-card">
                <h5 class="fw-bold mb-4" style="color: var(--plum);">User Comments & Feedback</h5>

                <!-- Average Rating Display -->
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
                    $initials = '';
                    $name_parts = explode(' ', $comment['full_name']);
                    foreach ($name_parts as $part) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    $comment_rating = $comment['rating'] ?? 0;
                ?>
                    <div class="comment-box mb-3">
                        <div class="d-flex align-items-start">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-size: 0.9rem; background: var(--rose-gold); color: var(--plum); font-weight: bold;">
                                <?php echo $initials; ?>
                            </div>
                            <div class="w-100">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo $comment['full_name']; ?></strong>
                                        <?php if ($comment_rating > 0): ?>
                                        <div class="comment-rating mb-1">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $comment_rating ? '-fill' : ''; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="text-muted small ms-1">(<?php echo $comment_rating; ?>/5)</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-muted small"><?php echo $comment['created_at']; ?></span>
                                </div>
                                <p class="text-secondary mb-1"><?php echo $comment['comment_text']; ?></p>
                                <?php if(!empty($comment['image_path'])): ?>
                                    <img src="<?php echo $comment['image_path']; ?>" class="comment-image" alt="Comment image">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            </div>

        <!-- Sidebar: Performance, Tips, Danger Zone -->
        <div class="col-lg-4">
            <div class="management-card">
                <h5 class="fw-bold mb-3" style="color: var(--plum);">Performance</h5>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $listing['page_views']; ?></div>
                            <div class="small text-muted">Page Views</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-number">★ <?php echo $avg_rating; ?></div>
                            <div class="small text-muted">Avg. Rating</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert shadow-sm" style="background-color: #e3f2fd; border: none; border-radius: 15px; color: #0d47a1;">
                
                <strong>Verification Tip:</strong> 4+ star ratings, consistent views, and positive feedback improve chances of getting verified!
            </div>

            <!-- Danger Zone - appears LAST on mobile (in right column, after tip) -->
            <div class="danger-zone">
                <div class="d-flex align-items-center gap-2 mb-2">
                    
                    <h5 class="danger-zone-title mb-0">Danger Zone</h5>
                </div>
                <p class="danger-zone-text">
                    Deleting your listing is permanent. All data including comments, ratings, and verification history will be removed. This action cannot be undone.
                </p>
                <button class="btn-delete-listing" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    Delete This Listing
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="border-bottom: 2px solid #f8d7da;">
                <h5 class="modal-title fw-bold" style="color: #d9534f;">
                    Delete Listing
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <p class="text-secondary">
                    Are you sure you want to delete <strong style="color: var(--plum);">"<?php echo $listing['listing_name']; ?>"</strong>?
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Register Service Worker for offline support -->
<script>
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