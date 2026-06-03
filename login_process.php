<?php
session_start();
include 'includes/db_connect.php';

/** @var mysqli $conn */

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: login.php?error=empty");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: login.php?error=invalidemail");
        exit();
    }

    $allowedDomains = ['gmail.com', 'co.za', 'org', 'net'];
    $emailDomain = substr(strrchr($email, '@'), 1);
    $isValidDomain = false;

    foreach ($allowedDomains as $domain) {
        if ($emailDomain === $domain || str_ends_with($emailDomain, '.' . $domain)) {
            $isValidDomain = true;
            break;
        }
    }

    if (!$isValidDomain) {
        header("Location: login.php?error=invalidemail");
        exit();
    }

    $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, user_role, password, is_active FROM UserAccount WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        
        if ((int)$row['is_active'] === 0) {
            header("Location: login.php?error=suspended");
            exit();
        }

        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['user_role'] = $row['user_role'];

            if ($row['user_role'] == 'Admin') {
                header("Location: admin_dashboard.php");
            } elseif ($row['user_role'] == 'Provider') {
                header("Location: listing_dashboard.php");
            } elseif ($row['user_role'] == 'Both') {
                header("Location: main.php?mode=both");
            } else {
                header("Location: main.php");
            }
            exit();
        } else {
            header("Location: login.php?error=wrongpass");
            exit();
        }
    } else {
        header("Location: login.php?error=nouser");
        exit();
    }

    mysqli_stmt_close($stmt);
}
?>