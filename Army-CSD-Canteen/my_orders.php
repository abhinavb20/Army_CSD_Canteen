<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's orders
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style2.css" rel="stylesheet">
</head>
<body class="bg-light">
 <nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <a class="h1" href="#">ARMY CSD CANTEEN</a>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="btn btn-danger" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-5">
    <h2>📦 My Orders</h2>
    <?php if ($orders->num_rows == 0): ?>
        <div class="alert alert-info mt-4">You have not placed any orders yet.</div>
    <?php else: ?>
        <div class="table-responsive mt-4">
            <table class="table table-bordered table-hover bg-white">
                <thead class="table-success">
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Items</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($order = $orders->fetch_assoc()): ?>
                    <tr>
                        <td><?= $order['id'] ?></td>
                        <td><?= date('d M Y, h:i A', strtotime($order['order_date'])) ?></td>
                        <td>₹<?= number_format($order['total_amount'], 2) ?></td>
                        <td>
                            <?php
                            // Fetch order items
                            $item_stmt = $conn->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                            $item_stmt->bind_param("i", $order['id']);
                            $item_stmt->execute();
                            $items = $item_stmt->get_result();
                            while ($item = $items->fetch_assoc()):
                            ?>
                                • <?= htmlspecialchars($item['name']) ?> × <?= $item['quantity'] ?> (₹<?= number_format($item['price'], 2) ?>)<br>
                            <?php endwhile; ?>
                            Status: <?= htmlspecialchars($order['status']) ?><br>
                            <div class="progress" style="height: 25px;">
                                <?php
                                $steps = ['Processing','Packed','Shipped','Delivered'];
                                $current = array_search($order['status'], $steps);
                                foreach ($steps as $index => $step):
                                ?>
                                    <div class="progress-bar <?= $index <= $current ? 'bg-success' : 'bg-secondary' ?>"
                                         style="width:25%;"><?= $step ?></div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($order['status'] === 'Processing') { ?>
                                <a href="cancel_order.php?order_id=<?= $order['id'] ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to cancel this order?')">
                                   Cancel Order
                                </a>
                            <?php } ?>
                            <?php if ($order['status'] === 'Delivered' && empty($order['return_requested'])): ?>
                                <a href="return_order.php?order_id=<?= $order['id'] ?>" class="btn btn-danger btn-sm mt-2">
                                    Request Return
                                </a>
                            <?php elseif (!empty($order['return_requested'])): ?>
                                <p class="text-info mt-2">Return Requested – Status: <strong><?= $order['return_status'] ?? 'Pending' ?></strong></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>