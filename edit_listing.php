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
    $stmt = mysqli_prepare($conn, "SELECT * FROM Listing WHERE listing_id = ? AND user_id = ?");
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
            margin-top: 15px;
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
            border-left: 3px solid var(--plum);
            padding-left: 15px;
            margin-top: 10px;
            background: #f8f9fa;
            padding-bottom: 10px;
            border-radius: 0 8px 8px 0;
        }

        /* NEW: Extension checkboxes */
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
            background-color: var(--plum);
            border-color: var(--plum);
        }

        /* Service mode display */
        #serviceModeDisplay {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px 15px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #555;
        }
        #serviceModeDisplay .mode-locked {
            color: var(--plum);
            font-weight: 600;
        }
        #serviceModeDisplay .mode-optional {
            color: #28a745;
            font-weight: 600;
        }
        .hidden-field {
            display: none;
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

                <form action="update_listing.php" method="POST">
                    <input type="hidden" name="listing_id" value="<?php echo $listing['listing_id']; ?>">

                    <label class="form-label">Listing Name</label>
                    <input type="text" name="listing_name" class="form-control" value="<?php echo htmlspecialchars($listing['listing_name']); ?>" minlength="3" maxlength="50" required>

                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" id="mainCategory" onchange="updateServiceTypes()" required>
                        <option value="">Select Category...</option>
                        <option value="Construction & Maintenance" <?php if($listing['category']=='Construction & Maintenance') echo 'selected'; ?>>Construction & Maintenance</option>
                        <option value="Transport" <?php if($listing['category']=='Transport') echo 'selected'; ?>>Transport</option>
                        <option value="Home & Rentals" <?php if($listing['category']=='Home & Rentals') echo 'selected'; ?>>Home & Rentals</option>
                        <option value="Food & Essentials" <?php if($listing['category']=='Food & Essentials') echo 'selected'; ?>>Food & Essentials</option>
                        <option value="Personal Care" <?php if($listing['category']=='Personal Care') echo 'selected'; ?>>Personal Care</option>
                    </select>

                    <label class="form-label">Service Type</label>
                    <select name="service_type" class="form-select" id="serviceType" onchange="updateServiceMode()" required>
                        <option value="">Select Type...</option>
                    </select>

                    <!-- NEW: Primary Extension -->
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

                    <!-- NEW: Additional Extensions -->
                    <label class="form-label">Additional Extensions (optional)</label>
                    <div class="ext-checkboxes" id="additionalExtContainer">
                        <!-- Checkboxes generated by JS -->
                    </div>

                    <!-- NEW: Service Mode (auto-set, hidden or visible) -->
                    <label class="form-label">Service Delivery Mode</label>
                    <div id="serviceModeDisplay">
                        <span class="text-muted">Loading...</span>
                    </div>
                    <!-- Hidden input for form submission -->
                    <input type="hidden" name="service_mode" id="serviceModeInput" value="<?php echo $listing['service_mode']; ?>">
                    <!-- Visible select for services that allow both -->
                    <select name="service_mode_visible" class="form-select hidden-field" id="serviceModeSelect" onchange="toggleAddress()">
                        <option value="door-to-door" <?php if($listing['service_mode']=='door-to-door') echo 'selected'; ?>>I go to the customer (Door-to-Door)</option>
                        <option value="physical-site" <?php if($listing['service_mode']=='physical-site') echo 'selected'; ?>>Customers come to me (Physical Shop/Home)</option>
                    </select>

                    <div id="addressSection" <?php if($listing['service_mode']=='physical-site') echo 'style="display:block;"'; ?>>
                        <label class="form-label">Street Address / House Number</label>
                        <input type="text" name="street_address" id="streetAddressInput" class="form-control" value="<?php echo htmlspecialchars($listing['street_address']); ?>" placeholder="e.g. 1234 Peach Street">
                    </div>

                    <label class="form-label">Price Description</label>
                    <input type="text" name="price_description" class="form-control" value="<?php echo htmlspecialchars($listing['price_description']); ?>" placeholder="e.g. Starts from R150" required>

                    <label class="form-label">Business Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Tell customers what you do..." required><?php echo htmlspecialchars($listing['description']); ?></textarea>

                    <button type="submit" class="btn btn-save">Save Changes</button>
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

// Service mode configuration
const serviceModeConfig = {
    "Painting": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "Plumbing": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "Tiling": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "Window Glazing": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "Bakkie-for-hire": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "School Transport": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "Work Transport": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "Appliance Repairs": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "Backroom Rentals": {"mode": "physical-site", "allowBoth": false, "label": "Fixed location"},
    "Gardening": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "Window Cleaning": {"mode": "door-to-door", "allowBoth": false, "label": "Door-to-door service"},
    "Bakery": {"mode": "door-to-door", "allowBoth": true, "label": "Choose your service mode:"},
    "Cooked & Prepared Meals": {"mode": "door-to-door", "allowBoth": true, "label": "Choose your service mode:"},
    "Fresh Produce": {"mode": "door-to-door", "allowBoth": true, "label": "Choose your service mode:"},
    "Gas Refill": {"mode": "door-to-door", "allowBoth": true, "label": "Choose your service mode:"},
    "Hair": {"mode": "physical-site", "allowBoth": true, "label": "Choose your service mode:"},
    "Make-up": {"mode": "door-to-door", "allowBoth": true, "label": "Choose your service mode:"},
    "Nails": {"mode": "physical-site", "allowBoth": true, "label": "Choose your service mode:"},
    "Spa": {"mode": "physical-site", "allowBoth": false, "label": "Fixed location"},
    "Tailor": {"mode": "door-to-door", "allowBoth": true, "label": "Choose your service mode:"},
};

const allExtensions = ["4", "13", "15", "19", "20", "21", "22", "23", "24", "25", "26", "36"];
const selectedAdditionalExts = <?php echo json_encode($selected_additional_exts); ?>;

function updateServiceTypes() {
    const mainCat = document.getElementById("mainCategory").value;
    const typeSelect = document.getElementById("serviceType");
    const currentType = "<?php echo $listing['service_type']; ?>";

    typeSelect.innerHTML = '<option value="">Select Type...</option>';
    if (services[mainCat]) {
        services[mainCat].forEach(type => {
            let option = new Option(type, type);
            if (type === currentType) option.selected = true;
            typeSelect.add(option);
        });
    }

    // Update service mode after setting service type
    updateServiceMode();
}

function updateServiceMode() {
    const serviceType = document.getElementById("serviceType").value;
    const display = document.getElementById("serviceModeDisplay");
    const hiddenInput = document.getElementById("serviceModeInput");
    const select = document.getElementById("serviceModeSelect");
    const addressSection = document.getElementById("addressSection");
    const addressInput = document.getElementById("streetAddressInput");

    if (!serviceType || !serviceModeConfig[serviceType]) {
        display.innerHTML = '<span class="text-muted">Select a service type first...</span>';
        return;
    }

    const config = serviceModeConfig[serviceType];
    const currentMode = hiddenInput.value || config.mode;

    if (config.allowBoth) {
        // Show dropdown for user to choose
        display.innerHTML = '<span class="mode-optional">' + config.label + '</span>';
        select.classList.remove("hidden-field");
        select.value = currentMode;
        select.required = true;
    } else {
        // Locked mode - show info text only
        const modeText = config.mode === "door-to-door" 
            ? '<span class="mode-locked">' + config.label + '</span>' 
            : '<span class="mode-locked">' + config.label + '</span>';
        display.innerHTML = modeText;
        select.classList.add("hidden-field");
        select.required = false;
        hiddenInput.value = config.mode;
    }

    // Handle address section
    const effectiveMode = config.allowBoth ? select.value : config.mode;
    if (effectiveMode === "physical-site") {
        addressSection.style.display = "block";
        addressInput.required = true;
    } else {
        addressSection.style.display = "none";
        addressInput.required = false;
    }
}

function toggleAddress() {
    const serviceType = document.getElementById("serviceType").value;
    const config = serviceModeConfig[serviceType];
    const mode = config && config.allowBoth 
        ? document.getElementById("serviceModeSelect").value 
        : document.getElementById("serviceModeInput").value;

    const addressSection = document.getElementById("addressSection");
    const addressInput = document.getElementById("streetAddressInput");

    if (mode === "physical-site") {
        addressSection.style.display = "block";
        addressInput.required = true;
    } else {
        addressSection.style.display = "none";
        addressInput.required = false;
    }
}

function updateAdditionalExtOptions() {
    const primary = document.getElementById("primaryExt").value;
    const container = document.getElementById("additionalExtContainer");

    if (!primary) {
        container.innerHTML = '<span class="text-muted small">Select primary extension first...</span>';
        return;
    }

    let html = '';
    allExtensions.forEach(ext => {
        if (ext !== primary) {
            const isChecked = selectedAdditionalExts.includes(ext) ? 'checked' : '';
            html += `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="service_extensions[]" value="${ext}" id="ext_${ext}" ${isChecked}>
                    <label class="form-check-label" for="ext_${ext}">Ext ${ext}</label>
                </div>
            `;
        }
    });
    container.innerHTML = html;
}

// Run on page load
updateServiceTypes();
updateAdditionalExtOptions();

document.querySelector('form').onsubmit = function() {
    const serviceType = document.getElementById("serviceType").value;
    const config = serviceModeConfig[serviceType];
    if (config && config.allowBoth) {
        document.getElementById("serviceModeInput").value = document.getElementById("serviceModeSelect").value;
    }
};
</script>

</body>
</html>