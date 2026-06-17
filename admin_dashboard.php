<?php
session_start();


if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get admin data from session
$admin_name = $_SESSION['full_name'] ?? 'Administrator';
$admin_first_name = explode(' ', $admin_name)[0];
$admin_avatar = strtoupper(substr($admin_name, 0, 1));


$conn = null;
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
}


$totalListings = 0;
$activeUsers = 0;
$pendingVerifications = 0;

if ($conn) {
    $statsRes = mysqli_query($conn, 'SELECT COUNT(*) as c FROM listing WHERE is_active = 1');
    if ($statsRes) $totalListings = mysqli_fetch_assoc($statsRes)['c'] ?? 0;

    $userRes = mysqli_query($conn, 'SELECT COUNT(*) as c FROM useraccount WHERE is_active = 1');
    if ($userRes) $activeUsers = mysqli_fetch_assoc($userRes)['c'] ?? 0;

    $pendRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM listing WHERE verification_status = 'Pending'");
    if ($pendRes) $pendingVerifications = mysqli_fetch_assoc($pendRes)['c'] ?? 0;
}


$extensions = [];
if ($conn) {
    $extRes = mysqli_query($conn, 'SELECT extension, COUNT(*) as cnt FROM useraccount WHERE extension IS NOT NULL AND extension != "" GROUP BY extension ORDER BY cnt DESC');

    $colors = ['var(--plum)', 'var(--rose-gold)', '#0dcaf0', '#28a745', '#ffc107', '#dc3545', '#6f42c1'];
    $i = 0;
    $totalExtUsers = 0;

    $totalRes = mysqli_query($conn, 'SELECT COUNT(*) as c FROM useraccount WHERE extension IS NOT NULL AND extension != ""');
    if ($totalRes) $totalExtUsers = mysqli_fetch_assoc($totalRes)['c'] ?? 1;
    if ($totalExtUsers == 0) $totalExtUsers = 1;

    while ($extRow = mysqli_fetch_assoc($extRes)) {
        $extensions[] = [
            'name' => 'Ext ' . htmlspecialchars($extRow['extension']),
            'percentage' => round(($extRow['cnt'] / $totalExtUsers) * 100),
            'color' => $colors[$i % count($colors)],
            'count' => $extRow['cnt']
        ];
        $i++;
    }
}


$topListings = [];
if ($conn) {
    // Calculate true average from comment table, only services with actual ratings
    $topRes = mysqli_query($conn, "
        SELECT l.listing_id, l.listing_name, l.extension, 
               ROUND(AVG(c.rating), 1) as avg_rating,
               COUNT(c.comment_id) as review_count
        FROM listing l
        INNER JOIN comment c ON l.listing_id = c.listing_id
        WHERE l.is_active = 1 AND c.rating > 0
        GROUP BY l.listing_id, l.listing_name, l.extension
        HAVING avg_rating > 0
        ORDER BY avg_rating DESC, review_count DESC, l.listing_name ASC
        LIMIT 3
    ");

    $rank = 1;
    while ($topRow = mysqli_fetch_assoc($topRes)) {
        $topListings[] = [
            'rank' => $rank++,
            'name' => $topRow['listing_name'],
            'ext' => 'Extension ' . htmlspecialchars($topRow['extension']),
            'rating' => floatval($topRow['avg_rating']),
            'review_count' => $topRow['review_count']
        ];
    }
}


$recentRequests = [];
if ($conn) {
    $reqRes = mysqli_query($conn, "SELECT listing_name, created_at FROM listing WHERE verification_status = 'Pending' ORDER BY created_at DESC LIMIT 3");
    while ($reqRow = mysqli_fetch_assoc($reqRes)) {
        $recentRequests[] = [
            'listing_name' => $reqRow['listing_name'],
            'date' => date('M j, Y', strtotime($reqRow['created_at']))
        ];
    }
}


$keywordAlertCount = 0;
$ratingAlertCount = 0;

if ($conn) {
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
                        'terrible service', 'broken', 'damaged', 'poor quality', 'cheap materials'];

    $keywordConditions = [];
    foreach ($flaggedKeywords as $kw) {
        $keywordConditions[] = "comment_text LIKE '%" . mysqli_real_escape_string($conn, $kw) . "%'";
    }

    if (!empty($keywordConditions)) {
        $kwQuery = "SELECT COUNT(DISTINCT listing_id) as c FROM comment WHERE " . implode(' OR ', $keywordConditions);
        $kwRes = mysqli_query($conn, $kwQuery);
        if ($kwRes) $keywordAlertCount = mysqli_fetch_assoc($kwRes)['c'] ?? 0;
    }

    // Services with low average rating (< 3.5) calculated from comments
    $ratingQuery = "
        SELECT COUNT(*) as c FROM (
            SELECT listing_id, AVG(rating) as avg_rating 
            FROM comment 
            WHERE rating > 0 
            GROUP BY listing_id 
            HAVING avg_rating < 3.5
        ) as low_rated
    ";
    $ratingRes = mysqli_query($conn, $ratingQuery);
    if ($ratingRes) $ratingAlertCount = mysqli_fetch_assoc($ratingRes)['c'] ?? 0;
}

$allGoodCount = max(0, $totalListings - $keywordAlertCount - $ratingAlertCount);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Olievenhoutbosch Digital Hub</title>
    <link rel="icon" type="image/png" href="images/logo.png"> 
    <link rel="apple-touch-icon" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #c99383;
            --rose-light: #fbe5e6;
            --text-gray: #6c757d;
        }

        /* Brand name swap: full on desktop, short on mobile */
        .brand-text.full-name { display: inline !important; }
        .brand-text.short-name { display: none !important; }

        @media (max-width: 575.98px) {
            .brand-text.full-name { display: none !important; }
            .brand-text.short-name { display: inline !important; }
        }

        body { 
            background-color: #f4f7f6; 
            font-family: 'Inter', sans-serif; 
        }

        .navbar.top-nav { 
            background-color: var(--plum); 
            padding: 0.6rem 1rem; 
            border-bottom: 3px solid var(--rose-gold);
        }

        .brand-text {
            font-size: clamp(0.8rem, 2.2vw, 1.1rem);
            white-space: nowrap;
        }

        .nav-link { 
            color: rgba(255,255,255,0.8) !important; 
            font-weight: 500; 
            border-bottom: 2px solid transparent;
        }

        .nav-link.active { 
            color: var(--rose-gold) !important; 
            border-bottom: 2px solid var(--rose-gold); 
        }

        .profile-avatar {
            width: 35px; height: 35px;
            background-color: var(--rose-gold);
            color: var(--plum);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold;
        }

        .stat-box {
            background: white; border-radius: 12px; padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border-top: 4px solid var(--rose-gold);
        }
        .stat-box.pending-box { border-top-color: #ffc107; }
        .stat-box.active-box { border-top-color: #28a745; }

        .rank-circle {
            width: 32px; height: 32px; background: var(--rose-gold);
            color: var(--plum); border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            font-weight: bold; flex-shrink: 0;
        }

        .card {
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-radius: 15px;
        }

        .request-preview-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .request-preview-item:last-child { border-bottom: none; }

        .status-dot {
            width: 8px; height: 8px;
            background: #ffc107;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .alert-card {
            background: white;
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border-left: 4px solid;
            transition: transform 0.2s;
            text-decoration: none;
            color: inherit;
        }
        .alert-card:hover { transform: translateY(-2px); }
        .alert-card.keyword { border-left-color: #d9534f; }
        .alert-card.rating { border-left-color: #ffc107; }
        .alert-card.good { border-left-color: #28a745; }

        .alert-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .alert-icon.keyword { background: #ffe5e5; color: #d9534f; }
        .alert-icon.rating { background: #fff3cd; color: #856404; }
        .alert-icon.good { background: #e6ffed; color: #28a745; }

        .alert-count { font-size: 1.4rem; font-weight: 700; line-height: 1; }
        .alert-label { font-size: 0.8rem; color: var(--text-gray); }

        .ext-bar-container {
            max-height: 300px;
            overflow-y: auto;
        }
        .ext-bar-container::-webkit-scrollbar {
            width: 6px;
        }
        .ext-bar-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .ext-bar-container::-webkit-scrollbar-thumb {
            background: var(--rose-gold);
            border-radius: 3px;
        }

        @media (max-width: 991px) {
            .navbar-toggler { order: 1; }
            .navbar-brand { order: 2; margin-right: auto !important; display: flex !important; align-items: center; }
            .admin-profile-wrapper { order: 3; }
            .navbar-collapse { order: 4; width: 100%; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg top-nav sticky-top">
        <div class="container-fluid">
            <button class="navbar-toggler border-0 p-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <i class="bi bi-list text-white fs-2"></i>
            </button>

            <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
                <img src="images/logo.png" width="28" height="28" alt="logo" class="me-2">
                <span class="brand-text fw-bold text-white full-name">Olievenhoutbosch Digital Hub</span>
                <span class="brand-text fw-bold text-white short-name">Olievenhoutbosch DH</span>
            </a>

            <div class="collapse navbar-collapse justify-content-center" id="adminNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active mx-2" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link mx-2" href="admin_listings.php">Listings</a></li>
                    <li class="nav-item"><a class="nav-link mx-2" href="admin_requests.php">Requests</a></li>
                    <li class="nav-item"><a class="nav-link mx-2" href="admin_users.php">Users</a></li>
                </ul>
            </div>

            <div class="dropdown admin-profile-wrapper">
                <div class="d-flex align-items-center" role="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                    <div class="text-end me-2 d-none d-md-block text-white">
                        <p class="mb-0 fw-bold" style="font-size: 0.8rem;"><?php echo htmlspecialchars($admin_first_name); ?></p>
                        <p class="mb-0 opacity-75" style="font-size: 0.65rem;">System Administrator</p>
                    </div>
                    <div class="profile-avatar"><?php echo $admin_avatar; ?></div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="profileDropdown">
                    <li><a class="dropdown-item small" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item small text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 mt-4">
        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4">
                <div class="stat-box text-center">
                    <small class="text-muted">Total Listings</small>
                    <h3 class="fw-bold mb-0"><?php echo number_format($totalListings); ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="stat-box text-center active-box">
                    <small class="text-muted">Active Users</small>
                    <h3 class="fw-bold mb-0"><?php echo number_format($activeUsers); ?></h3>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="stat-box text-center pending-box">
                    <small class="text-muted">Pending Verifications</small>
                    <h3 class="fw-bold mb-0 text-warning"><?php echo $pendingVerifications; ?></h3>
                </div>
            </div>
        </div>

        <!-- Listings Overview -->
        <h6 class="fw-bold mb-3" style="color: black;">Listings Overview</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="admin_listings.php" class="alert-card keyword">
                    <div class="alert-icon keyword"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div>
                        <div class="alert-count" style="color: #d9534f;"><?php echo $keywordAlertCount; ?></div>
                        <div class="alert-label">Keyword Alerts</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="admin_listings.php" class="alert-card rating">
                    <div class="alert-icon rating"><i class="bi bi-star-half"></i></div>
                    <div>
                        <div class="alert-count" style="color: #856404;"><?php echo $ratingAlertCount; ?></div>
                        <div class="alert-label">Rating Alerts</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="admin_listings.php" class="alert-card good">
                    <div class="alert-icon good"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="alert-count" style="color: #28a745;"><?php echo $allGoodCount; ?></div>
                        <div class="alert-label">All Good</div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Users by Extension — ALL EXTENSIONS -->
            <div class="col-lg-5">
                <div class="card p-4 h-100">
                    <h6 class="fw-bold mb-4">Users by Extension</h6>
                    <?php if (empty($extensions)): ?>
                        <p class="text-muted small">No extension data available yet.</p>
                    <?php else: ?>
                        <div class="ext-bar-container">
                            <?php foreach ($extensions as $ext): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small fw-bold mb-1">
                                    <span><?php echo $ext['name']; ?></span>
                                    <span><?php echo $ext['percentage']; ?>%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" style="width: <?php echo $ext['percentage']; ?>%; background-color: <?php echo $ext['color']; ?>"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Rated Listings — ONLY ACTUALLY RATED -->
            <div class="col-lg-4">
                <div class="card p-4 h-100">
                    <h6 class="fw-bold mb-4">Top Rated Listings</h6>
                    <?php if (empty($topListings)): ?>
                        <p class="text-muted small">No rated listings yet.</p>
                    <?php else: ?>
                        <?php foreach ($topListings as $listing): ?>
                        <div class="d-flex align-items-center mb-3 p-2 rounded-3 border">
                            <div class="rank-circle me-3"><?php echo $listing['rank']; ?></div>
                            <div class="flex-grow-1">
                                <p class="mb-0 small fw-bold"><?php echo htmlspecialchars($listing['name']); ?></p>
                                <small class="text-muted"><?php echo $listing['ext']; ?></small>
                            </div>
                            <span class="badge bg-light text-dark"><?php echo $listing['rating']; ?> ★</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Pending Requests -->
            <div class="col-lg-3">
                <div class="card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Pending Requests</h6>
                        <a href="admin_requests.php" class="small" style="color: var(--plum);">View All</a>
                    </div>
                    <?php if (empty($recentRequests)): ?>
                        <p class="text-muted small">No pending requests.</p>
                    <?php else: ?>
                        <?php foreach ($recentRequests as $req): ?>
                        <div class="request-preview-item">
                            <div class="d-flex align-items-center">
                                <span class="status-dot"></span>
                                <div>
                                    <p class="mb-0 small fw-bold"><?php echo htmlspecialchars($req['listing_name']); ?></p>
                                    <small class="text-muted"><?php echo $req['date']; ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>