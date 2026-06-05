<?php
session_start();
include 'includes/db_connect.php'; // Creates $conn (MySQLi)

/** @var mysqli $conn */

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// FIX: Clear stale flash messages from other pages (like update listing)
// Only clear on fresh page loads (GET), not when viewing a specific thread
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['thread'])) {
    unset($_SESSION['success_msg']);
    unset($_SESSION['error_msg']);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';
$user_type = $_SESSION['user_role'] ?? 'Customer';

// Determine which view: 'sent' = customer-side chats I started, 'received' = provider-side inquiries about my listings
$view = $_GET['view'] ?? 'sent';

// Validate view
if (!in_array($view, ['sent', 'received'], true)) {
    $view = 'sent';
}

// Role-based back links
$role_dashboards = [
    'Provider' => 'listing_dashboard.php',
    'Admin'    => 'admin_dashboard.php',
    'Customer' => 'main.php',
    'Both'     => 'main.php'
];
$default_back = $role_dashboards[$user_type] ?? 'main.php';

// Back link: if view is 'received', go back to listing dashboard. If 'sent', go back to main.
$back_link = ($view === 'received') ? 'listing_dashboard.php' : $default_back;

// Helper: Time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $time);
}

function safeRedirect($url) {
    header("Location: $url");
    exit;
}

function dbError($conn, $context = '') {
    error_log("DB Error [$context]: " . mysqli_error($conn));
    $_SESSION['error_msg'] = 'A database error occurred. Please try again.';
}

// Get unread count for badge (total across both views)
$unread_count = 0;
if ($conn) {
    $unread_sql = "SELECT COUNT(*) as unread_count FROM message WHERE receiver_id = ? AND read_status = 0";
    $unread_stmt = mysqli_prepare($conn, $unread_sql);
    if ($unread_stmt) {
        mysqli_stmt_bind_param($unread_stmt, "i", $user_id);
        mysqli_stmt_execute($unread_stmt);
        $unread_result = mysqli_stmt_get_result($unread_stmt);
        $unread_row = mysqli_fetch_assoc($unread_result);
        $unread_count = (int)($unread_row['unread_count'] ?? 0);
        mysqli_stmt_close($unread_stmt);
    }
}

// Get conversation threads based on view
$threads = [];
if ($conn) {
    if ($view === 'sent') {
        // Customer-side: conversations where I (the user) was the sender
        $threads_sql = "SELECT 
            m.message_id,
            m.listing_id,
            m.sender_id,
            m.receiver_id,
            m.message as last_message,
            m.created_at as last_message_time,
            m.read_status,
            l.listing_name,
            u.full_name as other_person_name,
            u.user_id as other_person_id,
            (SELECT COUNT(*) FROM message 
             WHERE receiver_id = ? AND sender_id = u.user_id 
             AND listing_id = m.listing_id AND read_status = 0) as unread_in_thread
        FROM message m
        JOIN listing l ON m.listing_id = l.listing_id
        JOIN useraccount u ON m.receiver_id = u.user_id
        WHERE m.sender_id = ?
        AND m.created_at IN (
            SELECT MAX(created_at) FROM message 
            WHERE sender_id = ? 
            GROUP BY listing_id, receiver_id
        )
        ORDER BY m.created_at DESC";
        
        $threads_stmt = mysqli_prepare($conn, $threads_sql);
        if ($threads_stmt) {
            mysqli_stmt_bind_param($threads_stmt, "iii", $user_id, $user_id, $user_id);
            mysqli_stmt_execute($threads_stmt);
            $threads_result = mysqli_stmt_get_result($threads_stmt);
            while ($row = mysqli_fetch_assoc($threads_result)) {
                $threads[] = $row;
            }
            mysqli_stmt_close($threads_stmt);
        }
    } else {
        // Provider-side: conversations about my listings where someone else initiated
        $threads_sql = "SELECT 
            m.message_id,
            m.listing_id,
            m.sender_id,
            m.receiver_id,
            m.message as last_message,
            m.created_at as last_message_time,
            m.read_status,
            l.listing_name,
            u.full_name as other_person_name,
            u.user_id as other_person_id,
            (SELECT COUNT(*) FROM message 
             WHERE receiver_id = ? AND sender_id = u.user_id 
             AND listing_id = m.listing_id AND read_status = 0) as unread_in_thread
        FROM message m
        JOIN listing l ON m.listing_id = l.listing_id
        JOIN useraccount u ON m.sender_id = u.user_id
        WHERE l.user_id = ? AND m.sender_id != ?
        AND m.created_at IN (
            SELECT MAX(created_at) FROM message 
            WHERE listing_id = l.listing_id 
            GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
        )
        ORDER BY m.created_at DESC";
        
        $threads_stmt = mysqli_prepare($conn, $threads_sql);
        if ($threads_stmt) {
            mysqli_stmt_bind_param($threads_stmt, "iii", $user_id, $user_id, $user_id);
            mysqli_stmt_execute($threads_stmt);
            $threads_result = mysqli_stmt_get_result($threads_stmt);
            while ($row = mysqli_fetch_assoc($threads_result)) {
                $threads[] = $row;
            }
            mysqli_stmt_close($threads_stmt);
        }
    }
}

// If viewing a specific conversation
$active_thread = null;
$messages = [];
$other_user_id = 0;
$listing_id = 0;

if (isset($_GET['thread']) && isset($_GET['listing'])) {
    $other_user_id = filter_input(INPUT_GET, 'thread', FILTER_VALIDATE_INT);
    $listing_id = filter_input(INPUT_GET, 'listing', FILTER_VALIDATE_INT);

    if ($other_user_id === false || $listing_id === false || $other_user_id <= 0 || $listing_id <= 0) {
        safeRedirect('messages.php?view=' . $view);
    }

    // Security: Verify this user is actually part of a conversation about this listing
    $verify_sql = "SELECT COUNT(*) as cnt FROM message 
                   WHERE listing_id = ? 
                   AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    $conversation_exists = false;
    if ($verify_stmt) {
        mysqli_stmt_bind_param($verify_stmt, "iiiii", $listing_id, $user_id, $other_user_id, $other_user_id, $user_id);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);
        $verify_row = mysqli_fetch_assoc($verify_result);
        $conversation_exists = ((int)$verify_row['cnt'] > 0);
        mysqli_stmt_close($verify_stmt);
    }

    // Also allow if user is viewing their own listing (provider checking messages)
    if (!$conversation_exists) {
        $owner_sql = "SELECT user_id FROM listing WHERE listing_id = ? AND user_id = ?";
        $owner_stmt = mysqli_prepare($conn, $owner_sql);
        if ($owner_stmt) {
            mysqli_stmt_bind_param($owner_stmt, "ii", $listing_id, $user_id);
            mysqli_stmt_execute($owner_stmt);
            $owner_result = mysqli_stmt_get_result($owner_stmt);
            $is_owner = mysqli_fetch_assoc($owner_result);
            mysqli_stmt_close($owner_stmt);
            if (!$is_owner) {
                safeRedirect('messages.php?view=' . $view);
            }
        }
    }

    // Mark messages as read
    $mark_sql = "UPDATE message 
                 SET read_status = 1 
                 WHERE receiver_id = ? AND sender_id = ? AND listing_id = ? AND read_status = 0";
    $mark_stmt = mysqli_prepare($conn, $mark_sql);
    if ($mark_stmt) {
        mysqli_stmt_bind_param($mark_stmt, "iii", $user_id, $other_user_id, $listing_id);
        mysqli_stmt_execute($mark_stmt);
        mysqli_stmt_close($mark_stmt);
    }

    // Get all messages in this thread
    $msg_sql = "SELECT m.*, u.full_name as sender_name
                FROM message m
                JOIN useraccount u ON m.sender_id = u.user_id
                WHERE m.listing_id = ? 
                AND ((m.sender_id = ? AND m.receiver_id = ?) 
                     OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC";
    $msg_stmt = mysqli_prepare($conn, $msg_sql);
    if ($msg_stmt) {
        mysqli_stmt_bind_param($msg_stmt, "iiiii", $listing_id, $user_id, $other_user_id, $other_user_id, $user_id);
        mysqli_stmt_execute($msg_stmt);
        $msg_result = mysqli_stmt_get_result($msg_stmt);
        while ($row = mysqli_fetch_assoc($msg_result)) {
            $messages[] = $row;
        }
        mysqli_stmt_close($msg_stmt);
    }

    // Get thread info
    $thread_info_sql = "SELECT u.full_name as other_person_name, l.listing_name, l.listing_id
                        FROM useraccount u
                        JOIN listing l ON l.listing_id = ?
                        WHERE u.user_id = ?";
    $thread_info_stmt = mysqli_prepare($conn, $thread_info_sql);
    if ($thread_info_stmt) {
        mysqli_stmt_bind_param($thread_info_stmt, "ii", $listing_id, $other_user_id);
        mysqli_stmt_execute($thread_info_stmt);
        $thread_info_result = mysqli_stmt_get_result($thread_info_stmt);
        $active_thread = mysqli_fetch_assoc($thread_info_result);
        mysqli_stmt_close($thread_info_stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Olievenhoutbosch Digital Hub</title>
    <link rel="icon" type="image/png" href="images/logo.png"> 
    <link rel="apple-touch-icon" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #c99383;
            --copper: #ba745f;
            --light-grey: #f4f7f6;
        }

        body {
            background-color: var(--light-grey);
            font-family: 'Inter', sans-serif;
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
            font-size: 0.95rem;
            font-weight: 700;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        .back-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .back-link:hover { opacity: 0.8; color: white; }

        /* Brand name swap: full on desktop, short on mobile */
        .brand-text.full-name { display: inline !important; }
        .brand-text.short-name { display: none !important; }

        @media (max-width: 575.98px) {
            .brand-text.full-name { display: none !important; }
            .brand-text.short-name { display: inline !important; }
            .back-link span { display: none; }
            .back-link { font-size: 1.1rem; padding: 8px; margin-right: -8px; }
        }

        .messages-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            min-height: 70vh;
        }

        .thread-list {
            border-right: 1px solid #eee;
            max-height: 70vh;
            overflow-y: auto;
        }

        .thread-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .thread-item:hover {
            background-color: #f8f9fa;
        }
        .thread-item.active {
            background-color: var(--light-grey);
            border-left: 3px solid var(--plum);
        }
        .thread-item.unread {
            background-color: #fff8f7;
        }

        .thread-avatar {
            width: 45px;
            height: 45px;
            background-color: var(--rose-gold);
            color: var(--plum);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .thread-name {
            font-weight: 600;
            color: var(--plum);
            font-size: 0.95rem;
        }

        .thread-listing {
            font-size: 0.8rem;
            color: var(--copper);
        }

        .thread-preview {
            font-size: 0.85rem;
            color: #888;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .thread-time {
            font-size: 0.75rem;
            color: #aaa;
            white-space: nowrap;
        }

        .unread-badge {
            background-color: var(--copper);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .view-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            background: #fafafa;
        }
        .view-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            color: #888;
            font-weight: 500;
            font-size: 0.9rem;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .view-tab:hover {
            color: var(--plum);
            background: #f0f0f0;
        }
        .view-tab.active {
            color: var(--plum);
            border-bottom-color: var(--plum);
            background: white;
            font-weight: 600;
        }

        /* Chat area */
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            background: white;
            border-radius: 0 15px 0 0;
        }

        .chat-messages {
            padding: 20px;
            max-height: 55vh;
            overflow-y: auto;
            background: #fafafa;
        }

        .message-bubble {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 15px;
            margin-bottom: 12px;
            font-size: 0.9rem;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .message-sent {
            background-color: var(--plum);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }

        .message-received {
            background-color: white;
            color: #333;
            border: 1px solid #eee;
            border-bottom-left-radius: 4px;
        }

        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 4px;
        }

        .chat-input-area {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            background: white;
            border-radius: 0 0 15px 0;
        }

        .btn-plum {
            background-color: var(--plum);
            color: white;
            border-radius: 50px;
            font-weight: bold;
            padding: 10px 24px;
            border: none;
        }
        .btn-plum:hover { opacity: 0.9; color: white; }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #888;
        }
        .empty-state i {
            font-size: 4rem;
            color: var(--rose-gold);
            margin-bottom: 20px;
        }

        @media (max-width: 991px) {
            .thread-list {
                border-right: none;
                border-bottom: 1px solid #eee;
                max-height: 40vh;
            }
            .chat-messages {
                max-height: 45vh;
            }
        }

        /* ===== MOBILE CHAT VIEW ===== */
        @media (max-width: 991px) {
            .messages-container {
                position: relative;
                overflow: hidden;
            }
            .thread-list {
                border-right: none;
                border-bottom: none;
                max-height: none;
                height: calc(100vh - 180px);
            }
            .chat-area-mobile {
                display: none;
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: white;
                z-index: 10;
                border-radius: 15px;
            }
            .chat-area-mobile.active {
                display: flex;
                flex-direction: column;
            }
            .chat-messages {
                max-height: none;
                flex: 1;
                overflow-y: auto;
            }
            .chat-header {
                border-radius: 15px 15px 0 0;
                flex-shrink: 0;
            }
            .chat-input-area {
                border-radius: 0 0 15px 15px;
                flex-shrink: 0;
            }
            .mobile-back-btn {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                background: none;
                border: none;
                color: var(--plum);
                font-size: 0.9rem;
                font-weight: 600;
                padding: 4px 8px;
                margin-right: 8px;
                cursor: pointer;
            }
        }

        @media (min-width: 992px) {
            .mobile-back-btn {
                display: none !important;
            }
            .chat-area-mobile {
                position: static !important;
                display: flex !important;
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .thread-item {
                padding: 12px;
            }
            .message-bubble {
                max-width: 85%;
                padding: 10px 14px;
            }
            .chat-header {
                padding: 12px 16px;
            }
            .chat-messages {
                padding: 16px;
            }
            .chat-input-area {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar top-nav sticky-top">
    <div class="container-fluid d-flex align-items-center justify-content-between" style="width:100%;padding:0 16px;">
        <a href="<?php echo htmlspecialchars($back_link); ?>" class="navbar-brand d-flex align-items-center" style="text-decoration:none;">
            <img src="images/logo.png" width="28" height="28" alt="logo" class="me-2" style="flex-shrink:0;">
            <span class="brand-text full-name">Olievenhoutbosch Digital Hub</span>
            <span class="brand-text short-name">Olievenhoutbosch DH</span>
        </a>
        <a href="<?php echo htmlspecialchars($back_link); ?>" class="back-link">
            <i class="bi bi-arrow-left"></i>
            <span>Back</span>
        </a>
    </div>
</nav>

<?php if (isset($_SESSION['error_msg'])): ?>
<div class="alert alert-danger alert-dismissible fade show shadow m-3" role="alert">
    <?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['success_msg'])): ?>
<div class="alert alert-success alert-dismissible fade show shadow m-3" role="alert">
    <?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<main class="container my-4">
    <h4 class="fw-bold mb-3" style="color: var(--plum);">Messages</h4>
    <div class="messages-container">
        
        <?php if (in_array($user_type, ['Provider', 'Both'], true)): ?>
        <div class="view-tabs">
            <a href="messages.php?view=sent" class="view-tab <?php echo $view === 'sent' ? 'active' : ''; ?>">
                 Sent
            </a>
            <a href="messages.php?view=received" class="view-tab <?php echo $view === 'received' ? 'active' : ''; ?>">
                Received
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.7rem;"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

        <div class="row g-0">
            <!-- Thread List (Left Side) -->
            <div class="col-lg-4 thread-list">
                <div class="p-3 border-bottom">
                    <h5 class="fw-bold mb-0" style="color: var(--plum);">
                        <?php echo $view === 'sent' ? 'Your Conversations' : 'Inquiries'; ?>
                    </h5>
                </div>

                <?php if (count($threads) === 0): ?>
                    <div class="p-4 text-center text-muted">
                        <p class="mb-0 small">
                            <?php echo $view === 'sent' ? 'No messages sent yet' : 'No inquiries yet'; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($threads as $thread): 
                        $is_active = $active_thread && $active_thread['listing_id'] == $thread['listing_id'];
                        $is_unread = (int)$thread['unread_in_thread'] > 0;
                        $initials = implode('', array_map(function($n) { return strtoupper($n[0]); }, explode(' ', $thread['other_person_name'])));
                        if (strlen($initials) > 2) $initials = substr($initials, 0, 2);
                    ?>
                        <a href="messages.php?view=<?php echo $view; ?>&thread=<?php echo (int)$thread['other_person_id']; ?>&listing=<?php echo (int)$thread['listing_id']; ?>" 
                           class="thread-item <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_unread ? 'unread' : ''; ?>"
                           onclick="openMobileChat(event)">
                            <div class="d-flex align-items-center">
                                <div class="thread-avatar me-3">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="thread-name"><?php echo htmlspecialchars($thread['other_person_name']); ?></span>
                                        <span class="thread-time"><?php echo timeAgo($thread['last_message_time']); ?></span>
                                    </div>
                                    <div class="thread-listing">
                                        <?php echo htmlspecialchars($thread['listing_name']); ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <span class="thread-preview"><?php echo htmlspecialchars(substr($thread['last_message'], 0, 40)) . (strlen($thread['last_message']) > 40 ? '...' : ''); ?></span>
                                        <?php if ($is_unread): ?>
                                            <span class="unread-badge"><?php echo (int)$thread['unread_in_thread']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Chat Area (Right Side) -->
            <div class="col-lg-8 chat-area-mobile" id="chatAreaMobile">
                <?php if ($active_thread): ?>
                    <div class="chat-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <button type="button" class="mobile-back-btn" onclick="closeMobileChat()" aria-label="Back to conversations">
                                <i class="bi bi-arrow-left" style="font-size:1.1rem;"></i>
                            </button>
                        <div>
                            <h6 class="fw-bold mb-0" style="color: var(--plum);">
                                <?php echo htmlspecialchars($active_thread['other_person_name'] ?? 'Unknown'); ?>
                            </h6>
                            <small class="text-muted">
                                <a href="view_service.php?id=<?php echo (int)$active_thread['listing_id']; ?>" class="text-decoration-none" style="color: var(--copper);">
                                    <?php echo htmlspecialchars($active_thread['listing_name'] ?? 'Unknown Listing'); ?>
                                </a>
                            </small>
                        </div>
                        </div>
                    </div>

                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-chat-square-text fs-1" style="color: var(--rose-gold);"></i>
                                <p class="mt-2">No messages yet. Say hello!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="message-bubble <?php echo $msg['sender_id'] == $user_id ? 'message-sent' : 'message-received'; ?>">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    <div class="message-time text-end">
                                        <?php echo date('M j, g:i a', strtotime($msg['created_at'])); ?>
                                        <?php if ($msg['sender_id'] == $user_id): ?>
                                            <i class="bi bi-check2-all ms-1"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="chat-input-area">
                        <form action="send_message.php" method="POST" class="d-flex gap-2">
                            <input type="hidden" name="listing_id" value="<?php echo (int)($active_thread['listing_id'] ?? 0); ?>">
                            <input type="hidden" name="receiver_id" value="<?php echo (int)$other_user_id; ?>">
                            <input type="hidden" name="return_to" value="messages.php?view=<?php echo $view; ?>&thread=<?php echo (int)$other_user_id; ?>&listing=<?php echo (int)($active_thread['listing_id'] ?? 0); ?>">
                            <textarea name="message" class="form-control rounded-pill" rows="1" placeholder="Type a message..." required style="resize: none; padding: 10px 20px;"></textarea>
                            <button type="submit" class="btn btn-plum">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-state d-none d-lg-flex">
                        <h5>Select a conversation</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    const textarea = document.querySelector('textarea[name="message"]');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        textarea.focus();
    }

    // Mobile chat view switching
    function openMobileChat(e) {
        if (window.innerWidth < 992) {
            // Let the navigation happen, then show chat
            // We use a small delay to let the page load, then activate
            setTimeout(function() {
                const chatArea = document.getElementById('chatAreaMobile');
                if (chatArea) chatArea.classList.add('active');
            }, 50);
        }
    }

    function closeMobileChat() {
        const chatArea = document.getElementById('chatAreaMobile');
        if (chatArea) chatArea.classList.remove('active');
        // On mobile, go back to thread list without thread params
        if (window.innerWidth < 992) {
            const url = new URL(window.location);
            url.searchParams.delete('thread');
            url.searchParams.delete('listing');
            window.history.replaceState({}, '', url);
        }
    }

    // On page load, if there's an active thread on mobile, show chat
    document.addEventListener('DOMContentLoaded', function() {
        const chatArea = document.getElementById('chatAreaMobile');
        const hasThread = <?php echo $active_thread ? 'true' : 'false'; ?>;
        if (window.innerWidth < 992 && hasThread && chatArea) {
            chatArea.classList.add('active');
        }
    });
</script>
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