<?php
/** @var mysqli $conn */
include 'includes/db_connect.php';
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: main.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Olievenhoutbosch Digital Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg top-nav sticky-top">
        <div class="container-fluid px-2 px-sm-3">
            <a class="navbar-brand d-flex align-items-center m-0" href="index.php">
                <img src="images/logo.png" width="28" height="28" alt="logo">
                <span class="brand-text fw-bold text-white d-none d-sm-inline ms-2">Olievenhoutbosch Digital Hub</span>
                <span class="brand-text fw-bold text-white d-sm-none ms-1" style="font-size:0.8rem;">Olievenhoutbosch DH</span>
            </a>
            <a href="login.php" class="btn btn-primary rounded-pill px-2 px-sm-3 py-1 py-sm-2">
                <span class="d-none d-sm-inline">Account</span>
                <i class="bi bi-person d-sm-none fs-5"></i>
            </a>
        </div>
    </nav>

    <header class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold">Your Neighbourhood, Digitized</h1>
            <p class="lead mb-4">The central marketplace for all Olievenhoutbosch goods and services..</p>
            <a href="register.php" class="btn btn-primary btn-lg rounded-pill px-5 shadow">Get Started</a>
        </div>
    </header>

    <section class="how-it-works">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold section-title">How It Works</h2>
                <p class="text-muted">Find trusted local listings in 3 simple steps</p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-md-4">
                    <div class="step-card text-center">
                        <div class="step-icon"><i class="bi bi-search"></i></div>
                        <h5>Discover</h5>
                        <p class="text-muted">Browse verified local listings in your area by category.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card text-center">
                        <div class="step-icon"><i class="bi bi-chat-dots-fill"></i></div>
                        <h5>Connect</h5>
                        <p class="text-muted">Message sellers directly to arrange your purchase or booking.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card text-center">
                        <div class="step-icon"><i class="bi bi-hand-thumbs-up-fill"></i></div>
                        <h5>Review</h5>
                        <p class="text-muted">Rate your experience to help others.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="services-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold section-title">Browse Listings</h2>
                <p class="text-muted">Find exactly what you need in your local extension.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="service-card" onclick="location.href='#'">
                        <i class="bi bi-houses"></i>
                        <h6>Construction & Maintenance</h6>
                        <small>Plumbing, Painting, Tiling</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="service-card" onclick="location.href='#'">
                        <i class="bi bi-truck"></i>
                        <h6>Transport</h6>
                        <small>Bakkie Hire, School Transport</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="service-card" onclick="location.href='#'">
                        <i class="bi bi-key"></i>
                        <h6>Home & Rentals</h6>
                        <small>Backrooms, Appliance Repair</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="service-card" onclick="location.href='#'">
                        <i class="bi bi-egg-fried"></i>
                        <h6>Food & Essentials</h6>
                        <small>Bakeries, Prepared Meals</small>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="service-card" onclick="location.href='#'">
                        <i class="bi bi-scissors"></i>
                        <h6>Personal Care</h6>
                        <small>Hair, Nails, Spa, Tailor</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-footer text-center">
        <div class="container">
            <p class="brand-text mb-1">Olievenhoutbosch Digital Hub</p>
            <p class="copyright">2026 All rights reserved</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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