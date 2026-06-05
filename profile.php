<?php
session_start();
include 'includes/db_connect.php';
/** @var mysqli $conn */

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$back_link = 'index.php';
if (isset($_SERVER['HTTP_REFERER'])) {
    $from = $_SERVER['HTTP_REFERER'];
    $host = $_SERVER['HTTP_HOST'];
    
    if (strpos($from, $host) !== false 
        && !str_contains($from, 'login.php') 
        && !str_contains($from, 'profile.php')) {
        $back_link = $from;
    }
}

$stmt = mysqli_prepare($conn, "SELECT full_name, email, contact_number, extension, user_role FROM useraccount WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user_data) {
    header("Location: login.php");
    exit();
}

$user = [
    'full_name' => $user_data['full_name'],
    'email' => $user_data['email'],
    'contact_number' => $user_data['contact_number'] ?? '',
    'extension' => $user_data['extension'],
    'user_role' => $user_data['user_role'],
    'avatar_letter' => strtoupper(substr($user_data['full_name'], 0, 1))
];

$role_labels = [
    'Provider'  => 'Service Provider',
    'Admin'     => 'System Administrator',
    'Customer'  => 'Customer',
    'Both'      => 'Customer & Provider'
];
$role_display = $role_labels[$user['user_role']] ?? 'Unknown Role';

$contact_display = '';
if (!empty($user['contact_number'])) {
    $digits = preg_replace('/[^0-9]/', '', $user['contact_number']);
    if (strpos($digits, '27') === 0) {
        $contact_display = substr($digits, 2);
    } else {
        $contact_display = $digits;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --plum: #230344;
            --rose-gold: #c99383;
            --blush: #d8b2a7;
            --copper: #ba745f;
            --light-grey: #f4f7f6;
        }

        /* Brand name swap: full on desktop, short on mobile */
        .brand-text.full-name { display: inline !important; }
        .brand-text.short-name { display: none !important; }

        @media (max-width: 575.98px) {
            .brand-text.full-name { display: none !important; }
            .brand-text.short-name { display: inline !important; }
        }

        body { 
            background-color: var(--light-grey);
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        .top-nav {
            background-color: var(--plum) !important;
            min-height: 60px;
            padding: 0.5rem 1rem;
            border-bottom: 3px solid var(--rose-gold);
        }

        .brand-text {
            font-size: clamp(0.75rem, 2vw, 1.1rem);
            font-weight: bold;
            color: white;
            white-space: nowrap;
        }

        .profile-container { margin-top: 20px; }

        .sidebar-nav .nav-link {
            color: #555;
            border-radius: 10px;
            padding: 12px 20px;
            margin-bottom: 5px;
        }

        .sidebar-nav .nav-link.active {
            background-color: var(--plum) !important;
            color: white !important;
        }

        .glass-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .avatar-circle {
            width: 120px;
            height: 120px;
            background-color: var(--rose-gold);
            color: var(--plum);
            font-size: 3rem;
            font-weight: bold;
            border: 6px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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

        .btn-outline-plum {
            background: transparent;
            border: 2px solid var(--plum);
            color: var(--plum);
            font-weight: bold;
        }

        .btn-outline-plum:hover {
            background: var(--plum);
            color: white;
        }

        .btn-cancel {
            background: transparent;
            border: 2px solid #dc3545;
            color: #dc3545;
            font-weight: bold;
        }

        .btn-cancel:hover {
            background: #dc3545;
            color: white;
        }

        .editable-field:read-only, .editable-field:disabled {
            background: #f8f9fa !important;
            cursor: default;
        }

        .password-toggle {
            cursor: pointer;
            color: #6c757d;
        }

        .password-toggle:hover { color: var(--plum); }

        .forgot-link {
            color: #6c757d;
            font-size: 0.85rem;
            text-decoration: none;
        }

        .forgot-link:hover { color: var(--plum); text-decoration: underline; }

        /* Back button text swap */
        .back-text { display: inline; }
        .back-icon-only { display: none; }

        @media (max-width: 575.98px) {
            .back-text { display: none; }
            .back-icon-only { display: inline; }
        }
    </style>
</head>
<body>

    <!-- Top Navigation -->
    <nav class="navbar top-nav sticky-top">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="images/logo.png" width="30" height="30" alt="logo" class="me-2">
                <span class="brand-text full-name">Olievenhoutbosch Digital Hub</span>
                <span class="brand-text short-name">Olievenhoutbosch DH</span>
            </a>
            <a href="<?php echo htmlspecialchars($back_link); ?>" class="text-white text-decoration-none small d-flex align-items-center">
                <i class="bi bi-arrow-left me-1 back-icon-only"></i>
                <span class="back-text"><i class="bi bi-arrow-left me-1"></i> Back</span>
            </a>
        </div>
    </nav>

    <div class="container profile-container mb-5">
        <div class="row g-4">
            
            <!-- Left Sidebar -->
            <div class="col-lg-4">
                <div class="card glass-card text-center p-4">
                    <div class="mb-3">
                        <div class="avatar-circle rounded-circle d-flex align-items-center justify-content-center mx-auto">
                            <?php echo $user['avatar_letter']; ?>
                        </div>
                    </div>
                    
                    <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($role_display); ?></p>

                    <hr class="my-4 opacity-50">
                    
                    <div class="nav flex-column sidebar-nav" id="profileTabs" role="tablist">
                        <a class="nav-link active" data-bs-toggle="pill" href="#personal-info" role="tab">
                            <i class="bi bi-person-vcard me-2"></i> Personal Details
                        </a>
                        <a class="nav-link" data-bs-toggle="pill" href="#password-settings" role="tab">
                            <i class="bi bi-key me-2"></i> Password
                        </a>
                        <a href="logout.php" class="nav-link text-danger mt-2">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Content -->
            <div class="col-lg-8">
                <div class="card glass-card p-4 p-md-5">
                    <div class="tab-content">
                        
                        <!-- Personal Details Tab -->
                        <div class="tab-pane fade show active" id="personal-info" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold mb-0">Personal Details</h4>
                                <button type="button" class="btn btn-outline-plum btn-sm" id="editBtn" onclick="toggleEdit()">
                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                </button>
                            </div>

                            <form action="update_profile.php" method="POST">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small fw-bold">Full Name</label>
                                        <input type="text" name="full_name" class="form-control form-control-lg bg-light border-0 editable-field" 
                                               value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small fw-bold">Email Address</label>
                                        <input type="email" name="email" class="form-control form-control-lg bg-light border-0 editable-field" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small fw-bold">Contact Number</label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-light border-0 text-muted">+27</span>
                                            <input type="tel" name="contact_number" class="form-control bg-light border-0 editable-field" 
                                                   value="<?php echo htmlspecialchars($contact_display); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small fw-bold">Extension</label>
                                        <select name="extension" class="form-select form-select-lg bg-light border-0 editable-field" disabled>
                                            <option disabled>Select Extension...</option>
                                            <option value="4" <?php if($user['extension']=='4') echo 'selected'; ?>>Ext 4</option>
                                            <option value="13" <?php if($user['extension']=='13') echo 'selected'; ?>>Ext 13</option>
                                            <option value="15" <?php if($user['extension']=='15') echo 'selected'; ?>>Ext 15</option>
                                            <option value="19" <?php if($user['extension']=='19') echo 'selected'; ?>>Ext 19</option>
                                            <option value="20" <?php if($user['extension']=='20') echo 'selected'; ?>>Ext 20</option>
                                            <option value="21" <?php if($user['extension']=='21') echo 'selected'; ?>>Ext 21</option>
                                            <option value="22" <?php if($user['extension']=='22') echo 'selected'; ?>>Ext 22</option>
                                            <option value="23" <?php if($user['extension']=='23') echo 'selected'; ?>>Ext 23</option>
                                            <option value="24" <?php if($user['extension']=='24') echo 'selected'; ?>>Ext 24</option>
                                            <option value="25" <?php if($user['extension']=='25') echo 'selected'; ?>>Ext 25</option>
                                            <option value="26" <?php if($user['extension']=='26') echo 'selected'; ?>>Ext 26</option>
                                            <option value="36" <?php if($user['extension']=='36') echo 'selected'; ?>>Ext 36</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mt-4" id="actionBtns" style="display:none;">
                                        <button type="submit" class="btn btn-rose btn-lg px-5 me-2">Save Changes</button>
                                        <button type="button" class="btn btn-cancel btn-lg px-4" onclick="cancelEdit()">Cancel</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Password Tab -->
                        <div class="tab-pane fade" id="password-settings" role="tabpanel">
                            <h4 class="fw-bold mb-4">Change Password</h4>
                            
                            <form action="update_password.php" method="POST">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Current Password</label>
                                        <div class="input-group">
                                            <input type="password" name="current_password" class="form-control bg-light border-0" required>
                                            <span class="input-group-text bg-light border-0 password-toggle" onclick="togglePw(this)">
                                                <i class="bi bi-eye-slash"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">New Password</label>
                                        <div class="input-group">
                                            <input type="password" name="new_password" class="form-control bg-light border-0" required minlength="6">
                                            <span class="input-group-text bg-light border-0 password-toggle" onclick="togglePw(this)">
                                                <i class="bi bi-eye-slash"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" name="confirm_password" class="form-control bg-light border-0" required>
                                            <span class="input-group-text bg-light border-0 password-toggle" onclick="togglePw(this)">
                                                <i class="bi bi-eye-slash"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-3 d-flex align-items-center gap-3 flex-wrap">
                                        <button type="submit" class="btn btn-rose px-4">Update Password</button>
                                        <a href="forgot_password.php" class="forgot-link">Forgot your password?</a>
                                    </div>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let originals = {};

        function toggleEdit() {
            var fields = document.querySelectorAll('.editable-field');
            for (var i = 0; i < fields.length; i++) {
                originals[fields[i].name] = fields[i].value;
                fields[i].readOnly = false;
                fields[i].disabled = false;
            }
            document.getElementById('editBtn').style.display = 'none';
            document.getElementById('actionBtns').style.display = 'block';
        }

        function cancelEdit() {
            var fields = document.querySelectorAll('.editable-field');
            for (var i = 0; i < fields.length; i++) {
                fields[i].value = originals[fields[i].name];
                fields[i].readOnly = true;
                fields[i].disabled = true;
            }
            document.getElementById('editBtn').style.display = 'inline-block';
            document.getElementById('actionBtns').style.display = 'none';
        }

        function togglePw(btn) {
            var input = btn.previousElementSibling;
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye-slash';
            }
        }
    </script>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then((registration) => {
                        console.log('Service Worker registered:', registration.scope);
                    })
                    .catch((error) => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }
    </script>
</body>
</html>