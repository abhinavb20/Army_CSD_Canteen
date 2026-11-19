<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    $redirect = 'checkout.php';
    if (isset($_GET['id'])) {
        $redirect .= '?id=' . (int)$_GET['id'] . '&quantity=' . max(1, (int)($_GET['quantity'] ?? 1));
    }
    header("Location: login.php?msg=login_required&redirect=" . urlencode($redirect));
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ---------- Buy Now or validate cart ----------
if (isset($_GET['id'])) {
    $buyNowId = (int)$_GET['id'];
    $buyNowQty = max(1, (int)($_GET['quantity'] ?? 1));

    $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $buyNowId);
    $stmt->execute();
    $buyNowProduct = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($buyNowProduct) {
        $buyNowQty = min($buyNowQty, max(1, (int)$buyNowProduct['stock']));
        $_SESSION['checkout_cart'] = [$buyNowId => $buyNowQty];
    } else {
        $_SESSION['checkout_cart'] = [];
    }
} else {
    // Reverting to the cart after Buy Now should use the normal cart.
    unset($_SESSION['checkout_cart']);
}

$cart = isset($_SESSION['checkout_cart']) ? $_SESSION['checkout_cart'] : ($_SESSION['cart'] ?? []);
if (!is_array($cart) || count($cart) === 0) {
    header("Location: cart.php");
    exit;
}

$ids = array_map('intval', array_keys($cart));
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare("SELECT id, name, price, stock FROM products WHERE is_active = 1 AND id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$res = $stmt->get_result();

$products = [];
while ($row = $res->fetch_assoc()) {
    $products[(int)$row['id']] = $row;
}
$stmt->close();

$lines = [];
$total = 0.0;
$problems = [];

foreach ($cart as $pid => $qty) {
    $pid = (int)$pid;
    $qty = max(1, (int)$qty);
    if (!isset($products[$pid])) {
        $problems[] = "A product in your cart is no longer available.";
        continue;
    }
    $p = $products[$pid];
    if ((int)$p['stock'] < $qty) {
        $problems[] = e($p['name']) . " has only " . (int)$p['stock'] . " left in stock.";
        continue;
    }
    $sub = (float)$p['price'] * $qty;
    $total += $sub;
    $lines[] = ['name' => $p['name'], 'qty' => $qty, 'price' => (float)$p['price'], 'sub' => $sub];
}

// ---------- Addresses ----------
$stmt = $conn->prepare("SELECT id, name, address_line, city, state, pincode, phone FROM addresses WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$addresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$selected = (int)($_POST['address_id'] ?? $_GET['address_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green-900: #103d25;
            --green-700: #17623b;
            --green-100: #eaf3ec;
            --accent: #c8e49b;
            --ink: #18251d;
            --muted: #6b7a70;
            --line: #e4ebe5;
            --bg: #f4f6f4;
            --danger: #b42318;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--ink); font-size: 14px; -webkit-font-smoothing: antialiased; }

        .page { max-width: 1080px; margin: 0 auto; padding: 28px 20px 60px; }
        .steps { display: flex; gap: 10px; align-items: center; font-size: 13px; color: var(--muted); margin-bottom: 20px; flex-wrap: wrap; }
        .steps .done { color: var(--green-700); font-weight: 600; }
        .steps .now { color: var(--ink); font-weight: 700; }
        .steps i { color: #b5c2b9; }
        .page h1 { font-size: 24px; font-weight: 800; letter-spacing: -.3px; margin: 0 0 4px; }
        .page .sub { color: var(--muted); margin-bottom: 22px; }

        .layout { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }

        .panel { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 22px; margin-bottom: 20px; }
        .panel h2 { font-size: 16px; font-weight: 700; margin: 0 0 14px; display: flex; justify-content: space-between; align-items: center; }
        .panel h2 a { font-size: 13px; font-weight: 600; color: var(--green-700); text-decoration: none; }

        .addr { position: relative; display: block; border: 1.5px solid var(--line); border-radius: 12px; padding: 16px 16px 16px 46px; margin-bottom: 12px; cursor: pointer; transition: .2s; }
        .addr:hover { border-color: #b9ccbe; }
        .addr input { position: absolute; left: 16px; top: 18px; width: 18px; height: 18px; accent-color: var(--green-700); }
        .addr:has(input:checked) { border-color: var(--green-700); background: var(--green-100); }
        .addr strong { display: block; font-size: 14px; margin-bottom: 4px; }
        .addr .lines { color: #4c5a51; line-height: 1.6; font-size: 13px; }
        .addr .phone { color: var(--muted); font-size: 12.5px; margin-top: 6px; }

        .empty-box { text-align: center; padding: 36px 16px; color: var(--muted); }
        .empty-box i { font-size: 36px; color: #aab7ad; }
        .empty-box p { margin: 10px 0 16px; }

        .btn-primary-x { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 48px; border: 0; border-radius: 10px; background: var(--green-900); color: #fff; font-weight: 700; font-size: 15px; }
        .btn-primary-x:hover { background: var(--green-700); }
        .btn-primary-x:disabled { background: #c9d3cc; }
        .btn-ghost { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #c5d6c9; background: #fff; color: var(--green-700); border-radius: 9px; padding: 9px 16px; font-weight: 600; text-decoration: none; font-size: 13px; }
        .btn-ghost:hover { background: var(--green-100); color: var(--green-900); }

        .summary { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 22px; position: sticky; top: 24px; }
        .summary h2 { font-size: 16px; font-weight: 700; margin: 0 0 14px; }
        .line-item { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; padding: 8px 0; border-bottom: 1px dashed var(--line); }
        .line-item span:first-child { color: var(--ink); }
        .line-item span:last-child { color: var(--muted); white-space: nowrap; }
        .sum-row { display: flex; justify-content: space-between; font-size: 13.5px; margin-top: 10px; color: var(--muted); }
        .sum-row.total { color: var(--ink); font-weight: 800; font-size: 17px; border-top: 1px solid var(--line); padding-top: 12px; margin-top: 14px; }
        .secure { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--muted); margin-top: 16px; }
        .secure i { color: var(--green-700); }

        .alert-soft { border-radius: 10px; font-size: 13px; }

        footer { background: var(--green-900); color: #cfe1d5; padding: 24px 15px; text-align: center; font-size: 12.5px; margin-top: 30px; }

        @media (max-width: 880px) {
            .layout { grid-template-columns: 1fr; }
            .summary { position: static; }
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="page">

        <div class="steps">
            <span class="done"><i class="bi bi-check-circle-fill"></i> Cart</span>
            <i class="bi bi-chevron-right"></i>
            <span class="now">Address</span>
            <i class="bi bi-chevron-right"></i>
            <span>Payment</span>
            <i class="bi bi-chevron-right"></i>
            <span>Confirmation</span>
        </div>

        <h1>Checkout</h1>
        <p class="sub">Choose where we should deliver your order.</p>

        <?php foreach ($problems as $msg): ?>
            <div class="alert alert-warning alert-soft"><i class="bi bi-exclamation-triangle me-1"></i><?= $msg ?></div>
        <?php endforeach; ?>

        <div class="layout">

            <section>
                <div class="panel">
                    <h2>
                        Delivery address
                        <a href="manage_addresses.php"><i class="bi bi-plus-lg"></i> Add new</a>
                    </h2>

                    <?php if (!empty($addresses)): ?>
                        <form method="POST" action="payment_method.php" id="addrForm">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                            <?php foreach ($addresses as $a): ?>
                                <label class="addr">
                                    <input type="radio" name="address_id" value="<?= (int)$a['id'] ?>"
                                           <?= ((int)$a['id'] === $selected) ? 'checked' : '' ?> required>
                                    <strong><?= e($a['name']) ?></strong>
                                    <div class="lines">
                                        <?= e($a['address_line']) ?>, <?= e($a['city']) ?><br>
                                        <?= e($a['state']) ?> – <?= e($a['pincode']) ?>
                                    </div>
                                    <div class="phone"><i class="bi bi-telephone me-1"></i><?= e($a['phone']) ?></div>
                                </label>
                            <?php endforeach; ?>

                            <button type="submit" class="btn-primary-x mt-3" id="payBtn" <?= empty($problems) ? '' : 'disabled' ?>>
                                <i class="bi bi-credit-card"></i>Continue to payment
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="empty-box">
                            <i class="bi bi-geo-alt"></i>
                            <p>You don't have a delivery address yet.</p>
                            <a href="manage_addresses.php" class="btn-ghost"><i class="bi bi-plus-lg"></i>Add an address</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="summary">
                <h2>Order summary</h2>
                <?php foreach ($lines as $l): ?>
                    <div class="line-item">
                        <span><?= e($l['name']) ?> × <?= $l['qty'] ?></span>
                        <span>₹<?= number_format($l['sub'], 2) ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="sum-row"><span>Subtotal</span><span>₹<?= number_format($total, 2) ?></span></div>
                <div class="sum-row"><span>Delivery</span><span>Free</span></div>
                <div class="sum-row total"><span>Total</span><span>₹<?= number_format($total, 2) ?></span></div>

                <div class="secure"><i class="bi bi-lock-fill"></i>Secure checkout. You won't be charged until you confirm payment.</div>
            </aside>

        </div>

        <div class="mt-4">
            <a href="cart.php" class="btn-ghost"><i class="bi bi-arrow-left"></i>Back to cart</a>
        </div>

    </main>

    <footer>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>