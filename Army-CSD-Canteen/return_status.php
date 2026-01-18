<?php
include 'config.php';
$order_id = $_POST['order_id'];
$return_status = $_POST['return_status'];

$stmt = $conn->prepare("UPDATE orders SET return_status = ? WHERE id = ?");
$stmt->bind_param("si", $return_status, $order_id);
$stmt->execute();

header("Location: orders_admin.php");
