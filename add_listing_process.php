<?php
session_start();
include 'includes/db_connect.php';
/** @var mysqli $conn */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: add_listing.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0) {
    header("Location: login.php");
    exit();
}

// ============================================
// SANITIZE INPUTS
// ============================================
$listing_name   = trim($_POST['listing_name'] ?? '');
$listing_type   = $_POST['listing_type'] ?? 'service';
$category       = $_POST['category'] ?? '';
$service_type   = $_POST['service_type'] ?? '';
$extension      = $_POST['extension'] ?? '';
$delivery_mode  = $_POST['delivery_mode'] ?? 'door_to_door';
$street_address = trim($_POST['street_address'] ?? '');
$price_desc     = trim($_POST['price_description'] ?? '');
$description    = trim($_POST['description'] ?? '');

// Payment options
$payment_options = [];
if (isset($_POST['payment_options']) && is_array($_POST['payment_options'])) {
    $payment_options = array_filter($_POST['payment_options']);
}
$payment_options_str = !empty($payment_options) ? implode(',', $payment_options) : 'Cash';

// Additional extensions
$service_extensions = [];
if (isset($_POST['service_extensions']) && is_array($_POST['service_extensions'])) {
    $service_extensions = array_filter($_POST['service_extensions']);
}
$service_extensions_str = !empty($service_extensions) ? implode(',', $service_extensions) : null;

// ============================================
// VALIDATION
// ============================================
$errors = [];
if (strlen($listing_name) < 3)  $errors[] = "Listing name too short (min 3 characters).";
if (strlen($description) < 10)  $errors[] = "Description too short (min 10 characters).";
if (empty($category))           $errors[] = "Please select a category.";
if (empty($service_type))       $errors[] = "Please select a service/product type.";
if (empty($extension))          $errors[] = "Please select a primary extension.";
if (empty($price_desc))         $errors[] = "Please enter a price description.";

// Photos are now required
if (empty($_FILES['work_photos']) || empty($_FILES['work_photos']['name'][0])) {
    $errors[] = "Please upload at least one photo.";
}

// Address required for fixed-location modes
if ((strpos($delivery_mode, 'customer_comes_to_me') !== false || 
     strpos($delivery_mode, 'customer_pickup') !== false ||
     strpos($delivery_mode, 'both_service') !== false ||
     strpos($delivery_mode, 'both_product') !== false) && empty($street_address)) {
    $errors[] = "Street address is required when customers come to you or pick up.";
}

if ($errors) {
    $_SESSION['error_msg'] = implode("<br>", $errors);
    header("Location: add_listing.php");
    exit();
}

// ============================================
// HANDLE IMAGE UPLOADS
// ============================================
$uploaded = [];
$max = 5;
$max_size = 2 * 1024 * 1024;
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];

if (!empty($_FILES['work_photos']) && is_array($_FILES['work_photos']['name'])) {
    for ($i = 0; $i < count($_FILES['work_photos']['name']) && count($uploaded) < $max; $i++) {
        if (empty($_FILES['work_photos']['tmp_name'][$i])) continue;

        $tmp  = $_FILES['work_photos']['tmp_name'][$i];
        $name = $_FILES['work_photos']['name'][$i];
        $size = $_FILES['work_photos']['size'][$i];
        $type = $_FILES['work_photos']['type'][$i];

        if ($size > $max_size) continue;
        if (!in_array($type, $allowed_types)) continue;
        if (getimagesize($tmp) === false) continue;

        $dir = "uploads/listings/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $safe_name = preg_replace('/[^a-zA-Z0-9.-]/', '_', $name);
        $target = $dir . time() . "_" . $i . "_" . $safe_name;

        if (move_uploaded_file($tmp, $target)) {
            $uploaded[] = $target;
        }
    }
}

$image_path = $uploaded[0] ?? 'uploads/listings/default_listing.jpg';

// ============================================
// INSERT LISTING
// ============================================
$stmt = mysqli_prepare($conn, "INSERT INTO listing (
    user_id, listing_name, category, listing_type, service_type, extension, service_extensions,
    delivery_mode, street_address, price_description, payment_options,
    description, image_path, verification_status, is_active
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unverified', 1)");

mysqli_stmt_bind_param($stmt, "issssssssssss",
    $user_id,
    $listing_name,
    $category,
    $listing_type,
    $service_type,
    $extension,
    $service_extensions_str,
    $delivery_mode,
    $street_address,
    $price_desc,
    $payment_options_str,
    $description,
    $image_path
);

mysqli_stmt_execute($stmt);
$listing_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

// Insert additional images
if ($uploaded) {
    $stmt = mysqli_prepare($conn, "INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)");
    foreach ($uploaded as $path) {
        mysqli_stmt_bind_param($stmt, "is", $listing_id, $path);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
}

$photo_msg = count($uploaded) > 0 ? " " . count($uploaded) . " photo(s) uploaded." : "";
$_SESSION['success_msg'] = "Listing created successfully!" . $photo_msg;
header("Location: listing_dashboard.php");
exit();