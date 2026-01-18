<?php
include 'config.php';
session_start();

if (!isset($_POST['id']) || !isset($_SESSION['user_id'])) {
    die('Invalid request.');
}

$id = intval($_POST['id']);
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE addresses SET name=?, phone=?, pincode=?, locality=?, address_line=?, city=?, state=?, landmark=?, alt_phone=?, address_type=? WHERE id=? AND user_id=?");
$stmt->bind_param(
    "ssssssssssii",
    $_POST['name'],
    $_POST['phone'],
    $_POST['pincode'],
    $_POST['locality'],
    $_POST['address_line'],
    $_POST['city'],
    $_POST['state'],
    $_POST['landmark'],
    $_POST['alt_phone'],
    $_POST['address_type'],
    $id,
    $user_id
);

if ($stmt->execute()) {
    header("Location: manage_addresses.php");
} else {
    echo "Error updating address.";
}
?>
