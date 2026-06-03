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
$check_stmt = mysqli_prepare($conn, "SELECT listing_id FROM listing WHERE listing_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check_stmt, "ii", $listing_id, $user_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) === 0) {
    mysqli_stmt_close($check_stmt);
    $_SESSION['error_msg'] = 'Listing not found or access denied.';
    header('Location: listing_dashboard.php');
    exit();
}
mysqli_stmt_close($check_stmt);

// Get and sanitize form data
$listing_name = trim($_POST['listing_name'] ?? '');
$category = $_POST['category'] ?? '';
$service_type = $_POST['service_type'] ?? '';
$extension = $_POST['extension'] ?? '';
$service_mode = $_POST['service_mode'] ?? '';
$street_address = trim($_POST['street_address'] ?? '');
$price_description = trim($_POST['price_description'] ?? '');
$description = trim($_POST['description'] ?? '');

// NEW: Handle additional extensions
$service_extensions = [];
if (isset($_POST['service_extensions']) && is_array($_POST['service_extensions'])) {
    $service_extensions = array_filter($_POST['service_extensions']);
}
$service_extensions_str = !empty($service_extensions) ? implode(',', $service_extensions) : null;

// Validation
$errors = [];

if (empty($listing_name) || strlen($listing_name) < 3) {
    $errors[] = 'Business name must be at least 3 characters.';
}

if (empty($category)) {
    $errors[] = 'Please select a category.';
}

if (empty($service_type)) {
    $errors[] = 'Please select a service type.';
}

if (empty($extension)) {
    $errors[] = 'Please select a primary extension.';
}

if (empty($service_mode)) {
    $errors[] = 'Please select a service delivery mode.';
}

if ($service_mode === 'physical-site' && empty($street_address)) {
    $errors[] = 'Street address is required for physical site services.';
}

if (empty($price_description)) {
    $errors[] = 'Please enter a price description.';
}

if (empty($description)) {
    $errors[] = 'Please enter a business description.';
}

// If validation errors, redirect back to edit form
if (!empty($errors)) {
    $_SESSION['error_msg'] = implode(' ', $errors);
    header('Location: edit_listing.php?id=' . $listing_id);
    exit();
}

// Update listing in database
$update_stmt = mysqli_prepare($conn, "UPDATE listing SET 
    listing_name = ?, 
    category = ?, 
    service_type = ?, 
    extension = ?, 
    service_extensions = ?,
    service_mode = ?, 
    street_address = ?, 
    price_description = ?, 
    description = ? 
    WHERE listing_id = ? AND user_id = ?");

mysqli_stmt_bind_param($update_stmt, "sssssssssss", 
    $listing_name, 
    $category, 
    $service_type, 
    $extension, 
    $service_extensions_str,
    $service_mode, 
    $street_address, 
    $price_description, 
    $description, 
    $listing_id, 
    $user_id
);

if (mysqli_stmt_execute($update_stmt)) {
    $_SESSION['success_msg'] = 'Listing updated successfully!';
    header('Location: listing_details_owner.php?id=' . $listing_id);
} else {
    $_SESSION['error_msg'] = 'Failed to update listing. Please try again.';
    header('Location: edit_listing.php?id=' . $listing_id);
}

mysqli_stmt_close($update_stmt);
exit();