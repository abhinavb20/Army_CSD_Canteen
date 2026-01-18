<?php
session_start();
include 'config.php';
// include 'header.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = "";

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $name = trim($_POST['name']);
    $ph_no = trim($_POST['ph_no']);
    // $address = trim($_POST['address']);

    $stmt = $conn->prepare("UPDATE users SET name=?, ph_no=?, address=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $ph_no, $address, $user_id);
    $stmt->execute();

    $success = "✅ Profile updated successfully.";
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    session_destroy();
    header("Location: register.php?msg=Account deleted");
    exit;
}

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style2.css" rel="stylesheet">
</head>
<body >
        <nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <a class="h1" href="#">ARMY CSD CANTEEN</a>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
                <li class="nav-item"><a class="btn btn-danger" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>



<div class="container mt-5">
    <h2>👤 My Profile</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-4 bg-white shadow-sm">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="ph_no" class="form-control" value="<?= htmlspecialchars($user['ph_no']) ?>" required>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 d-grid">
                <a href="manage_addresses.php" class="btn btn-outline-primary">Manage Addresses</a>
            </div>
            <div class="col-md-6 d-grid">
                <a href="my_orders.php" class="btn btn-info text-white">📦 My Orders</a>
            </div>
            <div class="col-md-6 d-grid">
                <button type="submit" name="update" class="btn btn-success">
                    💾 Update
                </button>
            </div>
            <div class="col-md-6 d-grid">
                <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('Are you sure?')">
                    🗑️ Delete Account
                </button>
            </div>
        </div>

    </form>
</div>

</body>
</html>
