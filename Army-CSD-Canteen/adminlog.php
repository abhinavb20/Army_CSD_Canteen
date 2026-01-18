<?php
session_start();
include 'config.php';

// Check if logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

// Approve user
if (isset($_GET['approve'])) {
    $uid = intval($_GET['approve']);
    $conn->query("UPDATE users SET is_approved = 1 WHERE id = $uid");
    header("Location: adminlog.php");
    exit;
}

// Delete user (optional)
if (isset($_GET['delete'])) {
    $uid = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id = $uid");
    header("Location: adminlog.php");
    exit;
}

// Fetch all users
$result = $conn->query("SELECT * FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #181a20;
        }
        .navbar-custom {
            background-color: #198754 !important;
            min-height: 66px;
            max-height: 66px;
        }
        .navbar-brand, .navbar-nav .nav-link, .navbar-brand .h1 {
            color: #fff !important;
            font-weight: bold;
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
        .admin-card {
            background: #23272f;
            color: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 18px;
            margin-bottom: 18px;
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
        
        .btn-success, .btn-danger {
            font-weight: 500;
        }
    </style>
</head>
<body>
<!-- header -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <span class="h1 mb-0" style="font-size:2rem;">ARMY CSD CANTEEN</span>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="admin_panel.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="adminlog.php">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="orders_admin.php">Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
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
                <a class="nav-link mb-2" href="admin_panel.php">Admin Panel</a>
                <a class="nav-link mb-2" href="product_dashboard.php">Product Dashboard</a>
                <a class="nav-link active mb-2" href="adminlog.php">Users</a>
                <a class="nav-link mb-2" href="orders_admin.php">Orders</a>
                <a class="nav-link mb-2" href="returned_stock.php">Returned</a>
                            </nav>
        </div>
        <!-- Main Content -->
        <div class="col-md-7 py-4">
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">🛡️ User Management</h2>
                    
                </div>
               <div class="table-responsive">
                <table class="table table-bordered table-hover shadow-sm">
                    <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Army ID</th>
                                <th>Role</th>
                                <th>Approved</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['ph_no']) ?></td>
                                <td><?= htmlspecialchars($row['army_id']) ?></td>
                                <td><?= htmlspecialchars($row['role']) ?></td>
                                <td><?= $row['is_approved'] ? "✅" : "❌" ?></td>
                                <td>
                                    <?php if ($row['is_approved'] == 0): ?>
                                        <a href="?approve=<?= $row['id'] ?>" class="btn btn-sm btn-success mb-1">✅ Approve</a>
                                    <?php endif; ?>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Are you sure?')">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>
</body>
</html>
