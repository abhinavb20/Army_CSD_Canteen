<?php
require_once 'admin_guard.php';

$pageTitle = 'Stock updates';

// CSRF token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['admin_csrf'];

// Reasons: each one sets the direction of the change
$reasons = [
    'received'   => ['label' => 'Stock received',   'sign' => 1],
    'returned'   => ['label' => 'Returned to stock', 'sign' => 1],
    'correction' => ['label' => 'Count correction',  'sign' => 0], // either direction
    'damaged'    => ['label' => 'Damaged / expired', 'sign' => -1],
    'sold_offline' => ['label' => 'Sold offline (counter)', 'sign' => -1],
];

$errors = [];
$selectedId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

// ---------- Save an adjustment ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['admin_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid request. Please reload the page and try again.');
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    $qty       = $_POST['quantity'] ?? '';
    $reason    = $_POST['reason'] ?? '';
    $note      = trim((string)($_POST['note'] ?? ''));
    $adminId   = (int)$_SESSION['user_id'];
    $selectedId = $productId;

    if ($productId <= 0) {
        $errors[] = 'Choose a product.';
    }
    if (!isset($reasons[$reason])) {
        $errors[] = 'Choose a reason for the change.';
    }
    if (!ctype_digit((string)$qty) || (int)$qty < 1 || (int)$qty > 100000) {
        $errors[] = 'Enter a quantity of at least 1.';
    }
    if (mb_strlen($note) > 255) {
        $errors[] = 'Note can be up to 255 characters.';
    }
    if ($reason === 'correction' && $note === '') {
        $errors[] = 'Add a note explaining the count correction.';
    }

    if (empty($errors)) {
        $amount = (int)$qty;

        // Direction: fixed for most reasons. Corrections use a separate sign field.
        if ($reasons[$reason]['sign'] === 0) {
            $dir = ($_POST['direction'] ?? '') === 'remove' ? -1 : 1;
        } else {
            $dir = $reasons[$reason]['sign'];
        }
        $change = $dir * $amount;

        $conn->begin_transaction();
        try {
            // Lock the row so two admins can't update the same product at once
            $s = $conn->prepare("SELECT stock FROM products WHERE id = ? AND is_active = 1 FOR UPDATE");
            $s->bind_param("i", $productId);
            $s->execute();
            $row = $s->get_result()->fetch_assoc();
            $s->close();

            if (!$row) {
                throw new RuntimeException('That product was not found.');
            }

            $oldStock = (int)$row['stock'];
            $newStock = $oldStock + $change;

            if ($newStock < 0) {
                throw new RuntimeException("Cannot remove $amount. Only $oldStock in stock.");
            }

            $s = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $s->bind_param("ii", $newStock, $productId);
            $s->execute();
            $s->close();

            $s = $conn->prepare(
                "INSERT INTO stock_log (product_id, user_id, change_qty, old_stock, new_stock, reason, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $s->bind_param("iiiiiss", $productId, $adminId, $change, $oldStock, $newStock, $reason, $note);
            $s->execute();
            $s->close();

            $conn->commit();

            header("Location: update_stock.php?product_id=" . $productId . "&msg=" . urlencode("Stock updated: $oldStock → $newStock."));
            exit;

        } catch (Throwable $ex) {
            $conn->rollback();
            $errors[] = $ex instanceof RuntimeException
                ? $ex->getMessage()
                : 'We could not update the stock. Please try again.';
            if (!($ex instanceof RuntimeException)) {
                error_log('Stock update failed: ' . $ex->getMessage());
            }
        }
    }
}

// ---------- Data for the page ----------
$products = rows($conn, "SELECT id, name, stock FROM products WHERE is_active = 1 ORDER BY name ASC");

$search = trim((string)($_GET['q'] ?? ''));
$filter = in_array($_GET['filter'] ?? '', ['low', 'out'], true) ? $_GET['filter'] : 'all';

$selected = null;
if ($selectedId > 0) {
    foreach ($products as $p) {
        if ((int)$p['id'] === $selectedId) { $selected = $p; break; }
    }
}

// Recent changes for the selected product
$history = [];
if ($selected) {
    $stmt = $conn->prepare(
        "SELECT l.change_qty, l.old_stock, l.new_stock, l.reason, l.note, l.created_at, u.name
         FROM stock_log l
         LEFT JOIN users u ON u.id = l.user_id
         WHERE l.product_id = ?
         ORDER BY l.id DESC LIMIT 10"
    );
    $stmt->bind_param("i", $selected['id']);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$msg = $_GET['msg'] ?? '';

require_once 'admin_header.php';
?>

<style>
    .layout { display:grid; grid-template-columns:1fr 380px; gap:18px; align-items:start; }
    .panel { background:#fff; border:1px solid var(--line); border-radius:14px; padding:20px; margin-bottom:18px; }
    .panel h2 { font-size:15px; font-weight:700; margin:0 0 14px; display:flex; align-items:center; gap:8px; }
    .panel h2 i { color:var(--g7); }

    .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
    .search { display:flex; align-items:center; gap:8px; border:1px solid var(--line); border-radius:9px; padding:0 6px 0 12px; height:38px; flex:1; min-width:200px; }
    .search i { color:var(--mu); }
    .search input { border:0; outline:0; flex:1; font:inherit; font-size:13.5px; background:none; }
    .search button { border:0; background:var(--g9); color:#fff; border-radius:7px; padding:6px 12px; font-size:12.5px; font-weight:600; }
    .chips { display:flex; gap:6px; flex-wrap:wrap; }
    .chip { font-size:12.5px; font-weight:600; padding:6px 12px; border-radius:999px; border:1px solid var(--line); color:var(--mu); background:#fff; }
    .chip.on { background:var(--g9); border-color:var(--g9); color:#fff; }

    table { width:100%; border-collapse:collapse; font-size:13.5px; }
    th { text-align:left; color:var(--mu); font-size:12px; font-weight:600; padding:10px 12px; background:var(--bg); border-bottom:1px solid var(--line); }
    td { padding:11px 12px; border-bottom:1px solid var(--line); vertical-align:middle; }
    tr:last-child td { border-bottom:0; }
    tr.pick { cursor:pointer; }
    tr.pick:hover { background:#fafcfa; }
    tr.active td { background:var(--g1); }
    .num { font-weight:700; text-align:right; white-space:nowrap; }
    .num.zero { color:var(--bad); }
    .num.low { color:var(--warn); }
    .pname { font-weight:600; }
    .go { color:var(--g7); font-size:12.5px; font-weight:600; }

    .selected-name { font-size:16px; font-weight:800; margin:0; }
    .selected-sub { color:var(--mu); font-size:13px; margin:2px 0 14px; }
    .now { background:var(--bg); border-radius:12px; padding:14px 16px; display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
    .now small { color:var(--mu); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
    .now b { font-size:26px; font-weight:800; display:block; letter-spacing:-.4px; }

    .form-label { font-size:13px; font-weight:600; margin-bottom:6px; }
    .form-control, .form-select { border-radius:9px; border-color:var(--line); padding:10px 12px; font-size:14px; }
    .form-control:focus, .form-select:focus { border-color:var(--g7); box-shadow:0 0 0 .2rem rgba(23,98,59,.15); }
    .seg { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .seg label { border:1.5px solid var(--line); border-radius:10px; padding:10px; text-align:center; font-weight:600; font-size:13px; cursor:pointer; }
    .seg input { position:absolute; opacity:0; pointer-events:none; }
    .seg label:has(input:checked) { border-color:var(--g7); background:var(--g1); color:var(--g9); }

    .btn-save { width:100%; display:inline-flex; justify-content:center; align-items:center; gap:8px; background:var(--g9); color:#fff; border:0; border-radius:10px; padding:12px; font-weight:700; font-size:14px; margin-top:6px; }
    .btn-save:hover { background:var(--g7); }
    .btn-save:disabled { background:#c9d3cc; }

    .hist { list-style:none; margin:0; padding:0; }
    .hist li { display:flex; justify-content:space-between; gap:10px; padding:10px 0; border-bottom:1px solid var(--line); font-size:13px; }
    .hist li:last-child { border-bottom:0; }
    .hist small { color:var(--mu); font-size:12px; display:block; }
    .delta { font-weight:700; white-space:nowrap; }
    .delta.up { color:var(--ok); }
    .delta.down { color:var(--bad); }

    .flash { border-radius:10px; font-size:13.5px; padding:10px 14px; margin-bottom:16px; }
    .flash.ok { background:#e4f5ea; color:var(--ok); }
    .flash.err { background:#fde8e6; color:var(--bad); }
    .empty { color:var(--mu); text-align:center; padding:28px 10px; font-size:13px; }

    @media (max-width:1000px) { .layout { grid-template-columns:1fr; } }
</style>

<div class="page-head">
    <div>
        <h1>Stock updates</h1>
        <p>Add, remove or correct stock. Every change is recorded.</p>
    </div>
    <a href="product_dashboard.php" class="btn-outline"><i class="bi bi-box-seam"></i>All products</a>
</div>

<?php if ($msg !== ''): ?>
    <div class="flash <?= stripos($msg, 'updated') !== false ? 'ok' : 'err' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="flash err">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="layout">

    <!-- Product list -->
    <section class="panel">
        <h2><i class="bi bi-list-ul"></i>Products</h2>

        <div class="toolbar">
            <form method="GET" class="search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Search products" value="<?= e($search) ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Product</th><th class="text-end">In stock</th><th></th></tr>
            </thead>
            <tbody>
            <?php
            $shown = 0;
            foreach ($products as $p):
                $stock = (int)$p['stock'];
                if ($search !== '' && stripos($p['name'], $search) === false) continue;
                if ($filter === 'low' && !($stock > 0 && $stock <= LOW_STOCK_LIMIT)) continue;
                if ($filter === 'out' && $stock !== 0) continue;
                $shown++;
                $isActive = $selected && (int)$selected['id'] === (int)$p['id'];
                $cls = $stock === 0 ? 'zero' : ($stock <= LOW_STOCK_LIMIT ? 'low' : '');
            ?>
                <tr class="pick <?= $isActive ? 'active' : '' ?>" onclick="location.href='?product_id=<?= (int)$p['id'] ?>'">
                    <td class="pname"><?= e($p['name']) ?></td>
                    <td class="num <?= $cls ?>"><?= $stock ?></td>
                    <td class="text-end"><span class="go">Select</span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($shown === 0): ?>
                <tr><td colspan="3" class="empty">No products match your search.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

    <!-- Adjustment panel -->
    <aside>
        <?php if (!$selected): ?>
            <section class="panel">
                <h2><i class="bi bi-hand-index"></i>Choose a product</h2>
                <div class="empty">Select a product on the left to change its stock.</div>
            </section>
        <?php else: ?>
            <section class="panel">
                <h2><i class="bi bi-boxes"></i>Adjust stock</h2>
                <p class="selected-name"><?= e($selected['name']) ?></p>
                <p class="selected-sub">Product #<?= (int)$selected['id'] ?></p>

                <div class="now">
                    <div><small>Current stock</small><b id="currentStock"><?= (int)$selected['stock'] ?></b></div>
                    <div class="text-end"><small>After change</small><b id="afterStock"><?= (int)$selected['stock'] ?></b></div>
                </div>

                <form method="POST" id="adjustForm">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="product_id" value="<?= (int)$selected['id'] ?>">

                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <select id="reason" name="reason" class="form-select" required>
                            <option value="">Select a reason</option>
                            <?php foreach ($reasons as $key => $r): ?>
                                <option value="<?= e($key) ?>"><?= e($r['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="dirBox" style="display:none">
                        <label class="form-label">Direction</label>
                        <div class="seg">
                            <label><input type="radio" name="direction" value="add" checked> Add</label>
                            <label><input type="radio" name="direction" value="remove"> Remove</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" min="1" max="100000" step="1" required>
                    </div>

                    <div class="mb-3">
                        <label for="note" class="form-label">Note <span class="text-muted fw-normal">(optional, required for corrections)</span></label>
                        <input type="text" id="note" name="note" class="form-control" maxlength="255" placeholder="e.g. Delivery note #204">
                    </div>

                    <button type="submit" class="btn-save" id="saveBtn"><i class="bi bi-check2"></i>Save stock change</button>
                </form>
            </section>

            <section class="panel">
                <h2><i class="bi bi-clock-history"></i>Recent changes</h2>
                <?php if (empty($history)): ?>
                    <div class="empty">No changes recorded yet.</div>
                <?php else: ?>
                    <ul class="hist">
                        <?php foreach ($history as $h):
                            $up = (int)$h['change_qty'] > 0;
                        ?>
                            <li>
                                <div>
                                    <?= e($reasons[$h['reason']]['label'] ?? $h['reason']) ?>
                                    <small><?= e($h['name'] ?? 'Admin') ?> · <?= e(date('d M Y, h:i A', strtotime($h['created_at']))) ?></small>
                                    <?php if (!empty($h['note'])): ?><small>“<?= e($h['note']) ?>”</small><?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="delta <?= $up ? 'up' : 'down' ?>"><?= $up ? '+' : '' ?><?= (int)$h['change_qty'] ?></span>
                                    <small><?= (int)$h['old_stock'] ?> → <?= (int)$h['new_stock'] ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </aside>
</div>

<script>
    const reasonSel = document.getElementById('reason');
    const dirBox    = document.getElementById('dirBox');
    const qtyInput  = document.getElementById('quantity');
    const currentEl = document.getElementById('currentStock');
    const afterEl   = document.getElementById('afterStock');
    const signed = {
        'received': 1, 'returned': 1, 'damaged': -1, 'sold_offline': -1, 'correction': 1
    };

    function preview() {
        if (!currentEl || !afterEl) return;
        const current = parseInt(currentEl.textContent, 10) || 0;
        const qty = parseInt(qtyInput.value, 10) || 0;
        let dir = signed[reasonSel.value] ?? 0;
        if (reasonSel.value === 'correction') {
            const removeChecked = document.querySelector('input[name="direction"]:checked')?.value === 'remove';
            dir = removeChecked ? -1 : 1;
        }
        afterEl.textContent = Math.max(0, current + dir * qty);
    }

    if (reasonSel) {
        reasonSel.addEventListener('change', function () {
            dirBox.style.display = this.value === 'correction' ? 'block' : 'none';
            preview();
        });
        qtyInput.addEventListener('input', preview);
        document.querySelectorAll('input[name="direction"]').forEach(r => r.addEventListener('change', preview));

        document.getElementById('adjustForm').addEventListener('submit', function () {
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';
        });
    }
</script>

<?php require_once 'admin_footer.php'; ?>