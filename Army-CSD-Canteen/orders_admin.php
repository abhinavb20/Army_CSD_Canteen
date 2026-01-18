<?php
session_start();
include 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle accept return action
if (isset($_POST['accept_return'])) {
    $order_id = intval($_POST['order_id']);

    // Fetch returned product details and reason from order
    $order = mysqli_query($conn, "SELECT return_reason FROM orders WHERE id='$order_id'");
    $order_row = mysqli_fetch_assoc($order);
    $return_reason = $order_row['return_reason'];

    $items = [];
    $item_query = mysqli_query($conn, "SELECT product_id, quantity FROM order_items WHERE order_id='$order_id'");
    while ($item = mysqli_fetch_assoc($item_query)) {
        $items[] = $item;
    }

    // Insert each returned item into returned_stock and update product stock
    foreach ($items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];

        // Insert into returned_stock with reason
        mysqli_query($conn, "INSERT INTO returned_stock (product_id, quantity, reason) VALUES ('$product_id', '$quantity', '".mysqli_real_escape_string($conn, $return_reason)."')");

        // Optionally, update product stock
        mysqli_query($conn, "UPDATE products SET stock = stock + $quantity WHERE id = $product_id");
    }

    // Update order status to 'Returned'
    mysqli_query($conn, "UPDATE orders SET status='Returned', return_status='Approved' WHERE id='$order_id'");

    echo "<script>alert('Return accepted and moved to returned stock.');window.location='orders_admin.php';</script>";
}

$query = "
    SELECT 
        orders.id AS order_id,
        orders.order_date,
        orders.total_amount,
        users.name AS customer_name,
        users.ph_no,
        addresses.address_line,
        addresses.city,
        addresses.state,
        addresses.pincode,
        orders.status,
        orders.return_requested,
        orders.return_reason,
        orders.return_status
    FROM orders
    JOIN users ON orders.user_id = users.id
    LEFT JOIN addresses ON orders.address_id = addresses.id
    ORDER BY orders.order_date DESC
";


$result = $conn->query($query);

if (isset($_POST['action']) && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    if ($_POST['action'] === 'accept') {
        // 1. Update order status
        mysqli_query($conn, "UPDATE orders SET return_status='Approved', status='Returned' WHERE id='$order_id'");

        // 2. Fetch returned product details from order_items
        $items = [];
        $item_query = mysqli_query($conn, "SELECT product_id, quantity FROM order_items WHERE order_id='$order_id'");
        while ($item = mysqli_fetch_assoc($item_query)) {
            $items[] = $item;
        }

        // 3. Insert each returned item into returned_stock and update product stock
        foreach ($items as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];

            // Insert into returned_stock table
            mysqli_query($conn, "INSERT INTO returned_stock (product_id, quantity) VALUES ('$product_id', '$quantity')");

            // Update product stock
            mysqli_query($conn, "UPDATE products SET stock = stock + $quantity WHERE id = $product_id");
        }
    } elseif ($_POST['action'] === 'decline') {
        mysqli_query($conn, "UPDATE orders SET return_status='Rejected' WHERE id='$order_id'");
    }
    header("Location: orders_admin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Orders - Admin Panel</title>
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
                <li class="nav-item"><a class="nav-link" href="adminlog.php">Users</a></li>
                <li class="nav-item"><a class="nav-link active" href="orders_admin.php">Orders</a></li>
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
                <a class="nav-link mb-2" href="adminlog.php">Users</a>
                <a class="nav-link active mb-2" href="orders_admin.php">Orders</a>
                <a class="nav-link mb-2" href="returned_stock.php">Returned</a>
                
            </nav>
        </div>
        <!-- Main Content -->
        <div class="col-md-7 py-4">
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">📦 All Orders</h2>
                                    </div>
                <div class="table-responsive">
                <table class="table table-bordered table-hover shadow-sm">
                    <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>Total (₹)</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Invoice</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['order_id'] ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['ph_no']) ?></td>
                                <td>
                    <?= htmlspecialchars($row['address_line']) ?>, <?= htmlspecialchars($row['city']) ?>,
                    <?= htmlspecialchars($row['state']) ?> - <?= $row['pincode'] ?>
                </td>
                                <td>₹<?= number_format($row['total_amount'], 2) ?></td>
                                <td><?= $row['order_date'] ?></td>
                                <td>
                                    <form method="POST" action="update_order_status.php" class="d-inline">
                                        <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                                        <select name="status" class="form-select form-select-sm d-inline w-auto">
                                            <?php
                                            $statuses = ['Processing','Packed','Shipped','Delivered','Cancelled'];
                                            foreach ($statuses as $s)
                                                echo "<option value='$s'".($row['status']==$s ? ' selected' : '').">$s</option>";
                                            ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-success ms-1">Update</button>
                                    </form>
                                </td>
                                <td>
                                    <a href="invoice.php?id=<?= $row['order_id'] ?>" class="btn btn-sm btn-primary" target="_blank">🧾 View Invoice</a>
                                </td>
                            </tr>
                            <?php if (!empty($row['return_requested']) && $row['return_status'] == 'Pending'): ?>
                                <tr>
                                    <td colspan="8" class="bg-light">
                                        <p class="mb-1"><strong>Return Reason:</strong> <?= htmlspecialchars($row['return_reason']) ?></p>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                                            <button type="submit" name="action" value="accept" class="btn btn-success">Accept Return</button>
                                            <button type="submit" name="action" value="decline" class="btn btn-danger ms-2">Decline Return</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php elseif (!empty($row['return_requested'])): ?>
                                <tr>
                                    <td colspan="8" class="bg-light">
                                        <p class="mb-1"><strong>Return Reason:</strong> <?= htmlspecialchars($row['return_reason']) ?></p>
                                        <span class="badge bg-success"><?= htmlspecialchars($row['return_status']) ?></span>
                                    </td>
                                </tr>
                            <?php endif; ?>
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