<?php
include 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    die("User not logged in.");
}

// Fetch user addresses
$addresses = [];
$stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$addresses = $result->fetch_all(MYSQLI_ASSOC);

// Reduce stock for each ordered product
if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $product_id = intval($product_id);
        $quantity   = intval($quantity);

        mysqli_query($conn, "UPDATE products SET stock = stock - $quantity WHERE id = $product_id AND stock >= $quantity");
    }
    if (mysqli_affected_rows($conn) <= 0) {
        die("Insufficient stock for one or more products.");
    }
} else {
    die("Your cart is empty.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link href="style2.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <a class="h1" href="#">ARMY CSD CANTEEN</a>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item">
                    <a class="btn btn-light" href="javascript:history.back();">Back</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
    <div class="container py-5">
        <h2>Select Delivery Address</h2>

        <form method="POST" action="payment_method.php">
            <?php if (!empty($addresses)): ?>
                <?php foreach ($addresses as $address): ?>
                    <div class="form-check border rounded p-2 mb-2">
                        <input class="form-check-input" type="radio" name="address_id" value="<?= $address['id'] ?>" required>
                        <label class="form-check-label">
                            <strong><?= htmlspecialchars($address['name']) ?></strong><br>
                            <?= htmlspecialchars($address['address_line']) ?>, <?= htmlspecialchars($address['city']) ?><br>
                            <?= htmlspecialchars($address['state']) ?> - <?= htmlspecialchars($address['pincode']) ?><br>
                            Phone: <?= htmlspecialchars($address['phone']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-success">Pay & Place Order</button>
            <?php else: ?>
                <p>No address found.</p>
                <a href="manage_addresses.php" class="btn btn-primary">Add Address</a>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
