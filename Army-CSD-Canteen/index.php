<?php
session_start();
require_once 'config.php';
include 'header.php';

$isLoggedIn = isset($_SESSION['user_id']);

// Handle search/filter
$search = $_GET['search'] ?? '';
$filter_sql = '';
$params = [];
$types = '';

if (!empty($search)) {
    $filter_sql = "WHERE name LIKE ? OR description LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types = 'ss';
}

$sql = "SELECT * FROM products WHERE is_active = 1 $filter_sql ORDER BY id DESC";
$stmt = $conn->prepare($sql);

if (!empty($filter_sql)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ARMY CSD CANTEEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style2.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f9f9f9;
        }
        .navbar-custom {
            background-color: #10451d;
        }
        .navbar-custom .nav-link,
        .navbar-custom .navbar-brand {
            color: #fff !important;
            font-weight: bold;
        }
        .btn-cart {
            background-color:rgb(204, 255, 0);
            color: black;
        }
        .category-card {
            transition: transform 0.2s;
        }
        .category-card:hover {
            transform: scale(1.05);
        }
        .h1 {
            font-size: 2rem;
            color:#fff;
            font-weight: bold;
            text-decoration: none;
        }
        .description {
    font-size: 14px;
    line-height: 1.5;
}
.read-more {
    color: #2c3e50;
    font-weight: bold;
    cursor: pointer;
    text-decoration: underline;
    margin-left: 5px;
}
.product-grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    padding: 20px;
}
.product-card {
    width: 220px;
    min-height: 420px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 10px;
}
.product-card img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 5px;
}
.product-card h3 {
    font-size: 16px;
    margin: 10px 0 5px 0;
    height: 40px;
    overflow: hidden;
}
.product-card p {
    font-weight: bold;
    color: green;
    margin-top: 5px;
}
    </style>
</head>
<body>
<!-- Navbar -->
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
                
                <li class="nav-item">
                    <?php if (!$isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                <?php endif; ?>
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

<marquee behavior="scroll" direction="left" style="background-color: #004d00; color: white; padding: 10px; font-size: 18px;">
🎖️ Welcome to the Army CSD Canteen Online Portal! Enjoy exclusive discounts on electronics, apparel, and more. Shop now and save big! 🎖️
</marquee>

<!-- Search Box -->
<div class="container my-4">
    <form class="row justify-content-center" method="get" action="index.php">
        <div class="col-md-6">
            <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-success w-100">Search</button>
        </div>
    </form>
</div>

<!-- Product Grid -->
<div class="container my-5">
    <h3 class="mb-4 text-center">Available Products</h3>
    <div class="product-grid">
<?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
        <div class="product-card">
            <?php if ($row['image']): ?>
                <img src="uploads/<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
            <?php else: ?>
                <div class="bg-secondary text-white text-center p-5">No Image</div>
            <?php endif; ?>
            <h3><?= htmlspecialchars($row['name']) ?></h3>
            <div class="description" id="desc-<?= $row['id'] ?>">
                <?php
                    $fullDesc = $row['description'];
                    $shortDesc = implode(' ', array_slice(explode(' ', $fullDesc), 0, 25));
                ?>
                <span class="short-text"><?= htmlspecialchars($shortDesc) ?><?php if (str_word_count($fullDesc) > 25) echo '...'; ?></span>
                <?php if (str_word_count($fullDesc) > 25): ?>
                    <span class="full-text" style="display: none;"><?= htmlspecialchars($fullDesc) ?></span>
                    <a href="javascript:void(0);" class="read-more" onclick="toggleDesc(<?= $row['id'] ?>)">Read more</a>
                <?php endif; ?>
            </div>
            <p>₹<?= number_format($row['price'], 2) ?></p>
            <a href="product_details.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm mt-3 w-100">View Details</a>
            <?php if ($row['stock'] > 0): ?>
                <form method="post" action="add_to_cart.php" class="mt-2">
                    <div class="d-flex gap-2">
                        <input type="hidden" name="product_id" value="<?= $row['id']; ?>">
                        <input type="number" name="quantity" value="1" min="1" max="<?= $row['stock']; ?>" class="form-control" style="max-width:80px;">
                        <button type="submit" class="btn btn-success btn-sm flex-fill" style="background-color:#10451d;">Add to Cart</button>
                    </div>
                </form>
            <?php else: ?>
                <p style="color: red; font-weight: bold;">Out of Stock</p>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="col-12">
        <div class="alert alert-warning text-center">No products available at the moment.</div>
    </div>
<?php endif; ?>
</div>
</div>

<!-- Footer (optional) -->
<footer class="text-center py-3 mt-4 bg-light border-top">
    <small>© <?php echo date('Y'); ?> ARMY CSD CANTEEN. All rights reserved.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleDesc(id) {
    const desc = document.getElementById('desc-' + id);
    const shortText = desc.querySelector('.short-text');
    const fullText = desc.querySelector('.full-text');
    const link = desc.querySelector('.read-more');

    if (fullText.style.display === "none" || fullText.style.display === "") {
        fullText.style.display = "inline";
        shortText.style.display = "none";
        link.textContent = "Show less";
    } else {
        fullText.style.display = "none";
        shortText.style.display = "inline";
        link.textContent = "Read more";
    }
}
</script>
<!-- alert for add_to_cart -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {
    $("form[action='add_to_cart.php']").submit(function(e) {
        e.preventDefault();
        var form = $(this);
        $.post("add_to_cart.php", form.serialize(), function(response) {
            if (response.trim() === "success") {
                alert("Product added to cart!");
            } else {
                alert(response);
            }
        });
    });
});
</script>
</body>
</html>