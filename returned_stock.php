<?php
require_once 'admin_guard.php';

$pageTitle = 'Returned stock';

const PER_PAGE = 25;
$page   = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * PER_PAGE;
$search = trim((string)($_GET['q'] ?? ''));

// ---------- Returned items (one row per return record) ----------
$where  = '';
$params = [];
$types  = '';

if ($search !== '') {
    $where  = 'WHERE p.name LIKE ? OR rs.reason LIKE ?';
    $like   = '%' . $search . '%';
    $params = [$like, $like];
    $types  = 'ss';
}

$sql = "SELECT rs.id, rs.quantity, rs.reason, rs.return_date,
               COALESCE(p.name, 'Deleted product') AS product_name, p.id AS product_id
        FROM returned_stock rs
        LEFT JOIN products p ON p.id = rs.product_id
        $where
        ORDER BY rs.return_date DESC, rs.id DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$allTypes  = $types . 'ii';
$allParams = array_merge($params, [PER_PAGE + 1, $offset]);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$fetched = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$hasNext = count($fetched) > PER_PAGE;
$records = array_slice($fetched, 0, PER_PAGE);

// ---------- Summary per product ----------
$summary = rows($conn, "
    SELECT p.id AS product_id, p.name AS product_name,
           SUM(rs.quantity) AS total_returned,
           COUNT(*) AS return_count
    FROM returned_stock rs
    JOIN products p ON p.id = rs.product_id
    GROUP BY p.id, p.name
    HAVING total_returned > 0
    ORDER BY total_returned DESC
    LIMIT 10
");

// ---------- Totals for the header cards ----------
$totalUnits   = (int)scalar_value($conn, "SELECT COALESCE(SUM(quantity),0) FROM returned_stock");
$totalRecords = (int)scalar_value($conn, "SELECT COUNT(*) FROM returned_stock");
$productCount = (int)scalar_value($conn, "SELECT COUNT(DISTINCT product_id) FROM returned_stock");

function page_link(int $p): string {
    global $search;
    return '?' . http_build_query(['p' => $p, 'q' => $search]);
}

require_once 'admin_header.php';
?>

<style>
    .kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:18px; }
    .kpi { background:#fff; border:1px solid var(--line); border-radius:14px; padding:18px; }
    .kpi .k-ic { width:36px; height:36px; border-radius:10px; background:var(--g1); color:var(--g7); display:grid; place-items:center; margin-bottom:10px; }
    .kpi .k-l { color:var(--mu); font-size:12.5px; font-weight:500; }
    .kpi .k-v { font-size:22px; font-weight:800; margin-top:3px; letter-spacing:-.3px; }

    .row2 { display:grid; grid-template-columns:1.6fr 1fr; gap:18px; align-items:start; }
    .panel { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:18px; }
    .panel-h { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:16px 20px; border-bottom:1px solid var(--line); }
    .panel-h h2 { font-size:15px; font-weight:700; margin:0; }
    .search { display:flex; align-items:center; gap:8px; border:1px solid var(--line); border-radius:9px; padding:0 6px 0 12px; height:38px; min-width:240px; }
    .search i { color:var(--mu); }
    .search input { border:0; outline:0; flex:1; font:inherit; font-size:13.5px; background:none; }
    .search button { border:0; background:var(--g9); color:#fff; border-radius:7px; padding:6px 12px; font-size:12.5px; font-weight:600; }

    table { width:100%; border-collapse:collapse; font-size:13.5px; }
    th { text-align:left; color:var(--mu); font-size:12px; font-weight:600; padding:11px 14px; background:var(--bg); border-bottom:1px solid var(--line); white-space:nowrap; }
    td { padding:12px 14px; border-bottom:1px solid var(--line); vertical-align:middle; }
    tr:last-child td { border-bottom:0; }
    tbody tr:hover { background:#fafcfa; }
    .num { text-align:right; font-weight:700; white-space:nowrap; }
    .pname { font-weight:600; }
    .muted { color:var(--mu); font-size:12px; }
    .reason { color:#3f4d44; font-size:12.5px; max-width:260px; }
    .pill { font-size:11.5px; font-weight:600; padding:3px 9px; border-radius:999px; background:var(--g1); color:var(--g9); display:inline-block; }
    .empty { text-align:center; color:var(--mu); padding:36px 20px; }
    .empty i { font-size:34px; color:#aab7ad; display:block; margin-bottom:8px; }

    .pager { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-top:1px solid var(--line); font-size:13px; color:var(--mu); }
    .pager a { color:var(--g7); font-weight:600; }
    .pager .dis { color:#b5c2b9; }

    @media (max-width:1000px) { .row2 { grid-template-columns:1fr; } }
    @media (max-width:700px) { .kpis { grid-template-columns:1fr; } }
</style>

<div class="page-head">
    <div>
        <h1>Returned stock</h1>
        <p>Items returned by customers and accepted into stock. Stock is added automatically when a return is accepted on the Orders page.</p>
    </div>
    <a href="orders_admin.php?f=returns" class="btn-outline"><i class="bi bi-arrow-counterclockwise"></i>Review return requests</a>
</div>

<section class="kpis">
    <div class="kpi">
        <div class="k-ic"><i class="bi bi-box-seam"></i></div>
        <div class="k-l">Units returned</div>
        <div class="k-v"><?= number_format($totalUnits) ?></div>
    </div>
    <div class="kpi">
        <div class="k-ic"><i class="bi bi-receipt"></i></div>
        <div class="k-l">Return records</div>
        <div class="k-v"><?= number_format($totalRecords) ?></div>
    </div>
    <div class="kpi">
        <div class="k-ic"><i class="bi bi-tags"></i></div>
        <div class="k-l">Products affected</div>
        <div class="k-v"><?= number_format($productCount) ?></div>
    </div>
</section>

<div class="row2">

    <!-- Return records -->
    <section class="panel">
        <div class="panel-h">
            <h2>Return records</h2>
            <form method="GET" class="search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Product or reason" value="<?= e($search) ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th class="num">Qty</th>
                    <th>Reason</th>
                    <th>Returned on</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="5">
                    <div class="empty"><i class="bi bi-inbox"></i>No returned stock found.</div>
                </td></tr>
            <?php endif; ?>

            <?php foreach ($records as $r): ?>
                <tr>
                    <td class="muted">#<?= (int)$r['id'] ?></td>
                    <td class="pname"><?= e($r['product_name']) ?></td>
                    <td class="num"><?= (int)$r['quantity'] ?></td>
                    <td class="reason"><?= e($r['reason'] ?: '—') ?></td>
                    <td class="muted"><?= e(date('d M Y, h:i A', strtotime($r['return_date']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="pager">
            <?php if ($page > 1): ?>
                <a href="<?= e(page_link($page - 1)) ?>"><i class="bi bi-chevron-left"></i> Previous</a>
            <?php else: ?>
                <span class="dis"><i class="bi bi-chevron-left"></i> Previous</span>
            <?php endif; ?>

            <span>Page <?= $page ?></span>

            <?php if ($hasNext): ?>
                <a href="<?= e(page_link($page + 1)) ?>">Next <i class="bi bi-chevron-right"></i></a>
            <?php else: ?>
                <span class="dis">Next <i class="bi bi-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    </section>

    <!-- Summary per product -->
    <section class="panel">
        <div class="panel-h">
            <h2>Top returned products</h2>
        </div>
        <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="num">Units</th>
                    <th class="num">Returns</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($summary)): ?>
                <tr><td colspan="3">
                    <div class="empty"><i class="bi bi-inbox"></i>No returned products yet.</div>
                </td></tr>
            <?php endif; ?>

            <?php foreach ($summary as $s): ?>
                <tr>
                    <td>
                        <span class="pname"><?= e($s['product_name']) ?></span>
                        <div class="muted">Product #<?= (int)$s['product_id'] ?></div>
                    </td>
                    <td class="num"><?= (int)$s['total_returned'] ?></td>
                    <td class="num"><span class="pill"><?= (int)$s['return_count'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

</div>

<?php require_once 'admin_footer.php'; ?>