<?php
session_start();
include 'config.php';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Already logged in: no need to register
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (empty($_SESSION['register_csrf'])) {
    $_SESSION['register_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['register_csrf'];

$errors = [];
$old    = ['name' => '', 'phone' => '', 'army_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $errors[] = 'Your session has expired. Please try again.';
    }

    // 2. Read input
    $name    = trim((string)($_POST['name'] ?? ''));
    $phone   = trim((string)($_POST['phone'] ?? ''));
    $armyId  = strtoupper(trim((string)($_POST['army_id'] ?? '')));
    $pass    = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $old = ['name' => $name, 'phone' => $phone, 'army_id' => $armyId];

    // 3. Validate on the server
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Please enter your full name (2 to 100 characters).';
    }
    if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
        $errors[] = 'Enter a valid 10-digit mobile number.';
    }
    if (!preg_match('/^[A-Z0-9\-\/]{4,20}$/', $armyId)) {
        $errors[] = 'Enter a valid Army ID (4 to 20 letters, digits, hyphens or slashes).';
    }
    if (strlen($pass) < 8 || strlen($pass) > 200) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $errors[] = 'Password must include at least one letter and one number.';
    }
    if ($pass !== $confirm) {
        $errors[] = 'The passwords do not match.';
    }

    // 4. Save
    if (empty($errors)) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare(
                "INSERT INTO users (name, ph_no, army_id, password, is_approved, is_active, role)
                 VALUES (?, ?, ?, ?, 0, 1, 'user')"
            );
            $stmt->bind_param("ssss", $name, $phone, $armyId, $hash);
            $stmt->execute();
            $stmt->close();

            // New token and clear the form after a successful sign-up
            unset($_SESSION['register_csrf']);
            header("Location: wait_approval.php");
            exit;

        } catch (mysqli_sql_exception $ex) {
            // 1062 = duplicate phone or Army ID (unique keys)
            if ($ex->getCode() === 1062) {
                $errors[] = 'This phone number or Army ID is already registered.';
            } else {
                error_log('Register failed: ' . $ex->getMessage());
                $errors[] = 'We could not create your account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account | Army CSD Canteen</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --green-900:#103d25; --green-700:#17623b; --green-100:#eaf3ec; --accent:#c8e49b; --ink:#18251d; --muted:#6b7a70; --line:#e4ebe5; --bg:#f4f6f4; --danger:#b42318; }
        * { box-sizing:border-box; }
        body { font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); font-size:14px; margin:0; -webkit-font-smoothing:antialiased; }

        .shell { min-height:100vh; display:grid; grid-template-columns:1fr 1.1fr; }

        .brand-side { background:linear-gradient(140deg,var(--green-900),var(--green-700)); color:#fff; padding:56px; display:flex; flex-direction:column; justify-content:space-between; position:relative; overflow:hidden; }
        .brand-side::after { content:''; position:absolute; right:-120px; bottom:-120px; width:360px; height:360px; border-radius:50%; background:rgba(200,228,155,.08); }
        .logo { display:flex; align-items:center; gap:12px; font-weight:700; font-size:16px; position:relative; z-index:1; }
        .logo-mark { width:42px; height:42px; border-radius:11px; border:1.5px solid var(--accent); color:var(--accent); display:grid; place-items:center; font-size:12px; font-weight:800; }
        .logo small { display:block; font-size:10.5px; font-weight:500; letter-spacing:1.4px; text-transform:uppercase; color:#a9c7b2; }
        .brand-copy { position:relative; z-index:1; max-width:420px; }
        .brand-copy h2 { font-size:34px; font-weight:800; letter-spacing:-.6px; line-height:1.15; margin:0 0 14px; }
        .brand-copy p { color:#d3e6d9; font-size:15px; line-height:1.6; margin:0 0 26px; }
        .steps { list-style:none; padding:0; margin:0; display:grid; gap:14px; }
        .steps li { display:flex; gap:12px; align-items:flex-start; font-size:13.5px; color:#e6f1ea; }
        .steps .n { width:26px; height:26px; border-radius:50%; background:rgba(200,228,155,.15); color:var(--accent); display:grid; place-items:center; font-weight:700; font-size:12px; flex-shrink:0; }
        .brand-foot { font-size:12px; color:#9dbba8; position:relative; z-index:1; }

        .form-side { display:flex; align-items:center; justify-content:center; padding:40px 24px; }
        .card-x { width:100%; max-width:480px; }
        .card-x h1 { font-size:26px; font-weight:800; letter-spacing:-.4px; margin:0 0 6px; }
        .card-x .sub { color:var(--muted); margin-bottom:22px; }

        .form-label { font-size:13px; font-weight:600; margin-bottom:6px; }
        .input-wrap { position:relative; }
        .input-wrap > i.lead { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:16px; pointer-events:none; }
        .input-wrap .form-control { padding-left:40px; height:46px; border-radius:10px; border-color:var(--line); font-size:14.5px; }
        .input-wrap .form-control.has-toggle { padding-right:44px; }
        .form-control:focus { border-color:var(--green-700); box-shadow:0 0 0 .2rem rgba(23,98,59,.15); }
        .toggle-pw { position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; color:var(--muted); width:34px; height:34px; border-radius:8px; display:grid; place-items:center; }
        .toggle-pw:hover { background:var(--bg); color:var(--ink); }
        .hint { font-size:12px; color:var(--muted); margin-top:5px; }

        .strength { height:5px; border-radius:5px; background:#e3e9e4; margin-top:8px; overflow:hidden; }
        .strength i { display:block; height:100%; width:0; border-radius:5px; transition:width .25s, background .25s; }
        .match { font-size:12px; margin-top:5px; min-height:16px; }
        .match.ok { color:var(--green-700); }
        .match.bad { color:var(--danger); }

        .alert-soft { border-radius:10px; font-size:13.5px; padding:11px 14px; margin-bottom:18px; background:#fde8e6; color:var(--danger); }
        .alert-soft ul { margin:0; padding-left:18px; }

        .btn-create { width:100%; height:48px; border:0; border-radius:11px; background:var(--green-900); color:#fff; font-weight:700; font-size:15px; display:inline-flex; align-items:center; justify-content:center; gap:8px; margin-top:6px; }
        .btn-create:hover { background:var(--green-700); }
        .btn-create:disabled { background:#c9d3cc; }

        .register-foot { margin-top:22px; text-align:center; font-size:13.5px; color:var(--muted); }
        .register-foot a { color:var(--green-700); font-weight:700; text-decoration:none; }
        .register-foot a:hover { text-decoration:underline; }
        .note { font-size:12px; color:var(--muted); text-align:center; margin-top:18px; }

        @media (max-width:900px) {
            .shell { grid-template-columns:1fr; }
            .brand-side { display:none; }
        }
    </style>
</head>
<body>
<div class="shell">

    <aside class="brand-side">
        <div class="logo">
            <div class="logo-mark">CSD</div>
            <div>Army CSD Canteen<small>Online store</small></div>
        </div>
        <div class="brand-copy">
            <h2>Create your account.</h2>
            <p>Register once to shop at canteen prices. Your account is activated after a quick check by the canteen office.</p>
            <ol class="steps">
                <li><span class="n">1</span>Fill in your details with your Army ID.</li>
                <li><span class="n">2</span>The canteen office reviews your request.</li>
                <li><span class="n">3</span>Log in once approved and start shopping.</li>
            </ol>
        </div>
        <div class="brand-foot">© <?= date('Y') ?> Army CSD Canteen. Authorised personnel only.</div>
    </aside>

    <main class="form-side">
        <div class="card-x">
            <h1>Create account</h1>
            <p class="sub">Enter your details exactly as they appear on your Army ID.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert-soft">
                    <strong><i class="bi bi-exclamation-circle me-1"></i>Please fix the following:</strong>
                    <ul class="mt-1">
                        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                <div class="mb-3">
                    <label for="name" class="form-label">Full name</label>
                    <div class="input-wrap">
                        <i class="bi bi-person lead"></i>
                        <input type="text" id="name" name="name" class="form-control" maxlength="100"
                               placeholder="As on your Army ID" value="<?= e($old['name']) ?>" required>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Mobile number</label>
                        <div class="input-wrap">
                            <i class="bi bi-phone lead"></i>
                            <input type="tel" id="phone" name="phone" class="form-control" maxlength="10"
                                   inputmode="numeric" placeholder="10-digit number" value="<?= e($old['phone']) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="army_id" class="form-label">Army ID</label>
                        <div class="input-wrap">
                            <i class="bi bi-shield-check lead"></i>
                            <input type="text" id="army_id" name="army_id" class="form-control" maxlength="20"
                                   placeholder="e.g. IC-12345" value="<?= e($old['army_id']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock lead"></i>
                            <input type="password" id="password" name="password" class="form-control has-toggle"
                                   minlength="8" maxlength="200" placeholder="Min. 8 characters" required>
                            <button type="button" class="toggle-pw" data-target="password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="strength"><i id="strengthBar"></i></div>
                        <div class="hint">Use letters and numbers.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Confirm password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill lead"></i>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control has-toggle"
                                   minlength="8" maxlength="200" placeholder="Re-enter password" required>
                            <button type="button" class="toggle-pw" data-target="confirm_password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="match" id="matchMsg"></div>
                    </div>
                </div>

                <button type="submit" class="btn-create" id="createBtn">
                    <i class="bi bi-person-plus"></i>Create account
                </button>
            </form>

            <div class="register-foot">
                Already registered? <a href="login.php">Log in</a>
            </div>
            <div class="note">Your account will be activated after admin approval.</div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const form    = document.getElementById('registerForm');
    const phone   = document.getElementById('phone');
    const armyId  = document.getElementById('army_id');
    const pass    = document.getElementById('password');
    const confirm = document.getElementById('confirm_password');
    const bar     = document.getElementById('strengthBar');
    const match   = document.getElementById('matchMsg');

    // Digits only for the phone number
    phone.addEventListener('input', () => phone.value = phone.value.replace(/\D/g, '').slice(0, 10));

    // Army ID in capitals
    armyId.addEventListener('input', () => armyId.value = armyId.value.toUpperCase());

    // Password strength
    function strength(p) {
        let s = 0;
        if (p.length >= 8) s++;
        if (/[A-Za-z]/.test(p) && /\d/.test(p)) s++;
        if (/[^A-Za-z0-9]/.test(p) || p.length >= 12) s++;
        return s;
    }
    pass.addEventListener('input', () => {
        const s = strength(pass.value);
        const colors = ['#e3e9e4', '#d9534f', '#e0a84a', '#17623b'];
        bar.style.width = (s / 3 * 100) + '%';
        bar.style.background = colors[s];
        checkMatch();
    });

    // Password match message
    function checkMatch() {
        if (!confirm.value) { match.textContent = ''; match.className = 'match'; return; }
        if (pass.value === confirm.value) {
            match.textContent = 'Passwords match';
            match.className = 'match ok';
        } else {
            match.textContent = 'Passwords do not match';
            match.className = 'match bad';
        }
    }
    confirm.addEventListener('input', checkMatch);

    // Show or hide password fields
    document.querySelectorAll('.toggle-pw').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            this.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
    });

    // Validate before sending
    form.addEventListener('submit', function (e) {
        if (!/^[6-9]\d{9}$/.test(phone.value)) {
            e.preventDefault();
            alert('Enter a valid 10-digit mobile number.');
            return;
        }
        if (pass.value !== confirm.value) {
            e.preventDefault();
            alert('The passwords do not match.');
            return;
        }
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }
        const btn = document.getElementById('createBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating account…';
    });
</script>
</body>
</html>