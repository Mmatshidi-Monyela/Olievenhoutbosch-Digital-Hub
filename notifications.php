<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// DB connection
$conn = null;
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
}

if (!$conn) {
    die("Database connection failed.");
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read']) && !empty($_POST['notif_id'])) {
        $stmt = mysqli_prepare($conn, "UPDATE notification SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $_POST['notif_id'], $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: notifications.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
        exit;
    }

    if (isset($_POST['mark_all_read'])) {
        $stmt = mysqli_prepare($conn, "UPDATE notification SET is_read = 1 WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: notifications.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
        exit;
    }

    if (isset($_POST['delete_notif']) && !empty($_POST['notif_id'])) {
        $stmt = mysqli_prepare($conn, "DELETE FROM notification WHERE notification_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $_POST['notif_id'], $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: notifications.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
        exit;
    }
}

// Fetch notifications
$filter = $_GET['filter'] ?? 'all';
$where = "user_id = ?";
$params = [$user_id];
$types = "i";

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where .= " AND is_read = 1";
}

$stmt = mysqli_prepare($conn, "SELECT * FROM notification WHERE $where ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$notifications = [];
$unreadCount = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
    if (!$row['is_read']) $unreadCount++;
}
mysqli_stmt_close($stmt);

// Time ago helper
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

// Icon map
$icons = ['success' => 'check-circle', 'danger' => 'exclamation-triangle', 'info' => 'chat-left-text'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --plum: #230344; --rose-gold: #c99383; }
        body { background: #e6e6e6; font-family: "Segoe UI", sans-serif; }
        .navbar-custom { background: var(--plum); border-bottom: 3px solid var(--rose-gold); padding: 12px 20px; }
        .brand-text { font-size: 1.1rem; font-weight: bold; color: white; }
        .back-link { color: white; text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { opacity: 0.8; color: white; }
        .main-content { padding: 30px 50px 50px; }
        .page-title { color: var(--plum); font-weight: bold; }
        .filter-tab { padding: 6px 16px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; text-decoration: none; border: 1.5px solid var(--plum); color: var(--plum); background: white; }
        .filter-tab.active { background: var(--plum); color: white; }
        .notif-card { background: white; border-radius: 12px; padding: 16px 20px; margin-bottom: 10px; display: flex; gap: 14px; align-items: flex-start; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
        .notif-card.unread { border-left: 3px solid var(--plum); background: #faf8ff; }
        .notif-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .notif-icon.success { background: #e6ffed; color: #28a745; }
        .notif-icon.danger { background: #ffe5e5; color: #d9534f; }
        .notif-icon.info { background: #e7f3ff; color: #0d6efd; }
        .notif-title { font-weight: 700; color: var(--plum); font-size: 0.95rem; }
        .notif-message { color: #555; font-size: 0.85rem; line-height: 1.4; }
        .notif-meta { font-size: 0.75rem; color: #999; margin-top: 4px; }
        .badge-new { background: var(--plum); color: white; padding: 2px 8px; border-radius: 50px; font-size: 0.65rem; font-weight: 700; }
        .btn-action { width: 30px; height: 30px; border-radius: 6px; border: none; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; background: #f0f0f0; color: #666; }
        .btn-action:hover { background: var(--plum); color: white; }
        .btn-action.delete:hover { background: #d9534f; }
        .btn-mark-all { background: transparent; color: var(--plum); border: 1.5px solid var(--plum); border-radius: 8px; padding: 6px 14px; font-size: 0.8rem; font-weight: 600; }
        .btn-mark-all:hover { background: var(--plum); color: white; }
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 12px; }
        .empty-state i { font-size: 3rem; color: var(--rose-gold); }
        @media (max-width: 768px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<nav class="navbar navbar-custom sticky-top">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <a class="navbar-brand d-flex align-items-center" href="listing_dashboard.php">
            <img src="images/logo.png" width="28" height="28" class="me-2">
            <span class="brand-text">Olievenhoutbosch Digital Hub</span>
        </a>
        <a href="listing_dashboard.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</nav>

<div class="container-fluid main-content">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="page-title mb-0">Notifications</h4>
            <small class="text-muted"><?php echo count($notifications); ?> total<?php echo $unreadCount > 0 ? ' • <strong style="color:var(--plum)">' . $unreadCount . ' unread</strong>' : ''; ?></small>
        </div>
        <?php if ($unreadCount > 0): ?>
        <form method="POST"><button type="submit" name="mark_all_read" class="btn-mark-all"><i class="bi bi-check-all me-1"></i>Mark All Read</button></form>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-2 mb-3">
        <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">Unread <?php echo $unreadCount > 0 ? '<span class="badge bg-danger ms-1" style="font-size:0.6rem">' . $unreadCount . '</span>' : ''; ?></a>
        <a href="?filter=read" class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">Read</a>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="empty-state">
        <i class="bi bi-bell-slash mb-2"></i>
        <h5 class="fw-bold" style="color:var(--plum)">No Notifications</h5>
        <?php if ($filter !== 'all'): ?><a href="notifications.php" class="btn btn-sm btn-outline-primary mt-1" style="border-color:var(--plum);color:var(--plum)">View All</a><?php endif; ?>
    </div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
        <div class="notif-card <?php echo $n['is_read'] ? '' : 'unread'; ?>">
            <div class="notif-icon <?php echo $n['type']; ?>"><i class="bi bi-<?php echo $icons[$n['type']] ?? 'bell'; ?>"></i></div>
            <div class="flex-grow-1" style="min-width:0">
                <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                <div class="notif-message"><?php echo htmlspecialchars($n['message']); ?></div>
                <div class="notif-meta">
                    <i class="bi bi-clock me-1"></i><?php echo timeAgo($n['created_at']); ?>
                    <?php if (!$n['is_read']): ?><span class="badge-new ms-2">NEW</span><?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-1">
                <?php if (!$n['is_read']): ?>
                <form method="POST"><input type="hidden" name="notif_id" value="<?php echo $n['notification_id']; ?>"><button type="submit" name="mark_read" class="btn-action" title="Mark read"><i class="bi bi-check-lg"></i></button></form>
                <?php endif; ?>
                <?php if (!empty($n['link'])): ?><a href="<?php echo htmlspecialchars($n['link']); ?>" class="btn-action" title="View"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this notification?')"><input type="hidden" name="notif_id" value="<?php echo $n['notification_id']; ?>"><button type="submit" name="delete_notif" class="btn-action delete" title="Delete"><i class="bi bi-trash3"></i></button></form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>