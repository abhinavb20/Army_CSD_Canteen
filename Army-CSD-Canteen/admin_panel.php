<?php
session_start();
include 'config.php';

// Check admin login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

// Fetch active products
$result = $conn->query("SELECT * FROM products WHERE is_active = 1");

// Fetch dashboard stats
$total_users = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
$pending_requests = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE is_approved = 0")->fetch_assoc()['cnt'];
$return_requests = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE return_status = 'Requested'")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style2.css" rel="stylesheet">
    <style>
        body {
            background: #181a20;
        }
        .sidebar {
            background: #23272f;
            color: #fff;
            min-height: 100vh;
            padding-top: 30px;
        }
        .sidebar .nav-link, .sidebar .navbar-brand {
            color: #fff;
            font-weight: 500;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: #198754;
            color: #fff;
            border-radius: 8px;
        }
        .admin-btn-row .btn, .admin-btn-row a {
            margin-right: 12px;
            margin-bottom: 8px;
        }
        .admin-btn-row {
            flex-wrap: wrap;
        }
        .table th, .table td {
            vertical-align: middle !important;
        }
        .table {
            background: #23272f;
            color: #fff;
        }
        .table th {
            background: #198754 !important;
            color: #fff !important;
        }
        .table td {
            background: #23272f !important;
            color: #fff !important;
        }
        .card, .card-header, .card-body {
            background: #23272f;
            color: #fff;
        }
        .navbar-custom {
            background-color: #198754 !important;
            min-height: 66px;
            max-height: 66px;
        }
        .navbar-brand, .navbar-nav .nav-link {
            color: #fff !important;
            font-weight: bold;
        }
        .navbar-brand .h1 {
            color: #fff !important;
        }
        .img-thumbnail {
            background: #181a20;
        }
    </style>
</head>
<body>
<!-- Green Header -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <span class="h1 mb-0" style="font-size:2rem;">ARMY CSD CANTEEN</span>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar d-flex flex-column align-items-center">
            <div class="navbar-brand mb-4">
                <span style="font-size:2rem;">🛡️</span> <span style="font-size:1.3rem;">Admin Panel</span>
            </div>
            <nav class="nav flex-column w-100">
                <a class="nav-link active mb-2" href="admin_panel.php">Admin Panel</a>
                <a class="nav-link mb-2" href="product_dashboard.php">Product Dashboard</a>
                <a class="nav-link mb-2" href="adminlog.php">Users</a>
                <a class="nav-link mb-2" href="orders_admin.php">Orders</a>
                <a class="nav-link mb-2" href="returned_stock.php">Returned</a>
                
            </nav>
        </div>
        <!-- Right Sidebar / Stats -->
        <div class="col-md-3 py-4">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">Dashboard</div>
                <div class="card-body">
                    <div class="mb-2">Total Users: <span class="fw-bold"><?= $total_users ?></span></div>
                    <div class="mb-2">Pending Requests: <span class="fw-bold"><?= $pending_requests ?></span></div>
                    <div class="mb-2">Return Requests: <span class="fw-bold"><?= $return_requests ?></span></div>
                    <hr>
                                </div>
            </div>
            <div class="card">
                <div class="card-header bg-info text-white">Quick Links</div>
                <div class="card-body">
                    <a href="add_product.php" class="btn btn-outline-success btn-sm mb-2 w-100">Add Product</a>
                    <a href="update_stock.php" class="btn btn-outline-warning btn-sm mb-2 w-100">Update Stock</a>
                    <a href="orders_admin.php" class="btn btn-outline-info btn-sm mb-2 w-100">View Orders</a>
                    <a href="returned_stock.php" class="btn btn-outline-secondary btn-sm mb-2 w-100">Returned Orders</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
