<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Olievenhoutbosch Digital Hub</title>
    <link rel="icon" type="image/png" href="images/logo.png"> 
    <link rel="apple-touch-icon" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Vertical center the login card */
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="login-page">
        <div class="w-100" style="max-width: 450px;">
            <div class="card shadow border-0">
                <div class="card-body p-3 p-sm-4 p-md-5">
                    <h2 class="text-center mb-4" style="color: var(--plum);">Login</h2>
                    
                    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                        <div class="alert alert-success">Registration successful! Please login.</div>
                    <?php endif; ?>

                    <?php if(isset($_GET['error'])): ?>
                        <div class="alert alert-danger py-2">
                            <?php 
                                if($_GET['error'] == 'wrongpass') echo "Incorrect password. Please try again.";
                                if($_GET['error'] == 'nouser') echo "No account found with that email.";
                                if($_GET['error'] == 'empty') echo "Please fill in all fields.";
                                if($_GET['error'] == 'invalidemail') echo "Please use a valid email (e.g., .com, .co.za, or .gmail.com)";
                                if($_GET['error'] == 'suspended') echo "Your account has been suspended.";
                            ?>
                        </div>
                    <?php endif; ?>

                    <form action="login_process.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" 
                                title="Please enter a valid email address (e.g., name@gmail.com or name@company.co.za)" 
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" 
                                required minlength="6"
                                title="Password must be at least 6 characters">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Login</button>
                    </form>
                                            
                    <p class="text-center mt-3">
                        Don't have an account? <a href="register.php">Register here</a>
                    </p>
                    <p class="text-center mt-2">
                        <a href="forgot_password.php" class="text-muted small text-decoration-none">Forgot your password?</a>
                    </p>
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