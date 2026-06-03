<?php
session_start();

// Admin auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// DB connection
$conn = null;
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
}

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

// Prevent admin from suspending/deleting themselves
$selfCheck = mysqli_prepare($conn, "SELECT user_id FROM useraccount WHERE user_id = ? AND user_role = 'Admin'");
mysqli_stmt_bind_param($selfCheck, 'i', $id);
mysqli_stmt_execute($selfCheck);
mysqli_stmt_store_result($selfCheck);
$isAdmin = mysqli_stmt_num_rows($selfCheck) > 0;
mysqli_stmt_close($selfCheck);

if ($isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Cannot modify admin accounts']);
    exit();
}

switch ($action) {
    case 'suspend':
        $stmt = mysqli_prepare($conn, "UPDATE useraccount SET is_active = 0 WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'User suspended successfully.' : 'Failed to suspend user.'
        ]);
        break;

    case 'restore':
        $stmt = mysqli_prepare($conn, "UPDATE useraccount SET is_active = 1 WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'User restored successfully.' : 'Failed to restore user.'
        ]);
        break;

    case 'delete':
        // Start transaction for safe cascading delete
        mysqli_begin_transaction($conn);
        try {
            // Delete user's comments
            $stmt1 = mysqli_prepare($conn, "DELETE FROM comment WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt1, 'i', $id);
            mysqli_stmt_execute($stmt1);
            mysqli_stmt_close($stmt1);

            // Delete user's listings and their related data
            // First get listing IDs
            $stmt2 = mysqli_prepare($conn, "SELECT listing_id FROM listing WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt2, 'i', $id);
            mysqli_stmt_execute($stmt2);
            $result = mysqli_stmt_get_result($stmt2);
            $listingIds = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $listingIds[] = $row['listing_id'];
            }
            mysqli_stmt_close($stmt2);

            // Delete comments on user's listings
            if (!empty($listingIds)) {
                $placeholders = implode(',', array_fill(0, count($listingIds), '?'));
                $stmt3 = mysqli_prepare($conn, "DELETE FROM comment WHERE listing_id IN ($placeholders)");
                $types = str_repeat('i', count($listingIds));
                mysqli_stmt_bind_param($stmt3, $types, ...$listingIds);
                mysqli_stmt_execute($stmt3);
                mysqli_stmt_close($stmt3);

                // Delete listing images
                $stmt4 = mysqli_prepare($conn, "DELETE FROM listing_images WHERE listing_id IN ($placeholders)");
                mysqli_stmt_bind_param($stmt4, $types, ...$listingIds);
                mysqli_stmt_execute($stmt4);
                mysqli_stmt_close($stmt4);

                // Delete listings
                $stmt5 = mysqli_prepare($conn, "DELETE FROM listing WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt5, 'i', $id);
                mysqli_stmt_execute($stmt5);
                mysqli_stmt_close($stmt5);
            }

            // Finally delete the user
            $stmt6 = mysqli_prepare($conn, "DELETE FROM useraccount WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt6, 'i', $id);
            mysqli_stmt_execute($stmt6);
            mysqli_stmt_close($stmt6);

            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'User and all related data deleted successfully.']);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
}
?>