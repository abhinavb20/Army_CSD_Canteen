<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('cart.php'));
    exit;
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$_SESSION['cart'] = $_SESSION['cart'] ?? [];

// ---------- ACTIONS (run before loading the cart) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid request. Please reload the page and try again.');
    }

    // Remove one item
    if (isset($_POST['remove'])) {
        unset($_SESSION['cart'][(int)$_POST['remove']]);
        header("Location: cart.php");
        exit;
    }

    // Update quantities
    if (isset($_POST['update_cart']) && isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $id => $q) {
            $id = (int)$id;
            if (array_key_exists($id, $_SESSION['cart'])) {
                $_SESSION['cart'][$id] = max(1, (int)$q);
            }
        }
        header("Location: cart.php");
        exit;
    }
}

// ---------- LOAD PRODUCTS ----------
$products = [];
if (!empty($_SESSION['cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $stmt = $conn->prepare("SELECT id, name, price, stock, image FROM products WHERE is_active = 1 AND id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $products[(int)$row['id']] = $row;
    }
    $stmt->close();
}

// Remove items that no longer exist or are inactive, and clamp quantities to stock
$lines = [];
$total = 0.0;
$hasProblem = false;

foreach ($_SESSION['cart'] as $pid => $qty) {
    $pid = (int)$pid;
    if (!isset($products[$pid])) {
        unset($_SESSION['cart'][$pid]);
        continue;
    }
    $p     = $products[$pid];
    $stock = (int)$p['stock'];

    if ($stock <= 0) {
        $hasProblem = true;
        $lines[] = ['id' => $pid, 'p' => $p, 'qty' => 0, 'sub' => 0, 'oos' => true];
        continue;
    }

    $qty = min(max(1, (int)$qty), $stock);
    $_SESSION['cart'][$pid] = $qty;

    $sub = (float)$p['price'] * $qty;
    $total += $sub;
    $lines[] = ['id' => $pid, 'p' => $p, 'qty' => $qty, 'sub' => $sub, 'oos' => false];
}

$cartCount = array_sum(array_map(fn($l) => $l['oos'] ? 0 : $l['qty'], $lines));
$_SESSION['cart_count'] = $cartCount;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart | Army CSD Canteen</title>

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
            max-width: 1140px;
            margin: 0 auto;
            padding: 28px 20px 60px;
        }

        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .page-head h1 {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.3px;
            margin: 0;
        }

        .page-head p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .link-back {
            color: var(--green-700);
            font-weight: 600;
            text-decoration: none;
            font-size: 13px;
        }

        .link-back:hover {
            text-decoration: underline;
        }

        .layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: start;
        }

        .panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }

        .panel-head {
            padding: 16px 22px;
            border-bottom: 1px solid var(--line);
            font-weight: 700;
            display: flex;
            justify-content: space-between;
        }

        .panel-head span {
            color: var(--muted);
            font-weight: 500;
            font-size: 13px;
        }

        .item {
            display: grid;
            grid-template-columns: 84px 1fr auto;
            gap: 16px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--line);
            align-items: center;
        }

        .item:last-child {
            border-bottom: 0;
        }

        .thumb {
            width: 84px;
            height: 84px;
            background: #f6f8f6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 6px;
        }

        .thumb i {
            color: #9aa69e;
            font-size: 24px;
        }

        .item-name {
            font-weight: 600;
            line-height: 1.4;
            margin: 0 0 4px;
            color: var(--ink);
            text-decoration: none;
        }

        .item-name:hover {
            color: var(--green-700);
        }

        .item-meta {
            font-size: 12px;
            color: var(--muted);
        }

        .item-meta.low {
            color: #b7791f;
        }

        .item-oos {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--danger);
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }

        .remove-btn {
            background: none;
            border: 0;
            color: var(--muted);
            font-size: 12px;
            padding: 0;
            margin-top: 8px;
            text-decoration: underline;
        }

        .remove-btn:hover {
            color: var(--danger);
        }

        .right {
            text-align: right;
            min-width: 150px;
        }

        .unit {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .qty {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            height: 36px;
        }

        .qty button {
            width: 32px;
            height: 100%;
            border: 0;
            background: #fff;
            color: var(--ink);
            font-size: 16px;
        }

        .qty button:hover {
            background: var(--green-100);
        }

        .qty input {
            width: 48px;
            height: 100%;
            border: 0;
            border-left: 1px solid var(--line);
            border-right: 1px solid var(--line);
            text-align: center;
            font-weight: 600;
            -moz-appearance: textfield;
        }

        .qty input::-webkit-outer-spin-button,
        .qty input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .subtotal {
            font-weight: 800;
            font-size: 16px;
            margin-top: 10px;
        }

        .summary {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 22px;
            position: sticky;
            top: 90px;
        }

        .summary h2 {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 16px;
        }

        .sum-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 10px;
            color: var(--muted);
        }

        .sum-row.total {
            color: var(--ink);
            font-weight: 800;
            font-size: 18px;
            border-top: 1px solid var(--line);
            padding-top: 14px;
            margin-top: 14px;
        }

        .btn-checkout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 48px;
            border-radius: 10px;
            background: var(--green-900);
            color: #fff;
            border: 0;
            font-weight: 700;
            margin-top: 18px;
            text-decoration: none;
        }

        .btn-checkout:hover {
            background: var(--green-700);
            color: #fff;
        }

        .btn-checkout.disabled {
            background: #c9d3cc;
            pointer-events: none;
        }

        .btn-update {
            width: 100%;
            height: 42px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid #c5d6c9;
            color: var(--green-700);
            font-weight: 600;
            margin-top: 10px;
        }

        .btn-update:hover {
            background: var(--green-100);
        }

        .note {
            font-size: 12px;
            color: var(--muted);
            margin-top: 12px;
        }

        .warn {
            background: #fff7e6;
            border: 1px solid #f5d89a;
            color: #7a5410;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 12.5px;
            margin-bottom: 14px;
        }

        .empty {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 60px 20px;
            text-align: center;
        }

        .empty i {
            font-size: 44px;
            color: #aab7ad;
        }

        .empty h2 {
            font-size: 18px;
            font-weight: 700;
            margin: 14px 0 6px;
        }

        .empty p {
            color: var(--muted);
            margin-bottom: 18px;
        }

        .btn-shop {
            display: inline-flex;
            background: var(--green-900);
            color: #fff;
            border-radius: 9px;
            padding: 11px 20px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-shop:hover {
            background: var(--green-700);
            color: #fff;
        }

        footer {
            background: var(--green-900);
            color: #cfe1d5;
            padding: 24px 15px;
            text-align: center;
            font-size: 12.5px;
            margin-top: 30px;
        }

        @media (max-width: 880px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .summary {
                position: static;
            }
        }

        @media (max-width: 560px) {
            .item {
                grid-template-columns: 70px 1fr;
            }

            .thumb {
                width: 70px;
                height: 70px;
            }

            .right {
                grid-column: 1 / -1;
                text-align: left;
                display: flex;
                justify-content: space-between;
                align-items: center;
                min-width: 0;
            }

            .unit {
                margin: 0;
            }

            .subtotal {
                margin-top: 0;
            }
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <main class="page">

        <div class="page-head">
            <div>
                <h1>Shopping cart</h1>
                <p><?= $cartCount ?> item<?= $cartCount === 1 ? '' : 's' ?> in your cart</p>
            </div>
            <a href="index.php" class="link-back"><i class="bi bi-arrow-left"></i> Continue shopping</a>
        </div>

        <?php if (empty($lines)): ?>

            <div class="empty">
                <i class="bi bi-bag"></i>
                <h2>Your cart is empty</h2>
                <p>Browse the catalogue to add products.</p>
                <a href="index.php" class="btn-shop">Start shopping</a>
            </div>

        <?php else: ?>

            <div class="layout">

                <!-- Items -->
                <section class="panel">
                    <div class="panel-head">
                        Items <span><?= count($lines) ?> product<?= count($lines) === 1 ? '' : 's' ?></span>
                    </div>

                    <?php foreach ($lines as $l): $p = $l['p']; ?>
                        <div class="item">
                            <div class="thumb">
                                <?php if (!empty($p['image'])): ?>
                                    <img src="uploads/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>">
                                <?php else: ?>
                                    <i class="bi bi-image"></i>
                                <?php endif; ?>
                            </div>

                            <div>
                                <a class="item-name d-block" href="product_details.php?id=<?= $l['id'] ?>"><?= e($p['name']) ?></a>
                                <?php if ($l['oos']): ?>
                                    <div class="item-oos"><i class="bi bi-x-circle-fill"></i>Out of stock. Remove to continue.</div>
                                <?php else: ?>
                                    <div class="item-meta <?= (int)$p['stock'] <= 5 ? 'low' : '' ?>">
                                        ₹<?= number_format((float)$p['price'], 2) ?>
                                        · <?= (int)$p['stock'] <= 5 ? 'Only ' . (int)$p['stock'] . ' left' : 'In stock' ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="cart.php" class="d-inline">
                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                    <button type="submit" name="remove" value="<?= $l['id'] ?>" class="remove-btn">Remove</button>
                                </form>
                            </div>

                            <div class="right">
                                <?php if (!$l['oos']): ?>
                                    <div class="unit">₹<?= number_format((float)$p['price'], 2) ?> each</div>
                                <?php endif; ?>
                                <div>
                                    <?php if (!$l['oos']): ?>
                                        <div class="qty" data-max="<?= (int)$p['stock'] ?>">
                                            <button type="button" class="dec" aria-label="Decrease">−</button>
                                            <input type="number" class="qv" value="<?= $l['qty'] ?>" min="1" max="<?= (int)$p['stock'] ?>" aria-label="Quantity" readonly>
                                            <button type="button" class="inc" aria-label="Increase">+</button>
                                        </div>
                                        <div class="subtotal" data-price="<?= (float)$p['price'] ?>">₹<?= number_format($l['sub'], 2) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <!-- Summary -->
                <aside class="summary">
                    <h2>Order summary</h2>

                    <?php if ($hasProblem): ?>
                        <div class="warn"><i class="bi bi-exclamation-triangle me-1"></i>Some items are out of stock. Remove them to check out.</div>
                    <?php endif; ?>

                    <div class="sum-row"><span>Items</span><span id="sumCount"><?= $cartCount ?></span></div>
                    <div class="sum-row"><span>Subtotal</span><span id="sumSub">₹<?= number_format($total, 2) ?></span></div>
                    <div class="sum-row"><span>Delivery</span><span>Calculated at checkout</span></div>
                    <div class="sum-row total"><span>Total</span><span id="sumTotal">₹<?= number_format($total, 2) ?></span></div>

                    <form method="POST" action="cart.php" id="updateForm">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="update_cart" value="1">
                        <div id="qtyInputs"></div>
                        <button type="submit" class="btn-update" id="updateBtn" disabled>Save quantities</button>
                    </form>

                    <a href="checkout.php" class="btn-checkout <?= ($hasProblem || $total <= 0) ? 'disabled' : '' ?>">
                        <i class="bi bi-lock"></i>Proceed to checkout
                    </a>
                    <p class="note">Secure checkout. You can review your address before paying.</p>
                </aside>

            </div>

        <?php endif; ?>

    </main>

    <footer>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quantity steppers update subtotals instantly, then the form saves them on the server
        const fmt = n => '₹' + n.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        const updateBtn = document.getElementById('updateBtn');

        function recalc() {
            let count = 0,
                total = 0;
            const inputs = document.getElementById('qtyInputs');
            inputs.innerHTML = '';

            document.querySelectorAll('.qty').forEach((box, i) => {
                const qv = box.querySelector('.qv');
                const row = box.closest('.item');
                const qty = parseInt(qv.value, 10) || 1;
                const price = parseFloat(row.querySelector('.subtotal').dataset.price);
                const sub = qty * price;

                row.querySelector('.subtotal').textContent = fmt(sub);
                count += qty;
                total += sub;

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'qty[' + row.querySelector('button[name="remove"]').value + ']';
                hidden.value = qty;
                inputs.appendChild(hidden);
            });

            document.getElementById('sumCount').textContent = count;
            document.getElementById('sumSub').textContent = fmt(total);
            document.getElementById('sumTotal').textContent = fmt(total);
            updateBtn.disabled = false;
        }

        document.querySelectorAll('.qty').forEach(box => {
            const max = parseInt(box.dataset.max, 10);
            const qv = box.querySelector('.qv');
            box.querySelector('.dec').addEventListener('click', () => {
                qv.value = Math.max(1, (parseInt(qv.value, 10) || 1) - 1);
                recalc();
            });
            box.querySelector('.inc').addEventListener('click', () => {
                qv.value = Math.min(max, (parseInt(qv.value, 10) || 1) + 1);
                recalc();
            });
        });
    </script>
</body>

</html>