<?php
// session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
// ...rest of your code...

?>
<!-- Header Section -->
<link href="style2.css" rel="stylesheet">
<header>
    <nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <a class="h1" href="#">ARMY CSD CANTEEN</a>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                
                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="btn btn-cart ms-2" href="cart.php">🛒 Cart</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">👤 My Profile</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
</header>
