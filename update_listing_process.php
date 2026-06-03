<?php
session_start();

// Check if database file exists
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
    /** @var mysqli $conn */
} else {
    $conn = null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $listing_id = $_POST['listing_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    
    $business_name = trim($_POST['business_name'] ?? '');
    $category = $_POST['category'] ?? '';
    $service_type = $_POST['service_type'] ?? '';
    $extension = $_POST['extension'] ?? '';
    $service_mode = $_POST['service_mode'] ?? 'door-to-door';
    $street_address = trim($_POST['street_address'] ?? '');
    $price_desc = trim($_POST['price_description'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $errors = [];

    if (empty($business_name) || strlen($business_name) < 3) {
        $errors[] = "Business name is too short or empty.";
    }
    
    if (empty($description) || strlen($description) < 10) {
        $errors[] = "Please provide a more detailed description (min 10 characters).";
    }

    // Handle image upload if provided
    $image_updated = false;
    $new_image_path = null;
    
    if (!empty($_FILES['service_image']['tmp_name'])) {
        $target_dir = "uploads/services/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = basename($_FILES["service_image"]["name"]);
        $target_file = $target_dir . time() . "_" . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES["service_image"]["tmp_name"]);
        if ($check === false) {
            $errors[] = "File is not a valid image.";
        }

        if ($_FILES["service_image"]["size"] > 2000000) {
            $errors[] = "Sorry, your image file is too large (Max 2MB).";
        }

        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
            $errors[] = "Only JPG, JPEG, & PNG files are allowed.";
        }
        
        if (empty($errors) && move_uploaded_file($_FILES["service_image"]["tmp_name"], $target_file)) {
            $new_image_path = $target_file;
            $image_updated = true;
        }
    }

    if (count($errors) > 0) {
        $_SESSION['error_msg'] = implode("<br>", $errors);
        header("Location: edit_listing.php?id=" . $listing_id);
        exit();
    }

    // Update database if connection exists
    if ($conn) {
        if ($image_updated) {
            $stmt = mysqli_prepare($conn, "UPDATE Listing SET business_name=?, category=?, service_type=?, extension=?, service_mode=?, street_address=?, price_description=?, description=?, image_path=? WHERE listing_id=? AND user_id=?");
            mysqli_stmt_bind_param($stmt, "sssssssssii", $business_name, $category, $service_type, $extension, $service_mode, $street_address, $price_desc, $description, $new_image_path, $listing_id, $user_id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE Listing SET business_name=?, category=?, service_type=?, extension=?, service_mode=?, street_address=?, price_description=?, description=? WHERE listing_id=? AND user_id=?");
            mysqli_stmt_bind_param($stmt, "ssssssssii", $business_name, $category, $service_type, $extension, $service_mode, $street_address, $price_desc, $description, $listing_id, $user_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_msg'] = "Listing updated successfully!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['success_msg'] = "Listing updated (database not connected yet)!";
    }
    
    header("Location: edit_listing.php?id=" . $listing_id);
    exit();
}
?>
























