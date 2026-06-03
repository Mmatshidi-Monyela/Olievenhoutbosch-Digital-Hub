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
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit();
}

switch ($action) {
    case 'suspend':
        $stmt = mysqli_prepare($conn, "UPDATE listing SET is_active = 0 WHERE listing_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Service suspended successfully.' : 'Failed to suspend service.'
        ]);
        break;

    case 'restore':
        $stmt = mysqli_prepare($conn, "UPDATE listing SET is_active = 1 WHERE listing_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Service restored successfully.' : 'Failed to restore service.'
        ]);
        break;

    case 'delete':
        // Start transaction for safe cascading delete
        mysqli_begin_transaction($conn);
        try {
            // Delete all comments for this listing
            $stmt1 = mysqli_prepare($conn, "DELETE FROM comment WHERE listing_id = ?");
            mysqli_stmt_bind_param($stmt1, 'i', $id);
            mysqli_stmt_execute($stmt1);
            mysqli_stmt_close($stmt1);

            // Delete all listing images
            $stmt2 = mysqli_prepare($conn, "DELETE FROM listing_images WHERE listing_id = ?");
            mysqli_stmt_bind_param($stmt2, 'i', $id);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            // Delete the listing
            $stmt3 = mysqli_prepare($conn, "DELETE FROM listing WHERE listing_id = ?");
            mysqli_stmt_bind_param($stmt3, 'i', $id);
            mysqli_stmt_execute($stmt3);
            mysqli_stmt_close($stmt3);

            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Service and all related data deleted successfully.']);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
}
?>