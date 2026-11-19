<?php
session_start();
include 'config.php';

const MAX_ATTEMPTS  = 5;
const LOCK_SECONDS  = 900; // 15 minutes

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Only allow redirects to known pages on this site
function safe_redirect(string $target): string {
    $allowed = ['index.php', 'cart.php', 'checkout.php', 'my_orders.php', 'profile.php',
                'manage_addresses.php', 'product_details.php'];
    if (!preg_match('/^[a-z_]+\.php(\?[a-zA-Z0-9_=&%.-]*)?$/', $target)) {
        return 'index.php';
    }
    $page = explode('?', $target, 2)[0];
    return in_array($page, $allowed, true) ? $target : 'index.php';
}

// Already logged in: send to the right place
if (isset($_SESSION['user_id'])) {
    header("Location: " . (($_SESSION['role'] ?? '') === 'admin' ? 'admin_panel.php' : 'index.php'));
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['login_csrf'];

$redirect = safe_redirect((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));

$notices = [
    'login_required' => 'Please log in to continue.',
    'admin_required' => 'Please log in with an admin account.',
    'logged_out'     => 'You have been logged out.',
];
$notice = $notices[$_GET['msg'] ?? ''] ?? '';

$error = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = trim((string)($_POST['ph_no'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $error = 'Your session has expired. Please try again.';
    } elseif (time() < ($_SESSION['login_lock_until'] ?? 0)) {
        $mins  = max(1, (int)ceil((($_SESSION['login_lock_until']) - time()) / 60));
        $error = "Too many failed attempts. Please try again in about $mins minute(s).";
    } elseif (!preg_match('/^\d{10}$/', $phone) || strlen($password) < 6 || strlen($password) > 200) {
        $error = 'Enter a valid 10-digit phone number and your password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, password, is_approved, is_active, role
             FROM users WHERE ph_no = ? LIMIT 1"
        );
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $hash = $user['password'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinva';
        $passwordOk = password_verify($password, $hash) && $user !== null;

        if (!$passwordOk) {
            $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
            if ($_SESSION['login_fail'] >= MAX_ATTEMPTS) {
                $_SESSION['login_lock_until'] = time() + LOCK_SECONDS;
                $_SESSION['login_fail'] = 0;
            }
            // Same message for wrong phone and wrong password
            $error = 'Phone number or password is incorrect.';
        } elseif ((int)($user['is_active'] ?? 1) !== 1) {
            $error = 'This account has been deactivated. Please contact the canteen office.';
        } elseif ((int)$user['is_approved'] !== 1) {
            $error = 'Your account is waiting for admin approval.';
        } else {
            // Good login: new session ID to prevent session fixation
            session_regenerate_id(true);
            unset($_SESSION['login_fail'], $_SESSION['login_lock_until']);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['name']    = (string)$user['name'];
            $_SESSION['role']    = (string)$user['role'];

            $dest = $user['role'] === 'admin' ? 'admin_panel.php' : $redirect;
            header("Location: $dest");
            exit;
        }
    }
}

$locked = time() < ($_SESSION['login_lock_until'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in | Army CSD Canteen</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --green-900:#103d25; --green-700:#17623b; --green-100:#eaf3ec; --accent:#c8e49b; --ink:#18251d; --muted:#6b7a70; --line:#e4ebe5; --bg:#f4f6f4; --danger:#b42318; }
        * { box-sizing:border-box; }
        body { font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); font-size:14px; min-height:100vh; margin:0; -webkit-font-smoothing:antialiased; }

        .shell { min-height:100vh; display:grid; grid-template-columns:1fr 1fr; }

        /* Brand side */
        .brand-side { background:linear-gradient(140deg, var(--green-900), var(--green-700)); color:#fff; padding:56px; display:flex; flex-direction:column; justify-content:space-between; position:relative; overflow:hidden; }
        .brand-side::after { content:''; position:absolute; right:-120px; bottom:-120px; width:360px; height:360px; border-radius:50%; background:rgba(200,228,155,.08); }
        .logo { display:flex; align-items:center; gap:12px; font-weight:700; font-size:16px; letter-spacing:.2px; position:relative; z-index:1; }
        .logo-mark { width:42px; height:42px; border-radius:11px; border:1.5px solid var(--accent); color:var(--accent); display:grid; place-items:center; font-size:12px; font-weight:800; }
        .logo small { display:block; font-size:10.5px; font-weight:500; letter-spacing:1.4px; text-transform:uppercase; color:#a9c7b2; }
        .brand-copy { position:relative; z-index:1; max-width:420px; }
        .brand-copy h2 { font-size:34px; font-weight:800; letter-spacing:-.6px; line-height:1.15; margin:0 0 14px; }
        .brand-copy p { color:#d3e6d9; font-size:15px; line-height:1.6; margin:0 0 26px; }
        .perks { list-style:none; padding:0; margin:0; display:grid; gap:12px; }
        .perks li { display:flex; align-items:center; gap:10px; font-size:13.5px; color:#e6f1ea; }
        .perks i { color:var(--accent); font-size:16px; }
        .brand-foot { font-size:12px; color:#9dbba8; position:relative; z-index:1; }

        /* Form side */
        .form-side { display:flex; align-items:center; justify-content:center; padding:40px 24px; }
        .card-x { width:100%; max-width:400px; }
        .card-x h1 { font-size:26px; font-weight:800; letter-spacing:-.4px; margin:0 0 6px; }
        .card-x .sub { color:var(--muted); margin-bottom:24px; }

        .form-label { font-size:13px; font-weight:600; margin-bottom:6px; }
        .input-wrap { position:relative; }
        .input-wrap > i.lead { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:16px; pointer-events:none; }
        .input-wrap .form-control { padding-left:40px; height:46px; border-radius:10px; border-color:var(--line); font-size:14.5px; }
        .input-wrap .form-control.has-toggle { padding-right:44px; }
        .form-control:focus { border-color:var(--green-700); box-shadow:0 0 0 .2rem rgba(23,98,59,.15); }
        .toggle-pw { position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; color:var(--muted); width:34px; height:34px; border-radius:8px; display:grid; place-items:center; }
        .toggle-pw:hover { background:var(--bg); color:var(--ink); }
        .row-x { display:flex; justify-content:space-between; align-items:center; margin:4px 0 20px; font-size:13px; }
        .row-x a { color:var(--green-700); font-weight:600; text-decoration:none; }
        .row-x a:hover { text-decoration:underline; }

        .btn-login { width:100%; height:48px; border:0; border-radius:11px; background:var(--green-900); color:#fff; font-weight:700; font-size:15px; display:inline-flex; align-items:center; justify-content:center; gap:8px; }
        .btn-login:hover { background:var(--green-700); }
        .btn-login:disabled { background:#c9d3cc; }

        .alert-soft { border-radius:10px; font-size:13.5px; padding:11px 14px; margin-bottom:18px; display:flex; gap:8px; align-items:flex-start; }
        .alert-soft.err { background:#fde8e6; color:var(--danger); }
        .alert-soft.info { background:var(--green-100); color:var(--green-900); }

        .divider { display:flex; align-items:center; gap:12px; color:var(--muted); font-size:12px; margin:24px 0; }
        .divider::before, .divider::after { content:''; flex:1; height:1px; background:var(--line); }
        .register { text-align:center; font-size:13.5px; color:var(--muted); }
        .register a { color:var(--green-700); font-weight:700; text-decoration:none; }
        .register a:hover { text-decoration:underline; }

        .foot-x { margin-top:34px; font-size:12px; color:var(--muted); text-align:center; }

        @media (max-width:900px) {
            .shell { grid-template-columns:1fr; }
            .brand-side { display:none; }
            .form-side { min-height:100vh; }
        }
    </style>
</head>
<body>
<div class="shell">

    <!-- Brand panel -->
    <aside class="brand-side">
        <div class="logo">
            <div class="logo-mark">CSD</div>
            <div>Army CSD Canteen<small>Online store</small></div>
        </div>
        <div class="brand-copy">
            <h2>Welcome back.</h2>
            <p>Sign in to shop everyday essentials and electronics at canteen prices, track your orders and manage your delivery addresses.</p>
            <ul class="perks">
                <li><i class="bi bi-patch-check"></i>Genuine products from authorised dealers</li>
                <li><i class="bi bi-lock"></i>Secure checkout and account protection</li>
                <li><i class="bi bi-truck"></i>Delivery to your cantonment address</li>
            </ul>
        </div>
        <div class="brand-foot">© <?= date('Y') ?> Army CSD Canteen. Authorised personnel only.</div>
    </aside>

    <!-- Form panel -->
    <main class="form-side">
        <div class="card-x">
            <h1>Log in</h1>
            <p class="sub">Use your registered phone number and password.</p>

            <?php if ($notice !== ''): ?>
                <div class="alert-soft info"><i class="bi bi-info-circle"></i><span><?= e($notice) ?></span></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert-soft err"><i class="bi bi-exclamation-circle"></i><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                <div class="mb-3">
                    <label for="ph_no" class="form-label">Phone number</label>
                    <div class="input-wrap">
                        <i class="bi bi-phone lead"></i>
                        <input type="tel" id="ph_no" name="ph_no" class="form-control"
                               inputmode="numeric" maxlength="10" placeholder="10-digit mobile number"
                               value="<?= e($phone) ?>" autocomplete="username" required <?= $locked ? 'disabled' : '' ?>>
                    </div>
                </div>

                <div class="mb-2">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock lead"></i>
                        <input type="password" id="password" name="password" class="form-control has-toggle"
                               placeholder="Your password" minlength="6" maxlength="200"
                               autocomplete="current-password" required <?= $locked ? 'disabled' : '' ?>>
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="row-x">
                    <span></span>
                    <a href="forgot_password.php">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login" id="loginBtn" <?= $locked ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-in-right"></i>Log in
                </button>
            </form>

            <div class="divider">New to the canteen?</div>
            <div class="register">
                Don't have an account? <a href="register.php">Create one</a>
            </div>

            <div class="foot-x">Accounts are activated after admin approval.</div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Show or hide password
    const pw = document.getElementById('password');
    const toggle = document.getElementById('togglePw');
    if (pw && toggle) {
        toggle.addEventListener('click', function () {
            const show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            this.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    }

    // Keep phone to digits only
    const phone = document.getElementById('ph_no');
    phone.addEventListener('input', () => phone.value = phone.value.replace(/\D/g, '').slice(0, 10));

    // Submit guard
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        if (!/^\d{10}$/.test(phone.value)) {
            e.preventDefault();
            alert('Please enter your 10-digit phone number.');
            return;
        }
        const btn = document.getElementById('loginBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Logging in…';
    });
</script>
</body>
</html>