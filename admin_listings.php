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

$listings = [];
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

if ($conn) {
    $listRes = mysqli_query($conn, "
        SELECT l.listing_id, l.listing_name, l.is_active, l.verification_status,
               COALESCE(AVG(c.rating), 0) as avg_rating,
               COUNT(c.comment_id) as comment_count,
               GROUP_CONCAT(c.comment_text SEPARATOR '|') as all_comments
        FROM listing l
        LEFT JOIN comment c ON l.listing_id = c.listing_id
        GROUP BY l.listing_id, l.listing_name, l.is_active, l.verification_status
        ORDER BY l.is_active DESC, l.listing_name ASC
    ");

    while ($row = mysqli_fetch_assoc($listRes)) {
        $comments = [];
        if ($row['all_comments']) {
            $comments = explode('|', $row['all_comments']);
        }

        $listings[] = [
            'id' => $row['listing_id'],
            'name' => $row['listing_name'],
            'rating' => round(floatval($row['avg_rating']), 1),
            'comments' => $comments,
            'status' => $row['is_active'] ? 'Active' : 'Inactive',
            'verification_status' => $row['verification_status'],
            'comment_count' => $row['comment_count']
        ];
    }
}

function getListingAlert($listing, $flaggedKeywords) {
    foreach ($listing['comments'] as $comment) {
        $lowerComment = strtolower($comment);
        foreach ($flaggedKeywords as $keyword) {
            if (strpos($lowerComment, $keyword) !== false) {
                return [
                    'type' => 'Keyword Alert',
                    'bg' => '#ffe5e5', 'text' => '#d9534f', 'border' => '#f5c6cb',
                    'priority' => 3
                ];
            }
        }
    }
    if ($listing['rating'] > 0 && $listing['rating'] < 3.5) {
        return [
            'type' => 'Rating Alert',
            'bg' => '#fff3cd', 'text' => '#856404', 'border' => '#ffeeba',
            'priority' => 2
        ];
    }
    return [
        'type' => 'All Good',
        'bg' => '#e6ffed', 'text' => '#28a745', 'border' => '#c3e6cb',
        'priority' => 1
    ];
}

usort($listings, function($a, $b) use ($flaggedKeywords) {
    $alertA = getListingAlert($a, $flaggedKeywords);
    $alertB = getListingAlert($b, $flaggedKeywords);
    return $alertB['priority'] <=> $alertA['priority'];
});

$admin_name = $_SESSION['full_name'] ?? 'Administrator';
$admin_first_name = explode(' ', $admin_name)[0];
$admin_avatar = strtoupper(substr($admin_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listings | Olievenhoutbosch Digital Hub</title>
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

        body
        { 
            background-color: #f4f7f6; 
            font-family: 'Inter', sans-serif; 
        }

        .navbar.top-nav { background-color: var(--plum); padding: 0.6rem 1rem; border-bottom: 3px solid var(--rose-gold); }
        .brand-text { font-size: clamp(0.8rem, 2.2vw, 1.1rem); white-space: nowrap; }
        .nav-link 
        { 
            color: rgba(255,255,255,0.8) !important; 
            font-weight: 500; 
            border-bottom: 2px solid transparent; 
        }
        .nav-link.active { color: var(--rose-gold) !important; border-bottom: 2px solid var(--rose-gold); }
        
        .profile-avatar { width: 35px; height: 35px; background-color: var(--rose-gold); color: var(--plum); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        @media (max-width: 991px) {
            .navbar-toggler { order: 1; }
            .navbar-brand { order: 2; margin-right: auto !important; display: flex !important; align-items: center; }
            .admin-profile-wrapper { order: 3; }
            .navbar-collapse { order: 4; width: 100%; }
        }

        .content-area { padding-top: 2rem; }

        .page-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .search-wrapper {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 50px;
            padding: 4px 4px 4px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            max-width: 380px;
            width: 100%;
        }
        .search-wrapper input {
            border: none;
            background: transparent;
            flex: 1;
            font-size: 0.9rem;
            outline: none;
            padding: 6px 8px;
        }
        .search-wrapper button {
            background: var(--plum);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 6px 18px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .glass-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border: none;
            border-top: 4px solid var(--rose-gold);
            overflow: hidden;
        }

        .sortable-header {
            font-size: 0.9rem !important;
            font-weight: 700 !important;
            color: var(--plum) !important;
            text-transform: none !important;
            letter-spacing: 0 !important;
            padding: 14px 16px;
            border-bottom: 2px solid var(--rose-gold) !important;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        .sortable-header:hover { background: var(--rose-light); }
        .sortable-header .sort-icon {
            font-size: 0.75rem;
            margin-left: 6px;
            color: #bbb;
            transition: color 0.2s;
        }
        .sortable-header:hover .sort-icon { color: var(--plum); }
        .sortable-header.active-sort .sort-icon { color: var(--plum); }
        .sortable-header.active-sort { background: var(--rose-light); }

        .table tbody td { padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid #f8f9fa; }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: #fafafa; }

        .alert-bubble {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid;
        }

        .rating-star-plum { color: var(--plum); font-size: 0.85rem; }
        .rating-text { font-weight: 600; color: var(--plum); }
        .rating-low { color: #d9534f !important; }
        .rating-unrated { color: #bbb !important; }

        .btn-action-round {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: 0.2s;
            margin-left: 5px;
        }
        .btn-suspend { background-color: #fff3cd; color: #856404; }
        .btn-suspend:hover { background-color: #856404; color: white; }
        .btn-delete { background-color: #f8d7da; color: #721c24; }
        .btn-delete:hover { background-color: #721c24; color: white; }
        .btn-view { background-color: #e6ffed; color: #28a745; }
        .btn-view:hover { background-color: #28a745; color: white; }
        .btn-restore { background-color: #d1ecf1; color: #0c5460; }
        .btn-restore:hover { background-color: #0c5460; color: white; }

        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--text-gray);
        }

        /* Suspended listing muted styles */
        tr.row-suspended {
            opacity: 0.5;
            background-color: #f8f9fa !important;
        }
        tr.row-suspended:hover {
            opacity: 0.7;
            background-color: #e9ecef !important;
        }
        .name-suspended {
            text-decoration: line-through;
            color: #6c757d !important;
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
                    <li class="nav-item"><a class="nav-link mx-2" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active mx-2" href="admin_listings.php">Listings</a></li>
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

    <div class="container content-area mb-5">

        <div class="page-header-bar">
            <h5 class="fw-bold mb-0" style="color: var(--plum);">Listings</h5>
            <div class="search-wrapper">
                <input type="text" id="searchInput" placeholder="Search listings by name or alert...">
                <button onclick="filterTable()">Search</button>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="listingsTable">
                            <thead>
                                <tr>
                                    <th class="sortable-header" onclick="sortTable(0, 'text')">
                                        Listing Name <i class="bi bi-arrow-down-up sort-icon"></i>
                                    </th>
                                    <th class="sortable-header" onclick="sortTable(1, 'number')">
                                        Rating <i class="bi bi-arrow-down-up sort-icon"></i>
                                    </th>
                                    <th class="sortable-header" onclick="sortTable(2, 'text')">
                                        Alert <i class="bi bi-arrow-down-up sort-icon"></i>
                                    </th>
                                    <th class="sortable-header text-center" style="cursor: default;">
                                        Manage
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if (empty($listings)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <p class="mt-2">No listings found in the database.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($listings as $listing): 
                                    $alert = getListingAlert($listing, $flaggedKeywords);
                                    $ratingClass = ($listing['rating'] > 0 && $listing['rating'] < 2.0) ? 'rating-low' : '';
                                    $isSuspended = ($listing['status'] === 'Inactive');
                                    $rowClass = $isSuspended ? 'row-suspended' : '';
                                    $nameClass = $isSuspended ? 'name-suspended' : '';
                                ?>
                                <tr class="<?php echo $rowClass; ?>" 
                                    data-name="<?php echo strtolower(htmlspecialchars($listing['name'])); ?>" 
                                    data-rating="<?php echo $listing['rating']; ?>" 
                                    data-alert="<?php echo strtolower($alert['type']); ?>"
                                    data-status="<?php echo strtolower($listing['status']); ?>">
                                    <td class="fw-bold <?php echo $nameClass; ?>">
                                        <?php echo htmlspecialchars($listing['name']); ?>
                                        <?php if ($isSuspended): ?>
                                            <span class="badge bg-secondary ms-2" style="font-size: 0.65rem;">SUSPENDED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($listing['rating'] > 0): ?>
                                        <i class="bi bi-star-fill rating-star-plum <?php echo $ratingClass; ?>"></i>
                                        <span class="rating-text <?php echo $ratingClass; ?>"><?php echo $listing['rating']; ?></span>
                                        <?php else: ?>
                                        <i class="bi bi-star rating-unrated"></i>
                                        <span class="rating-text rating-unrated">Unrated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="alert-bubble" style="background: <?php echo $alert['bg']; ?>; color: <?php echo $alert['text']; ?>; border-color: <?php echo $alert['border']; ?>">
                                            <?php echo $alert['type']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn-action-round btn-view" title="View Details" onclick="viewListing(<?php echo $listing['id']; ?>)"><i class="bi bi-eye-fill"></i></button>
                                        <?php if ($isSuspended): ?>
                                            <button class="btn-action-round btn-restore" title="Restore" onclick="restoreListing(<?php echo $listing['id']; ?>)"><i class="bi bi-play-fill"></i></button>
                                        <?php else: ?>
                                            <button class="btn-action-round btn-suspend" title="Suspend" onclick="suspendListing(<?php echo $listing['id']; ?>)"><i class="bi bi-pause-fill"></i></button>
                                        <?php endif; ?>
                                        <button class="btn-action-round btn-delete" title="Delete" onclick="deleteListing(<?php echo $listing['id']; ?>)"><i class="bi bi-trash3-fill"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div id="noResults" class="no-results" style="display: none;">
                            <p class="mt-2">No listings found matching your search.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Listing Details Modal -->
    <div class="modal fade" id="listingModal" tabindex="-1" aria-labelledby="listingModalLabel" aria-hidden="true" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="listingModalLabel" style="color: var(--plum);">Listing Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="listingModalBody">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ===== SEARCH / FILTER =====
    function filterTable() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#tableBody tr');
        let visible = 0;

        rows.forEach(row => {
            if (row.querySelector('td[colspan]')) return;
            const name = row.getAttribute('data-name');
            const alert = row.getAttribute('data-alert');
            const status = row.getAttribute('data-status');
            if (name.includes(query) || alert.includes(query) || status.includes(query)) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('noResults').style.display = visible === 0 ? 'block' : 'none';
    }

    document.getElementById('searchInput').addEventListener('input', filterTable);

    // ===== SORTING =====
    let sortState = { col: -1, dir: 'asc' };

    function sortTable(colIndex, type) {
        const tbody = document.getElementById('tableBody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.querySelector('td[colspan]'));
        const headers = document.querySelectorAll('.sortable-header');

        if (sortState.col === colIndex) {
            sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
        } else {
            sortState.dir = 'asc';
            sortState.col = colIndex;
        }

        headers.forEach((h, i) => {
            const icon = h.querySelector('.sort-icon');
            if (icon) {
                if (i === colIndex) {
                    h.classList.add('active-sort');
                    icon.className = sortState.dir === 'asc' 
                        ? 'bi bi-arrow-up sort-icon' 
                        : 'bi bi-arrow-down sort-icon';
                } else {
                    h.classList.remove('active-sort');
                    icon.className = 'bi bi-arrow-down-up sort-icon';
                }
            }
        });

        rows.sort((a, b) => {
            let aVal, bVal;

            if (type === 'number') {
                aVal = parseFloat(a.getAttribute('data-rating'));
                bVal = parseFloat(b.getAttribute('data-rating'));
            } else if (type === 'text') {
                aVal = a.cells[colIndex].textContent.trim().toLowerCase();
                bVal = b.cells[colIndex].textContent.trim().toLowerCase();
            }

            if (aVal < bVal) return sortState.dir === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortState.dir === 'asc' ? 1 : -1;
            return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
    }

    // ===== ACTION BUTTONS =====
    function viewListing(id) {
        const modalEl = document.getElementById('listingModal');
        const modalBody = document.getElementById('listingModalBody');

        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }

        modalBody.innerHTML = 'Loading...';
        modal.show();

        fetch('get_listing_details.php?id=' + id)
            .then(r => r.text())
            .then(html => {
                modalBody.innerHTML = html;
            })
            .catch(() => {
                modalBody.innerHTML = '<p class="text-danger">Failed to load listing details.</p>';
            });
    }

    function suspendListing(id) {
        if (confirm('Suspend this listing? It will be hidden from public view.')) {
            fetch('admin_listings_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=suspend&id=' + id
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Listing suspended successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Could not suspend listing'));
                }
            })
            .catch(() => alert('Network error. Please try again.'));
        }
    }

    function restoreListing(id) {
        if (confirm('Restore this listing? It will be visible to the public again.')) {
            fetch('admin_listings_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=restore&id=' + id
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Listing restored successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Could not restore listing'));
                }
            })
            .catch(() => alert('Network error. Please try again.'));
        }
    }

    function deleteListing(id) {
        if (confirm('Are you sure? This will permanently delete this listing and all its reviews. This action cannot be undone.')) {
            fetch('admin_listings_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete&id=' + id
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Listing deleted successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Could not delete listing'));
                }
            })
            .catch(() => alert('Network error. Please try again.'));
        }
    }
    </script>
</body>
</html>