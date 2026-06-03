<?php
session_start();
include 'includes/db_connect.php';
/** @var mysqli $conn */

$error = '';
$success = '';
$step = 1; // Step 1: Enter email, Step 2: Answer question, Step 3: Reset password
$user_data = null;

// Track where the user came from so we can send them back there
if (!isset($_SESSION['forgot_back']) && isset($_SERVER['HTTP_REFERER'])) {
    $from = $_SERVER['HTTP_REFERER'];
    if (strpos($from, $_SERVER['HTTP_HOST']) !== false) {
        $_SESSION['forgot_back'] = $from;
    }
}
$back_link = $_SESSION['forgot_back'] ?? 'login.php';

// Handle Step 1: Verify email exists
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && !isset($_POST['security_answer'])) {
    $email = trim($_POST['email']);

    $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, security_question FROM UserAccount WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user_data = mysqli_fetch_assoc($result)) {
        $_SESSION['reset_user_id'] = $user_data['user_id'];
        $_SESSION['reset_email'] = $email;
        $step = 2;
    } else {
        $error = 'No account found with that email address.';
    }
    mysqli_stmt_close($stmt);
}

// Handle Step 2: Verify security answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['security_answer'])) {
    $answer = trim($_POST['security_answer']);
    $user_id = $_SESSION['reset_user_id'] ?? 0;

    $stmt = mysqli_prepare($conn, "SELECT security_answer FROM UserAccount WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify(strtolower($answer), $row['security_answer'])) {
            $step = 3;
        } else {
            $error = 'Incorrect answer. Please try again.';
            $step = 2;
        }
    }
    mysqli_stmt_close($stmt);
}

// Handle Step 3: Update password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['reset_user_id'] ?? 0;

    if (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
        $step = 3;
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
        $step = 3;
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE UserAccount SET password = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Password reset successfully! You can now login.';
            session_unset();
            session_destroy();
            $step = 4; // Done
        } else {
            $error = 'Something went wrong. Please try again.';
            $step = 3;
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #f8c9c0;
            --light-grey: #f4f7f6;
        }
        body {
            background-color: var(--light-grey);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .btn-rose {
            background-color: var(--rose-gold);
            border: none;
            color: var(--plum);
            font-weight: bold;
        }
        .btn-rose:hover {
            background-color: #f2b8ad;
            color: var(--plum);
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(248, 201, 192, 0.5);
            border-color: var(--rose-gold);
        }
        .back-link {
            color: #6c757d;
            font-size: 0.85rem;
            text-decoration: none;
        }
        .back-link:hover {
            color: var(--plum);
        }
        .security-question-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card reset-card p-4 p-md-5">

                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="bi bi-shield-lock" style="font-size: 3rem; color: var(--plum);"></i>
                    </div>
                    <h3 class="fw-bold" style="color: var(--plum);">Reset Password</h3>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success py-2"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Step 1: Enter Email -->
                <?php if ($step == 1): ?>
                    <p class="text-muted small mb-3">Enter your email to verify your identity.</p>
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg bg-light border-0" 
                                   placeholder="you@example.com" required>
                        </div>
                        <button type="submit" class="btn btn-rose w-100 py-2 mb-3">Continue</button>
                    </form>
                <?php endif; ?>

                <!-- Step 2: Answer Security Question -->
                <?php if ($step == 2 && $user_data): ?>
                    <p class="text-muted small mb-3">Answer your security question to proceed.</p>
                    <div class="security-question-box">
                        <label class="form-label small fw-bold text-dark">Security Question:</label>
                        <p class="mb-0" style="color: var(--plum);"><?php echo htmlspecialchars($user_data['security_question']); ?></p>
                    </div>
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Your Answer</label>
                            <input type="text" name="security_answer" class="form-control form-control-lg bg-light border-0" 
                                   placeholder="Type your answer here" required autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-rose w-100 py-2 mb-3">Verify Answer</button>
                    </form>
                <?php endif; ?>

                <!-- Step 3: Set New Password -->
                <?php if ($step == 3): ?>
                    <p class="text-muted small mb-3">Create a new password for your account.</p>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">New Password</label>
                            <input type="password" name="new_password" class="form-control form-control-lg bg-light border-0" 
                                   required minlength="6">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control form-control-lg bg-light border-0" 
                                   required>
                        </div>
                        <button type="submit" class="btn btn-rose w-100 py-2 mb-3">Reset Password</button>
                    </form>
                <?php endif; ?>

                <!-- Step 4: Success -->
                <?php if ($step == 4): ?>
                    <div class="text-center">
                        <a href="<?php echo htmlspecialchars($back_link); ?>" class="btn btn-rose w-100 py-2">Continue</a>
                    </div>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="<?php echo htmlspecialchars($back_link); ?>" class="back-link">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

</body>
</html>