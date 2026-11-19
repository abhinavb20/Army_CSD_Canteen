<?php
// Include after admin_guard.php. Set $pageTitle before including.
$pageTitle      = $pageTitle ?? 'Admin';
$current        = basename($_SERVER['PHP_SELF']);
$adminName      = $_SESSION['name'] ?? 'Administrator';
$lowStockCount  = (int)scalar_value($conn, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock > 0 AND stock <= " . LOW_STOCK_LIMIT);
$pendingReturns = (int)scalar_value($conn, "SELECT COUNT(*) FROM orders WHERE return_requested = 1 AND return_status = 'Pending'");

$navItems = [
    ['Dashboard',     'admin_panel.php',      'bi-speedometer2',          0,              ''],
    ['Products',      'product_dashboard.php','bi-box-seam',              0,              ''],
    ['Stock updates', 'update_stock.php',     'bi-boxes',                 $lowStockCount, 'warn'],
    ['Orders',        'orders_admin.php',     'bi-receipt',               0,              ''],
    ['Returns',       'returned_stock.php',   'bi-arrow-counterclockwise',$pendingReturns,'bad'],
];
$manageItems = [
    ['Users', 'adminlog.php', 'bi-people'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Army CSD Canteen Admin</title>

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
        body { margin:0; font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); font-size:14px; -webkit-font-smoothing:antialiased; }
        a { color:inherit; text-decoration:none; }

        .shell { display:grid; grid-template-columns:236px 1fr; min-height:100vh; }

        /* Sidebar */
        .side { background:#fff; border-right:1px solid var(--line); padding:22px 14px; display:flex; flex-direction:column; position:sticky; top:0; height:100vh; }
        .brand { display:flex; align-items:center; gap:10px; padding:4px 8px 20px; }
        .brand-mark { width:36px; height:36px; border-radius:10px; background:var(--g9); color:#fff; display:grid; place-items:center; font-size:11px; font-weight:800; }
        .brand b { font-size:14px; display:block; }
        .brand small { color:var(--mu); font-size:11.5px; }
        .nav-h { font-size:11px; color:#8a9a90; font-weight:600; letter-spacing:.8px; text-transform:uppercase; padding:16px 10px 6px; }
        .nl { display:flex; align-items:center; gap:11px; padding:9px 10px; border-radius:9px; color:#3f4d44; font-weight:500; margin-bottom:2px; }
        .nl i { font-size:17px; }
        .nl:hover { background:var(--bg); color:var(--ink); }
        .nl.on { background:var(--g1); color:var(--g9); font-weight:700; }
        .nl .cnt { margin-left:auto; color:#fff; font-size:11px; font-weight:700; padding:1px 7px; border-radius:999px; }
        .cnt.warn { background:var(--warn); }
        .cnt.bad { background:var(--bad); }
        .side-foot { margin-top:auto; background:var(--bg); border-radius:12px; padding:12px; display:flex; align-items:center; gap:10px; }
        .av { width:36px; height:36px; border-radius:50%; background:var(--g7); color:#fff; display:grid; place-items:center; font-weight:700; flex-shrink:0; }
        .side-foot small { color:var(--mu); font-size:12px; display:block; }
        .logout { margin-left:auto; color:var(--bad); font-size:18px; }

        /* Main area */
        .main { padding:24px 28px 40px; min-width:0; }
        .page-head { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:22px; }
        .page-head h1 { font-size:24px; font-weight:800; letter-spacing:-.4px; margin:0 0 3px; }
        .page-head p { color:var(--mu); font-size:13px; margin:0; }
        .head-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .btn-solid, .btn-outline { display:inline-flex; align-items:center; gap:8px; height:40px; padding:0 16px; border-radius:9px; font-weight:600; font-size:13px; }
        .btn-solid { background:var(--g9); color:#fff; }
        .btn-solid:hover { background:var(--g7); color:#fff; }
        .btn-outline { background:#fff; border:1px solid #c5d6c9; color:var(--g7); }
        .btn-outline:hover { background:var(--g1); color:var(--g9); }

        footer.admin-foot { color:var(--mu); font-size:12px; text-align:center; margin-top:24px; }

        @media (max-width:820px) {
            .shell { grid-template-columns:1fr; }
            .side { position:static; height:auto; }
            .main { padding:18px 16px; }
        }
    </style>
</head>
<body>
<div class="shell">

    <aside class="side">
        <div class="brand">
            <div class="brand-mark">CSD</div>
            <div><b>Army CSD Canteen</b><small>Admin console</small></div>
        </div>

        <nav>
            <?php foreach ($navItems as [$label, $href, $icon, $count, $tone]): ?>
                <a href="<?= e($href) ?>" class="nl <?= $current === $href ? 'on' : '' ?>">
                    <i class="bi <?= e($icon) ?>"></i><?= e($label) ?>
                    <?php if ($count > 0): ?><span class="cnt <?= e($tone) ?>"><?= (int)$count ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>

            <div class="nav-h">Management</div>
            <?php foreach ($manageItems as [$label, $href, $icon]): ?>
                <a href="<?= e($href) ?>" class="nl <?= $current === $href ? 'on' : '' ?>">
                    <i class="bi <?= e($icon) ?>"></i><?= e($label) ?>
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

    <main class="main">