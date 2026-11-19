<?php
session_start();
include 'config.php';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('my_orders.php'));
    exit;
}

$viewerId = (int)$_SESSION['user_id'];
$isAdmin  = ($_SESSION['role'] ?? '') === 'admin';
$orderId  = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    die('Order ID not provided.');
}

// Order, customer and delivery address
$stmt = $conn->prepare(
    "SELECT o.id, o.user_id, o.order_date, o.total_amount, o.status, o.payment_method,
            u.name AS customer_name, u.ph_no,
            a.name AS recipient, a.phone AS recipient_phone,
            a.address_line, a.locality, a.landmark, a.city, a.state, a.pincode
     FROM orders o
     LEFT JOIN users u     ON u.id = o.user_id
     LEFT JOIN addresses a ON a.id = o.address_id
     WHERE o.id = ?"
);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Customers can only see their own orders. Use the same message for
// "not found" and "not yours" so order IDs can't be probed.
if (!$order || (!$isAdmin && (int)$order['user_id'] !== $viewerId)) {
    http_response_code(404);
    die('Order not found.');
}

// Items at the price the customer paid
$stmt = $conn->prepare(
    "SELECT COALESCE(p.name, 'Product no longer listed') AS name, oi.price, oi.quantity
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?
     ORDER BY oi.id"
);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$subtotal = 0.0;
foreach ($items as $it) {
    $subtotal += (float)$it['price'] * (int)$it['quantity'];
}
$total = (float)$order['total_amount'];

$invoiceNo  = 'CSD-' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
$orderDate  = !empty($order['order_date']) ? date('d M Y, h:i A', strtotime($order['order_date'])) : '—';
$cancelled  = strtolower((string)$order['status']) === 'cancelled';
$payLabel   = ($order['payment_method'] ?? '') === 'cod' ? 'Cash on delivery' : 'Online payment';
$hasAddress = !empty($order['address_line']);

$backLink  = $isAdmin ? 'orders_admin.php' : 'my_orders.php';
$backLabel = $isAdmin ? 'Back to orders' : 'Back to my orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= e($invoiceNo) ?> | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --g9:#103d25; --g7:#17623b; --g1:#eaf3ec; --ink:#18251d; --mu:#6b7a70; --line:#e4ebe5; --bg:#f4f6f4; --bad:#b42318; }
        * { box-sizing:border-box; }
        body { font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); font-size:14px; -webkit-font-smoothing:antialiased; }

        .toolbar { max-width:840px; margin:24px auto 0; display:flex; justify-content:space-between; gap:10px; padding:0 20px; }
        .btn-x { display:inline-flex; align-items:center; gap:6px; height:40px; padding:0 16px; border-radius:9px; font-weight:600; font-size:13px; text-decoration:none; border:1px solid #c5d6c9; background:#fff; color:var(--g7); cursor:pointer; }
        .btn-x:hover { background:var(--g1); color:var(--g9); }
        .btn-x.solid { background:var(--g9); border-color:var(--g9); color:#fff; }
        .btn-x.solid:hover { background:var(--g7); }

        .invoice { position:relative; max-width:840px; margin:18px auto 40px; background:#fff; border:1px solid var(--line); border-radius:16px; padding:40px; overflow:hidden; }
        .inv-head { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; padding-bottom:24px; border-bottom:2px solid var(--g9); }
        .brand { display:flex; align-items:center; gap:12px; }
        .mark { width:44px; height:44px; border-radius:11px; border:1.5px solid var(--g9); color:var(--g9); display:grid; place-items:center; font-size:12px; font-weight:800; }
        .brand b { font-size:16px; display:block; }
        .brand small { color:var(--mu); font-size:12px; }
        .inv-title { text-align:right; }
        .inv-title h1 { font-size:24px; font-weight:800; letter-spacing:-.4px; margin:0 0 4px; color:var(--g9); }
        .inv-title div { color:var(--mu); font-size:13px; }

        .meta { display:grid; grid-template-columns:1fr 1fr 1fr; gap:24px; padding:24px 0; }
        .meta h6 { font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:var(--mu); font-weight:700; margin:0 0 8px; }
        .meta p { margin:0; line-height:1.6; font-size:13.5px; }
        .meta .name { font-weight:700; font-size:14px; }
        .muted { color:var(--mu); }

        .badge-x { display:inline-block; font-size:12px; font-weight:700; padding:5px 11px; border-radius:999px; background:var(--g1); color:var(--g9); }
        .badge-x.bad { background:#fde8e6; color:var(--bad); }

        table { width:100%; border-collapse:collapse; font-size:13.5px; margin-top:4px; }
        th { text-align:left; font-size:11.5px; text-transform:uppercase; letter-spacing:.6px; color:var(--mu); font-weight:700; padding:10px 12px; background:var(--bg); border-bottom:1px solid var(--line); }
        td { padding:12px; border-bottom:1px solid var(--line); }
        .r { text-align:right; white-space:nowrap; }
        .num { font-variant-numeric:tabular-nums; }

        .totals { display:flex; justify-content:flex-end; margin-top:18px; }
        .totals-box { width:280px; }
        .trow { display:flex; justify-content:space-between; padding:6px 0; font-size:13.5px; color:var(--mu); }
        .trow.grand { border-top:2px solid var(--g9); margin-top:8px; padding-top:12px; color:var(--ink); font-weight:800; font-size:17px; }

        .inv-foot { margin-top:34px; padding-top:18px; border-top:1px solid var(--line); display:flex; justify-content:space-between; gap:20px; font-size:12px; color:var(--mu); }
        .inv-foot p { margin:0; }

        .void { position:absolute; top:44%; left:50%; transform:translate(-50%,-50%) rotate(-18deg); font-size:64px; font-weight:800; letter-spacing:6px; color:rgba(180,35,24,.12); border:6px solid rgba(180,35,24,.12); padding:6px 22px; border-radius:14px; pointer-events:none; white-space:nowrap; }

        @media (max-width:640px) {
            .invoice { padding:24px; }
            .inv-head { flex-direction:column; }
            .inv-title { text-align:left; }
            .meta { grid-template-columns:1fr; }
            .inv-foot { flex-direction:column; }
        }

        @page { margin:14mm; }
        @media print {
            body { background:#fff; }
            .no-print { display:none !important; }
            .invoice { border:0; border-radius:0; margin:0; max-width:none; padding:0; }
            .void { color:rgba(180,35,24,.15); border-color:rgba(180,35,24,.15); }
            th { background:#f2f4f2 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        }
    </style>
</head>
<body>

    <div class="toolbar no-print">
        <a href="<?= e($backLink) ?>" class="btn-x"><i class="bi bi-arrow-left"></i><?= e($backLabel) ?></a>
        <button type="button" class="btn-x solid" onclick="window.print()"><i class="bi bi-printer"></i>Print invoice</button>
    </div>

    <main class="invoice">

        <?php if ($cancelled): ?>
            <div class="void">CANCELLED</div>
        <?php endif; ?>

        <header class="inv-head">
            <div class="brand">
                <div class="mark">CSD</div>
                <div>
                    <b>Army CSD Canteen</b>
                    <small>Online store · Authorised personnel</small>
                </div>
            </div>
            <div class="inv-title">
                <h1>INVOICE</h1>
                <div><strong><?= e($invoiceNo) ?></strong></div>
                <div>Placed on <?= e($orderDate) ?></div>
            </div>
        </header>

        <section class="meta">
            <div>
                <h6>Billed to</h6>
                <p class="name"><?= e($order['customer_name'] ?? 'Customer') ?></p>
                <p class="muted">Phone: <?= e($order['ph_no'] ?? '—') ?></p>
            </div>

            <div>
                <h6>Deliver to</h6>
                <?php if ($hasAddress): ?>
                    <p class="name"><?= e($order['recipient'] ?? '') ?></p>
                    <p>
                        <?= e($order['address_line']) ?><?= !empty($order['locality']) ? ', ' . e($order['locality']) : '' ?><br>
                        <?php if (!empty($order['landmark'])): ?>Landmark: <?= e($order['landmark']) ?><br><?php endif; ?>
                        <?= e($order['city']) ?>, <?= e($order['state']) ?> – <?= e($order['pincode']) ?><br>
                        <span class="muted">Phone: <?= e($order['recipient_phone'] ?? '') ?></span>
                    </p>
                <?php else: ?>
                    <p class="muted">Address no longer on file.</p>
                <?php endif; ?>
            </div>

            <div>
                <h6>Order details</h6>
                <p>Status:
                    <span class="badge-x <?= $cancelled ? 'bad' : '' ?>"><?= e(ucfirst(strtolower((string)$order['status']))) ?></span>
                </p>
                <p class="mt-2">Payment: <strong><?= e($payLabel) ?></strong></p>
                <p class="muted">Delivery: Free</p>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th style="width:48%">Item</th>
                    <th class="r">Price</th>
                    <th class="r">Qty</th>
                    <th class="r">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it):
                $line = (float)$it['price'] * (int)$it['quantity'];
            ?>
                <tr>
                    <td><?= e($it['name']) ?></td>
                    <td class="r num">₹<?= number_format((float)$it['price'], 2) ?></td>
                    <td class="r num"><?= (int)$it['quantity'] ?></td>
                    <td class="r num">₹<?= number_format($line, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <tr><td colspan="4" class="muted" style="text-align:center;padding:24px">No items on this order.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-box">
                <div class="trow"><span>Subtotal</span><span class="num">₹<?= number_format($subtotal, 2) ?></span></div>
                <div class="trow"><span>Delivery</span><span>Free</span></div>
                <div class="trow grand"><span>Total</span><span class="num">₹<?= number_format($total, 2) ?></span></div>
            </div>
        </div>

        <footer class="inv-foot">
            <div>
                <p><strong>Thank you for shopping with us.</strong></p>
                <p>This is a computer-generated invoice and does not need a signature.</p>
            </div>
            <div style="text-align:right">
                <p>Army CSD Canteen</p>
                <p>Support: support@csdcanteen.in</p>
            </div>
        </footer>

    </main>

</body>
</html>