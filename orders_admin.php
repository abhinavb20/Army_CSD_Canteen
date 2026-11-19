<?php
require_once 'admin_guard.php';

$pageTitle = 'Orders';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$csrf    = $_SESSION['admin_csrf'];
$adminId = (int)$_SESSION['user_id'];

const PER_PAGE = 25;

// Status changes an admin may make from each current status
const NEXT_STATUSES = [
    'placed'     => ['Processing', 'Cancelled'],
    'Processing' => ['Packed', 'Cancelled'],
    'Packed'     => ['Shipped'],
    'Shipped'    => ['Delivered'],
];

const STATUS_CLASS = [
    'placed'     => 't-info',
    'Processing' => 't-info',
    'Packed'     => 't-warn',
    'Shipped'    => 't-warn',
    'Delivered'  => 't-ok',
    'Returned'   => 't-neutral',
    'Cancelled'  => 't-bad',
];

const RETURN_CLASS = [
    'Pending'  => 't-warn',
    'Approved' => 't-ok',
    'Rejected' => 't-bad',
];

// ---------- Helpers ----------
function order_items(mysqli $conn, int $orderId): array {
    $s = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $s->bind_param("i", $orderId);
    $s->execute();
    $items = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $items;
}

// Adds stock to a product and records it in stock_log.
// Must be called inside a transaction.
function add_stock(mysqli $conn, int $productId, int $qty, int $adminId, string $note): void {
    $s = $conn->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
    $s->bind_param("i", $productId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$row) {
        throw new RuntimeException("A product in this order no longer exists.");
    }

    $old    = (int)$row['stock'];
    $new    = $old + $qty;
    $reason = 'returned';

    $s = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $s->bind_param("ii", $new, $productId);
    $s->execute();
    $s->close();

    $s = $conn->prepare(
        "INSERT INTO stock_log (product_id, user_id, change_qty, old_stock, new_stock, reason, note)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $s->bind_param("iiiiiss", $productId, $adminId, $qty, $old, $new, $reason, $note);
    $s->execute();
    $s->close();
}

// Keep the filter, search and page when redirecting back
$filterOptions = ['all', 'returns', 'active', 'delivered', 'returned', 'cancelled'];
$requestedFilter = $_REQUEST['f'] ?? 'all';
$filter = in_array($requestedFilter, $filterOptions, true) ? $requestedFilter : 'all';
$search = trim((string)($_REQUEST['q'] ?? ''));
$page   = max(1, (int)($_REQUEST['p'] ?? 1));
$offset = ($page - 1) * PER_PAGE;

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid request. Please reload the page and try again.');
    }

    $action  = (string)($_POST['action'] ?? '');
    $orderId = (int)($_POST['order_id'] ?? 0);
    $result  = ['ok' => false, 'msg' => 'Invalid request.'];

    if ($orderId > 0 && in_array($action, ['status', 'accept_return', 'decline_return'], true)) {
        $conn->begin_transaction();
        try {
            // Lock the order so two admins can't act on it at the same time
            $s = $conn->prepare(
                "SELECT id, status, return_requested, return_status, return_reason
                 FROM orders WHERE id = ? FOR UPDATE"
            );
            $s->bind_param("i", $orderId);
            $s->execute();
            $order = $s->get_result()->fetch_assoc();
            $s->close();

            if (!$order) {
                throw new RuntimeException("Order not found.");
            }

            $current = (string)$order['status'];

            if ($action === 'status') {
                $new = (string)($_POST['new_status'] ?? '');
                if (!in_array($new, NEXT_STATUSES[$current] ?? [], true)) {
                    throw new RuntimeException("Order #$orderId can't be changed from $current to $new.");
                }

                if ($new === 'Cancelled') {
                    foreach (order_items($conn, $orderId) as $it) {
                        add_stock($conn, (int)$it['product_id'], (int)$it['quantity'], $adminId, "Order #$orderId cancelled");
                    }
                }

                $s = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $s->bind_param("si", $new, $orderId);
                $s->execute();
                $s->close();

                $result = ['ok' => true, 'msg' => "Order #$orderId is now $new."];
            }

            elseif ($action === 'accept_return') {
                if ((int)$order['return_requested'] !== 1
                    || $order['return_status'] !== 'Pending'
                    || $current !== 'Delivered') {
                    throw new RuntimeException("This return can't be accepted right now.");
                }

                $reason = (string)($order['return_reason'] ?? '');
                foreach (order_items($conn, $orderId) as $it) {
                    $pid = (int)$it['product_id'];
                    $qty = (int)$it['quantity'];

                    add_stock($conn, $pid, $qty, $adminId, "Return accepted for order #$orderId");

                    $s = $conn->prepare("INSERT INTO returned_stock (product_id, quantity, reason) VALUES (?, ?, ?)");
                    $s->bind_param("iis", $pid, $qty, $reason);
                    $s->execute();
                    $s->close();
                }

                $s = $conn->prepare("UPDATE orders SET status = 'Returned', return_status = 'Approved' WHERE id = ?");
                $s->bind_param("i", $orderId);
                $s->execute();
                $s->close();

                $result = ['ok' => true, 'msg' => "Return for order #$orderId accepted and stock restored."];
            }

            elseif ($action === 'decline_return') {
                if ((int)$order['return_requested'] !== 1 || $order['return_status'] !== 'Pending') {
                    throw new RuntimeException("This return has already been handled.");
                }

                $s = $conn->prepare("UPDATE orders SET return_status = 'Rejected' WHERE id = ?");
                $s->bind_param("i", $orderId);
                $s->execute();
                $s->close();

                $result = ['ok' => true, 'msg' => "Return for order #$orderId declined."];
            }

            $conn->commit();

        } catch (Throwable $ex) {
            $conn->rollback();
            $result = [
                'ok'  => false,
                'msg' => $ex instanceof RuntimeException
                    ? $ex->getMessage()
                    : 'We could not update this order. Please try again.',
            ];
            if (!($ex instanceof RuntimeException)) {
                error_log('Order action failed: ' . $ex->getMessage());
            }
        }
    }

    $back = http_build_query(['f' => $filter, 'q' => $search, 'p' => $page, 'msg' => $result['msg'], 'ok' => $result['ok'] ? 1 : 0]);
    header("Location: orders_admin.php?" . $back);
    exit;
}

// ---------- Filters and data ----------
$where  = [];
$params = [];
$types  = '';

switch ($filter) {
    case 'returns':
        $where[] = "o.return_requested = 1 AND o.return_status = 'Pending'";
        break;
    case 'active':
        $where[] = "o.status IN ('placed','Processing','Packed','Shipped')";
        break;
    case 'delivered':
        $where[] = "o.status = 'Delivered'";
        break;
    case 'returned':
        $where[] = "o.status = 'Returned'";
        break;
    case 'cancelled':
        $where[] = "o.status = 'Cancelled'";
        break;
}

if ($search !== '') {
    $where[]  = "(o.id = ? OR u.name LIKE ? OR u.ph_no LIKE ?)";
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [ctype_digit($search) ? (int)$search : 0, $like, $like]);
    $types   .= 'iss';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT o.id, o.order_date, o.total_amount, o.status, o.payment_method,
               o.return_requested, o.return_reason, o.return_status,
               COALESCE(u.name, 'Unknown customer') AS customer, u.ph_no,
               a.address_line, a.city, a.state, a.pincode,
               (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM orders o
        LEFT JOIN users u     ON u.id = o.user_id
        LEFT JOIN addresses a ON a.id = o.address_id
        $whereSql
        ORDER BY o.order_date DESC, o.id DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$allParams = array_merge($params, [PER_PAGE + 1, $offset]);
$allTypes  = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$fetched = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$hasNext = count($fetched) > PER_PAGE;
$orders  = array_slice($fetched, 0, PER_PAGE);

// Chip counts
$counts = [
    'all'       => (int)scalar_value($conn, "SELECT COUNT(*) FROM orders"),
    'returns'   => (int)scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE return_requested = 1 AND return_status = 'Pending'"),
    'active'    => (int)scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE status IN ('placed','Processing','Packed','Shipped')"),
    'delivered' => (int)scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE status = 'Delivered'"),
    'returned'  => (int)scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE status = 'Returned'"),
    'cancelled' => (int)scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE status = 'Cancelled'"),
];

$flashMsg = $_GET['msg'] ?? '';
$flashOk  = ($_GET['ok'] ?? '') === '1';

$chips = [
    'all'       => 'All orders',
    'returns'   => 'Return requests',
    'active'    => 'Active',
    'delivered' => 'Delivered',
    'returned'  => 'Returned',
    'cancelled' => 'Cancelled',
];

function query_string(array $overrides): string {
    global $filter, $search, $page;
    return http_build_query(array_merge(['f' => $filter, 'q' => $search, 'p' => $page], $overrides));
}

require_once 'admin_header.php';
?>

<style>
    .panel { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; }
    .toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:16px 20px; border-bottom:1px solid var(--line); }
    .chips { display:flex; gap:6px; flex-wrap:wrap; }
    .chip { font-size:12.5px; font-weight:600; padding:6px 12px; border-radius:999px; border:1px solid var(--line); color:var(--mu); background:#fff; }
    .chip:hover { color:var(--ink); }
    .chip.on { background:var(--g9); border-color:var(--g9); color:#fff; }
    .chip b { font-weight:700; margin-left:4px; }
    .search { display:flex; align-items:center; gap:8px; border:1px solid var(--line); border-radius:9px; padding:0 6px 0 12px; height:38px; min-width:280px; }
    .search i { color:var(--mu); }
    .search input { border:0; outline:0; flex:1; font:inherit; font-size:13.5px; background:none; }
    .search button { border:0; background:var(--g9); color:#fff; border-radius:7px; padding:6px 12px; font-size:12.5px; font-weight:600; }

    .flash { margin:16px 20px 0; border-radius:10px; font-size:13.5px; padding:10px 14px; }
    .flash.ok { background:#e4f5ea; color:var(--ok); }
    .flash.err { background:#fde8e6; color:var(--bad); }

    table { width:100%; border-collapse:collapse; font-size:13.5px; }
    th { text-align:left; color:var(--mu); font-size:12px; font-weight:600; padding:11px 14px; background:var(--bg); border-bottom:1px solid var(--line); white-space:nowrap; }
    td { padding:12px 14px; border-bottom:1px solid var(--line); vertical-align:middle; }
    tbody tr.order-row:hover { background:#fafcfa; }
    tr.ret-row td { background:#fffbf2; border-bottom:1px solid var(--line); }
    tr:last-child td { border-bottom:0; }
    .num { text-align:right; white-space:nowrap; font-weight:600; }
    .oid { font-weight:700; }
    .muted { color:var(--mu); font-size:12px; display:block; }
    .addr { font-size:12.5px; color:#3f4d44; line-height:1.5; max-width:230px; }

    .tag { font-size:11.5px; font-weight:600; padding:4px 10px; border-radius:999px; display:inline-block; white-space:nowrap; }
    .t-ok { background:#e4f5ea; color:var(--ok); }
    .t-warn { background:#fdf3e1; color:var(--warn); }
    .t-bad { background:#fde8e6; color:var(--bad); }
    .t-info { background:#e6f1fb; color:var(--info); }
    .t-neutral { background:#eef1ef; color:var(--mu); }

    .status-form { display:flex; gap:6px; align-items:center; }
    .status-form select { height:34px; border-radius:8px; border:1px solid var(--line); font-size:13px; padding:0 8px; }
    .btn-mini { height:34px; border:0; border-radius:8px; background:var(--g9); color:#fff; font-weight:600; font-size:12.5px; padding:0 12px; }
    .btn-mini:hover { background:var(--g7); }
    .btn-mini:disabled { background:#c9d3cc; }
    .btn-soft { height:34px; border:1px solid #c5d6c9; background:#fff; color:var(--g7); border-radius:8px; font-weight:600; font-size:12.5px; padding:0 12px; display:inline-flex; align-items:center; gap:6px; }
    .btn-soft:hover { background:var(--g1); color:var(--g9); }
    .btn-danger-soft { height:34px; border:1px solid #f1c9c4; background:#fff; color:var(--bad); border-radius:8px; font-weight:600; font-size:12.5px; padding:0 12px; }
    .btn-danger-soft:hover { background:#fde8e6; }

    .ret-box { display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap; }
    .ret-box p { margin:0; font-size:13px; }
    .ret-box strong { font-weight:700; }
    .ret-actions { display:flex; gap:8px; }

    .pager { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-top:1px solid var(--line); font-size:13px; color:var(--mu); }
    .pager a { color:var(--g7); font-weight:600; }
    .pager span.dis { color:#b5c2b9; }
    .empty { text-align:center; color:var(--mu); padding:44px 20px; }
    .empty i { font-size:36px; color:#aab7ad; display:block; margin-bottom:8px; }
</style>

<div class="page-head">
    <div>
        <h1>Orders</h1>
        <p>Track order progress, handle cancellations and process return requests.</p>
    </div>
</div>

<section class="panel">
    <?php if ($flashMsg !== ''): ?>
        <div class="flash <?= $flashOk ? 'ok' : 'err' ?>"><?= e($flashMsg) ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <div class="chips">
            <?php foreach ($chips as $key => $label): ?>
                <a class="chip <?= $filter === $key ? 'on' : '' ?>"
                   href="?<?= e(query_string(['f' => $key, 'p' => 1])) ?>">
                    <?= e($label) ?><b><?= $counts[$key] ?></b>
                </a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="search">
            <input type="hidden" name="f" value="<?= e($filter) ?>">
            <i class="bi bi-search"></i>
            <input type="text" name="q" placeholder="Order ID, customer name or phone" value="<?= e($search) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Delivery address</th>
                <th class="num">Items</th>
                <th class="num">Total</th>
                <th>Status</th>
                <th>Invoice</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($orders)): ?>
            <tr><td colspan="7">
                <div class="empty"><i class="bi bi-inbox"></i>No orders match this view.</div>
            </td></tr>
        <?php endif; ?>

        <?php foreach ($orders as $o):
            $oid     = (int)$o['id'];
            $status  = (string)$o['status'];
            $next    = NEXT_STATUSES[$status] ?? [];
            $pending = (int)$o['return_requested'] === 1 && $o['return_status'] === 'Pending';
            $hasRet  = (int)$o['return_requested'] === 1;
            $pmLabel = ($o['payment_method'] ?? '') === 'cod' ? 'Cash on delivery' : ($o['payment_method'] ?? '');
        ?>
            <tr class="order-row">
                <td>
                    <span class="oid">#<?= $oid ?></span>
                    <span class="muted"><?= e(date('d M Y, h:i A', strtotime($o['order_date']))) ?></span>
                </td>
                <td>
                    <?= e($o['customer']) ?>
                    <span class="muted"><?= e($o['ph_no'] ?? '') ?></span>
                </td>
                <td class="addr">
                    <?php if (!empty($o['address_line'])): ?>
                        <?= e($o['address_line']) ?>, <?= e($o['city']) ?><br>
                        <span class="muted"><?= e($o['state']) ?> – <?= e($o['pincode']) ?></span>
                    <?php else: ?>
                        <span class="muted">No address on file</span>
                    <?php endif; ?>
                </td>
                <td class="num"><?= (int)$o['item_count'] ?></td>
                <td class="num">₹<?= number_format((float)$o['total_amount'], 2) ?>
                    <span class="muted"><?= e($pmLabel) ?></span>
                </td>
                <td>
                    <?php if (!empty($next)): ?>
                        <form method="POST" class="status-form js-status">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="order_id" value="<?= $oid ?>">
                            <input type="hidden" name="f" value="<?= e($filter) ?>">
                            <input type="hidden" name="q" value="<?= e($search) ?>">
                            <input type="hidden" name="p" value="<?= $page ?>">
                            <select name="new_status" aria-label="Change status">
                                <option value="<?= e($status) ?>" selected><?= e($status) ?> (current)</option>
                                <?php foreach ($next as $n): ?>
                                    <option value="<?= e($n) ?>"><?= e($n) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-mini">Update</button>
                        </form>
                    <?php else: ?>
                        <span class="tag <?= STATUS_CLASS[$status] ?? 't-neutral' ?>"><?= e($status) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="invoice.php?id=<?= $oid ?>" target="_blank" class="btn-soft">
                        <i class="bi bi-receipt"></i>Invoice
                    </a>
                </td>
            </tr>

            <?php if ($hasRet): ?>
                <tr class="ret-row">
                    <td colspan="7">
                        <div class="ret-box">
                            <p>
                                <strong>Return request for #<?= $oid ?>:</strong>
                                <?= e($o['return_reason'] ?: 'No reason given') ?>
                                <span class="tag <?= RETURN_CLASS[$o['return_status']] ?? 't-neutral' ?> ms-2"><?= e($o['return_status'] ?: 'Pending') ?></span>
                            </p>

                            <?php if ($pending): ?>
                                <div class="ret-actions">
                                    <form method="POST" class="js-confirm" data-msg="Accept this return? The items will be added back to stock.">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="accept_return">
                                        <input type="hidden" name="order_id" value="<?= $oid ?>">
                                        <input type="hidden" name="f" value="<?= e($filter) ?>">
                                        <input type="hidden" name="q" value="<?= e($search) ?>">
                                        <input type="hidden" name="p" value="<?= $page ?>">
                                        <button type="submit" class="btn-mini"><i class="bi bi-check2 me-1"></i>Accept return</button>
                                    </form>
                                    <form method="POST" class="js-confirm" data-msg="Decline this return request?">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="decline_return">
                                        <input type="hidden" name="order_id" value="<?= $oid ?>">
                                        <input type="hidden" name="f" value="<?= e($filter) ?>">
                                        <input type="hidden" name="q" value="<?= e($search) ?>">
                                        <input type="hidden" name="p" value="<?= $page ?>">
                                        <button type="submit" class="btn-danger-soft"><i class="bi bi-x-lg me-1"></i>Decline</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <div class="pager">
        <?php if ($page > 1): ?>
            <a href="?<?= e(query_string(['p' => $page - 1])) ?>"><i class="bi bi-chevron-left"></i> Previous</a>
        <?php else: ?>
            <span class="dis"><i class="bi bi-chevron-left"></i> Previous</span>
        <?php endif; ?>

        <span>Page <?= $page ?></span>

        <?php if ($hasNext): ?>
            <a href="?<?= e(query_string(['p' => $page + 1])) ?>">Next <i class="bi bi-chevron-right"></i></a>
        <?php else: ?>
            <span class="dis">Next <i class="bi bi-chevron-right"></i></span>
        <?php endif; ?>
    </div>
</section>

<script>
    // Confirm cancellations and return decisions
    document.querySelectorAll('.js-status').forEach(form => {
        form.addEventListener('submit', function (e) {
            const sel = this.querySelector('select[name="new_status"]');
            if (sel.value === this.querySelector('select').options[0].value) {
                e.preventDefault();
                return;
            }
            if (sel.value === 'Cancelled' && !confirm('Cancel this order? The items will be returned to stock.')) {
                e.preventDefault();
                return;
            }
            this.querySelector('button').disabled = true;
        });
    });

    document.querySelectorAll('.js-confirm').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!confirm(this.dataset.msg)) {
                e.preventDefault();
                return;
            }
            this.querySelector('button').disabled = true;
        });
    });
</script>

<?php require_once 'admin_footer.php'; ?>