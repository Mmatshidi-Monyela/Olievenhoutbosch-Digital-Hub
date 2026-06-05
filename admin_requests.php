<?php
session_start();

// ============================================
// ADMIN AUTHENTICATION CHECK
// ============================================
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get admin data from session
$admin_name = $_SESSION['full_name'] ?? 'Administrator';
$admin_first_name = explode(' ', $admin_name)[0];
$admin_avatar = strtoupper(substr($admin_name, 0, 1));

// ============================================
// DATABASE CONNECTION
// ============================================
$conn = null;
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
}

// ============================================
// FETCH PENDING VERIFICATIONS FROM DATABASE
// ============================================
$pendingRequests = [];

if ($conn) {
    $query = "SELECT 
                l.listing_id as id,
                l.listing_id,
                l.listing_name,
                l.category,
                l.extension,
                l.created_at,
                u.full_name as owner,
                l.verification_status
              FROM listing l
              JOIN useraccount u ON l.user_id = u.user_id
              WHERE l.verification_status = 'Pending'
              ORDER BY l.created_at DESC";

    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['date_display'] = date('M j, Y', strtotime($row['created_at']));
            $row['date'] = date('Y-m-d', strtotime($row['created_at']));
            $pendingRequests[] = $row;
        }
    }
}

$adminMessage = $_SESSION['admin_message'] ?? '';
unset($_SESSION['admin_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Verifications | Olievenhoutbosch Digital Hub</title>
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

        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .navbar.top-nav { background-color: var(--plum); padding: 0.6rem 1rem; border-bottom: 3px solid var(--rose-gold); }
        .brand-text { font-size: clamp(0.8rem, 2.2vw, 1.1rem); white-space: nowrap; }
        .nav-link { color: rgba(255,255,255,0.8) !important; font-weight: 500; border-bottom: 2px solid transparent; }
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

        .table tbody td { 
            padding: 14px 16px; 
            vertical-align: middle; 
            border-bottom: 1px solid #f8f9fa; 
            font-size: 0.95rem;
            color: #333;
        }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: #fafafa; }

        .status-pill {
            padding: 0.35rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            background: #fff3cd;
            color: #856404;
        }

        .btn-view-pill {
            background: transparent;
            color: var(--plum);
            border: 1.5px solid var(--plum);
            border-radius: 20px;
            padding: 4px 18px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.3s;
            display: inline-block;
        }
        .btn-view-pill:hover { background: var(--plum); color: white; }

        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--text-gray);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i { font-size: 3rem; color: var(--rose-gold); }

        .alert-admin {
            border-radius: 12px;
            border: none;
            background: #e6ffed;
            color: #155724;
            padding: 14px 20px;
            margin-bottom: 1.5rem;
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
                    <li class="nav-item"><a class="nav-link mx-2" href="admin_listings.php">Listings</a></li>
                    <li class="nav-item"><a class="nav-link active mx-2" href="admin_requests.php">Requests</a></li>
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

        <?php if (!empty($adminMessage)): ?>
        <div class="alert-admin alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $adminMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter: invert(30%);"></button>
        </div>
        <?php endif; ?>

        <div class="page-header-bar">
            <div>
                <h5 class="fw-bold mb-0" style="color: var(--plum);">Pending Verifications</h5>
            </div>
            <div class="search-wrapper">
                <input type="text" id="searchInput" placeholder="Search Listing...">
                <button onclick="filterTable()">Search</button>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <?php if (count($pendingRequests) > 0): ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="requestsTable">
                            <thead>
                                <tr>
                                    <th class="sortable-header" onclick="sortTable(0, 'text')">
                                        Listing <i class="bi bi-arrow-down-up sort-icon"></i>
                                    </th>
                                    <th class="sortable-header" onclick="sortTable(1, 'date')">
                                        Date Submitted <i class="bi bi-arrow-down-up sort-icon"></i>
                                    </th>
                                    <th class="sortable-header text-center" style="cursor: default;">
                                        Status
                                    </th>
                                    <th class="sortable-header text-center" style="cursor: default;">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php foreach ($pendingRequests as $req): ?>
                                <tr data-listing="<?php echo strtolower($req['listing_name']); ?>" 
                                    data-date="<?php echo $req['date']; ?>">
                                    <td class="fw-bold"><?php echo $req['listing_name']; ?></td>
                                    <td class="text-muted"><?php echo $req['date_display']; ?></td>
                                    <td class="text-center"><span class="status-pill">Pending</span></td>
                                    <td class="text-center">
                                        <a href="view_request.php?id=<?php echo $req['listing_id']; ?>" class="btn-view-pill">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <h5 class="mt-3 fw-bold" style="color: var(--plum);">All Caught Up!</h5>
                        <p class="text-muted">No pending verification requests at the moment.</p>
                    </div>
                    <?php endif; ?>
                    <div id="noResults" class="no-results" style="display: none;">
                        <i class="bi bi-search fs-1 text-muted"></i>
                        <p class="mt-2">No requests found matching your search.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function filterTable() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#tableBody tr');
        let visible = 0;

        rows.forEach(row => {
            const listing = row.getAttribute('data-listing');
            if (listing.includes(query)) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('noResults').style.display = visible === 0 ? 'block' : 'none';
    }

    document.getElementById('searchInput').addEventListener('input', filterTable);

    let sortState = { col: -1, dir: 'asc' };

    function sortTable(colIndex, type) {
        const tbody = document.getElementById('tableBody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
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

            if (type === 'date') {
                aVal = a.getAttribute('data-date');
                bVal = b.getAttribute('data-date');
            } else {
                aVal = a.cells[colIndex].textContent.trim().toLowerCase();
                bVal = b.cells[colIndex].textContent.trim().toLowerCase();
            }

            if (aVal < bVal) return sortState.dir === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortState.dir === 'asc' ? 1 : -1;
            return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
    }
    </script>
</body>
</html>