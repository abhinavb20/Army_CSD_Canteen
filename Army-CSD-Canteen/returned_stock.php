<?php
session_start();
include 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch returned stock data
$sql = "SELECT * FROM returned_stock ORDER BY return_date DESC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Returned Stock - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            padding: 24px;
            margin-top: 40px;
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
                <a class="nav-link mb-2" href="admin_panel.php">Admin Panel</a>
                <a class="nav-link mb-2" href="product_dashboard.php">Product Dashboard</a>
                <li class="nav-item"><a class="nav-link" href="adminlog.php">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="orders_admin.php">Orders</a></li>
                <li class="nav-item"><a class="nav-link active" href="returned_stock.php">Returned</a></li>
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
                <a class="nav-link mb-2" href="admin_panel.php">Admin Panel </a>
                <a class="nav-link mb-2" href="product_dashboard.php">Product Dashboard</a>
                <a class="nav-link mb-2" href="adminlog.php">Users</a>
                <a class="nav-link mb-2" href="orders_admin.php">Orders</a>
                <a class="nav-link active mb-2" href="returned_stock.php">Returned</a>
            </nav>
        </div>
        <!-- Main Content -->
        <div class="col-md-7 py-4">
            <div class="admin-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">Returned Stock Details</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover shadow-sm">
                        <thead>
                            <tr>
                                <th>Return ID</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <!-- <th>Reason</th> -->
                                <th>Return Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                // Fetch product name using product_id
                                $product_name = '';
                                if (!empty($row['product_id'])) {
                                    $p_res = mysqli_query($conn, "SELECT name FROM products WHERE id = '{$row['product_id']}'");
                                    if ($p_row = mysqli_fetch_assoc($p_res)) {
                                        $product_name = $p_row['name'];
                                    }
                                }
                                echo "<tr>
                                    <td>{$row['id']}</td>
                                    <td>{$product_name}</td>
                                    <td>{$row['quantity']}</td>
                                    <td>{$row['return_date']}</td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center;'>No returned stock found.</td></tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Returned Products Summary Table -->
            <div class="admin-card">
                <h4 class="mb-3">Returned Products Summary</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover shadow-sm">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Total Returned Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $summary_sql = "
                            SELECT 
                                p.id AS product_id,
                                p.name AS product_name,
                                SUM(rs.quantity) AS total_returned
                            FROM returned_stock rs
                            JOIN products p ON rs.product_id = p.id
                            GROUP BY rs.product_id
                            HAVING total_returned > 0
                            ORDER BY total_returned DESC
                        ";
                        $summary_result = mysqli_query($conn, $summary_sql);
                        if (mysqli_num_rows($summary_result) > 0) {
                            while ($row = mysqli_fetch_assoc($summary_result)) {
                                echo "<tr>
                                    <td>{$row['product_id']}</td>
                                    <td>{$row['product_name']}</td>
                                    <td>{$row['total_returned']}</td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' style='text-align:center;'>No returned products found.</td></tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- No right sidebar -->
    </div>
</div>
</body>
</html>
