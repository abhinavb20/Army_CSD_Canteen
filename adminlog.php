<?php
require_once 'admin_guard.php';

$pageTitle = 'Users';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$csrf    = $_SESSION['admin_csrf'];
$adminId = (int)$_SESSION['user_id'];

const PER_PAGE = 25;

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid request. Please reload the page and try again.');
    }

    $action = (string)($_POST['action'] ?? '');
    $uid    = (int)($_POST['user_id'] ?? 0);
    $msg    = 'Invalid request.';
    $ok     = false;

    // Never act on your own admin account
    if ($uid > 0 && $uid === $adminId) {
        $msg = "You can't change your own account here.";
    } elseif ($uid > 0) {
        try {
            if ($action === 'approve') {
                $s = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ? AND role = 'user'");
                $s->bind_param("i", $uid);
                $s->execute();
                $ok  = $s->affected_rows > 0;
                $msg = $ok ? 'User approved.' : 'That user could not be approved.';
                $s->close();
            } elseif ($action === 'deactivate') {
                $s = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'user'");
                $s->bind_param("i", $uid);
                $s->execute();
                $ok  = $s->affected_rows > 0;
                $msg = $ok ? 'User deactivated. They can no longer log in.' : 'That user could not be deactivated.';
                $s->close();
            } elseif ($action === 'reactivate') {
                $s = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ? AND role = 'user'");
                $s->bind_param("i", $uid);
                $s->execute();
                $ok  = $s->affected_rows > 0;
                $msg = $ok ? 'User reactivated.' : 'That user could not be reactivated.';
                $s->close();
            }
        } catch (Throwable $ex) {
            error_log('User action failed: ' . $ex->getMessage());
            $msg = 'We could not update this user. Please try again.';
        }
    }

    $back = http_build_query([
        'f'   => $_POST['f'] ?? 'all',
        'q'   => $_POST['q'] ?? '',
        'p'   => (int)($_POST['p'] ?? 1),
        'msg' => $msg,
        'ok'  => $ok ? 1 : 0,
    ]);
    header("Location: adminlog.php?" . $back);
    exit;
}

// ---------- Filters ----------
$requestedFilter = $_GET['f'] ?? 'all';
$filter = in_array($requestedFilter, ['all', 'pending', 'approved', 'inactive'], true) ? $requestedFilter : 'all';
$search = trim((string)($_GET['q'] ?? ''));
$page   = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * PER_PAGE;

$where  = ["role = 'user'"];
$params = [];
$types  = '';

switch ($filter) {
    case 'pending':
        $where[] = 'is_approved = 0 AND is_active = 1';
        break;
    case 'approved':
        $where[] = 'is_approved = 1 AND is_active = 1';
        break;
    case 'inactive':
        $where[] = 'is_active = 0';
        break;
}

if ($search !== '') {
    $where[]  = '(name LIKE ? OR ph_no LIKE ? OR army_id LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = [$like, $like, $like];
    $types    = 'sss';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// ---------- Data ----------
$stmt = $conn->prepare(
    "SELECT id, name, ph_no, army_id, is_approved, is_active, created_at
     FROM users $whereSql
     ORDER BY id DESC
     LIMIT ? OFFSET ?"
);
$allParams = array_merge($params, [PER_PAGE + 1, $offset]);
$stmt->bind_param($types . 'ii', ...$allParams);
$stmt->execute();
$fetched = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$hasNext = count($fetched) > PER_PAGE;
$users   = array_slice($fetched, 0, PER_PAGE);

// Counts for the filter chips
$counts = [
    'all'      => (int)scalar_value($conn, "SELECT COUNT(*) FROM users WHERE role = 'user'"),
    'pending'  => (int)scalar_value($conn, "SELECT COUNT(*) FROM users WHERE role = 'user' AND is_approved = 0 AND is_active = 1"),
    'approved' => (int)scalar_value($conn, "SELECT COUNT(*) FROM users WHERE role = 'user' AND is_approved = 1 AND is_active = 1"),
    'inactive' => (int)scalar_value($conn, "SELECT COUNT(*) FROM users WHERE role = 'user' AND is_active = 0"),
];

$chips = [
    'all'      => 'All users',
    'pending'  => 'Pending approval',
    'approved' => 'Approved',
    'inactive' => 'Deactivated',
];

$flashMsg = $_GET['msg'] ?? '';
$flashOk  = ($_GET['ok'] ?? '') === '1';

function user_link(array $overrides): string {
    global $filter, $search, $page;
    return '?' . http_build_query(array_merge(['f' => $filter, 'q' => $search, 'p' => $page], $overrides));
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
    tr:last-child td { border-bottom:0; }
    tbody tr:hover { background:#fafcfa; }
    .uname { font-weight:600; }
    .muted { color:var(--mu); font-size:12px; }

    .tag { font-size:11.5px; font-weight:600; padding:4px 10px; border-radius:999px; display:inline-block; white-space:nowrap; }
    .t-ok { background:#e4f5ea; color:var(--ok); }
    .t-warn { background:#fdf3e1; color:var(--warn); }
    .t-bad { background:#fde8e6; color:var(--bad); }

    .acts { display:flex; gap:6px; justify-content:flex-end; }
    .btn-mini { height:32px; border:0; border-radius:8px; background:var(--g9); color:#fff; font-weight:600; font-size:12.5px; padding:0 12px; display:inline-flex; align-items:center; gap:6px; }
    .btn-mini:hover { background:var(--g7); }
    .btn-mini:disabled { background:#c9d3cc; }
    .btn-soft { height:32px; border:1px solid #c5d6c9; background:#fff; color:var(--g7); border-radius:8px; font-weight:600; font-size:12.5px; padding:0 12px; display:inline-flex; align-items:center; gap:6px; }
    .btn-soft:hover { background:var(--g1); color:var(--g9); }
    .btn-danger-soft { height:32px; border:1px solid #f1c9c4; background:#fff; color:var(--bad); border-radius:8px; font-weight:600; font-size:12.5px; padding:0 12px; display:inline-flex; align-items:center; gap:6px; }
    .btn-danger-soft:hover { background:#fde8e6; }

    .pager { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-top:1px solid var(--line); font-size:13px; color:var(--mu); }
    .pager a { color:var(--g7); font-weight:600; }
    .pager .dis { color:#b5c2b9; }
    .empty { text-align:center; color:var(--mu); padding:44px 20px; }
    .empty i { font-size:36px; color:#aab7ad; display:block; margin-bottom:8px; }
</style>

<div class="page-head">
    <div>
        <h1>Users</h1>
        <p>Approve new registrations and manage customer accounts.</p>
    </div>
</div>

<section class="panel">
    <?php if ($flashMsg !== ''): ?>
        <div class="flash <?= $flashOk ? 'ok' : 'err' ?>"><?= e($flashMsg) ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <div class="chips">
            <?php foreach ($chips as $key => $label): ?>
                <a class="chip <?= $filter === $key ? 'on' : '' ?>" href="<?= e(user_link(['f' => $key, 'p' => 1])) ?>">
                    <?= e($label) ?><b><?= $counts[$key] ?></b>
                </a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="search">
            <input type="hidden" name="f" value="<?= e($filter) ?>">
            <i class="bi bi-search"></i>
            <input type="text" name="q" placeholder="Name, phone or Army ID" value="<?= e($search) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Phone</th>
                <th>Army ID</th>
                <th>Status</th>
                <th>Joined</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="6">
                <div class="empty"><i class="bi bi-people"></i>No users match this view.</div>
            </td></tr>
        <?php endif; ?>

        <?php foreach ($users as $u):
            $uid      = (int)$u['id'];
            $active   = (int)$u['is_active'] === 1;
            $approved = (int)$u['is_approved'] === 1;

            if (!$active) {
                [$label, $cls] = ['Deactivated', 't-bad'];
            } elseif ($approved) {
                [$label, $cls] = ['Approved', 't-ok'];
            } else {
                [$label, $cls] = ['Pending approval', 't-warn'];
            }
        ?>
            <tr>
                <td>
                    <div class="uname"><?= e($u['name']) ?></div>
                    <div class="muted">ID #<?= $uid ?></div>
                </td>
                <td><?= e($u['ph_no']) ?></td>
                <td><?= e($u['army_id']) ?></td>
                <td><span class="tag <?= $cls ?>"><?= $label ?></span></td>
                <td class="muted"><?= !empty($u['created_at']) ? e(date('d M Y', strtotime($u['created_at']))) : '—' ?></td>
                <td>
                    <div class="acts">
                        <?php if (!$approved && $active): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="f" value="<?= e($filter) ?>">
                                <input type="hidden" name="q" value="<?= e($search) ?>">
                                <input type="hidden" name="p" value="<?= $page ?>">
                                <button type="submit" class="btn-mini"><i class="bi bi-check2"></i>Approve</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($active): ?>
                            <form method="POST" class="d-inline js-confirm" data-msg="Deactivate this user? They will not be able to log in.">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="f" value="<?= e($filter) ?>">
                                <input type="hidden" name="q" value="<?= e($search) ?>">
                                <input type="hidden" name="p" value="<?= $page ?>">
                                <button type="submit" class="btn-danger-soft"><i class="bi bi-slash-circle"></i>Deactivate</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="reactivate">
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="f" value="<?= e($filter) ?>">
                                <input type="hidden" name="q" value="<?= e($search) ?>">
                                <input type="hidden" name="p" value="<?= $page ?>">
                                <button type="submit" class="btn-soft"><i class="bi bi-arrow-counterclockwise"></i>Reactivate</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <div class="pager">
        <?php if ($page > 1): ?>
            <a href="<?= e(user_link(['p' => $page - 1])) ?>"><i class="bi bi-chevron-left"></i> Previous</a>
        <?php else: ?>
            <span class="dis"><i class="bi bi-chevron-left"></i> Previous</span>
        <?php endif; ?>

        <span>Page <?= $page ?></span>

        <?php if ($hasNext): ?>
            <a href="<?= e(user_link(['p' => $page + 1])) ?>">Next <i class="bi bi-chevron-right"></i></a>
        <?php else: ?>
            <span class="dis">Next <i class="bi bi-chevron-right"></i></span>
        <?php endif; ?>
    </div>
</section>

<script>
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