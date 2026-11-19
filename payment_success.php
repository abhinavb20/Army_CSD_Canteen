<?php
session_start();
include 'config.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function show_error(string $msg): void {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Order not placed</title>'
       . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>'
       . '<body class="bg-light"><div class="container py-5" style="max-width:520px">'
       . '<div class="card p-4 text-center"><h4 class="text-danger mb-2">Order not placed</h4>'
       . '<p class="text-muted">' . e($msg) . '</p>'
       . '<a href="cart.php" class="btn btn-success">Back to cart</a></div></div></body></html>';
    exit;
}

// Only accept form submissions. A refresh or direct visit never places an order.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    show_error('Your session has expired. Please go back and try again.');
}

$user_id = (int)$_SESSION['user_id'];
$addrId  = (int)($_SESSION['address_id'] ?? 0);
$cart    = isset($_SESSION['checkout_cart']) ? $_SESSION['checkout_cart'] : ($_SESSION['cart'] ?? []);
$method  = $_POST['payment_method'] ?? '';

if (!in_array($method, ['cod'], true)) {
    show_error('The selected payment method is not available.');
}
if (empty($cart)) {
    show_error('Your cart is empty.');
}

// Confirm the address belongs to this user
$stmt = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $addrId, $user_id);
$stmt->execute();
$validAddress = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$validAddress) {
    show_error('The delivery address is invalid. Please choose it again.');
}

// Place the order: stock check, stock update, order and items all succeed or none do
$conn->begin_transaction();

try {
    $total = 0.0;
    $items = [];

    foreach ($cart as $pid => $qty) {
        $pid = (int)$pid;
        $qty = max(1, (int)$qty);

        // Lock the product row so two customers can't buy the last item at once
        $stmt = $conn->prepare("SELECT price, stock FROM products WHERE id = ? AND is_active = 1 FOR UPDATE");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new RuntimeException("A product in your cart is no longer available.");
        }
        if ((int)$product['stock'] < $qty) {
            throw new RuntimeException("Not enough stock for one of your products.");
        }

        $price  = (float)$product['price'];
        $total += $price * $qty;
        $items[] = [$pid, $qty, $price];

        $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->bind_param("ii", $qty, $pid);
        $stmt->execute();
        $stmt->close();
    }

    $status = 'placed';
    $stmt = $conn->prepare("INSERT INTO orders (user_id, address_id, total_amount, payment_method, status, order_date) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iidss", $user_id, $addrId, $total, $method, $status);
    $stmt->execute();
    $orderId = (int)$stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($items as [$pid, $qty, $price]) {
        $stmt->bind_param("iiid", $orderId, $pid, $qty, $price);
        $stmt->execute();
    }
    $stmt->close();

    $conn->commit();
} catch (Throwable $ex) {
    $conn->rollback();
    show_error($ex instanceof RuntimeException ? $ex->getMessage() : 'We could not place your order. Please try again.');
}

// Order is saved: clear the checkout cart and checkout data.
if (!empty($_SESSION['checkout_cart'])) {
    unset($_SESSION['checkout_cart'], $_SESSION['address_id']);
} else {
    unset($_SESSION['cart'], $_SESSION['address_id']);
    $_SESSION['cart_count'] = 0;
}

// Redirect so a refresh doesn't repeat the POST
header("Location: order_success.php?id=" . $orderId);
exit;