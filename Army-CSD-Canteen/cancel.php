<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];

// Check if the order belongs to the user and is still processing
$order_check = mysqli_query($conn, "SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id AND status = 'Processing'");
if (mysqli_num_rows($order_check) === 0) {
    echo "<script>alert('Invalid order or already processed.'); window.location='my_orders.php';</script>";
    exit;
}

// Get ordered items
$order_items = mysqli_query($conn, "SELECT product_id, quantity FROM order_items WHERE order_id = $order_id");

// Return stock for each product
while ($item = mysqli_fetch_assoc($order_items)) {
    mysqli_query($conn, "UPDATE products SET stock = stock + {$item['quantity']} WHERE id = {$item['product_id']}");
}

// Update order status
mysqli_query($conn, "UPDATE orders SET status = 'Cancelled' WHERE id = $order_id");

echo "<script>alert('Order cancelled and stock updated successfully.'); window.location='my_orders.php';</script>";
?>
