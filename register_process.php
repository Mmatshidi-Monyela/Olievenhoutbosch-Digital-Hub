<?php
include 'includes/db_connect.php';
/** @var mysqli $conn */

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $extension = $_POST['extension'] ?? '';
    $role = $_POST['user_role'] ?? 'Customer';
    $allowed_roles = ['Customer', 'Provider', 'Both'];
    if(!in_array($role, $allowed_roles))
    {
        $role = 'Customer';
    }
    $plain_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $contact_number_raw = trim($_POST['contact_number'] ?? '');
    
    $security_question = $_POST['security_question'] ?? '';
    $security_answer = trim($_POST['security_answer'] ?? '');

    // Name validation
    if (preg_match('~[0-9]~', $full_name)) {
        header("Location: register.php?error=nameinvalid");
        exit();
    }
    if (strlen($full_name) < 2) {
        header("Location: register.php?error=nameshort");
        exit();
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=invalidemail");
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
        header("Location: register.php?error=invalidemail");
        exit();
    }

    // Security question validation
    if (empty($security_question) || empty($security_answer)) {
        header("Location: register.php?error=securityempty");
        exit();
    }

    // Contact number validation - simplified, no forced +27
    if (empty($contact_number_raw)) {
        header("Location: register.php?error=nophone");
        exit();
    }
    $digits_only = preg_replace('/[^0-9]/', '', $contact_number_raw);
    $length = strlen($digits_only);

    if ($length < 9 || $length > 10) {
        header("Location: register.php?error=phoneinvalid");
        exit();
    }
    
    // Validate SA mobile prefix (6, 7, 8)
    if ($length === 10 && strpos($digits_only, '0') === 0) {
        $prefix = substr($digits_only, 1, 1);
    } elseif ($length === 9) {
        $prefix = substr($digits_only, 0, 1);
    } else {
        header("Location: register.php?error=phoneinvalid");
        exit();
    }
    
    $valid_prefixes = ['6', '7', '8'];
    if (!in_array($prefix, $valid_prefixes)) {
        header("Location: register.php?error=phoneinvalid");
        exit();
    }

    // Store as-is (user's input format)
    $contact_number = $contact_number_raw;

    // Check email exists
    $stmt = mysqli_prepare($conn, "SELECT email FROM useraccount WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        header("Location: register.php?error=emailtaken");
        exit();
    }
    mysqli_stmt_close($stmt);

    // Password validation
    if ($plain_password !== $confirm_password) {
        header("Location: register.php?error=passmatch");
        exit();
    }
    if (strlen($plain_password) < 6) {
        header("Location: register.php?error=passshort");
        exit();
    }

    // Hash passwords
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    $hashed_security_answer = password_hash(strtolower($security_answer), PASSWORD_DEFAULT);

    // Insert with security_question and security_answer
    $stmt = mysqli_prepare($conn, "INSERT INTO useraccount 
        (full_name, email, password, extension, user_role, contact_number, security_question, security_answer) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssssss", 
        $full_name, $email, $hashed_password, $extension, $role, $contact_number, 
        $security_question, $hashed_security_answer);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: login.php?msg=success");
    } else {
        echo "Database Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}
?>