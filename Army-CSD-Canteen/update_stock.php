<?php
session_start();
include 'config.php';

// Optional: Check if admin is logged in (add your own session check logic)
// if (!isset($_SESSION['admin'])) {
//     header("Location: adminlog.php");
//     exit();
// }

// Handle stock update form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    $new_stock = intval($_POST['new_stock']);

    $query = "UPDATE products SET stock = $new_stock WHERE id = $product_id";
    mysqli_query($conn, $query);
}

// Fetch all products
$result = mysqli_query($conn, "SELECT id, name, stock FROM products");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Stock - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style2.css" rel="stylesheet">
    <style>
        body {
            background: #181a20;
        }
         .table th, .table td {
            vertical-align: middle !important;
        }
        .table {
            background: #23272f;
            color: #fff;
        }
        .table th {
            background: #198754 !important;
            color: #fff !important;
        }
        .table td {
            background: #23272f !important;
            color: #fff !important;
        }
        form { display: inline-block; }
        h2 { text-align: center; 
        color: #fff;}
        input[type="number"] { width: 60px; }
    </style>
</head>
<body>

<!-- header -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <span class="h1 mb-0" style="font-size:2rem;">ARMY CSD CANTEEN</span>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <a class="nav-link mb-2" href="admin_panel.php">Admin Panel</a>
                                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- table -->

<div class="col-md-7 py-4 mx-auto">
            <div class="admin-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">Update Stock</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover shadow-sm">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Current Stock</th>
                                <th>New Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?= $row['id']; ?></td>
            <td><?= htmlspecialchars($row['name']); ?></td>
            <td><?= $row['stock']; ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="product_id" value="<?= $row['id']; ?>">
                    <input type="number" name="new_stock" min="0" required>
                    <button type="submit">Update</button>
                </form>
            </td>
        </tr>
        <?php } ?>
                        </tbody>
                    </table>
                </div>
                
            </div>


    
</body>
</html>
