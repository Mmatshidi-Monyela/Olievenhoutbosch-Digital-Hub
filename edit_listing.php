<?php
session_start();
include 'includes/db_connect.php';
/** @var mysqli $conn */

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'Customer';

// Only Provider or Both can edit listings
if (!in_array($user_role, ['Provider', 'Both'])) {
    header("Location: main.php");
    exit();
}

$listing_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$listing_id) {
    header("Location: listing_dashboard.php");
    exit();
}

// Fetch listing data
$stmt = $conn->prepare("SELECT * FROM listing WHERE listing_id = ? AND user_id = ?");
$stmt->bind_param("ii", $listing_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();
$stmt->close();

if (!$listing) {
    header("Location: listing_dashboard.php");
    exit();
}

// Parse existing data
$existing_extensions = [];
if (!empty($listing['service_extensions'])) {
    $existing_extensions = array_map('trim', explode(',', $listing['service_extensions']));
}
$existing_payment = [];
if (!empty($listing['payment_options'])) {
    $existing_payment = array_map('trim', explode(',', $listing['payment_options']));
}

// Hardcoded categories
$categories = [
    'Construction & Maintenance',
    'Transport',
    'Home & Rentals',
    'Food & Essentials',
    'Personal Care'
];

// Hardcoded extensions — MATCHING ADD LISTING
$extensions = [
    '4', '13', '15','19', '20',
    '21', '22', '23', '24', '25', '26', '36'
];

$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['success_msg']);
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['error_msg']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Listing - Olievenhoutbosch Digital Hub</title>
    <link rel="icon" type="image/png" href="images/logo.png"> 
    <link rel="apple-touch-icon" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #c99383;
            --blush: #d8b2a7;
            --copper: #ba745f;
            --light-grey: #e6e6e6;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background-color: var(--light-grey);
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .form-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .form-card {
            background: white;
            border-radius: 15px;
            padding: 35px 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .page-title {
            text-align: center;
            color: var(--plum);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 30px;
        }

        /* Alert messages */
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .alert-success {
            background: #e6ffed;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #ffe5e5;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Section headings - simple, no underline */
        .section-heading {
            color: var(--plum);
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 15px;
            margin-top: 25px;
        }

        .section-heading:first-of-type {
            margin-top: 0;
        }

        /* Form labels */
        .form-label-custom {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        /* Input styling */
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 0.9rem;
            background: white;
            transition: border-color 0.2s;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--rose-gold);
        }

        .form-input::placeholder {
            color: #aaa;
        }

        select.form-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }

        /* Type selector cards */
        .type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .type-card {
            flex: 1;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s;
            background: white;
        }

        .type-card:hover {
            border-color: var(--blush);
        }

        .type-card.selected {
            border-color: var(--plum);
            background: #f8f4fc;
        }

        .type-card input {
            display: none;
        }

        .type-card strong {
            display: block;
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 3px;
        }

        .type-card span {
            font-size: 0.75rem;
            color: #888;
        }

        /* Delivery mode cards */
        .delivery-selector {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .delivery-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.25s;
            background: white;
        }

        .delivery-card:hover {
            border-color: var(--blush);
        }

        .delivery-card.selected {
            border-color: var(--plum);
            background: #f8f4fc;
        }

        .delivery-card.hidden {
            display: none;
        }

        .delivery-card input {
            display: none;
        }

        .delivery-card strong {
            display: block;
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 3px;
        }

        .delivery-card span {
            font-size: 0.8rem;
            color: #888;
        }

        /* Extension checkboxes */
        .ext-checkboxes {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 10px;
        }

        .ext-checkbox-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
        }

        .ext-checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--rose-gold);
            cursor: pointer;
        }

        .ext-placeholder {
            color: #999;
            font-size: 0.85rem;
            font-style: italic;
        }

        /* Payment checkboxes */
        .payment-options {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }

        .payment-option input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--rose-gold);
            cursor: pointer;
        }

        /* EFT Notice */
        .eft-notice {
            display: none;
            margin-top: 12px;
            padding: 12px 15px;
            background: #fff9f8;
            border-left: 3px solid var(--rose-gold);
            border-radius: 0 8px 8px 0;
            font-size: 0.8rem;
            color: #666;
        }

        /* Address section */
        #addressSection {
            display: none;
            margin-top: 12px;
            padding: 15px;
            background: #fff9f8;
            border-left: 3px solid var(--rose-gold);
            border-radius: 0 8px 8px 0;
        }

        .hint-text {
            font-size: 0.75rem;
            color: #888;
            margin-top: 5px;
        }

        /* Photo upload */
        .photo-upload-area {
            border: 2px dashed var(--rose-gold);
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }

        .photo-upload-area:hover {
            background: #fdfaf9;
            border-color: var(--copper);
        }

        .photo-upload-area .upload-title {
            font-weight: 600;
            color: var(--plum);
            margin-bottom: 5px;
        }

        .photo-upload-area .upload-hint {
            font-size: 0.8rem;
            color: #888;
        }

        .photo-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .photo-preview {
            position: relative;
            width: 80px;
            height: 80px;
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .photo-preview .remove-btn {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 20px;
            height: 20px;
            background: #d9534f;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Existing photos */
        .existing-photos {
            margin-bottom: 15px;
        }

        .existing-photos-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 10px;
        }

        .existing-photo {
            position: relative;
            display: inline-block;
            width: 80px;
            height: 80px;
            margin: 4px;
        }

        .existing-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .existing-photo .remove-btn {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 20px;
            height: 20px;
            background: #d9534f;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Buttons */
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn-primary-custom {
            flex: 1;
            padding: 14px;
            background: var(--rose-gold);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-primary-custom:hover {
            background: var(--copper);
        }

        .btn-secondary-custom {
            flex: 1;
            padding: 14px;
            background: white;
            color: var(--plum);
            border: 2px solid var(--rose-gold);
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
        }

        .btn-secondary-custom:hover {
            background: var(--rose-gold);
            color: white;
        }

        /* Conditional sections */
        .conditional-section {
            margin-bottom: 15px;
        }

        .mb-3 {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="form-card">
        <h1 class="page-title">Update Listing</h1>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form action="update_listing.php" method="POST" enctype="multipart/form-data" id="listingForm">
            <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">

            <!-- Basic Information -->
            <h2 class="section-heading">Basic Information</h2>

            <div class="mb-3">
                <label class="form-label-custom">Listing Name</label>
                <input type="text" name="listing_name" class="form-input" value="<?php echo htmlspecialchars($listing['listing_name'] ?? ''); ?>" placeholder="What are you offering?" required>
            </div>

            <div class="mb-3">
                <label class="form-label-custom">What are you offering?</label>
                <div class="type-selector">
                    <div class="type-card <?php echo ($listing['listing_type'] ?? 'service') === 'service' ? 'selected' : ''; ?>" onclick="selectType(this, 'service')">
                        <input type="radio" name="listing_type" value="service" <?php echo ($listing['listing_type'] ?? 'service') === 'service' ? 'checked' : ''; ?> onchange="updateFormFields()">
                        <strong>Service</strong>
                        <span>I do work</span>
                    </div>
                    <div class="type-card <?php echo ($listing['listing_type'] ?? '') === 'product' ? 'selected' : ''; ?>" onclick="selectType(this, 'product')">
                        <input type="radio" name="listing_type" value="product" <?php echo ($listing['listing_type'] ?? '') === 'product' ? 'checked' : ''; ?> onchange="updateFormFields()">
                        <strong>Goods</strong>
                        <span>I sell items</span>
                    </div>
                    <div class="type-card <?php echo ($listing['listing_type'] ?? '') === 'both' ? 'selected' : ''; ?>" onclick="selectType(this, 'both')">
                        <input type="radio" name="listing_type" value="both" <?php echo ($listing['listing_type'] ?? '') === 'both' ? 'checked' : ''; ?> onchange="updateFormFields()">
                        <strong>Both</strong>
                        <span>I do both</span>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label-custom">Main Category</label>
                <select name="category" id="categorySelect" class="form-input" onchange="updateServiceTypes()" required>
                    <option value="">Select Category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($listing['category'] ?? '') === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Service Type (shown for Service or Both) -->
            <div class="conditional-section" id="serviceTypeSection">
                <label class="form-label-custom">Service Type</label>
                <select name="service_type" id="serviceType" class="form-input">
                    <option value="">Select Category first...</option>
                </select>
            </div>

            <!-- Product Type (shown for Goods or Both) -->
            <div class="conditional-section" id="productTypeSection" style="display: none;">
                <label class="form-label-custom">Product Type</label>
                <input type="text" name="product_type" id="productType" class="form-input" value="<?php echo htmlspecialchars($listing['product_type'] ?? ''); ?>" placeholder="e.g., Baked Goods, Handmade Crafts">
            </div>

            <!-- Location -->
            <h2 class="section-heading">Location</h2>

            <div class="mb-3">
                <label class="form-label-custom">Primary Extension</label>
                <select name="extension" class="form-input" id="primaryExt" onchange="updateAdditionalExtOptions()" required>
                    <option value="">Select Extension...</option>
                    <?php foreach ($extensions as $ext): ?>
                        <option value="<?php echo htmlspecialchars($ext); ?>" <?php echo ($listing['extension'] ?? '') === $ext ? 'selected' : ''; ?>>Ext <?php echo htmlspecialchars($ext); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label-custom">Additional Extensions (optional)</label>
                <div id="additionalExtContainer">
                    <span class="ext-placeholder">Select a primary extension first...</span>
                </div>
            </div>

            <!-- How Customers Receive -->
            <h2 class="section-heading" id="deliveryHeading">How Customers Receive Your Service</h2>

            <div class="delivery-selector" id="deliverySelector">
                <div class="delivery-card <?php echo ($listing['delivery_mode'] ?? 'door_to_door') === 'door_to_door' ? 'selected' : ''; ?>" data-type="service" onclick="selectDelivery(this, 'door_to_door')">
                    <input type="radio" name="delivery_mode" value="door_to_door" <?php echo ($listing['delivery_mode'] ?? 'door_to_door') === 'door_to_door' ? 'checked' : ''; ?> onchange="toggleAddress()">
                    <strong>Door-to-Door</strong>
                    <span>I come to the customer</span>
                </div>
                <div class="delivery-card <?php echo ($listing['delivery_mode'] ?? '') === 'customer_comes_to_me' ? 'selected' : ''; ?>" data-type="service" onclick="selectDelivery(this, 'customer_comes_to_me')">
                    <input type="radio" name="delivery_mode" value="customer_comes_to_me" <?php echo ($listing['delivery_mode'] ?? '') === 'customer_comes_to_me' ? 'checked' : ''; ?> onchange="toggleAddress()">
                    <strong>Customer Comes to Me</strong>
                    <span>They visit my location</span>
                </div>
                <div class="delivery-card <?php echo ($listing['delivery_mode'] ?? '') === 'i_deliver' ? 'selected' : ''; ?>" data-type="product" onclick="selectDelivery(this, 'i_deliver')">
                    <input type="radio" name="delivery_mode" value="i_deliver" <?php echo ($listing['delivery_mode'] ?? '') === 'i_deliver' ? 'checked' : ''; ?> onchange="toggleAddress()">
                    <strong>I Deliver</strong>
                    <span>I bring the item to the customer</span>
                </div>
                <div class="delivery-card <?php echo ($listing['delivery_mode'] ?? '') === 'customer_pickup' ? 'selected' : ''; ?>" data-type="product" onclick="selectDelivery(this, 'customer_pickup')">
                    <input type="radio" name="delivery_mode" value="customer_pickup" <?php echo ($listing['delivery_mode'] ?? '') === 'customer_pickup' ? 'checked' : ''; ?> onchange="toggleAddress()">
                    <strong>Customer Pickup</strong>
                    <span>They collect from my location</span>
                </div>
                <div class="delivery-card <?php echo ($listing['delivery_mode'] ?? '') === 'both_service' ? 'selected' : ''; ?>" data-type="both" onclick="selectDelivery(this, 'both_service')">
                    <input type="radio" name="delivery_mode" value="both_service" <?php echo ($listing['delivery_mode'] ?? '') === 'both_service' ? 'checked' : ''; ?> onchange="toggleAddress()">
                    <strong>Both</strong>
                    <span>Door-to-door AND on-site</span>
                </div>
            </div>

            <div id="addressSection">
                <label class="form-label-custom">Street Address / House Number</label>
                <input type="text" name="street_address" id="streetAddressInput" class="form-input" value="<?php echo htmlspecialchars($listing['street_address'] ?? ''); ?>" placeholder="Required when customers come to you or pick up">
                <div class="hint-text">Required when customers come to you or pick up</div>
            </div>

            <!-- Pricing & Payment -->
            <h2 class="section-heading">Pricing & Payment</h2>

            <div class="mb-3">
                <label class="form-label-custom">Price Description</label>
                <input type="text" name="price_description" class="form-input" value="<?php echo htmlspecialchars($listing['price_description'] ?? ''); ?>" placeholder="e.g. Starts from R150 / R50 per item" required>
            </div>

            <div class="mb-3">
                <label class="form-label-custom">Payment Options (select all that apply)</label>
                <div class="payment-options">
                    <label class="payment-option">
                        <input type="checkbox" name="payment_options[]" value="Cash" <?php echo in_array('Cash', $existing_payment) ? 'checked' : ''; ?>>
                        Cash
                    </label>
                    <label class="payment-option">
                        <input type="checkbox" name="payment_options[]" value="EFT" id="payEFT" onchange="toggleEFTNotice()" <?php echo in_array('EFT', $existing_payment) ? 'checked' : ''; ?>>
                        EFT (Bank Transfer)
                    </label>
                    <label class="payment-option">
                        <input type="checkbox" name="payment_options[]" value="Card" <?php echo in_array('Card', $existing_payment) ? 'checked' : ''; ?>>
                        Card (in person)
                    </label>
                </div>
                <div class="eft-notice" id="eftNotice" style="<?php echo in_array('EFT', $existing_payment) ? 'display: block;' : ''; ?>">
                    For your data privacy, EFT details should be shared with inquiring customers via messaging.
                </div>
            </div>

            <!-- Description -->
            <h2 class="section-heading">Description</h2>

            <div class="mb-3">
                <label class="form-label-custom">Listing Description</label>
                <textarea name="description" class="form-input" rows="4" placeholder="Describe what you offer, your experience, availability..." required><?php echo htmlspecialchars($listing['description'] ?? ''); ?></textarea>
            </div>

            <!-- Photos -->
            <h2 class="section-heading">Photos</h2>

            <?php 
            // Fetch gallery images for this listing
            $gallery_images = [];
            $gal_stmt = $conn->prepare("SELECT image_id, image_path FROM listing_images WHERE listing_id = ? ORDER BY uploaded_at ASC");
            $gal_stmt->bind_param("i", $listing_id);
            $gal_stmt->execute();
            $gal_result = $gal_stmt->get_result();
            while ($g = $gal_result->fetch_assoc()) {
                $gallery_images[] = $g;
            }
            $gal_stmt->close();

            if (!empty($gallery_images)): 
            ?>
            <div class="existing-photos">
                <div class="existing-photos-label">Current Photos</div>
                <div>
                    <?php foreach ($gallery_images as $img): ?>
                    <div class="existing-photo" id="existing_<?php echo $img['image_id']; ?>">
                        <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Work photo">
                        <button type="button" class="remove-btn" onclick="markPhotoForDeletion(<?php echo $img['image_id']; ?>)">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <input type="hidden" name="photos_to_delete" id="photosToDelete" value="">
            <?php endif; ?>

            <div class="photo-upload-area" onclick="document.getElementById('photoInput').click()">
                <div class="upload-title">Click to add more photos</div>
                <div class="upload-hint">Max 5 images total, 2MB each, JPG/PNG</div>
                <div id="photoPreview" class="photo-preview-container"></div>
            </div>
            <input type="file" name="work_photos[]" id="photoInput" multiple accept="image/jpeg,image/png" style="display: none;" onchange="previewPhotos(this)">

            <div class="btn-group">
                <button type="submit" id="submitBtn" class="btn-primary-custom">Update Listing</button>
                <a href="listing_details_owner.php?id=<?php echo $listing_id; ?>" id="cancelBtn" class="btn-secondary-custom">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const allExtensions = <?php echo json_encode($extensions); ?>;
const existingExtensions = <?php echo json_encode($existing_extensions); ?>;

// Service types mapped by category (matching your screenshots)
const serviceTypesByCategory = {
    'Construction & Maintenance': ['Painting', 'Plumbing', 'Tiling', 'Window Glazing'],
    'Transport': ['Bakkie-for-hire', 'School Transport', 'Work Transport'],
    'Home & Rentals': ['Appliance Repairs', 'Backroom Rentals', 'Gardening', 'Window Cleaning'],
    'Food & Essentials': ['Baking', 'Cooked & Prepared Meals', 'Fresh Produce', 'Gas Refill'],
    'Personal Care': ['Hair', 'Make-up', 'Nails', 'Spa', 'Tailor']
};

function selectType(element, type) {
    document.querySelectorAll('.type-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    updateFormFields();
}

function updateServiceTypes() {
    const category = document.getElementById('categorySelect').value;
    const serviceSelect = document.getElementById('serviceType');
    const currentServiceType = <?php echo json_encode(trim($listing['service_type'] ?? '')); ?>;

    serviceSelect.innerHTML = '<option value="">Select Type...</option>';

    let hasSelected = false;

    if (category && serviceTypesByCategory[category]) {
        serviceTypesByCategory[category].forEach(type => {
            const option = document.createElement('option');
            option.value = type;
            option.textContent = type;
            if (currentServiceType && type.trim() === currentServiceType) {
                option.selected = true;
                hasSelected = true;
            }
            serviceSelect.appendChild(option);
        });
    }

    // Fallback: if saved value exists but wasn't matched, preserve it
    if (currentServiceType && !hasSelected) {
        const option = document.createElement('option');
        option.value = currentServiceType;
        option.textContent = currentServiceType;
        option.selected = true;
        serviceSelect.insertBefore(option, serviceSelect.options[1]);
    }
}

function updateFormFields() {
    const type = document.querySelector('input[name="listing_type"]:checked')?.value;
    const serviceSection = document.getElementById('serviceTypeSection');
    const productSection = document.getElementById('productTypeSection');
    const serviceInput = document.getElementById('serviceType');
    const productInput = document.getElementById('productType');
    const deliveryHeading = document.getElementById('deliveryHeading');
    const deliveryCards = document.querySelectorAll('.delivery-card');

    // Show/hide service/product sections
    if (type === 'service') {
        serviceSection.style.display = 'block';
        productSection.style.display = 'none';
        serviceInput.required = true;
        productInput.required = false;
        deliveryHeading.textContent = 'How Customers Receive Your Service';
    } else if (type === 'product') {
        serviceSection.style.display = 'none';
        productSection.style.display = 'block';
        serviceInput.required = false;
        productInput.required = true;
        deliveryHeading.textContent = 'How Customers Get Their Items';
    } else if (type === 'both') {
        serviceSection.style.display = 'block';
        productSection.style.display = 'block';
        serviceInput.required = true;
        productInput.required = true;
        deliveryHeading.textContent = 'How Customers Receive Your Service';
    }

    // Re-populate service types whenever type changes to ensure saved value is preserved
    updateServiceTypes();

    // Filter delivery options
    let firstVisible = null;
    let currentSelected = null;

    deliveryCards.forEach(card => {
        const cardType = card.getAttribute('data-type');
        let show = false;

        if (type === 'service') {
            show = (cardType === 'service' || cardType === 'both');
        } else if (type === 'product') {
            show = (cardType === 'product' || cardType === 'both');
        } else if (type === 'both') {
            show = true;
        }

        if (show) {
            card.classList.remove('hidden');
            if (!firstVisible) firstVisible = card;
            if (card.classList.contains('selected')) currentSelected = card;
        } else {
            card.classList.remove('selected');
            card.classList.add('hidden');
            card.querySelector('input').checked = false;
        }
    });

    // If current selection is now hidden, select first visible
    if (!currentSelected && firstVisible) {
        selectDelivery(firstVisible, firstVisible.querySelector('input').value);
    }

    toggleAddress();
}

function selectDelivery(element, mode) {
    document.querySelectorAll('.delivery-card:not(.hidden)').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    toggleAddress();
}

function toggleAddress() {
    const selectedCard = document.querySelector('.delivery-card.selected');
    if (!selectedCard) return;

    const deliveryMode = selectedCard.querySelector('input').value;
    const addressSection = document.getElementById("addressSection");
    const addressInput = document.getElementById("streetAddressInput");

    const needsAddress = ['customer_comes_to_me', 'customer_pickup', 'both_service'].includes(deliveryMode);

    if (needsAddress) {
        addressSection.style.display = "block";
        addressInput.required = true;
    } else {
        addressSection.style.display = "none";
        addressInput.required = false;
        addressInput.value = "";
    }
}

function toggleEFTNotice() {
    const notice = document.getElementById('eftNotice');
    const eftChecked = document.getElementById('payEFT').checked;
    notice.style.display = eftChecked ? 'block' : 'none';
}

function updateAdditionalExtOptions() {
    const primary = document.getElementById("primaryExt").value;
    const container = document.getElementById("additionalExtContainer");

    if (!primary) {
        container.innerHTML = '<span class="ext-placeholder">Select a primary extension first...</span>';
        return;
    }

    container.innerHTML = '<div class="ext-checkboxes">' +
        allExtensions
            .filter(ext => ext !== primary)
            .map(ext => {
                const isChecked = existingExtensions.includes(ext) ? 'checked' : '';
                return `
                    <label class="ext-checkbox-item">
                        <input type="checkbox" name="service_extensions[]" value="${ext}" ${isChecked}>
                        Ext ${ext}
                    </label>
                `;
            }).join('') +
        '</div>';
}

function markPhotoForDeletion(imageId) {
    const photo = document.getElementById('existing_' + imageId);
    if (photo) {
        photo.style.display = 'none';
    }
    const deleteInput = document.getElementById('photosToDelete');
    const current = deleteInput.value ? deleteInput.value.split(',') : [];
    if (!current.includes(String(imageId))) {
        current.push(imageId);
        deleteInput.value = current.join(',');
    }
}

let selectedFiles = [];
function previewPhotos(input) {
    const newFiles = Array.from(input.files);

    if (selectedFiles.length + newFiles.length > 5) {
        alert("Maximum 5 images total");
        return;
    }

    for (let f of newFiles) {
        if (f.size > 2 * 1024 * 1024) {
            alert(f.name + " is too large (max 2MB)");
            continue;
        }
        selectedFiles.push(f);
    }

    updatePhotoPreview();
    updateInput();
}

function updatePhotoPreview() {
    const preview = document.getElementById('photoPreview');
    preview.innerHTML = '';

    selectedFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'photo-preview';
            div.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="remove-btn" onclick="removePhoto(${index})">&times;</button>
            `;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

function removePhoto(index) {
    selectedFiles.splice(index, 1);
    updatePhotoPreview();
    updateInput();
}

function updateInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('photoInput').files = dt.files;
}

document.getElementById('listingForm').onsubmit = function() {
    const listingType = document.querySelector('input[name="listing_type"]:checked').value;

    if (listingType === 'service' || listingType === 'both') {
        const serviceType = document.getElementById('serviceType').value;
        if (!serviceType) {
            alert('Please select a service type.');
            return false;
        }
    }
    if (listingType === 'product' || listingType === 'both') {
        const productType = document.getElementById('productType').value.trim();
        if (!productType) {
            alert('Please enter a product type.');
            return false;
        }
    }

    updateInput();
    document.getElementById('submitBtn').textContent = "Updating...";
    document.getElementById('submitBtn').disabled = true;
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateServiceTypes();
    updateFormFields();
    updateAdditionalExtOptions();
    toggleAddress();
});
</script>
</body>
</html>