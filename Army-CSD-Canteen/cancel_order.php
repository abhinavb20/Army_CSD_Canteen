<?php

session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['order_id'])) {
    die("Order ID missing.");
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];

// Only allow cancelling if order belongs to user and is still Processing
$stmt = $conn->prepare("UPDATE orders SET status='Cancelled' WHERE id=? AND user_id=? AND status='Processing'");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    header("Location: my_orders.php?msg=cancelled");
    exit;
} else {
    die("Unable to cancel order. It may have already been processed or does not belong to you.");
}
?>