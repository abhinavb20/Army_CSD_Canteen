<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('my_orders.php'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// CSRF token for the cancel form
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Simple maths check for cancelling (answer is always between 1 and 9)
$mathOps = ['+', '-', '×'];
$mathOp  = $mathOps[random_int(0, 2)];

switch ($mathOp) {
    case '+':
        $mathA = random_int(1, 8);
        $mathB = random_int(1, 9 - $mathA);
        $mathAnswer = $mathA + $mathB;
        break;
    case '-':
        $mathA = random_int(2, 9);
        $mathB = random_int(1, $mathA - 1);
        $mathAnswer = $mathA - $mathB;
        break;
    default: // ×
        $mathA = random_int(1, 3);
        $mathB = random_int(1, 3);
        $mathAnswer = $mathA * $mathB;
        break;
}

$_SESSION['cancel_captcha'] = $mathAnswer;
$mathQuestion = $mathA . ' ' . $mathOp . ' ' . $mathB;

// Status stages for the progress bar
$stages = ['Processing', 'Packed', 'Shipped', 'Delivered'];
$statusToStage = [
    'placed'     => 0,
    'Processing' => 0,
    'Packed'     => 1,
    'Shipped'    => 2,
    'Delivered'  => 3,
];

// Message from cancel_order.php
$flash = '';
$flashType = 'alert-info';
if (!empty($_GET['msg'])) {
    if ($_GET['msg'] === 'cancelled') {
        $flash = 'Your order has been cancelled.';
        $flashType = 'alert-success';
    } else {
        $flash = (string)$_GET['msg'];
        $flashType = 'alert-warning';
    }
}

// Orders
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Items for all orders in one query
$itemsByOrder = [];
if (!empty($orders)) {
    $ids = array_map(fn($o) => (int)$o['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "SELECT oi.order_id, oi.quantity, oi.price, p.name
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id IN ($placeholders)"
    );
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $itemsByOrder[(int)$row['order_id']][] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --green-900:#103d25; --green-700:#17623b; --green-100:#eaf3ec; --ink:#18251d; --muted:#6b7a70; --line:#e4ebe5; --bg:#f4f6f4; --danger:#b42318; }
        * { box-sizing: border-box; }
        body { font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); font-size:14px; -webkit-font-smoothing:antialiased; }
        .page { max-width:960px; margin:0 auto; padding:28px 20px 60px; }
        .page-head { margin-bottom:20px; }
        .page-head h1 { font-size:24px; font-weight:800; letter-spacing:-.3px; margin:0 0 4px; }
        .page-head p { color:var(--muted); margin:0; }

        .order { background:#fff; border:1px solid var(--line); border-radius:14px; margin-bottom:16px; overflow:hidden; }
        .order-head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:16px 20px; background:#fbfcfb; border-bottom:1px solid var(--line); }
        .order-meta { display:flex; gap:28px; flex-wrap:wrap; }
        .meta small { display:block; color:var(--muted); font-size:11.5px; text-transform:uppercase; letter-spacing:.5px; font-weight:600; }
        .meta strong { font-size:14px; }
        .badge-status { font-size:12px; font-weight:600; padding:6px 10px; border-radius:999px; background:var(--green-100); color:var(--green-900); }
        .badge-status.cancelled { background:#fde8e6; color:var(--danger); }

        .order-body { padding:18px 20px; }
        .item { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px dashed var(--line); font-size:13.5px; }
        .item:last-child { border-bottom:0; }
        .item span:last-child { color:var(--muted); white-space:nowrap; }

        .track { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; margin:18px 0 6px; }
        .track div { height:6px; border-radius:6px; background:#e3e9e4; }
        .track div.on { background:var(--green-700); }
        .track-labels { display:grid; grid-template-columns:repeat(4,1fr); font-size:12px; color:var(--muted); gap:6px; }
        .track-labels span.on { color:var(--green-900); font-weight:600; }

        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        .btn-soft { border:1px solid #c5d6c9; background:#fff; color:var(--green-700); border-radius:9px; padding:8px 16px; font-size:13px; font-weight:600; text-decoration:none; }
        .btn-soft:hover { background:var(--green-100); color:var(--green-900); }
        .btn-cancel { border:1px solid #f1c9c4; background:#fff; color:var(--danger); border-radius:9px; padding:8px 16px; font-size:13px; font-weight:600; }
        .btn-cancel:hover { background:#fde8e6; color:var(--danger); }
        .return-note { font-size:13px; color:var(--muted); margin-top:12px; }

        .empty { background:#fff; border:1px solid var(--line); border-radius:14px; padding:56px 20px; text-align:center; }
        .empty i { font-size:40px; color:#aab7ad; }
        .empty h2 { font-size:18px; font-weight:700; margin:12px 0 6px; }
        .empty p { color:var(--muted); margin-bottom:18px; }
        .btn-main { display:inline-flex; background:var(--green-900); color:#fff; border-radius:9px; padding:11px 20px; font-weight:600; text-decoration:none; }
        .btn-main:hover { background:var(--green-700); color:#fff; }

        /* Cancel modal */
        .cancel-modal { border:0; border-radius:16px; overflow:hidden; }
        .cancel-icon { width:58px; height:58px; border-radius:50%; background:#fde8e6; color:var(--danger); display:grid; place-items:center; font-size:24px; margin:0 auto 14px; }
        .cancel-modal h5 { font-size:18px; font-weight:800; color:var(--ink); }
        .cancel-total { display:inline-block; background:var(--bg); border-radius:8px; padding:6px 12px; font-size:13px; color:var(--ink); }
        .math-box { background:var(--bg); border-radius:10px; padding:14px 16px; }
        .math-box label { font-weight:600; font-size:13px; margin-bottom:6px; }
        .math-box input { height:44px; border-radius:8px; border-color:var(--line); font-size:15px; font-weight:600; text-align:center; }
        .math-box input:focus { border-color:var(--green-700); box-shadow:0 0 0 .2rem rgba(23,98,59,.15); }
        .btn-cancel-confirm { background:var(--danger); color:#fff; border:0; border-radius:9px; padding:10px 20px; font-weight:600; font-size:14px; }
        .btn-cancel-confirm:hover { background:#8f1c13; color:#fff; }
        .btn-keep { border:1px solid #c5d6c9; background:#fff; color:var(--green-700); border-radius:9px; padding:10px 20px; font-weight:600; font-size:14px; }
        .btn-keep:hover { background:var(--green-100); color:var(--green-900); }

        footer { background:var(--green-900); color:#cfe1d5; padding:24px 15px; text-align:center; font-size:12.5px; margin-top:30px; }

        @media (max-width:600px) {
            .order-meta { gap:16px; }
            .track-labels { font-size:10.5px; }
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="page">

        <?php if ($flash): ?>
            <div class="alert <?= $flashType ?> alert-dismissible fade show" role="alert">
                <?= e($flash) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="page-head">
            <h1>My orders</h1>
            <p><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?></p>
        </div>

        <?php if (empty($orders)): ?>

            <div class="empty">
                <i class="bi bi-box-seam"></i>
                <h2>No orders yet</h2>
                <p>When you place an order, it will appear here.</p>
                <a href="index.php" class="btn-main">Start shopping</a>
            </div>

        <?php else: ?>

            <?php foreach ($orders as $order):
                $oid       = (int)$order['id'];
                $status    = (string)($order['status'] ?? 'placed');
                $isCancel  = strtolower($status) === 'cancelled';
                $stage     = $statusToStage[$status] ?? 0;
                $items     = $itemsByOrder[$oid] ?? [];
                $canCancel = in_array($status, ['placed', 'Processing'], true);
                $canReturn = $status === 'Delivered' && empty($order['return_requested']);
                $returnReq = !empty($order['return_requested']);
                $total     = (float)($order['total_amount'] ?? 0);
                $date      = !empty($order['order_date']) ? date('d M Y, h:i A', strtotime($order['order_date'])) : '';
            ?>
                <article class="order">
                    <div class="order-head">
                        <div class="order-meta">
                            <div class="meta"><small>Order</small><strong>#<?= $oid ?></strong></div>
                            <div class="meta"><small>Placed</small><strong><?= e($date) ?></strong></div>
                            <div class="meta"><small>Total</small><strong>₹<?= number_format($total, 2) ?></strong></div>
                        </div>
                        <span class="badge-status <?= $isCancel ? 'cancelled' : '' ?>">
                            <?= $isCancel ? 'Cancelled' : e(ucfirst(strtolower($status))) ?>
                        </span>
                    </div>

                    <div class="order-body">
                        <?php foreach ($items as $it): ?>
                            <div class="item">
                                <span><?= e($it['name']) ?> × <?= (int)$it['quantity'] ?></span>
                                <span>₹<?= number_format((float)$it['price'] * (int)$it['quantity'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$isCancel): ?>
                            <div class="track" aria-label="Delivery progress">
                                <?php foreach ($stages as $i => $_): ?>
                                    <div class="<?= $i <= $stage ? 'on' : '' ?>"></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="track-labels">
                                <?php foreach ($stages as $i => $label): ?>
                                    <span class="<?= $i <= $stage ? 'on' : '' ?>"><?= e($label) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="actions">
                            <?php if ($canCancel): ?>
                                <button type="button" class="btn-cancel"
                                        data-bs-toggle="modal"
                                        data-bs-target="#cancelModal"
                                        data-order-id="<?= $oid ?>"
                                        data-order-total="<?= number_format($total, 2) ?>">
                                    <i class="bi bi-x-circle me-1"></i>Cancel order
                                </button>
                            <?php endif; ?>

                            <?php if ($canReturn): ?>
                                <a href="return_order.php?order_id=<?= $oid ?>" class="btn-soft">Request return</a>
                            <?php endif; ?>
                        </div>

                        <?php if ($returnReq): ?>
                            <p class="return-note">
                                <i class="bi bi-arrow-return-left me-1"></i>
                                Return requested. Status: <strong><?= e($order['return_status'] ?? 'Pending') ?></strong>
                            </p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>

        <?php endif; ?>
    </main>

    <!-- Cancel order modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content cancel-modal">
                <form method="POST" action="cancel_order.php" id="cancelForm">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="order_id" id="cancelOrderId" value="">

                    <div class="modal-body text-center p-4">
                        <div class="cancel-icon"><i class="bi bi-x-lg"></i></div>
                        <h5 class="mb-2" id="cancelModalLabel">Cancel this order?</h5>
                        <p class="text-muted mb-0">The items will be returned to stock and the order will be marked as cancelled.</p>
                        <p class="cancel-total mt-3 mb-0">
                            Order <strong id="cancelOrderLabel"></strong> · ₹<span id="cancelOrderTotal"></span>
                        </p>

                        <div class="math-box mt-4 text-start">
                            <label for="cancelCaptcha" class="form-label d-block">
                                Quick check: what is <?= e($mathQuestion) ?> ?
                            </label>
                            <input type="number" id="cancelCaptcha" name="captcha" class="form-control"
                                   min="0" max="9" inputmode="numeric" autocomplete="off" required>
                        </div>
                    </div>

                    <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                        <button type="button" class="btn-keep" data-bs-dismiss="modal">Keep order</button>
                        <button type="submit" class="btn-cancel-confirm" id="cancelConfirmBtn">Yes, cancel order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fill the modal with the order that was clicked
        const cancelModal = document.getElementById('cancelModal');
        cancelModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('cancelOrderId').value = btn.dataset.orderId;
            document.getElementById('cancelOrderLabel').textContent = '#' + btn.dataset.orderId;
            document.getElementById('cancelOrderTotal').textContent = btn.dataset.orderTotal;
        });

        // Clear the answer each time the modal opens
        cancelModal.addEventListener('shown.bs.modal', function () {
            document.getElementById('cancelCaptcha').value = '';
            document.getElementById('cancelCaptcha').focus();
        });

        // Prevent double submission
        document.getElementById('cancelForm').addEventListener('submit', function () {
            const btn = document.getElementById('cancelConfirmBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cancelling…';
        });
    </script>
</body>
</html>