<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('manage_addresses.php'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// CSRF token for delete forms
if (empty($_SESSION['addr_csrf'])) {
    $_SESSION['addr_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['addr_csrf'];

// Message from add, edit or delete pages
$flash = '';
$flashType = 'alert-info';
if (!empty($_GET['msg'])) {
    $flash = (string)$_GET['msg'];
    $flashType = str_contains(strtolower($flash), 'deleted') || str_contains(strtolower($flash), 'saved') || str_contains(strtolower($flash), 'added')
        ? 'alert-success'
        : 'alert-warning';
}

// Addresses for this user only
$stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$addresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Addresses | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --green-900:#103d25; --green-700:#17623b; --green-100:#eaf3ec; --ink:#18251d; --muted:#6b7a70; --line:#e4ebe5; --bg:#f4f6f4; --danger:#b42318; }
        * { box-sizing: border-box; }
        body { min-height:100vh; display:flex; flex-direction:column; font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); font-size:14px; -webkit-font-smoothing:antialiased; }
        .page { flex:1; width:100%; max-width:1080px; margin:0 auto; padding:28px 20px 60px; }

        .page-head { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:14px; margin-bottom:22px; }
        .page-head h1 { font-size:24px; font-weight:800; letter-spacing:-.3px; margin:0 0 4px; }
        .page-head p { color:var(--muted); margin:0; }
        .btn-main { display:inline-flex; align-items:center; gap:8px; background:var(--green-900); color:#fff; border-radius:9px; padding:10px 18px; font-weight:600; text-decoration:none; font-size:14px; }
        .btn-main:hover { background:var(--green-700); color:#fff; }

        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:18px; }

        .addr { background:#fff; border:1px solid var(--line); border-radius:14px; padding:20px; display:flex; flex-direction:column; transition:box-shadow .2s, border-color .2s; }
        .addr:hover { box-shadow:0 10px 26px rgba(16,61,37,.08); border-color:#d3e0d6; }
        .addr-top { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:10px; }
        .addr-name { font-size:15px; font-weight:700; margin:0; }
        .tag { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; background:var(--green-100); color:var(--green-900); padding:4px 9px; border-radius:999px; white-space:nowrap; }
        .addr-body { font-size:13.5px; line-height:1.65; color:#3f4d44; flex:1; }
        .addr-body .muted { color:var(--muted); }
        .addr-meta { margin-top:12px; padding-top:12px; border-top:1px dashed var(--line); font-size:12.5px; color:var(--muted); display:grid; gap:4px; }
        .addr-meta i { color:var(--green-700); width:16px; }

        .addr-actions { display:flex; gap:8px; margin-top:16px; }
        .btn-soft { flex:1; display:inline-flex; justify-content:center; align-items:center; gap:6px; border:1px solid #c5d6c9; background:#fff; color:var(--green-700); border-radius:8px; padding:8px 12px; font-size:13px; font-weight:600; text-decoration:none; }
        .btn-soft:hover { background:var(--green-100); color:var(--green-900); }
        .btn-del { flex:1; display:inline-flex; justify-content:center; align-items:center; gap:6px; border:1px solid #f1c9c4; background:#fff; color:var(--danger); border-radius:8px; padding:8px 12px; font-size:13px; font-weight:600; }
        .btn-del:hover { background:#fde8e6; }

        .empty { background:#fff; border:1px solid var(--line); border-radius:14px; padding:56px 20px; text-align:center; }
        .empty i { font-size:40px; color:#aab7ad; }
        .empty h2 { font-size:18px; font-weight:700; margin:12px 0 6px; }
        .empty p { color:var(--muted); margin-bottom:18px; }

        .alert-soft { border-radius:10px; font-size:13.5px; margin-bottom:18px; }

        /* Delete modal */
        .confirm-modal { border:0; border-radius:16px; overflow:hidden; }
        .confirm-icon { width:58px; height:58px; border-radius:50%; background:#fde8e6; color:var(--danger); display:grid; place-items:center; font-size:24px; margin:0 auto 14px; }
        .btn-confirm-del { background:var(--danger); color:#fff; border:0; border-radius:9px; padding:10px 20px; font-weight:600; font-size:14px; }
        .btn-confirm-del:hover { background:#8f1c13; color:#fff; }
        .btn-keep { border:1px solid #c5d6c9; background:#fff; color:var(--green-700); border-radius:9px; padding:10px 20px; font-weight:600; font-size:14px; }
        .btn-keep:hover { background:var(--green-100); color:var(--green-900); }

        footer { background:var(--green-900); color:#cfe1d5; padding:24px 15px; text-align:center; font-size:12.5px; margin-top:30px; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="page">

        <?php if ($flash): ?>
            <div class="alert <?= $flashType ?> alert-soft alert-dismissible fade show" role="alert">
                <?= e($flash) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="page-head">
            <div>
                <h1>My addresses</h1>
                <p><?= count($addresses) ?> saved address<?= count($addresses) === 1 ? '' : 'es' ?></p>
            </div>
            <a href="add_address.php" class="btn-main"><i class="bi bi-plus-lg"></i>Add new address</a>
        </div>

        <?php if (empty($addresses)): ?>

            <div class="empty">
                <i class="bi bi-geo-alt"></i>
                <h2>No addresses saved</h2>
                <p>Add a delivery address to make checkout faster.</p>
                <a href="add_address.php" class="btn-main"><i class="bi bi-plus-lg"></i>Add an address</a>
            </div>

        <?php else: ?>

            <div class="grid">
                <?php foreach ($addresses as $a): ?>
                    <article class="addr">
                        <div class="addr-top">
                            <h2 class="addr-name"><?= e($a['name']) ?></h2>
                            <?php if (!empty($a['address_type'])): ?>
                                <span class="tag"><?= e($a['address_type']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="addr-body">
                            <?= e($a['address_line']) ?>
                            <?php if (!empty($a['locality'])): ?>, <?= e($a['locality']) ?><?php endif; ?><br>
                            <?= e($a['city']) ?>, <?= e($a['state']) ?> – <?= e($a['pincode']) ?>
                        </div>

                        <div class="addr-meta">
                            <div><i class="bi bi-telephone"></i><?= e($a['phone']) ?>
                                <?php if (!empty($a['alt_phone'])): ?><span class="muted"> · Alt: <?= e($a['alt_phone']) ?></span><?php endif; ?>
                            </div>
                            <?php if (!empty($a['landmark'])): ?>
                                <div><i class="bi bi-signpost"></i>Landmark: <?= e($a['landmark']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="addr-actions">
                            <a href="edit_address.php?id=<?= (int)$a['id'] ?>" class="btn-soft"><i class="bi bi-pencil"></i>Edit</a>
                            <button type="button" class="btn-del"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteModal"
                                    data-addr-id="<?= (int)$a['id'] ?>"
                                    data-addr-name="<?= e($a['name']) ?>">
                                <i class="bi bi-trash"></i>Delete
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </main>

    <!-- Delete confirmation -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content confirm-modal">
                <form method="POST" action="delete_address.php">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" id="deleteAddrId" value="">

                    <div class="modal-body text-center p-4">
                        <div class="confirm-icon"><i class="bi bi-trash"></i></div>
                        <h5 class="fw-bold mb-2" id="deleteModalLabel">Delete this address?</h5>
                        <p class="text-muted mb-0">
                            <strong id="deleteAddrName"></strong> will be removed from your saved addresses.
                        </p>
                    </div>

                    <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                        <button type="button" class="btn-keep" data-bs-dismiss="modal">Keep it</button>
                        <button type="submit" class="btn-confirm-del" id="deleteConfirmBtn">Yes, delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('deleteAddrId').value = btn.dataset.addrId;
            document.getElementById('deleteAddrName').textContent = btn.dataset.addrName;
        });

        document.querySelector('#deleteModal form').addEventListener('submit', function () {
            const btn = document.getElementById('deleteConfirmBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Deleting…';
        });
    </script>
</body>
</html>