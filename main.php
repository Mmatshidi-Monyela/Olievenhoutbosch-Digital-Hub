<?php
session_start();
include 'includes/db_connect.php';
/** @var mysqli $conn */

// Get user data for header
$display_name = $_SESSION['full_name'] ?? 'Guest';
$first_name = explode(' ', $display_name)[0];
$user_role = $_SESSION['user_role'] ?? 'Customer';
$user_role_display = match($user_role) {
    'Provider' => 'Service Provider',
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
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

        .navbar .search-container {
            background: white;
            border-radius: 50px;
            padding: 4px 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }

        .navbar .search-container .form-select,
        .navbar .search-container .form-control {
            font-size: 0.9rem;
        }

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

        .cat-bar::-webkit-scrollbar {
            display: none;
        }

        .cat-bar-inner {
            display: flex;
            gap: 0;
            padding: 0 15px;
            min-width: max-content;
        }

        .cat-item {
            position: relative;
            display: inline-block;
        }

        .cat-btn {
            background: none;
            border: none;
            color: var(--plum);
            font-weight: 700;
            font-size: 0.9rem;
            padding: 0.4rem 1.2rem;
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cat-btn:hover {
            color: var(--copper);
        }

        .cat-btn i {
            font-size: 0.7rem;
            transition: transform 0.2s;
        }

        .cat-btn.active i {
            transform: rotate(180deg);
        }

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

        .cat-dropdown.show {
            display: block;
        }

        .cat-dropdown a {
            display: block;
            padding: 10px 20px;
            color: var(--plum);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .cat-dropdown a:hover {
            background: var(--rose-gold);
            color: var(--plum);
        }

        .dropdown-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 99998;
        }

        .dropdown-overlay.show {
            display: block;
        }

        .listing-card {
            background: #fff;
            border-radius: 15px;
            border: 1px solid #eee;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            cursor: pointer;
        }
        .listing-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .badge-verified {
            background-color: var(--copper);
            color: white;
            padding: 2px 10px;
            border-radius: 5px;
            font-size: 0.75rem;
        }

        .badge-unverified {
            background-color: #e9ecef;
            color: #6c757d;
            padding: 2px 10px;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-multi-ext {
            background-color: #e3f2fd;
            color: #0d47a1;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .ext-label {
            font-size: 0.75rem;
            color: #888;
        }
        .service-label {
            font-size: 0.85rem;
            color: #666;
        }
        .price-text {
            color: var(--copper);
            font-weight: 700;
            margin-top: 10px;
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

        .results-count {
            color: var(--plum);
            font-weight: 600;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: black;
        }
        .no-results i {
            font-size: 3rem;
            color: var(--plum);
            margin-bottom: 15px;
        }

        @media (max-width: 1024px) {
            .search-container {
                max-width: 350px;
            }
            .brand-text {
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        @media (max-width: 991px) {
            .navbar .search-container {
                display: none !important;
            }
            .mobile-search-area {
                background-color: var(--plum);
                padding: 15px;
                display: none;
            }
            .mobile-search-area.show {
                display: block;
            }
        }

        @media (max-width: 576px) {
            .brand-text {
                font-size: 0.85rem;
            }
            .listing-card {
                padding: 1rem !important;
            }
            .listing-card h5 {
                font-size: 1rem;
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

            <div class="search-container d-none d-lg-flex align-items-center mx-auto" id="desktopSearch">
                <select class="form-select border-0 bg-transparent fw-bold" id="desktopExt" style="width: 100px; color: var(--plum);">
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
                <div class="vr mx-2" style="height: 20px;"></div>
                <input type="text" class="form-control border-0 shadow-none bg-transparent" id="desktopSearchInput" placeholder="Search for listings..." oninput="handleSearch()">
                <button class="btn btn-primary rounded-pill px-3 py-1 ms-2" onclick="handleSearch()">Search</button>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button class="btn border-0 d-lg-none" type="button" onclick="toggleSearch()">
                    <i class="bi bi-search text-white fs-4"></i>
                </button>

                <div class="dropdown">
                    <div class="d-flex align-items-center" role="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="text-end me-2 d-none d-md-block text-white">
                            <p class="mb-0 fw-bold" style="font-size: 0.8rem;"><?php echo htmlspecialchars($first_name); ?></p>
                            <p class="mb-0 opacity-75" style="font-size: 0.65rem;"><?php echo $user_role_display; ?></p>
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

    <div class="mobile-search-area d-lg-none" id="searchArea">
        <div class="search-container d-flex align-items-center bg-white rounded-pill p-2">
            <select class="form-select border-0 bg-transparent fw-bold" id="mobileExt" style="width: 100px;">
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
            <input type="text" class="form-control border-0 shadow-none" id="mobileSearchInput" placeholder="Search..." oninput="handleSearch()">
            <button class="btn btn-primary rounded-pill" onclick="handleSearch()">Search</button>
        </div>
    </div>

    <div class="cat-bar shadow-sm">
        <div class="container">
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
                        <a href="#" onclick="filterByCategory('Bakery'); return false;">Bakery</a>
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

    <div class="container pt-3 pb-0">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--plum);">Available Listings</h6>
            <span class="results-count small" id="resultsCount"></span>
        </div>
    </div>

    <section class="services-section pt-3 pb-5">
        <div class="container">
            <div class="row g-3 justify-content-center" id="listingsGrid">
            </div>
            <div id="noResults" class="no-results" style="display: none;">
                <i class="bi bi-search"></i>
                <h5>No listings found</h5>
            </div>
        </div>
    </section>

    <script>
        const listings = <?php echo json_encode(array_map(function($item) {
            $ext_display = 'Ext ' . $item['extension'];
            $has_multiple = false;
            if (!empty($item['service_extensions'])) {
                $ext_display = 'Multiple Ext';
                $has_multiple = true;
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
                'price' => $item['price_description'],
                'verified' => $item['verification_status'] === 'Verified'
            ];
        }, $db_listings)); ?>;

        let currentCategoryFilter = '';

        function renderListings(filteredListings) {
            const grid = document.getElementById('listingsGrid');
            const noResults = document.getElementById('noResults');
            const resultsCount = document.getElementById('resultsCount');

            grid.innerHTML = '';

            if (filteredListings.length === 0) {
                grid.style.display = 'none';
                noResults.style.display = 'block';
                resultsCount.textContent = '0 results';
                return;
            }

            grid.style.display = 'flex';
            noResults.style.display = 'none';
            resultsCount.textContent = filteredListings.length + ' result' + (filteredListings.length !== 1 ? 's' : '');

            filteredListings.forEach(item => {
                const card = document.createElement('div');
                card.className = 'col-12 col-sm-6 col-lg-4';
                const badgeHtml = item.verified 
                    ? '<span class="badge-verified">Verified</span>'
                    : '<span class="badge-unverified">Unverified</span>';

                let extHtml = '<span class="ext-label text-uppercase fw-bold text-muted small">' + item.ext + '</span>';
                if (item.hasMultipleExt) {
                    extHtml = '<span class="badge-multi-ext">' + item.ext + '</span>';
                }

                card.innerHTML = `
                    <a href="view_service.php?id=${item.id}" class="text-decoration-none">
                        <div class="listing-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                ${badgeHtml}
                                ${extHtml}
                            </div>
                            <h5 class="fw-bold mb-1" style="color: var(--plum);">${item.name}</h5>
                            <p class="service-label mb-0 text-muted small">Service: <span class="text-dark">${item.category}</span></p>
                            <p class="price-text mt-2 fw-bold" style="color: var(--copper);">${item.price}</p>
                        </div>
                    </a>
                `;
                grid.appendChild(card);
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