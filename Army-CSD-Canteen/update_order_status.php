<?php
include 'config.php';
$order_id = $_POST['order_id'];
$status = $_POST['status'];
$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $order_id);
$stmt->execute();
header("Location: orders_admin.php");
