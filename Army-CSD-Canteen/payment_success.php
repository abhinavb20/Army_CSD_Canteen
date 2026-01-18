<?php
include 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$address_id = $_SESSION['address_id'] ?? null;
$payment_method = $_POST['payment_method'] ?? null;
$cart = $_SESSION['cart'] ?? [];

if (!$user_id) {
    die("User not logged in.");
}

if (!$address_id) {
    die("Address not selected.");
}

if (!$payment_method) {
    die("Payment method not selected.");
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
    <title>Payment Successful</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            animation: fadeInUp 1s ease;
        }
        h2 {
            color: #27ae60;
            font-size: 2rem;
            margin-bottom: 10px;
            animation: popIn 0.8s;
        }
        .success-icon {
            font-size: 3rem;
            color: #27ae60;
            animation: bounce 1.2s;
            display: inline-block;
            margin-bottom: 10px;
        }
        a {
            display: inline-block;
            padding: 10px 20px;
            background: #27ae60;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 15px;
            transition: background 0.2s, transform 0.2s;
        }
        a:hover {
            background: #219150;
            transform: scale(1.05);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px);}
            to { opacity: 1; transform: translateY(0);}
        }
        @keyframes popIn {
            0% { transform: scale(0.7); opacity: 0;}
            80% { transform: scale(1.1);}
            100% { transform: scale(1); opacity: 1;}
        }
        @keyframes bounce {
            0% { transform: scale(0.5);}
            60% { transform: scale(1.2);}
            80% { transform: scale(0.95);}
            100% { transform: scale(1);}
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="success-icon">✅</div>
        <h2>Payment Successful!</h2>
        <p>Your order has been placed successfully.</p>
        <?php if ($address_id): ?>
            <p>📦 Your order will be delivered to:<br>
            <?php
            $stmt = $conn->prepare("SELECT address_line, city, state, pincode FROM addresses WHERE id = ?");
            $stmt->bind_param("i", $address_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($address = $result->fetch_assoc()) {
                echo htmlspecialchars($address['address_line']) . ", " .
                    htmlspecialchars($address['city']) . ", " .
                    htmlspecialchars($address['state']) . " - " .
                    htmlspecialchars($address['pincode']);
            }
            ?>
            </p>
        <?php endif; ?>
        <a href="my_orders.php">View My Orders</a>
        <a href="index.php" class="btn" style="background:#198754;color:#fff;margin-left:10px;">Go to Home</a>
    </div>
</body>
</html>
