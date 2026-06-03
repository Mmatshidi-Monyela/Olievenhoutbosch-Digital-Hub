<?php
session_start();

if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
    /** @var mysqli $conn */
} else {
    $conn = null;
}

// Strict access: only Provider or Both allowed
$role = $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['Provider', 'Both'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = $_SESSION['full_name'] ?? 'Guest';
$first_name = explode(' ', $display_name)[0];
$avatar_letter = strtoupper(substr($display_name, 0, 1));

$listings = [];
if ($conn) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM listing WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $listings[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$notifications = [];
if (isset($_SESSION['dashboard_message'])) {
    $notifications[] = ['type' => 'success', 'message' => $_SESSION['dashboard_message']];
    unset($_SESSION['dashboard_message']);
}
if (isset($_SESSION['provider_notification'])) {
    $notifications[] = $_SESSION['provider_notification'];
    unset($_SESSION['provider_notification']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listing Dashboard - Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #c99383;
            --blush: #d8b2a7;
            --copper: #ba745f;
            --light-grey: #e6e6e6;
            --white: #ffffff;
        }

        body { 
            background-color: var(--light-grey); 
            font-family: "Segoe UI", sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .top-nav {
            background-color: var(--plum) !important;
            min-height: 60px;
            padding: 0.5rem 1rem;
            border-bottom: 3px solid var(--rose-gold);
        }

        .brand-text {
            font-size: 1.1rem;
            font-weight: bold;
            color: white;
            white-space: nowrap;
            font-size: clamp(0.75rem, 2vw, 1.1rem);
        }

        .dashboard-header { 
            color: var(--plum); 
            font-weight: bold; 
            margin-top: 20px; 
        }

        .listing-card {
            background: white;
            border-radius: 15px;
            border: none;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        .listing-card:hover { 
            transform: translateY(-10px); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }

        .badge-verified { background-color: var(--rose-gold); color: white; }
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-unverified { background-color: #6c757d; color: white; }

        .btn-register {
            background-color: var(--rose-gold);
            color: white;
            border-radius: 8px;
            font-weight: bold;
            border: none;
            padding: 10px 20px;
        }
        .btn-register:hover { 
            background-color: var(--copper); 
            opacity: 0.9;
            color: white; 
        }

        .text-price { 
            color: var(--copper); font-weight: bold; 
        }

        .btn-outline-plum {
            border: 1px solid var(--plum);
            color: var(--plum);
            border-radius: 8px;
        }

        .btn-outline-plum:hover {
            background-color: var(--plum);
            color: white;
        }

        .main-content {
            padding-left: 50px;
            padding-right: 50px;
        }

        .profile-avatar {
            width: 35px; 
            height: 35px; 
            background-color: var(--rose-gold); 
            color: var(--plum); 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: bold;
            flex-shrink: 0;
        }

        .notification-banner {
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .notification-danger {
            background: #ffe5e5;
            border-left: 4px solid #d9534f;
            color: #721c24;
        }
        .notification-success {
            background: #e6ffed;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .photo-badge {
            font-size: 0.75rem;
            color: #888;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .ext-badge {
            font-size: 0.75rem;
            background: #e3f2fd;
            color: #0d47a1;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .brand-text {
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        @media (max-width: 991px) {
            .main-content { padding-left: 20px; padding-right: 20px; }
        }

        @media (max-width: 576px) {
            .brand-text {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .brand-text {
                display: block;
                max-width: 140px;
                line-height: 1.2;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar top-nav sticky-top">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="images/logo.png" width="30" height="30" alt="logo" class="me-2">
                <span class="brand-text d-inline">Olievenhoutbosch Digital Hub</span>
            </a>

            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <div class="d-flex align-items-center" role="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="text-end me-2 d-none d-md-block text-white">
                            <p class="mb-0 fw-bold" style="font-size: 0.8rem;"><?php echo htmlspecialchars($first_name); ?></p>
                            <p class="mb-0 opacity-75" style="font-size: 0.65rem;"><?php echo $role === 'Both' ? 'Customer & Provider' : 'Service Provider'; ?></p>
                        </div>
                        <div class="profile-avatar"><?php echo $avatar_letter; ?></div>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <?php if ($role === 'Both'): ?>
                        <li><a class="dropdown-item small" href="main.php"><i class="bi bi-grid me-2"></i> Browse Marketplace</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item small" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small" href="notifications.php"><i class="bi bi-bell me-2"></i> Notifications</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small" href="messages.php?view=received"><i class="bi bi-envelope me-2"></i> Messages</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid main-content">

        <?php foreach ($notifications as $notif): ?>
        <div class="notification-banner notification-<?php echo $notif['type']; ?>">
            <i class="bi bi-<?php echo $notif['type'] === 'danger' ? 'exclamation-triangle' : 'check-circle'; ?>-fill fs-4"></i>
            <div>
                <strong><?php echo $notif['type'] === 'danger' ? 'Verification Rejected' : 'Success'; ?></strong>
                <p class="mb-0 small"><?php echo $notif['message']; ?></p>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
            <h3 class="dashboard-header">Your Listings</h3>
            <a href="add_listing.php" class="btn btn-register">+ Add New Listing</a>
        </div>

        <div class="row">

            <?php foreach($listings as $row): 
                $photo_count = 0;
                if ($conn) {
                    $pc_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM listing_images WHERE listing_id = " . intval($row['listing_id']));
                    if ($pc_res) $photo_count = mysqli_fetch_assoc($pc_res)['c'] ?? 0;
                }

                $ext_display = 'Ext ' . $row['extension'];
                $ext_badge = '';
                if (!empty($row['service_extensions'])) {
                    $ext_display = 'Multiple Ext';
                    $ext_badge = '<span class="ext-badge">Multiple Ext</span>';
                }

                $statusClass = 'badge-unverified';
                if ($row['verification_status'] == 'Verified') $statusClass = 'badge-verified';
                if ($row['verification_status'] == 'Pending') $statusClass = 'badge-pending';
            ?>
                <div class="col-md-4 mb-4">
                    <div class="card listing-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold" style="color: var(--plum);"><?php echo htmlspecialchars($row['listing_name']); ?></h5>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $row['verification_status']; ?>
                                </span>
                            </div>

                            <p class="text-muted small mb-1"><strong>Category:</strong> <?php echo htmlspecialchars($row['category']); ?></p>
                            <p class="text-muted small mb-2">
                                <strong>Location:</strong> 
                                <?php echo $ext_badge ? $ext_badge : htmlspecialchars($ext_display); ?>
                            </p>

                            <p class="card-text text-secondary" style="font-size: 14px;">
                                <?php echo htmlspecialchars($row['description']); ?>
                            </p>

                            <?php if ($photo_count > 0): ?>
                            <div class="photo-badge mb-2">
                                <i class="bi bi-images"></i>
                                <span><?php echo $photo_count; ?> photo<?php echo $photo_count > 1 ? 's' : ''; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer bg-transparent border-0 pb-3">
                            <hr class="opacity-25">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-price"><?php echo htmlspecialchars($row['price_description']); ?></span>
                                <a href="listing_details_owner.php?id=<?php echo $row['listing_id']; ?>" class="btn btn-sm btn-outline-plum px-3">Edit Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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