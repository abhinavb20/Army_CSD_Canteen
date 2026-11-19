<?php
// Keep session_start() in the page that includes this header
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$cartCount  = (int)($_SESSION['cart_count'] ?? 0); // set this when items are added
$current    = basename($_SERVER['PHP_SELF']);
?>
<style>
    .site-header { background:#103d25; border-bottom:1px solid rgba(255,255,255,.08); position:sticky; top:0; z-index:1030; }
    .site-header .navbar { min-height:82px; }
    .site-header .brand { color:#fff; text-decoration:none; display:flex; align-items:center; gap:14px; }
    .site-header .brand img { width:46px; height:46px; object-fit:contain; }
    .site-header .brand-name { font-size:18px; font-weight:700; letter-spacing:.4px; line-height:1.1; }
    .site-header .brand-sub { font-size:11px; font-weight:500; letter-spacing:1.6px; text-transform:uppercase; color:#a9c7b2; }
    .site-header .nav-link { color:#d7e7dc !important; font-size:15px; font-weight:500; padding:11px 16px !important; border-radius:6px; }
    .site-header .nav-link:hover, .site-header .nav-link.active { color:#fff !important; background:rgba(255,255,255,.08); }
    .site-header .btn-cart { position:relative; background:#c8e49b; color:#103d25; font-weight:700; font-size:15px; border-radius:8px; padding:10px 18px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
    .site-header .btn-cart:hover { background:#d7eeb0; color:#103d25; }
    .site-header .cart-count { background:#103d25; color:#c8e49b; font-size:11px; font-weight:700; min-width:20px; height:20px; border-radius:10px; display:inline-grid; place-items:center; padding:0 6px; }
</style>

<header class="site-header">
    <nav class="navbar navbar-expand-lg py-2">
        <div class="container-fluid px-lg-5">
            <a class="brand" href="index.php">
                <img src="icons/logo.png" alt="Army CSD Canteen logo">
                <span>
                    <span class="brand-name d-block">ARMY CSD CANTEEN</span>
                    <span class="brand-sub d-block">Online Store</span>
                </span>
            </a>

            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-3"></i>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 mt-3 mt-lg-0">
                    <li class="nav-item"><a class="nav-link <?= $current === 'index.php' ? 'active' : '' ?>" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link <?= $current === 'contact.php' ? 'active' : '' ?>" href="contact.php">Contact</a></li>

                    <?php if (!$isLoggedIn): ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person-circle me-1"></i>My Profile</a></li>
                    <?php endif; ?>

                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn-cart" href="cart.php">
                            <i class="bi bi-cart3"></i> Cart
                            <span class="cart-count" id="cartCount"><?= $cartCount ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>