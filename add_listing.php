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

// Only Provider or Both can add listings
if (!in_array($user_role, ['Provider', 'Both'])) {
    header("Location: main.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Listing - Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #c99383;
            --copper: #ba745f;
        }

        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
            padding-top: 30px; 
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

        .button-row { 
            display: flex; 
            gap: 12px; 
            margin-top: 30px; 
        }

        .btn-register { 
            background-color: var(--rose-gold); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            padding: 12px; 
            font-weight: bold; 
            flex: 1; 
        }

        .btn-register:hover { 
            background-color: var(--copper); 
            color: white; 
        }

        .btn-cancel { 
            background-color: transparent; 
            color: var(--rose-gold); 
            border: 2px solid var(--rose-gold); 
            border-radius: 8px; 
            padding: 12px; 
            font-weight: bold; 
            flex: 1; 
        }

        .btn-cancel:hover { 
            background-color: var(--rose-gold); 
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

        .upload-area { 
            border: 2px dashed var(--rose-gold); 
            border-radius: 8px; 
            padding: 20px; 
            text-align: center; 
            cursor: pointer; 
        }

        .upload-area:hover { 
            background: #fff9f8; 
        }

        .file-item { 
            display: flex; 
            justify-content: space-between; 
            padding: 5px 10px; 
            background: white; 
            border-radius: 5px; 
            margin-bottom: 5px; 
            font-size: 0.85rem; 
            border: 1px solid #eee; 
        }

        /* Checkboxes grid */
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

        /* Type selector styling */
        .type-selector {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        .type-option {
            flex: 1;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .type-option:hover {
            border-color: var(--rose-gold);
            background: #fff9f8;
        }
        .type-option.selected {
            border-color: var(--plum);
            background: #fdf8ff;
        }
        .type-option input {
            display: none;
        }
        .type-option i {
            font-size: 1.5rem;
            color: var(--copper);
            margin-bottom: 6px;
            display: block;
        }
        .type-option.selected i {
            color: var(--plum);
        }
        .type-option strong {
            display: block;
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 2px;
        }
        .type-option span {
            font-size: 0.75rem;
            color: #888;
        }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center">
        <div class="form-card">
            <div class="form-title">Register New Listing</div>

            <form action="add_listing_process.php" method="POST" enctype="multipart/form-data" id="listingForm">

                <!-- BASIC INFO -->
                <div class="section-title">Basic Information</div>

                <label class="form-label">Listing Name</label>
                <input type="text" name="listing_name" class="form-control" minlength="3" maxlength="50" placeholder="e.g. John's Bakery" required>

                <label class="form-label">What are you offering?</label>
                <div class="type-selector">
                    <div class="type-option selected" onclick="selectType(this, 'service')">
                        <input type="radio" name="listing_type" id="typeService" value="service" checked onchange="updateDeliveryOptions()">
                        <i class="bi bi-tools"></i>
                        <strong>Service</strong>
                        <span>I do work</span>
                    </div>
                    <div class="type-option" onclick="selectType(this, 'product')">
                        <input type="radio" name="listing_type" id="typeProduct" value="product" onchange="updateDeliveryOptions()">
                        <i class="bi bi-box-seam"></i>
                        <strong>Goods</strong>
                        <span>I sell items</span>
                    </div>
                    <div class="type-option" onclick="selectType(this, 'both')">
                        <input type="radio" name="listing_type" id="typeBoth" value="both" onchange="updateDeliveryOptions()">
                        <i class="bi bi-grid"></i>
                        <strong>Both</strong>
                        <span>I do both</span>
                    </div>
                </div>

                <label class="form-label">Main Category</label>
                <select name="category" class="form-select" id="mainCategory" onchange="updateServiceTypes()" required>
                    <option value="">Select Category...</option>
                    <option>Construction & Maintenance</option>
                    <option>Transport</option>
                    <option>Home & Rentals</option>
                    <option>Food & Essentials</option>
                    <option>Personal Care</option>
                </select>

                <label class="form-label" id="serviceTypeLabel">Service / Product Type</label>
                <select name="service_type" class="form-select" id="serviceType" required>
                    <option value="">Select Type...</option>
                </select>

                <!-- LOCATION -->
                <div class="section-divider"></div>
                <div class="section-title">Location</div>

                <label class="form-label">Primary Extension</label>
                <select name="extension" class="form-select" id="primaryExt" onchange="updateAdditionalExtOptions()" required>
                    <option selected disabled value="">Select Extension...</option>
                    <option value="4">Ext 4</option>
                    <option value="13">Ext 13</option>
                    <option value="15">Ext 15</option>
                    <option value="19">Ext 19</option>
                    <option value="20">Ext 20</option>
                    <option value="21">Ext 21</option>
                    <option value="22">Ext 22</option>
                    <option value="23">Ext 23</option>
                    <option value="24">Ext 24</option>
                    <option value="25">Ext 25</option>
                    <option value="26">Ext 26</option>
                    <option value="36">Ext 36</option>     
                </select>

                <label class="form-label">Additional Extensions (optional)</label>
                <div class="ext-checkboxes" id="additionalExtContainer">
                    <span class="text-muted small">Select a primary extension first...</span>
                </div>

                <!-- DELIVERY / SERVICE MODE -->
                <div class="section-divider"></div>
                <div class="section-title" id="deliverySectionTitle">How Customers Receive Your Service</div>

                <div class="delivery-options" id="deliveryOptionsContainer">
                    <!-- Populated by JS -->
                </div>
                <input type="hidden" name="delivery_mode" id="deliveryModeInput" value="door_to_door">
                <input type="hidden" name="delivery_modes[]" id="deliveryModesArray" value="">

                <div id="addressSection">
                    <label class="form-label">Street Address / House Number</label>
                    <input type="text" name="street_address" id="streetAddressInput" class="form-control" placeholder="e.g. 1234 Peach Street">
                    <div class="hint" id="addressHint">Required when customers come to you or pick up</div>
                </div>

                <!-- PRICING & PAYMENT -->
                <div class="section-divider"></div>
                <div class="section-title">Pricing & Payment</div>

                <label class="form-label">Price Description</label>
                <input type="text" name="price_description" class="form-control" placeholder="e.g. Starts from R150 / R50 per item" required>

                <label class="form-label">Payment Options (select all that apply)</label>
                <div class="payment-checkboxes">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="payment_options[]" id="payCash" value="Cash" checked>
                        <label class="form-check-label" for="payCash">Cash</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="payment_options[]" id="payEFT" value="EFT" onchange="toggleEFTNotice()">
                        <label class="form-check-label" for="payEFT">EFT (Bank Transfer)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="payment_options[]" id="payCard" value="Card">
                        <label class="form-check-label" for="payCard">Card (in person)</label>
                    </div>
                </div>

                <div class="privacy-notice" id="eftNotice">
                    For your data privacy, EFT details should be shared with inquiring customers via messaging.
                </div>

                <!-- DESCRIPTION -->
                <div class="section-divider"></div>
                <div class="section-title">Description</div>

                <label class="form-label">Listing Description</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Describe what you offer, your experience, availability..." required></textarea>

                <!-- PHOTOS -->
                <div class="section-divider"></div>
                <div class="section-title">Photos</div>

                <label class="form-label">Photos</label>
                <div class="upload-area" onclick="document.getElementById('workPhotos').click()">
                    <div style="color: var(--rose-gold); font-size: 1.5rem;">+</div>
                    <div style="font-size: 0.9rem; color: #666;">Click to upload photos</div>
                    <div style="font-size: 0.75rem; color: #999;">Max 5 images, 2MB each, JPG/PNG</div>
                </div>
                <input type="file" name="work_photos[]" id="workPhotos" multiple accept="image/jpeg,image/png" style="display: none;" onchange="handleFiles(this)" required>
                <div id="fileList"></div>

                <!-- BUTTONS -->
                <div class="button-row">
                    <button type="submit" id="submitBtn" class="btn btn-register">Create Listing</button>
                    <button type="button" id="cancelBtn" class="btn btn-cancel">Cancel</button>
                </div>
            </form>
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

// Context-aware delivery options
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

let currentListingType = 'service';
let selectedDeliveryModes = ['door_to_door'];

function selectType(element, value) {
    document.querySelectorAll('.type-option').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    currentListingType = value;
    updateDeliveryOptions();

    // Update label text
    const label = document.getElementById('serviceTypeLabel');
    if (value === 'service') {
        label.textContent = 'Service Type';
    } else if (value === 'product') {
        label.textContent = 'Product Type';
    } else {
        label.textContent = 'Service / Product Type';
    }
}

function updateServiceTypes() {
    const cat = document.getElementById("mainCategory").value;
    const sel = document.getElementById("serviceType");
    sel.innerHTML = '<option value="">Select Type...</option>';
    if (services[cat]) {
        services[cat].forEach(t => sel.add(new Option(t, t)));
    }
}

function updateDeliveryOptions() {
    const listingType = document.querySelector('input[name="listing_type"]:checked').value;
    currentListingType = listingType;
    const config = deliveryOptions[listingType] || deliveryOptions.service;
    const container = document.getElementById("deliveryOptionsContainer");
    const options = config.options;

    // Update section title
    document.getElementById("deliverySectionTitle").textContent = config.title;
    document.getElementById("addressHint").textContent = config.hint;

    selectedDeliveryModes = [options[0].value];

    if (listingType === 'both') {
        container.innerHTML = options.map((opt, idx) => `
            <div class="delivery-option ${idx === 0 ? 'selected' : ''}" onclick="toggleDeliveryMulti(this, '${opt.value}')">
                <input type="checkbox" name="delivery_mode_multi[]" value="${opt.value}" ${idx === 0 ? 'checked' : ''} style="display: none;">
                <div class="option-text">
                    <strong>${opt.label}</strong>
                    <span>${opt.sublabel}</span>
                </div>
            </div>
        `).join('');
    } else {
        container.innerHTML = options.map((opt, idx) => `
            <div class="delivery-option ${idx === 0 ? 'selected' : ''}" onclick="selectDeliverySingle(this, '${opt.value}')">
                <input type="radio" name="delivery_mode_radio" value="${opt.value}" ${idx === 0 ? 'checked' : ''} style="display: none;">
                <div class="option-text">
                    <strong>${opt.label}</strong>
                    <span>${opt.sublabel}</span>
                </div>
            </div>
        `).join('');
    }

    document.getElementById("deliveryModeInput").value = options[0].value;
    document.getElementById("deliveryModesArray").value = options[0].value;
    toggleAddress();
}

function selectDeliverySingle(element, value) {
    document.querySelectorAll('.delivery-option').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    document.getElementById("deliveryModeInput").value = value;
    document.getElementById("deliveryModesArray").value = value;
    selectedDeliveryModes = [value];
    toggleAddress();
}

function toggleDeliveryMulti(element, value) {
    const checkbox = element.querySelector('input');
    checkbox.checked = !checkbox.checked;

    if (checkbox.checked) {
        element.classList.add('selected');
        if (!selectedDeliveryModes.includes(value)) {
            selectedDeliveryModes.push(value);
        }
    } else {
        element.classList.remove('selected');
        selectedDeliveryModes = selectedDeliveryModes.filter(v => v !== value);
    }

    document.getElementById("deliveryModeInput").value = selectedDeliveryModes.join(',');
    document.getElementById("deliveryModesArray").value = selectedDeliveryModes.join(',');

    toggleAddress();
}

function toggleAddress() {
    const addressSection = document.getElementById("addressSection");
    const addressInput = document.getElementById("streetAddressInput");

    const needsAddress = selectedDeliveryModes.some(mode => 
        mode === 'customer_comes_to_me' || mode === 'customer_pickup' || mode === 'both_service' || mode === 'both_product'
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
        .map(ext => `
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="service_extensions[]" value="${ext}" id="ext_${ext}">
                <label class="form-check-label" for="ext_${ext}">Ext ${ext}</label>
            </div>
        `).join('');
}

let selectedFiles = [];
function handleFiles(input) {
    const newFiles = Array.from(input.files);
    if (selectedFiles.length + newFiles.length > 5) { 
        alert("Max 5 images"); 
        return; 
    }
    for (let f of newFiles) {
        if (f.size > 2*1024*1024) { 
            alert(f.name + " too large (max 2MB)"); 
            continue; 
        }
        selectedFiles.push(f);
    }
    updateList();
    updateInput();
}

function updateList() {
    const div = document.getElementById('fileList');
    div.innerHTML = selectedFiles.map((f, i) => 
        `<div class="file-item">
            <span>${f.name} (${(f.size/1024).toFixed(1)} KB)</span>
            <span style="color:#d9534f;cursor:pointer" onclick="removeFile(${i})">×</span>
        </div>`
    ).join('');
}

function removeFile(i) { 
    selectedFiles.splice(i, 1); 
    updateList(); 
    updateInput(); 
}

function updateInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('workPhotos').files = dt.files;
}

document.getElementById('listingForm').onsubmit = function() {
    if (currentListingType !== 'both') {
        const selectedDelivery = document.querySelector('input[name="delivery_mode_radio"]:checked');
        if (selectedDelivery) {
            document.getElementById("deliveryModeInput").value = selectedDelivery.value;
        }
    }

    updateInput();
    document.getElementById('submitBtn').innerHTML = "Creating...";
    document.getElementById('submitBtn').disabled = true;
};

document.getElementById('cancelBtn').onclick = function() {
    if (confirm('Cancel? All changes will be lost.')) {
        window.location.href = 'listing_dashboard.php';
    }
};

// Initialize
updateDeliveryOptions();
</script>

</body>
</html>