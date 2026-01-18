<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("INSERT INTO addresses 
    (user_id, name, phone, pincode, locality, address_line, city, state, landmark, alt_phone, address_type) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("issssssssss", $user_id, 
    $_POST['name'], $_POST['phone'], $_POST['pincode'], $_POST['locality'],
    $_POST['address_line'], $_POST['city'], $_POST['state'],
    $_POST['landmark'], $_POST['alt_phone'], $_POST['address_type']
);

$stmt->execute();
header("Location: profile.php?msg=Address saved");
?>
