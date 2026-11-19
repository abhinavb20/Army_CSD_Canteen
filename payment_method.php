<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('checkout.php'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Address comes from the checkout form (POST) or from the session
$addrId = (int)($_POST['address_id'] ?? $_SESSION['address_id'] ?? 0);
if ($addrId <= 0) {
    header("Location: checkout.php");
    exit;
}

// Make sure the address belongs to this user
$stmt = $conn->prepare("SELECT id, name, address_line, city, state, pincode, phone FROM addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $addrId, $user_id);
$stmt->execute();
$address = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$address) {
    header("Location: checkout.php");
    exit;
}
$_SESSION['address_id'] = $addrId;

// Cart total, recalculated from the database
$cart = isset($_SESSION['checkout_cart']) ? $_SESSION['checkout_cart'] : ($_SESSION['cart'] ?? []);
if (empty($cart)) {
    header("Location: cart.php");
    exit;
}

$ids = array_map('intval', array_keys($cart));
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare("SELECT id, price, stock FROM products WHERE is_active = 1 AND id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$res = $stmt->get_result();
$products = [];
while ($row = $res->fetch_assoc()) {
    $products[(int)$row['id']] = $row;
}
$stmt->close();

$total = 0.0;
$itemCount = 0;
foreach ($cart as $pid => $qty) {
    $pid = (int)$pid;
    $qty = max(1, (int)$qty);
    if (!isset($products[$pid]) || (int)$products[$pid]['stock'] < $qty) {
        header("Location: cart.php");
        exit;
    }
    $total += (float)$products[$pid]['price'] * $qty;
    $itemCount += $qty;
}

// Online payment stays off until a gateway is connected
$onlineEnabled = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --green-900:#103d25; --green-700:#17623b; --green-100:#eaf3ec; --ink:#18251d; --muted:#6b7a70; --line:#e4ebe5; --bg:#f4f6f4; }
        * { box-sizing: border-box; }
        body { font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); font-size:14px; -webkit-font-smoothing:antialiased; }
        .page { max-width:1080px; margin:0 auto; padding:28px 20px 60px; }
        .steps { display:flex; gap:10px; align-items:center; font-size:13px; color:var(--muted); margin-bottom:20px; flex-wrap:wrap; }
        .steps .done { color:var(--green-700); font-weight:600; }
        .steps .now { color:var(--ink); font-weight:700; }
        .steps i { color:#b5c2b9; }
        h1 { font-size:24px; font-weight:800; letter-spacing:-.3px; margin:0 0 4px; }
        .sub { color:var(--muted); margin-bottom:22px; }
        .layout { display:grid; grid-template-columns:1fr 340px; gap:24px; align-items:start; }
        .panel { background:#fff; border:1px solid var(--line); border-radius:14px; padding:22px; margin-bottom:20px; }
        .panel h2 { font-size:16px; font-weight:700; margin:0 0 14px; }
        .opt { display:flex; gap:14px; align-items:flex-start; border:1.5px solid var(--line); border-radius:12px; padding:16px; margin-bottom:12px; cursor:pointer; transition:.2s; }
        .opt:hover { border-color:#b9ccbe; }
        .opt.disabled { opacity:.55; cursor:not-allowed; background:#fafbfa; }
        .opt input { margin-top:3px; width:18px; height:18px; accent-color:var(--green-700); flex-shrink:0; }
        .opt:has(input:checked) { border-color:var(--green-700); background:var(--green-100); }
        .opt strong { display:block; font-size:14px; }
        .opt small { color:var(--muted); font-size:12.5px; }
        .opt .ic { font-size:22px; color:var(--green-700); margin-left:auto; }
        .addr-box { background:var(--bg); border-radius:10px; padding:14px 16px; font-size:13px; line-height:1.6; color:#3f4d44; }
        .addr-box strong { color:var(--ink); }
        .btn-pay { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; height:48px; border:0; border-radius:10px; background:var(--green-900); color:#fff; font-weight:700; font-size:15px; margin-top:6px; }
        .btn-pay:hover { background:var(--green-700); }
        .btn-pay:disabled { background:#c9d3cc; }
        .summary { background:#fff; border:1px solid var(--line); border-radius:14px; padding:22px; position:sticky; top:24px; }
        .summary h2 { font-size:16px; font-weight:700; margin:0 0 14px; }
        .sum-row { display:flex; justify-content:space-between; font-size:13.5px; margin-top:10px; color:var(--muted); }
        .sum-row.total { color:var(--ink); font-weight:800; font-size:17px; border-top:1px solid var(--line); padding-top:12px; margin-top:14px; }
        .back { display:inline-flex; align-items:center; gap:6px; color:var(--green-700); font-weight:600; text-decoration:none; font-size:13px; margin-top:6px; }
        footer { background:var(--green-900); color:#cfe1d5; padding:24px 15px; text-align:center; font-size:12.5px; margin-top:30px; }
        @media (max-width:880px) { .layout { grid-template-columns:1fr; } .summary { position:static; } }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="page">

        <div class="steps">
            <span class="done"><i class="bi bi-check-circle-fill"></i> Cart</span>
            <i class="bi bi-chevron-right"></i>
            <span class="done"><i class="bi bi-check-circle-fill"></i> Address</span>
            <i class="bi bi-chevron-right"></i>
            <span class="now">Payment</span>
            <i class="bi bi-chevron-right"></i>
            <span>Confirmation</span>
        </div>

        <h1>Payment</h1>
        <p class="sub">Choose how you would like to pay.</p>

        <div class="layout">
            <section>
                <div class="panel">
                    <h2>Delivering to</h2>
                    <div class="addr-box">
                        <strong><?= e($address['name']) ?></strong><br>
                        <?= e($address['address_line']) ?>, <?= e($address['city']) ?><br>
                        <?= e($address['state']) ?> – <?= e($address['pincode']) ?><br>
                        <i class="bi bi-telephone me-1"></i><?= e($address['phone']) ?>
                    </div>
                    <a href="checkout.php" class="back"><i class="bi bi-arrow-left"></i>Change address</a>
                </div>

                <form method="POST" action="payment_success.php" class="panel" id="payForm">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <h2>Payment method</h2>

                    <label class="opt">
                        <input type="radio" name="payment_method" value="cod" required>
                        <div>
                            <strong>Cash on delivery</strong>
                            <small>Pay in cash when your order is delivered.</small>
                        </div>
                        <i class="bi bi-cash-stack ic"></i>
                    </label>

                    <label class="opt <?= $onlineEnabled ? '' : 'disabled' ?>">
                        <input type="radio" name="payment_method" value="online" <?= $onlineEnabled ? '' : 'disabled' ?>>
                        <div>
                            <strong>Online payment</strong>
                            <small><?= $onlineEnabled ? 'Cards, UPI and net banking.' : 'Not available yet.' ?></small>
                        </div>
                        <i class="bi bi-credit-card ic"></i>
                    </label>

                    <button type="submit" class="btn-pay" id="payBtn">
                        <i class="bi bi-bag-check"></i>Place order
                    </button>
                </form>
            </section>

            <aside class="summary">
                <h2>Order summary</h2>
                <div class="sum-row"><span>Items</span><span><?= $itemCount ?></span></div>
                <div class="sum-row"><span>Delivery</span><span>Free</span></div>
                <div class="sum-row total"><span>Total</span><span>₹<?= number_format($total, 2) ?></span></div>
            </aside>
        </div>
    </main>

    <footer>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent double submission
        document.getElementById('payForm').addEventListener('submit', function () {
            const btn = document.getElementById('payBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Placing order…';
        });
    </script>
</body>
</html>