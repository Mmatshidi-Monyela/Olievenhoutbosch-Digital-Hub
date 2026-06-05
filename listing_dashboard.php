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
    <link rel="icon" type="image/png" href="images/logo.png"> 
    <link rel="apple-touch-icon" href="images/logo.png">
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
            height: 56px;
            padding: 0 16px;
            border-bottom: 3px solid var(--rose-gold);
            display: flex;
            align-items: center;
        }

        .brand-text {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* Shortened brand on small screens */
        .brand-short {
            display: none;
        }
        @media (max-width: 575.98px) {
            .brand-text {
                display: none;
            }
            .brand-short {
                display: inline;
                font-size: 0.85rem;
                font-weight: 700;
                color: white;
                white-space: nowrap;
            }
        }

        .dashboard-header { 
            color: var(--plum); 
            font-weight: bold; 
            margin-top: 20px; 
        }

        /* ===== SIMPLIFIED LISTING CARD ===== */
        .listing-card {
            background: white;
            border-radius: 16px;
            border: none;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(35, 3, 68, 0.06);
            transition: all 0.25s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .listing-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 24px rgba(35, 3, 68, 0.1);
        }
        .listing-card:active {
            transform: scale(0.98);
        }

        .card-title {
            color: var(--plum);
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.3;
            word-break: break-word;
        }

        .status-verified { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-unverified { background-color: #eee; color: #666; }
        .status-badge {
            border-radius: 8px;
            padding: 8px 15px;
            font-weight: bold;
            font-size: 0.9rem;
            display: inline-block;
        }

        .btn-manage {
            border: 1.5px solid var(--plum);
            color: var(--plum);
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 20px;
            background: transparent;
            transition: all 0.2s ease;
            width: 100%;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        .btn-manage:hover {
            background-color: var(--plum);
            color: white;
        }

        .btn-register {
            background-color: var(--rose-gold);
            color: white;
            border-radius: 8px;
            font-weight: bold;
            border: none;
            padding: 10px 20px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .btn-register:hover { 
            background-color: var(--copper); 
            opacity: 0.9;
            color: white; 
        }
        /* Smaller button on mobile */
        @media (max-width: 575.98px) {
            .btn-register {
                padding: 6px 12px;
                font-size: 0.8rem;
                border-radius: 6px;
            }
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

        @media (max-width: 991px) {
            .main-content { padding-left: 20px; padding-right: 20px; }
        }

        @media (max-width: 576px) {
            .brand-text {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar top-nav sticky-top">
        <div class="container-fluid d-flex justify-content-between align-items-center" style="max-width:1400px;margin:0 auto;width:100%;">
            <a class="navbar-brand d-flex align-items-center" href="index.php" style="text-decoration:none;">
                <img src="images/logo.png" width="28" height="28" alt="logo" class="me-2" style="flex-shrink:0;">
                <span class="brand-text">Olievenhoutbosch Digital Hub</span>
                <span class="brand-short">Olievenhoutbosch DH</span>
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

        <div class="row g-4">

            <?php foreach($listings as $row): 
                $statusClass = 'status-unverified';
                if ($row['verification_status'] == 'Verified') $statusClass = 'status-verified';
                if ($row['verification_status'] == 'Pending') $statusClass = 'status-pending';
            ?>
                <div class="col-md-4 col-lg-3">
                    <a href="listing_details_owner.php?id=<?php echo $row['listing_id']; ?>" class="listing-card">
                        <div class="card-body d-flex flex-column justify-content-between" style="padding: 20px;">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($row['listing_name']); ?>
                                </h5>
                                <span class="status-badge <?php echo $statusClass; ?>" style="font-size: 0.75rem; flex-shrink: 0; padding: 6px 12px;">
                                    <?php echo $row['verification_status']; ?>
                                </span>
                            </div>
                            <div class="mt-2 pt-3" style="border-top: 1px solid #f0f0f0;">
                                <span class="btn-manage">Manage Listing</span>
                            </div>
                        </div>
                    </a>
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