<?php
session_start();
include 'config.php';
require_once 'address_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required&redirect=" . urlencode('manage_addresses.php'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$id      = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: manage_addresses.php?msg=" . urlencode("Invalid address."));
    exit;
}

// Shared token with the address pages
if (empty($_SESSION['addr_csrf'])) {
    $_SESSION['addr_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['addr_csrf'];

// Load the address, only if it belongs to this user
$stmt = $conn->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$address = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$address) {
    header("Location: manage_addresses.php?msg=" . urlencode("Address not found."));
    exit;
}

// Use the typed values if the last save failed for this same address
$values = [];
foreach (ADDRESS_FIELDS as $f) {
    $values[$f] = (string)($address[$f] ?? '');
}
if (!empty($_SESSION['addr_edit_old']) && (int)$_SESSION['addr_edit_old']['id'] === $id) {
    $values = array_merge($values, $_SESSION['addr_edit_old']['data']);
}
if ($values['address_type'] === '') {
    $values['address_type'] = 'Home';
}

$flash = $_SESSION['addr_flash'] ?? null;
unset($_SESSION['addr_flash'], $_SESSION['addr_edit_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit address | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --green-900:#103d25; --green-700:#17623b; --green-100:#eaf3ec; --ink:#18251d; --muted:#6b7a70; --line:#e4ebe5; --bg:#f4f6f4; --danger:#b42318; }
        * { box-sizing:border-box; }
        body { font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); font-size:14px; -webkit-font-smoothing:antialiased; }

        .page { max-width:760px; margin:0 auto; padding:28px 20px 60px; }
        .crumb { font-size:13px; color:var(--muted); margin-bottom:10px; }
        .crumb a { color:var(--muted); text-decoration:none; }
        .crumb a:hover { color:var(--green-700); }
        .page h1 { font-size:24px; font-weight:800; letter-spacing:-.3px; margin:0 0 4px; }
        .page .sub { color:var(--muted); margin-bottom:22px; }

        .panel { background:#fff; border:1px solid var(--line); border-radius:14px; padding:24px; margin-bottom:18px; }
        .panel h2 { font-size:15px; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; }
        .panel h2 i { color:var(--green-700); }

        .form-label { font-size:13px; font-weight:600; margin-bottom:6px; }
        .form-label .opt { color:var(--muted); font-weight:500; }
        .form-control, .form-select { border-radius:9px; border-color:var(--line); padding:10px 12px; font-size:14px; }
        .form-control:focus, .form-select:focus { border-color:var(--green-700); box-shadow:0 0 0 .2rem rgba(23,98,59,.15); }

        .type-picker { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .type-opt { position:relative; border:1.5px solid var(--line); border-radius:12px; padding:14px; cursor:pointer; display:flex; align-items:center; gap:12px; transition:.2s; }
        .type-opt:hover { border-color:#b9ccbe; }
        .type-opt input { position:absolute; opacity:0; pointer-events:none; }
        .type-opt i { font-size:20px; color:var(--green-700); width:36px; height:36px; border-radius:10px; background:var(--green-100); display:grid; place-items:center; }
        .type-opt strong { display:block; font-size:14px; }
        .type-opt small { color:var(--muted); font-size:12px; }
        .type-opt:has(input:checked) { border-color:var(--green-700); background:var(--green-100); }

        .alert-soft { border-radius:10px; font-size:13.5px; margin-bottom:18px; }

        .actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
        .btn-save { display:inline-flex; align-items:center; gap:8px; background:var(--green-900); color:#fff; border:0; border-radius:10px; padding:12px 24px; font-weight:700; font-size:14px; }
        .btn-save:hover { background:var(--green-700); }
        .btn-save:disabled { background:#c9d3cc; }
        .btn-back { display:inline-flex; align-items:center; gap:6px; border:1px solid #c5d6c9; background:#fff; color:var(--green-700); border-radius:10px; padding:12px 20px; font-weight:600; font-size:14px; text-decoration:none; }
        .btn-back:hover { background:var(--green-100); color:var(--green-900); }

        footer { background:var(--green-900); color:#cfe1d5; padding:24px 15px; text-align:center; font-size:12.5px; margin-top:30px; }

        @media (max-width:600px) {
            .type-picker { grid-template-columns:1fr; }
            .actions { flex-direction:column-reverse; }
            .btn-save, .btn-back { width:100%; justify-content:center; }
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="page">

        <div class="crumb"><a href="manage_addresses.php">My addresses</a> / Edit</div>
        <h1>Edit address</h1>
        <p class="sub">Update the details for this delivery address.</p>

        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-danger' ?> alert-soft">
                <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?> me-1"></i>
                <?= e($flash['text']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="update_address.php" id="addressForm" novalidate>
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">

            <section class="panel">
                <h2><i class="bi bi-person"></i>Contact details</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full name</label>
                        <input type="text" id="name" name="name" class="form-control" maxlength="100"
                               value="<?= e($values['name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Mobile number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" maxlength="10"
                               inputmode="numeric" value="<?= e($values['phone']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="alt_phone" class="form-label">Alternate phone <span class="opt">(optional)</span></label>
                        <input type="tel" id="alt_phone" name="alt_phone" class="form-control" maxlength="10"
                               inputmode="numeric" value="<?= e($values['alt_phone']) ?>">
                    </div>
                </div>
            </section>

            <section class="panel">
                <h2><i class="bi bi-geo-alt"></i>Address</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="address_line" class="form-label">Flat, house no., street, area</label>
                        <textarea id="address_line" name="address_line" class="form-control" rows="2" maxlength="255"
                                  required><?= e($values['address_line']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="locality" class="form-label">Locality <span class="opt">(optional)</span></label>
                        <input type="text" id="locality" name="locality" class="form-control" maxlength="100"
                               value="<?= e($values['locality']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="landmark" class="form-label">Landmark <span class="opt">(optional)</span></label>
                        <input type="text" id="landmark" name="landmark" class="form-control" maxlength="150"
                               value="<?= e($values['landmark']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="city" class="form-label">City / District / Town</label>
                        <input type="text" id="city" name="city" class="form-control" maxlength="100"
                               value="<?= e($values['city']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="state" class="form-label">State</label>
                        <select id="state" name="state" class="form-select" required>
                            <option value="">Select state</option>
                            <?php foreach (ADDRESS_STATES as $s): ?>
                                <option value="<?= e($s) ?>" <?= $values['state'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="pincode" class="form-label">Pincode</label>
                        <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6"
                               inputmode="numeric" value="<?= e($values['pincode']) ?>" required>
                    </div>
                </div>
            </section>

            <section class="panel">
                <h2><i class="bi bi-house"></i>Address type</h2>
                <div class="type-picker">
                    <label class="type-opt">
                        <input type="radio" name="address_type" value="Home" <?= $values['address_type'] === 'Home' ? 'checked' : '' ?>>
                        <i class="bi bi-house-door"></i>
                        <div><strong>Home</strong><small>Residence or quarters</small></div>
                    </label>
                    <label class="type-opt">
                        <input type="radio" name="address_type" value="Work" <?= $values['address_type'] === 'Work' ? 'checked' : '' ?>>
                        <i class="bi bi-building"></i>
                        <div><strong>Work</strong><small>Office or unit lines</small></div>
                    </label>
                </div>
            </section>

            <div class="actions">
                <a href="manage_addresses.php" class="btn-back"><i class="bi bi-arrow-left"></i>Cancel</a>
                <button type="submit" class="btn-save" id="saveBtn"><i class="bi bi-check2"></i>Save changes</button>
            </div>
        </form>

    </main>

    <footer>© <?= date('Y') ?> Army CSD Canteen. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const form = document.getElementById('addressForm');

        // Keep phone and pincode to digits only
        ['phone', 'alt_phone', 'pincode'].forEach(id => {
            const el = document.getElementById(id);
            el.addEventListener('input', () => el.value = el.value.replace(/\D/g, ''));
        });

        form.addEventListener('submit', function (e) {
            const phone = document.getElementById('phone').value;
            const pin   = document.getElementById('pincode').value;

            if (!/^[6-9]\d{9}$/.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit mobile number.');
                return;
            }
            if (!/^\d{6}$/.test(pin)) {
                e.preventDefault();
                alert('Please enter a valid 6-digit pincode.');
                return;
            }
            if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
                return;
            }

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';
        });
    </script>
</body>
</html>