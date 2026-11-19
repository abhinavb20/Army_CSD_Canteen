<?php
require_once 'admin_guard.php';

// ---------- KPI figures ----------
$revenueToday   = (float)scalar_value($conn, "SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(order_date) = CURDATE() AND status <> 'Cancelled'");
$ordersToday    = (int)scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURDATE()");
$totalUsers     = (int)scalar_value($conn, "SELECT COUNT(*) FROM users");
$pendingUsers   = (int)scalar_value($conn, "SELECT COUNT(*) FROM users WHERE is_approved = 0");
$lowStockCount  = (int)scalar_value($conn, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= " . LOW_STOCK_LIMIT);
$pendingReturns = (int)scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE return_requested = 1 AND return_status = 'Pending'");

// ---------- Sales for the last 7 days ----------
$salesRaw = rows($conn, "SELECT DATE(order_date) AS d, COALESCE(SUM(total_amount),0) AS t
                         FROM orders
                         WHERE order_date >= CURDATE() - INTERVAL 6 DAY AND status <> 'Cancelled'
                         GROUP BY DATE(order_date)");
$salesMap = [];
foreach ($salesRaw as $r) {
    $salesMap[$r['d']] = (float)$r['t'];
}
$sales = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $sales[] = ['label' => date('D', strtotime($day)), 'value' => $salesMap[$day] ?? 0];
}
$maxSale = max(array_column($sales, 'value')) ?: 1;

// ---------- Lists ----------
$recentOrders = rows($conn, "SELECT o.id, o.total_amount, o.status, o.payment_method, o.order_date, u.name
                             FROM orders o
                             LEFT JOIN users u ON u.id = o.user_id
                             ORDER BY o.order_date DESC
                             LIMIT 6");

$lowStock = rows($conn, "SELECT id, name, stock FROM products
                         WHERE is_active = 1 AND stock <= " . LOW_STOCK_LIMIT . "
                         ORDER BY stock ASC LIMIT 5");

$returns = rows($conn, "SELECT o.id, o.return_status, u.name
                        FROM orders o
                        LEFT JOIN users u ON u.id = o.user_id
                        WHERE o.return_requested = 1
                        ORDER BY o.id DESC LIMIT 5");

// ---------- Display helpers ----------
$adminName = $_SESSION['name'] ?? 'Administrator';
$hour      = (int)date('G');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

function status_class(string $s): string {
    return [
        'placed'     => 't-info',
        'Processing' => 't-info',
        'Packed'     => 't-warn',
        'Shipped'    => 't-warn',
        'Delivered'  => 't-ok',
        'Cancelled'  => 't-bad',
    ][$s] ?? 't-info';
}

function return_class(string $s): string {
    return [
        'Pending'  => 't-warn',
        'Approved' => 't-info',
        'Refunded' => 't-ok',
        'Rejected' => 't-bad',
    ][$s] ?? 't-warn';
}

$nav = [
    ['label' => 'Dashboard',      'href' => 'admin_panel.php',     'icon' => 'M3 12l9-8 9 8M5 10v10h14V10'],
    ['label' => 'Products',       'href' => 'product_dashboard.php','icon' => 'M4 7l8-4 8 4-8 4-8-4zM4 7v10l8 4 8-4V7'],
    ['label' => 'Stock updates',  'href' => 'update_stock.php',    'icon' => 'M3 3h18v4H3zM5 7v14h14V7M9 11h6', 'badge' => $lowStockCount, 'badgeClass' => 'cnt-warn'],
    ['label' => 'Orders',         'href' => 'orders_admin.php',    'icon' => 'M6 2h9l5 5v15H6zM14 2v6h6'],
    ['label' => 'Returns',        'href' => 'returned_stock.php',  'icon' => 'M3 12a9 9 0 1 0 3-6.7M3 4v5h5', 'badge' => $pendingReturns, 'badgeClass' => 'cnt-bad'],
];
$manage = [
    ['label' => 'Users',          'href' => 'adminlog.php',        'icon' => 'M9 8a4 4 0 1 0 0 0M2 21c1-4 4-6 7-6s6 2 7 6M16 4a4 4 0 0 1 0 8'],
];
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --g9:#103d25; --g7:#17623b; --g1:#eaf3ec; --accent:#c8e49b;
            --ink:#18251d; --mu:#6b7a70; --line:#e4ebe5; --bg:#f5f7f5;
            --ok:#1f8a4c; --warn:#b7791f; --bad:#b42318; --info:#1d6fb8;
        }
        * { box-sizing:border-box; }
        body { font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); font-size:14px; display:grid; grid-template-columns:236px 1fr; min-height:100vh; -webkit-font-smoothing:antialiased; margin:0; }
        a { color:inherit; text-decoration:none; }

        /* Sidebar */
        .side { background:#fff; border-right:1px solid var(--line); padding:22px 14px; display:flex; flex-direction:column; position:sticky; top:0; height:100vh; }
        .brand { display:flex; align-items:center; gap:10px; padding:4px 8px 20px; }
        .brand-mark { width:36px; height:36px; border-radius:10px; background:var(--g9); color:#fff; display:grid; place-items:center; font-size:11px; font-weight:800; }
        .brand b { font-size:14px; display:block; }
        .brand small { color:var(--mu); font-size:11.5px; }
        .nav-h { font-size:11px; color:#8a9a90; font-weight:600; letter-spacing:.8px; text-transform:uppercase; padding:16px 10px 6px; }
        .nav-link-x { display:flex; align-items:center; gap:11px; padding:9px 10px; border-radius:9px; color:#3f4d44; font-weight:500; margin-bottom:2px; }
        .nav-link-x i { font-size:17px; }
        .nav-link-x:hover { background:var(--bg); color:var(--ink); }
        .nav-link-x.on { background:var(--g1); color:var(--g9); font-weight:700; }
        .nav-link-x .cnt { margin-left:auto; color:#fff; font-size:11px; font-weight:700; padding:1px 7px; border-radius:999px; }
        .cnt-warn { background:var(--warn); }
        .cnt-bad { background:var(--bad); }
        .side-foot { margin-top:auto; background:var(--bg); border-radius:12px; padding:12px; display:flex; align-items:center; gap:10px; }
        .av { width:36px; height:36px; border-radius:50%; background:var(--g7); color:#fff; display:grid; place-items:center; font-weight:700; flex-shrink:0; }
        .side-foot small { color:var(--mu); font-size:12px; display:block; }
        .logout { margin-left:auto; color:var(--bad); font-size:18px; }

        /* Main */
        main { padding:24px 28px 40px; min-width:0; }
        .head { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:22px; }
        .head h1 { font-size:24px; font-weight:800; letter-spacing:-.4px; margin:0 0 3px; }
        .head p { color:var(--mu); font-size:13px; margin:0; }
        .btn-solid { display:inline-flex; align-items:center; gap:8px; height:40px; padding:0 16px; border-radius:9px; background:var(--g9); color:#fff; font-weight:600; font-size:13px; }
        .btn-solid:hover { background:var(--g7); color:#fff; }

        /* KPI cards */
        .kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:18px; }
        .card-x { background:#fff; border:1px solid var(--line); border-radius:14px; padding:18px; }
        .kic { width:38px; height:38px; border-radius:10px; background:var(--g1); color:var(--g7); display:grid; place-items:center; font-size:18px; margin-bottom:12px; }
        .kic.w { background:#fdf3e1; color:var(--warn); }
        .kic.r { background:#fde8e6; color:var(--bad); }
        .kic.b { background:#e6f1fb; color:var(--info); }
        .kl { color:var(--mu); font-size:12.5px; font-weight:500; }
        .kv { font-size:24px; font-weight:800; margin:4px 0 6px; letter-spacing:-.4px; }
        .kd { font-size:12px; color:var(--mu); }
        .kd.bad { color:var(--bad); font-weight:600; }

        /* Layout */
        .row2 { display:grid; grid-template-columns:1.5fr 1fr; gap:16px; }
        .panel { background:#fff; border:1px solid var(--line); border-radius:14px; padding:20px; margin-bottom:16px; }
        .ph { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
        .ph h2 { font-size:15px; font-weight:700; margin:0; }
        .ph a { color:var(--g7); font-size:12.5px; font-weight:600; }

        /* Sales chart */
        .chart { display:grid; grid-template-columns:repeat(7,1fr); gap:10px; align-items:end; height:170px; }
        .bar-col { display:flex; flex-direction:column; align-items:center; gap:6px; height:100%; justify-content:flex-end; }
        .bar { width:100%; max-width:44px; background:var(--g1); border-radius:8px 8px 4px 4px; position:relative; transition:background .2s; }
        .bar.today { background:var(--g7); }
        .bar:hover { background:var(--g9); }
        .bar-val { font-size:11px; color:var(--mu); white-space:nowrap; }
        .bar-lbl { font-size:12px; color:var(--mu); font-weight:600; }

        /* Tables */
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th { text-align:left; color:var(--mu); font-weight:600; font-size:12px; padding:9px 8px; background:var(--bg); }
        td { padding:11px 8px; border-bottom:1px solid var(--line); vertical-align:middle; }
        tr:last-child td { border-bottom:0; }
        .tag { font-size:11.5px; font-weight:600; padding:4px 10px; border-radius:999px; display:inline-block; }
        .t-ok { background:#e4f5ea; color:var(--ok); }
        .t-warn { background:#fdf3e1; color:var(--warn); }
        .t-bad { background:#fde8e6; color:var(--bad); }
        .t-info { background:#e6f1fb; color:var(--info); }
        .empty { color:var(--mu); text-align:center; padding:18px 0; font-size:13px; }

        /* Stock list */
        .stock { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--line); gap:10px; }
        .stock:last-child { border-bottom:0; }
        .stock b { font-size:13.5px; display:block; }
        .stock small { color:var(--mu); font-size:12px; }
        .qv { font-weight:800; font-size:15px; }
        .qv.zero { color:var(--bad); }
        .qv.low { color:var(--warn); }

        /* Quick actions */
        .quick { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .quick a { border:1px solid var(--line); border-radius:11px; padding:14px; display:flex; flex-direction:column; gap:6px; font-weight:600; font-size:13px; transition:.2s; }
        .quick a i { font-size:20px; color:var(--g7); }
        .quick a small { color:var(--mu); font-weight:400; font-size:11.5px; }
        .quick a:hover { border-color:var(--g7); background:var(--g1); }
        .quick a.hl { background:var(--g9); color:#fff; border-color:var(--g9); }
        .quick a.hl i { color:var(--accent); }
        .quick a.hl small { color:#b9d4c1; }
        .quick a.hl:hover { background:var(--g7); }

        footer { color:var(--mu); font-size:12px; text-align:center; margin-top:10px; }

        @media (max-width:1200px) { .kpis { grid-template-columns:1fr 1fr; } .row2 { grid-template-columns:1fr; } }
        @media (max-width:820px) {
            body { grid-template-columns:1fr; }
            .side { position:static; height:auto; }
            main { padding:18px 16px; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="side">
    <div class="brand">
        <div class="brand-mark">CSD</div>
        <div><b>Army CSD Canteen</b><small>Admin console</small></div>
    </div>

    <nav>
        <?php foreach ($nav as $item): ?>
            <a href="<?= e($item['href']) ?>" class="nav-link-x <?= $current === $item['href'] ? 'on' : '' ?>">
                <i class="bi <?= e($item['icon']) ?>"></i>
                <?= e($item['label']) ?>
                <?php if (!empty($item['badge'])): ?>
                    <span class="cnt <?= e($item['badgeClass']) ?>"><?= (int)$item['badge'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <div class="nav-h">Management</div>
        <?php foreach ($manage as $item): ?>
            <a href="<?= e($item['href']) ?>" class="nav-link-x <?= $current === $item['href'] ? 'on' : '' ?>">
                <i class="bi bi-people"></i>
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="side-foot">
        <div class="av"><?= e(strtoupper(mb_substr($adminName, 0, 1))) ?></div>
        <div><b><?= e($adminName) ?></b><small>Administrator</small></div>
        <form method="POST" action="logout.php" class="m-0 ms-auto">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['admin_csrf']) ?>">
            <button type="submit" class="logout border-0 bg-transparent p-0" title="Log out" aria-label="Log out">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</aside>

<!-- Main -->
<main>

    <div class="head">
        <div>
            <h1><?= e($greeting) ?>, <?= e(explode(' ', $adminName)[0]) ?></h1>
            <p>Overview for <?= e(date('l, d F Y')) ?></p>
        </div>
        <a href="add_product.php" class="btn-solid"><i class="bi bi-plus-lg"></i>Add product</a>
    </div>

    <!-- KPI cards -->
    <section class="kpis">
        <div class="card-x">
            <div class="kic"><i class="bi bi-currency-rupee"></i></div>
            <div class="kl">Revenue today</div>
            <div class="kv">₹<?= number_format($revenueToday, 2) ?></div>
            <div class="kd">Excludes cancelled orders</div>
        </div>

        <div class="card-x">
            <div class="kic b"><i class="bi bi-bag-check"></i></div>
            <div class="kl">Orders today</div>
            <div class="kv"><?= $ordersToday ?></div>
            <div class="kd">Placed since midnight</div>
        </div>

        <div class="card-x">
            <div class="kic"><i class="bi bi-people"></i></div>
            <div class="kl">Registered users</div>
            <div class="kv"><?= number_format($totalUsers) ?></div>
            <div class="kd <?= $pendingUsers > 0 ? 'bad' : '' ?>">
                <?= $pendingUsers > 0 ? $pendingUsers . ' awaiting approval' : 'No pending approvals' ?>
            </div>
        </div>

        <div class="card-x">
            <div class="kic r"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="kl">Low stock items</div>
            <div class="kv" style="color:<?= $lowStockCount > 0 ? 'var(--bad)' : 'inherit' ?>"><?= $lowStockCount ?></div>
            <div class="kd <?= $lowStockCount > 0 ? 'bad' : '' ?>">
                <?= $lowStockCount > 0 ? 'At or below ' . LOW_STOCK_LIMIT . ' units' : 'Stock levels are healthy' ?>
            </div>
        </div>
    </section>

    <div class="row2">

        <!-- Left column -->
        <div>
            <section class="panel">
                <div class="ph"><h2>Sales, last 7 days</h2></div>
                <div class="chart">
                    <?php foreach ($sales as $i => $s):
                        $h = max(4, round(($s['value'] / $maxSale) * 100));
                        $isToday = $i === count($sales) - 1;
                    ?>
                        <div class="bar-col">
                            <span class="bar-val">₹<?= number_format($s['value']) ?></span>
                            <div class="bar <?= $isToday ? 'today' : '' ?>" style="height:<?= $h ?>%"
                                 title="<?= e($s['label']) ?>: ₹<?= number_format($s['value'], 2) ?>"></div>
                            <span class="bar-lbl"><?= e($s['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel">
                <div class="ph"><h2>Recent orders</h2><a href="orders_admin.php">View all orders</a></div>
                <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Order</th><th>Customer</th><th>Amount</th><th>Payment</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="5" class="empty">No orders yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td><a href="orders_admin.php?id=<?= (int)$o['id'] ?>"><b>#<?= (int)$o['id'] ?></b></a></td>
                            <td><?= e($o['name'] ?? 'Unknown') ?></td>
                            <td>₹<?= number_format((float)$o['total_amount'], 2) ?></td>
                            <td><?= ($o['payment_method'] ?? '') === 'cod' ? 'Cash on delivery' : e($o['payment_method'] ?? '—') ?></td>
                            <td><span class="tag <?= status_class((string)$o['status']) ?>"><?= e($o['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </section>

            <section class="panel">
                <div class="ph"><h2>Return requests</h2><a href="returned_stock.php">Open returns</a></div>
                <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Order</th><th>Customer</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="3" class="empty">No return requests.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($returns as $r): $rs = $r['return_status'] ?: 'Pending'; ?>
                        <tr>
                            <td><b>#<?= (int)$r['id'] ?></b></td>
                            <td><?= e($r['name'] ?? 'Unknown') ?></td>
                            <td><span class="tag <?= return_class($rs) ?>"><?= e($rs) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </section>
        </div>

        <!-- Right column -->
        <div>
            <section class="panel">
                <div class="ph"><h2>Low stock</h2><a href="update_stock.php">Update stock</a></div>
                <?php if (empty($lowStock)): ?>
                    <div class="empty">All products are above the low stock level.</div>
                <?php endif; ?>
                <?php foreach ($lowStock as $p):
                    $units = (int)$p['stock'];
                ?>
                    <div class="stock">
                        <div>
                            <b><?= e($p['name']) ?></b>
                            <small>Product #<?= (int)$p['id'] ?></small>
                        </div>
                        <span class="qv <?= $units === 0 ? 'zero' : 'low' ?>"><?= $units ?></span>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="panel">
                <div class="ph"><h2>Quick actions</h2></div>
                <div class="quick">
                    <a href="update_stock.php" class="hl">
                        <i class="bi bi-boxes"></i>Stock updates
                        <small>Adjust quantities</small>
                    </a>
                    <a href="add_product.php">
                        <i class="bi bi-plus-square"></i>Add product
                        <small>New listing</small>
                    </a>
                    <a href="orders_admin.php">
                        <i class="bi bi-receipt"></i>Orders
                        <small>Process and pack</small>
                    </a>
                    <a href="returned_stock.php">
                        <i class="bi bi-arrow-counterclockwise"></i>Returned stock
                        <small>Restock returns</small>
                    </a>
                </div>
            </section>
        </div>
    </div>

    <footer>© <?= date('Y') ?> Army CSD Canteen. Admin console.</footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>