<?php
session_start();
$_SESSION['last_dashboard'] = 'main.php';

// Access control: only Customer and Both allowed
$role = $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['Customer', 'Both'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db_connect.php';
/** @var mysqli $conn */
// Get user data for header
$display_name = $_SESSION['full_name'] ?? 'Guest';
$first_name = explode(' ', $display_name)[0];
$user_role = $_SESSION['user_role'] ?? 'Customer';
$user_role_display = match($user_role) {
    'Provider' => 'Provider',
    'Admin' => 'Administrator',
    'Both' => 'Customer & Provider',
    default => 'Customer',
};
$avatar_letter = strtoupper(substr($display_name, 0, 1));

// Fetch all active listings from database
$listings_query = "SELECT l.*, u.full_name as owner_name 
    FROM listing l 
    JOIN useraccount u ON l.user_id = u.user_id 
    WHERE l.is_active = 1 
    ORDER BY l.created_at DESC";
$listings_result = mysqli_query($conn, $listings_query);
$db_listings = [];
while ($row = mysqli_fetch_assoc($listings_result)) {
    $db_listings[] = $row;
}
mysqli_free_result($listings_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace - Olievenhoutbosch Digital Hub</title>
    <link rel="icon" type="image/png" href="images/logo.png"> 
    <link rel="apple-touch-icon" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
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
            margin: 0;
        }

        /*      NAVBAR      */
        .top-nav {
            background-color: var(--plum) !important;
            height: 56px;
            padding: 0 16px;
            border-bottom: 3px solid var(--rose-gold);
            display: flex;
            align-items: center;
        }

        .nav-inner {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0 16px;
        }

        .nav-brand-section {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 1;
            min-width: 0;
            text-decoration: none;
        }

        .nav-brand-section img {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
        }

        .brand-text {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        .nav-search-section {
            flex: 1;
            max-width: 600px;
            display: flex;
            justify-content: center;
            padding: 0 16px;
        }

        .search-container {
            background: white;
            border-radius: 50px;
            padding: 4px 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            display: flex;
            align-items: center;
        }

        .search-container .form-select,
        .search-container .form-control {
            font-size: clamp(0.8rem, 1.2vw, 0.9rem);
        }

        .nav-actions-section {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .profile-avatar {
            width: 36px; 
            height: 36px; 
            background-color: var(--rose-gold); 
            color: var(--plum); 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: bold;
            flex-shrink: 0;
            font-size: 0.9rem;
        }

        .profile-name {
            font-size: clamp(0.7rem, 1.2vw, 0.85rem);
            font-weight: 700;
            color: white;
            margin-bottom: 0;
            line-height: 1.2;
        }

        .profile-role {
            font-size: clamp(0.6rem, 1vw, 0.7rem);
            color: rgba(255,255,255,0.75);
            margin-bottom: 0;
            line-height: 1.2;
        }

        /*      CATEGORY BAR      */
        .cat-bar {
            background: #e9ecef;
            border-bottom: 1px solid #eee;
            padding: 8px 0;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .cat-bar::-webkit-scrollbar { display: none; }

        .cat-bar-inner {
            display: flex;
            gap: 0;
            padding: 0 15px;
            min-width: max-content;
        }

        .cat-item { position: relative; display: inline-block; }

        .cat-btn {
            background: none;
            border: none;
            color: var(--plum);
            font-weight: 700;
            font-size: clamp(0.75rem, 1.8vw, 0.95rem);
            padding: 0.4rem clamp(0.6rem, 1.5vw, 1.2rem);
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cat-btn:hover { color: var(--copper); }

        .cat-btn i {
            font-size: clamp(0.6rem, 1.2vw, 0.75rem);
            transition: transform 0.2s;
        }

        .cat-btn.active i { transform: rotate(180deg); }

        .cat-dropdown {
            display: none;
            position: fixed;
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            min-width: 220px;
            padding: 8px 0;
            z-index: 99999;
        }

        .cat-dropdown.show { display: block; }

        .cat-dropdown a {
            display: block;
            padding: 10px 20px;
            color: var(--plum);
            text-decoration: none;
            font-weight: 500;
            font-size: clamp(0.8rem, 1.5vw, 0.9rem);
        }

        .cat-dropdown a:hover {
            background: var(--rose-gold);
            color: var(--plum);
        }

        .dropdown-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 99998;
        }

        .dropdown-overlay.show { display: block; }

        /*      MOBILE SEARCH AREA      */
        .mobile-search-area {
            background-color: var(--plum);
            padding: clamp(8px, 2vw, 14px) clamp(12px, 3vw, 16px);
            display: none;
        }
        .mobile-search-area.show { display: block; }

        /*      MAIN CONTENT      */
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: clamp(12px, 2vw, 24px);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(12px, 2vw, 24px);
        }

        .results-title {
            font-size: clamp(1rem, 2.5vw, 1.35rem);
            font-weight: 700;
            color: var(--plum);
            margin: 0;
        }

        .results-count {
            color: var(--plum);
            font-weight: 600;
            font-size: clamp(0.8rem, 1.5vw, 0.95rem);
        }

        /* ---- Mobile Cards ---- */
        .mobile-listings {
            display: flex;
            flex-direction: column;
            gap: clamp(10px, 2.5vw, 16px);
        }

        .listing-card-mobile {
            background: white;
            border-radius: clamp(14px, 2.5vw, 20px);
            padding: clamp(16px, 3.5vw, 24px);
            box-shadow: 0 2px 8px rgba(35, 3, 68, 0.06);
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: block;
            border: 1px solid rgba(35, 3, 68, 0.04);
            position: relative;
            overflow: hidden;
        }

        .listing-card-mobile::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--plum);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .listing-card-mobile:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(35, 3, 68, 0.1);
        }

        .listing-card-mobile:hover::before {
            opacity: 1;
        }

        .listing-card-mobile:active {
            transform: scale(0.98);
        }

        /* Title row: name + verification badge */
        .card-mobile-title-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: clamp(10px, 2vw, 14px);
        }

        .card-mobile-title {
            font-size: clamp(1.05rem, 3.2vw, 1.3rem);
            font-weight: 700;
            color: var(--plum);
            margin: 0;
            line-height: 1.25;
            word-break: break-word;
            flex: 1;
        }

        /* Verification badge - rectangle style matching view_service.php */
        .status-badge {
            border-radius: 8px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: clamp(0.65rem, 1.5vw, 0.75rem);
            white-space: nowrap;
            flex-shrink: 0;
        }
        .status-unverified { background-color: #eee; color: #666; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-verified { background-color: #d4edda; color: #155724; }

        /* Extensions row */
        .card-mobile-exts {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: clamp(12px, 2.5vw, 18px);
        }

        .ext-pill-primary {
            background: var(--plum);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: clamp(0.7rem, 1.5vw, 0.8rem);
            font-weight: 600;
        }

        .ext-pill-secondary {
            background: white;
            color: var(--plum);
            padding: 3px 9px;
            border-radius: 12px;
            font-size: clamp(0.65rem, 1.4vw, 0.75rem);
            font-weight: 500;
            border: 1.5px solid var(--rose-gold);
        }

        .ext-pill-more {
            background: #f5f0f7;
            color: var(--plum);
            padding: 3px 9px;
            border-radius: 12px;
            font-size: clamp(0.65rem, 1.4vw, 0.75rem);
            font-weight: 500;
        }

        /* Bottom row: price */
        .card-mobile-bottom {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 8px;
            padding-top: clamp(10px, 2vw, 14px);
            border-top: 1px solid #f0f0f0;
        }

        .card-mobile-price {
            font-size: clamp(1.15rem, 3.2vw, 1.5rem);
            font-weight: 700;
            color: var(--copper);
            line-height: 1;
        }

        .card-mobile-price .price-label {
            font-size: clamp(0.6rem, 1.2vw, 0.7rem);
            color: #aaa;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 3px;
        }

        .card-mobile-owner {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #999;
            font-size: clamp(0.75rem, 1.8vw, 0.85rem);
        }

        .card-mobile-owner .owner-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--rose-gold);
            color: var(--plum);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: clamp(0.6rem, 1.2vw, 0.7rem);
            flex-shrink: 0;
        }

        /* ---- Desktop Cards ---- */
        .desktop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(clamp(300px, 28vw, 360px), 1fr));
            gap: clamp(18px, 2.5vw, 28px);
        }

        .listing-card-desktop {
            background: white;
            border-radius: clamp(14px, 2vw, 20px);
            border: 1px solid rgba(35, 3, 68, 0.06);
            padding: clamp(20px, 3vw, 28px);
            height: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(35, 3, 68, 0.04);
        }

        .listing-card-desktop::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--plum);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .listing-card-desktop:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(35, 3, 68, 0.12);
            border-color: rgba(35, 3, 68, 0.1);
        }

        .listing-card-desktop:hover::before {
            opacity: 1;
        }

        .card-desktop-title-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }

        .card-desktop-title {
            font-size: clamp(1.05rem, 1.8vw, 1.2rem);
            font-weight: 700;
            color: var(--plum);
            margin: 0;
            line-height: 1.3;
            word-break: break-word;
            flex: 1;
        }

        .card-desktop-exts {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .card-desktop-bottom {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 10px;
            padding-top: 14px;
            border-top: 1px solid #f0f0f0;
        }

        .card-desktop-price {
            font-size: clamp(1.1rem, 1.8vw, 1.35rem);
            font-weight: 700;
            color: var(--copper);
            line-height: 1;
        }

        .card-desktop-price .price-label {
            font-size: 0.65rem;
            color: #aaa;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }

        .card-desktop-owner {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #999;
            font-size: clamp(0.8rem, 1.2vw, 0.9rem);
        }

        .card-desktop-owner .owner-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--rose-gold);
            color: var(--plum);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        /*      NO RESULTS      */
        .no-results {
            text-align: center;
            padding: clamp(40px, 8vw, 80px) clamp(16px, 3vw, 32px);
            color: #666;
        }
        .no-results i {
            font-size: clamp(2rem, 5vw, 3.5rem);
            color: var(--plum);
            margin-bottom: clamp(10px, 2vw, 20px);
        }
        .no-results h5 { font-size: clamp(1rem, 2.5vw, 1.35rem); }
        .no-results p { font-size: clamp(0.85rem, 1.5vw, 1rem); }

        /*      RESPONSIVE      */
        @media (max-width: 991.98px) {
            .nav-search-section { display: none !important; }
            .profile-name, .profile-role { display: none !important; }
            .desktop-grid { display: none !important; }
        }

        @media (min-width: 768px) and (max-width: 991.98px) {
            .mobile-listings {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr);
                gap: clamp(12px, 2vw, 18px);
            }
        }

        @media (max-width: 575.98px) {
            .top-nav { height: 52px; padding: 0 12px; }
            .nav-inner { gap: 8px; }
            .brand-text { font-size: 0.85rem; }
            .profile-avatar { width: 34px; height: 34px; font-size: 0.85rem; }
        }

        @media (max-width: 375px) {
            .brand-text { font-size: 0.8rem; }
            .nav-brand-section img { width: 24px; height: 24px; }
        }

        @media (min-width: 992px) {
            .mobile-search-area, .mobile-listings { display: none !important; }
            .top-nav { height: 60px; padding: 0 clamp(16px, 2vw, 32px); }
        }

        @media (min-width: 1400px) {
            .page-container { max-width: 1500px; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar top-nav sticky-top">
        <div class="nav-inner">
            <a class="nav-brand-section" href="index.php">
                <img src="images/logo.png" alt="logo">
                <span class="brand-text">Olievenhoutbosch Digital Hub</span>
            </a>

            <div class="nav-search-section d-none d-lg-flex">
                <div class="search-container">
                    <select class="form-select border-0 bg-transparent fw-bold" id="desktopExt" style="width: clamp(80px, 10vw, 110px); color: var(--plum); flex-shrink:0;">
                        <option value="" selected>Ext...</option>
                        <option>Ext 4</option>
                        <option>Ext 13</option>
                        <option>Ext 15</option>
                        <option>Ext 19</option>
                        <option>Ext 20</option>
                        <option>Ext 21</option>
                        <option>Ext 22</option>
                        <option>Ext 23</option>
                        <option>Ext 24</option>
                        <option>Ext 25</option>
                        <option>Ext 26</option>
                        <option>Ext 36</option>
                    </select>
                    <div class="vr mx-2" style="height: 20px; flex-shrink:0;"></div>
                    <input type="text" class="form-control border-0 shadow-none bg-transparent" id="desktopSearchInput" placeholder="Search for listings..." oninput="handleSearch()" style="min-width:0;">
                    <button class="btn rounded-pill px-3 py-1 ms-2 flex-shrink-0" style="background:var(--rose-gold);color:var(--plum);border:none;font-weight:600;font-size:clamp(0.8rem,1.2vw,0.95rem);" onclick="handleSearch()">Search</button>
                </div>
            </div>

            <div class="nav-actions-section">
                <button class="btn border-0 d-lg-none p-1" type="button" onclick="toggleSearch()">
                    <i class="bi bi-search text-white" style="font-size:clamp(1.2rem,3vw,1.5rem);"></i>
                </button>
                <div class="dropdown">
                    <div class="d-flex align-items-center" role="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="text-end me-2 d-none d-lg-block">
                            <p class="profile-name"><?php echo htmlspecialchars($first_name); ?></p>
                            <p class="profile-role"><?php echo $user_role_display; ?></p>
                        </div>
                        <div class="profile-avatar"><?php echo $avatar_letter; ?></div>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <?php if ($user_role === 'Both'): ?>
                        <li><a class="dropdown-item small" href="listing_dashboard.php"><i class="bi bi-shop me-2"></i> My Listings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item small" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small" href="messages.php?view=sent"><i class="bi bi-envelope me-2"></i> Messages</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>  
            </div>
        </div>
    </nav>

    <!-- Mobile Search Area -->
    <div class="mobile-search-area d-lg-none" id="searchArea">
        <div class="search-container d-flex align-items-center bg-white rounded-pill p-2" style="max-width:600px;margin:0 auto;">
            <select class="form-select border-0 bg-transparent fw-bold" id="mobileExt" style="width: clamp(80px, 22vw, 110px);font-size:clamp(0.8rem,2.5vw,0.95rem);flex-shrink:0;">
                <option value="" selected>Ext...</option>
                <option>Ext 4</option>
                <option>Ext 13</option>
                <option>Ext 15</option>
                <option>Ext 19</option>
                <option>Ext 20</option>
                <option>Ext 21</option>
                <option>Ext 22</option>
                <option>Ext 23</option>
                <option>Ext 24</option>
                <option>Ext 25</option>
                <option>Ext 26</option>
                <option>Ext 36</option>
            </select>
            <input type="text" class="form-control border-0 shadow-none" id="mobileSearchInput" placeholder="Search..." oninput="handleSearch()" style="font-size:clamp(0.85rem,2.5vw,1rem);min-width:0;">
            <button class="btn rounded-pill px-3 flex-shrink-0" style="background:var(--rose-gold);color:var(--plum);font-weight:600;font-size:clamp(0.8rem,2.5vw,0.95rem);" onclick="handleSearch()">Search</button>
        </div>
    </div>

    <!-- Category Bar -->
    <div class="cat-bar shadow-sm">
        <div class="container" style="max-width:1400px;">
            <div class="cat-bar-inner">
                <div class="cat-item">
                    <button class="cat-btn" onclick="toggleCat(this, 'cat1')">
                        Construction & Maintenance <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="cat-dropdown" id="cat1">
                        <a href="#" onclick="filterByCategory('Painting'); return false;">Painting</a>
                        <a href="#" onclick="filterByCategory('Plumbing'); return false;">Plumbing</a>
                        <a href="#" onclick="filterByCategory('Tiling'); return false;">Tiling</a>
                        <a href="#" onclick="filterByCategory('Window Glazing'); return false;">Window Glazing</a>
                    </div>
                </div>
                <div class="cat-item">
                    <button class="cat-btn" onclick="toggleCat(this, 'cat2')">
                        Transport <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="cat-dropdown" id="cat2">
                        <a href="#" onclick="filterByCategory('Bakkie-for-hire'); return false;">Bakkie-for-hire</a>
                        <a href="#" onclick="filterByCategory('School Transport'); return false;">School Transport</a>
                        <a href="#" onclick="filterByCategory('Work Transport'); return false;">Work Transport</a>
                    </div>
                </div>
                <div class="cat-item">
                    <button class="cat-btn" onclick="toggleCat(this, 'cat3')">
                        Home & Rentals <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="cat-dropdown" id="cat3">
                        <a href="#" onclick="filterByCategory('Appliance Repairs'); return false;">Appliance Repairs</a>
                        <a href="#" onclick="filterByCategory('Backroom Rentals'); return false;">Backroom Rentals</a>
                        <a href="#" onclick="filterByCategory('Gardening'); return false;">Gardening</a>
                        <a href="#" onclick="filterByCategory('Window Cleaning'); return false;">Window Cleaning</a>
                    </div>
                </div>
                <div class="cat-item">
                    <button class="cat-btn" onclick="toggleCat(this, 'cat4')">
                        Food & Essentials <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="cat-dropdown" id="cat4">
                        <a href="#" onclick="filterByCategory('Baking'); return false;">Baking</a>
                        <a href="#" onclick="filterByCategory('Cooked & Prepared Meals'); return false;">Cooked & Prepared Meals</a>
                        <a href="#" onclick="filterByCategory('Fresh Produce'); return false;">Fresh Produce</a>
                        <a href="#" onclick="filterByCategory('Gas Refill'); return false;">Gas Refill</a>
                    </div>
                </div>
                <div class="cat-item">
                    <button class="cat-btn" onclick="toggleCat(this, 'cat5')">
                        Personal Care <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="cat-dropdown" id="cat5">
                        <a href="#" onclick="filterByCategory('Hair'); return false;">Hair</a>
                        <a href="#" onclick="filterByCategory('Make-up'); return false;">Make-up</a>
                        <a href="#" onclick="filterByCategory('Nails'); return false;">Nails</a>
                        <a href="#" onclick="filterByCategory('Spa'); return false;">Spa</a>
                        <a href="#" onclick="filterByCategory('Tailor'); return false;">Tailor</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dropdown-overlay" id="dropdownOverlay" onclick="closeAllCats()"></div>

    <!-- Main Content -->
    <div class="page-container">
        <div class="results-header">
            <h6 class="results-title">Available Listings</h6>
            <span class="results-count" id="resultsCount"></span>
        </div>

        <!-- Mobile Listings -->
        <div class="mobile-listings" id="mobileListings"></div>

        <!-- Desktop Listings -->
        <div class="desktop-grid" id="desktopListings"></div>

        <!-- No Results -->
        <div id="noResults" class="no-results" style="display: none;">
            <i class="bi bi-search"></i>
            <h5>No listings found</h5>
            <p class="text-muted">Try adjusting your search or use the categories to narrow your results.</p>
        </div>
    </div>

    <script>
        const listings = <?php echo json_encode(array_map(function($item) {
            $ext_display = 'Ext ' . $item['extension'];
            $has_multiple = false;
            $ext_count = 1;
            if (!empty($item['service_extensions'])) {
                $ext_display = 'Multiple Ext';
                $has_multiple = true;
                $ext_count = count(explode(',', $item['service_extensions'])) + 1;
            }

            // Determine listing type icon
            $type_icon = 'bi-briefcase';
            $type_label = 'Service';
            if ($item['listing_type'] == 'product') {
                $type_icon = 'bi-box-seam';
                $type_label = 'Goods';
            } elseif ($item['listing_type'] == 'both') {
                $type_icon = 'bi-layers';
                $type_label = 'Service & Goods';
            }

            // Delivery mode icon
            $delivery_icon = 'bi-truck';
            $delivery_modes_arr = [];
            if (!empty($item['delivery_mode'])) {
                $delivery_modes_arr = array_map('trim', explode(',', $item['delivery_mode']));
            }
            $primary_delivery = $delivery_modes_arr[0] ?? '';
            $delivery_label = '';
            if (strpos($primary_delivery, 'door') !== false || strpos($primary_delivery, 'deliver') !== false) {
                $delivery_icon = 'bi-truck';
                $delivery_label = 'Delivers';
            } elseif (strpos($primary_delivery, 'pickup') !== false || strpos($primary_delivery, 'comes') !== false) {
                $delivery_icon = 'bi-geo-alt';
                $delivery_label = 'Pickup';
            } elseif (strpos($primary_delivery, 'both') !== false) {
                $delivery_icon = 'bi-arrow-left-right';
                $delivery_label = 'Both';
            }

            return [
                'id' => (int)$item['listing_id'],
                'name' => $item['listing_name'],
                'category' => $item['service_type'],
                'parentCategory' => $item['category'],
                'ext' => $ext_display,
                'primaryExt' => $item['extension'],
                'additionalExts' => $item['service_extensions'] ?? '',
                'hasMultipleExt' => $has_multiple,
                'extCount' => $ext_count,
                'price' => $item['price_description'],
                'verified' => $item['verification_status'] === 'Verified',
                'pending' => $item['verification_status'] === 'Pending',
                'typeIcon' => $type_icon,
                'typeLabel' => $type_label,
                'deliveryIcon' => $delivery_icon,
                'deliveryLabel' => $delivery_label,
                // Owner info intentionally hidden from listing cards
            ];
        }, $db_listings)); ?>;

        let currentCategoryFilter = '';

        function renderListings(filteredListings) {
            const mobileContainer = document.getElementById('mobileListings');
            const desktopContainer = document.getElementById('desktopListings');
            const noResults = document.getElementById('noResults');
            const resultsCount = document.getElementById('resultsCount');

            mobileContainer.innerHTML = '';
            desktopContainer.innerHTML = '';

            if (filteredListings.length === 0) {
                mobileContainer.style.display = 'none';
                desktopContainer.style.display = 'none';
                noResults.style.display = 'block';
                resultsCount.textContent = '0 results';
                return;
            }

            mobileContainer.style.display = '';
            desktopContainer.style.display = '';
            noResults.style.display = 'none';
            resultsCount.textContent = filteredListings.length + ' result' + (filteredListings.length !== 1 ? 's' : '');

            filteredListings.forEach(item => {
                // Verification badge - rectangle style matching view_service.php
                let statusClass = 'status-unverified';
                let statusText = 'Unverified';
                if (item.verified) {
                    statusClass = 'status-verified';
                    statusText = 'Verified';
                } else if (item.pending) {
                    statusClass = 'status-pending';
                    statusText = 'Pending';
                }
                const verifiedBadge = '<span class="status-badge ' + statusClass + '">' + statusText + '</span>';

                // Build extension pills
                let extPillsHtml = '<span class="ext-pill-primary">Ext ' + item.primaryExt + '</span>';
                if (item.hasMultipleExt && item.additionalExts) {
                    const additional = item.additionalExts.split(',');
                    if (additional.length > 0) {
                        extPillsHtml += '<span class="ext-pill-secondary">Ext ' + additional[0] + '</span>';
                    }
                    if (additional.length > 1) {
                        extPillsHtml += '<span class="ext-pill-more">+' + (additional.length - 1) + ' more</span>';
                    }
                }

                // Mobile card - SIMPLIFIED
                const mobileCard = document.createElement('a');
                mobileCard.href = 'view_service.php?id=' + item.id;
                mobileCard.className = 'listing-card-mobile';
                mobileCard.innerHTML = `
                    <div class="card-mobile-title-row">
                        <h5 class="card-mobile-title">${item.name}</h5>
                        ${verifiedBadge}
                    </div>
                    <div class="card-mobile-exts">${extPillsHtml}</div>
                    <div class="card-mobile-bottom">
                        <div class="card-mobile-price">
                            <span class="price-label">Price</span>
                            ${item.price}
                        </div>

                    </div>
                `;
                mobileContainer.appendChild(mobileCard);

                // Desktop card - SIMPLIFIED
                const desktopCard = document.createElement('a');
                desktopCard.href = 'view_service.php?id=' + item.id;
                desktopCard.className = 'listing-card-desktop';
                desktopCard.innerHTML = `
                    <div class="card-desktop-title-row">
                        <h5 class="card-desktop-title">${item.name}</h5>
                        ${verifiedBadge}
                    </div>
                    <div class="card-desktop-exts">${extPillsHtml}</div>
                    <div class="card-desktop-bottom">
                        <div class="card-desktop-price">
                            <span class="price-label">Price</span>
                            ${item.price}
                        </div>

                    </div>
                `;
                desktopContainer.appendChild(desktopCard);
            });
        }

        function getSelectedExt() {
            const desktopExt = document.getElementById('desktopExt');
            const mobileExt = document.getElementById('mobileExt');
            return (window.innerWidth >= 992) ? (desktopExt ? desktopExt.value : '') : (mobileExt ? mobileExt.value : '');
        }

        function getSearchQuery() {
            const desktopInput = document.getElementById('desktopSearchInput');
            const mobileInput = document.getElementById('mobileSearchInput');
            return (window.innerWidth >= 992) ? (desktopInput ? desktopInput.value.trim().toLowerCase() : '') : (mobileInput ? mobileInput.value.trim().toLowerCase() : '');
        }

        function handleSearch() {
            const query = getSearchQuery();
            const ext = getSelectedExt();
            let filtered = listings;

            if (ext && ext !== 'Ext...') {
                const extNum = ext.replace('Ext ', '');
                filtered = filtered.filter(item => {
                    if (item.primaryExt === extNum) return true;
                    if (item.additionalExts && item.additionalExts.split(',').includes(extNum)) return true;
                    return false;
                });
            }

            if (query) {
                filtered = filtered.filter(item => 
                    item.name.toLowerCase().includes(query) ||
                    item.category.toLowerCase().includes(query) ||
                    item.parentCategory.toLowerCase().includes(query)
                );
            }
            if (currentCategoryFilter) filtered = filtered.filter(item => item.category === currentCategoryFilter);
            renderListings(filtered);
        }

        function filterByCategory(category) {
            currentCategoryFilter = category;
            closeAllCats();
            handleSearch();
        }

        document.getElementById('desktopExt').addEventListener('change', handleSearch);
        document.getElementById('mobileExt').addEventListener('change', handleSearch);
        renderListings(listings);

        function toggleSearch() { 
            document.getElementById('searchArea').classList.toggle('show'); 
        }

        function toggleCat(btn, dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const overlay = document.getElementById('dropdownOverlay');
            const isOpen = dropdown.classList.contains('show');
            closeAllCats();
            if (!isOpen) {
                const rect = btn.getBoundingClientRect();
                dropdown.style.top = (rect.bottom + 5) + 'px';
                dropdown.style.left = rect.left + 'px';
                dropdown.classList.add('show');
                btn.classList.add('active');
                overlay.classList.add('show');
            }
        }

        function closeAllCats() {
            document.querySelectorAll('.cat-dropdown').forEach(d => d.classList.remove('show'));
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('dropdownOverlay').classList.remove('show');
        }
    </script>

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