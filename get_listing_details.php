<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    echo '<p class="text-danger">Unauthorized</p>';
    exit();
}

$conn = null;
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
}

if (!$conn) {
    echo '<p class="text-danger">Database connection failed</p>';
    exit();
}

$listing_id = intval($_GET['id'] ?? 0);
if ($listing_id <= 0) {
    echo '<p class="text-danger">Invalid service ID</p>';
    exit();
}

// Fetch listing + owner
$stmt = mysqli_prepare($conn, "
    SELECT l.*, u.full_name as owner_name, u.extension as owner_ext
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
    echo '<p class="text-danger">Service not found</p>';
    exit();
}

// Fetch comments
$comments = [];
$stmt2 = mysqli_prepare($conn, "
    SELECT c.*, u.full_name 
    FROM comment c 
    JOIN useraccount u ON c.user_id = u.user_id 
    WHERE c.listing_id = ? 
    ORDER BY c.created_at DESC
");
mysqli_stmt_bind_param($stmt2, 'i', $listing_id);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);
while ($row = mysqli_fetch_assoc($result2)) {
    $comments[] = $row;
}
mysqli_stmt_close($stmt2);

// Calculate average
$avg_rating = 0;
$rated_count = 0;
if (count($comments) > 0) {
    $sum = 0;
    foreach ($comments as $c) {
        if ($c['rating'] > 0) {
            $sum += $c['rating'];
            $rated_count++;
        }
    }
    if ($rated_count > 0) {
        $avg_rating = round($sum / $rated_count, 1);
    }
}

// Extensions
$all_extensions = [$listing['extension']];
if (!empty($listing['service_extensions'])) {
    $additional = array_map('trim', explode(',', $listing['service_extensions']));
    $all_extensions = array_merge($all_extensions, $additional);
}

// Check alerts
$flaggedKeywords = ['scam', 'terrible', 'worst', 'never again', 'rip off', 'fraud', 
                    'disappointed', 'horrible', 'awful', 'garbage', 'trash', 
                    'waste of money', 'broken', 'fake', 'liar', 'stole'];
$hasKeywordAlert = false;
$alertComments = [];
foreach ($comments as $c) {
    $lower = strtolower($c['comment_text']);
    foreach ($flaggedKeywords as $kw) {
        if (strpos($lower, $kw) !== false) {
            $hasKeywordAlert = true;
            $alertComments[] = $c;
            break;
        }
    }
}
$hasRatingAlert = $avg_rating > 0 && $avg_rating < 3.5;

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

<!-- ===== HEADER ===== -->
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h5 class="fw-bold mb-1" style="color: var(--plum);"><?php echo htmlspecialchars($listing['listing_name']); ?></h5>
            <p class="text-muted mb-1" style="font-size: 0.8rem;">
                <?php echo htmlspecialchars($listing['category']); ?> • 
                <?php echo htmlspecialchars($listing['owner_name']); ?> (Ext <?php echo htmlspecialchars($listing['owner_ext']); ?>)
            </p>
        </div>
        <span class="badge bg-<?php echo $listing['verification_status'] === 'Verified' ? 'success' : ($listing['verification_status'] === 'Pending' ? 'warning text-dark' : 'secondary'); ?>" style="font-size: 0.7rem;">
            <?php echo $listing['verification_status']; ?>
        </span>
    </div>
    <div class="d-flex gap-1 flex-wrap mt-1">
        <?php foreach ($all_extensions as $ext): ?>
            <span class="badge" style="background: var(--plum); font-size: 0.65rem; padding: 3px 8px;">Ext <?php echo htmlspecialchars(trim($ext)); ?></span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===== DESCRIPTION ===== -->
<div class="mb-3">
    <p class="text-secondary mb-2" style="font-size: 0.85rem; line-height: 1.5;">
        <?php echo nl2br(htmlspecialchars($listing['description'])); ?>
    </p>
    <p class="text-muted mb-0" style="font-size: 0.75rem;">
        <i class="bi bi-geo-alt me-1"></i><?php echo !empty($listing['delivery_mode']) ? getDeliveryLabel($listing['delivery_mode']) : '—'; ?>
        <?php if (!empty($listing['street_address'])): ?>
            • <?php echo htmlspecialchars($listing['street_address']); ?>
        <?php endif; ?>
    </p>
</div>

<!-- ===== STATS BAR ===== -->
<div class="d-flex gap-3 mb-3 py-2 px-3 rounded-3" style="background: #f8f6fa;">
    <div class="d-flex align-items-center gap-1">
        <?php if ($avg_rating > 0): ?>
            <span class="fw-bold" style="color: var(--plum); font-size: 1.1rem;"><?php echo $avg_rating; ?></span>
            <span class="text-warning" style="font-size: 0.75rem;">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="bi bi-star<?php echo $i <= round($avg_rating) ? '-fill' : ''; ?>"></i>
                <?php endfor; ?>
            </span>
            <span class="text-muted" style="font-size: 0.7rem;">(<?php echo $rated_count; ?>)</span>
        <?php else: ?>
            <span class="text-muted" style="font-size: 0.8rem;">No ratings</span>
        <?php endif; ?>
    </div>
    <div class="border-start ps-3" style="border-color: #ddd !important;">
        <span class="text-muted" style="font-size: 0.75rem;"><?php echo count($comments); ?> reviews</span>
    </div>
    <div class="border-start ps-3" style="border-color: #ddd !important;">
        <span class="text-muted" style="font-size: 0.75rem;"><?php echo $listing['page_views'] ?? 0; ?> views</span>
    </div>
    <div class="border-start ps-3" style="border-color: #ddd !important;">
        <span class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-truck me-1"></i><?php echo !empty($listing['delivery_mode']) ? getDeliveryLabel($listing['delivery_mode']) : '—'; ?></span>
    </div>
    <div class="border-start ps-3" style="border-color: #ddd !important;">
        <span class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($listing['price_description'] ?? '—'); ?></span>
    </div>
</div>

<!-- ===== ALERTS ===== -->
<?php if ($hasKeywordAlert || $hasRatingAlert): ?>
<div class="mb-3">
    <?php if ($hasKeywordAlert): ?>
    <div class="d-flex align-items-center gap-2 p-2 rounded-2 mb-1" style="background: #ffe5e5; font-size: 0.8rem;">
        <i class="bi bi-exclamation-triangle-fill text-danger"></i>
        <span><strong class="text-danger">Keyword Alert:</strong> <?php echo count($alertComments); ?> flagged review(s)</span>
    </div>
    <?php endif; ?>
    <?php if ($hasRatingAlert): ?>
    <div class="d-flex align-items-center gap-2 p-2 rounded-2" style="background: #fff3cd; font-size: 0.8rem;">
        <i class="bi bi-star-half text-warning"></i>
        <span><strong class="text-warning">Rating Alert:</strong> Average <?php echo $avg_rating; ?> is below 3.5</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== CUSTOMER FEEDBACK ===== -->
<div>
    <h5 class="fw-bold mb-2" style="color: var(--plum); font-size: 0.85rem;">
        Customer Feedback
    </h5>

    <?php if (empty($comments)): ?>
        <p class="text-muted" style="font-size: 0.8rem;">No reviews yet.</p>
    <?php else: ?>
        <?php foreach ($comments as $comment): 
            $initials = '';
            $parts = explode(' ', $comment['full_name']);
            foreach ($parts as $p) $initials .= strtoupper(substr($p, 0, 1));
            $isFlagged = false;
            $lowerText = strtolower($comment['comment_text']);
            foreach ($flaggedKeywords as $kw) {
                if (strpos($lowerText, $kw) !== false) { $isFlagged = true; break; }
            }
        ?>
        <div class="d-flex align-items-start mb-2 p-2 rounded-2 <?php echo $isFlagged ? 'border border-danger' : ''; ?>" style="background: <?php echo $isFlagged ? '#fff5f5' : '#fafafa'; ?>;">
            <div class="rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" 
                 style="width: 28px; height: 28px; background: var(--rose-gold); color: var(--plum); font-weight: bold; font-size: 0.7rem;">
                <?php echo $initials; ?>
            </div>
            <div class="w-100" style="min-width: 0;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <strong style="font-size: 0.8rem;"><?php echo htmlspecialchars($comment['full_name']); ?></strong>
                        <?php if ($comment['rating'] > 0): ?>
                        <span class="text-warning" style="font-size: 0.7rem;">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi bi-star<?php echo $i <= $comment['rating'] ? '-fill' : ''; ?>"></i>
                            <?php endfor; ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($isFlagged): ?>
                        <span class="badge bg-danger" style="font-size: 0.55rem; padding: 2px 6px;">FLAGGED</span>
                        <?php endif; ?>
                    </div>
                    <span class="text-muted flex-shrink-0" style="font-size: 0.7rem;"><?php echo date('M j, Y', strtotime($comment['created_at'])); ?></span>
                </div>
                <p class="text-secondary mb-1" style="font-size: 0.8rem;"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                <?php if (!empty($comment['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($comment['image_path']); ?>" class="rounded-2" style="max-width: 100px; max-height: 100px; object-fit: cover;" alt="Review image">
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>