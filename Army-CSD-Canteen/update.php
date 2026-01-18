<?php
session_start();
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);

    if ($name === '' || $phone === '') {
        header("Location: profile.php?error=empty");
        exit();
    }

    // Update user info
    $stmt = $conn->prepare("UPDATE users SET name = ?, ph_no = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $phone, $user_id);

    if ($stmt->execute()) {
        header("Location: profile.php?success=1");
        exit();
    } else {
        header("Location: profile.php?error=update_failed");
        exit();
    }
} else {
    header("Location: profile.php");
    exit();
}
?>
