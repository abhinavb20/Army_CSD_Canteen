<?php
require_once 'admin_guard.php';

$pageTitle = 'Products';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['admin_csrf'];

// Search and filter
$search = trim($_GET['q'] ?? '');
$filter = in_array($_GET['filter'] ?? '', ['low', 'out'], true) ? $_GET['filter'] : 'all';

$where  = ['is_active = 1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = '(name LIKE ? OR description LIKE ?)';
    $like    = '%' . $search . '%';
    $params  = [$like, $like];
    $types   = 'ss';
}
if ($filter === 'low') {
    $where[] = '(stock > 0 AND stock <= ' . LOW_STOCK_LIMIT . ')';
} elseif ($filter === 'out') {
    $where[] = '(stock = 0)';
}

$sql  = 'SELECT id, name, description, price, stock, image FROM products
         WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC';
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Summary figures
$totalActive = (int)scalar_value($conn, 'SELECT COUNT(*) FROM products WHERE is_active = 1');
$outCount    = (int)scalar_value($conn, 'SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock = 0');
$lowCount    = (int)scalar_value($conn, 'SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock > 0 AND stock <= ' . LOW_STOCK_LIMIT);
$stockValue  = (float)scalar_value($conn, 'SELECT COALESCE(SUM(price * stock), 0) FROM products WHERE is_active = 1');

$flash = $_GET['msg'] ?? '';

require_once 'admin_header.php';
?>

<style>
    .kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:18px; }
    .kpi { background:#fff; border:1px solid var(--line); border-radius:14px; padding:18px; }
    .kpi .k-ic { width:36px; height:36px; border-radius:10px; background:var(--g1); color:var(--g7); display:grid; place-items:center; margin-bottom:10px; }
    .kpi .k-ic.w { background:#fdf3e1; color:var(--warn); }
    .kpi .k-ic.r { background:#fde8e6; color:var(--bad); }
    .kpi .k-l { color:var(--mu); font-size:12.5px; font-weight:500; }
    .kpi .k-v { font-size:22px; font-weight:800; margin-top:3px; letter-spacing:-.3px; }

    .panel { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; }
    .toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:16px 20px; border-bottom:1px solid var(--line); }
    .chips { display:flex; gap:6px; flex-wrap:wrap; }
    .chip { font-size:12.5px; font-weight:600; padding:6px 12px; border-radius:999px; border:1px solid var(--line); color:var(--mu); background:#fff; }
    .chip:hover { color:var(--ink); }
    .chip.on { background:var(--g9); border-color:var(--g9); color:#fff; }
    .chip b { font-weight:700; margin-left:4px; }
    .search { display:flex; align-items:center; gap:8px; border:1px solid var(--line); border-radius:9px; padding:0 6px 0 12px; height:38px; min-width:260px; }
    .search i { color:var(--mu); }
    .search input { border:0; outline:0; flex:1; font:inherit; font-size:13.5px; background:none; }
    .search button { border:0; background:var(--g9); color:#fff; border-radius:7px; padding:6px 12px; font-size:12.5px; font-weight:600; }

    .flash { margin:16px 20px 0; border-radius:10px; font-size:13.5px; padding:10px 14px; }
    .flash.ok { background:#e4f5ea; color:var(--ok); }
    .flash.err { background:#fde8e6; color:var(--bad); }

    table { width:100%; border-collapse:collapse; font-size:13.5px; }
    th { text-align:left; color:var(--mu); font-size:12px; font-weight:600; padding:11px 16px; background:var(--bg); border-bottom:1px solid var(--line); white-space:nowrap; }
    td { padding:12px 16px; border-bottom:1px solid var(--line); vertical-align:middle; }
    tr:last-child td { border-bottom:0; }
    th.r, td.r { text-align:right; }
    tbody tr:hover { background:#fafcfa; }

    .prod { display:flex; align-items:center; gap:12px; }
    .thumb { width:46px; height:46px; border-radius:9px; background:#f6f8f6; border:1px solid var(--line); display:grid; place-items:center; overflow:hidden; flex-shrink:0; }
    .thumb img { width:100%; height:100%; object-fit:contain; }
    .thumb i { color:#9aa69e; font-size:18px; }
    .prod b { font-weight:600; display:block; }
    .prod small { color:var(--mu); font-size:12px; }
    .desc { color:var(--mu); max-width:260px; font-size:12.5px; line-height:1.5; }
    .num { font-weight:600; white-space:nowrap; }
    .tag { font-size:11.5px; font-weight:600; padding:4px 10px; border-radius:999px; display:inline-block; }
    .t-ok { background:#e4f5ea; color:var(--ok); }
    .t-warn { background:#fdf3e1; color:var(--warn); }
    .t-bad { background:#fde8e6; color:var(--bad); }

    .acts { display:flex; gap:6px; justify-content:flex-end; }
    .ibtn { width:34px; height:34px; border-radius:8px; border:1px solid var(--line); background:#fff; color:var(--g7); display:grid; place-items:center; font-size:15px; }
    .ibtn:hover { background:var(--g1); border-color:#c5d6c9; color:var(--g9); }
    .ibtn.danger { color:var(--bad); }
    .ibtn.danger:hover { background:#fde8e6; border-color:#f1c9c4; }

    .empty { text-align:center; color:var(--mu); padding:44px 20px; }
    .empty i { font-size:36px; color:#aab7ad; display:block; margin-bottom:8px; }

    .delete-modal { border:0; border-radius:16px; overflow:hidden; }
    .d-icon { width:58px; height:58px; border-radius:50%; background:#fde8e6; color:var(--bad); display:grid; place-items:center; font-size:24px; margin:0 auto 14px; }
    .btn-del { background:var(--bad); color:#fff; border:0; border-radius:9px; padding:10px 20px; font-weight:600; font-size:14px; }
    .btn-del:hover { background:#8f1c13; color:#fff; }
    .btn-keep { border:1px solid #c5d6c9; background:#fff; color:var(--g7); border-radius:9px; padding:10px 20px; font-weight:600; font-size:14px; }
    .btn-keep:hover { background:var(--g1); color:var(--g9); }

    @media (max-width:1100px) { .kpis { grid-template-columns:1fr 1fr; } }
    @media (max-width:700px) { .search { min-width:100%; } .kpis { grid-template-columns:1fr 1fr; } }
</style>

<div class="page-head">
    <div>
        <h1>Products</h1>
        <p>Manage the catalogue, prices and listings.</p>
    </div>
    <div class="head-actions">
        <a href="update_stock.php" class="btn-outline"><i class="bi bi-boxes"></i>Update stock</a>
        <a href="add_product.php" class="btn-solid"><i class="bi bi-plus-lg"></i>Add product</a>
    </div>
</div>

<section class="kpis">
    <div class="kpi">
        <div class="k-ic"><i class="bi bi-box-seam"></i></div>
        <div class="k-l">Active products</div>
        <div class="k-v"><?= number_format($totalActive) ?></div>
    </div>
    <div class="kpi">
        <div class="k-ic"><i class="bi bi-currency-rupee"></i></div>
        <div class="k-l">Stock value</div>
        <div class="k-v">₹<?= number_format($stockValue, 2) ?></div>
    </div>
    <div class="kpi">
        <div class="k-ic w"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="k-l">Low stock</div>
        <div class="k-v"><?= $lowCount ?></div>
    </div>
    <div class="kpi">
        <div class="k-ic r"><i class="bi bi-x-circle"></i></div>
        <div class="k-l">Out of stock</div>
        <div class="k-v"><?= $outCount ?></div>
    </div>
</section>

<section class="panel">
    <?php if ($flash !== ''): ?>
        <?php $isOk = stripos($flash, 'removed') !== false || stripos($flash, 'saved') !== false || stripos($flash, 'updated') !== false; ?>
        <div class="flash <?= $isOk ? 'ok' : 'err' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <div class="chips">
            <a class="chip <?= $filter === 'all' ? 'on' : '' ?>" href="product_dashboard.php">All<b><?= $totalActive ?></b></a>
            <a class="chip <?= $filter === 'low' ? 'on' : '' ?>" href="?filter=low">Low stock<b><?= $lowCount ?></b></a>
            <a class="chip <?= $filter === 'out' ? 'on' : '' ?>" href="?filter=out">Out of stock<b><?= $outCount ?></b></a>
        </div>
        <form method="GET" class="search">
            <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
            <i class="bi bi-search"></i>
            <input type="text" name="q" placeholder="Search name or description" value="<?= e($search) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Description</th>
                    <th class="r">Price</th>
                    <th class="r">Stock</th>
                    <th>Status</th>
                    <th class="r">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="6">
                    <div class="empty">
                        <i class="bi bi-inbox"></i>
                        No products match your filters.
                    </div>
                </td></tr>
            <?php endif; ?>

            <?php foreach ($products as $p):
                $id    = (int)$p['id'];
                $stock = (int)$p['stock'];
                if ($stock === 0) {
                    [$statusText, $statusClass] = ['Out of stock', 't-bad'];
                } elseif ($stock <= LOW_STOCK_LIMIT) {
                    [$statusText, $statusClass] = ['Low stock', 't-warn'];
                } else {
                    [$statusText, $statusClass] = ['In stock', 't-ok'];
                }
            ?>
                <tr>
                    <td>
                        <div class="prod">
                            <div class="thumb">
                                <?php if (!empty($p['image'])): ?>
                                    <img src="uploads/<?= e($p['image']) ?>" alt="">
                                <?php else: ?>
                                    <i class="bi bi-image"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <b><?= e($p['name']) ?></b>
                                <small>ID #<?= $id ?></small>
                            </div>
                        </div>
                    </td>
                    <td class="desc"><?= e(mb_strimwidth((string)$p['description'], 0, 90, '…')) ?></td>
                    <td class="r num">₹<?= number_format((float)$p['price'], 2) ?></td>
                    <td class="r num"><?= $stock ?></td>
                    <td><span class="tag <?= $statusClass ?>"><?= $statusText ?></span></td>
                    <td>
                        <div class="acts">
                            <a class="ibtn" href="edit_product.php?id=<?= $id ?>" title="Edit product"><i class="bi bi-pencil"></i></a>
                            <a class="ibtn" href="update_stock.php?product_id=<?= $id ?>" title="Update stock"><i class="bi bi-boxes"></i></a>
                            <button type="button" class="ibtn danger" title="Remove product"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-id="<?= $id ?>" data-name="<?= e($p['name']) ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Remove product confirmation -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content delete-modal">
            <form method="POST" action="delete_product.php" id="deleteForm">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" id="deleteId" value="">
                <div class="modal-body text-center p-4">
                    <div class="d-icon"><i class="bi bi-trash"></i></div>
                    <h5 class="fw-bold mb-2">Remove this product?</h5>
                    <p class="text-muted mb-0">
                        <strong id="deleteName"></strong> will be hidden from the store.
                        Past orders are kept.
                    </p>
                </div>
                <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                    <button type="button" class="btn-keep" data-bs-dismiss="modal">Keep it</button>
                    <button type="submit" class="btn-del" id="deleteBtn">Yes, remove</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const deleteModal = document.getElementById('deleteModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('deleteId').value = btn.dataset.id;
        document.getElementById('deleteName').textContent = btn.dataset.name;
    });
    document.getElementById('deleteForm').addEventListener('submit', function () {
        const btn = document.getElementById('deleteBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Removing…';
    });
</script>

<?php require_once 'admin_footer.php'; ?>