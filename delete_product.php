<?php
require_once 'admin_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: product_dashboard.php");
    exit;
}

if (!hash_equals($_SESSION['admin_csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    die("Invalid request. Please reload the products page and try again.");
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: product_dashboard.php?msg=" . urlencode("Invalid product."));
    exit;
}

$stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();

$msg = $changed ? "Product removed from the store." : "That product could not be removed.";
header("Location: product_dashboard.php?msg=" . urlencode($msg));
exit;