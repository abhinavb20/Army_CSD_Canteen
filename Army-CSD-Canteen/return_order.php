<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle return request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['reason'])) {
    $order_id = $_POST['order_id'];
    $reason = trim($_POST['reason']);

    $stmt = $conn->prepare("UPDATE orders SET return_requested = 1, return_reason = ?, return_status = 'Pending' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $reason, $order_id, $_SESSION['user_id']);
    $stmt->execute();

    header("Location: my_orders.php");
    exit;
}

// Handle accept/decline by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    $action = $_POST['action'];

    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE orders SET return_status = 'Approved' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
    } elseif ($action === 'decline') {
        $stmt = $conn->prepare("UPDATE orders SET return_status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
    }
    header("Location: orders_admin.php");
    exit;
}

// Show the return form if GET request
$order_id = $_GET['order_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Return</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2>Request Return for Order #<?= htmlspecialchars($order_id) ?></h2>
    <form method="POST" class="card p-4 bg-white shadow-sm mt-4">
        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
        <div class="mb-3">
            <label for="reason" class="form-label">Reason for Return</label>
            <textarea name="reason" id="reason" class="form-control" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn btn-danger">Submit Return Request</button>
        <a href="my_orders.php" class="btn btn-secondary ms-2">Cancel</a>
    </form>

    <!-- Admin Accept/Decline Buttons (example, show only if admin and order is pending) -->
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && $order_id): ?>
        <form method="POST" class="mt-4 d-flex gap-2">
            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
            <button type="submit" name="action" value="accept" class="btn btn-success">Accept Return</button>
            <button type="submit" name="action" value="decline" class="btn btn-danger">Decline Return</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
