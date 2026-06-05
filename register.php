<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Olievenhoutbosch Digital Hub</title>
    <link rel="icon" type="image/png" href="images/logo.png"> 
    <link rel="apple-touch-icon" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="auth-page">
        <div class="w-100" style="max-width: 500px;">
            <div class="card shadow border-0">
                <div class="card-body p-3 p-sm-4 p-md-5">
                    <h2 class="text-center mb-4" style="color: var(--plum);">Create Account</h2>

                    <?php if(isset($_GET['error'])): ?>
                            <div class="alert alert-danger py-2">
                                <?php 
                                    if($_GET['error'] == 'emailtaken') echo "That email is already registered!";
                                    if($_GET['error'] == 'passmatch') echo "Passwords do not match!";
                                    if($_GET['error'] == 'passshort') echo "Password must be at least 6 characters.";
                                    if($_GET['error'] == 'nameinvalid') echo "Name cannot contain numbers.";
                                    if($_GET['error'] == 'nameshort') echo "Name is too short!";
                                    if($_GET['error'] == 'invalidemail') echo "Please use a valid email (e.g., .com, .co.za, or .gmail.com)";
                                    if($_GET['error'] == 'nophone') echo "Please enter your contact number.";
                                    if($_GET['error'] == 'phoneinvalid') echo "Please enter a valid South African number.";
                                    if($_GET['error'] == 'securityempty') echo "Please select and answer a security question.";
                                ?>
                            </div>
                    <?php endif; ?>

                    <form action="register_process.php" method="POST">
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   pattern="^[a-zA-Z]{2,}(?:\s[a-zA-Z]{2,})*$" 
                                   title="Please enter at least two letters. Numbers and symbols are not allowed." required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" 
                                   title="Please enter a valid email address (e.g., name@gmail.com, name@company.co.za)" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <div class="input-group">
                                <span class="input-group-text">+27</span>
                                <input type="tel" name="contact_number" class="form-control" 
                                    pattern="^(0[6-8][0-9]{8}|[6-8][0-9]{8})$" 
                                    required>
                            </div>
                            <div class="form-text text-muted small">For calls and messages. Don't type +27 (already there)</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Security Question</label>
                            <select name="security_question" class="form-select" required>
                                <option selected disabled>Select a security question...</option>
                                <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                                <option value="What was your first pet's name?">What was your first pet's name?</option>
                                <option value="What is your favorite color?">What is your favorite color?</option>
                                <option value="What city were you born in?">What city were you born in?</option>
                                <option value="What is your favorite food?">What is your favorite food?</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Security Answer</label>
                            <input type="text" name="security_answer" class="form-control" 
                                   required minlength="2">
                            <div class="form-text text-muted small">You'll need this to reset your password if you forget it.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" 
                                   required minlength="6" 
                                   title="Password must be at least 6 characters long.">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Extension</label>
                            <select name="extension" class="form-select" required>
                                <option selected disabled>Select Extension...</option>
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
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-block">I want to:</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="user_role" value="Customer" checked>
                                <label class="form-check-label">Buy</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="user_role" value="Provider">
                                <label class="form-check-label">Sell</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="user_role" value="Both">
                                <label class="form-check-label">Both</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Register</button>
                   
                        <p class="text-center mt-3">
                            Already have an account? <a href="login.php">Login here</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
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