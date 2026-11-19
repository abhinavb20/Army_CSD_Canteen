<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(16));
}
$profileCsrf = $_SESSION['profile_csrf'];
$message = '';
$error   = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $name  = trim($_POST['name'] ?? '');
    $ph_no = trim($_POST['ph_no'] ?? '');

    if ($name === '' || $ph_no === '') {
        $error = 'Name and phone number are required.';
    } elseif (!preg_match('/^[0-9+\-\s]{7,15}$/', $ph_no)) {
        $error = 'Please enter a valid phone number.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, ph_no = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $ph_no, $user_id);
        if ($stmt->execute()) {
            $message = 'Profile updated successfully.';
        } else {
            $error = 'Could not update your profile. Please try again.';
        }
        $stmt->close();
    }
}

// Handle account deletion (requires typing DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (trim($_POST['confirm_text'] ?? '') === 'DELETE') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION = [];
        session_destroy();
        header("Location: register.php?msg=" . urlencode("Account deleted"));
        exit;
    }
    $error = 'Type DELETE in the box to confirm account deletion.';
}

// Fetch user
$stmt = $conn->prepare("SELECT name, ph_no FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$initial = strtoupper(mb_substr($user['name'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Army CSD Canteen</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green-900: #103d25;
            --green-700: #17623b;
            --green-100: #eaf3ec;
            --accent: #c8e49b;
            --ink: #18251d;
            --muted: #6b7a70;
            --line: #e4ebe5;
            --bg: #f4f6f4;
            --danger: #b42318;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        .page-hero {
            background: linear-gradient(120deg, var(--green-900), var(--green-700));
            color: #fff;
            padding: 40px 20px 80px;
        }

        .page-hero-inner {
            max-width: 960px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--accent);
            color: var(--green-900);
            font-size: 26px;
            font-weight: 800;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .page-hero h1 {
            font-size: 24px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -.3px;
        }

        .page-hero p {
            margin: 4px 0 0;
            color: #d3e6d9;
            font-size: 13px;
        }

        .wrap {
            max-width: 960px;
            margin: -50px auto 60px;
            padding: 0 20px;
        }

        .panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 26px;
            box-shadow: 0 10px 30px rgba(16, 61, 37, .07);
            margin-bottom: 20px;
        }

        .panel h2 {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .panel .sub {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .form-control {
            border-radius: 8px;
            border-color: var(--line);
            padding: 10px 12px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--green-700);
            box-shadow: 0 0 0 .2rem rgba(23, 98, 59, .15);
        }

        .form-control[readonly] {
            background: #f7f9f7;
            color: var(--muted);
        }

        .btn-save {
            background: var(--green-900);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-save:hover {
            background: var(--green-700);
            color: #fff;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--ink);
            text-decoration: none;
            font-weight: 600;
            transition: .2s;
        }

        .quick-link:hover {
            border-color: var(--green-700);
            background: var(--green-100);
            color: var(--green-900);
        }

        .quick-link i {
            font-size: 18px;
            color: var(--green-700);
        }

        .quick-link small {
            display: block;
            font-weight: 400;
            color: var(--muted);
            font-size: 12px;
        }

        .danger-zone {
            border: 1px solid #f1c9c4;
            background: #fff;
        }

        .danger-zone h2 {
            color: var(--danger);
        }

        .btn-delete {
            background: var(--danger);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-delete:hover {
            background: #8f1c13;
            color: #fff;
        }

        .alert {
            border-radius: 10px;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            .quick-links {
                grid-template-columns: 1fr;
            }

            .page-hero {
                padding: 30px 20px 70px;
            }

            .wrap {
                margin-top: -40px;
            }
        }

        .page-hero-inner {
            flex-wrap: wrap;
        }

        .btn-logout {
            background: rgba(255, 255, 255, .12);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .35);
            border-radius: 9px;
            padding: 9px 16px;
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
        }

        .btn-logout:hover {
            background: #fff;
            color: var(--green-900);
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <header class="page-hero">
        <div class="page-hero-inner">
            <div class="avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="flex-grow-1">
                <h1><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Manage your personal details, addresses and orders</p>
            </div>
            <form method="POST" action="logout.php" class="m-0">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($profileCsrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-logout">
                    <i class="bi bi-box-arrow-right me-1"></i>Log out
                </button>
            </form>
        </div>
    </header>

    <main class="wrap">

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="panel">
            <h2>Personal details</h2>
            <p class="sub">Update your name and phone number.</p>

            <form method="POST" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Full name</label>
                        <input type="text" id="name" name="name" class="form-control"
                            value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ph_no">Phone number</label>
                        <input type="tel" id="ph_no" name="ph_no" class="form-control"
                            value="<?= htmlspecialchars($user['ph_no'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="15">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" name="update" value="1" class="btn-save">
                        <i class="bi bi-save me-1"></i>Save changes
                    </button>
                </div>
            </form>
        </section>

        <section class="panel">
            <h2>Quick links</h2>
            <p class="sub">Manage your account activity.</p>
            <div class="quick-links">
                <a href="manage_addresses.php" class="quick-link">
                    <i class="bi bi-geo-alt"></i>
                    <div>Manage addresses<small>Delivery locations</small></div>
                </a>
                <a href="my_orders.php" class="quick-link">
                    <i class="bi bi-box-seam"></i>
                    <div>My orders<small>Track and review purchases</small></div>
                </a>
            </div>
        </section>

        <section class="panel danger-zone">
            <h2>Delete account</h2>
            <p class="sub">This permanently removes your account and cannot be undone. Type <strong>DELETE</strong> below to confirm.</p>

            <form method="POST" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="text" name="confirm_text" class="form-control" placeholder="Type DELETE to confirm" autocomplete="off">
                </div>
                <div class="col-md-auto">
                    <button type="submit" name="delete" value="1" class="btn-delete">
                        <i class="bi bi-trash me-1"></i>Delete my account
                    </button>
                </div>
            </form>
        </section>

    </main>

    <footer class="text-center text-muted small pb-4">
        © <?= date('Y') ?> Army CSD Canteen. All rights reserved.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>