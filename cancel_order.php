<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('my_orders.php'));
    exit;
}

// Only accept the form submission from my_orders.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_orders.php");
    exit;
}

// 1. CSRF check
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    die("Invalid request. Please reload the orders page and try again.");
}

// 2. Maths check. The question is used once, right or wrong.
$expected = $_SESSION['cancel_captcha'] ?? null;
unset($_SESSION['cancel_captcha']);

$given = trim((string)($_POST['captcha'] ?? ''));
$expectedAnswer = (is_int($expected) || (is_string($expected) && ctype_digit($expected)))
    ? (ltrim((string)$expected, '0') ?: '0')
    : null;
$givenAnswer = ctype_digit($given) ? (ltrim($given, '0') ?: '0') : null;

if ($expectedAnswer === null || $givenAnswer === null || $givenAnswer !== $expectedAnswer) {
    header("Location: my_orders.php?msg=" . urlencode("Incorrect answer. The order was not cancelled. Please try again."));
    exit;
}

// 3. Cancel the order
$user_id = (int)$_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    header("Location: my_orders.php?msg=" . urlencode("Invalid order."));
    exit;
}

$conn->begin_transaction();

try {
    // Lock the order row. This also confirms it belongs to this user.
    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $orderId, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new RuntimeException("Order not found.");
    }
    if (!in_array($order['status'], ['placed', 'Processing'], true)) {
        throw new RuntimeException("This order can no longer be cancelled.");
    }

    // Return every item to stock
    $stmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $restore = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
    foreach ($items as $it) {
        $qty = (int)$it['quantity'];
        $pid = (int)$it['product_id'];
        $restore->bind_param("ii", $qty, $pid);
        $restore->execute();
    }
    $restore->close();

    // Mark the order as cancelled
    $stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $orderId, $user_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Throwable $ex) {
    $conn->rollback();
    $msg = $ex instanceof RuntimeException
        ? $ex->getMessage()
        : "We could not cancel your order. Please try again.";
    header("Location: my_orders.php?msg=" . urlencode($msg));
    exit;
}

header("Location: my_orders.php?msg=cancelled");
exit;