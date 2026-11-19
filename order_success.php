<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$user_id = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);

// Only show orders that belong to this user
$stmt = $conn->prepare(
    "SELECT o.id, o.total_amount, o.status, o.payment_method, o.order_date,
            a.name, a.address_line, a.city, a.state, a.pincode, a.phone
     FROM orders o
     JOIN addresses a ON a.id = o.address_id
     WHERE o.id = ? AND o.user_id = ?"
);
$stmt->bind_param("ii", $orderId, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    die("Order not found.");
}

$stmt = $conn->prepare(
    "SELECT p.name, oi.quantity, oi.price
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?"
);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$payLabel = $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online payment';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order placed | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green-900: #103d25;
            --green-700: #17623b;
            --green-100: #eaf3ec;
            --ink: #18251d;
            --muted: #6b7a70;
            --line: #e4ebe5;
            --bg: #f4f6f4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            max-width: 760px;
            margin: 0 auto;
            padding: 36px 20px 60px;
        }

        .done-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 34px;
            text-align: center;
            margin-bottom: 20px;
        }

        .done-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--green-100);
            color: var(--green-700);
            display: grid;
            place-items: center;
            font-size: 30px;
            margin: 0 auto 14px;
        }

        .done-card h1 {
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 6px;
        }

        .done-card p {
            color: var(--muted);
            margin: 0;
        }

        .order-no {
            display: inline-block;
            margin-top: 14px;
            background: var(--bg);
            border-radius: 8px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 13px;
        }

        .panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .panel h2 {
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 12px;
        }

        .row-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px dashed var(--line);
            font-size: 13.5px;
        }

        .row-item:last-child {
            border-bottom: 0;
        }

        .row-item span:last-child {
            color: var(--muted);
            white-space: nowrap;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            font-size: 16px;
            border-top: 1px solid var(--line);
            padding-top: 12px;
            margin-top: 6px;
        }

        .addr {
            line-height: 1.6;
            color: #3f4d44;
            font-size: 13.5px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-main {
            background: var(--green-900);
            color: #fff;
            border-radius: 9px;
            padding: 11px 20px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-main:hover {
            background: var(--green-700);
            color: #fff;
        }

        .btn-soft {
            background: #fff;
            border: 1px solid #c5d6c9;
            color: var(--green-700);
            border-radius: 9px;
            padding: 11px 20px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-soft:hover {
            background: var(--green-100);
            color: var(--green-900);
        }

        footer {
            background: var(--green-900);
            color: #cfe1d5;
            padding: 24px 15px;
            text-align: center;
            font-size: 12.5px;
            margin-top: 30px;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <main class="page">

        <div class="done-card">
            <div class="done-icon"><i class="bi bi-check-lg"></i></div>
            <h1>Your order has been placed</h1>
            <p>Thank you. We will deliver to the address below.</p>
            <span class="order-no">Order #<?= (int)$order['id'] ?></span>
        </div>

        <section class="panel">
            <h2>Items</h2>
            <?php foreach ($items as $it): ?>
                <div class="row-item">
                    <span><?= e($it['name']) ?> × <?= (int)$it['quantity'] ?></span>
                    <span>₹<?= number_format($it['price'] * $it['quantity'], 2) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="total-row">
                <span>Total</span>
                <span>₹<?= number_format((float)$order['total_amount'], 2) ?></span>
            </div>
            <p class="text-muted small mt-2 mb-0">Payment: <?= e($payLabel) ?></p>
        </section>

        <section class="panel">
            <h2>Delivery address</h2>
            <div class="addr">
                <strong><?= e($order['name']) ?></strong><br>
                <?= e($order['address_line']) ?>, <?= e($order['city']) ?><br>
                <?= e($order['state']) ?> – <?= e($order['pincode']) ?><br>
                <i class="bi bi-telephone me-1"></i><?= e($order['phone']) ?>
            </div>
        </section>

        <div class="actions">
            <a href="my_orders.php" class="btn-main"><i class="bi bi-box-seam me-1"></i>View my orders</a>
            <a href="index.php" class="btn-soft">Continue shopping</a>
        </div>

    </main>

    <footer>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>