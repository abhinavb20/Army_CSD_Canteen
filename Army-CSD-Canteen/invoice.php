<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Order ID not provided.");
}

$order_id = intval($_GET['id']);

// Fetch order details
$order_query = "
    SELECT orders.*, users.name AS customer_name, users.ph_no,
           addresses.address_line, addresses.city, addresses.state, addresses.pincode
    FROM orders
    JOIN users ON orders.user_id = users.id
    LEFT JOIN addresses ON orders.address_id = addresses.id
    WHERE orders.id = $order_id
";
$order_result = $conn->query($order_query);
if ($order_result->num_rows == 0) {
    die("Order not found.");
}
$order = $order_result->fetch_assoc();

// Fetch order items
$items_query = "
    SELECT products.name, products.price, order_items.quantity
    FROM order_items
    JOIN products ON order_items.product_id = products.id
    WHERE order_items.order_id = $order_id
";
$items_result = $conn->query($items_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Invoice #<?= $order_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container mt-4">
    <h2>🧾 Invoice #<?= $order_id ?></h2>
    <hr>
    <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
    <p><strong>Phone:</strong> <?= $order['ph_no'] ?></p>
    <p><strong>Address:</strong>
    <?php
    if (!empty($order['address_line'])) {
        echo htmlspecialchars($order['address_line']) . ', ' .
             htmlspecialchars($order['city']) . ', ' .
             htmlspecialchars($order['state']) . ' - ' .
             htmlspecialchars($order['pincode']);
    } else {
        echo "<span class='text-muted'>[Deleted]</span>";
    }
    ?>
    </p>
    <p><strong>Date:</strong> <?= $order['order_date'] ?></p>
    
    <table class="table table-bordered mt-4">
        <thead class="table-secondary">
            <tr>
                <th>Item</th>
                <th>Price (₹)</th>
                <th>Qty</th>
                <th>Subtotal (₹)</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $total = 0;
        while ($item = $items_result->fetch_assoc()):
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;
        ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td>₹<?= number_format($item['price'], 2) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>₹<?= number_format($subtotal, 2) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr
                class="table-dark">
                <td colspan="3" class="text-end"><strong>Total</strong></td>
                <td><strong>₹<?= number_format($total, 2) ?></strong></td>
            </tr>
        </tfoot>
    </table>
    <div class="text-center mt-4">
        <button class="btn btn-primary" onclick="window.print()">🖨️ Print Invoice</button>
        <a href="orders_admin.php" class="btn btn-secondary">⬅️ Back to Orders</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

