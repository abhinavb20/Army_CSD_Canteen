<?php
include 'config.php';
session_start();
include 'header.php';

if (!isset($_GET['id'])) {
    die("Product ID not specified.");
}

$id = intval($_GET['id']);
$result = $conn->query("SELECT * FROM products WHERE id = $id");

if ($result->num_rows === 0) {
    die("Product not found.");
}

$product = $result->fetch_assoc();
$isLoggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['name']) ?> | Product Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style2.css" rel="stylesheet">
</head>
<body>
<!-- header -->
    <!-- <nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="icons/logo.png" alt="Logo" width="40" height="40" class="me-2">
            <a class="h1" href="#">ARMY CSD CANTEEN</a>
        </a>
        <div class="collapse navbar-collapse justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                <?php endif; ?>
                
                
                <li class="nav-item">
                    <a class="btn btn-cart ms-2" href="cart.php">
                        🛒 Cart
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="profile.php">👤 My Profile</a>
                            </li>
                            
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav> -->

<div class="container mt-5">
    
    <div class="row">
        <div class="col-md-6">
            <img src="uploads/<?= htmlspecialchars($product['image']) ?>" class="img-fluid border rounded shadow-sm">
            
        </div>
        <div class="col-md-6">
            <h2><?= htmlspecialchars($product['name']) ?></h2>
            <p class="text-muted"><?= htmlspecialchars($product['description']) ?></p>
            <h4 class="text-success">₹<?= number_format($product['price'], 2) ?></h4>

            

            <?php if ($product['stock'] > 0): ?>
                <form method="post" action="add_to_cart.php" id="addToCartForm">
                    <input type="hidden" name="id" value="<?= $product['id']; ?>">
                    <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                    <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock']; ?>">
                    <button type="submit" class="btn btn-success" id="addToCartBtn">Add to Cart</button>
                </form>
                <a href="cart.php" class="btn btn-primary mt-2 d-none" id="goToCartBtn">Go to Cart</a>
            <?php else: ?>
                <div class="alert alert-danger" role="alert">
                    This product is currently out of stock.
                </div>
            <?php endif; ?>

            <!-- Optionally keep AJAX and Buy Now buttons for logged in users if you want both options -->
            <?php if ($isLoggedIn): ?>
                <!-- Buy Now -->
                <a href="checkout.php?id=<?= $product['id'] ?>" class="btn btn-warning mt-3">⚡ Buy Now</a>
            <?php else: ?>
                <!-- Not Logged In -->
                <a href="login.php?msg=login_required" class="btn btn-success mt-3">🛒 Add to Cart</a>
                <a href="login.php?msg=login_required" class="btn btn-warning mt-3">⚡ Buy Now</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Only load AJAX script if logged in -->
<?php if ($isLoggedIn): ?>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#addToCartForm').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        $.ajax({
            url: 'add_to_cart.php',
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                $('#addToCartBtn').addClass('d-none');
                $('#goToCartBtn').removeClass('d-none');
            },
            error: function() {
                alert("Something went wrong. Please try again.");
            }
        });
    });
});
</script>
<?php endif; ?>

<!-- Reviews Section -->
<h4>Customer Reviews</h4>
<?php
$stmt = $conn->prepare("SELECT r.*, u.name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$reviews = $stmt->get_result();

while ($row = $reviews->fetch_assoc()):
?>
    <p><strong><?= htmlspecialchars($row['name']) ?></strong> ⭐ <?= $row['rating'] ?>/5</p>
    <p><?= htmlspecialchars($row['review']) ?></p>
<?php endwhile; ?>

<!-- Add Review -->
<?php if (isset($_SESSION['user_id'])): ?>
<form method="POST" action="add_review.php">
    <input type="hidden" name="product_id" value="<?= $product_id ?>">
    <label>Rating:</label>
    <select name="rating" required>
        <?php for ($i = 1; $i <= 5; $i++) echo "<option value='$i'>$i</option>"; ?>
    </select>
    <textarea name="review" placeholder="Write your review" required></textarea>
    <button type="submit" class="btn btn-sm btn-success">Submit</button>
</form>
<?php else: ?>
<p><a href="login.php">Login</a> to write a review.</p>
<?php endif; ?>


</body>
</html>
