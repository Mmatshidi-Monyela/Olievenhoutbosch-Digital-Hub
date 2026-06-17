<?php
session_start();

// Check if database file exists
if (!file_exists('includes/db_connect.php')) {
    $_SESSION['error_msg'] = 'Database connection failed.';
    header('Location: listing_dashboard.php');
    exit();
}

include 'includes/db_connect.php';
/** @var mysqli $conn */

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listing_dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$listing_id = intval($_POST['listing_id'] ?? 0);

// Validate listing_id
if ($listing_id <= 0) {
    $_SESSION['error_msg'] = 'Invalid listing ID.';
    header('Location: listing_dashboard.php');
    exit();
}

// Verify listing belongs to current user
$check_stmt = mysqli_prepare($conn, "SELECT listing_id, image_path FROM listing WHERE listing_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check_stmt, "ii", $listing_id, $user_id);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);
$existing_listing = mysqli_fetch_assoc($result);
mysqli_stmt_close($check_stmt);

if (!$existing_listing) {
    $_SESSION['error_msg'] = 'Listing not found or access denied.';
    header('Location: listing_dashboard.php');
    exit();
}

$listing_name   = trim($_POST['listing_name'] ?? '');
$listing_type   = $_POST['listing_type'] ?? 'service';
$category       = $_POST['category'] ?? '';
$service_type   = $_POST['service_type'] ?? '';
$product_type   = $_POST['product_type'] ?? '';
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

$errors = [];
if (strlen($listing_name) < 3)  $errors[] = "Listing name too short (min 3 characters).";
if (strlen($description) < 10)  $errors[] = "Description too short (min 10 characters).";
if (empty($category))           $errors[] = "Please select a category.";
if (empty($extension))          $errors[] = "Please select a primary extension.";
if (empty($price_desc))         $errors[] = "Please enter a price description.";
if (empty($listing_type))       $errors[] = "Please select what you are offering.";
if (empty($delivery_mode))      $errors[] = "Please select a delivery/service mode.";

// FIXED: Only require service_type for Service or Both
if (($listing_type === 'service' || $listing_type === 'both') && empty($service_type)) {
    $errors[] = "Please select a service type.";
}

// FIXED: Only require product_type for Goods or Both
if (($listing_type === 'product' || $listing_type === 'both') && empty($product_type)) {
    $errors[] = "Please enter a product type.";
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
    header("Location: edit_listing.php?id=" . $listing_id);
    exit();
}


if (!empty($_POST['photos_to_delete'])) {
    $photos_to_delete = array_filter(array_map('intval', explode(',', $_POST['photos_to_delete'])));

    foreach ($photos_to_delete as $image_id) {
        // Verify image belongs to this listing before deleting
        $verify_stmt = mysqli_prepare($conn, "SELECT image_path FROM listing_images WHERE image_id = ? AND listing_id = ?");
        mysqli_stmt_bind_param($verify_stmt, "ii", $image_id, $listing_id);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);
        $img_data = mysqli_fetch_assoc($verify_result);
        mysqli_stmt_close($verify_stmt);

        if ($img_data) {
            // Delete file from filesystem
            if (!empty($img_data['image_path']) && file_exists($img_data['image_path']) && $img_data['image_path'] !== 'uploads/listings/default_listing.jpg') {
                unlink($img_data['image_path']);
            }
            // Delete from database
            $del_img_stmt = mysqli_prepare($conn, "DELETE FROM listing_images WHERE image_id = ? AND listing_id = ?");
            mysqli_stmt_bind_param($del_img_stmt, "ii", $image_id, $listing_id);
            mysqli_stmt_execute($del_img_stmt);
            mysqli_stmt_close($del_img_stmt);
        }
    }

    // Update main image_path if the deleted photo was the primary one
    $check_main = mysqli_prepare($conn, "SELECT image_path FROM listing WHERE listing_id = ?");
    mysqli_stmt_bind_param($check_main, "i", $listing_id);
    mysqli_stmt_execute($check_main);
    $main_result = mysqli_stmt_get_result($check_main);
    $main_data = mysqli_fetch_assoc($main_result);
    mysqli_stmt_close($check_main);

    if (!empty($main_data['image_path'])) {
        $main_path = $main_data['image_path'];
        $check_exists = mysqli_prepare($conn, "SELECT image_id FROM listing_images WHERE image_path = ? AND listing_id = ?");
        mysqli_stmt_bind_param($check_exists, "si", $main_path, $listing_id);
        mysqli_stmt_execute($check_exists);
        $exists_result = mysqli_stmt_get_result($check_exists);
        $still_exists = mysqli_fetch_assoc($exists_result);
        mysqli_stmt_close($check_exists);

        if (!$still_exists) {
            // Main image was deleted, pick a new one
            $new_img_stmt = mysqli_prepare($conn, "SELECT image_path FROM listing_images WHERE listing_id = ? ORDER BY uploaded_at ASC LIMIT 1");
            mysqli_stmt_bind_param($new_img_stmt, "i", $listing_id);
            mysqli_stmt_execute($new_img_stmt);
            $new_result = mysqli_stmt_get_result($new_img_stmt);
            $new_img = mysqli_fetch_assoc($new_result);
            mysqli_stmt_close($new_img_stmt);

            $new_path = $new_img['image_path'] ?? 'uploads/listings/default_listing.jpg';
            $upd_main = mysqli_prepare($conn, "UPDATE listing SET image_path = ? WHERE listing_id = ?");
            mysqli_stmt_bind_param($upd_main, "si", $new_path, $listing_id);
            mysqli_stmt_execute($upd_main);
            mysqli_stmt_close($upd_main);
        }
    }
}


$uploaded = [];
$max_total = 5;
$max_size = 2 * 1024 * 1024;
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];

// Count existing images
$cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM listing_images WHERE listing_id = ?");
mysqli_stmt_bind_param($cnt_stmt, "i", $listing_id);
mysqli_stmt_execute($cnt_stmt);
$cnt_result = mysqli_stmt_get_result($cnt_stmt);
$cnt_row = mysqli_fetch_assoc($cnt_result);
$existing_count = $cnt_row['cnt'] ?? 0;
mysqli_stmt_close($cnt_stmt);

$remaining = $max_total - $existing_count;

if (!empty($_FILES['work_photos']) && is_array($_FILES['work_photos']['name']) && $remaining > 0) {
    for ($i = 0; $i < count($_FILES['work_photos']['name']) && count($uploaded) < $remaining; $i++) {
        if (empty($_FILES['work_photos']['tmp_name'][$i])) continue;

        $tmp  = $_FILES['work_photos']['tmp_name'][$i];
        $name = $_FILES['work_photos']['name'][$i];
        $size = $_FILES['work_photos']['size'][$i];
        $type = $_FILES['work_photos']['type'][$i];

        if ($size > $max_size) continue;
        if (!in_array($type, $allowed_types)) continue;
        if (getimagesize($tmp) === false) continue;

        $dir = "uploads/listings/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $safe_name = preg_replace('/[^a-zA-Z0-9.-]/', '_', $name);
        $target = $dir . time() . "_" . $i . "_" . $safe_name;

        if (move_uploaded_file($tmp, $target)) {
            $uploaded[] = $target;
        }
    }
}

// Insert new images into listing_images table
if (!empty($uploaded)) {
    $stmt = mysqli_prepare($conn, "INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)");
    foreach ($uploaded as $path) {
        mysqli_stmt_bind_param($stmt, "is", $listing_id, $path);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);

    // Update main image_path if none exists
    if (empty($existing_listing['image_path']) || $existing_listing['image_path'] == 'uploads/listings/default_listing.jpg') {
        $upd_img = mysqli_prepare($conn, "UPDATE listing SET image_path = ? WHERE listing_id = ?");
        mysqli_stmt_bind_param($upd_img, "si", $uploaded[0], $listing_id);
        mysqli_stmt_execute($upd_img);
        mysqli_stmt_close($upd_img);
    }
}


$update_stmt = mysqli_prepare($conn, "UPDATE listing SET 
    listing_name = ?, 
    listing_type = ?,
    category = ?, 
    service_type = ?, 
    product_type = ?,
    extension = ?, 
    service_extensions = ?,
    delivery_mode = ?,
    street_address = ?, 
    price_description = ?, 
    payment_options = ?,
    description = ? 
    WHERE listing_id = ? AND user_id = ?");

mysqli_stmt_bind_param($update_stmt, "sssssssssssssi", 
    $listing_name, 
    $listing_type,
    $category, 
    $service_type,
    $product_type,
    $extension, 
    $service_extensions_str,
    $delivery_mode,
    $street_address, 
    $price_desc, 
    $payment_options_str,
    $description, 
    $listing_id, 
    $user_id
);

if (mysqli_stmt_execute($update_stmt)) {
    $photo_msg = count($uploaded) > 0 ? " " . count($uploaded) . " new photo(s) added." : "";
    $_SESSION['update_success_msg'] = 'Listing updated successfully!' . $photo_msg;
    header('Location: listing_details_owner.php?id=' . $listing_id);
} else {
    $_SESSION['update_error_msg'] = 'Failed to update listing. Please try again.';
    header('Location: edit_listing.php?id=' . $listing_id);
}

mysqli_stmt_close($update_stmt);
exit();