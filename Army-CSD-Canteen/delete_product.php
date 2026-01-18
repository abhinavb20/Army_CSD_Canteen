<?php
include 'config.php';

if (!isset($_GET['id'])) {
    die("Product ID missing");
}

$id = intval($_GET['id']);

// Get product to delete image
$stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    die("Product not found.");
}

// Delete image if exists
if (!empty($product['image']) && file_exists("uploads/" . $product['image'])) {
    unlink("uploads/" . $product['image']);
}

// Replace the DELETE query with an UPDATE
$stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

// Redirect back to admin panel
header("Location: admin_panel.php");
exit;
?>
