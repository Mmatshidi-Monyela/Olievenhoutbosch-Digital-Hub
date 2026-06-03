<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: login.php");
    exit();
}

$conn = null;
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
}

$users = [];
if ($conn) {
    // EXCLUDE Admin from the users table — only Customers and Service Providers
    $userRes = mysqli_query($conn, "
        SELECT user_id, full_name, email, user_role, extension, created_at, is_active 
        FROM useraccount 
        WHERE user_role != 'Admin'
        ORDER BY created_at DESC
    ");

    while ($row = mysqli_fetch_assoc($userRes)) {
        $users[] = [
            'id' => $row['user_id'],
            'name' => $row['full_name'],
            'email' => $row['email'],
            'role' => $row['user_role'],
            'ext' => $row['extension'] ?? 'N/A',
            'is_active' => $row['is_active'],
            'joined' => $row['created_at'],
            'joined_display' => date('M j, Y', strtotime($row['created_at']))
        ];
    }
}

$admin_name = $_SESSION['full_name'] ?? 'Administrator';
$admin_first_name = explode(' ', $admin_name)[0];
$admin_avatar = strtoupper(substr($admin_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #f8c9c0;
            --rose-light: #fbe5e6;
            --text-gray: #6c757d;
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

        .table tbody td { padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid #f8f9fa; }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: #fafafa; }

        .plain-text { color: #333; font-weight: 500; }

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
        .btn-restore { background-color: #e6ffed; color: #28a745; }
        .btn-restore:hover { background-color: #28a745; color: white; }
        .btn-delete { background-color: #f8d7da; color: #721c24; }
        .btn-delete:hover { background-color: #721c24; color: white; }

        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--text-gray);
        }


        .status-inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }
        .suspended-label {
            font-size: 0.7rem;
            color: #856404;
            font-weight: 600;
            margin-left: 8px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg top-nav sticky-top">
        <div class="container-fluid">
            <button class="navbar-toggler border-0 p-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <i class="bi bi-list text-white fs-2"></i>
            </button>
            <a class="navbar-brand d-flex align-items-center ms-2" href="admin_dashboard.php">
                <img src="images/logo.png" width="28" height="28" alt="logo" class="me-2">
                <span class="brand-text fw-bold text-white">Olievenhoutbosch Digital Hub</span>
            </a>
            <div class="collapse navbar-collapse justify-content-center" id="adminNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link mx-2" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link mx-2" href="admin_listings.php">Listings</a></li>
                    <li class="nav-item"><a class="nav-link mx-2" href="admin_requests.php">Requests</a></li>
                    <li class="nav-item"><a class="nav-link active mx-2" href="admin_users.php">Users</a></li>
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
            <h5 class="fw-bold mb-0" style="color: var(--plum);">User Management</h5>
            <div class="search-wrapper">
                <input type="text" id="searchInput" placeholder="Search by name, role, or extension...">
                <button onclick="filterTable()">Search</button>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="glass-card">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="usersTable">
                            <thead>
                                <tr>
                                    <th class="sortable-header" onclick="sortTable(0, 'text')">
                                        User <i class="bi bi-arrow-down-up sort-icon"></i>
                                    </th>
                                    <th class="sortable-header" onclick="sortTable(1, 'text')">
                                        Role <i class="bi bi-arrow-down-up sort-icon"></i>
                                    </th>
                                    <th class="sortable-header" onclick="sortTable(2, 'text')">
                                        Extension <i class="bi bi-arrow-down-up sort-icon"></i>
                                    </th>
                                    <th class="sortable-header" onclick="sortTable(3, 'date')">
                                        Joined Date <i class="bi bi-arrow-down-up sort-icon"></i>
                                    </th>
                                    <th class="sortable-header text-center" style="cursor: default;">
                                        Manage
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <p class="mt-2">No users found in the database.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($users as $user): 

                                    $rowClass = $user['is_active'] ? '' : 'status-inactive';
                                    $suspendBtn = $user['is_active'] 
                                        ? '<button class="btn-action-round btn-suspend" title="Suspend" onclick="toggleUser(' . $user['id'] . ', \'suspend\')"><i class="bi bi-pause-fill"></i></button>'
                                        : '<button class="btn-action-round btn-restore" title="Restore" onclick="toggleUser(' . $user['id'] . ', \'restore\')"><i class="bi bi-play-fill"></i></button>';
                                ?>
                                <tr class="<?php echo $rowClass; ?>" data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>" data-role="<?php echo strtolower(htmlspecialchars($user['role'])); ?>" data-ext="<?php echo strtolower(htmlspecialchars($user['ext'])); ?>" data-joined="<?php echo $user['joined']; ?>">
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?>
                                            <?php if (!$user['is_active']): ?><span class="suspended-label">[SUSPENDED]</span><?php endif; ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </td>
                                    <td class="plain-text">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </td>
                                    <td class="plain-text"><?php echo htmlspecialchars($user['ext']); ?></td>
                                    <td class="text-muted"><?php echo $user['joined_display']; ?></td>
                                    <td class="text-center">
                                        <?php echo $suspendBtn; ?>
                                        <button class="btn-action-round btn-delete" title="Delete" onclick="deleteUser(<?php echo $user['id']; ?>)"><i class="bi bi-trash3-fill"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div id="noResults" class="no-results" style="display: none;">
                            <p class="mt-2">No users found matching your search.</p>
                        </div>
                    </div>
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
            const role = row.getAttribute('data-role');
            const ext = row.getAttribute('data-ext');
            if (name.includes(query) || role.includes(query) || ext.includes(query)) {
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

            if (type === 'date') {
                aVal = a.getAttribute('data-joined');
                bVal = b.getAttribute('data-joined');
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

    // ===== ACTION BUTTONS — consolidated process file =====
    function toggleUser(id, action) {
        const msg = action === 'suspend' 
            ? 'Suspend this user? They will not be able to log in or use the platform.' 
            : 'Restore this user? They will regain full access to the platform.';
        if (confirm(msg)) {
            fetch('admin_users_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + action + '&id=' + id
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Action failed'));
                }
            })
            .catch(() => alert('Network error. Please try again.'));
        }
    }

    function deleteUser(id) {
        if (confirm('Are you sure? This will permanently delete this user and all their data. This action cannot be undone.')) {
            fetch('admin_users_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete&id=' + id
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('User deleted successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Could not delete user'));
                }
            })
            .catch(() => alert('Network error. Please try again.'));
        }
    }
    </script>
</body>
</html>