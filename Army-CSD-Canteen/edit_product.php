<?php
include 'config.php';

if (!isset($_GET['id'])) {
    die("Product ID missing");
}

$id = intval($_GET['id']);

// Fetch product
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Product not found.");
}

// Fetch categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// On form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category'];
    $new_category = trim($_POST['new_category']);

    if (!empty($new_category)) {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $new_category);
        $stmt->execute();
        $category_id = $conn->insert_id;
    }

    $image = $product['image'];
    if ($_FILES['image']['name']) {
        $image = time() . '_' . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image);
    }

    $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, image = ?, category_id = ? WHERE id = ?");
    $stmt->bind_param("ssdssi", $name, $desc, $price, $image, $category_id, $id);
    $stmt->execute();

    header("Location: admin_panel.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product</title>
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
            <h4>Edit Product</h4>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">

                <div class="mb-3">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($product['name']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control"><?= htmlspecialchars($product['description']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Price (₹)</label>
                    <input type="number" name="price" class="form-control" step="0.01" required value="<?= $product['price'] ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Current Image</label><br>
                    <?php if ($product['image']): ?>
                        <img src="uploads/<?= $product['image'] ?>" width="100" class="img-thumbnail">
                    <?php else: ?>
                        <p>No image</p>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Change Image</label>
                    <input type="file" name="image" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Select Category</label>
                    <select name="category" class="form-select">
                        <option value="">-- Select --</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $product['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Or enter new category</label>
                    <input type="text" name="new_category" class="form-control" placeholder="e.g., Stationery">
                </div>

                <button type="submit" class="btn btn-success">Update Product</button>
                <a href="admin_panel.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
