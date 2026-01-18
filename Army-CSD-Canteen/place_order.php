<?php
include 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$address_id = $_POST['address_id'] ?? null;
$cart = $_SESSION['cart'] ?? [];

if (!$user_id) {
    die("User not logged in.");
}

if (!$address_id) {
    die("Address not selected.");
}

// Validate that the address exists for this user:
$stmt = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $address_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("Invalid address selected.");
}

if (empty($cart)) {
    die("Cart is empty.");
}

// Step 1: Calculate total amount and prepare order items
$total = 0;
$order_items = [];

foreach ($cart as $product_id => $quantity) {
    // If $quantity is an array, extract the actual quantity
    if (is_array($quantity)) {
        $quantity = $quantity['qty'] ?? 1;
    }

    // Fetch product price from DB
    $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) continue; // skip if product not found

    $price = $product['price'];
    $subtotal = $price * $quantity;
    $total += $subtotal;

    $order_items[] = [
        'product_id' => $product_id,
        'quantity' => $quantity,
        'price' => $price
    ];
}

// Step 2: Insert into orders table
$stmt = $conn->prepare("INSERT INTO orders (user_id, address_id, total_amount, order_date) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iid", $user_id, $address_id, $total);

if ($stmt->execute()) {
    $order_id = $stmt->insert_id;

    // Step 3: Insert each item into order_items table
    foreach ($order_items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $price = $item['price'];

        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $item_stmt->bind_param("iiid", $order_id, $product_id, $quantity, $price);
        $item_stmt->execute();
    }

    // Step 4: Clear cart
    unset($_SESSION['cart']);

    
} else {
    echo "❌ Failed to place order: " . $stmt->error;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center mb-4">Order Confirmation</h2>
        <div class="alert alert-success text-center">
            <?php echo "✅ Order placed successfully! Order ID: $order_id"; ?>
        </div>
        <div class="text-center">
            <a href="index.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>     
</html>

<form method="POST" action="place_order.php">
    <select name="address_id" required>
        <?php
        $res = $conn->query("SELECT id, address_line FROM addresses WHERE user_id = ".$_SESSION['user_id']);
        while ($row = $res->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['address_line']}</option>";
        }
        ?>
    </select>
    <button type="submit">Place Order</button>
</form>z
