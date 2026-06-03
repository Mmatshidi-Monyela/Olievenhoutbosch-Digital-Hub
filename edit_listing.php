<?php
session_start();

// Check if database file exists
if (file_exists('includes/db_connect.php')) {
    include 'includes/db_connect.php';
    /** @var mysqli $conn */
} else {
    $conn = null;
}

$listing_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// Get listing data
$listing = null;
if ($conn) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM listing WHERE listing_id = ? AND user_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $listing_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $listing = mysqli_fetch_assoc($result);
    }
}

// Redirect if listing not found or not owned by user
if (!$listing) {
    header('Location: listing_dashboard.php');
    exit();
}

// Parse additional extensions into array
$selected_additional_exts = [];
if (!empty($listing['service_extensions'])) {
    $selected_additional_exts = explode(',', $listing['service_extensions']);
}

// Parse payment options into array
$selected_payment_options = [];
if (!empty($listing['payment_options'])) {
    $selected_payment_options = array_map('trim', explode(',', $listing['payment_options']));
}

// Parse delivery modes into array
$selected_delivery_modes = [];
if (!empty($listing['delivery_mode'])) {
    $selected_delivery_modes = array_map('trim', explode(',', $listing['delivery_mode']));
}

// Get success/error messages
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg']);
unset($_SESSION['error_msg']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Listing - Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --plum: #230344;
            --rose-gold: #f8c9c0;
            --copper: #ba745f;
            --light-grey: #f4f7f6;
        }

        body {
            background-color: var(--light-grey);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            padding-top: 50px;
            padding-bottom: 50px;
        }

        .form-card {
            background: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 600px;
        }

        .form-title {
            color: var(--plum);
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }

        .form-label {
            color: #555;
            font-size: 14px;
            margin-bottom: 5px;
            margin-top: 20px;
            font-weight: 600;
        }

        .form-control, .form-select {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            background-color: #fff;
        }

        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 0.2rem rgba(248, 201, 192, 0.5);
            border-color: var(--rose-gold);
        }

        .section-divider {
            border-top: 2px solid var(--rose-gold);
            margin: 25px 0 15px 0;
            opacity: 0.3;
        }

        .section-title {
            color: var(--plum);
            font-weight: bold;
            font-size: 1rem;
            margin-bottom: 15px;
        }

        .btn-save {
            background-color: var(--plum);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: bold;
            margin-top: 25px;
            width: 100%;
        }

        .btn-save:hover {
            background-color: #3a065e;
            color: white;
        }

        .btn-cancel {
            background-color: transparent;
            border: 2px solid #dc3545;
            color: #dc3545;
            border-radius: 8px;
            padding: 12px;
            font-weight: bold;
            margin-top: 10px;
            width: 100%;
        }

        .btn-cancel:hover {
            background-color: #dc3545;
            color: white;
        }

        #addressSection {
            display: none;
            border-left: 3px solid var(--rose-gold);
            padding: 15px;
            margin-top: 10px;
            background: #fff9f8;
            border-radius: 0 8px 8px 0;
        }

        /* Extension checkboxes */
        .ext-checkboxes {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 8px;
        }
        .ext-checkboxes .form-check {
            margin: 0;
            padding-left: 1.8em;
        }
        .ext-checkboxes .form-check-input {
            cursor: pointer;
        }
        .ext-checkboxes .form-check-label {
            font-size: 0.85rem;
            cursor: pointer;
            margin-top: 0;
        }
        .ext-checkboxes .form-check-input:checked {
            background-color: var(--rose-gold);
            border-color: var(--rose-gold);
        }

        /* Payment options */
        .payment-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 8px;
        }
        .payment-checkboxes .form-check {
            margin: 0;
            padding-left: 1.8em;
        }
        .payment-checkboxes .form-check-input:checked {
            background-color: var(--rose-gold);
            border-color: var(--rose-gold);
        }

        /* Delivery options */
        .delivery-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 8px;
        }

        .delivery-option {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .delivery-option:hover {
            border-color: var(--rose-gold);
            background: #fff9f8;
        }

        .delivery-option.selected {
            border-color: var(--rose-gold);
            background: #fff9f8;
        }

        .delivery-option input {
            margin: 0;
            flex-shrink: 0;
        }

        .delivery-option .option-text strong {
            color: var(--plum);
            display: block;
            font-size: 0.9rem;
        }

        .delivery-option .option-text span {
            font-size: 0.8rem;
            color: #888;
        }

        .hint {
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
        }

        .privacy-notice {
            display: none;
            border-left: 3px solid var(--rose-gold);
            padding: 12px 15px;
            margin-top: 10px;
            background: #fff9f8;
            border-radius: 0 8px 8px 0;
            font-size: 0.85rem;
            color: #666;
        }

        /* Photo gallery */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .gallery-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }
        .gallery-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }
        .gallery-delete {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(217, 83, 79, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gallery-add {
            border: 2px dashed var(--rose-gold);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            color: var(--copper);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100px;
        }
        .gallery-add:hover {
            background: #fff9f8;
            border-color: var(--copper);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="form-card">
                <div class="form-title">Edit Listing</div>

                <?php if(!empty($success_msg)): ?>
                    <div class="alert alert-success py-2"><?php echo $success_msg; ?></div>
                <?php endif; ?>

                <?php if(!empty($error_msg)): ?>
                    <div class="alert alert-danger py-2"><?php echo $error_msg; ?></div>
                <?php endif; ?>

                <form action="update_listing.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="listing_id" value="<?php echo $listing['listing_id']; ?>">

                    <!-- BASIC INFO -->
                    <div class="section-title">Basic Information</div>

                    <label class="form-label">Listing Name</label>
                    <input type="text" name="listing_name" class="form-control" value="<?php echo htmlspecialchars($listing['listing_name']); ?>" minlength="3" maxlength="50" required>

                    <label class="form-label">What are you offering?</label>
                    <div class="payment-checkboxes">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="listing_type" id="typeService" value="service" <?php if($listing['listing_type']=='service') echo 'checked'; ?> onchange="updateFormFields()">
                            <label class="form-check-label" for="typeService">Service (I do work)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="listing_type" id="typeProduct" value="product" <?php if($listing['listing_type']=='product') echo 'checked'; ?> onchange="updateFormFields()">
                            <label class="form-check-label" for="typeProduct">Goods (I sell items)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="listing_type" id="typeBoth" value="both" <?php if($listing['listing_type']=='both') echo 'checked'; ?> onchange="updateFormFields()">
                            <label class="form-check-label" for="typeBoth">Both</label>
                        </div>
                    </div>

                    <label class="form-label">Main Category</label>
                    <select name="category" class="form-select" id="mainCategory" onchange="updateServiceTypes()" required>
                        <option value="">Select Category...</option>
                        <option value="Construction & Maintenance" <?php if($listing['category']=='Construction & Maintenance') echo 'selected'; ?>>Construction & Maintenance</option>
                        <option value="Transport" <?php if($listing['category']=='Transport') echo 'selected'; ?>>Transport</option>
                        <option value="Home & Rentals" <?php if($listing['category']=='Home & Rentals') echo 'selected'; ?>>Home & Rentals</option>
                        <option value="Food & Essentials" <?php if($listing['category']=='Food & Essentials') echo 'selected'; ?>>Food & Essentials</option>
                        <option value="Personal Care" <?php if($listing['category']=='Personal Care') echo 'selected'; ?>>Personal Care</option>
                    </select>

                    <!-- ===== OPTION B FIX #1: Separate Service Type & Product Type fields ===== -->
                    <div id="serviceTypeSection">
                        <label class="form-label">Service Type</label>
                        <select name="service_type" class="form-select" id="serviceType">
                            <option value="">Select Service Type...</option>
                        </select>
                    </div>

                    <div id="productTypeSection">
                        <label class="form-label">Product Type</label>
                        <input type="text" name="product_type" class="form-control" id="productType" value="<?php echo htmlspecialchars($listing['product_type'] ?? ''); ?>" placeholder="e.g. Handmade jewelry, Second-hand clothes...">
                    </div>

                    <!-- LOCATION -->
                    <div class="section-divider"></div>
                    <div class="section-title">Location</div>

                    <label class="form-label">Primary Extension</label>
                    <select name="extension" class="form-select" id="primaryExt" onchange="updateAdditionalExtOptions()" required>
                        <option disabled value="">Select Extension...</option>
                        <option value="4" <?php if($listing['extension']=='4') echo 'selected'; ?>>Ext 4</option>
                        <option value="13" <?php if($listing['extension']=='13') echo 'selected'; ?>>Ext 13</option>
                        <option value="15" <?php if($listing['extension']=='15') echo 'selected'; ?>>Ext 15</option>
                        <option value="19" <?php if($listing['extension']=='19') echo 'selected'; ?>>Ext 19</option>
                        <option value="20" <?php if($listing['extension']=='20') echo 'selected'; ?>>Ext 20</option>
                        <option value="21" <?php if($listing['extension']=='21') echo 'selected'; ?>>Ext 21</option>
                        <option value="22" <?php if($listing['extension']=='22') echo 'selected'; ?>>Ext 22</option>
                        <option value="23" <?php if($listing['extension']=='23') echo 'selected'; ?>>Ext 23</option>
                        <option value="24" <?php if($listing['extension']=='24') echo 'selected'; ?>>Ext 24</option>
                        <option value="25" <?php if($listing['extension']=='25') echo 'selected'; ?>>Ext 25</option>
                        <option value="26" <?php if($listing['extension']=='26') echo 'selected'; ?>>Ext 26</option>
                        <option value="36" <?php if($listing['extension']=='36') echo 'selected'; ?>>Ext 36</option>     
                    </select>

                    <label class="form-label">Additional Extensions (optional)</label>
                    <div class="ext-checkboxes" id="additionalExtContainer">
                        <!-- Checkboxes generated by JS -->
                    </div>

                    <!-- DELIVERY / SERVICE MODE -->
                    <div class="section-divider"></div>
                    <div class="section-title" id="deliverySectionTitle">How Customers Receive</div>

                    <div class="delivery-options" id="deliveryOptionsContainer">
                        <!-- Populated by JS -->
                    </div>
                    <input type="hidden" name="delivery_mode" id="deliveryModeInput" value="<?php echo htmlspecialchars($listing['delivery_mode'] ?? 'door_to_door'); ?>">
                    <input type="hidden" name="delivery_modes[]" id="deliveryModesArray" value="<?php echo htmlspecialchars($listing['delivery_mode'] ?? ''); ?>">

                    <div id="addressSection" <?php 
                        $needsAddress = false;
                        foreach ($selected_delivery_modes as $mode) {
                            if (in_array($mode, ['customer_comes_to_me', 'customer_pickup', 'both_service'])) {
                                $needsAddress = true;
                                break;
                            }
                        }
                        if ($needsAddress) echo 'style="display:block;"';
                    ?>>
                        <label class="form-label">Street Address / House Number</label>
                        <input type="text" name="street_address" id="streetAddressInput" class="form-control" value="<?php echo htmlspecialchars($listing['street_address'] ?? ''); ?>" placeholder="e.g. 1234 Peach Street">
                        <div class="hint" id="addressHint">Required when customers come to you or pick up</div>
                    </div>

                    <!-- PRICING & PAYMENT -->
                    <div class="section-divider"></div>
                    <div class="section-title">Pricing & Payment</div>

                    <label class="form-label">Price Description</label>
                    <input type="text" name="price_description" class="form-control" value="<?php echo htmlspecialchars($listing['price_description']); ?>" placeholder="e.g. Starts from R150 / R50 per item" required>

                    <label class="form-label">Payment Options (select all that apply)</label>
                    <div class="payment-checkboxes">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="payment_options[]" id="payCash" value="Cash" <?php if(in_array('Cash', $selected_payment_options)) echo 'checked'; ?>>
                            <label class="form-check-label" for="payCash">Cash</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="payment_options[]" id="payEFT" value="EFT" <?php if(in_array('EFT', $selected_payment_options)) echo 'checked'; ?> onchange="toggleEFTNotice()">
                            <label class="form-check-label" for="payEFT">EFT (Bank Transfer)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="payment_options[]" id="payCard" value="Card" <?php if(in_array('Card', $selected_payment_options)) echo 'checked'; ?>>
                            <label class="form-check-label" for="payCard">Card (in person)</label>
                        </div>
                    </div>

                    <div class="privacy-notice" id="eftNotice" <?php if(in_array('EFT', $selected_payment_options)) echo 'style="display:block;"'; ?>>
                        For your data privacy, EFT details should be shared with inquiring customers via messaging.
                    </div>

                    <!-- DESCRIPTION -->
                    <div class="section-divider"></div>
                    <div class="section-title">Description</div>

                    <label class="form-label">Listing Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe what you offer, your experience, availability..." required><?php echo htmlspecialchars($listing['description']); ?></textarea>

                    <!-- PHOTOS -->
                    <div class="section-divider"></div>
                    <div class="section-title">Photos</div>

                    <?php
                    // Fetch current gallery images
                    $gallery_images = [];
                    if ($conn) {
                        $gal_stmt = mysqli_prepare($conn, "SELECT image_id, image_path FROM listing_images WHERE listing_id = ? ORDER BY uploaded_at ASC");
                        mysqli_stmt_bind_param($gal_stmt, "i", $listing_id);
                        mysqli_stmt_execute($gal_stmt);
                        $gal_result = mysqli_stmt_get_result($gal_stmt);
                        while ($g = mysqli_fetch_assoc($gal_result)) {
                            $gallery_images[] = $g;
                        }
                        mysqli_stmt_close($gal_stmt);
                    }
                    ?>

                    <div class="gallery-grid" id="photoGallery">
                        <?php foreach ($gallery_images as $img): ?>
                        <div class="gallery-item">
                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Work photo">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($gallery_images) < 5): ?>
                    <div class="gallery-add mt-3" onclick="document.getElementById('newPhotosInput').click()">
                        <i class="bi bi-plus-lg" style="font-size: 1.5rem;"></i>
                        <div class="small mt-1">Add Photos</div>
                        <div class="small text-muted"><?php echo count($gallery_images); ?>/5 uploaded</div>
                    </div>
                    <input type="file" name="new_photos[]" id="newPhotosInput" multiple accept="image/jpeg,image/png" style="display: none;" onchange="handleNewPhotos(this)">
                    <div id="newFileList"></div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-save" id="submitBtn">Save Changes</button>
                    <a href="listing_details_owner.php?id=<?php echo $listing['listing_id']; ?>" class="btn btn-cancel">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const services = {
    "Construction & Maintenance": ["Painting", "Plumbing", "Tiling", "Window Glazing"],
    "Transport": ["Bakkie-for-hire", "School Transport", "Work Transport"],
    "Home & Rentals": ["Appliance Repairs", "Backroom Rentals", "Gardening", "Window Cleaning"],
    "Food & Essentials": ["Bakery", "Cooked & Prepared Meals", "Fresh Produce", "Gas Refill"],
    "Personal Care": ["Hair", "Make-up", "Nails", "Spa", "Tailor"],
};

const allExtensions = ["4", "13", "15", "19", "20", "21", "22", "23", "24", "25", "26", "36"];
const selectedAdditionalExts = <?php echo json_encode($selected_additional_exts); ?>;
const selectedDeliveryModes = <?php echo json_encode($selected_delivery_modes); ?>;

const deliveryOptions = {
    service: {
        title: "How Customers Receive Your Service",
        hint: "Required when customers come to you",
        options: [
            { value: 'door_to_door', label: 'Door-to-Door', sublabel: 'I go to the customer\'s location' },
            { value: 'customer_comes_to_me', label: 'Customer Comes to Me', sublabel: 'They visit my shop or home' },
            { value: 'both_service', label: 'Both', sublabel: 'I do door-to-door AND customers can come to me' },
        ]
    },
    product: {
        title: "How Customers Get Their Items",
        hint: "Required when customers pick up from you",
        options: [
            { value: 'i_deliver', label: 'I Deliver', sublabel: 'I bring the item to the customer' },
            { value: 'customer_pickup', label: 'Customer Pickup', sublabel: 'They collect from my location' },
            { value: 'both_product', label: 'Both', sublabel: 'I deliver AND customers can pick up' },
        ]
    },
    both: {
        title: "How Customers Receive",
        hint: "Required when customers come to you or pick up",
        options: [
            { value: 'door_to_door', label: 'Door-to-Door / Delivery', sublabel: 'I go to them or deliver items' },
            { value: 'i_deliver', label: 'I Deliver', sublabel: 'I bring goods to the customer' },
            { value: 'customer_comes_to_me', label: 'Customer Comes to Me', sublabel: 'They visit my shop or home' },
            { value: 'customer_pickup', label: 'Customer Pickup', sublabel: 'They collect from my location' },
        ]
    }
};

let currentListingType = '<?php echo $listing['listing_type'] ?? 'service'; ?>';
let activeDeliveryModes = selectedDeliveryModes.length > 0 ? [...selectedDeliveryModes] : ['door_to_door'];

// ===== OPTION B FIX #2: Show/hide service_type & product_type based on listing_type =====
function updateFormFields() {
    const listingType = document.querySelector('input[name="listing_type"]:checked').value;
    currentListingType = listingType;
    const serviceSection = document.getElementById('serviceTypeSection');
    const productSection = document.getElementById('productTypeSection');
    const serviceSelect = document.getElementById('serviceType');
    const productInput = document.getElementById('productType');

    if (listingType === 'service') {
        serviceSection.style.display = 'block';
        productSection.style.display = 'none';
        serviceSelect.required = true;
        productInput.required = false;
    } else if (listingType === 'product') {
        serviceSection.style.display = 'none';
        productSection.style.display = 'block';
        serviceSelect.required = false;
        productInput.required = true;
    } else if (listingType === 'both') {
        serviceSection.style.display = 'block';
        productSection.style.display = 'block';
        serviceSelect.required = true;
        productInput.required = true;
    }

    updateServiceTypes();
    updateDeliveryOptions();
}

function updateServiceTypes() {
    const cat = document.getElementById("mainCategory").value;
    const sel = document.getElementById("serviceType");
    const currentType = "<?php echo $listing['service_type']; ?>";

    sel.innerHTML = '<option value="">Select Service Type...</option>';
    if (services[cat]) {
        services[cat].forEach(type => {
            let option = new Option(type, type);
            if (type === currentType) option.selected = true;
            sel.add(option);
        });
    }
}

function updateDeliveryOptions() {
    const listingType = document.querySelector('input[name="listing_type"]:checked').value;
    currentListingType = listingType;
    const container = document.getElementById("deliveryOptionsContainer");
    const options = deliveryOptions[listingType] || deliveryOptions.service;

    document.getElementById("deliverySectionTitle").textContent = options.title;
    document.getElementById("addressHint").textContent = options.hint;

    if (listingType === 'both') {
        container.innerHTML = options.map(opt => {
            const isSelected = selectedDeliveryModes.includes(opt.value);
            return `
                <div class="delivery-option ${isSelected ? 'selected' : ''}" onclick="toggleDeliveryMulti(this, '${opt.value}')">
                    <input type="checkbox" name="delivery_mode_multi[]" value="${opt.value}" ${isSelected ? 'checked' : ''} style="display: none;">
                    <div class="option-text">
                        <strong>${opt.label}</strong>
                        <span>${opt.sublabel}</span>
                    </div>
                </div>
            `;
        }).join('');

        if (activeDeliveryModes.length === 0 || !activeDeliveryModes.some(m => options.find(o => o.value === m))) {
            activeDeliveryModes = [options[0].value];
            const firstOpt = container.querySelector('.delivery-option');
            if (firstOpt) {
                firstOpt.classList.add('selected');
                firstOpt.querySelector('input').checked = true;
            }
        }
    } else {
        const currentMode = selectedDeliveryModes[0] || options[0].value;
        const selectedValue = options.find(o => o.value === currentMode) ? currentMode : options[0].value;

        container.innerHTML = options.map((opt, idx) => `
            <div class="delivery-option ${opt.value === selectedValue ? 'selected' : ''}" onclick="selectDeliverySingle(this, '${opt.value}')">
                <input type="radio" name="delivery_mode_radio" value="${opt.value}" ${opt.value === selectedValue ? 'checked' : ''} style="display: none;">
                <div class="option-text">
                    <strong>${opt.label}</strong>
                    <span>${opt.sublabel}</span>
                </div>
            </div>
        `).join('');

        activeDeliveryModes = [selectedValue];
    }

    document.getElementById("deliveryModeInput").value = activeDeliveryModes.join(',');
    document.getElementById("deliveryModesArray").value = activeDeliveryModes.join(',');
    toggleAddress();
}

function selectDeliverySingle(element, value) {
    document.querySelectorAll('.delivery-option').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    document.getElementById("deliveryModeInput").value = value;
    document.getElementById("deliveryModesArray").value = value;
    activeDeliveryModes = [value];
    toggleAddress();
}

function toggleDeliveryMulti(element, value) {
    const checkbox = element.querySelector('input');
    checkbox.checked = !checkbox.checked;

    if (checkbox.checked) {
        element.classList.add('selected');
        if (!activeDeliveryModes.includes(value)) {
            activeDeliveryModes.push(value);
        }
    } else {
        element.classList.remove('selected');
        activeDeliveryModes = activeDeliveryModes.filter(v => v !== value);
    }

    document.getElementById("deliveryModeInput").value = activeDeliveryModes.join(',');
    document.getElementById("deliveryModesArray").value = activeDeliveryModes.join(',');

    toggleAddress();
}

function toggleAddress() {
    const addressSection = document.getElementById("addressSection");
    const addressInput = document.getElementById("streetAddressInput");

    const needsAddress = activeDeliveryModes.some(mode => 
        mode === 'customer_comes_to_me' || mode === 'customer_pickup' || mode === 'both_service'
    );

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
    const eftChecked = document.getElementById("payEFT").checked;
    document.getElementById("eftNotice").style.display = eftChecked ? "block" : "none";
}

function updateAdditionalExtOptions() {
    const primary = document.getElementById("primaryExt").value;
    const container = document.getElementById("additionalExtContainer");

    if (!primary) {
        container.innerHTML = '<span class="text-muted small">Select a primary extension first...</span>';
        return;
    }

    container.innerHTML = allExtensions
        .filter(ext => ext !== primary)
        .map(ext => {
            const isChecked = selectedAdditionalExts.includes(ext) ? 'checked' : '';
            return `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="service_extensions[]" value="${ext}" id="ext_${ext}" ${isChecked}>
                    <label class="form-check-label" for="ext_${ext}">Ext ${ext}</label>
                </div>
            `;
        }).join('');
}

// Photo handling for new uploads
let newSelectedFiles = [];
function handleNewPhotos(input) {
    const currentCount = <?php echo count($gallery_images); ?>;
    const newFiles = Array.from(input.files);

    if (currentCount + newSelectedFiles.length + newFiles.length > 5) { 
        alert("Max 5 images total. You currently have " + currentCount + " uploaded."); 
        return; 
    }
    for (let f of newFiles) {
        if (f.size > 2*1024*1024) { 
            alert(f.name + " too large (max 2MB)"); 
            continue; 
        }
        newSelectedFiles.push(f);
    }
    updateNewFileList();
    updateNewInput();
}

function updateNewFileList() {
    const div = document.getElementById('newFileList');
    if (newSelectedFiles.length === 0) {
        div.innerHTML = '';
        return;
    }
    div.innerHTML = '<div class="mt-2 mb-2"><strong>New photos to add:</strong></div>' + 
        newSelectedFiles.map((f, i) => 
            `<div class="file-item" style="display:flex;justify-content:space-between;padding:5px 10px;background:#f8f9fa;border-radius:5px;margin-bottom:5px;font-size:0.85rem;border:1px solid #eee;">
                <span>${f.name} (${(f.size/1024).toFixed(1)} KB)</span>
                <span style="color:#d9534f;cursor:pointer" onclick="removeNewFile(${i})">×</span>
            </div>`
        ).join('');
}

function removeNewFile(i) { 
    newSelectedFiles.splice(i, 1); 
    updateNewFileList(); 
    updateNewInput(); 
}

function updateNewInput() {
    const dt = new DataTransfer();
    newSelectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('newPhotosInput').files = dt.files;
}

// ===== OPTION B FIX #3: Validation & submit handling =====
document.querySelector('form').onsubmit = function() {
    if (currentListingType !== 'both') {
        const selectedDelivery = document.querySelector('input[name="delivery_mode_radio"]:checked');
        if (selectedDelivery) {
            document.getElementById("deliveryModeInput").value = selectedDelivery.value;
        }
    }

    // Validate based on listing type
    if (currentListingType === 'service' || currentListingType === 'both') {
        const serviceType = document.getElementById('serviceType').value;
        if (!serviceType) {
            alert('Please select a service type.');
            return false;
        }
    }
    if (currentListingType === 'product' || currentListingType === 'both') {
        const productType = document.getElementById('productType').value.trim();
        if (!productType) {
            alert('Please enter a product type.');
            return false;
        }
    }

    updateNewInput();
    document.getElementById('submitBtn').innerHTML = "Saving...";
    document.getElementById('submitBtn').disabled = true;
};

// Initialize on page load
updateFormFields();
updateAdditionalExtOptions();
</script>

</body>
</html>