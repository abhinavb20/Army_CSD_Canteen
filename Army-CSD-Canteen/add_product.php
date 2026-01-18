<?php
include 'config.php';

// Handle product submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];
    $stock = intval($_POST['stock']); // Get stock value
    $new_category = trim($_POST['new_category']);
    $category_id = $_POST['category'];

    // Handle category logic
    if (!empty($new_category)) {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $new_category);
        $stmt->execute();
        $category_id = $conn->insert_id;
    }

    // Handle image upload
    $image = '';
    if ($_FILES['image']['name']) {
        $image = time() . '_' . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image);
    }

    // Add stock to insert query
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, image, category_id, stock) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsii", $name, $desc, $price, $image, $category_id, $stock);
    $stmt->execute();

    header("Location: admin_panel.php");
    exit;
}

// Fetch existing categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style2.css" rel="stylesheet">
</head>
<body class="bg-light">
<!-- header -->
  <nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <a class="h1" href="#">ARMY CSD CANTEEN</a>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h3>Add New Product</h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label>Name:</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Description:</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
                <div class="mb-3">
                    <label>Price:</label>
                    <input type="number" name="price" step="0.01" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Stock:</label>
                    <input type="number" name="stock" min="0" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Image:</label>
                    <input type="file" name="image" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Category:</label>
                    <select name="category" class="form-select">
                        <option value="">--Select--</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Or enter new category:</label>
                    <input type="text" name="new_category" class="form-control" placeholder="e.g., Electronics">
                </div>
                <button type="submit" class="btn btn-success">Add Product</button>
                <a href="admin_panel.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>