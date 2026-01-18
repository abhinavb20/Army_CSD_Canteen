<?php
session_start();
include 'config.php';

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Addresses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style2.css" rel="stylesheet">
    <style>
        body {
            background-color: #f9f9f9;
        }
        .address-card {
            border-radius: 10px;
            border: 1px solid #ddd;
            transition: box-shadow 0.3s ease;
        }
        .address-card:hover {
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .btn-sm {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <a class="h1" href="#">ARMY CSD CANTEEN</a>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="btn btn-danger" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">My Addresses</h3>
        <a href="add_address.php" class="btn btn-success">+ Add New Address</a>
    </div>

    <div class="row">
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card address-card p-3 h-100">
                <h5 class="mb-2"><?= htmlspecialchars($row['name']) ?> <span class="text-muted">(<?= htmlspecialchars($row['address_type']) ?>)</span></h5>
                <p class="mb-2">
                    <?= htmlspecialchars($row['address_line']) ?>, <?= htmlspecialchars($row['locality']) ?><br>
                    <?= htmlspecialchars($row['city']) ?>, <?= htmlspecialchars($row['state']) ?> - <?= htmlspecialchars($row['pincode']) ?><br>
                    📞 <?= htmlspecialchars($row['phone']) ?>
                    <?php if ($row['alt_phone']): ?>, Alt: <?= htmlspecialchars($row['alt_phone']) ?><?php endif; ?><br>
                    🗺️ Landmark: <?= htmlspecialchars($row['landmark']) ?>
                </p>
                <div>
                    <a href="edit_address.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    <a href="delete_address.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this address?')">Delete</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
