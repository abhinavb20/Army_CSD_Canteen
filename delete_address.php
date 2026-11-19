<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_addresses.php");
    exit;
}

if (!hash_equals($_SESSION['addr_csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    die("Invalid request. Please reload the page and try again.");
}

$user_id = (int)$_SESSION['user_id'];
$id      = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header("Location: manage_addresses.php?msg=" . urlencode("Invalid address."));
    exit;
}

// Only delete if the address belongs to this user
$stmt = $conn->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

$msg = $deleted ? "Address deleted." : "That address could not be deleted.";
header("Location: manage_addresses.php?msg=" . urlencode($msg));
exit;